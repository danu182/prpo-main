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
use App\Models\Status;
use App\Models\VendorQuote;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\User;
use App\Notifications\DocumentApprovalNotification;
use App\Models\ApprovalWorkflow;
use App\Models\DocumentApproval;
use App\Services\SystemSettingService;
use PDF;

class PurchaseRequestController extends Controller
{
    use InteractsWithMedia;


    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $departmentId = $request->input('department_id');
        $user = auth()->user();

        // 1. Tarik semua Role ID yang dimiliki user saat ini
        $userRoleIds = $user->roles->pluck('id')->toArray();

        $query = \App\Models\PurchaseRequest::with(['requester', 'department', 'approvals.role'])
            ->when($search, function ($q) use ($search) {
                $q->where('pr_number', 'like', "%{$search}%")
                  ->orWhereHas('requester', function ($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%");
                  });
            })
            ->when($status, function ($q) use ($status) {
                $q->whereHas('status', function ($qStatus) use ($status) {
                    $qStatus->where('name', $status);
                });
            })
            ->when($departmentId, function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });

        // 🔥 LOGIKA VISIBILITAS (PINTU AKSES) 🔥
        if (!$user->hasAnyRole(['Super Admin', 'super-admin'])) {
            $query->where(function ($q) use ($user, $userRoleIds) {
                // A. Pembuat PR
                $q->where('user_id', $user->id)

                  // B. Approver yang sedang ditunggu
                  ->orWhereHas('approvals', function ($qApprovals) use ($userRoleIds) {
                      $qApprovals->where('status', 'PENDING')
                                 ->whereIn('role_id', $userRoleIds);
                  })

                  // C. Riwayat Approver
                  ->orWhereHas('approvals', function ($qApprovals) use ($user) {
                      $qApprovals->where('approved_by', $user->id);
                  });
            });
        }

        // PERUBAHAN: Gunakan nama variabel $requests agar sesuai dengan Blade Anda!
        $requests = $query->latest()->paginate(10)->withQueryString();

        // 🔥 INI OBATNYA: Mengirim variabel $statuses dan $companies yang dicari Blade 🔥
        $statuses = \App\Models\Status::where('type', 'PR')->orderBy('sequence')->get();
        $companies = \App\Models\Company::orderBy('name')->get();
        $departments = \App\Models\Department::orderBy('name')->get();

        return view('pr.index', compact('requests', 'search', 'status', 'statuses', 'companies', 'departments'));
    }


    // 1. FUNGSI CREATE (DIKOSONGKAN DARI $items AGAR RINGAN)
    public function create()
    {
        // ❌ Hapus pemanggilan $items di sini agar tidak berat!
        $vendors = \App\Models\Vendor::where('is_active', true)->get();
        $companies = \App\Models\Company::all();
        $currencies = \App\Models\Currency::where('is_active', true)->get();
        $users = \App\Models\User::with('company')->orderBy('name')->get();

        // Tidak perlu lagi melempar 'items'
        return view('pr.create', compact('companies', 'vendors', 'currencies', 'users'));
    }

    // ==========================================
    // FUNGSI PENCARIAN BARANG AJAX (TAHAN BANTING)
    // ==========================================
    public function searchItems(Request $request)
    {
        $search = $request->search;

        // 🔥 HANYA PANGGIL 'uom' DASAR, JANGAN PANGGIL RELASI 'itemUoms' AGAR TIDAK ERROR 500 🔥
        $items = \App\Models\Item::with('uom')
            ->where('is_active', true)
            ->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            })
            ->limit(20)
            ->get();

        $formattedItems = [];
        foreach ($items as $item) {
            $uomList = [];
            $baseUomName = optional($item->uom)->name ?? 'Unit';

            // 1. Masukkan Satuan Dasar
            $uomList[] = [
                'id' => $item->uom_id ?? '',
                'name' => $baseUomName,
                'isi' => 1,
                'base' => $baseUomName
            ];

            // 2. Tarik Kemasan Alternatif Manual dari Database (Sama seperti logika di GI)
            try {
                $altUoms = \Illuminate\Support\Facades\DB::table('item_uoms')
                    ->where('item_id', $item->id)
                    ->select('id', 'uom_name', 'conversion_qty')
                    ->get();

                foreach ($altUoms as $alt) {
                    $uomList[] = [
                        'id' => $alt->id,
                        'name' => $alt->uom_name,
                        'isi' => $alt->conversion_qty,
                        'base' => $baseUomName
                    ];
                }
            } catch (\Exception $e) {
                // Abaikan jika tabel item_uoms bermasalah, agar pencarian tetap berjalan
            }

            $formattedItems[] = [
                'id' => $item->id,
                'text' => '[' . $item->code . '] ' . $item->name,
                'uoms' => $uomList // Bawa data UOM ke Javascript
            ];
        }

        return response()->json($formattedItems);
    }

    private function generatePrNumber($companyId)
    {
        $company = \App\Models\Company::find($companyId);
        $code = $company && $company->code ? $company->code : 'HO';

        $now = now();
        // 🔥 UBAH FORMAT TANGGAL JADI YYYYMMDD DAN PEMISAH JADI STRIP (-) 🔥
        $dateStr = $now->format('Ymd');
        $prefix = "PR-{$code}-{$dateStr}-";

        $lastPr = \App\Models\PurchaseRequest::where('pr_number', 'like', $prefix . '%')
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

    // ==========================================
    // STORE PR (SIMPAN DATA BARU)
    // ==========================================
    public function store(Request $request, \App\Services\SystemSettingService $settingService)
    {
        // 🔥 SABUK PENGAMAN DITAMBAHKAN DI SINI 🔥
        $request->validate([
            'user_id'         => 'required|exists:users,id',
            'company_id'      => 'required|exists:companies,id',
            'request_date'    => 'required|date',
            'need_date'       => 'required|date|after_or_equal:request_date',
            'description'     => 'required|string|max:500',
            'items'           => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty'     => 'required|numeric|min:0.01',
            'items.*.uom_id'  => 'required|integer',
            // 🔥 TAMBAHAN: Validasi kolom spesifikasi agar aman masuk database 🔥
            'items.*.specification' => 'nullable|string',
            // Validasi file: maksimal 5MB (5120 KB) per file lampiran
            'items.*.vendors.*.files.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',
        ], [
            'items.*.vendors.*.files.*.max' => 'Ukuran salah satu file lampiran melebihi batas maksimal 5 MB!'
        ]);

        try {
            DB::transaction(function () use ($request, $settingService) {
                $newPrNumber = $this->generatePrNumber($request->company_id);

                $workflow = \App\Models\ApprovalWorkflow::with(['steps' => function($q) {
                                $q->orderBy('step_order', 'asc');
                            }])->where('document_type', PurchaseRequest::class)->where('is_active', true)->first();

                if (!$workflow || $workflow->steps->isEmpty()) {
                    throw new \Exception('Sistem Gagal: Matriks Persetujuan PR belum dikonfigurasi!');
                }

                $estimasiGrandTotal = 0;
                foreach ($request->items as $itemData) {
                    $hargaTermurah = 0;
                    if (isset($itemData['vendors']) && is_array($itemData['vendors'])) {
                        $daftarHarga = array_column($itemData['vendors'], 'price');
                        $hargaTermurah = !empty($daftarHarga) ? min($daftarHarga) : 0;
                    }
                    $estimasiGrandTotal += ($itemData['qty'] * $hargaTermurah);
                }

                $validSteps = $workflow->steps->filter(function($step) use ($estimasiGrandTotal) { return $estimasiGrandTotal >= $step->min_amount; });
                $isAutoApprove = $validSteps->isEmpty();
                $initialStatusSlug = $isAutoApprove ? 'approved' : 'pending_approval';

                $initialStatus = \App\Models\Status::where('type', 'PR')->where('slug', $initialStatusSlug)->first();

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

                if (!$isAutoApprove) {
                    $urutanAktual = 1;
                    foreach ($validSteps as $step) {
                        \App\Models\DocumentApproval::create([
                            'document_id'   => $pr->id,
                            'document_type' => get_class($pr),
                            'step_order'    => $urutanAktual++,
                            'role_id'       => $step->role_id,
                            'status'        => 'PENDING'
                        ]);
                    }
                }

                foreach ($request->items as $itemIndex => $itemData) {
                    $uomName = 'PCS';
                    $masterItem = \App\Models\Item::with('uom', 'itemUoms')->find($itemData['item_id']);
                    if ($masterItem) {
                        if ($masterItem->uom_id == $itemData['uom_id']) { $uomName = $masterItem->uom->name; }
                        else { $altUom = $masterItem->itemUoms->where('id', $itemData['uom_id'])->first(); $uomName = $altUom ? $altUom->uom_name : 'PCS'; }
                    }

                    $prItem = \App\Models\PurchaseRequestItem::create([
                        'purchase_request_id' => $pr->id,
                        'item_id'             => $itemData['item_id'],
                        // 🔥 TAMBAHAN: MENYIMPAN DATA SPESIFIKASI DARI FORM 🔥
                        'specification'       => $itemData['specification'] ?? null,
                        'qty'                 => $itemData['qty'],
                        'uom_id'              => $itemData['uom_id'],
                        'uom'                 => $uomName,
                        'estimated_price'     => 0,
                        'status'              => 'PENDING'
                    ]);

                    if (isset($itemData['vendors']) && is_array($itemData['vendors'])) {
                        foreach ($itemData['vendors'] as $vendorIndex => $quoteData) {
                            if (!empty($quoteData['vendor_id'])) {
                                $currencyObj = \App\Models\Currency::where('code', $quoteData['currency'] ?? 'IDR')->first();

                                // 1. INSERT KE TABEL PIVOT VENDOR
                                $quoteId = DB::table('purchase_request_item_vendors')->insertGetId([
                                    'pr_item_id'     => $prItem->id,
                                    'vendor_id'      => $quoteData['vendor_id'],
                                    'currency_id'    => $currencyObj ? $currencyObj->id : null,
                                    'quoted_price'   => $quoteData['price'] ?? 0,
                                    'reference_link' => $quoteData['link'] ?? null,
                                    'notes'          => $quoteData['notes'] ?? null,
                                    'created_at'     => now(),
                                    'updated_at'     => now(),
                                ]);

                                // 2. INSERT MULTI-FILE KE TABEL LAMPIRAN BARU
                                if ($request->hasFile("items.$itemIndex.vendors.$vendorIndex.files")) {
                                    $safePrNumber = str_replace(['/', '\\'], '-', $newPrNumber);

                                    // 🔥 AMBIL PATH DINAMIS DARI TABEL SYSTEM_SETTINGS SESUAI KEY 🔥
                                    $settingPath = \Illuminate\Support\Facades\DB::table('system_settings')
                                                        ->where('setting_key', 'path_pr_attachment')
                                                        ->value('setting_value');

                                    // Gunakan path dari DB, jika kosong/gagal gunakan fallback default
                                    $basePath = $settingPath ? $settingPath : 'attachments/purchase_requests';
                                    $targetFolder = $basePath . '/' . $safePrNumber;


                                    $attachmentsData = [];

                                    foreach ($request->file("items.$itemIndex.vendors.$vendorIndex.files") as $file) {
                                        $originalName = $file->getClientOriginalName();
                                        $fileName = "v_" . $quoteData['vendor_id'] . "_" . uniqid() . "." . $file->getClientOriginalExtension();

                                        $path = $file->storeAs($targetFolder, $fileName, 'public');
                                        $cleanPath = str_replace('\\', '/', $path);

                                        $attachmentsData[] = [
                                            'pr_item_vendor_id' => $quoteId,
                                            'file_name'         => $originalName,
                                            'file_path'         => $cleanPath,
                                            'created_at'        => now(),
                                            'updated_at'        => now(),
                                        ];
                                    }
                                    DB::table('pr_vendor_attachments')->insert($attachmentsData);
                                }
                            }
                        }
                    }
                }

                if ($isAutoApprove) {
                    $this->logHistory($pr->id, 'AUTO-APPROVED', "PR disetujui otomatis.");
                } else {
                    $this->logHistory($pr->id, 'CREATED', "PR {$newPrNumber} diajukan.");
                }
            });

            return redirect()->route('pr.index')->with('success', 'PR Berhasil Diajukan beserta seluruh data Vendor!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal: ' . $e->getMessage());
        }
    }

    // public function edit($slug)
    // {
    //     $pr = \App\Models\PurchaseRequest::with(['items.vendorQuotes.attachments', 'items.item', 'user', 'company'])
    //             ->where('pr_number', $slug)->firstOrFail();

    //             // return $pr;
    //     if (optional($pr->status)->slug !== 'pending_approval' || $pr->current_approval_level > 0) {
    //         return redirect()->route('pr.index')->with('error', 'Dokumen tidak dapat diedit karena sudah masuk tahap persetujuan.');
    //     }

    //     $items = \App\Models\Item::with(['uom', 'itemUoms'])->where('is_active', true)->get();
    //     $vendors = \App\Models\Vendor::where('is_active', true)->get();
    //     $companies = \App\Models\Company::all();
    //     $currencies = \App\Models\Currency::where('is_active', true)->get();
    //     $users = \App\Models\User::with('company')->orderBy('name')->get();

    //     return view('pr.edit', compact('pr', 'items', 'vendors', 'currencies', 'companies', 'users'));
    // }

    // ==========================================
    // 1. HALAMAN EDIT PR (MENGGUNAKAN SLUG)
    // ==========================================
    public function edit($slug)
    {
        // 🔥 PERBAIKAN: Cari berdasarkan pr_number (slug) bukan ID 🔥
        $pr = \App\Models\PurchaseRequest::with([
            'items.item.uom',
            'items.item.itemUoms',
            'items.vendorQuotes.attachments'
        ])->where('pr_number', $slug)->firstOrFail();

        $companies = \App\Models\Company::all();
        $currencies = \App\Models\Currency::where('is_active', true)->get();
        $vendors = \App\Models\Vendor::where('is_active', true)->get();
        $users = \App\Models\User::orderBy('name')->get();

        return view('pr.edit', compact('pr', 'companies', 'currencies', 'vendors', 'users'));
    }

    // ==========================================
    // PROSES UPDATE PR (ANTI-HILANG FILE)
    // ==========================================
    public function update(Request $request, $slug, \App\Services\SystemSettingService $settingService)
    {
        $request->validate([
            'user_id'         => 'required|exists:users,id',
            'company_id'      => 'required|exists:companies,id',
            'request_date'    => 'required|date',
            'need_date'       => 'required|date|after_or_equal:request_date',
            'description'     => 'required|string|max:500',
            'items'           => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty'     => 'required|numeric|min:0.01',
            'items.*.specification' => 'nullable|string',
        ]);

        try {
            DB::transaction(function () use ($request, $slug, $settingService) {
                $pr = \App\Models\PurchaseRequest::where('pr_number', $slug)->firstOrFail();

                $pr->update([
                    'user_id'      => $request->user_id,
                    'company_id'   => $request->company_id,
                    'request_date' => $request->request_date,
                    'need_date'    => $request->need_date,
                    'description'  => $request->description,
                ]);

                // 🔥 LANGKAH PENYELAMATAN: AMBIL DATA FILE LAMA SEBELUM DIHAPUS 🔥
                $keptFileIds = [];
                foreach ($request->items as $itemData) {
                    if (isset($itemData['vendors'])) {
                        foreach ($itemData['vendors'] as $vData) {
                            if (!empty($vData['existing_files'])) {
                                $keptFileIds = array_merge($keptFileIds, $vData['existing_files']);
                            }
                        }
                    }
                }
                // Simpan data file yang dipertahankan ke dalam memory (Kunci Anti-Hilang)
                $savedFilesData = DB::table('pr_vendor_attachments')->whereIn('id', $keptFileIds)->get()->keyBy('id');


                // BERSINKAN RECORD LAMA
                $oldItems = $pr->items;
                foreach($oldItems as $oldItem) {
                    // Cek kedua nama relasi untuk keamanan (vendorQuotes atau vendors)
                    $oldVendors = $oldItem->vendorQuotes ?? $oldItem->vendors ?? [];
                    foreach($oldVendors as $oldVendor) {
                        DB::table('pr_vendor_attachments')->where('pr_item_vendor_id', $oldVendor->id)->delete();
                    }
                    DB::table('purchase_request_item_vendors')->where('pr_item_id', $oldItem->id)->delete();
                    $oldItem->delete();
                }

                // MASUKKAN ITEM & VENDOR BARU
                foreach ($request->items as $itemIndex => $itemData) {
                    $uomName = 'Unit';
                    $masterItem = \App\Models\Item::with('uom', 'itemUoms')->find($itemData['item_id']);
                    if ($masterItem) {
                        if ($masterItem->uom_id == $itemData['uom_id']) { $uomName = optional($masterItem->uom)->name ?? 'Unit'; }
                        else { $altUom = $masterItem->itemUoms->where('id', $itemData['uom_id'])->first(); $uomName = $altUom ? $altUom->uom_name : 'Unit'; }
                    }

                    $prItem = \App\Models\PurchaseRequestItem::create([
                        'purchase_request_id' => $pr->id,
                        'item_id'             => $itemData['item_id'],
                        'specification'       => $itemData['specification'] ?? null,
                        'qty'                 => $itemData['qty'],
                        'uom_id'              => $itemData['uom_id'],
                        'uom'                 => $uomName,
                        'status'              => 'PENDING'
                    ]);

                    if (isset($itemData['vendors'])) {
                        foreach ($itemData['vendors'] as $vIdx => $vData) {
                            $currencyObj = \App\Models\Currency::where('code', $vData['currency'] ?? 'IDR')->first();

                            $quoteId = DB::table('purchase_request_item_vendors')->insertGetId([
                                'pr_item_id'     => $prItem->id,
                                'vendor_id'      => $vData['vendor_id'],
                                'currency_id'    => $currencyObj->id ?? null,
                                'quoted_price'   => $vData['price'] ?? 0,
                                'reference_link' => $vData['link'] ?? null,
                                'notes'          => $vData['notes'] ?? null,
                                'created_at'     => now(), 'updated_at' => now(),
                            ]);

                            // 🔥 KEMBALIKAN FILE LAMA YANG ADA DI MEMORY TADI 🔥
                            if (!empty($vData['existing_files'])) {
                                foreach ($vData['existing_files'] as $oldFileId) {
                                    if ($savedFilesData->has($oldFileId)) {
                                        $oldFile = $savedFilesData->get($oldFileId);
                                        DB::table('pr_vendor_attachments')->insert([
                                            'pr_item_vendor_id' => $quoteId,
                                            'file_name'         => $oldFile->file_name,
                                            'file_path'         => $oldFile->file_path,
                                            'created_at'        => now(), 'updated_at' => now(),
                                        ]);
                                    }
                                }
                            }

                            // 🔥 SIMPAN FILE BARU JIKA ADA 🔥
                            if ($request->hasFile("items.$itemIndex.vendors.$vIdx.files")) {
                                $settingPath = \Illuminate\Support\Facades\DB::table('system_settings')->where('setting_key', 'path_pr_attachment')->value('setting_value');
                                $basePath = $settingPath ? $settingPath : 'attachments/purchase_requests';
                                $targetFolder = $basePath . '/' . str_replace(['/', '\\'], '-', $pr->pr_number);

                                foreach ($request->file("items.$itemIndex.vendors.$vIdx.files") as $file) {
                                    $fileName = "v_" . $vData['vendor_id'] . "_" . uniqid() . "." . $file->getClientOriginalExtension();
                                    $path = $file->storeAs($targetFolder, $fileName, 'public');

                                    DB::table('pr_vendor_attachments')->insert([
                                        'pr_item_vendor_id' => $quoteId,
                                        'file_name'         => $file->getClientOriginalName(),
                                        'file_path'         => str_replace('\\', '/', $path),
                                        'created_at' => now(), 'updated_at' => now(),
                                    ]);
                                }
                            }
                        }
                    }
                }

                $this->logHistory($pr->id, 'UPDATED', "Data PR diperbarui oleh " . auth()->user()->name);
            });

            return redirect()->route('pr.index')->with('success', 'Perubahan PR Berhasil Disimpan & File Aman!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal Update: ' . $e->getMessage());
        }
    }


    // ========================================================
    // 1. FUNGSI SHOW
    // ========================================================
    public function show(string $slug)
    {
        $pr = \App\Models\PurchaseRequest::with([
                    'items.vendorQuotes.vendor',
                    'items.vendorQuotes.attachments',
                    'items.item.uom',       // ⚡ WAJIB: Tarik satuan dasar (PCS)
                    'items.item.itemUoms',  // ⚡ WAJIB: Tarik satuan konversi (PACK, BOX)
                    'user',
                    'company',
                    'histories.user',
                    'status'
                ])
                ->where('pr_number', $slug)
                ->firstOrFail();

        $currencySymbols = \App\Models\Currency::pluck('symbol', 'code')->toArray();
        $isEditable = in_array(optional($pr->status)->slug, ['pending_approval', 'draft']);

        $user = auth()->user();
        $currentApproval = \App\Models\DocumentApproval::where('document_id', $pr->id)
            ->where('document_type', get_class($pr))
            ->where('status', 'PENDING')
            ->orderBy('step_order', 'asc')
            ->first();

        $canApprove = false;
        $currentRoleName = null;

        if ($currentApproval) {
            $currentRoleName = $currentApproval->role->name;
            if ($user->hasRole($currentRoleName) || $user->hasRole('Super Admin')) {
                $canApprove = true;
            }
        }

        return view('pr.show', compact('pr', 'currencySymbols', 'isEditable', 'canApprove', 'currentRoleName'));
    }


    public function decide(Request $request, string $slug)
    {
        $pr = PurchaseRequest::where('pr_number', $slug)->firstOrFail();
        $pembuatPR = User::find($pr->user_id);

        foreach(auth()->user()->unreadNotifications as $notification) {
            if(isset($notification->data['url']) && str_contains($notification->data['url'], route('pr.show', $pr->id))) {
                $notification->markAsRead();
            }
        }

        // 🔥 PERBAIKAN 1: Wajib menggunakan with('role') agar bisa mengambil nama jabatan
        $currentApproval = \App\Models\DocumentApproval::with('role')
            ->where('document_id', $pr->id)
            ->where('document_type', get_class($pr))
            ->where('status', 'PENDING')
            ->orderBy('step_order', 'asc')
            ->first();

        if (!$currentApproval && $request->global_action !== 'REJECT') {
            return redirect()->back()->with('error', 'Dokumen ini tidak sedang menunggu persetujuan Anda.');
        }

        // 🔥 PERBAIKAN 2: TEMBOK BESI ANTI BYPASS OTORITAS 🔥
        if ($currentApproval && !auth()->user()->hasRole($currentApproval->role->name) && !auth()->user()->hasRole('Super Admin')) {
            return redirect()->back()->with('error', 'AKSES DITOLAK: Giliran persetujuan saat ini adalah wewenang ' . $currentApproval->role->name . '. Anda tidak memiliki hak akses!');
        }

        $approverRoleName = $currentApproval ? $currentApproval->role->name : 'Atasan';

        // ==========================================
        // EKSEKUSI PENOLAKAN GLOBAL
        // ==========================================
        if ($request->global_action === 'REJECT') {
            if ($currentApproval) {
                $currentApproval->update(['status' => 'REJECTED', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            }
            $statusRejected = \App\Models\Status::where('type', 'PR')->where('slug', 'rejected')->first();
            if ($statusRejected) $pr->update(['status_id' => $statusRejected->id, 'current_approval_level' => 0]);

            foreach ($pr->items as $item) {
                $item->update(['status' => 'REJECTED', 'rejection_reason' => 'Ditolak secara global oleh ' . auth()->user()->name]);
            }

            $this->logHistory($pr->id, 'Ditolak', "Dokumen PR ditolak secara keseluruhan oleh " . auth()->user()->name . " ($approverRoleName)");

            if ($pembuatPR) {
                $pembuatPR->notify(new DocumentApprovalNotification('PR Ditolak ❌', "Mohon maaf, PR Nomor {$pr->pr_number} ditolak secara global oleh {$approverRoleName}.", route('pr.show', $pr->id)));
            }

            return redirect()->route('pr.index')->with('error', 'Purchase Request ditolak secara keseluruhan.');
        }

        // ==========================================
        // EKSEKUSI PERSETUJUAN PER ITEM
        // ==========================================
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

        // Jika semua item ditolak, tolak dokumen secara keseluruhan
        if ($totalApprovedItems === 0) {
            $currentApproval->update(['status' => 'REJECTED', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            $statusRejected = \App\Models\Status::where('type', 'PR')->where('slug', 'rejected')->first();
            if ($statusRejected) $pr->update(['status_id' => $statusRejected->id, 'current_approval_level' => 0]);

            $this->logHistory($pr->id, 'Ditolak (Otomatis)', "PR ditolak otomatis karena semua item ditolak oleh " . auth()->user()->name . " ($approverRoleName)");

            if ($pembuatPR) $pembuatPR->notify(new DocumentApprovalNotification('PR Ditolak ❌', "Semua item pada PR Nomor {$pr->pr_number} telah ditolak.", route('pr.show', $pr->id)));

            return redirect()->route('pr.index')->with('error', 'PR ditolak karena semua item di dalamnya ditolak.');
        }

        // ==========================================
        // DOKUMEN LOLOS, LANJUTKAN WORKFLOW MATRIKS
        // ==========================================
        $currentApproval->update(['status' => 'APPROVED', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        $pr->update(['current_approval_level' => $currentApproval->step_order]);

        $nextApproval = \App\Models\DocumentApproval::where('document_id', $pr->id)
            ->where('document_type', get_class($pr))
            ->where('status', 'PENDING')
            ->orderBy('step_order', 'asc')
            ->first();

        $actionText = 'Disetujui (' . strtoupper($approverRoleName) . ')';
        $catatan = "Dokumen disetujui pada tahap ini.\n";

        if ($nextApproval) {
            $nextRoleName = $nextApproval->role->name;
            $nextApprovers = \App\Models\User::role($nextRoleName)->get();

            foreach ($nextApprovers as $approver) {
                $approver->notify(new DocumentApprovalNotification('PR Butuh Persetujuan Lapis Lanjutan 📝', "PR Nomor {$pr->pr_number} telah disetujui lapis sebelumnya dan kini butuh persetujuan Anda sebagai {$nextRoleName}.", route('pr.show', $pr->id)));
            }
            $catatan .= "Diteruskan ke Lapis Berikutnya: **" . strtoupper($nextRoleName) . "**\n";
            $successMsg = "Disetujui! Dokumen telah diteruskan ke $nextRoleName.";
        } else {
            // JIKA SUDAH FINAL
            $statusFinal = \App\Models\Status::where('type', 'PR')->where('slug', 'approved')->first();
            if ($statusFinal) $pr->update(['status_id' => $statusFinal->id]);

            $actionText = 'Disetujui Final';
            $catatan .= "Persetujuan Matriks telah SELESAI. Dokumen Siap di-PO-kan.\n";
            $successMsg = "Hore! Dokumen ini telah disetujui secara FINAL!";

            if ($pembuatPR) $pembuatPR->notify(new DocumentApprovalNotification('PR Disetujui ✅', "Hore! PR Nomor {$pr->pr_number} telah disetujui Final.", route('pr.show', $pr->id)));

            $timPurchasing = User::role(['Super Admin', 'Purchasing'])->get();
            foreach($timPurchasing as $purchasing) {
                $purchasing->notify(new DocumentApprovalNotification('PR Baru Siap Di-PO 🛒', "PR Nomor {$pr->pr_number} telah disetujui final dan siap dibuatkan PO.", route('pr.show', $pr->id)));
            }
        }

        if (count($rejectedDetails) > 0) $catatan .= "\n**Daftar Item Ditolak:**\n" . implode("\n", $rejectedDetails) . "\n";
        if (count($vendorDetails) > 0) $catatan .= "\n**Rekomendasi Vendor (Sifat Opsional/Referensi):**\n" . implode("\n", $vendorDetails) . "\n";

        $this->logHistory($pr->id, $actionText, $catatan);

        return redirect()->route('pr.index')->with('success', $successMsg);
    }

    public function rejectAll(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $pr = PurchaseRequest::with('items.vendorQuotes')->findOrFail($id);
            $user = auth()->user();
            $reason = $request->input('reject_reason', 'Ditolak oleh atasan tanpa alasan spesifik');

            $currentApproval = \App\Models\DocumentApproval::where('document_id', $pr->id)
                ->where('document_type', get_class($pr))
                ->where('status', 'PENDING')
                ->orderBy('step_order', 'asc')
                ->first();

            $roleName = $currentApproval ? $currentApproval->role->name : 'Atasan';

            if ($currentApproval) {
                $currentApproval->update(['status' => 'REJECTED', 'approved_by' => auth()->id(), 'approved_at' => now(), 'note' => $reason]);
            }

            $statusDb = \App\Models\Status::where('type', 'PR')->where('slug', 'rejected')->first();

            if ($statusDb) {
                $pr->update(['status_id' => $statusDb->id, 'current_approval_level' => 0]);

                foreach ($pr->items as $item) {
                    $item->update(['status' => 'REJECTED', 'rejection_reason' => $reason]);
                    \App\Models\VendorQuote::where('purchase_request_item_id', $item->id)->update(['is_selected' => 0]);
                }

                \App\Models\PurchaseRequestHistory::create([
                    'purchase_request_id' => $pr->id,
                    'user_id' => $user->id,
                    'action' => 'REJECTED',
                    'note' => "❌ " . strtoupper($roleName) . " MENOLAK seluruh permintaan PR.\nAlasan: " . $reason,
                ]);

                $pembuatPR = User::find($pr->user_id);
                if ($pembuatPR) {
                    $pembuatPR->notify(new DocumentApprovalNotification('PR Ditolak ❌', "Mohon maaf, PR Nomor {$pr->pr_number} ditolak secara keseluruhan oleh {$roleName}.", route('pr.show', $pr->id)));
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

    public function print($slug)
    {

        // 1. Tarik data PR Utama
        $pr = \App\Models\PurchaseRequest::with(['items.vendorQuotes.vendor', 'items.item.itemUoms', 'user', 'company', 'status'])
                ->where('pr_number', $slug)
                ->firstOrFail();

        // 2. Tarik data Matriks Persetujuan yang dinamis untuk PR ini
        // Kita gabungkan dengan data Role (Jabatan) dan data User yang melakukan ACC
        $approvals = \App\Models\DocumentApproval::with(['role', 'approver'])
                ->where('document_id', $pr->id)
                ->where('document_type', get_class($pr))
                ->orderBy('step_order', 'asc')
                ->get();

        return view('pr.print', compact('pr', 'approvals'));
    }

    public function cancel(Request $request, $slug)
    {
        $pr = \App\Models\PurchaseRequest::where('pr_number', $slug)->firstOrFail();

        $currentStatusSlug = strtolower(optional($pr->status)->slug);
        $unallowableStatuses = ['po_issued', 'partial_po', 'canceled', 'cancelled', 'batal', 'dibatalkan', 'rejected', 'ditolak', 'completed'];

        if (in_array($currentStatusSlug, $unallowableStatuses)) {
            return redirect()->back()->with('error', 'AKSES DITOLAK: Dokumen ini sudah dalam status terkunci (Selesai/Batal).');
        }

        $request->validate(['cancel_reason' => 'required|string|max:255']);

        try {
            DB::transaction(function () use ($request, $pr) {
                \App\Models\DocumentApproval::where('document_id', $pr->id)
                    ->where('document_type', get_class($pr))
                    ->where('status', 'PENDING')
                    ->update(['status' => 'REJECTED', 'note' => 'Dokumen Dibatalkan.']);

                $statusCancelled = \App\Models\Status::where('type', 'PR')->where('slug', 'cancelled')->first();

                $pr->update([
                    'status_id' => $statusCancelled->id,
                    'cancellation_reason' => $request->cancel_reason
                ]);

                $pr->items()->update(['status' => 'CANCELLED']);

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
                        ->update(['status_id' => $statusTarget->id, 'updated_at' => now()]);
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
