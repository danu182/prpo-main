<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\History;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestHistory;
use App\Models\PurchaseRequestItem;
use Illuminate\Support\Facades\Auth; // <--- INI YANG BENAR
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseRequestItemVendor;
use App\Models\Status;
use App\Models\VendorQuote;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\User;
use App\Notifications\DocumentApprovalNotification;
use PDF;

// use Barryvdh\DomPDF\Facade\Pdf;
// use Barryvdh\DomPDF\Facade\Pdf; // <--- TARUH DISINI (JANGAN DI DALAM CLASS)

use PDF; // Cukup ini saja

class PurchaseRequestController extends Controller
{

    use InteractsWithMedia; // <--- WAJIB ADA

    /**
     * Display a listing of the resource.
     */

    public function index(\Illuminate\Http\Request $request)
    {
        // 1. Tangkap Inputan Filter dari View
        $search = $request->input('search');
        $statusFilter = $request->input('status');
        $deptFilter = $request->input('department');

        // 2. Ambil data master untuk Dropdown Filter
        $statuses = \App\Models\Status::where('type', 'PR')->orderBy('sequence')->get();
        $companies = \App\Models\Company::all();

        // 3. Ambil data user yang sedang login
        $user = auth()->user();

        // 4. Query PR dengan Filter Server-Side (Eloquent)
        $requests = \App\Models\PurchaseRequest::with(['status', 'company', 'user', 'purchaseOrders'])
            // ==========================================================
            // 🛡️ PROTEKSI DATA: STAF HANYA LIHAT PR MILIK SENDIRI
            // ==========================================================
            ->when(!$user->hasAnyRole(['Super Admin', 'Purchasing', 'manager', 'direktur', 'Finance', 'Gudang']), function ($query) use ($user) {
                // Jika bukan orang pusat/bos, KUNCI hanya tampilkan PR milik dia sendiri!
                $query->where('user_id', $user->id);
            })
            // ==========================================================
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('pr_number', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($qUser) use ($search) {
                          $qUser->where('name', 'like', "%{$search}%");
                      });
                });
            })
            ->when($statusFilter, function ($query) use ($statusFilter) {
                $query->whereHas('status', function ($q) use ($statusFilter) {
                    $q->where('name', $statusFilter);
                });
            })
            ->when($deptFilter, function ($query) use ($deptFilter) {
                if ($deptFilter === 'Head Office') {
                    $query->whereNull('company_id');
                } else {
                    $query->whereHas('company', function ($q) use ($deptFilter) {
                        $q->where('name', $deptFilter);
                    });
                }
            })
            ->latest()
            ->paginate(10); // Otomatis Paginate 10 baris

        return view('pr.index', compact('requests', 'companies', 'statuses'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $items = \App\Models\Item::all(); // Master Barang
        $vendors = \App\Models\Vendor::all(); // Master Vendor (untuk referensi)
        $companies = \App\Models\Company::all();

        // AMBIL DATA CURRENCY
        $currencies = Currency::where('is_active', true)->get();

        return view('pr.create', compact('companies', 'items', 'vendors', 'currencies'));
    }


    // Method Generator Baru
    private function generatePrNumber($companyId)
    {
        // 1. Ambil Kode Company (Default 'HO' jika kosong)
        $company = \App\Models\Company::find($companyId);
        $code = $company && $company->code ? $company->code : 'HO';

        // 2. Buat Format Tanggal: YYYY/MM/DD
        $now = now();
        $dateStr = $now->format('Y/m/d'); // Hasil: 2026/02/06

        // 3. Susun Prefix (Awalan)
        // Format: PR/HO/2026/02/06/
        $prefix = "PR/{$code}/{$dateStr}/";

        // 4. Cari nomor urut terakhir di database BERDASARKAN PREFIX HARI INI
        // Kita cari PR yang nomornya diawali dengan $prefix
        $lastPr = PurchaseRequest::where('pr_number', 'like', $prefix . '%')
                    ->orderBy('id', 'desc') // Ambil yang paling baru dibuat
                    ->lockForUpdate() // Cegah duplikat jika submit barengan
                    ->first();

        if ($lastPr) {
            // Jika hari ini sudah ada PR, ambil 4 angka terakhir, lalu tambah 1
            // Contoh: PR/HO/2026/02/06/0005 -> ambil 0005 -> jadi 6
            $lastNumber = (int) substr($lastPr->pr_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            // Jika hari ini belum ada PR, mulai dari 1
            $newNumber = 1;
        }

        // 5. Gabungkan semua jadi string utuh dengan padding 4 digit (0001)
        return $prefix . sprintf('%04d', $newNumber);
    }

   

    public function store(Request $request)
    {
        // 1. Validasi
        $request->validate([
            'request_date' => 'required|date',
            'items'        => 'required|array',
            'company_id'   => 'required|exists:companies,id',
            // TAMBAHAN: Validasi Need Date (harus ada, berupa tanggal, dan minimal sama dengan request_date)
            'need_date'    => 'required|date|after_or_equal:request_date',
        ]);

        // 2. Transaction Start
        try {
            DB::transaction(function () use ($request) {

                // A. Buat Header PR
                $newPrNumber = $this->generatePrNumber($request->company_id);
                $initialStatus = Status::pr()->where('slug', 'pending_approval')->first();

                if (!$initialStatus) {
                    throw new \Exception('Master Status PR belum disetting (jalankan seeder).');
                }

                $pr = \App\Models\PurchaseRequest::create([
                    'pr_number'             => $newPrNumber,
                    // TAMBAHAN: Simpan Need Date ke Database
                    'need_date'    => $request->need_date,
                    'user_id'               => auth()->id(),
                    'company_id'            => $request->company_id,
                    'request_date'          => $request->request_date,
                    'description'           => $request->description,
                    'status_id'             => $initialStatus->id,
                    'current_approval_level'=> 0
                ]);

                // Persiapkan Nama Folder berdasarkan Nomor PR (Ganti / jadi - agar aman untuk OS)
                $folderName = str_replace(['/', '\\'], '-', $newPrNumber);
                $storagePath = "pr_uploads/" . $folderName;

                // B. Loop Items
                foreach ($request->items as $itemIndex => $itemData) {

                    // Simpan Item
                    $prItem = \App\Models\PurchaseRequestItem::create([
                        'purchase_request_id' => $pr->id,
                        'item_id'             => $itemData['item_id'],
                        'qty'                 => $itemData['qty'],
                        'estimated_price'     => 0
                    ]);

                    // C. Simpan Vendor (Penawaran)
                    if (isset($itemData['vendors']) && is_array($itemData['vendors'])) {

                        foreach ($itemData['vendors'] as $vendorIndex => $quoteData) {

                            if (!empty($quoteData['vendor_id'])) {

                                $attachmentPath = null;

                                // D. LOGIKA UPLOAD FILE MANUAL (Per Vendor)
                                // Cek apakah ada file yang diunggah untuk vendor ini
                                if ($request->hasFile("items.$itemIndex.vendors.$vendorIndex.file")) {
                                    $file = $request->file("items.$itemIndex.vendors.$vendorIndex.file");

                                    // Beri nama file yang unik: itemID_vendorID_timestamp.ext
                                    $fileName = "item_{$itemData['item_id']}_vendor_{$quoteData['vendor_id']}_" . time() . "." . $file->getClientOriginalExtension();

                                    // Simpan ke storage/app/public/pr_uploads/PR-NOMOR-DATA/nama-file.pdf
                                    $attachmentPath = $file->storeAs($storagePath, $fileName, 'public');
                                }

                                // INSERT KE TABEL pr_item_vendors
                                DB::table('pr_item_vendors')->insert([
                                $vendorQuote = PurchaseRequestItemVendor::create([
                                    'pr_item_id'     => $prItem->id,
                                    'vendor_id'      => $quoteData['vendor_id'],
                                    'currency'       => $quoteData['currency'] ?? 'IDR',
                                    'quoted_price'   => $quoteData['price'] ?? 0,
                                    'reference_link' => $quoteData['link'] ?? null,
                                    'notes'          => $quoteData['notes'] ?? null,
                                    'attachment'     => $attachmentPath, // <--- PATH FILE TERSIMPAN DI SINI
                                    'is_selected'    => 0,
                                    'created_at'     => now(),
                                    'updated_at'     => now(),
                                ]);

                                // SIMPAN FILE KE TABEL TERPISAH (Spatie Media Library)
                                if ($request->hasFile("items.$itemIndex.vendors.$vendorIndex.file")) {
                                    $file = $request->file("items.$itemIndex.vendors.$vendorIndex.file");
                                    $vendorQuote->addMedia($file)->toMediaCollection('attachments');
                                }
                            }
                        }
                    }
                }

                // Log History
                $this->logHistory($pr->id, 'CREATED', 'Membuat PR Baru dengan nomor **' . $newPrNumber . '**');
            });

            return redirect()->route('pr.index')->with('success', 'PR berhasil dibuat!');

        } catch (\Exception $e) {
            Log::error('Gagal membuat PR: ' . $e->getMessage());
            \Log::error('Gagal membuat PR: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Gagal membuat PR: ' . $e->getMessage());
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // return "asdasdasd";
        $pr = PurchaseRequest::with(['items.vendorQuotes.vendor', 'items.item', 'user', 'company'])
                ->findOrFail($id);

        $currencySymbols = Currency::pluck('symbol', 'code')->toArray();

        $isEditable = in_array($pr->status, ['PENDING', 'DRAFT']);

        return view('pr.show', compact('pr', 'currencySymbols','isEditable'));
    }


    // 2. Proses Simpan Keputusan (Approve/Reject)
    // public function decide(Request $request, $id)
    // {
    //     DB::beginTransaction();

    //     try {
    //         // Load PR beserta seluruh relasinya
    //         $pr = PurchaseRequest::with(['items.item', 'items.vendorQuotes.vendor'])->findOrFail($id);
    //         $user = auth()->user();

    //         $isManager  = $user->hasRole('Manager') || $user->role == 'manager';
    //         $isDirector = $user->hasRole('Director') || $user->role == 'director';

    //         $decisions = $request->input('decisions', []);
    //         $selectedVendors = $request->input('selected_vendor', []);
    //         $hasApprovedItems = false;

    //         // =================================================================
    //         // 1. SIMPAN KEPUTUSAN KE DATABASE (PASTI TERSIMPAN)
    //         // =================================================================
    //         if (!empty($decisions)) {
    //             // Loop langsung dari data asli database agar lebih aman
    //             foreach ($pr->items as $item) {
    //                 $itemId = $item->id;

    //                 if (isset($decisions[$itemId])) {
    //                     $action = $decisions[$itemId]['action'] ?? 'APPROVED';
    //                     $reason = $decisions[$itemId]['reason'] ?? null;

    //                     if ($action === 'REJECTED') {
    //                         $item->status = 'REJECTED';
    //                         $item->rejection_reason = $reason;
    //                         $item->save();

    //                         // Jika ditolak, reset semua pilihan vendor menjadi 0 (false)
    //                         foreach ($item->vendorQuotes as $quote) {
    //                             $quote->is_selected = 0;
    //                             $quote->save();
    //                         }
    //                     } else {
    //                         $item->status = 'APPROVED';
    //                         $item->rejection_reason = null;
    //                         $item->save();
    //                         $hasApprovedItems = true;

    //                         // Simpan vendor yang dipilih (jika ada)
    //                         $quoteId = $selectedVendors[$itemId] ?? null;
    //                         foreach ($item->vendorQuotes as $quote) {
    //                             // Paksa simpan sebagai integer (1 atau 0) agar tidak error casting
    //                             $quote->is_selected = ($quote->id == $quoteId) ? 1 : 0;
    //                             $quote->save();
    //                         }
    //                     }
    //                 }
    //             }
    //         } else {
    //             $hasApprovedItems = $pr->items()->where('status', 'APPROVED')->exists();
    //         }

    //         // =================================================================
    //         // 2. RELOAD DATA UNTUK MENGHINDARI CACHE NYANGKUT!
    //         // =================================================================
    //         // Perintah ini memaksa Laravel melupakan data lama dan membaca ulang DB terbaru
    //         $pr->load(['items.item', 'items.vendorQuotes.vendor']);
    //         $logDetails = [];

    //         foreach ($pr->items as $item) {
    //             $itemName = $item->item->name ?? 'Item #' . $item->id;

    //             if ($item->status === 'REJECTED') {
    //                 // Jika ditolak, catat penolakannya
    //                 $logDetails[] = "❌ Menolak {$itemName} (Alasan: {$item->rejection_reason}).";
    //             } elseif ($item->status === 'APPROVED') {

    //                 // CARI VENDOR YANG TERPILIH (Pencarian Anti-Gagal)
    //                 $selectedQuote = null;
    //                 foreach ($item->vendorQuotes as $quote) {
    //                     // Cek dengan toleransi tipe data (bisa angka 1 atau boolean true)
    //                     if ($quote->is_selected == 1 || $quote->is_selected === true) {
    //                         $selectedQuote = $quote;
    //                         break;
    //                     }
    //                 }

    //                 // Jika vendor berhasil ditemukan, baru cetak ke Log
    //                 if ($selectedQuote) {
    //                     $vendorName = $selectedQuote->vendor->name ?? 'Vendor';
    //                     $price = number_format($selectedQuote->quoted_price, 0, ',', '.');
    //                     $logDetails[] = "✅ Menyetujui {$itemName} (Rekomendasi: {$vendorName} - Rp. {$price}).";
    //                 }

    //                 // Catatan: Jika tidak ada vendor yang di-klik, maka rinciannya sengaja dikosongkan
    //                 // (Sesuai dengan permintaan Anda sebelumnya).
    //             }
    //         }

    //         // =================================================================
    //         // 3. TENTUKAN STATUS HEADER PR
    //         // =================================================================
    //         $actionLog = 'DECIDED';
    //         $mainNote   = '';

    //         if ($isDirector && $pr->current_approval_level >= 1) {
    //             $finalSlug = (!$hasApprovedItems) ? 'rejected' : 'approved';
    //             $mainNote = ($finalSlug == 'approved') ? 'Direktur memberikan PERSETUJUAN FINAL.' : 'Direktur MENOLAK seluruh permintaan.';

    //             $statusDb = \App\Models\Status::where('type', 'PR')->where('slug', $finalSlug)->first();
    //             if ($statusDb) {
    //                 $pr->update([
    //                     'status_id' => $statusDb->id,
    //                     'current_approval_level' => 2,
    //                     'approved_by' => $user->id,
    //                     'approved_at' => now(),
    //                 ]);
    //             }
    //         } elseif ($isManager && $pr->current_approval_level == 0) {
    //             $finalSlug = (!$hasApprovedItems) ? 'rejected' : 'approved_manager';
    //             $mainNote = ($finalSlug == 'rejected') ? 'Manager MENOLAK seluruh permintaan.' : 'Manager menyetujui PR dan diteruskan ke Direktur.';

    //             $statusDb = \App\Models\Status::where('type', 'PR')->where('slug', $finalSlug)->first();
    //             if ($statusDb) {
    //                 $pr->update([
    //                     'status_id' => $statusDb->id,
    //                     'current_approval_level' => ($finalSlug == 'rejected' ? 0 : 1)
    //                 ]);
    //             }
    //         }

    //         // =================================================================
    //         // 4. SIMPAN KE HISTORY LOG
    //         // =================================================================
    //         $finalHistoryNote = $mainNote;

    //         // Gabungkan teks hanya jika ada rinciannya
    //         if (!empty($logDetails)) {
    //             $finalHistoryNote .= "\n\nRincian Keputusan:\n" . implode("\n", $logDetails);
    //         }

    //         \App\Models\PurchaseRequestHistory::create([
    //             'purchase_request_id' => $pr->id,
    //             'user_id' => $user->id,
    //             'action' => $actionLog,
    //             'note' => $finalHistoryNote,
    //         ]);

    //         DB::commit();
    //         return back()->with('success', 'Keputusan approval berhasil disimpan.');

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
    //     }
    // }

    // public function decide(Request $request, string $id)
    // {
    //     $pr = PurchaseRequest::findOrFail($id);

    //     // =========================================================
    //     // 1. JIKA KLIK TOMBOL "TOLAK PR (GLOBAL)"
    //     // =========================================================
    //     if ($request->global_action === 'REJECT') {
    //         // Ambil ID Status 'Ditolak' khusus tipe PR dari tabel master
    //         $statusRejected = Status::where('type', 'PR')->where('slug', 'rejected')->first();
    //         $pr->update(['status_id' => $statusRejected->id]);

    //         // Ubah semua item menjadi REJECTED
    //         foreach ($pr->items as $item) {
    //             $item->update([
    //                 'status' => 'REJECTED',
    //                 'rejection_reason' => 'Ditolak secara global oleh ' . auth()->user()->name
    //             ]);
    //         }

    //         // PENA PENCATAT: LOG REJECT GLOBAL
    //         $pr->histories()->create([
    //             'user_id' => auth()->id(),
    //             'action'  => 'Ditolak (Global)',
    //             'note'    => 'Dokumen PR ditolak secara keseluruhan oleh ' . auth()->user()->name
    //         ]);

    //         return redirect()->route('pr.index')->with('error', 'Purchase Request ditolak secara keseluruhan.');
    //     }

    //     // =========================================================
    //     // 2. JIKA KLIK TOMBOL "SETUJUI & TERUSKAN" (Item per Item)
    //     // =========================================================
    //     $totalApprovedItems = 0;

    //     foreach ($request->items as $itemId => $data) {
    //         $item = PurchaseRequestItem::find($itemId);

    //         $item->qty = $data['qty'];
    //         $item->suggested_vendor_id = $data['vendor_id'] ?? null;
    //         $item->status = $data['status']; // Tetap pakai text 'APPROVED' / 'REJECTED'
    //         $item->rejection_reason = $data['status'] === 'REJECTED' ? $data['reject_reason'] : null;
    //         $item->save();

    //         if ($data['status'] === 'APPROVED') {
    //             $totalApprovedItems++;
    //         }
    //     }

    //     // =========================================================
    //     // 3. TENTUKAN NASIB DOKUMEN PR SECARA GLOBAL
    //     // =========================================================

    //     // Jika semua item ditolak, maka dokumen PR otomatis Ditolak
    //     if ($totalApprovedItems === 0) {
    //         $statusRejected = Status::where('type', 'PR')->where('slug', 'rejected')->first();
    //         $pr->update(['status_id' => $statusRejected->id]);

    //         $pr->histories()->create([
    //             'user_id' => auth()->id(),
    //             'action'  => 'Ditolak (Otomatis)',
    //             'note'    => 'PR ditolak secara sistem karena **semua item** di dalamnya ditolak oleh ' . auth()->user()->name
    //         ]);

    //         return redirect()->route('pr.index')->with('error', 'PR ditolak karena semua item di dalamnya ditolak.');
    //     }

    //     // JIKA ADA ITEM YANG DISETUJUI -> TENTUKAN STATUS SELANJUTNYA
    //     $actionText = 'Disetujui';

    //     // Jika yang login punya akses Manager dan status PR masih 'pending_approval'
    //     if (auth()->user()->can('approve_manager_pr') && $pr->status->slug === 'pending_approval') {

    //         // Ambil ID Status 'Disetujui Manager' dari tabel master
    //         $statusDir = Status::where('type', 'PR')->where('slug', 'approved_manager')->first();
    //         $pr->update(['status_id' => $statusDir->id]);
    //         $actionText = 'Disetujui Manager';

    //     // Jika yang login punya akses Direktur dan status PR sudah 'approved_manager'
    //     } elseif (auth()->user()->can('approve_director_pr') && $pr->status->slug === 'approved_manager') {

    //         // Ambil ID Status 'Disetujui (Final)' dari tabel master
    //         $statusFinal = Status::where('type', 'PR')->where('slug', 'approved')->first();
    //         $pr->update(['status_id' => $statusFinal->id]);
    //         $actionText = 'Disetujui Direktur (Final)';
    //     }

    //     // PENA PENCATAT: LOG PERSETUJUAN
    //     $ditolak = $pr->items()->where('status', 'REJECTED')->count();
    //     $catatan = "Dokumen diproses ke tahap selanjutnya. ";

    //     if ($ditolak > 0) {
    //         $catatan .= "Namun ada **" . $ditolak . " item** yang ditolak sebagian.";
    //     } else {
    //         $catatan .= "Semua item disetujui tanpa penolakan.";
    //     }

    //     $pr->histories()->create([
    //         'user_id' => auth()->id(),
    //         'action'  => $actionText,
    //         'note'    => $catatan
    //     ]);

    //     return redirect()->route('pr.index')->with('success', 'Keputusan PR berhasil disimpan. Item yang ditolak telah disingkirkan.');
    // }

    public function decide(Request $request, string $id)
    {
        $pr = PurchaseRequest::findOrFail($id);

        // =========================================================
        // 1. JIKA KLIK TOMBOL "TOLAK PR (GLOBAL)"
        // =========================================================
        if ($request->global_action === 'REJECT') {
            // Ambil ID Status 'Ditolak' khusus tipe PR dari tabel master
            $statusRejected = \App\Models\Status::where('type', 'PR')->where('slug', 'rejected')->first();

            if ($statusRejected) {
                $pr->update(['status_id' => $statusRejected->id]);
            }

            // Ubah semua item menjadi REJECTED
            foreach ($pr->items as $item) {
                $item->update([
                    'status' => 'REJECTED',
                    'rejection_reason' => 'Ditolak secara global oleh ' . auth()->user()->name
                ]);
            }

            // PENA PENCATAT 1: LOG REJECT GLOBAL
            $pr->histories()->create([
                'user_id' => auth()->id(),
                'action'  => 'Ditolak (Global)',
                'note'    => 'Dokumen PR ditolak secara keseluruhan oleh ' . auth()->user()->name
            ]);

            return redirect()->route('pr.index')->with('error', 'Purchase Request ditolak secara keseluruhan.');
        }

        // =========================================================
        // 2. JIKA KLIK TOMBOL "SETUJUI & TERUSKAN" (Item per Item)
        // =========================================================
        $totalApprovedItems = 0;

        // Array untuk mengumpulkan kalimat catatan log yang cantik
        $rejectedDetails = [];
        $vendorDetails = [];

        foreach ($request->items as $itemId => $data) {
            // Gunakan eager loading 'item' agar bisa memanggil nama barangnya di log
            $prItem = \App\Models\PurchaseRequestItem::with('item')->find($itemId);

            if (!$prItem) continue; // Skip jika item tidak ditemukan (safety)
            if (!$prItem) continue;

            // Update data item sesuai inputan form
            $prItem->qty = $data['qty'];
            // Asumsi kolom rekomendasi vendor di tabel Anda bernama 'suggested_vendor_id'
            $prItem->suggested_vendor_id = $data['vendor_id'] ?? null;
            $prItem->status = $data['status']; // Isinya 'APPROVED' atau 'REJECTED'
            $prItem->status = $data['status'];
            $prItem->rejection_reason = $data['status'] === 'REJECTED' ? ($data['reject_reason'] ?? 'Tanpa alasan spesifik') : null;
            $prItem->save();

            // PENGUMPULAN DATA UNTUK LOG HISTORY
            $namaBarang = $prItem->item ? $prItem->item->name : 'Item #' . $prItem->id;

            if ($data['status'] === 'APPROVED') {
                $totalApprovedItems++; // Tambah hitungan barang yang selamat

                // Jika Manager/Direktur memilih vendor rekomendasi
                if (!empty($data['vendor_id'])) {
                    $vendor = \App\Models\Vendor::find($data['vendor_id']);
                    $vendorName = $vendor ? $vendor->name : 'Vendor Tidak Diketahui';
                    $vendorDetails[] = "- " . $namaBarang . " -> Rekomendasi: **" . $vendorName . "**";
                }
            } else {
                // Jika item ditolak, kumpulkan alasan penolakannya
                $reason = $data['reject_reason'] ?? 'Tanpa alasan spesifik';
                $rejectedDetails[] = "- " . $namaBarang . " (Alasan: " . $reason . ")";
            }
        }

        // =========================================================
        // 3. TENTUKAN NASIB DOKUMEN PR SECARA GLOBAL
        // =========================================================

        // Jika user iseng menolak SEMUA item satu per satu (tidak ada item yang selamat)
        if ($totalApprovedItems === 0) {
            $statusRejected = \App\Models\Status::where('type', 'PR')->where('slug', 'rejected')->first();

            if ($statusRejected) {
                $pr->update(['status_id' => $statusRejected->id]);
            }

            // PENA PENCATAT 2: LOG REJECT KARENA SEMUA ITEM DITOLAK
            $pr->histories()->create([
                'user_id' => auth()->id(),
                'action'  => 'Ditolak (Otomatis)',
                'note'    => 'PR ditolak secara sistem karena **semua item** di dalamnya ditolak secara manual oleh ' . auth()->user()->name
            ]);

            return redirect()->route('pr.index')->with('error', 'PR ditolak karena semua item di dalamnya ditolak.');
        }

        // JIKA ADA MINIMAL 1 ITEM YANG DISETUJUI -> TENTUKAN STATUS SELANJUTNYA
        $actionText = 'Disetujui';

        // Jika yang login punya hak Manager dan status PR masih 'pending_approval'
        if (auth()->user()->can('approve_manager_pr') && $pr->status->slug === 'pending_approval') {

            $statusDir = \App\Models\Status::where('type', 'PR')->where('slug', 'approved_manager')->first();
            if ($statusDir) {
                $pr->update(['status_id' => $statusDir->id]);
            }
            $actionText = 'Disetujui Manager';

        // Jika yang login punya hak Direktur dan status PR sudah 'approved_manager'
        } elseif (auth()->user()->can('approve_director_pr') && $pr->status->slug === 'approved_manager') {

            $statusFinal = \App\Models\Status::where('type', 'PR')->where('slug', 'approved')->first();
            if ($statusFinal) {
                $pr->update(['status_id' => $statusFinal->id]);
            }
            $actionText = 'Disetujui Direktur (Final)';
        }

        // =========================================================
        // 4. PENA PENCATAT: LOG PERSETUJUAN SUPER DETAIL
        // =========================================================

        $catatan = "Dokumen diproses ke tahap selanjutnya.\n";

        // Tambahkan list item yang ditolak ke dalam catatan log jika ada
        if (count($rejectedDetails) > 0) {
            $catatan .= "\n**Daftar Item Ditolak:**\n" . implode("\n", $rejectedDetails) . "\n";
        }

        // Tambahkan list rekomendasi vendor ke dalam catatan log jika ada
        if (count($vendorDetails) > 0) {
            $catatan .= "\n**Rekomendasi Vendor (Sifat Opsional/Referensi):**\n" . implode("\n", $vendorDetails) . "\n";
        }

        // Jika mulus semua (tidak ada yang ditolak dan tidak pilih vendor)
        if (count($rejectedDetails) === 0 && count($vendorDetails) === 0) {
            $catatan .= "\nSemua item disetujui penuh. Pemilihan vendor diserahkan sepenuhnya ke tim Purchasing (PO).";
        }

        // Simpan log ke database
        $pr->histories()->create([
            'user_id' => auth()->id(),
            'action'  => $actionText,
            'note'    => $catatan
        ]);

        return redirect()->route('pr.index')->with('success', 'Keputusan PR berhasil disimpan beserta rincian lognya.');
    }


    /**
     * Show the form for editing the specified resource.
     */
    // public function edit($id)
    // {
    //     // Ambil PR beserta Item, Vendor Quotes, dan File Medianya
    //     $pr = \App\Models\PurchaseRequest::with(['items.vendorQuotes.media'])
    //             ->findOrFail($id);

    //     // Master data untuk dropdown
    //     $items = \App\Models\Item::all();
    //     $vendors = \App\Models\Vendor::all();
    //     $companies = \App\Models\Company::all();

    //     $currencies = Currency::where('is_active', true)->get();

    //     return view('pr.edit', compact('pr', 'items', 'vendors', 'currencies','companies'));

    // }

    // /**
    //  * Update the specified resource in storage.
    //  */
    // public function update(Request $request, $id)
    // {
    //     // 1. Validasi
    //     $request->validate([
    //         'request_date' => 'required|date',
    //         'description'  => 'required|string',
    //         'company_id'   => 'required|exists:companies,id',
    //         // Validasi Item
    //         'items'           => 'required|array',
    //         'items.*.item_id' => 'required', // Pastikan ID barang dipilih
    //         'items.*.qty'     => 'required|numeric|min:1',
    //     ]);

    //     $pr = PurchaseRequest::findOrFail($id);

    //     DB::transaction(function () use ($request, $pr) {
    //         // A. UPDATE HEADER PR
    //         $pr->update([
    //             'request_date' => $request->request_date,
    //             'description'  => $request->description,
    //             'company_id'   => $request->company_id,
    //         ]);

    //         // ====================================================
    //         // B. LOGIKA HAPUS ITEM (Barang) YANG DIHAPUS DI FORM
    //         // ====================================================

    //         // Ambil semua ID Item yang dikirim dari form (yang masih ada / tidak disilang)
    //         $submittedItemIds = collect($request->items)->pluck('id')->filter()->toArray();

    //         // Hapus Item di database yang TIDAK ADA di form submission
    //         // Note: Relasi vendorQuotes di model Item harus 'cascade' atau ditangani manual
    //         $pr->items()->whereNotIn('id', $submittedItemIds)->delete();


    //         // ====================================================
    //         // C. LOOPING UPDATE / CREATE ITEM & VENDOR
    //         // ====================================================

    //         foreach ($request->items as $itemData) {
    //             // C.1 Update atau Create Item
    //             $item = $pr->items()->updateOrCreate(
    //                 ['id' => $itemData['id'] ?? null], // Cari berdasarkan ID (jika ada)
    //                 ['id' => $itemData['id'] ?? null],
    //                 [
    //                     'item_id' => $itemData['item_id'],
    //                     'qty'     => $itemData['qty']
    //                 ]
    //             );

    //             // C.2 LOGIKA VENDOR
    //             // Cek apakah ada data vendor yang dikirim?
    //             if (isset($itemData['vendors']) && is_array($itemData['vendors'])) {

    //                 // 1. Hapus Vendor yang dibuang user (Klik tombol silang X)
    //                 $submittedVendorIds = collect($itemData['vendors'])->pluck('id')->filter()->toArray();

    //                 // PENTING: Pakai get()->each->delete() agar Event Eloquent jalan
    //                 // Supaya Spatie Media Library mendeteksi event 'deleting' dan menghapus file fisiknya
    //                 $item->vendorQuotes()->whereNotIn('id', $submittedVendorIds)->get()->each->delete();

    //                 // 2. Loop Update/Create Vendor yang tersisa
    //                 foreach ($itemData['vendors'] as $vData) {
    //                     $vendorQuote = $item->vendorQuotes()->updateOrCreate(
    //                         ['id' => $vData['id'] ?? null], // Jika ID null = Buat Baru
    //                         ['id' => $vData['id'] ?? null],
    //                         [
    //                             'vendor_id'      => $vData['vendor_id'],

    //                             // PERBAIKAN PENTING (Mencegah Error 1048):
    //                             // Jika price kosong/null, ganti jadi 0
    //                             'currency'       => $vData['currency'] ?? 'IDR', // <--- JANGAN LUPA BARIS INI
    //                             'quoted_price'   => $vData['price'] ?? 0,

    //                             'reference_link' => $vData['link'] ?? null,
    //                             'notes'          => $vData['notes'] ?? null,
    //                         ]
    //                     );

    //                     // --- LOGIKA FILE / GAMBAR ---

    //                     // Cek apakah ada file baru yang diupload di form?
    //                     if (isset($vData['file']) && $vData['file'] instanceof \Illuminate\Http\UploadedFile) {

    //                         // 1. HAPUS FILE LAMA (PENTING)
    //                         // Kita clear collection dulu agar file lama hilang dan diganti baru
    //                         $vendorQuote->clearMediaCollection('vendor_quotes');

    //                         // 2. UPLOAD FILE BARU
    //                         // CustomPathGenerator akan otomatis menangani folder berdasarkan Nomor PR
    //                         $vendorQuote->addMedia($vData['file'])
    //                                     ->toMediaCollection('vendor_quotes');
    //                     }
    //                 }
    //             } else {
    //                 // Jika user menghapus SEMUA vendor di item tersebut (Area vendor kosong)
    //                 // Hapus semua data vendor & file terkait item ini
    //                 $item->vendorQuotes()->get()->each->delete();
    //             }
    //         }
    //     });

    //     $this->logHistory($id, 'UPDATED', 'Melakukan perubahan data / vendor');

    //     return redirect()->route('pr.index')->with('success', 'PR berhasil diperbarui!');
    // }

    public function edit($id)
    {
        // Ambil PR beserta Item dan Vendor Quotes
        $pr = \App\Models\PurchaseRequest::with(['items.vendorQuotes'])
                ->findOrFail($id);

        // Master data untuk dropdown
        $items = \App\Models\Item::all();
        $vendors = \App\Models\Vendor::all();
        $companies = \App\Models\Company::all();
        $currencies = \App\Models\Currency::where('is_active', true)->get();

        return view('pr.edit', compact('pr', 'items', 'vendors', 'currencies', 'companies'));
    }

    public function update(Request $request, $id)
    {
        // 1. Validasi
        $request->validate([
            'request_date' => 'required|date',
            // TAMBAHAN: Validasi Need Date (harus ada, berupa tanggal, dan minimal sama dengan request_date)
            'need_date'    => 'required|date|after_or_equal:request_date',
            'description'  => 'required|string',
            'company_id'   => 'required|exists:companies,id',
            'items'           => 'required|array',
            'items.*.item_id' => 'required',
            'items.*.qty'     => 'required|numeric|min:0.01',
        ]);

        $pr = \App\Models\PurchaseRequest::findOrFail($id);

        try {
            DB::transaction(function () use ($request, $pr) {
                // A. UPDATE HEADER PR
                $pr->update([
                    'request_date' => $request->request_date,
                    'description'  => $request->description,
                    'company_id'   => $request->company_id,
                    // TAMBAHAN: Simpan Need Date ke Database
                    'need_date'    => $request->need_date,
                ]);

                // Persiapkan Nama Folder (mengikuti logika folder store)
                $folderName = str_replace(['/', '\\'], '-', $pr->pr_number);
                $storagePath = "pr_uploads/" . $folderName;

                // B. PEMBERSIHAN DATA (Syncing)
                // Hapus Item yang tidak ada lagi di form
                $submittedItemIds = collect($request->items)->pluck('id')->filter()->toArray();
                $itemsToDelete = $pr->items()->whereNotIn('id', $submittedItemIds)->get();

                foreach ($itemsToDelete as $delItem) {
                    // Hapus file fisik lampiran vendor terkait sebelum hapus record
                    foreach ($delItem->vendorQuotes as $quote) {
                        if ($quote->attachment) {
                            Storage::disk('public')->delete($quote->attachment);
                        }
                    }
                    $delItem->delete();
                }

                // C. LOOPING UPDATE / CREATE
                foreach ($request->items as $itemIndex => $itemData) {
                    $prItem = $pr->items()->updateOrCreate(
                        ['id' => $itemData['id'] ?? null],
                        [
                            'item_id' => $itemData['item_id'],
                            'qty'     => $itemData['qty']
                        ]
                    );

                    if (isset($itemData['vendors']) && is_array($itemData['vendors'])) {
                        // Hapus Vendor yang dibuang di form
                        $submittedVendorIds = collect($itemData['vendors'])->pluck('id')->filter()->toArray();
                        $quotesToDelete = $prItem->vendorQuotes()->whereNotIn('id', $submittedVendorIds)->get();

                        foreach ($quotesToDelete as $delQuote) {
                            if ($delQuote->attachment) {
                                Storage::disk('public')->delete($delQuote->attachment);
                            }
                            $delQuote->delete();
                        }

                        // Update atau Create Vendor
                        foreach ($itemData['vendors'] as $vIndex => $vData) {
                            $attachmentPath = null;

                            // Ambil record lama untuk cek file lama
                            $existingQuote = null;
                            if (!empty($vData['id'])) {
                                $existingQuote = DB::table('pr_item_vendors')->where('id', $vData['id'])->first();
                                $attachmentPath = $existingQuote->attachment ?? null;
                            }

                            // JIKA ADA FILE BARU DIUPLOAD
                            if ($request->hasFile("items.$itemIndex.vendors.$vIndex.file")) {
                                // Hapus file lama jika ada
                                if ($attachmentPath) {
                                    Storage::disk('public')->delete($attachmentPath);
                                }

                                $file = $request->file("items.$itemIndex.vendors.$vIndex.file");
                                $fileName = "item_{$itemData['item_id']}_v_{$vData['vendor_id']}_" . time() . "." . $file->getClientOriginalExtension();
                                $attachmentPath = $file->storeAs($storagePath, $fileName, 'public');
                            }

                            // Update database menggunakan DB Table atau Model
                            DB::table('pr_item_vendors')->updateOrInsert(
                            $vendorQuote = PurchaseRequestItemVendor::updateOrCreate(
                                ['id' => $vData['id'] ?? null],
                                [
                                    'pr_item_id'     => $prItem->id,
                                    'vendor_id'      => $vData['vendor_id'],
                                    'currency'       => $vData['currency'] ?? 'IDR',
                                    'quoted_price'   => $vData['price'] ?? 0,
                                    'reference_link' => $vData['link'] ?? null,
                                    'notes'          => $vData['notes'] ?? null,
                                    'attachment'     => $attachmentPath,
                                    'updated_at'     => now(),
                                    'created_at'     => !empty($vData['id']) ? ($existingQuote->created_at ?? now()) : now(),
                                ]
                            );

                            if ($request->hasFile("items.$itemIndex.vendors.$vIndex.file")) {
                                $vendorQuote->clearMediaCollection('attachments');
                                $file = $request->file("items.$itemIndex.vendors.$vIndex.file");
                                $vendorQuote->addMedia($file)->toMediaCollection('attachments');
                            }
                        }
                    } else {
                        // Jika item tidak punya vendor sama sekali
                        $prItem->vendorQuotes()->delete();
                    }
                }
            });

            $this->logHistory($id, 'UPDATED', 'Melakukan revisi data PR dan lampiran vendor');
            return redirect()->route('pr.index')->with('success', 'PR berhasil diperbarui!');

        } catch (\Exception $e) {
            Log::error('Update PR Error: ' . $e->getMessage());
            \Log::error('Update PR Error: ' . $e->getMessage());
            return back()->with('error', 'Gagal memperbarui PR: ' . $e->getMessage());
        }
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }


    // 2. Proses Approval (Multi-Level)
    public function approve(Request $request, $id)
    {

        // dd("Function Approve Terpanggil!", $request->all(), Auth::user()->roles->pluck('name'));

        // 1. Validasi Input (Pastikan ada action)
        $request->validate([
            'action' => 'required|in:APPROVE,REJECT',
            'rejection_reason' => 'required_if:action,REJECT' // Wajib isi alasan jika tolak
            'rejection_reason' => 'required_if:action,REJECT'
        ]);

        $pr = PurchaseRequest::findOrFail($id);
        $user = Auth::user();

        // ====================================================
        // A. LOGIKA JIKA DITOLAK (REJECT)
        // ====================================================
        if ($request->action == 'REJECT') {

            $pr->update([
                'status' => 'REJECTED',
                'rejection_reason' => $request->rejection_reason
            ]);

            // [LOG DISINI] - Pastikan log dipanggil SEBELUM return
            $this->logHistory($id, 'REJECTED', 'Ditolak: ' . $request->rejection_reason);

            return back()->with('success', 'PR telah ditolak.');
        }

        // ====================================================
        // B. LOGIKA JIKA DISETUJUI (APPROVE)
        // ====================================================

        // B.1. Cek Level 1: MANAGER
        // Syarat: Level 0 (Pending) DAN User adalah Manager
        if ($pr->current_approval_level == 0 && $user->hasRole('Manager')) {

            $pr->update([
                'current_approval_level' => 1,
                'status' => 'APPROVED_MANAGER'
            ]);

            // [LOG DISINI] - Log spesifik Manager
            $this->logHistory($id, 'APPROVED', 'Manager menyetujui (Menunggu Director)');

            return back()->with('success', 'Approval Manager berhasil.');
        }

        // B.2. Cek Level 2: DIRECTOR (Final)
        // Syarat: Level 1 (Sudah Manager) DAN User adalah Director
        elseif ($pr->current_approval_level == 1 && $user->hasRole('Director')) {

            $pr->update([
                'current_approval_level' => 2,
                'status' => 'APPROVED' // Status Final
            ]);

            // [LOG DISINI] - Log spesifik Director
            $this->logHistory($id, 'APPROVED', 'Director menyetujui (Final)');

            return back()->with('success', 'PR Full Approved.');
        }

        // ====================================================
        // C. JIKA TIDAK MEMENUHI SYARAT (Tidak ada akses)
        // ====================================================
        else {
            // Log Error (Opsional, agar tahu ada yang mencoba akses)
            // \Log::warning("User {$user->name} mencoba approve PR #{$id} tapi gagal syarat.");

            return back()->with('error', 'Anda tidak memiliki akses approval untuk tahapan ini.');
        }
    }


    public function print($id)
    {
        $pr = \App\Models\PurchaseRequest::with([
            'items.item',
            'items.vendorQuotes.vendor',
            'user',
            'company',
            'histories.user.roles' // Load history beserta user & rolenya
            'histories.user.roles'
        ])->findOrFail($id);

        $currencySymbols = \App\Models\Currency::pluck('symbol', 'code')->toArray();

        // --- LOGIKA BARU (Cari Berdasarkan Role) ---
        $manager = null;
        $director = null;

        // Ambil semua log keputusan (DECIDED) selain requester sendiri
        $approvalLogs = $pr->histories
            ->where('action', 'DECIDED')
            ->where('user_id', '!=', $pr->user_id);

        foreach ($approvalLogs as $log) {
            $approver = $log->user;

            // Cek apakah user ini Manager?
            if ($approver->hasRole('Manager')) {
                $manager = $approver;
            }
            // Cek apakah user ini Director?
            elseif ($approver->hasRole('Director')) {
                $director = $approver;
            }
            // Jika Super Admin yang approve, masukkan ke slot yang kosong
            elseif ($approver->hasRole('Super Admin') || $approver->hasRole('Admin')) {
                if (!$manager) $manager = $approver;
                elseif (!$director) $director = $approver;
            }
        }

        return view('pr.print', compact('pr', 'currencySymbols', 'manager', 'director'));
    }

    // FITUR BATALKAN PR (CANCEL)
    public function cancel(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $pr = PurchaseRequest::with('items')->findOrFail($id);
            $user = auth()->user();
            $reason = $request->input('cancel_reason', 'Dibatalkan oleh user');

            // Cek apakah PR sudah jadi PO
            if ($pr->status && $pr->status->slug === 'po_issued') {
                return back()->with('error', 'PR tidak dapat dibatalkan karena sudah diproses menjadi PO.');
            }

            // PERBAIKAN: Fokus HANYA mencari status "cancelled" atau "batal" (Tanpa fallback ke rejected)
            $statusDb = \App\Models\Status::where('type', 'PR')
                            ->whereIn('slug', ['cancelled', 'batal'])
                            ->first();

            if ($statusDb) {
                // Update header PR: Ubah status dan turunkan level persetujuan jadi 0
                $pr->update([
                    'status_id' => $statusDb->id,
                    'current_approval_level' => 0,
                ]);

                // Ubah status SEMUA item menjadi CANCELLED
                foreach ($pr->items as $item) {
                    $item->update([
                        'status' => 'CANCELLED',
                        'rejection_reason' => 'Dibatalkan: ' . $reason
                    ]);
                }

                // Catat di riwayat aktivitas
                \App\Models\PurchaseRequestHistory::create([
                    'purchase_request_id' => $pr->id,
                    'user_id' => $user->id,
                    'action' => 'CANCELLED',
                    'note' => "Dokumen PR dibatalkan secara keseluruhan.\nAlasan: " . $reason,
                ]);
            } else {
                DB::rollBack();
                // Munculkan error jika Master Status "cancelled" belum dibuat di database
                return back()->with('error', 'Sistem gagal! Status "cancelled" belum ada di tabel Master Status Database Anda.');
            }

            DB::commit();
            return back()->with('success', 'Purchase Request berhasil dibatalkan.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat membatalkan PR: ' . $e->getMessage());
        }
    }


    // Tambahkan helper function ini di paling bawah class controller
    private function logHistory($prId, $action, $note = null)
    {
        \App\Models\PurchaseRequestHistory::create([
            'purchase_request_id' => $prId,
            'user_id' => auth()->id(),
            'action'  => $action,
            'note'    => $note
        ]);
    }


    public function rejectAll(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $pr = PurchaseRequest::with('items.vendorQuotes')->findOrFail($id);
            $user = auth()->user();

            // Tangkap alasan dari modal pop-up
            $reason = $request->input('reject_reason', 'Ditolak oleh atasan tanpa alasan spesifik');

            // Cek role yang sedang login untuk keperluan log
            $roleName = ($user->hasRole('Director') || $user->role == 'director') ? 'Direktur' : 'Manager';

            // Ambil status rejected dari database
            $statusDb = \App\Models\Status::where('type', 'PR')->where('slug', 'rejected')->first();

            if ($statusDb) {
                // 1. Ubah status PR menjadi Ditolak dan kembalikan ke level 0 (agar staf bisa perbaiki)
                $pr->update([
                    'status_id' => $statusDb->id,
                    'current_approval_level' => 0
                ]);

                // 2. Ubah status SEMUA item menjadi REJECTED
                foreach ($pr->items as $item) {
                    $item->update([
                        'status' => 'REJECTED',
                        'rejection_reason' => $reason
                    ]);

                    // Reset semua vendor agar tidak ada yang terpilih
                    \App\Models\VendorQuote::where('purchase_request_item_id', $item->id)->update(['is_selected' => 0]);
                }

                // 3. Catat di riwayat aktivitas
                \App\Models\PurchaseRequestHistory::create([
                    'purchase_request_id' => $pr->id,
                    'user_id' => $user->id,
                    'action' => 'REJECTED',
                    'note' => "❌ {$roleName} MENOLAK seluruh permintaan PR.\nAlasan: " . $reason,
                ]);
            } else {
                DB::rollBack();
                return back()->with('error', 'Status ditolak tidak ditemukan di Master Status.');
            }

            DB::commit();
            return redirect()->route('pr.index')->with('success', 'Seluruh Purchase Request berhasil ditolak.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat menolak PR: ' . $e->getMessage());
        }
    }


    public function forceCloseItem(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            // 1. CARI DATA
            $item = \App\Models\PurchaseRequestItem::findOrFail($id);
            $prId = $item->purchase_request_id;
            $reason = $request->input('reason', '-');

            // ==========================================================
            // 2. TEMBAK DATABASE SECARA LANGSUNG (QUERY BUILDER)
            // Ini akan bypass semua proteksi/cache Model Laravel
            // ==========================================================
            DB::table('purchase_request_items')
                ->where('id', $id)
                ->update([
                    'status' => 'FORCE_CLOSED',
                    'rejection_reason' => 'Sisa kuantitas digugurkan. Catatan: ' . $reason,
                    'updated_at' => now(),
                ]);

            // 3. CATAT HISTORI
            $itemName = $item->item->name ?? 'Item #' . $item->id;
            $sisa = $item->qty - ($item->ordered_qty ?? 0);

            \App\Models\PurchaseRequestHistory::create([
                'purchase_request_id' => $prId,
                'user_id' => auth()->id(),
                'action' => 'SHORT CLOSED',
                'note' => "Menutup sisa pesanan untuk {$itemName} sebanyak {$sisa} {$item->uom}.\nAlasan: " . $reason,
            ]);

            // 4. CEK ULANG STATUS KESELURUHAN PR
            // Ambil ulang data PR beserta items DARI AWAL agar datanya fresh
            $pr = \App\Models\PurchaseRequest::with('items')->findOrFail($prId);

            $allFulfilled = true;
            foreach($pr->items as $prItem) {
                // Kita HANYA mempedulikan barang yang masih berstatus APPROVED
                if ($prItem->status === 'APPROVED') {
                    $ordered = $prItem->ordered_qty ?? 0;
                    if ($ordered < $prItem->qty) {
                        $allFulfilled = false;
                        break;
                    }
                }
            }

            // ==========================================================
            // 5. UPDATE HEADER PR PAKAI QUERY BUILDER
            // ==========================================================
            if ($allFulfilled) {
                $statusTarget = \App\Models\Status::where('type', 'PR')->where('slug', 'po_issued')->first();
                if ($statusTarget) {
                    DB::table('purchase_requests')
                        ->where('id', $prId)
                        ->update([
                            'status_id' => $statusTarget->id,
                            'updated_at' => now(),
                        ]);
                }
            }

            DB::commit();
            return back()->with('success', 'Sisa item berhasil digugurkan! Status dokumen telah diperbarui.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menutup sisa item: ' . $e->getMessage());
        }
    }

}
