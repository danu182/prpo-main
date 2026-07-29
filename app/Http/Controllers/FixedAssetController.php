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
                'item', 'company', 'status', 'assignee.company', 'warehouse', 'goodsReceipt','assetCategory', // 🔥 TAMBAHKAN INI
                'histories' => function($q) { $q->orderBy('created_at', 'desc'); },
                'histories.assignee', 'histories.creator'
            ])
            // =========================================================================
            // 🔥 BLOK FILTER VOID: Usir aset yang sudah dibatalkan dari daftar ini! 🔥
            // =========================================================================
            ->where('notes', 'not like', '%[DIBATALKAN%')
            ->whereHas('status', function($q) {
                $q->where('slug', 'not like', '%void%')
                  ->where('slug', 'not like', '%batal%');
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

        // Tambahkan baris ini di atas return view(...)
        $assetCategories = \App\Models\AssetCategory::where('is_active', true)->orderBy('useful_life_years', 'asc')->get();

        // Tambahkan variabelnya ke dalam compact()
        return view('fixed_assets.index', compact('assets', 'search', 'users', 'items', 'companies', 'statuses', 'warehouses', 'currencies', 'assetCategories'));
    }

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
            // 🔥 TAMBAHAN: Validasi foto aset fisik 🔥
            'photos.*'                => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
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

                    \App\Models\FixedAssetHistory::create([
                        'fixed_asset_id' => $asset->id,
                        'status'         => optional($selectedStatus)->name ?? 'Unknown',
                        'assigned_to'    => $assignedTo,
                        'notes'          => 'Registrasi Manual/Hibah (Unit ke-' . $i . ' dari ' . $qty . '). ' . $request->notes,
                        'created_by'     => auth()->id(),
                    ]);

                    // 🔥 PROSES UPLOAD FOTO FISIK ASET 🔥
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

                    // PENCATATAN MUTASI STOK
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

                $itemMaster->update(['current_stock' => $currentStock]);
            });

            return redirect()->route('fixed-assets.index')->with('success', $request->quantity . ' Unit Aset (Hibah) berhasil didaftarkan beserta foto fisiknya!');
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
        $users = User::with('company')->orderBy('name', 'asc')->get();

        return view('fixed_assets.edit', compact('asset', 'warehouses', 'assetCategories', 'companies', 'statuses', 'currencies', 'users'));
    }


    public function update(Request $request, $id)
    {
        // 1. Validasi semua data yang datang dari Modal Edit
        $request->validate([
            'serial_number'           => 'nullable|string|max:255',
            'status_id'               => 'required|exists:statuses,id',
            'accounting_asset_number' => 'nullable|string|max:255',
            'spesifikasi_detail'      => 'nullable|string',
            'assigned_to'             => 'nullable|exists:users,id',
            'notes'                   => 'nullable|string',
            'purchase_price'          => 'nullable|numeric|min:0',

            // 🔥 TAMBAHAN KOLOM BARU AGAR LOLOS VALIDASI 🔥
            'asset_category_id'       => 'required|exists:asset_categories,id',
            'acquisition_date'        => 'required|date',
            'currency_id'             => 'required|exists:currencies,id',
            'company_id'              => 'required|exists:companies,id',
        ]);

        try {
            DB::transaction(function () use ($request, $id) {
                $asset = FixedAsset::with('status')->findOrFail($id);

                $oldStatusSlug = optional($asset->status)->slug;
                $oldAssignee = $asset->assigned_to;

                $newStatus = Status::find($request->status_id);
                $assignedTo = in_array($newStatus->slug, ['available', 'disposed', 'maintenance', 'returned']) ? null : $request->assigned_to;

                $isChanged = ($oldStatusSlug !== $newStatus->slug) || ($oldAssignee != $assignedTo);

                // 2. Eksekusi Update ke Database
                $asset->update([
                    'serial_number'           => $request->serial_number,
                    'accounting_asset_number' => $request->accounting_asset_number,
                    'spesifikasi_detail'      => $request->spesifikasi_detail,
                    'status_id'               => $request->status_id,
                    'assigned_to'             => $assignedTo,
                    'notes'                   => $request->notes,
                    'purchase_price'          => $request->purchase_price,

                    // 🔥 TAMBAHAN KOLOM BARU AGAR TERSIMPAN KE DATABASE 🔥
                    'asset_category_id'       => $request->asset_category_id,
                    'acquisition_date'        => $request->acquisition_date,
                    'currency_id'             => $request->currency_id,
                    'company_id'              => $request->company_id,
                ]);

                // 🔥 PROSES UPLOAD FOTO TAMBAHAN SAAT EDIT 🔥
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

                // 3. Catat Histori Perubahan Status / Assignee
                if ($isChanged) {
                    $systemNote = '';

                    // LOGIKA 1: PENYERAHAN ASET MANUAL
                    if ($oldStatusSlug === 'available' && $newStatus->slug === 'in_use') {
                        $user = User::find($assignedTo);
                        $systemNote = "Aset diserahkan kepada: " . ($user ? $user->name : 'Unknown') . ".";

                        // OTOMATIS CATAT MUTASI KELUAR & POTONG STOK
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

                        // OTOMATIS CATAT MUTASI MASUK & TAMBAH STOK
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

                        // OTOMATIS CATAT MUTASI KELUAR JIKA ASALNYA DARI GUDANG
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

            return redirect()->route('fixed-assets.index')->with('success', 'Informasi Aset berhasil diperbarui sepenuhnya!');
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


    // =========================================================================
    // 3. WORKFLOW: AJUKAN PERSETUJUAN
    // =========================================================================
    public function submitApproval($batch_id)
    {
        DB::beginTransaction();
        try {
            $batch = \App\Models\FixedAssetImportBatch::findOrFail($batch_id);

            // 1. Validasi Baris Error
            if ($batch->details->where('is_valid', 0)->count() > 0) {
                throw new \Exception("Ada baris data yang error. Harap perbaiki form Excel Anda dan upload ulang.");
            }

            // 2. Generate Workflow Menggunakan Service
            $needsApproval = \App\Services\ApprovalService::generateWorkflow($batch);

            if ($needsApproval) {
                $batch->update(['status' => 'waiting_approval']);
                DB::commit();
                return back()->with('success', 'Draft berhasil diajukan ke Atasan!');
            } else {
                // AUTO APPROVE: Langsung pindah ke Buku Aset & Generate Item
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
                DB::rollBack();
                return back()->with('error', 'Tidak menunggu persetujuan Anda.');
            }

            // JIKA REJECT
            if ($action === 'REJECT') {
                $currentApproval->update(['status' => 'REJECTED', 'approved_by' => auth()->id(), 'approved_at' => now()]);
                $batch->update(['status' => 'draft']);
                DB::commit();
                return back()->with('error', 'Pengajuan dikembalikan ke Draft.');
            }

            // JIKA APPROVE
            $currentApproval->update(['status' => 'APPROVED', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            $nextApproval = \App\Models\DocumentApproval::where('document_id', $batch->id)->where('document_type', get_class($batch))
                ->where('status', 'PENDING')->orderBy('step_order', 'asc')->first();

            if ($nextApproval) {
                DB::commit();
                return back()->with('success', 'Disetujui. Diteruskan ke atasan berikutnya.');
            } else {
                // ===========================================================
                // FINAL APPROVAL: PINDAH DATA KE BUKU ASET MENGGUNAKAN HELPER
                // ===========================================================
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

    // =========================================================================
    // HELPER METHOD: PINDAH DATA KE BUKU ASET & BUAT ITEM OTOMATIS
    // =========================================================================
    private function processAssetImport($batch)
    {
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

            // PENCARIAN KATEGORI ASET YANG AMAN
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

            // D. Bikin Nomor Aset
            $lastAsset = \App\Models\FixedAsset::where('asset_number', 'like', "AST/{$yearMonth}/%")->orderBy('id', 'desc')->lockForUpdate()->first();
            $nextSeq = $lastAsset ? ((int) substr($lastAsset->asset_number, -4)) + 1 : 1;
            $assetNumber = "AST/{$yearMonth}/" . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

            // FORMAT TANGGAL
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

            // E. Simpan ke Buku Besar Aset
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

            // F. Mutasi Stok
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
        // 🔥 JURUS ANTI-404: Cari berdasarkan ID urut ATAU berdasarkan Nomor Batch 🔥
        $batch = \App\Models\FixedAssetImportBatch::where('id', $batch_id)
                    ->orWhere('batch_number', $batch_id)
                    ->firstOrFail();

        // Bersihkan file PDF/Bukti
        if (!empty($batch->support_doc) && \Illuminate\Support\Facades\Storage::disk('public')->exists($batch->support_doc)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($batch->support_doc);
        }

        // Bersihkan file Excel sisa
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
    // 🔥 MASTER LIST ASET (FIX FILTER & MASTER ITEM & PENYUSUTAN) 🔥
    // =========================================================================
    public function masterList(\Illuminate\Http\Request $request)
    {
        $query = \App\Models\FixedAsset::with([
            'item.category',
            'assetCategory', // Pastikan ini terpanggil
            'assignee.department',
            'company',
            'department',
            'status',
            'warehouse'
        ]);

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

        // Ambil data untuk tabel dengan pagination
        $assets = $query->latest()->paginate(15)->withQueryString();

        // 🔥 LOGIKA KALKULATOR TOTAL UANG (Ambil semua data tanpa pagination untuk dihitung)
        // Gunakan get() agar accessor net_book_value bisa tereksekusi
        $allAssetsForCalculation = \App\Models\FixedAsset::with('assetCategory')->get();

        $totalAssets = $allAssetsForCalculation->count();
        $inUse = $allAssetsForCalculation->whereNotNull('assigned_to')->count();
        $inWarehouse = $allAssetsForCalculation->whereNull('assigned_to')->count();

        $totalValue = $allAssetsForCalculation->sum('purchase_price');
        $totalCurrentValue = $allAssetsForCalculation->sum('net_book_value'); // 🔥 Menjumlahkan Total Nilai Buku Saat Ini

        return view('fixed_assets.list_asset', compact(
            'assets', 'totalAssets', 'inUse', 'inWarehouse', 'totalValue', 'totalCurrentValue' // Kirim variabel baru ini ke View
        ));
    }



    // =========================================================================
    // 🔥 FUNGSI EXPORT MASTER LIST KE EXCEL 🔥
    // =========================================================================
    public function exportMasterList(\Illuminate\Http\Request $request)
    {
        $namaFile = 'Laporan_Master_Data_Aset_' . date('Y-m-d_H-i-s') . '.xlsx';

        // request()->all() dikirim ke Export Class agar Excel mengunduh data yang difilter saja
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\MasterAssetExport($request->all()), $namaFile);
    }




    // =========================================================================
    // 🔥 1. HALAMAN KHUSUS TRANSAKSI ASET 🔥
    // =========================================================================
    public function transactions(\Illuminate\Http\Request $request)
    {
        // Menampilkan aset yang sedang dipakai (untuk dikembalikan)
        // dan aset di gudang (untuk diserahkan ke staf).
        $query = \App\Models\FixedAsset::with(['item', 'assignee', 'department', 'warehouse', 'status', 'company'])
                    // =========================================================================
                    // 🔥 BLOK FILTER VOID: Usir aset yang sudah dibatalkan dari daftar ini! 🔥
                    // =========================================================================
                    ->where('notes', 'not like', '%[DIBATALKAN%')
                    ->whereHas('status', function($q) {
                        $q->where('slug', 'not like', '%void%')
                          ->where('slug', 'not like', '%batal%');
                    });
                    // =========================================================================

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

        // Tarik master data untuk dropdown di modal pengembalian & penyerahan
        $warehouses  = \App\Models\Warehouse::orderBy('name')->get();
        $statuses    = \App\Models\Status::where('type', 'AST')->orderBy('sequence')->get();
        $users       = \App\Models\User::with(['department', 'company'])->orderBy('name')->get();
        $departments = \App\Models\Department::orderBy('name')->get();

        return view('fixed_assets.transactions', compact('assets', 'warehouses', 'statuses', 'users', 'departments'));
    }

    // =========================================================================
    // 🔥 2. MESIN PROSES PENGEMBALIAN ASET (RETURN) 🔥
    // =========================================================================
    public function returnAsset(\Illuminate\Http\Request $request, $id)
    {
        // 1. Validasi input dari form modal pengembalian
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'status_id'    => 'required|exists:statuses,id',
            'return_date'  => 'required|date',
            'return_notes' => 'nullable|string'
        ]);

        try {
            // Gunakan DB Transaction agar data aman
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $id) {

                // Cari data asetnya
                $asset = \App\Models\FixedAsset::findOrFail($id);
                $previousUserId = $asset->assigned_to;

                if (empty($previousUserId)) {
                    throw new \Exception('Aset ini sudah berada di gudang dan tidak sedang dipegang oleh siapapun.');
                }

                // 2. CATAT HISTORI PENGEMBALIAN (Sesuai dengan kolom tabel Anda yang asli)
                \App\Models\FixedAssetHistory::create([
                    'fixed_asset_id'   => $asset->id,
                    'status'           => 'RETURNED',
                    'assigned_to'      => null,
                    'notes'            => 'Dikembalikan ke gudang (ID: ' . $request->warehouse_id . ') oleh User ID: ' . $previousUserId . '. Catatan: ' . $request->return_notes,
                    'created_by'       => auth()->id(),
                ]);

                // 3. UPDATE DATA ASET INDUK (Lepas ikatan dari User)
                $asset->assigned_to   = null;
                $asset->department_id = null;
                $asset->warehouse_id  = $request->warehouse_id;
                $asset->status_id     = $request->status_id;
                $asset->save();

                // 4. UPDATE KARTU STOK MASTER ITEM (+1)
                $item = \App\Models\Item::find($asset->item_id);
                if ($item) {
                    $balanceBefore = $item->current_stock; // Catat stok sebelum ditambah

                    $item->current_stock += 1;
                    $item->save();

                    // 🔥 5. CATAT KE KARTU MUTASI STOK (RETUR MASUK) 🔥
                    \App\Models\StockMutation::create([
                        'item_id'          => $item->id,
                        'warehouse_id'     => $request->warehouse_id,
                        'type'             => 'IN',  // Sesuai ENUM di database
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
    // 🔥 3. MESIN PROSES PENYERAHAN ASET (HANDOVER) 🔥
    // =========================================================================
    public function handoverAsset(\Illuminate\Http\Request $request, $id)
    {
        // 1. Hapus validasi department_id karena form-nya kita buang
        $request->validate([
            'assigned_to'   => 'required|exists:users,id',
            'status_id'     => 'required|exists:statuses,id',
            'handover_date' => 'required|date',
            'handover_notes'=> 'nullable|string'
        ]);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $id) {
                $asset = \App\Models\FixedAsset::findOrFail($id);
                $previousWarehouseId = $asset->warehouse_id;

                // 🔥 SAKTI: Ambil profil lengkap Staf Penerima dari database 🔥
                $userPenerima = \App\Models\User::findOrFail($request->assigned_to);

                if (!empty($asset->assigned_to)) {
                    throw new \Exception('Aset ini sedang dipakai dan belum dikembalikan ke gudang.');
                }

                // 1. CATAT HISTORI PENYERAHAN
                \App\Models\FixedAssetHistory::create([
                    'fixed_asset_id'   => $asset->id,
                    'status'           => 'HANDOVER',
                    'assigned_to'      => $request->assigned_to,
                    'notes'            => 'Diserahkan ke User ID: ' . $request->assigned_to . '. Catatan: ' . $request->handover_notes,
                    'created_by'       => auth()->id(),
                ]);

                // 2. UPDATE DATA ASET INDUK
                $asset->assigned_to   = $request->assigned_to;
                $asset->department_id = $userPenerima->department_id; // 🔥 OTOMATIS TERISI DARI PROFIL USER 🔥
                $asset->warehouse_id  = null;
                $asset->status_id     = $request->status_id;
                $asset->save();

                // 3. UPDATE KARTU STOK MASTER ITEM (-1 KELUAR)
                $item = \App\Models\Item::find($asset->item_id);
                if ($item) {
                    $balanceBefore = $item->current_stock; // Catat stok sebelum dikurangi

                    $item->current_stock -= 1;
                    $item->save();

                    // 🔥 4. CATAT MUTASI KELUAR (HANDOVER) 🔥
                    \App\Models\StockMutation::create([
                        'item_id'          => $item->id,
                        'warehouse_id'     => $previousWarehouseId,
                        'type'             => 'OUT', // Sesuai ENUM di database
                        'qty'              => 1,     // Tetap 1 karena tipe-nya sudah 'OUT'
                        'balance_before'   => $balanceBefore,
                        'balance_after'    => $item->current_stock,
                        'reference_number' => 'GI-AST/' . date('Y/m/d') . '/' . $asset->asset_number,
                        'notes'            => 'Penyerahan Aset (' . $asset->asset_number . ') ke User ID: ' . $request->assigned_to,
                        'created_by'       => auth()->id(),
                    ]);

                }
            });

            return redirect()->back()->with('success', 'Aset berhasil diserahkan ke pengguna dan Stok Mutasi Gudang (-1) telah tercatat.');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menyerahkan aset: ' . $e->getMessage());
        }
    }





    // =========================================================================
    // 🔥 4. HALAMAN RIWAYAT PERJALANAN ASET (LIFECYCLE) 🔥
    // =========================================================================
    public function history($id)
    {
        // Tarik data aset beserta relasinya
        $asset = \App\Models\FixedAsset::with(['item', 'company', 'warehouse', 'assignee'])->findOrFail($id);

        // Tarik histori dan urutkan dari yang PALING LAMA ke TERBARU untuk menghitung durasi
        $histories = \App\Models\FixedAssetHistory::where('fixed_asset_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        $processedHistories = collect();
        $lastHandoverDate = null;

        foreach ($histories as $history) {
            // Ambil nama Admin yang melakukan transaksi
            $adminName = \App\Models\User::where('id', $history->created_by)->value('name') ?? 'System';
            $history->admin_name = $adminName;

            // Logika Penghitungan Durasi
            if ($history->status === 'HANDOVER') {
                $lastHandoverDate = $history->created_at;
                $history->durasi = null;
            } elseif ($history->status === 'RETURNED') {
                if ($lastHandoverDate) {
                    // 🔥 PERBAIKAN: Gunakan startOfDay() agar perhitungannya murni selisih tanggal (bilangan bulat)
                    $startDate = \Carbon\Carbon::parse($lastHandoverDate)->startOfDay();
                    $endDate = \Carbon\Carbon::parse($history->created_at)->startOfDay();
                    $days = $startDate->diffInDays($endDate);

                    $history->durasi = ($days == 0 ? 1 : $days) . ' Hari Dipakai';
                    $lastHandoverDate = null; // Reset setelah dikembalikan
                }
            } else {
                $history->durasi = null;
            }

            $processedHistories->push($history);
        }

        // Hitung durasi pemakaian saat ini (jika aset sedang dipakai dan belum dikembalikan)
        if ($lastHandoverDate && $asset->assigned_to) {
            // 🔥 PERBAIKAN: Gunakan startOfDay() untuk perhitungan hari ini
            $startDate = \Carbon\Carbon::parse($lastHandoverDate)->startOfDay();
            $today = now()->startOfDay();
            $days = $startDate->diffInDays($today);

            $asset->current_usage_duration = ($days == 0 ? 1 : $days) . ' Hari';
        }

        // Balikkan urutannya menjadi TERBARU ke LAMA untuk ditampilkan di halaman
        $historiesDesc = $processedHistories->sortByDesc('created_at')->values();

        return view('fixed_assets.history', compact('asset', 'historiesDesc'));
    }



    // =========================================================================
    // HALAMAN FORM INPUT MANUAL (HIBAH)
    // =========================================================================
    public function createManual()
    {
        $warehouses = \App\Models\Warehouse::orderBy('name', 'asc')->get();
        $assetCategories = \App\Models\AssetCategory::where('is_active', true)->orderBy('useful_life_years', 'asc')->get();
        $companies = \App\Models\Company::orderBy('name', 'asc')->get();
        $statuses = \App\Models\Status::where('type', 'AST')->orderBy('sequence', 'asc')->get();
        $currencies = \App\Models\Currency::where('is_active', 1)->get();

        return view('fixed_assets.create_manual', compact('warehouses', 'assetCategories', 'companies', 'statuses', 'currencies'));
    }

}
