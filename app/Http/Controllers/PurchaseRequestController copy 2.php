<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\History;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestHistory;
use App\Models\PurchaseRequestItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;
use App\Models\PurchaseRequestItemVendor;
use App\Models\Status;
use App\Models\VendorQuote;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\User;
use App\Notifications\DocumentApprovalNotification;
use App\Models\ApprovalWorkflow;
use App\Models\DocumentApproval;
// Pastikan DB dan Exception juga sudah di-use

use PDF;

class PurchaseRequestController extends Controller
{
    use InteractsWithMedia;

    public function index(\Illuminate\Http\Request $request)
    {
        $search = $request->input('search');
        $statusFilter = $request->input('status');
        $deptFilter = $request->input('department');

        $statuses = \App\Models\Status::where('type', 'PR')->orderBy('sequence')->get();
        $companies = \App\Models\Company::all();

        $user = auth()->user();

        $requests = \App\Models\PurchaseRequest::with(['status', 'company', 'user', 'purchaseOrders'])
            // ==========================================================
            // 🛡️ PROTEKSI DATA: STAF HANYA LIHAT PR MILIK SENDIRI
            // ==========================================================
            ->when(!$user->hasAnyRole(['Super Admin', 'Purchasing', 'manager', 'direktur', 'Finance', 'Gudang']), function ($query) use ($user) {
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
            ->paginate(10);

        return view('pr.index', compact('requests', 'companies', 'statuses'));
    }

    public function create()
    {
        $items = \App\Models\Item::with(['uom', 'itemUoms'])->where('is_active', true)->get();
        $vendors = \App\Models\Vendor::where('is_active', true)->get();
        $companies = \App\Models\Company::all();
        $currencies = \App\Models\Currency::where('is_active', true)->get();

        // 🔥 TAMBAHKAN INI: Ambil semua user aktif beserta data PT mereka
        $users = \App\Models\User::with('company')->orderBy('name')->get();

        return view('pr.create', compact('companies', 'items', 'vendors', 'currencies', 'users'));
    }

    private function generatePrNumber($companyId)
    {
        $company = \App\Models\Company::find($companyId);
        $code = $company && $company->code ? $company->code : 'HO';

        $now = now();
        $dateStr = $now->format('Y/m/d');

        $prefix = "PR/{$code}/{$dateStr}/";

        $lastPr = PurchaseRequest::where('pr_number', 'like', $prefix . '%')
                    ->orderBy('id', 'desc')
                    ->lockForUpdate()
                    ->first();

        if ($lastPr) {
            $lastNumber = (int) substr($lastPr->pr_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . sprintf('%04d', $newNumber);
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id'      => 'required|exists:users,id',
            'company_id'   => 'required|exists:companies,id',
            'request_date' => 'required|date',
            'need_date'    => 'required|date|after_or_equal:request_date',
            'description'  => 'required|string|max:500',
            'items'        => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty'     => 'required|numeric|min:0.01',

            // 🔥 PERBAIKAN: Validasi diperketat menjadi integer
            'items.*.uom_id'  => 'required|integer',
        ], [
            // Pesan error ramah pengguna jika 'undefined' berhasil masuk
            'items.*.uom_id.integer' => 'Satuan barang tidak valid. Silakan pilih ulang nama barang di tabel.'
        ]);

        try {
            DB::transaction(function () use ($request) {
                $newPrNumber = $this->generatePrNumber($request->company_id);
                $initialStatus = \App\Models\Status::where('type', 'PR')->where('slug', 'pending_approval')->first();

                if (!$initialStatus) throw new \Exception('Status "pending_approval" belum ada.');

                $pr = \App\Models\PurchaseRequest::create([
                    'pr_number'             => $newPrNumber,
                    'user_id'               => $request->user_id,
                    'company_id'            => $request->company_id,
                    'request_date'          => $request->request_date,
                    'need_date'             => $request->need_date,
                    'description'           => $request->description,
                    'status_id'             => $initialStatus->id,
                    'current_approval_level'=> 0,
                    'created_by'            => auth()->id(),
                ]);

                foreach ($request->items as $itemIndex => $itemData) {
                    // Ambil data item untuk backup
                    $masterItem = \App\Models\Item::findOrFail($itemData['item_id']);

                    $prItem = \App\Models\PurchaseRequestItem::create([
                        'purchase_request_id' => $pr->id,
                        'item_id'             => $itemData['item_id'],
                        'qty'                 => $itemData['qty'],
                        'uom_id'              => $itemData['uom_id'], // Sudah dipastikan angka karena lolos validasi
                        'estimated_price'     => 0, // Default 0 karena harga ada di tabel vendor_quotes
                        'status'              => 'PENDING'
                    ]);

                    if (isset($itemData['vendors']) && is_array($itemData['vendors'])) {
                        foreach ($itemData['vendors'] as $vendorIndex => $quoteData) {
                            if (!empty($quoteData['vendor_id'])) {
                                $attachmentPath = null;
                                if ($request->hasFile("items.$itemIndex.vendors.$vendorIndex.file")) {
                                    $file = $request->file("items.$itemIndex.vendors.$vendorIndex.file");
                                    $folderName = str_replace(['/', '\\'], '-', $newPrNumber);
                                    $attachmentPath = $file->storeAs("pr_uploads/$folderName", "item_{$itemData['item_id']}_v_{$quoteData['vendor_id']}_".time().".{$file->getClientOriginalExtension()}", 'public');
                                }

                                DB::table('pr_item_vendors')->insert([
                                    'pr_item_id'     => $prItem->id,
                                    'vendor_id'      => $quoteData['vendor_id'],
                                    'currency'       => $quoteData['currency'] ?? 'IDR',
                                    'quoted_price'   => $quoteData['price'] ?? 0,
                                    'attachment'     => $attachmentPath,
                                    'created_at'     => now(), 'updated_at' => now(),
                                ]);
                            }
                        }
                    }
                }
                $this->logHistory($pr->id, 'CREATED', "PR {$newPrNumber} diajukan.");
            });

            // Redirect sukses setelah transaksi selesai
            return redirect()->route('pr.index')->with('success', 'PR Berhasil Diajukan!');

        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal: ' . $e->getMessage());
        }
    }

    public function show(string $slug)
    {
        // Terjemahkan Slug kembali ke Nomor PR
        $prNumber = str_replace('_', '/', $slug);

        // Cari berdasarkan pr_number
        $pr = \App\Models\PurchaseRequest::with(['items.vendorQuotes.vendor', 'items.item', 'user', 'company'])
                ->where('pr_number', $prNumber)
                ->firstOrFail();

        $currencySymbols = \App\Models\Currency::pluck('symbol', 'code')->toArray();
        $isEditable = in_array(optional($pr->status)->slug, ['pending_approval', 'draft']);

        // ==========================================
        // 🔥 LOGIKA HAK AKSES APPROVAL DIPINDAH KE SINI
        // ==========================================
        $user = auth()->user();
        $isManager = $user->hasAnyRole(['manager', 'Super Admin']);
        $isDirektur = $user->hasAnyRole(['direktur', 'Super Admin']);
        $statusSlug = optional($pr->status)->slug;

        $canApprove = ($isManager && $statusSlug === 'pending_approval') ||
                      ($isDirektur && $statusSlug === 'approved_manager');

        // Pastikan $canApprove ikut dikirim ke View
        return view('pr.show', compact('pr', 'currencySymbols', 'isEditable', 'canApprove'));
    }


    public function decide(Request $request, string $slug)
    {
        // Terjemahkan Slug kembali ke Nomor PR
        $prNumber = str_replace('_', '/', $slug);

        // Cari berdasarkan pr_number
        $pr = PurchaseRequest::where('pr_number', $prNumber)->firstOrFail();
        $pembuatPR = User::find($pr->user_id);

        // =========================================================
        // 🧹 AUTO-SAPU NOTIFIKASI: Tandai sudah dibaca jika di-eksekusi
        // =========================================================
        foreach(auth()->user()->unreadNotifications as $notification) {
            // Jika notifikasi ini URL-nya mengarah ke PR ini, langsung tandai terbaca!
            if(isset($notification->data['url']) && str_contains($notification->data['url'], route('pr.show', $pr->id))) {
                $notification->markAsRead();
            }
        }

        // =========================================================
        // 1. JIKA KLIK TOMBOL "TOLAK PR (GLOBAL)"
        // =========================================================
        if ($request->global_action === 'REJECT') {
            $statusRejected = \App\Models\Status::where('type', 'PR')->where('slug', 'rejected')->first();

            if ($statusRejected) {
                $pr->update(['status_id' => $statusRejected->id, 'current_approval_level' => 0]);
            }

            foreach ($pr->items as $item) {
                $item->update([
                    'status' => 'REJECTED',
                    'rejection_reason' => 'Ditolak secara global oleh ' . auth()->user()->name
                ]);
            }

            $pr->histories()->create([
                'user_id' => auth()->id(),
                'action'  => 'Ditolak (Global)',
                'note'    => 'Dokumen PR ditolak secara keseluruhan oleh ' . auth()->user()->name
            ]);

            // 🔔 NOTIF KE PEMBUAT PR
            if ($pembuatPR) {
                $pembuatPR->notify(new DocumentApprovalNotification(
                    'PR Ditolak ❌', "Mohon maaf, PR Nomor {$pr->pr_number} ditolak secara global oleh Atasan.", route('pr.show', $pr->id)
                ));
            }

            return redirect()->route('pr.index')->with('error', 'Purchase Request ditolak secara keseluruhan.');
        }

        // =========================================================
        // 2. JIKA KLIK TOMBOL "SETUJUI & TERUSKAN" (Item per Item)
        // =========================================================
        $totalApprovedItems = 0;
        $rejectedDetails = [];
        $vendorDetails = [];

        foreach ($request->items as $itemId => $data) {
            $prItem = \App\Models\PurchaseRequestItem::with('item')->find($itemId);
            if (!$prItem) continue;

            $prItem->qty = $data['qty'];
            $prItem->suggested_vendor_id = $data['vendor_id'] ?? null;
            $prItem->status = $data['status'];
            $prItem->rejection_reason = $data['status'] === 'REJECTED' ? ($data['reject_reason'] ?? 'Tanpa alasan spesifik') : null;
            $prItem->save();

            $namaBarang = $prItem->item ? $prItem->item->name : 'Item #' . $prItem->id;

            if ($data['status'] === 'APPROVED') {
                $totalApprovedItems++;

                if (!empty($data['vendor_id'])) {
                    $vendor = \App\Models\Vendor::find($data['vendor_id']);
                    $vendorName = $vendor ? $vendor->name : 'Vendor Tidak Diketahui';
                    $vendorDetails[] = "- " . $namaBarang . " -> Rekomendasi: **" . $vendorName . "**";
                }
            } else {
                $reason = $data['reject_reason'] ?? 'Tanpa alasan spesifik';
                $rejectedDetails[] = "- " . $namaBarang . " (Alasan: " . $reason . ")";
            }
        }

        // =========================================================
        // 3. TENTUKAN NASIB DOKUMEN PR SECARA GLOBAL
        // =========================================================
        if ($totalApprovedItems === 0) {
            $statusRejected = \App\Models\Status::where('type', 'PR')->where('slug', 'rejected')->first();
            if ($statusRejected) $pr->update(['status_id' => $statusRejected->id, 'current_approval_level' => 0]);

            $pr->histories()->create([
                'user_id' => auth()->id(),
                'action'  => 'Ditolak (Otomatis)',
                'note'    => 'PR ditolak secara sistem karena **semua item** di dalamnya ditolak secara manual oleh ' . auth()->user()->name
            ]);

            // 🔔 NOTIF KE PEMBUAT PR
            if ($pembuatPR) {
                $pembuatPR->notify(new DocumentApprovalNotification(
                    'PR Ditolak ❌', "Mohon maaf, semua item pada PR Nomor {$pr->pr_number} telah ditolak.", route('pr.show', $pr->id)
                ));
            }

            return redirect()->route('pr.index')->with('error', 'PR ditolak karena semua item di dalamnya ditolak.');
        }

        $actionText = 'Disetujui';

        // =========================================================
        // 🛡️ JIKA MANAGER YANG APPROVE
        // =========================================================
        if (auth()->user()->hasAnyRole(['manager', 'Super Admin']) && optional($pr->status)->slug === 'pending_approval') {
            $statusDir = \App\Models\Status::where('type', 'PR')->where('slug', 'approved_manager')->first();
            if ($statusDir) {
                $pr->update([
                    'status_id' => $statusDir->id,
                    'current_approval_level' => 1
                ]);
            }
            $actionText = 'Disetujui Manager';

            // 🔔 NOTIF KE DIREKTUR (Minta Persetujuan Lanjutan)
            $direkturs = User::role(['Super Admin', 'direktur'])->get();
            foreach($direkturs as $dir) {
                $dir->notify(new DocumentApprovalNotification(
                    'PR Butuh Persetujuan Final 📝', "PR Nomor {$pr->pr_number} telah disetujui Manager dan butuh persetujuan final Anda.", route('pr.show', $pr->id)
                ));
            }

        // =========================================================
        // 🛡️ JIKA DIREKTUR YANG APPROVE (FINAL)
        // =========================================================
        } elseif (auth()->user()->hasAnyRole(['direktur', 'Super Admin']) && optional($pr->status)->slug === 'approved_manager') {
            $statusFinal = \App\Models\Status::where('type', 'PR')->where('slug', 'approved')->first();
            if ($statusFinal) {
                $pr->update([
                    'status_id' => $statusFinal->id,
                    'current_approval_level' => 2,
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);
            }
            $actionText = 'Disetujui Direktur (Final)';

            // 🔔 NOTIF KE PEMBUAT PR (Kabar Gembira)
            if ($pembuatPR) {
                $pembuatPR->notify(new DocumentApprovalNotification(
                    'PR Disetujui ✅', "Hore! PR Nomor {$pr->pr_number} telah disetujui Final.", route('pr.show', $pr->id)
                ));
            }

            // 🔔 NOTIF KE TIM PURCHASING (Perintah Eksekusi)
            $timPurchasing = User::role(['Super Admin', 'Purchasing'])->get();
            foreach($timPurchasing as $purchasing) {
                $purchasing->notify(new DocumentApprovalNotification(
                    'PR Baru Siap Di-PO 🛒', "PR Nomor {$pr->pr_number} telah disetujui final dan siap dibuatkan PO.", route('pr.show', $pr->id)
                ));
            }
        }

        // =========================================================
        // 4. PENA PENCATAT LOG & AUDIT TRAIL
        // =========================================================
        $catatan = "Dokumen diproses ke tahap selanjutnya.\n";
        if (count($rejectedDetails) > 0) $catatan .= "\n**Daftar Item Ditolak:**\n" . implode("\n", $rejectedDetails) . "\n";
        if (count($vendorDetails) > 0) $catatan .= "\n**Rekomendasi Vendor (Sifat Opsional/Referensi):**\n" . implode("\n", $vendorDetails) . "\n";
        if (count($rejectedDetails) === 0 && count($vendorDetails) === 0) {
            $catatan .= "\nSemua item disetujui penuh. Pemilihan vendor diserahkan sepenuhnya ke tim Purchasing (PO).";
        }

        $pr->histories()->create([
            'user_id' => auth()->id(),
            'action'  => $actionText,
            'note'    => $catatan
        ]);

        return redirect()->route('pr.index')->with('success', 'Keputusan PR berhasil disimpan beserta rincian lognya.');
    }


    public function edit($slug)
    {
        // 1. Kembalikan URL ( _ ) menjadi nomor PR asli ( / )
        $prNumber = str_replace('_', '/', $slug);

        // 2. Cari ke database berdasarkan pr_number
        $pr = \App\Models\PurchaseRequest::with(['items.vendorQuotes', 'items.item', 'user', 'company'])
                ->where('pr_number', $prNumber)
                ->firstOrFail(); // <-- Jika tidak ketemu, baru 404

        // 3. Cek Status
        if (!in_array(optional($pr->status)->slug, ['pending_approval', 'draft'])) {
            return redirect()->route('pr.index')->with('error', 'Dokumen tidak dapat diedit karena sudah diproses.');
        }

        $items = \App\Models\Item::with(['uom', 'itemUoms'])->where('is_active', true)->get();
        $vendors = \App\Models\Vendor::where('is_active', true)->get();
        $companies = \App\Models\Company::all();
        $currencies = \App\Models\Currency::where('is_active', true)->get();
        $users = \App\Models\User::with('company')->orderBy('name')->get();

        return view('pr.edit', compact('pr', 'items', 'vendors', 'currencies', 'companies', 'users'));
    }

    public function update(Request $request, $slug)
    {
        // Terjemahkan slug kembali ke pr_number
        $prNumber = str_replace('_', '/', $slug);

        // Cari PR-nya
        $pr = \App\Models\PurchaseRequest::where('pr_number', $prNumber)->firstOrFail();

        $request->validate([
            'user_id'      => 'required|exists:users,id',
            'company_id'   => 'required|exists:companies,id',
            'request_date' => 'required|date',
            'need_date'    => 'required|date|after_or_equal:request_date',
            'description'  => 'required|string|max:500',
            'items'        => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty'     => 'required|numeric|min:0.01',
            'items.*.uom_id'  => 'required',
        ]);

        // $pr = \App\Models\PurchaseRequest::findOrFail($id);

        try {
            DB::transaction(function () use ($request, $pr) {
                // 1. UPDATE HEADER
                $pr->update([
                    'user_id'      => $request->user_id,
                    'company_id'   => $request->company_id,
                    'request_date' => $request->request_date,
                    'need_date'    => $request->need_date,
                    'description'  => $request->description,
                ]);

                $folderName = str_replace(['/', '\\'], '-', $pr->pr_number);
                $storagePath = "pr_uploads/" . $folderName;

                // 2. IDENTIFIKASI BARANG YANG DIHAPUS USER SAAT EDIT
                $submittedItemIds = collect($request->items)->pluck('id')->filter()->toArray();
                $itemsToDelete = $pr->items()->whereNotIn('id', $submittedItemIds)->get();

                foreach ($itemsToDelete as $delItem) {
                    foreach ($delItem->vendorQuotes as $quote) {
                        if ($quote->attachment) Storage::disk('public')->delete($quote->attachment);
                    }
                    $delItem->delete();
                }

                // 3. PROSES BARANG (UPDATE / CREATE)
                foreach ($request->items as $itemIndex => $itemData) {
                    $prItem = $pr->items()->updateOrCreate(
                        ['id' => $itemData['id'] ?? null], // Jika ada ID = Update, Jika null = Insert
                        [
                            'item_id' => $itemData['item_id'],
                            'qty'     => $itemData['qty'],
                            'uom_id'  => $itemData['uom_id'],
                        ]
                    );

                    // 4. PROSES VENDOR PER BARANG
                    if (isset($itemData['vendors']) && is_array($itemData['vendors'])) {
                        // Cari vendor yang dihapus di baris ini
                        $submittedVendorIds = collect($itemData['vendors'])->pluck('id')->filter()->toArray();
                        $quotesToDelete = $prItem->vendorQuotes()->whereNotIn('id', $submittedVendorIds)->get();

                        foreach ($quotesToDelete as $delQuote) {
                            if ($delQuote->attachment) Storage::disk('public')->delete($delQuote->attachment);
                            $delQuote->delete();
                        }

                        // Update atau Create Vendor
                        foreach ($itemData['vendors'] as $vIndex => $vData) {
                            if (empty($vData['vendor_id'])) continue;

                            $attachmentPath = null;
                            $existingQuote = !empty($vData['id']) ? DB::table('pr_item_vendors')->where('id', $vData['id'])->first() : null;
                            $attachmentPath = $existingQuote->attachment ?? null;

                            // Jika ada file baru di-upload, hapus yang lama, simpan yang baru
                            if ($request->hasFile("items.$itemIndex.vendors.$vIndex.file")) {
                                if ($attachmentPath) Storage::disk('public')->delete($attachmentPath);
                                $file = $request->file("items.$itemIndex.vendors.$vIndex.file");
                                $fileName = "item_{$itemData['item_id']}_v_{$vData['vendor_id']}_" . time() . "." . $file->getClientOriginalExtension();
                                $attachmentPath = $file->storeAs($storagePath, $fileName, 'public');
                            }

                            DB::table('pr_item_vendors')->updateOrInsert(
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
                                    'created_at'     => $existingQuote->created_at ?? now(),
                                ]
                            );
                        }
                    } else {
                        // Jika user menghapus semua vendor di baris ini
                        $prItem->vendorQuotes()->delete();
                    }
                }
            });



            $this->logHistory($pr->id, 'UPDATED', 'Melakukan revisi data PR dan lampiran vendor');
            return redirect()->route('pr.index')->with('success', 'PR berhasil diperbarui!');

        } catch (\Exception $e) {
            \Log::error('Update PR Error: ' . $e->getMessage());
            return back()->with('error', 'Gagal memperbarui PR: ' . $e->getMessage());
        }
    }



    public function print($slug)
    {
        // 1. Terjemahkan Slug kembali ke Nomor PR
        $prNumber = str_replace('_', '/', $slug);

        // 2. Cari ke database
        $pr = \App\Models\PurchaseRequest::with(['items.vendorQuotes.vendor', 'items.item.itemUoms', 'user', 'company', 'status'])
                ->where('pr_number', $prNumber)
                ->firstOrFail();

        // 3. Tampilkan halaman khusus cetak
        return view('pr.print', compact('pr'));
    }



    public function cancel(Request $request, $slug)
    {
        $prNumber = str_replace('_', '/', $slug);
        $pr = \App\Models\PurchaseRequest::where('pr_number', $prNumber)->firstOrFail();

        // 1. Validasi: Hanya bisa dibatalkan jika BELUM jadi PO
        // Dan hanya bisa dibatalkan jika statusnya belum 'Rejected' atau 'Cancelled'
        if (in_array(optional($pr->status)->slug, ['rejected', 'cancelled', 'completed'])) {
            return back()->with('error', 'Dokumen ini tidak dapat dibatalkan.');
        }

        $request->validate([
            'cancel_reason' => 'required|string|max:255'
        ]);

        try {
            DB::transaction(function () use ($request, $pr) {
                $statusCancelled = \App\Models\Status::where('type', 'PR')->where('slug', 'cancelled')->first();

                // 2. Update Status Header
                $pr->update([
                    'status_id' => $statusCancelled->id,
                    'cancellation_reason' => $request->cancel_reason // Pastikan kolom ini ada di DB
                ]);

                // 3. Update Semua Item menjadi CANCELLED
                $pr->items()->update(['status' => 'CANCELLED']);

                // 4. Catat di History
                $pr->histories()->create([
                    'user_id' => auth()->id(),
                    'action'  => 'Dibatalkan',
                    'note'    => 'PR Dibatalkan dengan alasan: ' . $request->cancel_reason
                ]);
            });

            return redirect()->route('pr.index')->with('success', 'Purchase Request berhasil dibatalkan.');

        } catch (\Exception $e) {
            return back()->with('error', 'Gagal membatalkan PR: ' . $e->getMessage());
        }
    }

    public function rejectAll(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $pr = PurchaseRequest::with('items.vendorQuotes')->findOrFail($id);
            $user = auth()->user();

            $reason = $request->input('reject_reason', 'Ditolak oleh atasan tanpa alasan spesifik');
            $roleName = ($user->hasRole('Director') || $user->role == 'director') ? 'Direktur' : 'Manager';

            $statusDb = \App\Models\Status::where('type', 'PR')->where('slug', 'rejected')->first();

            if ($statusDb) {
                $pr->update([
                    'status_id' => $statusDb->id,
                    'current_approval_level' => 0
                ]);

                foreach ($pr->items as $item) {
                    $item->update([
                        'status' => 'REJECTED',
                        'rejection_reason' => $reason
                    ]);
                    \App\Models\VendorQuote::where('purchase_request_item_id', $item->id)->update(['is_selected' => 0]);
                }

                \App\Models\PurchaseRequestHistory::create([
                    'purchase_request_id' => $pr->id,
                    'user_id' => $user->id,
                    'action' => 'REJECTED',
                    'note' => "❌ {$roleName} MENOLAK seluruh permintaan PR.\nAlasan: " . $reason,
                ]);

                // 🔔 NOTIF KE PEMBUAT PR
                $pembuatPR = User::find($pr->user_id);
                if ($pembuatPR) {
                    $pembuatPR->notify(new DocumentApprovalNotification(
                        'PR Ditolak ❌', "Mohon maaf, PR Nomor {$pr->pr_number} ditolak secara keseluruhan oleh {$roleName}.", route('pr.show', $pr->id)
                    ));
                }

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
            $item = \App\Models\PurchaseRequestItem::findOrFail($id);
            $prId = $item->purchase_request_id;
            $reason = $request->input('reason', '-');

            DB::table('purchase_request_items')
                ->where('id', $id)
                ->update([
                    'status' => 'FORCE_CLOSED',
                    'rejection_reason' => 'Sisa kuantitas digugurkan. Catatan: ' . $reason,
                    'updated_at' => now(),
                ]);

            $itemName = $item->item->name ?? 'Item #' . $item->id;
            $sisa = $item->qty - ($item->ordered_qty ?? 0);

            \App\Models\PurchaseRequestHistory::create([
                'purchase_request_id' => $prId,
                'user_id' => auth()->id(),
                'action' => 'SHORT CLOSED',
                'note' => "Menutup sisa pesanan untuk {$itemName} sebanyak {$sisa} {$item->uom}.\nAlasan: " . $reason,
            ]);

            $pr = \App\Models\PurchaseRequest::with('items')->findOrFail($prId);

            $allFulfilled = true;
            foreach($pr->items as $prItem) {
                if ($prItem->status === 'APPROVED') {
                    $ordered = $prItem->ordered_qty ?? 0;
                    if ($ordered < $prItem->qty) {
                        $allFulfilled = false;
                        break;
                    }
                }
            }

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

    private function logHistory($prId, $action, $note = null)
    {
        \App\Models\PurchaseRequestHistory::create([
            'purchase_request_id' => $prId,
            'user_id' => auth()->id(),
            'action'  => $action,
            'note'    => $note
        ]);
    }
}
