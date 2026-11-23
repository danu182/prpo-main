<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FixedAsset;
use App\Models\User;
use App\Models\Status;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class FixedAssetController extends Controller
{


    public function index(Request $request)
    {
        $search      = trim((string) $request->input('search'));
        $warehouseId = $request->input('warehouse_id');

        $assets = FixedAsset::with([
                'item', 'company', 'status', 'assignee.company', 'assignee.department',
                'warehouse', 'assetCategory', 'currency',
                'goodsReceipt.po.items', 'goodsReceipt.purchaseOrder.items',
                'histories' => function ($q) { $q->orderBy('created_at', 'desc'); },
                'histories.assignee', 'histories.creator'
            ])
            ->where(function ($q) {
                $q->whereNull('notes')
                  ->orWhere('notes', 'not like', '%[DIBATALKAN%');
            })
            ->whereHas('status', function ($q) {
                $q->whereNotIn('slug', ['void', 'batal', 'canceled', 'cancelled']);
            })
            ->when($warehouseId, function ($query) use ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q1) use ($search) {
                    $q1->where('asset_number', 'like', "%{$search}%")
                       ->orWhere('serial_number', 'like', "%{$search}%")
                       ->orWhere('accounting_asset_number', 'like', "%{$search}%")
                       ->orWhere('name', 'like', "%{$search}%")
                       ->orWhereHas('item', function ($qi) use ($search) {
                           $qi->where('code', 'like', "%{$search}%")
                              ->orWhere('name', 'like', "%{$search}%");
                       })
                       ->orWhereHas('company', function ($qc) use ($search) {
                           $qc->where('name', 'like', "%{$search}%");
                       })
                       ->orWhereHas('assignee', function ($qa) use ($search) {
                           $qa->where('name', 'like', "%{$search}%");
                       });
                });
            })
            ->latest()->paginate(10)->withQueryString();

        $users           = User::with('company')->orderBy('name', 'asc')->get();
        $items           = \App\Models\Item::where('item_type_code', 'AST')->orderBy('name', 'asc')->get();
        $companies       = \App\Models\Company::orderBy('name', 'asc')->get();
        $statuses        = Status::where('type', 'AST')->orderBy('sequence', 'asc')->get();
        $warehouses      = \App\Models\Warehouse::orderBy('name', 'asc')->get();
        $currencies      = \App\Models\Currency::where('is_active', 1)->get();
        $assetCategories = \App\Models\AssetCategory::where('is_active', true)->orderBy('useful_life_years', 'asc')->get();

        return view('fixed_assets.index', compact(
            'assets', 'search', 'users', 'items', 'companies', 'statuses', 'warehouses', 'currencies', 'assetCategories'
        ));
    }


    // =========================================================================
    // 🔥 PROSES SIMPAN ASET MANUAL (CLEAN CODE, SMART MUTATION & DYNAMIC BATCH) 🔥
    // =========================================================================
    public function store(Request $request)
    {
        $request->validate([
            'item_id'                 => 'required|exists:items,id',
            'asset_category_id'       => 'required|exists:asset_categories,id',
            'warehouse_id'            => 'required|exists:warehouses,id',
            'quantity'                => 'required|integer|min:1',
            'status_id'               => 'required|exists:statuses,id',
            'company_id'              => 'required|exists:companies,id',
            'asset_name'              => 'nullable|string|max:255',
            'serial_number'           => 'nullable|string|max:255',
            'accounting_asset_number' => [
                'nullable',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('fixed_assets', 'accounting_asset_number')->where(function ($query) {
                    return $query->whereNull('notes')->orWhere('notes', 'not like', '%[DIBATALKAN%');
                }),
            ],
            'spesifikasi_detail'      => 'required|string',
            'notes'                   => 'nullable|string',
            'acquisition_date'        => 'required|date',
            'purchase_price'          => 'nullable|numeric|min:0',
            'currency_id'             => 'required|exists:currencies,id',
            'supporting_document'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'photos.*'                => 'nullable|file|mimes:jpeg,png,jpg,gif,pdf,doc,docx,xls,xlsx,zip,rar|max:5120',
            'acquisition_type_id'     => 'required|exists:acquisition_types,id',

        ], [
            'accounting_asset_number.unique' => 'Nomor Aset Akuntansi tersebut sudah terdaftar pada aset lain!',
            'photos.*.mimes' => 'File lampiran harus berupa Gambar, PDF, Word, Excel, atau ZIP.',
            'photos.*.max'   => 'Ukuran setiap file lampiran maksimal 5MB.',
        ]);

        $selectedStatus = \App\Models\Status::find($request->status_id);
        $statusSlug = optional($selectedStatus)->slug;

        if ($statusSlug === 'in_use' && empty($request->assigned_to)) {
            return back()->withInput()->with('error', 'VALIDASI GAGAL: Anda mengatur status aset menjadi "In Use", maka Anda WAJIB memilih Karyawan/User pada kolom Penanggung Jawab!');
        }

        try {
            DB::transaction(function () use ($request, $selectedStatus, $statusSlug) {
                $qty = $request->quantity;
                $itemMaster = \App\Models\Item::lockForUpdate()->findOrFail($request->item_id);
                $yearMonth = date('Y/m');

                $finalAssetName = $request->filled('asset_name') ? $request->asset_name : $itemMaster->name;
                $currentStock = (float) $itemMaster->current_stock;

                $acqType = \App\Models\AcquisitionType::find($request->acquisition_type_id);
                $kodePerolehan = $acqType ? $acqType->code : 'MANUAL';
                $namaPerolehan = $acqType ? $acqType->name : 'Registrasi Aset Lama';

                $nomorPenerimaanManual = $kodePerolehan . '-' . date('Ymd-His');
                $documentPath = null;

                if ($request->hasFile('supporting_document')) {
                    $file = $request->file('supporting_document');
                    $filename = time() . '_' . uniqid() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
                    $folderPath = 'input_asset_manual/' . $nomorPenerimaanManual;
                    $documentPath = $file->storeAs($folderPath, $filename, 'public');
                }

                for ($i = 1; $i <= $qty; $i++) {
                    $lastAsset = \App\Models\FixedAsset::where('asset_number', 'like', "AST/{$yearMonth}/%")
                                    ->orderBy('id', 'desc')->lockForUpdate()->first();
                    $nextSeq = $lastAsset ? ((int) substr($lastAsset->asset_number, -4)) + 1 : 1;
                    $assetNumber = "AST/{$yearMonth}/" . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

                    $assignedTo = ($statusSlug !== 'in_use') ? null : $request->assigned_to;

                    $asset = \App\Models\FixedAsset::create([
                        'asset_number'            => $assetNumber,
                        'item_id'                 => $request->item_id,
                        'asset_category_id'       => $request->asset_category_id,
                        'name'                    => $finalAssetName,
                        'warehouse_id'            => $request->warehouse_id,
                        'company_id'              => $request->company_id,
                        'serial_number'           => $qty > 1 ? null : $request->serial_number,
                        'accounting_asset_number' => $qty > 1 ? null : $request->accounting_asset_number,
                        'status_id'               => $request->status_id,
                        'assigned_to'             => $assignedTo,
                        'spesifikasi_detail'      => $request->spesifikasi_detail,
                        'notes'                   => $request->notes ?? "Aset didaftarkan secara manual ($namaPerolehan)",
                        'acquisition_date'        => $request->acquisition_date,
                        'purchase_price'          => $request->purchase_price ?? 0,
                        'currency_id'             => $request->currency_id,
                        'batch_id'                => $nomorPenerimaanManual,
                        'supporting_document'     => $documentPath,
                    ]);

                    $historyNote = 'Input Manual: ' . $namaPerolehan . ' (Unit ke-' . $i . ' dari ' . $qty . '). ' . $request->notes;
                    if ($assignedTo) {
                        $user = \App\Models\User::find($assignedTo);
                        $historyNote .= ' [Aset langsung diserahkan kepada: ' . optional($user)->name . ']';
                    }

                    \App\Models\FixedAssetHistory::create([
                        'fixed_asset_id' => $asset->id,
                        'status'         => optional($selectedStatus)->name ?? 'Unknown',
                        'assigned_to'    => $assignedTo,
                        'notes'          => $historyNote,
                        'created_by'     => auth()->id(),
                    ]);

                    if ($request->hasFile('photos')) {
                        $safeFolderName = str_replace('/', '-', $assetNumber);
                        $folderPathPhotos = "FixAsset/{$safeFolderName}";

                        foreach ($request->file('photos') as $photoFile) {
                            $filenamePhoto = time() . '_' . uniqid() . '.' . $photoFile->getClientOriginalExtension();
                            $pathPhoto = $photoFile->storeAs($folderPathPhotos, $filenamePhoto, 'public');

                            \App\Models\AssetPhoto::create([
                                'fixed_asset_id' => $asset->id,
                                'file_path'      => $pathPhoto
                            ]);
                        }
                    }

                    $balanceBefore = $currentStock;
                    $balanceAfter  = $currentStock + 1;

                    \App\Models\StockMutation::create([
                        'item_id'          => $request->item_id,
                        'warehouse_id'     => $request->warehouse_id,
                        'type'             => 'IN',
                        'qty'              => 1,
                        'balance_before'   => $balanceBefore,
                        'balance_after'    => $balanceAfter,
                        'reference_number' => $assetNumber,
                        'notes'            => "Penerimaan Manual: $namaPerolehan",
                        'created_by'       => auth()->id(),
                    ]);

                    if ($assignedTo) {
                        \App\Models\StockMutation::create([
                            'item_id'          => $request->item_id,
                            'warehouse_id'     => $request->warehouse_id,
                            'type'             => 'OUT',
                            'qty'              => 1,
                            'balance_before'   => $balanceAfter,
                            'balance_after'    => $balanceAfter - 1,
                            'reference_number' => 'GI-' . $assetNumber,
                            'notes'            => "Aset langsung diserahkan ke pengguna (Input Manual)",
                            'created_by'       => auth()->id(),
                        ]);
                        $currentStock = $balanceAfter - 1;
                    } else {
                        $currentStock = $balanceAfter;
                    }
                }

                $itemMaster->update(['current_stock' => $currentStock]);
            });

            return redirect()->route('fixed-assets.index')->with('success', $request->quantity . ' Unit Aset berhasil didaftarkan dan kartu stok disesuaikan!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal mendaftarkan aset: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // HALAMAN FORM EDIT ASET TERSENDIRI
    // =========================================================================
    public function edit($id)
    {
        $asset = FixedAsset::with([
            'photos', 'item', 'company', 'status', 'warehouse', 'assetCategory',
            'goodsReceipt.po.items', 'goodsReceipt.purchaseOrder.items'
        ])->findOrFail($id);

        $warehouses = \App\Models\Warehouse::orderBy('name', 'asc')->get();
        $assetCategories = \App\Models\AssetCategory::where('is_active', true)->orderBy('useful_life_years', 'asc')->get();
        $companies = \App\Models\Company::orderBy('name', 'asc')->get();
        $statuses = Status::where('type', 'AST')->orderBy('sequence', 'asc')->get();
        $currencies = \App\Models\Currency::where('is_active', 1)->get();
        $users = User::with(['company', 'department'])->orderBy('name', 'asc')->get();

        $poItemName = null;
        if ($asset->goodsReceipt) {
            $po = $asset->goodsReceipt->po ?? $asset->goodsReceipt->purchaseOrder;
            if ($po && $po->items) {
                $poItem = $po->items->where('item_id', $asset->item_id)->first();
                if ($poItem) {
                    $poItemName = $poItem->item_name;
                }
            }
        }

        return view('fixed_assets.edit', compact(
            'asset', 'warehouses', 'assetCategories', 'companies', 'statuses', 'currencies', 'users', 'poItemName'
        ));
    }

    // =========================================================================
    // 🔥 PROSES SIMPAN EDIT ASET (CLEAN CODE & ENTERPRISE MUTATION) 🔥
    // =========================================================================
    public function update(Request $request, $id)
    {
        $request->validate([
            'asset_category_id'       => 'required|exists:asset_categories,id',
            'status_id'               => 'required|exists:statuses,id',
            'warehouse_id'            => 'nullable|exists:warehouses,id',
            'company_id'              => 'nullable|exists:companies,id',
            'name'                    => 'nullable|string|max:255',
            'serial_number'           => 'nullable|string|max:255',
            'accounting_asset_number' => [
                'nullable',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('fixed_assets', 'accounting_asset_number')
                    ->ignore($id)
                    ->where(function ($query) {
                        return $query->whereNull('notes')->orWhere('notes', 'not like', '%[DIBATALKAN%');
                    }),
            ],
            'spesifikasi_detail'      => 'nullable|string',
            'notes'                   => 'nullable|string',
            'acquisition_date'        => 'required|date',
            'purchase_price'          => 'nullable|numeric|min:0',
            'currency_id'             => 'required|exists:currencies,id',
            'photos.*'                => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ], [
            'accounting_asset_number.unique' => 'Nomor Aset Akuntansi tersebut sudah digunakan oleh aset lain!',
        ]);

        $asset = \App\Models\FixedAsset::findOrFail($id);
        $selectedStatus = \App\Models\Status::find($request->status_id);
        $statusSlug = optional($selectedStatus)->slug;

        if ($statusSlug === 'in_use' && empty($request->assigned_to)) {
            return back()->withInput()->with('error', 'VALIDASI GAGAL: Anda mengubah status aset menjadi "In Use", maka Anda WAJIB memilih Karyawan pada kolom Penanggung Jawab!');
        }

        try {
            DB::transaction(function () use ($request, $asset, $selectedStatus, $statusSlug) {

                $oldStatusId = $asset->status_id;
                $oldAssignee = $asset->assigned_to;

                $assignedTo = ($statusSlug !== 'in_use') ? null : $request->assigned_to;

                $asset->update([
                    'asset_category_id'       => $request->asset_category_id,
                    'name'                    => $request->name ?? $asset->name,
                    'warehouse_id'            => $request->warehouse_id ?? $asset->warehouse_id,
                    'company_id'              => $request->company_id ?? $asset->company_id,
                    'serial_number'           => $request->serial_number,
                    'accounting_asset_number' => $request->accounting_asset_number,
                    'status_id'               => $request->status_id,
                    'assigned_to'             => $assignedTo,
                    'spesifikasi_detail'      => $request->spesifikasi_detail,
                    'notes'                   => $request->notes,
                    'acquisition_date'        => $request->acquisition_date,
                    'purchase_price'          => $request->purchase_price ?? 0,
                    'currency_id'             => $request->currency_id,
                ]);

                if ($oldStatusId != $request->status_id || $oldAssignee != $assignedTo) {
                    $historyNote = 'Perubahan Data Aset menjadi: ' . optional($selectedStatus)->name . '.';

                    if ($assignedTo) {
                        $user = \App\Models\User::find($assignedTo);
                        $historyNote .= ' [Diserahkan ke: ' . optional($user)->name . ']';
                    } elseif ($oldAssignee != null && $assignedTo == null) {
                        $historyNote .= ' [Dikembalikan ke Gudang]';
                    }

                    if ($request->filled('status_change_reason')) {
                        $historyNote .= ' | Alasan Resmi: ' . trim($request->status_change_reason);
                    } elseif ($request->filled('notes') && $oldStatusId == $request->status_id) {
                        $historyNote .= ' | Catatan: ' . trim($request->notes);
                    }

                    \App\Models\FixedAssetHistory::create([
                        'fixed_asset_id' => $asset->id,
                        'status'         => optional($selectedStatus)->name ?? 'Updated',
                        'assigned_to'    => $assignedTo,
                        'notes'          => $historyNote,
                        'created_by'     => auth()->id(),
                    ]);
                }

                $oldStatus = \App\Models\Status::find($oldStatusId);
                $oldStatusSlug = optional($oldStatus)->slug;

                $wasDisposed = ($oldStatusSlug === 'disposed');
                $wasInWarehouse = (!$wasDisposed && empty($oldAssignee));

                $isNowDisposed = ($statusSlug === 'disposed');
                $isNowInWarehouse = (!$isNowDisposed && empty($assignedTo));

                if ($asset->item_id && $asset->warehouse_id) {
                    $masterItem = \App\Models\Item::lockForUpdate()->find($asset->item_id);

                    if ($masterItem) {
                        $currStock = (float) $masterItem->current_stock;
                        $invStock = \App\Models\InventoryStock::where('item_id', $masterItem->id)
                                        ->where('warehouse_id', $asset->warehouse_id)
                                        ->first();

                        if ($wasInWarehouse && !$isNowInWarehouse) {
                            if ($currStock > 0) {
                                $masterItem->decrement('current_stock', 1);
                                if ($invStock && $invStock->stock_qty > 0) {
                                    $invStock->decrement('stock_qty', 1);
                                }

                                $mutationNote = $isNowDisposed
                                    ? 'Penghapusan Aset (Status: ' . optional($selectedStatus)->name . ')'
                                    : 'Aset diserahkan ke pengguna (via Edit Form)';

                                \App\Models\StockMutation::create([
                                    'item_id'          => $masterItem->id,
                                    'warehouse_id'     => $asset->warehouse_id,
                                    'type'             => 'OUT',
                                    'qty'              => 1,
                                    'balance_before'   => $currStock,
                                    'balance_after'    => $currStock - 1,
                                    'reference_number' => 'UPD-OUT-' . $asset->asset_number,
                                    'notes'            => $mutationNote,
                                    'created_by'       => auth()->id()
                                ]);
                            }
                        } elseif (!$wasInWarehouse && $isNowInWarehouse) {
                            $masterItem->increment('current_stock', 1);
                            if ($invStock) {
                                $invStock->increment('stock_qty', 1);
                            } else {
                                \App\Models\InventoryStock::create([
                                    'company_id'       => $asset->company_id,
                                    'warehouse_id'     => $asset->warehouse_id,
                                    'item_id'          => $masterItem->id,
                                    'stock_qty'        => 1,
                                    'reference_number' => 'UPD-IN-' . $asset->asset_number,
                                    'notes'            => 'Stok Diciptakan via Pengembalian',
                                ]);
                            }

                            $mutationNote = $wasDisposed
                                ? 'Pembatalan Disposal (Barang Aktif Kembali)'
                                : 'Pengembalian Aset ke Gudang (Status: ' . optional($selectedStatus)->name . ')';

                            \App\Models\StockMutation::create([
                                'item_id'          => $masterItem->id,
                                'warehouse_id'     => $asset->warehouse_id,
                                'type'             => 'IN',
                                'qty'              => 1,
                                'balance_before'   => $currStock,
                                'balance_after'    => $currStock + 1,
                                'reference_number' => 'UPD-IN-' . $asset->asset_number,
                                'notes'            => $mutationNote,
                                'created_by'       => auth()->id()
                            ]);
                        }

                        if (!empty($asset->serial_number)) {
                            $snStatus = 'AVAILABLE';
                            if ($isNowDisposed) {
                                $snStatus = 'DISPOSED';
                            } elseif (!empty($assignedTo)) {
                                $snStatus = 'IN_USE';
                            }

                            \DB::table('item_serials')
                                ->where('item_id', $asset->item_id)
                                ->where('serial_number', $asset->serial_number)
                                ->update([
                                    'status'     => $snStatus,
                                    'updated_at' => now()
                                ]);
                        }
                    }
                }

                if ($request->hasFile('photos')) {
                    $safeFolderName = str_replace('/', '-', $asset->asset_number);
                    $folderPathPhotos = "FixAsset/{$safeFolderName}";

                    foreach ($request->file('photos') as $photoFile) {
                        $filenamePhoto = time() . '_' . uniqid() . '.' . $photoFile->getClientOriginalExtension();
                        $pathPhoto = $photoFile->storeAs($folderPathPhotos, $filenamePhoto, 'public');

                        \App\Models\AssetPhoto::create([
                            'fixed_asset_id' => $asset->id,
                            'file_path'      => $pathPhoto
                        ]);
                    }
                }
            });

            return redirect()->route('fixed-assets.index')->with('success', 'Data Aset ' . $asset->asset_number . ' berhasil diperbarui dan mutasi stok telah disesuaikan!');

        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal memperbarui aset: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // 🔥 CETAK BAST DENGAN KUNCI POSISI MUTLAK 🔥
    // =========================================================================
    public function printBast(\Illuminate\Http\Request $request, $id)
    {
        $asset = FixedAsset::with(['item', 'assignee.company', 'status'])->findOrFail($id);
        if (optional($asset->status)->slug !== 'in_use' || !$asset->assigned_to) {
            return back()->with('error', 'Aset ini sedang tidak diserahkan ke siapapun. BAST tidak dapat dicetak.');
        }

        $signerIds = $request->input('signers', []);

        if (empty($signerIds)) {
            $signers = collect([
                (object)['name' => auth()->user()->name, 'job_title' => auth()->user()->job_title ?? 'IT / GA', 'department' => auth()->user()->department],
                (object)['name' => optional($asset->assignee)->name, 'job_title' => optional($asset->assignee)->job_title ?? 'Karyawan', 'department' => optional($asset->assignee)->department]
            ]);
        } else {
            $selectedUsers = \App\Models\User::with('department')->whereIn('id', $signerIds)->get();
            $signers = collect();

            $adminId = auth()->id();
            $assigneeId = $asset->assigned_to;

            $pihak1 = $selectedUsers->where('id', $adminId)->first();
            if (!$pihak1) $pihak1 = $selectedUsers->first();

            if ($pihak1) {
                $signers->push($pihak1);
                $selectedUsers = $selectedUsers->reject(function($u) use ($pihak1) { return $u->id == $pihak1->id; });
            }

            $pihak2 = $selectedUsers->where('id', $assigneeId)->first();
            if (!$pihak2) $pihak2 = $selectedUsers->first();

            if ($pihak2) {
                $signers->push($pihak2);
                $selectedUsers = $selectedUsers->reject(function($u) use ($pihak2) { return $u->id == $pihak2->id; });
            }

            foreach ($selectedUsers as $u) {
                $signers->push($u);
            }
        }

        $latestHandover = \App\Models\FixedAssetTransaction::where('fixed_asset_id', $asset->id)
            ->where('transaction_type', 'HANDOVER')
            ->latest()
            ->first();

        $documentNumber = $latestHandover
            ? $latestHandover->transaction_number
            : 'BAST/' . now()->format('Y/m/d') . '/' . substr($asset->asset_number, -4);

        $pdf = Pdf::loadView('fixed_assets.bast', compact('asset', 'signers', 'documentNumber'))
            ->setPaper('A4', 'portrait');
        return $pdf->stream('BAST_' . str_replace('/', '_', $documentNumber) . '.pdf');
    }

    // =========================================================================
    // 🔥 CETAK BAPA DENGAN KUNCI POSISI MUTLAK 🔥
    // =========================================================================
    public function printBapa(\Illuminate\Http\Request $request, $id)
    {
        $asset = FixedAsset::with(['item', 'status', 'histories' => function($query) {
            $query->orderBy('created_at', 'desc');
        }])->findOrFail($id);

        $lastUsageHistory = $asset->histories->whereNotNull('assigned_to')->first();
        if (!$lastUsageHistory || !$lastUsageHistory->assigned_to) {
            return back()->with('error', 'Belum ada riwayat peminjaman untuk aset ini. BAPA tidak dapat dicetak.');
        }

        $lastAssignee = User::with('company')->find($lastUsageHistory->assigned_to);
        $signerIds = $request->input('signers', []);

        if (empty($signerIds)) {
            $signers = collect([
                (object)['name' => optional($lastAssignee)->name, 'job_title' => optional($lastAssignee)->job_title ?? 'Karyawan', 'department' => optional($lastAssignee)->department],
                (object)['name' => auth()->user()->name, 'job_title' => auth()->user()->job_title ?? 'IT / GA', 'department' => auth()->user()->department]
            ]);
        } else {
            $selectedUsers = \App\Models\User::with('department')->whereIn('id', $signerIds)->get();
            $signers = collect();

            $adminId = auth()->id();
            $assigneeId = optional($lastAssignee)->id;

            $pihak1 = $selectedUsers->where('id', $assigneeId)->first();
            if (!$pihak1) $pihak1 = $selectedUsers->where('id', '!=', $adminId)->first();
            if (!$pihak1) $pihak1 = $selectedUsers->first();

            if ($pihak1) {
                $signers->push($pihak1);
                $selectedUsers = $selectedUsers->reject(function($u) use ($pihak1) { return $u->id == $pihak1->id; });
            }

            $pihak2 = $selectedUsers->where('id', $adminId)->first();
            if (!$pihak2) $pihak2 = $selectedUsers->first();

            if ($pihak2) {
                $signers->push($pihak2);
                $selectedUsers = $selectedUsers->reject(function($u) use ($pihak2) { return $u->id == $pihak2->id; });
            }

            foreach ($selectedUsers as $u) {
                $signers->push($u);
            }
        }

        $latestHandover = \App\Models\FixedAssetTransaction::where('fixed_asset_id', $asset->id)
            ->where('transaction_type', 'HANDOVER')
            ->latest()
            ->first();

        $documentNumber = $latestHandover
            ? $latestHandover->transaction_number
            : 'BAST/' . now()->format('Y/m/d') . '/' . substr($asset->asset_number, -4);

        $pdf = Pdf::loadView('fixed_assets.bast', compact('asset', 'signers', 'documentNumber'))
            ->setPaper('A4', 'portrait');
        return $pdf->stream('BAST_' . str_replace('/', '_', $documentNumber) . '.pdf');
    }

    public function printBapp($id)
    {
        $asset = FixedAsset::with(['item', 'status'])->findOrFail($id);
        if (optional($asset->status)->slug !== 'disposed') {
            return back()->with('error', 'Aset ini belum berstatus Dihancurkan/Disposed. BAPP tidak dapat dicetak!');
        }
        $pdf = Pdf::loadView('fixed_assets.bapp', compact('asset'))->setPaper('A4', 'portrait');
        return $pdf->stream('BAPP_Aset_' . str_replace('/', '_', $asset->asset_number) . '.pdf');
    }

    // =========================================================================
    // 1. PROSES BACA EXCEL & LEMPAR KE KARANTINA (STAGING) + VALIDASI KETAT
    // =========================================================================
    public function processImport(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|max:10240|mimes:xlsx,xls,csv',
            'support_doc' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        try {
            $file = $request->file('import_file');
            $originalName = str_replace(['(', ')', ' '], '_', $file->getClientOriginalName());
            $fileName = time() . '_' . $originalName;

            if (!Storage::disk('local')->exists('temp_imports')) {
                Storage::disk('local')->makeDirectory('temp_imports');
            }

            $filePath = $file->storeAs('temp_imports', $fileName, 'local');
            $fullPath = Storage::disk('local')->path($filePath);

            $batchNumber = 'AST-IMP-' . date('Ymd-His');

            $finalDocPath = null;
            if ($request->hasFile('support_doc')) {
                $doc = $request->file('support_doc');
                $docName = time() . '_DOC_' . str_replace(['(', ')', ' '], '_', $doc->getClientOriginalName());
                $finalDocPath = $doc->storeAs('Upload_asset/' . $batchNumber, $docName, 'public');
            }

            $batch = \App\Models\FixedAssetImportBatch::create([
                'batch_number' => $batchNumber,
                'user_id'      => auth()->id(),
                'status'       => 'draft',
                'file_path'    => $filePath,
                'support_doc'  => $finalDocPath,
            ]);

            \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\FixedAssetImport($batch->id), $fullPath);
            Storage::disk('local')->delete($filePath);

            // 🔥 VALIDASI KELENGKAPAN BATCH SETELAH BACA EXCEL 🔥
            $totalRows = $batch->details()->count();
            if ($totalRows === 0) {
                $batch->delete();
                return redirect()->route('fixed-assets.index')->with('error', 'File Excel kosong atau tidak memiliki baris data yang terbaca.');
            }

            $invalidCount = $batch->details()->where('is_valid', 0)->count();
            if ($invalidCount > 0) {
                return redirect()->route('fixed-assets.import_staging', $batch->id)
                    ->with('error', "Ditemukan {$invalidCount} baris data tidak valid! Harap perbaiki file Excel Anda, batalkan batch ini, dan upload ulang.");
            }

            return redirect()->route('fixed-assets.import_staging', $batch->id)->with('success', 'Data berhasil masuk ke Ruang Karantina dan seluruh baris lolos validasi!');
        } catch (\Exception $e) {
            if (isset($filePath)) Storage::disk('local')->delete($filePath);
            return redirect()->route('fixed-assets.index')->with('error', 'Gagal memproses excel: ' . $e->getMessage());
        }
    }

    public function importStaging($batch_id)
    {
        $batch = \App\Models\FixedAssetImportBatch::with('details')->findOrFail($batch_id);
        return view('fixed_assets.import_staging', compact('batch'));
    }

    public function submitApproval($batch_id)
    {
        DB::beginTransaction();
        try {
            $batch = \App\Models\FixedAssetImportBatch::with('details')->findOrFail($batch_id);

            if ($batch->details->count() === 0) {
                throw new \Exception("Batch kosong. Tidak ada data aset yang dapat diajukan.");
            }

            if ($batch->details->where('is_valid', 0)->count() > 0) {
                throw new \Exception("Ada baris data yang error. Harap perbaiki form Excel Anda dan upload ulang.");
            }

            $needsApproval = \App\Services\ApprovalService::generateWorkflow($batch);

            if ($needsApproval) {
                $batch->update(['status' => 'waiting_approval']);
                DB::commit();
                return back()->with('success', 'Draft berhasil diajukan ke Atasan!');
            } else {
                $this->processAssetImport($batch);
                $batch->update(['status' => 'approved']);

                DB::commit();
                return redirect()->route('fixed-assets.index')->with('success', 'Buku Aset Resmi disahkan otomatis! Item baru telah digenerate.');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    public function decide(Request $request, $batch_id)
    {
        DB::beginTransaction();
        try {
            $batch = \App\Models\FixedAssetImportBatch::with('details')->findOrFail($batch_id);
            $action = strtoupper($request->input('action', ''));
            $currentApproval = \App\Models\DocumentApproval::with('role')->where('document_id', $batch->id)
                ->where('document_type', get_class($batch))->where('status', 'PENDING')->orderBy('step_order', 'asc')->first();

            if (!$currentApproval && $action !== 'REJECT') {
                DB::rollBack();
                return back()->with('error', 'Tidak menunggu persetujuan Anda.');
            }

            if ($action === 'REJECT') {
                $currentApproval->update(['status' => 'REJECTED', 'approved_by' => auth()->id(), 'approved_at' => now()]);
                $batch->update(['status' => 'draft']);
                DB::commit();
                return back()->with('error', 'Pengajuan dikembalikan ke Draft.');
            }

            // 🔥 BLOKIR APPROVE JIKA ADA BARIS TIDAK VALID 🔥
            if ($batch->details->where('is_valid', 0)->count() > 0) {
                DB::rollBack();
                return back()->with('error', 'Gagal menyetujui: Terdapat baris data yang tidak valid di dalam batch ini.');
            }

            $currentApproval->update(['status' => 'APPROVED', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            $nextApproval = \App\Models\DocumentApproval::where('document_id', $batch->id)->where('document_type', get_class($batch))
                ->where('status', 'PENDING')->orderBy('step_order', 'asc')->first();

            if ($nextApproval) {
                DB::commit();
                return back()->with('success', 'Disetujui. Diteruskan ke atasan berikutnya.');
            } else {
                $this->processAssetImport($batch);
                $batch->update(['status' => 'approved']);

                DB::commit();
                return redirect()->route('fixed-assets.index')->with('success', 'Buku Aset Resmi disahkan! Item baru telah digenerate otomatis.');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    private function processAssetImport($batch)
    {
        $yearMonth = date('Y/m');
        $details = \App\Models\FixedAssetImportDetail::where('batch_id', $batch->id)->get();

        // 🔥 KUNCI PENGAMAN: Pastikan tidak memproses jika ada satupun baris invalid 🔥
        if ($details->where('is_valid', 0)->count() > 0) {
            throw new \Exception("Proses import dibatalkan karena masih terdapat data yang tidak valid.");
        }

        // Ambil status default tipe AST (Available di Gudang & In Use)
        $defaultAvailableStatus = \App\Models\Status::where('type', 'AST')
            ->whereIn('slug', ['available', 'ready', 'in_warehouse', 'active', 'good'])
            ->orderBy('sequence', 'asc')
            ->first();

        $defaultInUseStatus = \App\Models\Status::where('type', 'AST')
            ->where('slug', 'in_use')
            ->first();

        foreach ($details as $row) {
            $item = null;

            // 1. Cari atau Buat Master Barang
            if (!empty($row->kode_barang)) {
                $item = \App\Models\Item::lockForUpdate()->where('code', $row->kode_barang)->first();
            }

            if (!$item) {
                $namaAsetBaru = $row->nama_spesifik_aset ?? 'Aset Import Tanpa Nama';
                $item = \App\Models\Item::lockForUpdate()->where('name', $namaAsetBaru)->first();

                if (!$item) {
                    $lastItem = \App\Models\Item::orderBy('id', 'desc')->lockForUpdate()->first();
                    $nextId   = $lastItem ? $lastItem->id + 1 : 1;
                    $newCode  = 'ITM-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                    $slug     = \Illuminate\Support\Str::slug($namaAsetBaru) . '-' . \Illuminate\Support\Str::random(4);

                    $cat = \App\Models\Category::firstOrCreate(
                        ['name' => 'Umum'], ['code' => 'UMU']
                    );
                    $uom = \App\Models\Uom::firstOrCreate(['code' => 'PCS'], ['name' => 'Pieces']);

                    $item = \App\Models\Item::create([
                        'code'           => $newCode,
                        'slug'           => $slug,
                        'name'           => $namaAsetBaru,
                        'category_id'    => $cat->id,
                        'uom_id'         => $uom->id,
                        'item_type_code' => 'AST',
                        'is_trackable'   => 1,
                        'is_active'      => 1,
                        'current_stock'  => 0,
                        'specification'  => 'Item dibuat otomatis dari Import Aset'
                    ]);
                }
            }

            // 2. Ambil ID & Validasi Status Hanya Tipe 'AST'
            $companyId       = !empty($row->nama_pt) ? (int) $row->nama_pt : 1;
            $warehouseId     = !empty($row->nama_gudang) ? (int) $row->nama_gudang : 1;
            $assetCategoryId = !empty($row->kategori_aset) ? (int) $row->kategori_aset : null;
            $assignedTo      = !empty($row->nama_peminjam) ? (int) $row->nama_peminjam : null;

            $rawStatusId = !empty($row->status_aset) ? (int) $row->status_aset : null;
            $status = $rawStatusId ? \App\Models\Status::where('id', $rawStatusId)->where('type', 'AST')->first() : null;

            // Jika ID status bukan tipe AST (misal nyasar ke status dokumen), gunakan status AST yang sah
            if (!$status) {
                $status = $assignedTo ? $defaultInUseStatus : $defaultAvailableStatus;
            }
            $statusId = $status ? $status->id : 1;

            if ($status && $status->slug !== 'in_use') {
                $assignedTo = null;
            }

            $userPenerima = $assignedTo ? \App\Models\User::find($assignedTo) : null;

            // 3. Format Tanggal Perolehan (Support Format Excel)
            $formattedDate = date('Y-m-d');
            if (!empty($row->tanggal_perolehan)) {
                if (is_numeric($row->tanggal_perolehan)) {
                    $formattedDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row->tanggal_perolehan)->format('Y-m-d');
                } else {
                    $formattedDate = date('Y-m-d', strtotime($row->tanggal_perolehan));
                }
            }

            $currency = \App\Models\Currency::where('code', strtoupper($row->mata_uang))->first();

            $lastAsset = \App\Models\FixedAsset::where('asset_number', 'like', "AST/{$yearMonth}/%")->orderBy('id', 'desc')->lockForUpdate()->first();
            $nextSeq = $lastAsset ? ((int) substr($lastAsset->asset_number, -4)) + 1 : 1;
            $assetNumber = "AST/{$yearMonth}/" . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

            $asset = \App\Models\FixedAsset::create([
                'asset_number'            => $assetNumber,
                'item_id'                 => $item->id,
                'asset_category_id'       => $assetCategoryId,
                'name'                    => $row->nama_spesifik_aset ?: $item->name,
                'warehouse_id'            => $warehouseId,
                'company_id'              => $companyId,
                'department_id'           => $userPenerima ? $userPenerima->department_id : null,
                'serial_number'           => $row->serial_number,
                'accounting_asset_number' => $row->label_akuntansi,
                'status_id'               => $statusId,
                'assigned_to'             => $assignedTo,
                'spesifikasi_detail'      => $row->spesifikasi,
                'notes'                   => trim($row->catatan),
                'acquisition_date'        => $formattedDate,
                'purchase_price'          => $row->harga_beli ?: 0,
                'currency_id'             => $currency ? $currency->id : 1,
                'supporting_document'     => $batch->support_doc,
                'batch_id'                => $batch->batch_number,
            ]);

            \App\Models\FixedAssetHistory::create([
                'fixed_asset_id' => $asset->id,
                'status'         => $status ? $status->name : 'Registered',
                'assigned_to'    => $assignedTo,
                'notes'          => 'Di-import via Karantina (Batch: '.$batch->batch_number.')',
                'created_by'     => auth()->id()
            ]);

            // 4. Mutasi Stok Pintar
            $currStock = (float) $item->current_stock;
            $balanceAfter = $currStock + 1;

            \App\Models\StockMutation::create([
                'item_id'          => $item->id,
                'warehouse_id'     => $warehouseId,
                'type'             => 'IN',
                'qty'              => 1,
                'balance_before'   => $currStock,
                'balance_after'    => $balanceAfter,
                'reference_number' => $assetNumber,
                'notes'            => "Penerimaan Aset Import",
                'created_by'       => auth()->id()
            ]);

            if ($status && $status->slug === 'in_use') {
                \App\Models\StockMutation::create([
                    'item_id'          => $item->id,
                    'warehouse_id'     => $warehouseId,
                    'type'             => 'OUT',
                    'qty'              => 1,
                    'balance_before'   => $balanceAfter,
                    'balance_after'    => $balanceAfter - 1,
                    'reference_number' => 'GI-' . $assetNumber,
                    'notes'            => "Aset langsung diserahkan ke pengguna (Import)",
                    'created_by'       => auth()->id()
                ]);
                $item->update(['current_stock' => $balanceAfter - 1]);
            } else {
                $item->update(['current_stock' => $balanceAfter]);
            }
        }
    }

    public function cancelImport($batch_id)
    {
        $batch = \App\Models\FixedAssetImportBatch::where('id', $batch_id)
                    ->orWhere('batch_number', $batch_id)
                    ->firstOrFail();

        if (!empty($batch->support_doc) && \Illuminate\Support\Facades\Storage::disk('public')->exists($batch->support_doc)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($batch->support_doc);
        }

        if (!empty($batch->file_path) && \Illuminate\Support\Facades\Storage::disk('local')->exists($batch->file_path)) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($batch->file_path);
        }

        $batch->details()->delete();
        $batch->delete();

        return redirect()->route('fixed-assets.index')->with('success', 'Draft Import Aset berhasil dibatalkan dan file dibersihkan.');
    }

    public function downloadTemplate()
    {
        return Excel::download(new \App\Exports\AssetsTemplateExport, 'Template_Import_Aset_Lengkap.xlsx');
    }

    public function importHistory()
    {
        $batches = \App\Models\FixedAssetImportBatch::with('user')->orderBy('created_at', 'desc')->paginate(10);
        return view('fixed_assets.import_history', compact('batches'));
    }

    public function printBastByBatch($batchId)
    {
        $batch = \App\Models\FixedAssetImportBatch::where('batch_number', $batchId)->firstOrFail();
        $assets = \App\Models\FixedAsset::where('batch_id', $batchId)
                    ->whereNotNull('assigned_to')
                    ->with(['item', 'user', 'company'])
                    ->get();

        if ($assets->isEmpty()) {
            return back()->with('error', 'Tidak ada aset berstatus "In Use" (Dipinjam) pada Batch ini untuk dicetak BAST-nya.');
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('fixed_assets.pdf_bast_massal', compact('assets', 'batch'))->setPaper('A4', 'portrait');
        return $pdf->download("BAST_{$batchId}.pdf");
    }

    public function showImportBatch($batchId)
    {
        $batch = \App\Models\FixedAssetImportBatch::where('batch_number', $batchId)->with('user')->firstOrFail();
        $assets = \App\Models\FixedAsset::where('batch_id', $batchId)
                    ->with(['item', 'user', 'company', 'status'])
                    ->paginate(20);

        return view('fixed_assets.import_history_show', compact('batch', 'assets'));
    }

    public function printQrLabel(\Illuminate\Http\Request $request, $id)
    {
        return redirect()->to('/inventory/print-label/' . $id . '?' . http_build_query($request->query()));
    }

    public function printMassQrLabel(\Illuminate\Http\Request $request, $batchId)
    {
        $ids = \App\Models\FixedAsset::where('batch_id', $batchId)
                    ->orderBy('id', 'asc')
                    ->pluck('id')
                    ->toArray();

        if (empty($ids)) {
            return back()->with('error', 'Tidak ada aset pada Batch ini untuk dicetak.');
        }

        // Ambil ID pertama sebagai fallback parameter {id} pada URL, dan kirim semua ID lewat ?ids=1,2,3,...
        $firstId = $ids[0];
        $queryParams = array_merge($request->query(), [
            'ids' => implode(',', $ids),
        ]);

        return redirect()->to('/inventory/print-label/' . $firstId . '?' . http_build_query($queryParams));
    }

    public function searchItems(Request $request)
    {
        $search = $request->search;

        $items = \App\Models\Item::where('is_active', 1)
                    ->when($search, function($query) use ($search) {
                        $query->where(function($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                              ->orWhere('code', 'like', "%{$search}%");
                        });
                    })
                    ->limit(30)
                    ->get();

        $formattedItems = [];
        foreach ($items as $item) {
            $formattedItems[] = [
                'id'   => $item->id,
                'text' => '[' . $item->code . '] ' . $item->name
            ];
        }

        return response()->json($formattedItems);
    }

    public function hibahHistory()
    {
        $hibahs = \App\Models\FixedAsset::select(
                'batch_id', 'created_at', 'supporting_document', 'notes',
                \DB::raw('COUNT(id) as total_items'),
                \DB::raw('MAX(name) as sample_name')
            )
            ->where('batch_id', 'like', 'HIBAH-%')
            ->groupBy('batch_id', 'created_at', 'supporting_document', 'notes')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('fixed_assets.hibah_history', compact('hibahs'));
    }

    // =========================================================================
    // 🔥 MASTER LIST ASET (GROUPING MATA UANG & FILTER PERUSAHAAN) 🔥
    // =========================================================================
    public function masterList(\Illuminate\Http\Request $request)
    {
        $voidStatusIds = \App\Models\Status::where('type', 'AST')
            ->whereIn('slug', ['void', 'batal', 'canceled', 'cancelled'])
            ->pluck('id')->toArray();

        $disposedStatusIds = \App\Models\Status::where('type', 'AST')
            ->where('slug', 'disposed')
            ->pluck('id')->toArray();

        $companies = \App\Models\Company::orderBy('name')->get();

        $query = \App\Models\FixedAsset::with([
            'item.category', 'assetCategory', 'assignee.department',
            'company', 'department', 'status', 'warehouse', 'currency'
        ]);

        if (!empty($voidStatusIds)) {
            $query->whereNotIn('status_id', $voidStatusIds);
        }
        $query->where(function ($q) {
            $q->whereNull('notes')
              ->orWhere('notes', 'not like', '%[DIBATALKAN%');
        });

        // Filter Perusahaan
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // Filter Status Keberadaan
        if ($request->filled('status')) {
            if ($request->status === 'in_use') {
                $query->whereNotNull('assigned_to');
            } elseif ($request->status === 'in_warehouse') {
                $query->whereNull('assigned_to');
            }
        }

        // Pencarian Universal
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('asset_number', 'like', "%{$search}%")
                  ->orWhere('accounting_asset_number', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('spesifikasi_detail', 'like', "%{$search}%")
                  ->orWhereHas('assignee', function ($userQ) use ($search) {
                      $userQ->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('item', function ($itemQ) use ($search) {
                      $itemQ->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('company', function ($compQ) use ($search) {
                      $compQ->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $assets = $query->latest()->paginate(15)->withQueryString();

        // KPI mengikuti filter Perusahaan yang dipilih
        $kpiQuery = \App\Models\FixedAsset::with('currency');
        $excludeKpiIds = array_merge($voidStatusIds, $disposedStatusIds);

        if (!empty($excludeKpiIds)) {
            $kpiQuery->whereNotIn('status_id', $excludeKpiIds);
        }
        $kpiQuery->where(function ($q) {
            $q->whereNull('notes')
              ->orWhere('notes', 'not like', '%[DIBATALKAN%');
        });
        if ($request->filled('company_id')) {
            $kpiQuery->where('company_id', $request->company_id);
        }

        $allKpiAssets = (clone $kpiQuery)->get();
        $totalAssets  = $allKpiAssets->count();
        $inUse        = $allKpiAssets->whereNotNull('assigned_to')->count();
        $inWarehouse  = $allKpiAssets->whereNull('assigned_to')->count();

        // Grouping Total Nilai Perolehan & Nilai Buku berdasarkan relasi Currency
        $currencyTotals = [];
        foreach ($allKpiAssets as $kpiItem) {
            $currObj  = is_object($kpiItem->currency) ? $kpiItem->currency : null;
            $currCode = strtoupper($currObj->code ?? (is_string($kpiItem->currency) ? $kpiItem->currency : 'IDR'));
            $symbol   = $currObj->symbol ?? ($currCode === 'IDR' ? 'Rp' : $currCode);

            if (!isset($currencyTotals[$currCode])) {
                $currencyTotals[$currCode] = [
                    'symbol'   => $symbol,
                    'purchase' => 0,
                    'book'     => 0,
                ];
            }
            $currencyTotals[$currCode]['purchase'] += (float) ($kpiItem->purchase_price ?? 0);
            $currencyTotals[$currCode]['book']     += (float) ($kpiItem->net_book_value ?? $kpiItem->purchase_price ?? 0);
        }

        return view('fixed_assets.list_asset', compact(
            'assets', 'companies', 'totalAssets', 'inUse', 'inWarehouse',
            'currencyTotals', 'disposedStatusIds'
        ));
    }

    public function exportMasterList(\Illuminate\Http\Request $request)
    {
        $namaFile = 'Laporan_Master_Data_Aset_' . date('Y-m-d_H-i-s') . '.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\MasterAssetExport($request->all()), $namaFile);
    }



    // =========================================================================
    // 🔥 MESIN PROSES PENYERAHAN / UPDATE STATUS ASET (DINAMIS + NOMOR TRANSAKSI) 🔥
    // =========================================================================
    public function handoverAsset(\Illuminate\Http\Request $request, $id)
    {
        $request->validate([
            'assigned_to'    => 'nullable|exists:users,id',
            'status_id'      => 'required|exists:statuses,id',
            'handover_date'  => 'required|date',
            'handover_notes' => 'nullable|string'
        ]);

        try {
            $generatedTrxNumber = null;

            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $id, &$generatedTrxNumber) {
                $asset = \App\Models\FixedAsset::lockForUpdate()->findOrFail($id);
                $previousWarehouseId = $asset->warehouse_id;

                if (!empty($asset->assigned_to)) {
                    throw new \Exception('Aset ini sedang dipakai dan belum dikembalikan ke gudang.');
                }

                $newStatus = \App\Models\Status::find($request->status_id);

                if (optional($newStatus)->slug === 'in_use' && empty($request->assigned_to)) {
                    throw new \Exception('Staf penerima WAJIB diisi jika status diubah menjadi Dipakai (In Use).');
                }

                $userPenerima = $request->assigned_to ? \App\Models\User::find($request->assigned_to) : null;

                // 1. Generate Nomor Dokumen Resmi (BAST jika diserahkan ke user, AST-TRX jika hanya perubahan status)
                $prefix = $userPenerima ? 'BAST' : 'AST-TRX';
                $trxNumber = $this->generateTransactionNumber($prefix);
                $generatedTrxNumber = $trxNumber;

                // 2. Update Status & Kepemilikan Aset
                $asset->assigned_to   = $request->assigned_to;
                $asset->department_id = $userPenerima ? $userPenerima->department_id : null;
                $asset->status_id     = $request->status_id;

                if ($userPenerima || in_array(optional($newStatus)->slug, ['disposed', 'void'])) {
                    $asset->warehouse_id = null;
                }

                if ($request->filled('handover_notes')) {
                    $asset->notes = trim($request->handover_notes);
                }

                $asset->save();

                // 3. Simpan Header Transaksi Resmi ke Tabel fixed_asset_transactions
                \App\Models\FixedAssetTransaction::create([
                    'transaction_number' => $trxNumber,
                    'transaction_type'   => $userPenerima ? 'HANDOVER' : 'STATUS_CHANGE',
                    'transaction_date'   => $request->handover_date,
                    'fixed_asset_id'     => $asset->id,
                    'from_user_id'       => auth()->id(),
                    'to_user_id'         => $request->assigned_to,
                    'from_warehouse_id'  => $previousWarehouseId,
                    'to_warehouse_id'    => $asset->warehouse_id,
                    'status_id'          => $request->status_id,
                    'notes'              => $request->handover_notes,
                    'created_by'         => auth()->id(),
                ]);

                // 4. Catat ke Riwayat Perjalanan Aset (Timeline Item) beserta Nomor Transaksinya
                $catatanTeks = $request->filled('handover_notes') ? trim($request->handover_notes) : '-';
                if ($userPenerima) {
                    $historyNotes = "[{$trxNumber}] Diserahkan ke User: {$userPenerima->name} | Catatan: {$catatanTeks}";
                } else {
                    $historyNotes = "[{$trxNumber}] Update Status menjadi " . (optional($newStatus)->name ?? '-') . " | Alasan: {$catatanTeks}";
                }

                \App\Models\FixedAssetHistory::create([
                    'fixed_asset_id' => $asset->id,
                    'status'         => $userPenerima ? 'HANDOVER' : (optional($newStatus)->name ?? 'Updated'),
                    'assigned_to'    => $request->assigned_to,
                    'notes'          => $historyNotes,
                    'created_by'     => auth()->id(),
                ]);

                // 5. Kurangi Stok Master Item & Catat Mutasi Gudang (Jika Keluar dari Gudang)
                if ($asset->warehouse_id === null && $previousWarehouseId && $asset->item_id) {
                    $item = \App\Models\Item::lockForUpdate()->find($asset->item_id);
                    if ($item) {
                        $balanceBefore = (float) $item->current_stock;
                        $item->current_stock = max(0, $balanceBefore - 1);
                        $item->save();

                        $invStock = \App\Models\InventoryStock::where('item_id', $item->id)
                            ->where('warehouse_id', $previousWarehouseId)
                            ->first();
                        if ($invStock && $invStock->stock_qty > 0) {
                            $invStock->decrement('stock_qty', 1);
                        }

                        \App\Models\StockMutation::create([
                            'item_id'          => $item->id,
                            'warehouse_id'     => $previousWarehouseId,
                            'type'             => 'OUT',
                            'qty'              => 1,
                            'balance_before'   => $balanceBefore,
                            'balance_after'    => $item->current_stock,
                            'reference_number' => $trxNumber,
                            'notes'            => 'Pengeluaran Aset (' . $asset->asset_number . ') ' . ($userPenerima ? 'ke User: ' . $userPenerima->name : 'Status: ' . optional($newStatus)->name),
                            'created_by'       => auth()->id(),
                        ]);
                    }
                }

                // 6. Sinkronkan Status di Tabel item_serials jika ada S/N
                if (!empty($asset->serial_number) && $asset->item_id) {
                    $snStatus = in_array(optional($newStatus)->slug, ['disposed', 'void']) ? 'DISPOSED' : ($userPenerima ? 'IN_USE' : 'AVAILABLE');
                    \DB::table('item_serials')
                        ->where('item_id', $asset->item_id)
                        ->where('serial_number', $asset->serial_number)
                        ->update(['status' => $snStatus, 'updated_at' => now()]);
                }
            });

            return redirect()->back()->with('success', "Transaksi aset berhasil diproses dengan Nomor Dokumen: {$generatedTrxNumber}");

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal memproses transaksi: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // 🔥 MESIN PROSES PENGEMBALIAN ASET (RETURN + NOMOR BAPA RESMI) 🔥
    // =========================================================================
    public function returnAsset(\Illuminate\Http\Request $request, $id)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'status_id'    => 'required|exists:statuses,id',
            'return_date'  => 'required|date',
            'return_notes' => 'nullable|string'
        ]);

        try {
            $generatedTrxNumber = null;

            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $id, &$generatedTrxNumber) {
                $asset = \App\Models\FixedAsset::lockForUpdate()->findOrFail($id);
                $previousUserId = $asset->assigned_to;

                if (empty($previousUserId)) {
                    throw new \Exception('Aset ini sudah berada di gudang dan tidak sedang dipegang oleh siapapun.');
                }

                $previousUser = \App\Models\User::find($previousUserId);
                $newStatus = \App\Models\Status::find($request->status_id);
                $warehouseTujuan = \App\Models\Warehouse::find($request->warehouse_id);
                $returnReason = $request->filled('return_notes') ? trim($request->return_notes) : 'Pengembalian rutin ke gudang.';

                // 1. Generate Nomor Dokumen BAPA Resmi
                $trxNumber = $this->generateTransactionNumber('BAPA');
                $generatedTrxNumber = $trxNumber;

                // 2. Simpan Header Transaksi ke fixed_asset_transactions
                \App\Models\FixedAssetTransaction::create([
                    'transaction_number' => $trxNumber,
                    'transaction_type'   => 'RETURN',
                    'transaction_date'   => $request->return_date,
                    'fixed_asset_id'     => $asset->id,
                    'from_user_id'       => $previousUserId,
                    'to_user_id'         => auth()->id(),
                    'from_warehouse_id'  => null,
                    'to_warehouse_id'    => $request->warehouse_id,
                    'status_id'          => $request->status_id,
                    'notes'              => $returnReason,
                    'created_by'         => auth()->id(),
                ]);

                // 3. Catat ke Timeline Aset
                \App\Models\FixedAssetHistory::create([
                    'fixed_asset_id' => $asset->id,
                    'status'         => 'RETURNED',
                    'assigned_to'    => null,
                    'notes'          => "[{$trxNumber}] Dikembalikan ke " . (optional($warehouseTujuan)->name ?? 'Gudang') . " oleh " . (optional($previousUser)->name ?? 'User') . " | Alasan: {$returnReason}",
                    'created_by'     => auth()->id(),
                ]);

                // 4. Update Data Aset
                $asset->assigned_to   = null;
                $asset->department_id = null;
                $asset->warehouse_id  = $request->warehouse_id;
                $asset->status_id     = $request->status_id;
                $asset->notes         = $returnReason;
                $asset->save();

                // 5. Tambah Stok Master Item & Catat Mutasi Gudang
                if ($asset->item_id) {
                    $item = \App\Models\Item::lockForUpdate()->find($asset->item_id);
                    if ($item) {
                        $balanceBefore = (float) $item->current_stock;
                        $item->current_stock = $balanceBefore + 1;
                        $item->save();

                        $invStock = \App\Models\InventoryStock::where('item_id', $item->id)
                            ->where('warehouse_id', $request->warehouse_id)
                            ->first();
                        if ($invStock) {
                            $invStock->increment('stock_qty', 1);
                        } else {
                            \App\Models\InventoryStock::create([
                                'company_id'       => $asset->company_id,
                                'warehouse_id'     => $request->warehouse_id,
                                'item_id'          => $item->id,
                                'stock_qty'        => 1,
                                'reference_number' => $trxNumber,
                                'notes'            => 'Pengembalian Aset ke Gudang',
                            ]);
                        }

                        \App\Models\StockMutation::create([
                            'item_id'          => $item->id,
                            'warehouse_id'     => $request->warehouse_id,
                            'type'             => 'IN',
                            'qty'              => 1,
                            'balance_before'   => $balanceBefore,
                            'balance_after'    => $item->current_stock,
                            'reference_number' => $trxNumber,
                            'notes'            => 'Pengembalian Aset (' . $asset->asset_number . ') dari ' . (optional($previousUser)->name ?? 'User'),
                            'created_by'       => auth()->id(),
                        ]);
                    }
                }

                // 6. Sinkronkan Status Serial Number
                if (!empty($asset->serial_number) && $asset->item_id) {
                    \DB::table('item_serials')
                        ->where('item_id', $asset->item_id)
                        ->where('serial_number', $asset->serial_number)
                        ->update(['status' => 'AVAILABLE', 'updated_at' => now()]);
                }
            });

            return redirect()->back()->with('success', "Aset berhasil dikembalikan ke Gudang dengan Nomor Dokumen: {$generatedTrxNumber}");

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal mengembalikan aset: ' . $e->getMessage());
        }
    }



    public function transactionHistory(\Illuminate\Http\Request $request)
    {
        $query = \App\Models\FixedAssetTransaction::with([
        'details.asset.item.category',
        'details.asset.assetCategory',
        'signers.user',
        'fromUser',
        'toUser',
        'warehouse',
        'status',
        'creator'
    ]);

        if ($request->filled('type')) {
            $query->where('transaction_type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                  ->orWhereHas('asset', function ($qa) use ($search) {
                      $qa->where('asset_number', 'like', "%{$search}%")
                         ->orWhere('accounting_asset_number', 'like', "%{$search}%")
                         ->orWhere('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('toUser', fn($qu) => $qu->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('fromUser', fn($qu) => $qu->where('name', 'like', "%{$search}%"));
            });
        }

        $transactions = $query->latest()->paginate(15)->withQueryString();
        $users = \App\Models\User::with('department')->orderBy('name')->get();

        return view('fixed_assets.transaction_history', compact('transactions', 'users'));
    }




    public function history($id)
    {
        $asset = \App\Models\FixedAsset::with(['item', 'company', 'warehouse', 'assignee'])->findOrFail($id);

        $histories = \App\Models\FixedAssetHistory::where('fixed_asset_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        $processedHistories = collect();
        $lastHandoverDate = null;

        foreach ($histories as $history) {
            $adminName = \App\Models\User::where('id', $history->created_by)->value('name') ?? 'System';
            $history->admin_name = $adminName;

            if ($history->status === 'HANDOVER') {
                $lastHandoverDate = $history->created_at;
                $history->durasi = null;
            } elseif ($history->status === 'RETURNED') {
                if ($lastHandoverDate) {
                    $startDate = \Carbon\Carbon::parse($lastHandoverDate)->startOfDay();
                    $endDate = \Carbon\Carbon::parse($history->created_at)->startOfDay();
                    $days = $startDate->diffInDays($endDate);

                    $history->durasi = ($days == 0 ? 1 : $days) . ' Hari Dipakai';
                    $lastHandoverDate = null;
                }
            } else {
                $history->durasi = null;
            }

            $processedHistories->push($history);
        }

        if ($lastHandoverDate && $asset->assigned_to) {
            $startDate = \Carbon\Carbon::parse($lastHandoverDate)->startOfDay();
            $today = now()->startOfDay();
            $days = $startDate->diffInDays($today);

            $asset->current_usage_duration = ($days == 0 ? 1 : $days) . ' Hari';
        }

        $historiesDesc = $processedHistories->sortByDesc('created_at')->values();

        return view('fixed_assets.history', compact('asset', 'historiesDesc'));
    }

    public function createManual()
    {
        $warehouses = \App\Models\Warehouse::orderBy('name', 'asc')->get();
        $assetCategories = \App\Models\AssetCategory::where('is_active', true)->orderBy('useful_life_years', 'asc')->get();
        $companies = \App\Models\Company::orderBy('name', 'asc')->get();
        $statuses = \App\Models\Status::where('type', 'AST')->orderBy('sequence', 'asc')->get();
        $currencies = \App\Models\Currency::where('is_active', 1)->get();
        $users = \App\Models\User::with(['company', 'department'])->orderBy('name', 'asc')->get();
        $acquisitionTypes = \App\Models\AcquisitionType::where('is_active', true)->orderBy('id', 'asc')->get();

        return view('fixed_assets.create_manual', compact('warehouses', 'assetCategories', 'companies', 'statuses', 'currencies', 'users', 'acquisitionTypes'));
    }

    public function createImport()
    {
        return view('fixed_assets.create_import');
    }

    // =========================================================================
    // 🔥 FITUR HAPUS / BATALKAN ASET (PENGGUNAAN SLUG MURNI & REVERSE STOK) 🔥
    // =========================================================================
    public function destroy($id)
    {
        try {
            DB::transaction(function () use ($id) {
                $asset = \App\Models\FixedAsset::findOrFail($id);

                if ($asset->assigned_to != null) {
                    throw new \Exception('Aset sedang digunakan oleh staf. Lakukan Retur terlebih dahulu jika ingin menghapusnya.');
                }

                $hasBeenUsed = \App\Models\FixedAssetHistory::where('fixed_asset_id', $id)
                                ->whereNotNull('assigned_to')
                                ->exists();

                if ($hasBeenUsed) {
                    throw new \Exception('TIDAK DIIZINKAN: Aset ini memiliki jejak riwayat pernah diserahkan/digunakan oleh staf. Gunakan fitur Edit -> ubah status menjadi "Disposed".');
                }

                $hasTransaction = \App\Models\FixedAssetHistory::where('fixed_asset_id', $id)
                                ->where(function($q) {
                                    $q->where('status', 'like', '%Maintenance%')
                                      ->orWhere('status', 'like', '%Disposed%')
                                      ->orWhere('status', 'like', '%Rusak%')
                                      ->orWhere('status', 'like', '%Retur%');
                                })->exists();

                if ($hasTransaction) {
                    throw new \Exception('TIDAK DIIZINKAN: Aset ini sudah memiliki riwayat transaksi aktif. Data tidak boleh dihapus.');
                }

                if ($asset->item_id && $asset->warehouse_id) {
                    $masterItem = \App\Models\Item::lockForUpdate()->find($asset->item_id);
                    if ($masterItem) {
                        $currStock = (float) $masterItem->current_stock;
                        $invStock = \App\Models\InventoryStock::where('item_id', $masterItem->id)
                                        ->where('warehouse_id', $asset->warehouse_id)
                                        ->first();

                        if ($asset->goods_receipt_id) {
                            $masterItem->update(['current_stock' => $currStock + 1]);

                            if ($invStock) {
                                $invStock->increment('stock_qty', 1);
                            } else {
                                \App\Models\InventoryStock::create([
                                    'company_id'       => $asset->company_id,
                                    'warehouse_id'     => $asset->warehouse_id,
                                    'item_id'          => $masterItem->id,
                                    'stock_qty'        => 1,
                                    'reference_number' => 'DEL-AST-' . $asset->asset_number,
                                    'notes'            => 'Pengembalian dari Hapus Aset GR',
                                ]);
                            }

                            \App\Models\StockMutation::create([
                                'item_id'          => $masterItem->id,
                                'warehouse_id'     => $asset->warehouse_id,
                                'type'             => 'IN',
                                'qty'              => 1,
                                'balance_before'   => $currStock,
                                'balance_after'    => $currStock + 1,
                                'reference_number' => 'DEL-AST-' . $asset->asset_number,
                                'notes'            => "Pengembalian stok (Batal Kapitalisasi GR)",
                                'created_by'       => auth()->id()
                            ]);

                        } else {
                            $masterItem->update(['current_stock' => max(0, $currStock - 1)]);

                            if ($invStock && $invStock->stock_qty > 0) {
                                $invStock->decrement('stock_qty', 1);
                            }

                            \App\Models\StockMutation::create([
                                'item_id'          => $masterItem->id,
                                'warehouse_id'     => $asset->warehouse_id,
                                'type'             => 'OUT',
                                'qty'              => 1,
                                'balance_before'   => $currStock,
                                'balance_after'    => max(0, $currStock - 1),
                                'reference_number' => 'DEL-AST-' . $asset->asset_number,
                                'notes'            => "Pengurangan stok (Batal Input Manual/Hibah)",
                                'created_by'       => auth()->id()
                            ]);
                        }

                        if (!empty($asset->serial_number)) {
                            \DB::table('item_serials')
                                ->where('item_id', $masterItem->id)
                                ->where('serial_number', $asset->serial_number)
                                ->update([
                                    'status' => 'AVAILABLE',
                                    'updated_at' => now()
                                ]);
                        }
                    }
                }

                $photos = \App\Models\AssetPhoto::where('fixed_asset_id', $id)->get();
                foreach ($photos as $photo) {
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($photo->file_path)) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($photo->file_path);
                    }
                    $photo->delete();
                }

                \App\Models\FixedAssetHistory::where('fixed_asset_id', $id)->delete();
                $asset->delete();
            });

            return redirect()->route('fixed-assets.index')->with('success', 'Aset berhasil dihapus. Mutasi stok telah dikoreksi secara otomatis.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal Menghapus: ' . $e->getMessage());
        }
    }



    private function generateTransactionNumber(string $prefix): string
    {
        $yearMonth = date('Y/m');
        $lastTrx = \App\Models\FixedAssetTransaction::where('transaction_number', 'like', "{$prefix}/{$yearMonth}/%")
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        $nextSeq = $lastTrx ? ((int) substr($lastTrx->transaction_number, -4)) + 1 : 1;
        return "{$prefix}/{$yearMonth}/" . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
    }

    public function transactions(Request $request)
    {
        $query = \App\Models\FixedAssetTransaction::with([
            'details.asset.item.category',
            'details.asset.assetCategory',
            'fromUser',
            'toUser',
            'warehouse',
            'status',
            'creator'
        ]);

        if ($request->filled('type')) {
            if ($request->type === 'DISPOSAL') {
                $query->where('transaction_type', 'STATUS_CHANGE')
                      ->whereHas('status', fn($qs) => $qs->where('slug', 'disposed'));
            } elseif ($request->type === 'REPAIR') {
                $query->whereIn('transaction_type', ['REPAIR_SEND', 'REPAIR_FINISH']);
            } else {
                $query->where('transaction_type', $request->type);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('details.asset', function ($qa) use ($search) {
                      $qa->where('asset_number', 'like', "%{$search}%")
                         ->orWhere('accounting_asset_number', 'like', "%{$search}%")
                         ->orWhere('serial_number', 'like', "%{$search}%")
                         ->orWhere('name', 'like', "%{$search}%")
                         ->orWhereHas('item', function ($qi) use ($search) {
                             $qi->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhereHas('category', fn($qc) => $qc->where('name', 'like', "%{$search}%"));
                         })
                         ->orWhereHas('assetCategory', fn($qac) => $qac->where('name', 'like', "%{$search}%"));
                  })
                  ->orWhereHas('toUser', fn($qu) => $qu->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('fromUser', fn($qu) => $qu->where('name', 'like', "%{$search}%"));
            });
        }

        $transactions = $query->latest()->paginate(15)->withQueryString();

        // Statistik ringkas untuk KPI Cards
        $stats = [
            'total'    => \App\Models\FixedAssetTransaction::count(),
            'handover' => \App\Models\FixedAssetTransaction::where('transaction_type', 'HANDOVER')->count(),
            'return'   => \App\Models\FixedAssetTransaction::where('transaction_type', 'RETURN')->count(),
            'repair'   => \App\Models\FixedAssetTransaction::whereIn('transaction_type', ['REPAIR_SEND', 'REPAIR_FINISH'])->count(),
            'disposal' => \App\Models\FixedAssetTransaction::where('transaction_type', 'STATUS_CHANGE')
                            ->whereHas('status', fn($qs) => $qs->where('slug', 'disposed'))->count(),
        ];

        return view('fixed_assets.transactions', compact('transactions', 'stats'));
    }




    public function createTransaction()
    {
        $voidDisposedIds = Status::where('type', 'AST')
            ->whereIn('slug', ['void', 'batal', 'canceled', 'cancelled', 'disposed'])
            ->pluck('id');

        $maintenanceStatusIds = Status::where('type', 'AST')
            ->where(function ($q) {
                $q->whereIn('slug', ['maintenance', 'repair', 'in_repair', 'service', 'rusak', 'broken'])
                  ->orWhere('name', 'like', '%Maintenance%')
                  ->orWhere('name', 'like', '%Servis%')
                  ->orWhere('name', 'like', '%Perbaikan%')
                  ->orWhere('name', 'like', '%Rusak%');
            })
            ->pluck('id');

        // 1. Aset siap serah terima di Gudang (Hanya yang siap pakai / bukan rusak & bukan servis)
        $availableAssets = FixedAsset::with(['item', 'warehouse', 'status'])
            ->whereNull('assigned_to')
            ->whereNotIn('status_id', $voidDisposedIds)
            ->when($maintenanceStatusIds->isNotEmpty(), fn($q) => $q->whereNotIn('status_id', $maintenanceStatusIds))
            ->whereNotNull('warehouse_id')
            ->orderBy('asset_number', 'asc')
            ->get();

        // 2. Aset sedang dipakai Staf (Untuk Return / BAPA)
        $inUseAssets = FixedAsset::with(['item', 'assignee.department', 'status'])
            ->whereNotNull('assigned_to')
            ->whereNotIn('status_id', $voidDisposedIds)
            ->orderBy('asset_number', 'asc')
            ->get();

        // 3. Aset yang sedang di Service Center / Maintenance (Khusus Selesai Perbaikan / BAPA-SRV)
        $repairAssets = FixedAsset::with(['item', 'warehouse', 'status'])
            ->whereNull('assigned_to')
            ->whereNotIn('status_id', $voidDisposedIds)
            ->where(function ($q) use ($maintenanceStatusIds) {
                if ($maintenanceStatusIds->isNotEmpty()) {
                    $q->whereIn('status_id', $maintenanceStatusIds);
                }
                $q->orWhere('notes', 'like', 'Service Center:%')
                  ->orWhereNull('warehouse_id');
            })
            ->orderBy('asset_number', 'asc')
            ->get();

        // 4. Semua aset aktif (Untuk Kirim Perbaikan ke Service Center / BASP & Update Status / Disposal)
        $allActiveAssets = FixedAsset::with(['item', 'warehouse', 'assignee.department', 'status'])
            ->whereNotIn('status_id', $voidDisposedIds)
            ->orderBy('asset_number', 'asc')
            ->get();

        $warehouses = \App\Models\Warehouse::orderBy('name')->get();
        $statuses   = Status::where('type', 'AST')->orderBy('sequence')->get();
        $users      = User::with(['department', 'company'])->orderBy('name')->get();

        return view('fixed_assets.create_transaction', compact(
            'availableAssets', 'inUseAssets', 'repairAssets', 'allActiveAssets', 'warehouses', 'statuses', 'users'
        ));
    }


    public function storeTransaction(Request $request)
    {
        $request->validate([
            'transaction_type'    => 'required|in:HANDOVER,RETURN,REPAIR_SEND,REPAIR_FINISH,STATUS_CHANGE',
            'transaction_date'    => 'required|date',
            'asset_ids'           => 'required|array|min:1',
            'asset_ids.*'         => 'exists:fixed_assets,id',
            'status_id'           => 'required|exists:statuses,id',
            'to_user_id'          => 'required_if:transaction_type,HANDOVER|nullable|exists:users,id',
            'warehouse_id'        => 'required_if:transaction_type,RETURN,REPAIR_FINISH|nullable|exists:warehouses,id',
            'service_center_name' => 'required_if:transaction_type,REPAIR_SEND|nullable|string|max:255',
            'rma_number'          => 'nullable|string|max:100',
            'notes'               => 'nullable|string',
        ], [
            'asset_ids.required'              => 'Pilih minimal 1 unit aset untuk diproses.',
            'to_user_id.required_if'          => 'Staf penerima wajib dipilih untuk transaksi Serah Terima (BAST).',
            'warehouse_id.required_if'        => 'Gudang tujuan wajib dipilih untuk transaksi Pengembalian / Selesai Servis.',
            'service_center_name.required_if' => 'Nama Service Center / Vendor wajib diisi saat mengirim aset perbaikan.',
        ]);

        try {
            $trxCreated = null;

            DB::transaction(function () use ($request, &$trxCreated) {
                $type = $request->transaction_type;

                $newStatus  = Status::findOrFail($request->status_id);
                $statusSlug = strtolower($newStatus->slug ?? '');

                $prefix = match ($type) {
                    'HANDOVER'      => 'BAST',
                    'RETURN'        => 'BAPA',
                    'REPAIR_SEND'   => 'BASP',
                    'REPAIR_FINISH' => 'BAPA-SRV',
                    default         => ($statusSlug === 'disposed' ? 'BAPP' : 'AST-TRX'),
                };

                // Pengaman Backend: Kunci kombinasi Jenis Transaksi & Status Aset Baru
                $allowedSlugsByType = [
                    'HANDOVER'      => ['in_use'],
                    'RETURN'        => ['available', 'returned', 'maintenance', 'repair', 'rusak'],
                    'REPAIR_SEND'   => ['maintenance', 'repair', 'in_repair', 'service', 'rusak'],
                    'REPAIR_FINISH' => ['available', 'returned', 'maintenance', 'repair', 'rusak'],
                    'STATUS_CHANGE' => ['disposed', 'maintenance', 'repair', 'rusak', 'available', 'returned'],
                ];

                if (isset($allowedSlugsByType[$type]) && !in_array($statusSlug, $allowedSlugsByType[$type])) {
                    throw new \Exception("Status '{$newStatus->name}' tidak diizinkan untuk jenis transaksi {$type}.");
                }

                $trxNumber    =$this->generateTransactionNumber($prefix);$userPenerima = $request->to_user_id ? User::find($request->to_user_id) : null;

                $firstAsset = FixedAsset::findOrFail($request->asset_ids[0]);$fromUserId = ($type === 'RETURN') ?$firstAsset->assigned_to : auth()->id();

                // Susun catatan terstruktur khusus jika transaksi pengiriman ke Service Center
                $compiledNotes =$request->notes ?: '';
                if ($type === 'REPAIR_SEND') {
                    $scName  = trim($request->service_center_name);
                    $rmaNo   = trim($request->rma_number ?: '-');
                    $keluhan = trim($request->notes ?: '-');
                    $compiledNotes = "Service Center: {$scName} | No. RMA/Tiket: {$rmaNo} | Keluhan/Keterangan: {$keluhan}";
                }

                $trxCreated = \App\Models\FixedAssetTransaction::create([
                    'transaction_number' => $trxNumber,
                    'transaction_type'   => $type,
                    'transaction_date'   => $request->transaction_date,
                    'fixed_asset_id'     => $firstAsset->id,
                    'from_user_id'       => $fromUserId,
                    'to_user_id'         => ($type === 'HANDOVER') ? $request->to_user_id : (($type === 'RETURN') ? auth()->id() : null),
                    'warehouse_id'       => in_array($type, ['RETURN', 'REPAIR_FINISH']) ?$request->warehouse_id : null,
                    'status_id'          => $request->status_id,
                    'notes'              => $compiledNotes,
                    'created_by'         => auth()->id(),
                ]);

                foreach ($request->asset_ids as$assetId) {
                    $asset = FixedAsset::lockForUpdate()->findOrFail($assetId);
                    $prevWarehouseId =$asset->warehouse_id;
                    $prevUserId      =$asset->assigned_to;

                    \App\Models\FixedAssetTransactionDetail::create([
                        'fixed_asset_transaction_id' => $trxCreated->id,
                        'fixed_asset_id'             => $asset->id,
                        'previous_warehouse_id'      => $prevWarehouseId,
                        'previous_user_id'           => $prevUserId,
                    ]);

                    if ($type === 'HANDOVER') {
                        if (!empty($asset->assigned_to)) {
                            throw new \Exception("Aset {$asset->asset_number} sedang dipakai staf lain.");
                        }

                        $asset->update([
                            'assigned_to'   => $userPenerima->id,
                            'department_id' => $userPenerima->department_id,
                            'status_id'     => $newStatus->id,
                            'warehouse_id'  => null,
                            'notes'         => $request->notes ?: $asset->notes,
                        ]);

                        \App\Models\FixedAssetHistory::create([
                            'fixed_asset_id' => $asset->id,
                            'status'         => 'HANDOVER',
                            'assigned_to'    => $userPenerima->id,
                            'notes'          => "[{$trxNumber}] Diserahkan ke {$userPenerima->name} | Catatan: " . ($request->notes ?: '-'),
                            'created_by'     => auth()->id(),
                        ]);

                        if ($prevWarehouseId &&$asset->item_id) {
                            $item = \App\Models\Item::lockForUpdate()->find($asset->item_id);
                            if ($item) {
                                $before = (float)$item->current_stock;
                                $item->update(['current_stock' => max(0,$before - 1)]);
                                \App\Models\StockMutation::create([
                                    'item_id' => $item->id, 'warehouse_id' =>$prevWarehouseId,
                                    'type' => 'OUT', 'qty' => 1, 'balance_before' => $before, 'balance_after' =>$item->current_stock,
                                    'reference_number' => $trxNumber, 'notes' => "Serah Terima Aset ({$asset->asset_number}) ke {$userPenerima->name}",
                                    'created_by' => auth()->id(),
                                ]);
                            }
                        }
                    } elseif ($type === 'RETURN') {
                        if (empty($asset->assigned_to)) {
                            throw new \Exception("Aset {$asset->asset_number} sudah berada di gudang.");
                        }
                        $prevUser = User::find($prevUserId);

                        $asset->update([
                            'assigned_to'   => null,
                            'department_id' => null,
                            'warehouse_id'  => $request->warehouse_id,
                            'status_id'     => $newStatus->id,
                            'notes'         => $request->notes ?: $asset->notes,
                        ]);

                        \App\Models\FixedAssetHistory::create([
                            'fixed_asset_id' => $asset->id,
                            'status'         => 'RETURNED',
                            'assigned_to'    => null,
                            'notes'          => "[{$trxNumber}] Dikembalikan oleh " . (optional($prevUser)->name ?? 'User') . " | Alasan: " . ($request->notes ?: '-'),
                            'created_by'     => auth()->id(),
                        ]);

                        if ($asset->item_id) {
                            $item = \App\Models\Item::lockForUpdate()->find($asset->item_id);
                            if ($item) {
                                $before = (float)$item->current_stock;
                                $item->update(['current_stock' =>$before + 1]);
                                \App\Models\StockMutation::create([
                                    'item_id' => $item->id, 'warehouse_id' =>$request->warehouse_id,
                                    'type' => 'IN', 'qty' => 1, 'balance_before' => $before, 'balance_after' =>$item->current_stock,
                                    'reference_number' => $trxNumber, 'notes' => "Pengembalian Aset ({$asset->asset_number})",
                                    'created_by' => auth()->id(),
                                ]);
                            }
                        }
                    } elseif ($type === 'REPAIR_SEND') {
                        $prevHolderInfo =$prevUserId
                            ? 'Pemegang sebelumnya: ' . (optional(User::find($prevUserId))->name ?? '-')
                            : 'Dari Gudang';

                        $asset->update([
                            'assigned_to'   => null,
                            'department_id' => null,
                            'warehouse_id'  => null,
                            'status_id'     => $newStatus->id,
                            'notes'         => $compiledNotes,
                        ]);

                        \App\Models\FixedAssetHistory::create([
                            'fixed_asset_id' => $asset->id,
                            'status'         => 'REPAIR_SEND',
                            'assigned_to'    => null,
                            'notes'          => "[{$trxNumber}] Dikirim ke {$compiledNotes} ({$prevHolderInfo})",
                            'created_by'     => auth()->id(),
                        ]);

                        if ($prevWarehouseId &&$asset->item_id) {
                            $item = \App\Models\Item::lockForUpdate()->find($asset->item_id);
                            if ($item) {
                                $before = (float)$item->current_stock;
                                $item->update(['current_stock' => max(0,$before - 1)]);
                                \App\Models\StockMutation::create([
                                    'item_id' => $item->id, 'warehouse_id' =>$prevWarehouseId,
                                    'type' => 'OUT', 'qty' => 1, 'balance_before' => $before, 'balance_after' =>$item->current_stock,
                                    'reference_number' => $trxNumber, 'notes' => "Pengiriman Servis Aset ({$asset->asset_number}) ke {$request->service_center_name}",
                                    'created_by' => auth()->id(),
                                ]);
                            }
                        }
                    } elseif ($type === 'REPAIR_FINISH') {$asset->update([
                            'assigned_to'   => null,
                            'department_id' => null,
                            'warehouse_id'  => $request->warehouse_id,
                            'status_id'     => $newStatus->id,
                            'notes'         => $request->notes ?: 'Selesai perbaikan dari Service Center.',
                        ]);

                        \App\Models\FixedAssetHistory::create([
                            'fixed_asset_id' => $asset->id,
                            'status'         => 'REPAIR_FINISH',
                            'assigned_to'    => null,
                            'notes'          => "[{$trxNumber}] Selesai Perbaikan & Masuk Gudang | Catatan: " . ($request->notes ?: '-'),
                            'created_by'     => auth()->id(),
                        ]);

                        if ($asset->item_id) {
                            $item = \App\Models\Item::lockForUpdate()->find($asset->item_id);
                            if ($item) {
                                $before = (float)$item->current_stock;
                                $item->update(['current_stock' =>$before + 1]);
                                \App\Models\StockMutation::create([
                                    'item_id' => $item->id, 'warehouse_id' =>$request->warehouse_id,
                                    'type' => 'IN', 'qty' => 1, 'balance_before' => $before, 'balance_after' =>$item->current_stock,
                                    'reference_number' => $trxNumber, 'notes' => "Penerimaan Kembali Hasil Servis ({$asset->asset_number})",
                                    'created_by' => auth()->id(),
                                ]);
                            }
                        }
                    } else {
                        // STATUS_CHANGE / DISPOSAL
                        $isDisposed = in_array($statusSlug, ['disposed', 'void']);

                        if ($isDisposed && !empty($asset->assigned_to)) {
                            throw new \Exception("Aset {$asset->asset_number} masih dipegang staf. Lakukan Pengembalian (BAPA) terlebih dahulu sebelum Disposal.");
                        }

                        $asset->update([
                            'assigned_to'   => $isDisposed ? null : $asset->assigned_to,
                            'department_id' => $isDisposed ? null : $asset->department_id,
                            'status_id'     => $newStatus->id,
                            'warehouse_id'  => $isDisposed ? null : $asset->warehouse_id,
                            'notes'         => $request->notes ?: $asset->notes,
                        ]);

                        \App\Models\FixedAssetHistory::create([
                            'fixed_asset_id' => $asset->id,
                            'status'         => $newStatus->name,
                            'assigned_to'    => $asset->assigned_to,
                            'notes'          => "[{$trxNumber}] Perubahan Status: {$newStatus->name} | Alasan: " . ($request->notes ?: '-'),
                            'created_by'     => auth()->id(),
                        ]);

                        if ($isDisposed && $prevWarehouseId &&$asset->item_id) {
                            $item = \App\Models\Item::lockForUpdate()->find($asset->item_id);
                            if ($item) {
                                $before = (float)$item->current_stock;
                                $item->update(['current_stock' => max(0,$before - 1)]);
                                \App\Models\StockMutation::create([
                                    'item_id' => $item->id, 'warehouse_id' =>$prevWarehouseId,
                                    'type' => 'OUT', 'qty' => 1, 'balance_before' => $before, 'balance_after' =>$item->current_stock,
                                    'reference_number' => $trxNumber, 'notes' => "Penghapusan / Disposal Aset ({$asset->asset_number})",
                                    'created_by' => auth()->id(),
                                ]);
                            }
                        }
                    }
                }
            });

            return redirect()->route('fixed-assets.transactions.show', $trxCreated->id)
                ->with('success', "Transaksi {$trxCreated->transaction_number} berhasil diproses!");

        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal memproses transaksi: ' . $e->getMessage());
        }
    }


    public function printTransactionDocument(Request $request, $id)
    {
        $transaction = \App\Models\FixedAssetTransaction::with([
            'details.asset.item',
            'details.asset.company',
            'details.asset.currency',
            'signers.user.department',
            'fromUser.department',
            'toUser.department',
            'warehouse',
            'status'
        ])->findOrFail($id);

        $firstDetail = $transaction->details->first();
        $asset = $firstDetail ? $firstDetail->asset : FixedAsset::find($transaction->fixed_asset_id);

        if (!$asset) {
            return back()->with('error', 'Data aset pada transaksi ini tidak ditemukan.');
        }

        $isDisposedDoc = optional($transaction->status)->slug === 'disposed' || str_starts_with($transaction->transaction_number, 'BAPP/');
        $defaultP1 = $transaction->fromUser ?? auth()->user();
        $defaultP2 = $transaction->toUser ?? auth()->user();

        // 1. Jika ada input penanda tangan dari modal cetak, susun urutan peran secara konsisten lalu simpan ke DB
        if ($request->has('signers')) {
            $rawIds = array_values(array_unique(array_filter((array) $request->input('signers', []))));
            $signatureMode = $request->input('signature_mode', 'digital');

            if ($isDisposedDoc || $transaction->transaction_type === 'REPAIR_SEND') {
                // Untuk BAPP (Pengaju, Pemeriksa, Menyetujui) & BASP (Pihak 1, Saksi...), urutan form sudah sesuai peran
                $orderedIds = array_map('intval', $rawIds);
            } else {
                $orderedIds = [];
                $remainingIds = collect($rawIds)->map(fn($uid) => (int) $uid);

                // Kunci Pihak Pertama (Yang Menyerahkan) di urutan #1 jika termasuk dalam pilihan
                if ($defaultP1 && $remainingIds->contains((int) $defaultP1->id)) {
                    $orderedIds[] = (int) $defaultP1->id;
                    $remainingIds = $remainingIds->reject(fn($uid) => $uid === (int) $defaultP1->id);
                }

                // Kunci Pihak Kedua (Yang Menerima) di urutan #2 jika termasuk dalam pilihan
                if ($defaultP2 && $remainingIds->contains((int) $defaultP2->id)) {
                    $orderedIds[] = (int) $defaultP2->id;
                    $remainingIds = $remainingIds->reject(fn($uid) => $uid === (int) $defaultP2->id);
                }

                // Sisanya otomatis masuk sebagai Saksi / Mengetahui di urutan #3 dst
                foreach ($remainingIds as $extraId) {
                    $orderedIds[] = $extraId;
                }
            }

            // Hapus daftar lama & simpan ulang sesuai urutan terbaru ke tabel fixed_asset_transaction_signers
            $transaction->signers()->delete();

            foreach ($orderedIds as $idx => $uid) {
                $roleLabel = match (true) {
                    $isDisposedDoc && $idx === 0 => 'YANG MENGAJUKAN',
                    $isDisposedDoc && $idx === 1 => 'DIPERIKSA (FINANCE / GA)',
                    $isDisposedDoc && $idx === 2 => 'MENYETUJUI',
                    $transaction->transaction_type === 'REPAIR_SEND' && $idx === 0 => 'PIHAK PERTAMA',
                    !$isDisposedDoc && $idx === 0 => 'PIHAK PERTAMA',
                    !$isDisposedDoc && $idx === 1 => 'PIHAK KEDUA',
                    default => 'SAKSI / MENGETAHUI',
                };

                \App\Models\FixedAssetTransactionSigner::create([
                    'fixed_asset_transaction_id' => $transaction->id,
                    'user_id'                    => $uid,
                    'role_label'                 => $roleLabel,
                    'sequence'                   => $idx + 1,
                    'signature_mode'             => $signatureMode,
                ]);
            }

            $transaction->load('signers.user.department');
        }

        // 2. Ambil daftar user penanda tangan dari tabel relasi (jika sudah tersimpan) atau fallback ke default
        if ($transaction->signers->count() > 0) {
            $signers = $transaction->signers->map(fn($s) => $s->user)->filter();
            $signatureMode = $request->input('signature_mode', $transaction->signers->first()->signature_mode ?? 'digital');
        } else {
            $signatureMode = $request->input('signature_mode', 'digital');
            $signers = ($isDisposedDoc || $transaction->transaction_type === 'REPAIR_SEND')
                ? collect([$defaultP1])->filter()
                : collect([$defaultP1, $defaultP2])->filter();
        }

        $documentNumber = $transaction->transaction_number;

        // 1. Dokumen Pengembalian (BAPA) & Selesai Perbaikan Masuk Gudang (BAPA-SRV)
        if (in_array($transaction->transaction_type, ['RETURN', 'REPAIR_FINISH'])) {
            $lastAssignee = $transaction->fromUser;
            $pdf = Pdf::loadView('fixed_assets.bapa', compact('asset', 'lastAssignee', 'signers', 'documentNumber', 'transaction', 'signatureMode'))
                ->setOptions(['isPhpEnabled' => true, 'isRemoteEnabled' => true])
                ->setPaper('A4', 'portrait');
            return $pdf->stream(str_replace('/', '_', $documentNumber) . '.pdf');
        }

        // 2. Dokumen Serah Terima Perbaikan ke Service Center (BASP)
        if ($transaction->transaction_type === 'REPAIR_SEND') {
            $pdf = Pdf::loadView('fixed_assets.basp', compact('asset', 'signers', 'documentNumber', 'transaction', 'signatureMode'))
                ->setOptions(['isPhpEnabled' => true, 'isRemoteEnabled' => true])
                ->setPaper('A4', 'portrait');
            return $pdf->stream('BASP_' . str_replace('/', '_', $documentNumber) . '.pdf');
        }

        // 3. Dokumen Penghapusan / Pemusnahan Aset (BAPP)
        if ($isDisposedDoc) {
            $pdf = Pdf::loadView('fixed_assets.bapp', compact('asset', 'signers', 'documentNumber', 'transaction', 'signatureMode'))
                ->setOptions(['isPhpEnabled' => true, 'isRemoteEnabled' => true])
                ->setPaper('A4', 'portrait');
            return $pdf->stream('BAPP_' . str_replace('/', '_', $documentNumber) . '.pdf');
        }

        // 4. Dokumen Serah Terima ke Karyawan (BAST)
        $pdf = Pdf::loadView('fixed_assets.bast', compact('asset', 'signers', 'documentNumber', 'transaction', 'signatureMode'))
            ->setOptions(['isPhpEnabled' => true, 'isRemoteEnabled' => true])
            ->setPaper('A4', 'portrait');
        return $pdf->stream('BAST_' . str_replace('/', '_', $documentNumber) . '.pdf');
    }





    public function showTransaction($id)
    {
        $transaction = \App\Models\FixedAssetTransaction::with([
            'details.asset.item.category',
            'details.asset.assetCategory',
            'details.asset.company',
            'details.asset.status',
            'details.asset.warehouse',
            'details.asset.assignee.department',
            'details.asset.currency',
            'details.asset.photos',
            'signers.user.department',
            'fromUser.department',
            'fromUser.company',
            'toUser.department',
            'toUser.company',
            'warehouse',
            'status',
            'creator'
        ])->findOrFail($id);

        $users = User::with('department')->orderBy('name')->get();

        return view('fixed_assets.transaction_show', compact('transaction', 'users'));
    }



}
