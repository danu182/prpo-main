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
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class PurchaseRequestController extends Controller
{
    use InteractsWithMedia;

    // =========================================================================
    // DAFTAR PR (DENGAN GEMBOK PRIVASI & BYPASS SUPER ADMIN)
    // =========================================================================
    public function index(\Illuminate\Http\Request $request)
    {
        $search = $request->input('search');
        $statusFilter = $request->input('status');
        $departmentFilter = $request->input('department');

        $user = auth()->user();
        $userRoleNames = $user->getRoleNames()->toArray();
        $isSuperAdmin = in_array('Super Admin', $userRoleNames) || in_array('Super Administrator', $userRoleNames) || $user->id === 1;
        $userRoleIds = $user->roles->pluck('id')->toArray();

        $query = \App\Models\PurchaseRequest::with(['user', 'status', 'company', 'purchaseOrders']);

        if (!$isSuperAdmin) {
            $query->where(function ($q) use ($user, $userRoleIds) {
                $q->where('purchase_requests.user_id', $user->id);

                if (!empty($userRoleIds)) {
                    $q->orWhereExists(function ($subQuery) use ($user, $userRoleIds) {
                        $subQuery->select(\DB::raw(1))
                                 ->from('document_approvals')
                                 ->join('users as pembuat', 'pembuat.id', '=', 'purchase_requests.user_id')
                                 ->whereColumn('document_approvals.document_id', 'purchase_requests.id')
                                 ->whereIn('document_approvals.document_type', [\App\Models\PurchaseRequest::class, 'PR', 'PurchaseRequest'])
                                 ->whereIn('document_approvals.role_id', $userRoleIds)
                                 ->where(function ($deptQ) use ($user) {
                                     $deptQ->where('document_approvals.target_department_id', $user->department_id)
                                           ->orWhere('document_approvals.target_department_id', 'all')
                                           ->orWhere(function ($emptyDeptQ) use ($user) {
                                               $emptyDeptQ->where(function($qNull) {
                                                    $qNull->whereNull('document_approvals.target_department_id')
                                                          ->orWhere('document_approvals.target_department_id', '');
                                               })->where('pembuat.department_id', $user->department_id);
                                           });
                                 });
                    });
                }
            });
        }

        $query->when($search, function ($q) use ($search) {
            $q->where(function($subQ) use ($search) {
                $subQ->where('pr_number', 'like', "%{$search}%")
                     ->orWhereHas('user', function ($userQuery) use ($search) {
                         $userQuery->where('name', 'like', "%{$search}%");
                     });
            });
        });

        $query->when($statusFilter, function ($q) use ($statusFilter) {
            $q->whereHas('status', function ($statusQuery) use ($statusFilter) {
                $statusQuery->where('name', $statusFilter);
            });
        });

        $query->when($departmentFilter, function ($q) use ($departmentFilter) {
            $q->whereHas('company', function ($companyQuery) use ($departmentFilter) {
                $companyQuery->where('name', $departmentFilter);
            });
        });

        $requests = $query->latest()->paginate(10);
        $statuses = \App\Models\Status::where('type', 'PR')->get();
        $companies = \App\Models\Company::orderBy('name')->get();

        return view('pr.index', compact('requests', 'statuses', 'companies'));
    }


    public function create()
    {
        $vendors = \App\Models\Vendor::where('is_active', true)->get();
        $companies = \App\Models\Company::all();
        $currencies = \App\Models\Currency::where('is_active', true)->get();
        $users = \App\Models\User::with('company')->orderBy('name')->get();

        $customWorkflows = [];
        if (class_exists('\App\Models\ApprovalWorkflow')) {
            $customWorkflows = \App\Models\ApprovalWorkflow::where('is_active', true)
                ->where(function($q) {
                    $q->where('document_type', 'App\Models\PurchaseRequest')
                      ->orWhere('document_type', 'PR')
                      ->orWhere('document_type', 'PurchaseRequest');
                })->get();
        }

        return view('pr.create', compact('companies', 'vendors', 'currencies', 'users', 'customWorkflows'));
    }

    // =========================================================================
    // 🔥 PENCARIAN BARANG & KONVERSI UOM (ANTI TABRAKAN) 🔥
    // =========================================================================
    public function searchItems(Request $request)
    {
        $search = $request->search;

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
            $baseUomName = strtoupper(optional($item->uom)->name ?? 'UNIT');

            // 1. Masukkan Satuan Dasar (Berikan Prefix BASE_)
            $uomList[] = [
                'id' => 'BASE_' . ($item->uom_id ?? 0),
                'name' => $baseUomName . ' (Dasar)',
                'isi' => 1,
                'base' => $baseUomName
            ];

            // 2. Tarik Kemasan Alternatif (Berikan Prefix ALT_)
            try {
                $altUoms = \Illuminate\Support\Facades\DB::table('item_uoms')
                    ->where('item_id', $item->id)
                    ->select('id', 'uom_name', 'conversion_qty')
                    ->get();

                foreach ($altUoms as $alt) {
                    $uomList[] = [
                        'id' => 'ALT_' . $alt->id,
                        'name' => strtoupper($alt->uom_name) . ' (Isi: ' . (float)$alt->conversion_qty . ')',
                        'isi' => $alt->conversion_qty,
                        'base' => $baseUomName
                    ];
                }
            } catch (\Exception $e) {}

            $formattedItems[] = [
                'id' => $item->id,
                'text' => '[' . $item->code . '] ' . $item->name,
                'uoms' => $uomList
            ];
        }

        return response()->json($formattedItems);
    }

    private function generatePrNumber($companyId)
    {
        $company = \App\Models\Company::find($companyId);
        $code = $company && $company->code ? $company->code : 'HO';

        $now = now();
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

    // =========================================================================
    // 🔥 STORE PR (MENGANDUNG AUTO-HEAL DATABASE & ANTI-TABRAKAN UOM) 🔥
    // =========================================================================
    public function store(Request $request, \App\Services\SystemSettingService $settingService)
    {
        // 🔥 AUTO-HEAL DATABASE: Jika kolom uom belum ada, sistem akan otomatis membuatnya! 🔥
        if (!Schema::hasColumn('purchase_request_items', 'uom')) {
            Schema::table('purchase_request_items', function (Blueprint $table) {
                $table->string('uom')->nullable()->after('uom_id');
            });
        }

        $request->validate([
            'user_id'         => 'required|exists:users,id',
            'company_id'      => 'required|exists:companies,id',
            'request_date'    => 'required|date',
            'need_date'       => 'required|date|after_or_equal:request_date',
            'description'     => 'required|string|max:500',
            'items'           => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty'     => 'required|numeric|min:0.01',
            'items.*.uom_id'  => 'required',
            'items.*.item_name'     => 'nullable|string|max:255',
            'items.*.specification' => 'nullable|string',
            'items.*.vendors.*.files.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',
        ], [
            'items.*.vendors.*.files.*.max' => 'Ukuran salah satu file lampiran melebihi batas maksimal 5 MB!'
        ]);

        try {
            DB::transaction(function () use ($request, $settingService) {
                $newPrNumber = $this->generatePrNumber($request->company_id);

                $estimasiGrandTotal = 0;
                foreach ($request->items as $itemData) {
                    $hargaTermurah = 0;
                    if (isset($itemData['vendors']) && is_array($itemData['vendors'])) {
                        $daftarHarga = array_column($itemData['vendors'], 'price');
                        $hargaTermurah = !empty($daftarHarga) ? min($daftarHarga) : 0;
                    }
                    $estimasiGrandTotal += ($itemData['qty'] * $hargaTermurah);
                }

                $pr = \App\Models\PurchaseRequest::create([
                    'pr_number'             => $newPrNumber,
                    'user_id'               => $request->user_id,
                    'company_id'            => $request->company_id,
                    'request_date'          => $request->request_date,
                    'need_date'             => $request->need_date,
                    'description'           => $request->description,
                    'status_id'             => null,
                    'current_approval_level'=> 0,
                    'created_by'            => auth()->id(),
                ]);

                foreach ($request->items as $itemIndex => $itemData) {
                    $masterItem = \App\Models\Item::with('uom', 'itemUoms')->find($itemData['item_id']);
                    $baseUomName = strtoupper(optional($masterItem->uom)->name ?? 'PCS');

                    $uomName = $baseUomName;
                    $finalUomId = null;

                    // 🔥 LOGIKA CERDAS DEKODE UOM 🔥
                    if ($masterItem) {
                        $rawUomId = $itemData['uom_id'];

                        if (str_starts_with($rawUomId, 'ALT_')) {
                            $cleanId = (int) str_replace('ALT_', '', $rawUomId);
                            $altUom = collect($masterItem->itemUoms)->where('id', $cleanId)->first();
                            if ($altUom) {
                                $finalUomId = $cleanId;
                                $uomName = strtoupper($altUom->uom_name);
                            }
                        } elseif (str_starts_with($rawUomId, 'BASE_')) {
                            $cleanId = (int) str_replace('BASE_', '', $rawUomId);
                            $finalUomId = $cleanId;
                            $uomName = $baseUomName;
                        } else {
                            $finalUomId = (int) $rawUomId;
                            if ($finalUomId == $masterItem->uom_id) {
                                $uomName = $baseUomName;
                            } else {
                                $altUom = collect($masterItem->itemUoms)->where('id', $finalUomId)->first();
                                if ($altUom) {
                                    $uomName = strtoupper($altUom->uom_name);
                                }
                            }
                        }
                    }

                    $prItem = \App\Models\PurchaseRequestItem::create([
                        'purchase_request_id' => $pr->id,
                        'item_id'             => $itemData['item_id'],
                        'item_name'           => $itemData['item_name'] ?? null,
                        'specification'       => $itemData['specification'] ?? null,
                        'qty'                 => $itemData['qty'],
                        'uom_id'              => $finalUomId,
                        'uom'                 => $uomName, // INI SEKARANG DIJAMIN MASUK KE DATABASE KARENA KOLOM UOM SUDAH DIBIKIN!
                        'estimated_price'     => 0,
                        'status'              => 'PENDING'
                    ]);

                    if (isset($itemData['vendors']) && is_array($itemData['vendors'])) {
                        foreach ($itemData['vendors'] as $vendorIndex => $quoteData) {
                            if (!empty($quoteData['vendor_id'])) {
                                $currencyObj = \App\Models\Currency::where('code', $quoteData['currency'] ?? 'IDR')->first();

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

                                if ($request->hasFile("items.$itemIndex.vendors.$vendorIndex.files")) {
                                    $safePrNumber = str_replace(['/', '\\'], '-', $newPrNumber);

                                    $settingPath = \Illuminate\Support\Facades\DB::table('system_settings')
                                                        ->where('setting_key', 'path_pr_attachment')
                                                        ->value('setting_value');

                                    $basePath = $settingPath ? $settingPath : 'attachments/purchase_requests';
                                    $targetFolder = $basePath . '/' . $safePrNumber;

                                    $attachmentsData = [];
                                    foreach ($request->file("items.$itemIndex.vendors.$vendorIndex.files") as $file) {
                                        $originalName = $file->getClientOriginalName();
                                        $fileName = "v_" . $quoteData['vendor_id'] . "_" . uniqid() . "." . $file->getClientOriginalExtension();
                                        $path = $file->storeAs($targetFolder, $fileName, 'public');

                                        $attachmentsData[] = [
                                            'pr_item_vendor_id' => $quoteId,
                                            'file_name'         => $originalName,
                                            'file_path'         => str_replace('\\', '/', $path),
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

                $customWorkflowId = $request->input('custom_workflow_id');
                $needsApproval = false;

                if ($customWorkflowId) {
                    $workflow = \App\Models\ApprovalWorkflow::with('steps')->find($customWorkflowId);

                    if ($workflow && $workflow->steps->count() > 0) {
                        foreach ($workflow->steps as $step) {
                            $targetDept = $step->target_department_id ?? $step->department_id ?? null;

                            \App\Models\DocumentApproval::create([
                                'document_id'          => $pr->id,
                                'document_type'        => get_class($pr),
                                'role_id'              => $step->role_id,
                                'target_department_id' => $targetDept,
                                'step_order'           => $step->step_order,
                                'status'               => 'PENDING'
                            ]);
                        }
                        $needsApproval = true;
                        $this->logHistory($pr->id, 'SYSTEM', "Menggunakan Rute Persetujuan Khusus: " . $workflow->name);
                    }
                } else {
                    $pr->amount = $estimasiGrandTotal;
                    $needsApproval = \App\Services\ApprovalService::generateWorkflow($pr);
                    unset($pr->amount);

                    if ($needsApproval) {
                        $this->logHistory($pr->id, 'SYSTEM', "Rute persetujuan standar (Departemen) berhasil di-generate.");
                    }
                }

                if ($needsApproval) {
                    $pendingStatus = \App\Models\Status::where('type', 'PR')->where('slug', 'pending_approval')->first();
                    $pr->update(['status_id' => $pendingStatus ? $pendingStatus->id : 1]);
                } else {
                    $approvedStatus = \App\Models\Status::where('type', 'PR')->where('slug', 'approved')->first();
                    $pr->update(['status_id' => $approvedStatus ? $approvedStatus->id : 3]);
                    $this->logHistory($pr->id, 'AUTO-APPROVED', "PR {$newPrNumber} disetujui otomatis karena tidak ada aturan aktif atau nominal di bawah batas.");
                }

            });

            return redirect()->route('pr.index')->with('success', 'PR Berhasil Diajukan beserta seluruh data Vendor!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal: ' . $e->getMessage());
        }
    }


    public function edit($slug)
    {
        $pr = \App\Models\PurchaseRequest::with([
            'items.item.uom',
            'items.item.itemUoms',
            'items.vendorQuotes.attachments'
        ])->where('pr_number', $slug)->firstOrFail();

        $companies = \App\Models\Company::all();
        $currencies = \App\Models\Currency::where('is_active', true)->get();
        $vendors = \App\Models\Vendor::where('is_active', true)->get();
        $users = \App\Models\User::orderBy('name')->get();

        $customWorkflows = [];
        $selectedWorkflowId = null;

        if (class_exists('\App\Models\ApprovalWorkflow')) {
            $customWorkflows = \App\Models\ApprovalWorkflow::with('steps')
                ->where('is_active', true)
                ->where(function($q) {
                    $q->where('document_type', 'App\Models\PurchaseRequest')
                      ->orWhere('document_type', 'PR')
                      ->orWhere('document_type', 'PurchaseRequest');
                })->get();

            $historyLog = \App\Models\PurchaseRequestHistory::where('purchase_request_id', $pr->id)
                ->where('action', 'SYSTEM')
                ->where('note', 'like', 'Menggunakan Rute Persetujuan Khusus:%')
                ->orderBy('id', 'desc')->first();

            if ($historyLog) {
                $workflowName = trim(str_replace('Menggunakan Rute Persetujuan Khusus:', '', $historyLog->note));
                $matchedWorkflow = $customWorkflows->where('name', $workflowName)->first();
                if ($matchedWorkflow) {
                    $selectedWorkflowId = $matchedWorkflow->id;
                }
            }
        }

        return view('pr.edit', compact('pr', 'companies', 'currencies', 'vendors', 'users', 'customWorkflows', 'selectedWorkflowId'));
    }

    // =========================================================================
    // 6. UPDATE (SIMPAN REVISI + OVERRIDE WORKFLOW)
    // =========================================================================
    public function update(Request $request, $slug, \App\Services\SystemSettingService $settingService)
    {
        // 🔥 AUTO-HEAL DATABASE
        if (!Schema::hasColumn('purchase_request_items', 'uom')) {
            Schema::table('purchase_request_items', function (Blueprint $table) {
                $table->string('uom')->nullable()->after('uom_id');
            });
        }

        $request->validate([
            'user_id'         => 'required|exists:users,id',
            'company_id'      => 'required|exists:companies,id',
            'request_date'    => 'required|date',
            'need_date'       => 'required|date|after_or_equal:request_date',
            'description'     => 'required|string|max:500',
            'items'           => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty'     => 'required|numeric|min:0.01',
            'items.*.uom_id'  => 'required',
            'items.*.item_name'     => 'nullable|string|max:255',
            'items.*.specification' => 'nullable|string',
        ]);

        try {
            DB::transaction(function () use ($request, $slug, $settingService) {
                $pr = \App\Models\PurchaseRequest::with('items.vendorQuotes')->where('pr_number', $slug)->firstOrFail();

                $pr->update([
                    'user_id'      => $request->user_id,
                    'company_id'   => $request->company_id,
                    'request_date' => $request->request_date,
                    'need_date'    => $request->need_date,
                    'description'  => $request->description,
                ]);

                $estimasiGrandTotal = 0;
                foreach ($request->items as $itemData) {
                    $hargaTermurah = 0;
                    if (isset($itemData['vendors']) && is_array($itemData['vendors'])) {
                        $daftarHarga = array_column($itemData['vendors'], 'price');
                        $hargaTermurah = !empty($daftarHarga) ? min($daftarHarga) : 0;
                    }
                    $estimasiGrandTotal += ($itemData['qty'] * $hargaTermurah);
                }

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

                $savedFilesData = DB::table('pr_vendor_attachments')->whereIn('id', $keptFileIds)->get()->keyBy('id');

                foreach($pr->items as $oldItem) {
                    $oldVendors = $oldItem->vendorQuotes ?? $oldItem->vendors ?? [];
                    foreach($oldVendors as $oldVendor) {
                        $attachments = DB::table('pr_vendor_attachments')->where('pr_item_vendor_id', $oldVendor->id)->get();
                        foreach($attachments as $att) {
                            if (!in_array($att->id, $keptFileIds)) {
                                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($att->file_path)) {
                                    \Illuminate\Support\Facades\Storage::disk('public')->delete($att->file_path);
                                }
                            }
                        }
                        DB::table('pr_vendor_attachments')->where('pr_item_vendor_id', $oldVendor->id)->delete();
                    }
                    DB::table('purchase_request_item_vendors')->where('pr_item_id', $oldItem->id)->delete();
                    $oldItem->delete();
                }

                foreach ($request->items as $itemIndex => $itemData) {
                    $masterItem = \App\Models\Item::with('uom', 'itemUoms')->find($itemData['item_id']);
                    $baseUomName = strtoupper(optional($masterItem->uom)->name ?? 'PCS');

                    $uomName = $baseUomName;
                    $finalUomId = null;

                    if ($masterItem) {
                        $rawUomId = $itemData['uom_id'];

                        if (str_starts_with($rawUomId, 'ALT_')) {
                            $cleanId = (int) str_replace('ALT_', '', $rawUomId);
                            $altUom = collect($masterItem->itemUoms)->where('id', $cleanId)->first();
                            if ($altUom) {
                                $finalUomId = $cleanId;
                                $uomName = strtoupper($altUom->uom_name);
                            }
                        } elseif (str_starts_with($rawUomId, 'BASE_')) {
                            $cleanId = (int) str_replace('BASE_', '', $rawUomId);
                            $finalUomId = $cleanId;
                            $uomName = $baseUomName;
                        } else {
                            $finalUomId = (int) $rawUomId;
                            if ($finalUomId == $masterItem->uom_id) {
                                $uomName = $baseUomName;
                            } else {
                                $altUom = collect($masterItem->itemUoms)->where('id', $finalUomId)->first();
                                if ($altUom) {
                                    $uomName = strtoupper($altUom->uom_name);
                                }
                            }
                        }
                    }

                    $prItem = \App\Models\PurchaseRequestItem::create([
                        'purchase_request_id' => $pr->id,
                        'item_id'             => $itemData['item_id'],
                        'item_name'           => $itemData['item_name'] ?? null,
                        'specification'       => $itemData['specification'] ?? null,
                        'qty'                 => $itemData['qty'],
                        'uom_id'              => $finalUomId,
                        'uom'                 => $uomName, // MASUK KE DB DENGAN AMAN!
                        'status'              => 'PENDING'
                    ]);

                    if (isset($itemData['vendors'])) {
                        foreach ($itemData['vendors'] as $vIdx => $vData) {
                            if (empty($vData['vendor_id'])) continue;

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
                                        'created_at'        => now(), 'updated_at' => now(),
                                    ]);
                                }
                            }
                        }
                    }
                }

                $this->logHistory($pr->id, 'UPDATED', "Data PR diperbarui oleh " . auth()->user()->name);

                \App\Models\DocumentApproval::where('document_id', $pr->id)->whereIn('document_type', [get_class($pr), 'PR', 'PurchaseRequest'])->delete();

                $customWorkflowId = $request->input('custom_workflow_id');
                $needsApproval = false;

                if ($customWorkflowId) {
                    $workflow = \App\Models\ApprovalWorkflow::with('steps')->find($customWorkflowId);
                    if ($workflow && $workflow->steps->count() > 0) {
                        foreach ($workflow->steps as $step) {
                            $targetDept = $step->target_department_id ?? $step->department_id ?? null;
                            \App\Models\DocumentApproval::create([
                                'document_id'          => $pr->id,
                                'document_type'        => get_class($pr),
                                'role_id'              => $step->role_id,
                                'target_department_id' => $targetDept,
                                'step_order'           => $step->step_order,
                                'status'               => 'PENDING'
                            ]);
                        }
                        $needsApproval = true;
                        $this->logHistory($pr->id, 'SYSTEM', "Revisi menggunakan Rute Persetujuan Khusus: " . $workflow->name);
                    }
                } else {
                    $pr->amount = $estimasiGrandTotal;
                    $needsApproval = \App\Services\ApprovalService::generateWorkflow($pr);
                    unset($pr->amount);

                    if ($needsApproval) {
                        $this->logHistory($pr->id, 'SYSTEM', 'Rute persetujuan (Workflow) PR telah di-reset menyesuaikan data revisi.');
                    }
                }

                if ($needsApproval) {
                    $pendingStatus = \App\Models\Status::where('type', 'PR')->where('slug', 'pending_approval')->first();
                    $pr->update(['status_id' => $pendingStatus ? $pendingStatus->id : 1]);
                } else {
                    $approvedStatus = \App\Models\Status::where('type', 'PR')->where('slug', 'approved')->first();
                    $pr->update(['status_id' => $approvedStatus ? $approvedStatus->id : 3]);
                    $this->logHistory($pr->id, 'AUTO-APPROVED', 'PR Auto-Approved karena tidak ada aturan aktif atau nominal di bawah batas.');
                }
            });

            return redirect()->route('pr.show', $slug)->with('success', 'Perubahan PR Berhasil Disimpan, File Terkelola & Rute Persetujuan Diperbarui!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal Update: ' . $e->getMessage());
        }
    }


    public function show(string $slug)
    {
        $pr = \App\Models\PurchaseRequest::with([
                    'items.vendorQuotes.vendor',
                    'items.vendorQuotes.attachments',
                    'items.item.uom',
                    'items.item.itemUoms',
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
        $currentApproval = \App\Models\DocumentApproval::with('role')->where('document_id', $pr->id)
            ->where('document_type', get_class($pr))
            ->where('status', 'PENDING')
            ->orderBy('step_order', 'asc')
            ->first();

        $canApprove = false;
        $roleDisplay = null;

        if ($currentApproval) {
            $currentRoleName = $currentApproval->role->name;

            $targetDeptId = $currentApproval->target_department_id;
            $deptName = '';
            if (!empty($targetDeptId) && $targetDeptId !== 'all') {
                $dept = \App\Models\Department::find($targetDeptId);
                if ($dept) $deptName = ' - ' . $dept->name;
            } elseif (empty($targetDeptId)) {
                $deptName = ' - Atasan Langsung';
            }
            $roleDisplay = $currentRoleName . $deptName;

            if ($user->hasRole('Super Admin') || $user->hasRole('Super Administrator')) {
                $canApprove = true;
            } elseif ($user->hasRole($currentRoleName)) {
                if (!empty($targetDeptId) && $targetDeptId !== 'all') {
                    if ($user->department_id == $targetDeptId) {
                        $canApprove = true;
                    }
                } else {
                    $pembuatPR = \App\Models\User::find($pr->user_id);
                    if ($pembuatPR && $user->department_id == $pembuatPR->department_id) {
                        $canApprove = true;
                    } elseif ($targetDeptId === 'all') {
                        $canApprove = true;
                    }
                }
            }
        }

        return view('pr.show', compact('pr', 'currencySymbols', 'isEditable', 'canApprove', 'roleDisplay'));
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

        $currentApproval = \App\Models\DocumentApproval::with('role')
            ->where('document_id', $pr->id)
            ->where('document_type', get_class($pr))
            ->where('status', 'PENDING')
            ->orderBy('step_order', 'asc')
            ->first();

        if (!$currentApproval && $request->global_action !== 'REJECT') {
            return redirect()->back()->with('error', 'Dokumen ini tidak sedang menunggu persetujuan Anda.');
        }

        $user = auth()->user();
        $isAuthorized = false;

        if ($currentApproval) {
            if ($user->hasRole('Super Admin') || $user->hasRole('Super Administrator')) {
                $isAuthorized = true;
            } elseif ($user->hasRole($currentApproval->role->name)) {
                $targetDeptId = $currentApproval->target_department_id;
                if (!empty($targetDeptId) && $targetDeptId !== 'all') {
                    if ($user->department_id == $targetDeptId) $isAuthorized = true;
                } else {
                    if ($pembuatPR && $user->department_id == $pembuatPR->department_id) {
                        $isAuthorized = true;
                    } elseif ($targetDeptId === 'all') {
                        $isAuthorized = true;
                    }
                }
            }
        }

        if ($currentApproval && !$isAuthorized) {
            return redirect()->back()->with('error', 'AKSES DITOLAK: Giliran persetujuan saat ini adalah wewenang ' . $currentApproval->role->name . ' dari departemen terkait. Anda tidak memiliki hak akses!');
        }

        $approverRoleName = $currentApproval ? $currentApproval->role->name : 'Atasan';

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

            $namaBarang = $prItem->item_name ?? ($prItem->item ? $prItem->item->name : 'Item #' . $prItem->id);

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

        if ($totalApprovedItems === 0) {
            $currentApproval->update(['status' => 'REJECTED', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            $statusRejected = \App\Models\Status::where('type', 'PR')->where('slug', 'rejected')->first();
            if ($statusRejected) $pr->update(['status_id' => $statusRejected->id, 'current_approval_level' => 0]);

            $this->logHistory($pr->id, 'Ditolak (Otomatis)', "PR ditolak otomatis karena semua item ditolak oleh " . auth()->user()->name . " ($approverRoleName)");

            if ($pembuatPR) $pembuatPR->notify(new DocumentApprovalNotification('PR Ditolak ❌', "Semua item pada PR Nomor {$pr->pr_number} telah ditolak.", route('pr.show', $pr->id)));

            return redirect()->route('pr.index')->with('error', 'PR ditolak karena semua item di dalamnya ditolak.');
        }

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
        $pr = \App\Models\PurchaseRequest::with(['items.vendorQuotes.vendor', 'items.item.itemUoms', 'user', 'company', 'status'])
                ->where('pr_number', $slug)
                ->firstOrFail();

        $approvals = \App\Models\DocumentApproval::with(['role', 'approver'])
                ->where('document_id', $pr->id)
                ->where('document_type', get_class($pr))
                ->orderBy('step_order', 'asc')
                ->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pr.print', compact('pr', 'approvals'))
                ->setPaper('a4', 'portrait');

        return $pdf->stream('Dokumen-PR-' . $pr->pr_number . '.pdf');
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

            $itemName = $item->item_name ?? ($item->item->name ?? 'Item #' . $item->id);
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

    // =========================================================================
    // 🔥 GENERATE PR DARI SMART RESTOCK (PASTIKAN UOM STRING TERSIMPAN) 🔥
    // =========================================================================
    public function generateMassPr(\Illuminate\Http\Request $request)
    {
        // 🔥 AUTO-HEAL DATABASE: Jika kolom uom belum ada, sistem akan otomatis membuatnya! 🔥
        if (!Schema::hasColumn('purchase_request_items', 'uom')) {
            Schema::table('purchase_request_items', function (Blueprint $table) {
                $table->string('uom')->nullable()->after('uom_id');
            });
        }

        $request->validate(['items' => 'required|array']);

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $selectedItems = collect($request->items)->filter(function ($item) {
                return isset($item['is_selected']) && $item['qty'] > 0;
            });

            if ($selectedItems->isEmpty()) {
                throw new \Exception('Gagal: Anda harus mencentang minimal 1 barang untuk dibuatkan PR!');
            }

            $consolidatedItems = [];
            foreach ($selectedItems as $item) {
                $itemId = $item['item_id'];
                $qty = (float)$item['qty'];
                $warehouseName = $item['warehouse_name'] ?? 'Gudang Utama / Default';

                $groupKey = $itemId . '_' . $warehouseName;

                if (!isset($consolidatedItems[$groupKey])) {
                    $consolidatedItems[$groupKey] = [
                        'item_id'   => $itemId,
                        'warehouse' => $warehouseName,
                        'total_qty' => 0,
                    ];
                }
                $consolidatedItems[$groupKey]['total_qty'] += $qty;
            }

            $companyId = $request->company_id;
            $prNumber = $this->generatePrNumber($companyId);
            $statusDraftId = \App\Models\Status::where('type', 'PR')->whereIn('slug', ['draft', 'pending'])->first()->id ?? 1;

            $pr = \App\Models\PurchaseRequest::create([
                'pr_number'     => $prNumber,
                'request_date'  => now(),
                'need_date'     => now()->addDays(14),
                'department_id' => auth()->user()->department_id ?? null,
                'company_id'    => $companyId,
                'user_id'       => auth()->id(),
                'requester_id'  => auth()->id(),
                'description'   => 'Auto-Restock Rombongan (Multi-Gudang)',
                'status_id'     => $statusDraftId,
                'notes'         => 'Dokumen PR ini digenerate secara otomatis oleh sistem Smart Restock.',
            ]);

            foreach ($consolidatedItems as $data) {
                $masterItem = \App\Models\Item::with('uom')->find($data['item_id']);
                if (!$masterItem) continue;

                $alokasiTeks = "Rincian Alokasi:\n- " . $data['total_qty'] . " Pcs dialokasikan untuk " . $data['warehouse'];

                \Illuminate\Support\Facades\DB::table('purchase_request_items')->insert([
                    'purchase_request_id' => $pr->id,
                    'item_id'             => $masterItem->id,
                    'item_name'           => $masterItem->name,
                    'qty'                 => $data['total_qty'],
                    'uom_id'              => $masterItem->uom_id ?? null,
                    'uom'                 => strtoupper(optional($masterItem->uom)->name ?? 'PCS'), // 🔥 PASTIKAN STRING TERSIMPAN!
                    'status'              => 'PENDING',
                    'allocation_notes'    => $alokasiTeks,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }

            $needsApproval = \App\Services\ApprovalService::generateWorkflow($pr);

            if ($needsApproval) {
                $statusPending = \App\Models\Status::where('type', 'PR')->where('slug', 'pending_approval')->first();
                if ($statusPending) $pr->update(['status_id' => $statusPending->id]);

                if (class_exists('\App\Models\PurchaseRequestHistory')) {
                    \App\Models\PurchaseRequestHistory::create([
                        'purchase_request_id' => $pr->id,
                        'user_id' => auth()->id(),
                        'action' => 'SUBMITTED',
                        'note' => 'PR Massal digenerate otomatis dan masuk antrean persetujuan.'
                    ]);
                }
            } else {
                $statusApproved = \App\Models\Status::where('type', 'PR')->where('slug', 'approved')->first();
                if ($statusApproved) $pr->update(['status_id' => $statusApproved->id]);

                if (class_exists('\App\Models\PurchaseRequestHistory')) {
                    \App\Models\PurchaseRequestHistory::create([
                        'purchase_request_id' => $pr->id,
                        'user_id' => auth()->id(),
                        'action' => 'APPROVED',
                        'note' => 'PR Massal Auto-Approved karena tidak ada aturan matriks.'
                    ]);
                }
            }

            if (class_exists('\App\Models\History')) {
                \App\Models\History::create([
                    'record_id'   => $pr->id,
                    'record_type' => get_class($pr),
                    'user_id'     => auth()->id(),
                    'action'      => 'CREATED',
                    'note'        => 'Dokumen PR dibuat masal melalui fitur Smart Restock.'
                ]);
            }

            \Illuminate\Support\Facades\DB::commit();
            return redirect()->route('pr.edit', $pr->pr_number)->with('success', 'Hore! Mass PR berhasil di-generate secara otomatis dan Rute Persetujuan telah aktif!');

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollback();
            return back()->with('error', $e->getMessage());
        }
    }

    public function printCompleteWithAttachments($slug)
    {
        $pr = \App\Models\PurchaseRequest::with([
            'items.item.uom',
            'items.item.itemUoms',
            'items.vendorQuotes.vendor',
            'items.vendorQuotes.currency',
            'items.vendorQuotes.attachments',
            'user.department',
            'company',
            'status'
        ])->where('pr_number', $slug)->firstOrFail();

        $approvals = \App\Models\DocumentApproval::with(['role', 'approver'])
            ->where('document_id', $pr->id)
            ->where('document_type', get_class($pr))
            ->orderBy('step_order', 'asc')
            ->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pr.print_complete', compact('pr', 'approvals'))
                ->setPaper('A4', 'portrait');

        $tempMainPdfPath = storage_path('app/temp_pr_complete_' . uniqid() . '.pdf');
        $pdf->save($tempMainPdfPath);

        $merger = new \iio\libmergepdf\Merger();
        $merger->addFile($tempMainPdfPath);

        if ($pr->items) {
            foreach ($pr->items as $item) {
                if ($item->vendorQuotes) {
                    foreach ($item->vendorQuotes as $quote) {
                        if ($quote->attachments) {
                            foreach ($quote->attachments as $attachment) {
                                $ext = strtolower(pathinfo($attachment->file_path, PATHINFO_EXTENSION));
                                if ($ext === 'pdf') {
                                    $pdfAttachmentPath = public_path('storage/' . $attachment->file_path);
                                    if (file_exists($pdfAttachmentPath)) {
                                        $merger->addFile($pdfAttachmentPath);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $mergedPdfData = $merger->merge();

        if (file_exists($tempMainPdfPath)) {
            unlink($tempMainPdfPath);
        }

        $filename = 'PR_Dokumen_Lengkap_' . str_replace('/', '_', $pr->pr_number) . '.pdf';

        return response($mergedPdfData)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }
}
