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
        $search = $request->input('search');
        $warehouseId = $request->input('warehouse_id');

        $assets = FixedAsset::with([
                'item', 'company', 'status', 'assignee.company', 'warehouse', 'goodsReceipt','assetCategory',
                'histories' => function($q) { $q->orderBy('created_at', 'desc'); },
                'histories.assignee', 'histories.creator'
            ])
            // =========================================================================
            // 🔥 BLOK FILTER VOID (Menggunakan SLUG yang dinamis & bersih)
            // =========================================================================
            ->where('notes', 'not like', '%[DIBATALKAN%')
            ->whereHas('status', function($q) {
                $q->whereNotIn('slug', ['void', 'batal', 'canceled', 'cancelled']);
            })
            // =========================================================================
            ->when($warehouseId, function ($query) use ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q1) use ($search) {
                    $q1->where('asset_number', 'like', "%{$search}%")
                       ->orWhere('serial_number', 'like', "%{$search}%")
                       ->orWhere('accounting_asset_number', 'like', "%{$search}%")
                       ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->latest()->paginate(10)->withQueryString();

        $users = User::with('company')->orderBy('name', 'asc')->get();
        $items = \App\Models\Item::where('item_type_code', 'AST')->orderBy('name', 'asc')->get();
        $companies = \App\Models\Company::orderBy('name', 'asc')->get();
        $statuses = Status::where('type', 'AST')->orderBy('sequence', 'asc')->get();
        $warehouses = \App\Models\Warehouse::orderBy('name', 'asc')->get();
        $currencies = \App\Models\Currency::where('is_active', 1)->get();
        $assetCategories = \App\Models\AssetCategory::where('is_active', true)->orderBy('useful_life_years', 'asc')->get();

        return view('fixed_assets.index', compact('assets', 'search', 'users', 'items', 'companies', 'statuses', 'warehouses', 'currencies', 'assetCategories'));
    }


    // =========================================================================
    // 🔥 PROSES SIMPAN ASET MANUAL (CLEAN CODE & SMART MUTATION) 🔥
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
            'accounting_asset_number' => 'nullable|string|max:255',
            'spesifikasi_detail'      => 'required|string',
            'notes'                   => 'nullable|string',
            'acquisition_date'        => 'required|date',
            'purchase_price'          => 'nullable|numeric|min:0',
            'currency_id'             => 'required|exists:currencies,id',
            'supporting_document'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'photos.*'                => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $selectedStatus = \App\Models\Status::find($request->status_id);
        $statusSlug = optional($selectedStatus)->slug; // Menggunakan slug murni

        // BLOKIR JIKA STATUS "IN USE" TAPI TIDAK ADA KARYAWAN
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

                $nomorPenerimaanHibah = 'HIBAH-' . date('Ymd-His');
                $documentPath = null;

                if ($request->hasFile('supporting_document')) {
                    $file = $request->file('supporting_document');
                    $filename = time() . '_' . uniqid() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
                    $folderPath = 'input_asset_manual_hibah/' . $nomorPenerimaanHibah;
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
                        'notes'                   => $request->notes ?? 'Aset didaftarkan secara manual (Hibah)',
                        'acquisition_date'        => $request->acquisition_date,
                        'purchase_price'          => $request->purchase_price ?? 0,
                        'currency_id'             => $request->currency_id,
                        'batch_id'                => $nomorPenerimaanHibah,
                        'supporting_document'     => $documentPath,
                    ]);

                    $historyNote = 'Registrasi Manual/Hibah (Unit ke-' . $i . ' dari ' . $qty . '). ' . $request->notes;
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

                    // PENCATATAN MUTASI STOK (SMART IN & OUT)
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
                        'notes'            => "Penerimaan Hibah/Manual Aset",
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
        $asset = FixedAsset::with(['photos', 'item', 'company', 'status', 'warehouse', 'assetCategory'])->findOrFail($id);
        $warehouses = \App\Models\Warehouse::orderBy('name', 'asc')->get();
        $assetCategories = \App\Models\AssetCategory::where('is_active', true)->orderBy('useful_life_years', 'asc')->get();
        $companies = \App\Models\Company::orderBy('name', 'asc')->get();
        $statuses = Status::where('type', 'AST')->orderBy('sequence', 'asc')->get();
        $currencies = \App\Models\Currency::where('is_active', 1)->get();
        $users = User::with(['company', 'department'])->orderBy('name', 'asc')->get();

        return view('fixed_assets.edit', compact('asset', 'warehouses', 'assetCategories', 'companies', 'statuses', 'currencies', 'users'));
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
            'accounting_asset_number' => 'nullable|string|max:255',
            'spesifikasi_detail'      => 'nullable|string',
            'notes'                   => 'nullable|string',
            'acquisition_date'        => 'required|date',
            'purchase_price'          => 'nullable|numeric|min:0',
            'currency_id'             => 'required|exists:currencies,id',
            'photos.*'                => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $asset = \App\Models\FixedAsset::findOrFail($id);
        $selectedStatus = \App\Models\Status::find($request->status_id);
        $statusSlug = optional($selectedStatus)->slug;

        // BLOKIR JIKA STATUS "IN USE" TAPI TIDAK ADA KARYAWAN
        if ($statusSlug === 'in_use' && empty($request->assigned_to)) {
            return back()->withInput()->with('error', 'VALIDASI GAGAL: Anda mengubah status aset menjadi "In Use", maka Anda WAJIB memilih Karyawan pada kolom Penanggung Jawab!');
        }

        try {
            DB::transaction(function () use ($request, $asset, $selectedStatus, $statusSlug) {

                $oldStatusId = $asset->status_id;
                $oldAssignee = $asset->assigned_to;

                // Kosongkan nama user jika statusnya BUKAN in_use (Otomatis & Dinamis)
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

                // 🔥 CATAT KE HISTORY 🔥
                // 🔥 CATAT KE HISTORY 🔥
                if ($oldStatusId != $request->status_id || $oldAssignee != $assignedTo) {
                    $historyNote = 'Perubahan Data Aset menjadi: ' . optional($selectedStatus)->name . '.';

                    if ($assignedTo) {
                        $user = \App\Models\User::find($assignedTo);
                        $historyNote .= ' [Diserahkan ke: ' . optional($user)->name . ']';
                    } elseif ($oldAssignee != null && $assignedTo == null) {
                        $historyNote .= ' [Dikembalikan ke Gudang]';
                    }

                    // 🔥 TANGKAP ALASAN RESMI DISPOSAL / MAINTENANCE 🔥
                    if ($request->filled('status_change_reason')) {
                        $historyNote .= ' | Alasan Resmi: ' . trim($request->status_change_reason);
                    } elseif ($request->filled('notes') && $oldStatusId == $request->status_id) {
                        // Jika hanya update catatan biasa
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

                // =========================================================================
                // 🔥 MESIN SMART MUTASI STOK KELAS ENTERPRISE MENGGUNAKAN SLUG 🔥
                // =========================================================================
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

                        // KASUS A: BARANG KELUAR DARI GUDANG (-1 OUT)
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
                        }
                        // KASUS B: BARANG MASUK KEMBALI KE GUDANG (+1 IN)
                        elseif (!$wasInWarehouse && $isNowInWarehouse) {
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

                        // KUNCI STATUS SERIAL NUMBER (S/N)
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

                // PROSES UPLOAD FOTO TAMBAHAN
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

            // PIHAK 1: PENYERAH (Pasti Admin / User Login dikunci di sini)
            $pihak1 = $selectedUsers->where('id', $adminId)->first();
            if (!$pihak1) $pihak1 = $selectedUsers->first(); // Fallback jika admin tidak dipilih

            if ($pihak1) {
                $signers->push($pihak1);
                $selectedUsers = $selectedUsers->reject(function($u) use ($pihak1) { return $u->id == $pihak1->id; });
            }

            // PIHAK 2: PENERIMA (Pasti Karyawan)
            $pihak2 = $selectedUsers->where('id', $assigneeId)->first();
            if (!$pihak2) $pihak2 = $selectedUsers->first(); // Fallback

            if ($pihak2) {
                $signers->push($pihak2);
                $selectedUsers = $selectedUsers->reject(function($u) use ($pihak2) { return $u->id == $pihak2->id; });
            }

            // SISANYA: Menjadi Saksi
            foreach ($selectedUsers as $u) {
                $signers->push($u);
            }
        }

        $pdf = Pdf::loadView('fixed_assets.bast', compact('asset', 'signers'))->setPaper('A4', 'portrait');
        return $pdf->stream('BAST_Aset_' . str_replace('/', '_', $asset->asset_number) . '.pdf');
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

            // PIHAK 1: PENGEMBALI (Pasti Karyawan)
            $pihak1 = $selectedUsers->where('id', $assigneeId)->first();
            if (!$pihak1) $pihak1 = $selectedUsers->where('id', '!=', $adminId)->first(); // Cari siapa saja asal bukan admin
            if (!$pihak1) $pihak1 = $selectedUsers->first(); // Fallback terakhir

            if ($pihak1) {
                $signers->push($pihak1);
                $selectedUsers = $selectedUsers->reject(function($u) use ($pihak1) { return $u->id == $pihak1->id; });
            }

            // PIHAK 2: PENERIMA GUDANG (Pasti Admin / User Login dikunci di sini)
            $pihak2 = $selectedUsers->where('id', $adminId)->first();
            if (!$pihak2) $pihak2 = $selectedUsers->first(); // Fallback jika admin tidak dipilih

            if ($pihak2) {
                $signers->push($pihak2);
                $selectedUsers = $selectedUsers->reject(function($u) use ($pihak2) { return $u->id == $pihak2->id; });
            }

            // SISANYA: Menjadi Saksi
            foreach ($selectedUsers as $u) {
                $signers->push($u);
            }
        }

        $pdf = Pdf::loadView('fixed_assets.bapa', compact('asset', 'lastAssignee', 'signers'))->setPaper('A4', 'portrait');
        return $pdf->stream('BAPA_Aset_' . str_replace('/', '_', $asset->asset_number) . '.pdf');
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
    // 1. PROSES BACA EXCEL & LEMPAR KE KARANTINA (STAGING)
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

            return redirect()->route('fixed-assets.import_staging', $batch->id)->with('success', 'Data berhasil masuk ke Ruang Karantina!');
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
            $batch = \App\Models\FixedAssetImportBatch::findOrFail($batch_id);

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
            $batch = \App\Models\FixedAssetImportBatch::findOrFail($batch_id);
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

        foreach ($details as $row) {
            $item = null;

            if (!empty($row->kode_barang)) {
                $item = \App\Models\Item::lockForUpdate()->where('code', $row->kode_barang)->first();
            }

            if (!$item) {
                $namaAsetBaru = $row->nama_spesifik_aset;
                $item = \App\Models\Item::lockForUpdate()->where('name', $namaAsetBaru)->first();

                if (!$item) {
                    $lastItem = \App\Models\Item::orderBy('id', 'desc')->lockForUpdate()->first();
                    $nextId   = $lastItem ? $lastItem->id + 1 : 1;
                    $newCode  = 'ITM-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                    $slug     = \Illuminate\Support\Str::slug($namaAsetBaru) . '-' . \Illuminate\Support\Str::random(4);

                    $typeName = !empty($row->tipe_aset) ? $row->tipe_aset : 'Umum';

                    $cat = \App\Models\Category::firstOrCreate(
                        ['name' => $typeName],
                        ['code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $typeName), 0, 3))]
                    );

                    $uom = \App\Models\Uom::firstOrCreate(['code' => 'PCS'], ['name' => 'Pieces']);

                    $item = \App\Models\Item::create([
                        'code'           => $newCode,
                        'slug'           => $slug,
                        'name'           => $namaAsetBaru,
                        'category_id'    => $cat->id,
                        'uom_id'         => $uom->id,
                        'item_type_code' => 'STK',
                        'is_trackable'   => 1,
                        'is_active'      => 1,
                        'current_stock'  => 0,
                        'specification'  => 'Item dibuat otomatis dari tipe: ' . $typeName
                    ]);
                }
            }

            $company = \App\Models\Company::where('name', 'like', "%{$row->nama_pt}%")->first();
            $warehouse = \App\Models\Warehouse::where('name', 'like', "%{$row->nama_gudang}%")->first();

            // Perbaikan pencarian status menggunakan Like masih diperlukan disini krn dari excel string bebas,
            // Namun fallback-nya menggunakan Slug jika bisa
            $status = \App\Models\Status::where('type', 'AST')->where('name', 'like', "%".explode('(', $row->status_aset)[0]."%")->first();

            $assetCategory = \App\Models\AssetCategory::where('name', 'like', "%{$row->kategori_aset}%")->first();
            $defaultCategory = \App\Models\AssetCategory::first();
            $assetCategoryId = $assetCategory ? $assetCategory->id : ($defaultCategory ? $defaultCategory->id : null);

            $departmentId = null;
            if (!empty($row->departemen)) {
                $deptCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $row->departemen), 0, 3));
                $dept = \App\Models\Department::firstOrCreate(
                    ['name' => $row->departemen],
                    ['code' => $deptCode]
                );
                $departmentId = $dept->id;
            }

            $assignedTo = null;
            $extraNotes = "";

            if (!empty($row->nama_peminjam)) {
                $user = \App\Models\User::where('name', 'like', "%{$row->nama_peminjam}%")->first();
                if ($user) {
                    $assignedTo = $user->id;
                    if (!$status || $status->slug !== 'in_use') {
                        $status = \App\Models\Status::where('type', 'AST')->where('slug', 'in_use')->first();
                    }
                } else {
                    $extraNotes .= "\n• User (Excel): " . $row->nama_peminjam;
                }
            }

            if (!empty($row->condition) && $row->condition !== '-') { $extraNotes .= "\n• Kondisi: " . $row->condition; }
            if (!empty($row->po_number) && $row->po_number !== '-') { $extraNotes .= "\n• PO Lama: " . $row->po_number; }
            if (!empty($row->supplier) && $row->supplier !== '-') { $extraNotes .= "\n• Vendor Asal: " . $row->supplier; }

            $currency = \App\Models\Currency::where('code', strtoupper($row->mata_uang))->first();

            $lastAsset = \App\Models\FixedAsset::where('asset_number', 'like', "AST/{$yearMonth}/%")->orderBy('id', 'desc')->lockForUpdate()->first();
            $nextSeq = $lastAsset ? ((int) substr($lastAsset->asset_number, -4)) + 1 : 1;
            $assetNumber = "AST/{$yearMonth}/" . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

            $rawDate = $row->tanggal_perolehan;
            $formattedDate = null;

            if (!empty($rawDate)) {
                if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $rawDate)) {
                    $formattedDate = $rawDate;
                } else {
                    $indonesianMonths = [
                        'Januari' => '01', 'Jan' => '01', 'Februari' => '02', 'Febuari' => '02', 'Feb' => '02',
                        'Maret' => '03', 'Mar' => '03', 'April' => '04', 'Apr' => '04', 'Mei' => '05', 'May' => '05',
                        'Juni' => '06', 'Jun' => '06', 'Juli' => '07', 'Jul' => '07', 'Agustus' => '08', 'Agu' => '08', 'Aug' => '08',
                        'September' => '09', 'Sep' => '09', 'Oktober' => '10', 'Okt' => '10', 'Oct' => '10',
                        'November' => '11', 'Nov' => '11', 'Desember' => '12', 'Des' => '12', 'Dec' => '12'
                    ];
                    $dateStr = str_ireplace(array_keys($indonesianMonths), array_values($indonesianMonths), $rawDate);
                    try {
                        $formattedDate = \Carbon\Carbon::parse($dateStr)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $formattedDate = date('Y-m-d');
                    }
                }
            } else {
                $formattedDate = date('Y-m-d');
            }

            $asset = \App\Models\FixedAsset::create([
                'asset_number'            => $assetNumber,
                'item_id'                 => $item->id,
                'asset_category_id'       => $assetCategoryId,
                'name'                    => $row->nama_spesifik_aset ?: $item->name,
                'warehouse_id'            => $warehouse ? $warehouse->id : 1,
                'company_id'              => $company ? $company->id : 1,
                'department_id'           => $departmentId,
                'serial_number'           => $row->serial_number,
                'accounting_asset_number' => $row->label_akuntansi,
                'status_id'               => $status ? $status->id : 1,
                'assigned_to'             => $assignedTo,
                'spesifikasi_detail'      => $row->spesifikasi,
                'notes'                   => trim($row->catatan . $extraNotes),
                'acquisition_date'        => $formattedDate,
                'purchase_price'          => $row->harga_beli ?: 0,
                'currency_id'             => $currency ? $currency->id : 1,
                'supporting_document'     => $batch->support_doc,
                'batch_id'                => $batch->batch_number,
            ]);

            \App\Models\FixedAssetHistory::create([
                'fixed_asset_id' => $asset->id,
                'status'         => $status ? $status->name : 'Unknown',
                'assigned_to'    => $assignedTo,
                'notes'          => trim('Di-import via Karantina (Batch: '.$batch->batch_number.')' . $extraNotes),
                'created_by'     => auth()->id()
            ]);

            $currStock = (float) $item->current_stock;
            $balanceAfter = $currStock + 1;

            \App\Models\StockMutation::create([
                'item_id' => $item->id,
                'warehouse_id' => $warehouse ? $warehouse->id : 1,
                'type' => 'IN',
                'qty' => 1,
                'balance_before' => $currStock,
                'balance_after' => $balanceAfter,
                'reference_number' => $assetNumber,
                'notes' => "Penerimaan Aset Import",
                'created_by' => auth()->id()
            ]);

            if ($status && $status->slug === 'in_use') {
                \App\Models\StockMutation::create([
                    'item_id' => $item->id,
                    'warehouse_id' => $warehouse ? $warehouse->id : 1,
                    'type' => 'OUT',
                    'qty' => 1,
                    'balance_before' => $balanceAfter,
                    'balance_after' => $balanceAfter - 1,
                    'reference_number' => 'GI-' . $assetNumber,
                    'notes' => "Aset langsung diserahkan ke pengguna (Import)",
                    'created_by' => auth()->id()
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

    public function printQrLabel($id)
    {
        $assets = \App\Models\FixedAsset::with(['item', 'company'])->where('id', $id)->get();
        return view('fixed_assets.print_qr', compact('assets'));
    }

    public function printMassQrLabel($batchId)
    {
        $assets = \App\Models\FixedAsset::with(['item', 'company'])->where('batch_id', $batchId)->get();

        if ($assets->isEmpty()) {
            return back()->with('error', 'Tidak ada aset pada Batch ini untuk dicetak.');
        }

        return view('fixed_assets.print_qr', compact('assets', 'batchId'));
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
    // 🔥 MASTER LIST ASET (PENGGUNAAN SLUG MURNI) 🔥
    // =========================================================================
    public function masterList(\Illuminate\Http\Request $request)
    {
        $voidStatusIds = \App\Models\Status::where('type', 'AST')
            ->whereIn('slug', ['void', 'batal', 'canceled', 'cancelled'])
            ->pluck('id')->toArray();

        $disposedStatusIds = \App\Models\Status::where('type', 'AST')
            ->where('slug', 'disposed')
            ->pluck('id')->toArray();

        $query = \App\Models\FixedAsset::with([
            'item.category', 'assetCategory', 'assignee.department',
            'company', 'department', 'status', 'warehouse'
        ]);

        if (!empty($voidStatusIds)) {
            $query->whereNotIn('status_id', $voidStatusIds);
        }
        $query->where(function($q) {
            $q->whereNull('notes')
              ->orWhere('notes', 'not like', '%[DIBATALKAN%');
        });

        if ($request->filled('status')) {
            if ($request->status === 'in_use') {
                $query->whereNotNull('assigned_to');
            } elseif ($request->status === 'in_warehouse') {
                $query->whereNull('assigned_to');
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('asset_number', 'like', "%{$search}%")
                  ->orWhere('accounting_asset_number', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('spesifikasi_detail', 'like', "%{$search}%")
                  ->orWhereHas('assignee', function($userQ) use ($search) {
                      $userQ->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('item', function($itemQ) use ($search) {
                      $itemQ->where('code', 'like', "%{$search}%");
                  });
            });
        }

        $assets = $query->latest()->paginate(15)->withQueryString();

        $kpiQuery = \App\Models\FixedAsset::query();
        $excludeKpiIds = array_merge($voidStatusIds, $disposedStatusIds);

        if (!empty($excludeKpiIds)) {
            $kpiQuery->whereNotIn('status_id', $excludeKpiIds);
        }
        $kpiQuery->where(function($q) {
            $q->whereNull('notes')
              ->orWhere('notes', 'not like', '%[DIBATALKAN%');
        });

        $totalAssets = (clone $kpiQuery)->count();
        $inUse = (clone $kpiQuery)->whereNotNull('assigned_to')->count();
        $inWarehouse = (clone $kpiQuery)->whereNull('assigned_to')->count();
        $totalValue = (clone $kpiQuery)->sum('purchase_price');

        $totalCurrentValue = $assets->reject(function ($asset) use ($disposedStatusIds) {
            return in_array($asset->status_id, $disposedStatusIds);
        })->sum(function ($asset) {
            return $asset->net_book_value ?? $asset->purchase_price ?? 0;
        });

        return view('fixed_assets.list_asset', compact(
            'assets', 'totalAssets', 'inUse', 'inWarehouse', 'totalValue', 'totalCurrentValue', 'disposedStatusIds'
        ));
    }

    public function exportMasterList(\Illuminate\Http\Request $request)
    {
        $namaFile = 'Laporan_Master_Data_Aset_' . date('Y-m-d_H-i-s') . '.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\MasterAssetExport($request->all()), $namaFile);
    }

    // =========================================================================
    // 🔥 TRANSAKSI ASET (PENGGUNAAN SLUG MURNI) 🔥
    // =========================================================================
    public function transactions(\Illuminate\Http\Request $request)
    {
        $query = \App\Models\FixedAsset::with(['item.category', 'assignee.department', 'department', 'warehouse', 'status', 'company'])
                    ->where('notes', 'not like', '%[DIBATALKAN%')
                    ->whereHas('status', function($q) {
                        $q->whereNotIn('slug', ['void', 'batal', 'canceled', 'cancelled']);
                    });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('asset_number', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhereHas('assignee', function($userQ) use ($search) {
                      $userQ->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $assets = $query->latest()->paginate(15)->withQueryString();

        $warehouses  = \App\Models\Warehouse::orderBy('name')->get();
        $statuses    = \App\Models\Status::where('type', 'AST')->orderBy('sequence')->get();
        $users       = \App\Models\User::with(['department', 'company'])->orderBy('name')->get();
        $departments = \App\Models\Department::orderBy('name')->get();

        return view('fixed_assets.transactions', compact('assets', 'warehouses', 'statuses', 'users', 'departments'));
    }

    // =========================================================================
    // 🔥 2. MESIN PROSES PENGEMBALIAN ASET (RETURN) DINAMIS 🔥
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
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $id) {
                $asset = \App\Models\FixedAsset::findOrFail($id);
                $previousUserId = $asset->assigned_to;

                if (empty($previousUserId)) {
                    throw new \Exception('Aset ini sudah berada di gudang dan tidak sedang dipegang oleh siapapun.');
                }

                // 🔥 SAKTI: Ambil Nama Status dari Database & Format Catatan 🔥
                $newStatus = \App\Models\Status::find($request->status_id);
                $returnReason = $request->return_notes ? trim($request->return_notes) : 'Pengembalian rutin ke gudang.';

                \App\Models\FixedAssetHistory::create([
                    'fixed_asset_id'   => $asset->id,
                    'status'           => optional($newStatus)->name ?? 'Returned',
                    'assigned_to'      => null,
                    'notes'            => 'Dikembalikan ke gudang (ID: ' . $request->warehouse_id . ') oleh User ID: ' . $previousUserId . ' | Alasan Pengembalian: ' . $returnReason,
                    'created_by'       => auth()->id(),
                ]);

                $asset->assigned_to   = null;
                $asset->department_id = null;
                $asset->warehouse_id  = $request->warehouse_id;
                $asset->status_id     = $request->status_id;

                // 🔥 PERBAIKAN: Timpa catatan penyerahan lama dengan catatan retur yang baru 🔥
                $asset->notes         = $returnReason;

                $asset->save();

                $item = \App\Models\Item::find($asset->item_id);
                if ($item) {
                    $balanceBefore = $item->current_stock;
                    $item->current_stock += 1;
                    $item->save();

                    \App\Models\StockMutation::create([
                        'item_id'          => $item->id,
                        'warehouse_id'     => $request->warehouse_id,
                        'type'             => 'IN',
                        'qty'              => 1,
                        'balance_before'   => $balanceBefore,
                        'balance_after'    => $item->current_stock,
                        'reference_number' => 'RET/' . date('Y/m/d') . '/' . $asset->asset_number,
                        'notes'            => 'Pengembalian Aset (' . $asset->asset_number . ') dari User ID: ' . $previousUserId,
                        'created_by'       => auth()->id(),
                    ]);
                }
            });

            return redirect()->back()->with('success', 'Aset berhasil dikembalikan ke Gudang dan Stok Master Item telah bertambah +1.');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal mengembalikan aset: ' . $e->getMessage());
        }
    }



    // =========================================================================
    // 🔥 MESIN PROSES PENYERAHAN / UPDATE STATUS ASET (DINAMIS) 🔥
    // =========================================================================
    public function handoverAsset(\Illuminate\Http\Request $request, $id)
    {
        // 1. Ubah validasi assigned_to menjadi NULLABLE
        $request->validate([
            'assigned_to'   => 'nullable|exists:users,id',
            'status_id'     => 'required|exists:statuses,id',
            'handover_date' => 'required|date',
            'handover_notes'=> 'nullable|string'
        ]);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $id) {
                $asset = \App\Models\FixedAsset::findOrFail($id);
                $previousWarehouseId = $asset->warehouse_id;

                if (!empty($asset->assigned_to)) {
                    throw new \Exception('Aset ini sedang dipakai dan belum dikembalikan ke gudang.');
                }

                $newStatus = \App\Models\Status::find($request->status_id);

                // 2. Proteksi Ganda: Jika status In Use, User HARUS ada!
                if (optional($newStatus)->slug === 'in_use' && empty($request->assigned_to)) {
                    throw new \Exception('Staf penerima WAJIB diisi jika status diubah menjadi Dipakai (In Use).');
                }

                $userPenerima = $request->assigned_to ? \App\Models\User::find($request->assigned_to) : null;

                // 3. Catat di History
                $historyNotes = '';
                if ($userPenerima) {
                    $historyNotes = 'Diserahkan ke User: ' . $userPenerima->name . ' | Catatan: ' . $request->handover_notes;
                } else {
                    $historyNotes = 'Update Status via Transaksi | Alasan Resmi: ' . $request->handover_notes;
                }

                \App\Models\FixedAssetHistory::create([
                    'fixed_asset_id'   => $asset->id,
                    'status'           => optional($newStatus)->name ?? 'Handover / Update',
                    'assigned_to'      => $request->assigned_to,
                    'notes'            => $historyNotes,
                    'created_by'       => auth()->id(),
                ]);

                // 4. Update Aset
                $asset->assigned_to   = $request->assigned_to;
                $asset->department_id = $userPenerima ? $userPenerima->department_id : null;
                $asset->status_id     = $request->status_id;

                // Jika aset diserahkan ke user ATAU dibuang (disposed), maka dia hilang dari Gudang
                if ($userPenerima || in_array(optional($newStatus)->slug, ['disposed', 'void'])) {
                    $asset->warehouse_id = null;
                }

                // Catat alasan di tabel utama juga
                if ($request->filled('handover_notes')) {
                    $asset->notes = trim($request->handover_notes);
                }

                $asset->save();

                // 5. Mutasi Stok (Hanya potong stok jika barang benar-benar KELUAR dari gudang)
                if ($asset->warehouse_id === null && $previousWarehouseId) {
                    $item = \App\Models\Item::find($asset->item_id);
                    if ($item) {
                        $balanceBefore = $item->current_stock;
                        $item->current_stock -= 1;
                        $item->save();

                        \App\Models\StockMutation::create([
                            'item_id'          => $item->id,
                            'warehouse_id'     => $previousWarehouseId,
                            'type'             => 'OUT',
                            'qty'              => 1,
                            'balance_before'   => $balanceBefore,
                            'balance_after'    => $item->current_stock,
                            'reference_number' => 'GI-AST/' . date('Y/m/d') . '/' . $asset->asset_number,
                            'notes'            => 'Pengeluaran Aset (' . $asset->asset_number . ') ' . ($userPenerima ? 'ke User: ' . $userPenerima->name : 'Status: ' . optional($newStatus)->name),
                            'created_by'       => auth()->id(),
                        ]);
                    }
                }
            });

            return redirect()->back()->with('success', 'Transaksi / Update status aset berhasil diproses secara otomatis.');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal memproses transaksi: ' . $e->getMessage());
        }
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

        return view('fixed_assets.create_manual', compact('warehouses', 'assetCategories', 'companies', 'statuses', 'currencies', 'users'));
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

                // Cek history pakai nama yang tersimpan sebelumnya
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
}
