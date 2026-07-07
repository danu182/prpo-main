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
                'item', 'company', 'status', 'assignee.company', 'warehouse', 'goodsReceipt',
                'histories' => function($q) { $q->orderBy('created_at', 'desc'); },
                'histories.assignee', 'histories.creator'
            ])
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

        return view('fixed_assets.index', compact('assets', 'search', 'users', 'items', 'companies', 'statuses', 'warehouses', 'currencies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'item_id'                 => 'required|exists:items,id',
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
        ]);

        try {
            DB::transaction(function () use ($request) {
                $qty = $request->quantity;
                $itemMaster = \App\Models\Item::lockForUpdate()->findOrFail($request->item_id);
                $selectedStatus = \App\Models\Status::find($request->status_id);
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

                    $assignedTo = in_array(optional($selectedStatus)->slug, ['available', 'disposed', 'maintenance', 'returned']) ? null : $request->assigned_to;

                    $asset = \App\Models\FixedAsset::create([
                        'asset_number'            => $assetNumber,
                        'item_id'                 => $request->item_id,
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

                    \App\Models\FixedAssetHistory::create([
                        'fixed_asset_id' => $asset->id,
                        'status'         => optional($selectedStatus)->name ?? 'Unknown',
                        'assigned_to'    => $assignedTo,
                        'notes'          => 'Registrasi Manual/Hibah (Unit ke-' . $i . ' dari ' . $qty . '). ' . $request->notes,
                        'created_by'     => auth()->id(),
                    ]);

                    // PENCATATAN MUTASI STOK YANG BENAR (HANYA INI, TANPA INVENTORY_STOCKS)
                    $balanceBefore = $currentStock;
                    $balanceAfter  = $currentStock + 1;
                    $currentStock  = $balanceAfter;

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
                }

                // Update total stok terakhir di Master Barang
                $itemMaster->update(['current_stock' => $currentStock]);
            });

            return back()->with('success', $request->quantity . ' Unit Aset berhasil didaftarkan beserta Dokumen Pendukungnya!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mendaftarkan aset: ' . $e->getMessage());
        }
    }

    // public function update(Request $request, $id)
    // {
    //     $request->validate([
    //         'serial_number'           => 'nullable|string|max:255',
    //         'status_id'               => 'required|exists:statuses,id',
    //         'accounting_asset_number' => 'nullable|string|max:255',
    //         'spesifikasi_detail'      => 'nullable|string',
    //         'assigned_to'             => 'nullable|exists:users,id',
    //         'notes'                   => 'nullable|string',
    //         'purchase_price'          => 'nullable|numeric|min:0',
    //     ]);

    //     try {
    //         DB::transaction(function () use ($request, $id) {
    //             $asset = FixedAsset::with('status')->findOrFail($id);

    //             $oldStatusSlug = optional($asset->status)->slug;
    //             $oldAssignee = $asset->assigned_to;

    //             $newStatus = Status::find($request->status_id);
    //             $assignedTo = in_array($newStatus->slug, ['available', 'disposed', 'maintenance', 'returned']) ? null : $request->assigned_to;

    //             $isChanged = ($oldStatusSlug !== $newStatus->slug) || ($oldAssignee != $assignedTo);

    //             $asset->update([
    //                 'serial_number'           => $request->serial_number,
    //                 'accounting_asset_number' => $request->accounting_asset_number,
    //                 'spesifikasi_detail'      => $request->spesifikasi_detail,
    //                 'status_id'               => $request->status_id,
    //                 'assigned_to'             => $assignedTo,
    //                 'notes'                   => $request->notes,
    //                 'purchase_price'          => $request->purchase_price,
    //             ]);

    //             if ($isChanged) {
    //                 $systemNote = '';

    //                 if ($oldStatusSlug === 'available' && $newStatus->slug === 'in_use') {
    //                     $user = User::find($assignedTo);
    //                     $systemNote = "Aset diserahkan kepada: " . ($user ? $user->name : 'Unknown') . ".";
    //                 } elseif ($oldStatusSlug === 'in_use' && $newStatus->slug === 'available') {
    //                     $oldUser = User::find($oldAssignee);
    //                     $systemNote = "Aset dikembalikan ke Gudang/IT dari: " . ($oldUser ? $oldUser->name : 'Unknown') . ".";
    //                 } elseif ($oldStatusSlug === 'in_use' && $newStatus->slug === 'in_use' && $oldAssignee != $assignedTo) {
    //                     $oldUser = User::find($oldAssignee);
    //                     $newUser = User::find($assignedTo);
    //                     $systemNote = "Aset dipindahtangankan langsung dari " . ($oldUser ? $oldUser->name : 'Unknown') . " kepada " . ($newUser ? $newUser->name : 'Unknown') . ".";
    //                 } elseif ($newStatus->slug === 'maintenance') {
    //                     $systemNote = "Aset masuk status perbaikan/maintenance.";
    //                 } elseif ($newStatus->slug === 'disposed') {
    //                     $systemNote = "🔴 ASET DIHAPUSBUKUKAN (DISPOSED): Aset telah ditarik dari peredaran dan dihapus dari kekayaan aktif perusahaan.";
    //                 }

    //                 $finalNote = $systemNote;
    //                 if ($request->notes && $request->notes !== $asset->notes) {
    //                     $finalNote = $systemNote ? $systemNote . " | Catatan Baru: " . $request->notes : "Catatan: " . $request->notes;
    //                 }

    //                 \App\Models\FixedAssetHistory::create([
    //                     'fixed_asset_id' => $asset->id,
    //                     'status'         => $newStatus->name,
    //                     'assigned_to'    => $assignedTo,
    //                     'notes'          => $finalNote ?: 'Perubahan status / data aset.',
    //                     'created_by'     => auth()->id(),
    //                 ]);
    //             }
    //         });

    //         return back()->with('success', 'Informasi Aset berhasil diperbarui & Riwayat telah dicatat!');
    //     } catch (\Exception $e) {
    //         return back()->with('error', 'Gagal memperbarui aset: ' . $e->getMessage());
    //     }
    // }



    public function update(Request $request, $id)
    {
        $request->validate([
            'serial_number'           => 'nullable|string|max:255',
            'status_id'               => 'required|exists:statuses,id',
            'accounting_asset_number' => 'nullable|string|max:255',
            'spesifikasi_detail'      => 'nullable|string',
            'assigned_to'             => 'nullable|exists:users,id',
            'notes'                   => 'nullable|string',
            'purchase_price'          => 'nullable|numeric|min:0',
        ]);

        try {
            DB::transaction(function () use ($request, $id) {
                $asset = FixedAsset::with('status')->findOrFail($id);

                $oldStatusSlug = optional($asset->status)->slug;
                $oldAssignee = $asset->assigned_to;

                $newStatus = Status::find($request->status_id);
                $assignedTo = in_array($newStatus->slug, ['available', 'disposed', 'maintenance', 'returned']) ? null : $request->assigned_to;

                $isChanged = ($oldStatusSlug !== $newStatus->slug) || ($oldAssignee != $assignedTo);

                $asset->update([
                    'serial_number'           => $request->serial_number,
                    'accounting_asset_number' => $request->accounting_asset_number,
                    'spesifikasi_detail'      => $request->spesifikasi_detail,
                    'status_id'               => $request->status_id,
                    'assigned_to'             => $assignedTo,
                    'notes'                   => $request->notes,
                    'purchase_price'          => $request->purchase_price,
                ]);

                if ($isChanged) {
                    $systemNote = '';

                    // LOGIKA 1: PENYERAHAN ASET MANUAL
                    if ($oldStatusSlug === 'available' && $newStatus->slug === 'in_use') {
                        $user = User::find($assignedTo);
                        $systemNote = "Aset diserahkan kepada: " . ($user ? $user->name : 'Unknown') . ".";

                        // 🔥 OTOMATIS CATAT MUTASI KELUAR & POTONG STOK 🔥
                        $masterItem = \App\Models\Item::lockForUpdate()->find($asset->item_id);
                        if ($masterItem) {
                            $currStock = (float) $masterItem->current_stock;
                            $masterItem->update(['current_stock' => $currStock - 1]);
                            \App\Models\StockMutation::create([
                                'item_id' => $masterItem->id, 'warehouse_id' => $asset->warehouse_id,
                                'type' => 'OUT', 'qty' => 1, 'balance_before' => $currStock, 'balance_after' => $currStock - 1,
                                'reference_number' => 'GI-AST-' . $asset->asset_number,
                                'notes' => "Aset diserahkan langsung ke User via Master Aset", 'created_by' => auth()->id()
                            ]);
                        }
                    }

                    // LOGIKA 2: PENGEMBALIAN ASET DARI USER KE GUDANG PERUSAHAAN (RETUR)
                    elseif ($oldStatusSlug === 'in_use' && $newStatus->slug === 'available') {
                        $oldUser = User::find($oldAssignee);
                        $systemNote = "Aset dikembalikan ke Gudang/IT dari: " . ($oldUser ? $oldUser->name : 'Unknown') . ".";

                        // 🔥 OTOMATIS CATAT MUTASI MASUK & TAMBAH STOK 🔥
                        $masterItem = \App\Models\Item::lockForUpdate()->find($asset->item_id);
                        if ($masterItem) {
                            $currStock = (float) $masterItem->current_stock;
                            $masterItem->update(['current_stock' => $currStock + 1]);
                            \App\Models\StockMutation::create([
                                'item_id' => $masterItem->id, 'warehouse_id' => $asset->warehouse_id,
                                'type' => 'IN', 'qty' => 1, 'balance_before' => $currStock, 'balance_after' => $currStock + 1,
                                'reference_number' => 'RET-AST-' . $asset->asset_number,
                                'notes' => "Aset dikembalikan ke Gudang oleh User", 'created_by' => auth()->id()
                            ]);
                        }
                    }

                    // LOGIKA 3: PINDAH TANGAN ANTAR USER
                    elseif ($oldStatusSlug === 'in_use' && $newStatus->slug === 'in_use' && $oldAssignee != $assignedTo) {
                        $oldUser = User::find($oldAssignee);
                        $newUser = User::find($assignedTo);
                        $systemNote = "Aset dipindahtangankan langsung dari " . ($oldUser ? $oldUser->name : 'Unknown') . " kepada " . ($newUser ? $newUser->name : 'Unknown') . ".";
                        // Stok tetap, karena hanya pindah tangan user
                    }

                    // LOGIKA 4: RUSAK / MAINTENANCE
                    elseif ($newStatus->slug === 'maintenance') {
                        $systemNote = "Aset masuk status perbaikan/maintenance.";
                    }

                    // LOGIKA 5: DIHANCURKAN / DISPOSED
                    elseif ($newStatus->slug === 'disposed') {
                        $systemNote = "🔴 ASET DIHAPUSBUKUKAN (DISPOSED): Aset telah ditarik dari peredaran dan dihapus dari kekayaan aktif perusahaan.";

                        // 🔥 OTOMATIS CATAT MUTASI KELUAR JIKA ASALNYA DARI GUDANG 🔥
                        if (in_array($oldStatusSlug, ['available', 'maintenance', 'returned'])) {
                            $masterItem = \App\Models\Item::lockForUpdate()->find($asset->item_id);
                            if ($masterItem) {
                                $currStock = (float) $masterItem->current_stock;
                                $masterItem->update(['current_stock' => $currStock - 1]);
                                \App\Models\StockMutation::create([
                                    'item_id' => $masterItem->id, 'warehouse_id' => $asset->warehouse_id,
                                    'type' => 'OUT', 'qty' => 1, 'balance_before' => $currStock, 'balance_after' => $currStock - 1,
                                    'reference_number' => 'DISP-' . $asset->asset_number,
                                    'notes' => "[CAPITALIZE] Penghapusan Aset Tetap", 'created_by' => auth()->id()
                                ]);
                            }
                        }
                    }

                    $finalNote = $systemNote;
                    if ($request->notes && $request->notes !== $asset->notes) {
                        $finalNote = $systemNote ? $systemNote . " | Catatan Baru: " . $request->notes : "Catatan: " . $request->notes;
                    }

                    \App\Models\FixedAssetHistory::create([
                        'fixed_asset_id' => $asset->id,
                        'status'         => $newStatus->name,
                        'assigned_to'    => $assignedTo,
                        'notes'          => $finalNote ?: 'Perubahan status / data aset.',
                        'created_by'     => auth()->id(),
                    ]);
                }
            });

            return back()->with('success', 'Informasi Aset berhasil diperbarui & Mutasi Stok telah dicatat!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memperbarui aset: ' . $e->getMessage());
        }
    }


    public function printBast($id)
    {
        $asset = FixedAsset::with(['item', 'assignee.company', 'status'])->findOrFail($id);
        if (optional($asset->status)->slug !== 'in_use' || !$asset->assigned_to) {
            return back()->with('error', 'Aset ini sedang tidak diserahkan ke siapapun. BAST tidak dapat dicetak.');
        }
        $pdf = Pdf::loadView('fixed_assets.bast', compact('asset'))->setPaper('A4', 'portrait');
        return $pdf->stream('BAST_Aset_' . str_replace('/', '_', $asset->asset_number) . '.pdf');
    }

    public function printBapa($id)
    {
        $asset = FixedAsset::with(['item', 'status', 'histories' => function($query) {
            $query->orderBy('created_at', 'desc');
        }])->findOrFail($id);

        $lastUsageHistory = $asset->histories->whereNotNull('assigned_to')->first();
        if (!$lastUsageHistory || !$lastUsageHistory->assigned_to) {
            return back()->with('error', 'Belum ada riwayat peminjaman untuk aset ini. BAPA tidak dapat dicetak.');
        }

        $lastAssignee = User::with('company')->find($lastUsageHistory->assigned_to);
        $pdf = Pdf::loadView('fixed_assets.bapa', compact('asset', 'lastAssignee'))->setPaper('A4', 'portrait');
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

    // =========================================================================
    // 2. TAMPILKAN HALAMAN KARANTINA
    // =========================================================================
    public function importStaging($batch_id)
    {
        $batch = \App\Models\FixedAssetImportBatch::with('details')->findOrFail($batch_id);
        return view('fixed_assets.import_staging', compact('batch'));
    }

    // =========================================================================
    // 3. AJUKAN KE ATASAN (WORKFLOW)
    // =========================================================================
    public function submitApproval($batch_id)
    {
        DB::beginTransaction();
        try {
            $batch = \App\Models\FixedAssetImportBatch::findOrFail($batch_id);

            if ($batch->details->where('is_valid', 0)->count() > 0) {
                throw new \Exception("Ada baris data yang error. Harap perbaiki form Excel Anda dan upload ulang.");
            }

            $batch->update(['status' => 'waiting_approval']);
            \App\Models\DocumentApproval::where('document_id', $batch->id)->where('document_type', get_class($batch))->delete();

            $workflow = \DB::table('approval_workflows')->where('document_type', get_class($batch))->where('is_active', 1)->first();
            if (!$workflow) throw new \Exception("Workflow Persetujuan Aset belum diaktifkan di Pengaturan Sistem!");

            $steps = \DB::table('approval_workflow_steps')->where('approval_workflow_id', $workflow->id)->orderBy('step_order', 'asc')->get();
            foreach ($steps as $step) {
                \App\Models\DocumentApproval::create([
                    'document_id' => $batch->id, 'document_type' => get_class($batch),
                    'role_id' => $step->role_id, 'step_order' => $step->step_order, 'status' => 'PENDING'
                ]);
            }

            DB::commit();
            return back()->with('success', 'Draft berhasil diajukan ke Atasan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }


    // =========================================================================
    // 4. KEPUTUSAN ATASAN & 🔥 SMART AUTO-CREATE ITEM 🔥
    // =========================================================================
    public function decide(Request $request, $batch_id)
    {
        DB::beginTransaction();
        try {
            $batch = \App\Models\FixedAssetImportBatch::findOrFail($batch_id);
            $action = strtoupper($request->input('action', ''));
            $currentApproval = \App\Models\DocumentApproval::with('role')->where('document_id', $batch->id)
                ->where('document_type', get_class($batch))->where('status', 'PENDING')->orderBy('step_order', 'asc')->first();

            if (!$currentApproval && $action !== 'REJECT') {
                DB::rollBack(); return back()->with('error', 'Tidak menunggu persetujuan Anda.');
            }

            if ($action === 'REJECT') {
                $currentApproval->update(['status' => 'REJECTED', 'approved_by' => auth()->id(), 'approved_at' => now()]);
                $batch->update(['status' => 'draft']);
                DB::commit(); return back()->with('error', 'Pengajuan dikembalikan ke Draft.');
            }

            // JIKA APPROVE
            $currentApproval->update(['status' => 'APPROVED', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            $nextApproval = \App\Models\DocumentApproval::where('document_id', $batch->id)->where('document_type', get_class($batch))
                ->where('status', 'PENDING')->orderBy('step_order', 'asc')->first();

            if ($nextApproval) {
                DB::commit(); return back()->with('success', 'Disetujui. Diteruskan ke atasan berikutnya.');
            } else {
                // ===========================================================
                // FINAL APPROVAL: PINDAH DATA KE BUKU ASET & BUAT ITEM OTOMATIS
                // ===========================================================
                $yearMonth = date('Y/m');
                $details = \App\Models\FixedAssetImportDetail::where('batch_id', $batch->id)->get();

                foreach ($details as $row) {
                    $item = null;

                    // A. Cari by Kode
                    if (!empty($row->kode_barang)) {
                        $item = \App\Models\Item::lockForUpdate()->where('code', $row->kode_barang)->first();
                    }

                    // B. Jika Item Belum Ada, Eksekusi Smart Auto-Create Item
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

                    // C. Tarik Data Relasi
                    $company = \App\Models\Company::where('name', 'like', "%{$row->nama_pt}%")->first();
                    $warehouse = \App\Models\Warehouse::where('name', 'like', "%{$row->nama_gudang}%")->first();
                    $status = \App\Models\Status::where('type', 'AST')->where('name', 'like', "%".explode('(', $row->status_aset)[0]."%")->first();

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

                    // D. Bikin Nomor Aset
                    $lastAsset = \App\Models\FixedAsset::where('asset_number', 'like', "AST/{$yearMonth}/%")->orderBy('id', 'desc')->lockForUpdate()->first();
                    $nextSeq = $lastAsset ? ((int) substr($lastAsset->asset_number, -4)) + 1 : 1;
                    $assetNumber = "AST/{$yearMonth}/" . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

                    // 🔥 PERBAIKAN FORMAT TANGGAL: Translator Bulan Indonesia ke Format MySQL 🔥
                    $rawDate = $row->tanggal_perolehan;
                    $formattedDate = null;

                    if (!empty($rawDate)) {
                        // Jika format sudah YYYY-MM-DD
                        if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $rawDate)) {
                            $formattedDate = $rawDate;
                        }
                        // Jika formatnya teks campur sari Indonesia (Misal: 26 Febuari 2019)
                        else {
                            $indonesianMonths = [
                                'Januari' => '01', 'Jan' => '01', 'Februari' => '02', 'Febuari' => '02', 'Feb' => '02',
                                'Maret' => '03', 'Mar' => '03', 'April' => '04', 'Apr' => '04', 'Mei' => '05', 'May' => '05',
                                'Juni' => '06', 'Jun' => '06', 'Juli' => '07', 'Jul' => '07', 'Agustus' => '08', 'Agu' => '08', 'Aug' => '08',
                                'September' => '09', 'Sep' => '09', 'Oktober' => '10', 'Okt' => '10', 'Oct' => '10',
                                'November' => '11', 'Nov' => '11', 'Desember' => '12', 'Des' => '12', 'Dec' => '12'
                            ];

                            $dateStr = str_ireplace(array_keys($indonesianMonths), array_values($indonesianMonths), $rawDate);
                            try {
                                // Coba parsing string yang sudah ditranslate
                                $formattedDate = \Carbon\Carbon::parse($dateStr)->format('Y-m-d');
                            } catch (\Exception $e) {
                                // Jika gagal parah, gunakan hari ini
                                $formattedDate = date('Y-m-d');
                            }
                        }
                    } else {
                        // Jika di Excel kosong
                        $formattedDate = date('Y-m-d');
                    }

                    // E. Simpan ke Buku Besar Aset
                    $asset = \App\Models\FixedAsset::create([
                        'asset_number'            => $assetNumber,
                        'item_id'                 => $item->id,
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
                        'acquisition_date'        => $formattedDate, // <-- Tanggal yang sudah aman
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

                    // F. Mutasi Stok yang Benar (Tanpa InventoryStock)
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

                $batch->update(['status' => 'approved']);
                DB::commit();
                return redirect()->route('fixed-assets.index')->with('success', 'Buku Aset Resmi disahkan! Item baru telah digenerate otomatis.');
            }
        } catch (\Exception $e) {
            DB::rollBack(); return back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }



    public function cancelImport($batch_id)
    {
        $batch = \App\Models\FixedAssetImportBatch::findOrFail($batch_id);

        // 🔥 PERBAIKAN: Pastikan support_doc tidak kosong/null sebelum dicek keberadaannya
        if (!empty($batch->support_doc) && \Illuminate\Support\Facades\Storage::disk('public')->exists($batch->support_doc)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($batch->support_doc);
        }

        $batch->details()->delete();
        $batch->delete();

        return redirect()->route('fixed-assets.index')->with('success', 'Draft Import Aset dibatalkan.');
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
}
