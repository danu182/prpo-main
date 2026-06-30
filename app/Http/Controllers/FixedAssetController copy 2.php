<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FixedAsset;
use App\Models\User;
use App\Models\Status;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage; // 🔥 TAMBAHAN WAJIB UNTUK IMPORT
use App\Imports\FixedAssetImport;
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
        $items = \App\Models\Item::where('is_asset', true)->orderBy('name', 'asc')->get();
        $companies = \App\Models\Company::orderBy('name', 'asc')->get();
        $statuses = Status::where('type', 'AST')->orderBy('sequence', 'asc')->get();
        $warehouses = \App\Models\Warehouse::orderBy('name', 'asc')->get();

        // 🔥 AMBIL DATA MATA UANG DARI DATABASE 🔥
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
            // 🔥 VALIDASI MATA UANG & FILE 🔥
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

                // 🔥 GENERATE BATCH HIBAH & FOLDER DOKUMEN 🔥
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

                        // 🔥 SIMPAN HARGA, MATA UANG, BATCH, DAN DOKUMEN 🔥
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

                    \App\Models\InventoryStock::create([
                        'company_id'       => $request->company_id,
                        'warehouse_id'     => $request->warehouse_id,
                        'item_id'          => $request->item_id,
                        'stock_qty'        => 1,
                        'reference_number' => $assetNumber,
                        'notes'            => "Masuk via Registrasi Manual: " . $assetNumber,
                    ]);

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

            return back()->with('success', $request->quantity . ' Unit Aset berhasil didaftarkan beserta Dokumen Pendukungnya!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mendaftarkan aset: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'serial_number'           => 'nullable|string|max:255',
            'status_id'               => 'required|exists:statuses,id',
            'accounting_asset_number' => 'nullable|string|max:255',
            'spesifikasi_detail'      => 'nullable|string',
            'assigned_to'             => 'nullable|exists:users,id',
            'notes'                   => 'nullable|string'
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
                ]);

                if ($isChanged) {
                    $systemNote = '';

                    if ($oldStatusSlug === 'available' && $newStatus->slug === 'in_use') {
                        $user = User::find($assignedTo);
                        $systemNote = "Aset diserahkan kepada: " . ($user ? $user->name : 'Unknown') . ".";
                    } elseif ($oldStatusSlug === 'in_use' && $newStatus->slug === 'available') {
                        $oldUser = User::find($oldAssignee);
                        $systemNote = "Aset dikembalikan ke Gudang/IT dari: " . ($oldUser ? $oldUser->name : 'Unknown') . ".";
                    } elseif ($oldStatusSlug === 'in_use' && $newStatus->slug === 'in_use' && $oldAssignee != $assignedTo) {
                        $oldUser = User::find($oldAssignee);
                        $newUser = User::find($assignedTo);
                        $systemNote = "Aset dipindahtangankan langsung dari " . ($oldUser ? $oldUser->name : 'Unknown') . " kepada " . ($newUser ? $newUser->name : 'Unknown') . ".";
                    } elseif ($newStatus->slug === 'maintenance') {
                        $systemNote = "Aset masuk status perbaikan/maintenance.";
                    } elseif ($newStatus->slug === 'disposed') {
                        $systemNote = "🔴 ASET DIHAPUSBUKUKAN (DISPOSED): Aset telah ditarik dari peredaran dan dihapus dari kekayaan aktif perusahaan.";
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

            return back()->with('success', 'Informasi Aset berhasil diperbarui & Riwayat telah dicatat!');
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
    // 1. FUNGSI PREVIEW IMPORT
    // =========================================================================
    public function previewImport(Request $request)
    {
        $request->validate([
            'import_file' => 'required|mimes:xlsx,xls,csv|max:10240',
            'support_doc' => 'nullable|mimes:pdf,jpg,jpeg,png|max:5120',
            // 🔥 Hapus validasi currency_id, biarkan Excel yang bicara!
        ]);

        try {
            $file = $request->file('import_file');
            $originalName = str_replace(['(', ')', ' '], '_', $file->getClientOriginalName());
            $fileName = time() . '_' . $originalName;

            if (!\Illuminate\Support\Facades\Storage::disk('local')->exists('temp_imports')) {
                \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory('temp_imports');
            }

            $filePath = $file->storeAs('temp_imports', $fileName, 'local');
            $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($filePath);

            $docPath = null;
            if ($request->hasFile('support_doc')) {
                $doc = $request->file('support_doc');
                $docName = time() . '_DOC_' . str_replace(['(', ')', ' '], '_', $doc->getClientOriginalName());
                $docPath = $doc->storeAs('temp_imports', $docName, 'local');
            }

            $importClass = new class implements \Maatwebsite\Excel\Concerns\ToArray, \Maatwebsite\Excel\Concerns\WithHeadingRow {
                public function array(array $array) {}
            };

            $rows = \Maatwebsite\Excel\Facades\Excel::toArray($importClass, $fullPath)[0];
            $previewData = [];
            $hasError = false;

            $items = \App\Models\Item::where('is_asset', true)->pluck('name', 'code')->toArray();
            $users = \App\Models\User::pluck('id', 'name')->mapWithKeys(function ($id, $name) {
                return [strtolower(trim($name)) => $id];
            })->toArray();
            $statuses = \App\Models\Status::where('type', 'AST')->get();
            $warehouses = \App\Models\Warehouse::pluck('id', 'name')->mapWithKeys(function ($id, $name) {
                return [strtolower(trim($name)) => $id];
            })->toArray();

            $currencies = \App\Models\Currency::where('is_active', 1)->get()->keyBy('code');

            foreach ($rows as $index => $row) {
                if (empty($row['kode_barang'])) continue;

                $itemCode = trim($row['kode_barang']);
                $itemValid = array_key_exists($itemCode, $items);
                if (!$itemValid) $hasError = true;

                $rawStatus = $row['status'] ?? 'Available (Tersedia)';
                $cleanStatusName = trim(explode('(', $rawStatus)[0]);
                $statusObj = $statuses->filter(function($s) use ($cleanStatusName) { return stripos($s->name, $cleanStatusName) !== false; })->first();
                $statusSlug = $statusObj ? $statusObj->slug : 'available';

                $borrowerName = trim($row['nama_peminjam'] ?? '');
                $userValid = true;
                $logicValid = true;
                $logicMsg = '';

                if ($statusSlug === 'in_use') {
                    if (empty($borrowerName)) {
                        $logicValid = false;
                        $logicMsg = 'Status "In Use" tapi Peminjam kosong!';
                        $hasError = true;
                    } else {
                        $userValid = array_key_exists(strtolower($borrowerName), $users);
                        if (!$userValid) { $hasError = true; $logicValid = false; $logicMsg = 'User tidak ada!'; }
                    }
                } elseif (!empty($borrowerName)) {
                    $logicValid = false;
                    $logicMsg = 'Aset status "'.$cleanStatusName.'" tidak boleh ada Peminjam!';
                    $hasError = true;
                }

                $warehouseName = trim($row['nama_gudang'] ?? '');
                $warehouseValid = false;
                if (!empty($warehouseName)) {
                    $warehouseValid = array_key_exists(strtolower($warehouseName), $warehouses);
                    if (!$warehouseValid) {
                        $hasError = true;
                        $logicValid = false;
                        $logicMsg = ($logicMsg ? $logicMsg . ' | ' : '') . 'Gudang "'.$warehouseName.'" tidak ada!';
                    }
                } else {
                    $hasError = true;
                    $logicValid = false;
                    $logicMsg = ($logicMsg ? $logicMsg . ' | ' : '') . 'Nama Gudang Kosong!';
                }

                $rawDate = $row['tanggal_perolehan'] ?? null;
                $acqDate = null;
                if (!empty($rawDate)) {
                    if (is_numeric($rawDate)) { $acqDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($rawDate)->format('Y-m-d'); }
                    else { try { $acqDate = \Carbon\Carbon::parse(str_replace('/', '-', $rawDate))->format('Y-m-d'); } catch (\Exception $e) { $acqDate = null; } }
                }

                $purchasePrice = $row['harga_beli_angka_murni'] ?? 0;
                $cleanPrice = preg_replace('/[^0-9]/', '', $purchasePrice);
                $customName = trim($row['nama_spesifik_aset'] ?? '');

                // 🔥 HANDLE MATA UANG DARI EXCEL 🔥
                $currencyCode = strtoupper(trim($row['mata_uang'] ?? 'IDR'));
                if (empty($currencyCode)) $currencyCode = 'IDR';

                $currencyObj = $currencies->get($currencyCode);
                $currencyValid = $currencyObj ? true : false;
                $currencySymbol = $currencyObj ? $currencyObj->symbol : '???';

                if (!$currencyValid) {
                    $hasError = true;
                    $logicValid = false;
                    $logicMsg = ($logicMsg ? $logicMsg . ' | ' : '') . 'Kode Mata Uang ('.$currencyCode.') tidak valid!';
                }

                $previewData[] = [
                    'kode_barang'     => $itemCode,
                    'nama_barang'     => $itemValid ? $items[$itemCode] : 'KODE TIDAK DIKENAL',
                    'nama_custom'     => $customName ?: '-',
                    'item_valid'      => $itemValid,
                    'nama_pt'         => $row['nama_pt'] ?? 'Pusat/Umum',
                    'nama_gudang'     => $warehouseName ?: '-',
                    'status'          => $rawStatus,
                    'peminjam'        => $borrowerName ?: '-',
                    'user_valid'      => $userValid,
                    'logic_valid'     => $logicValid,
                    'logic_msg'       => $logicMsg,
                    'serial'          => $row['serial_number'] ?? '-',
                    'akuntansi'       => $row['label_akuntansi'] ?? '-',
                    'spesifikasi'     => $row['spesifikasi'] ?? '-',
                    'catatan'         => $row['catatan'] ?? '-',
                    'tanggal'         => $acqDate,
                    'harga'           => $cleanPrice ?: 0,
                    'currency_symbol' => $currencySymbol, // Dilempar ke Preview HTML
                    'currency_valid'  => $currencyValid,
                ];
            }

            return view('fixed_assets.preview', compact('previewData', 'filePath', 'hasError', 'docPath'));

        } catch (\Exception $e) {
            if (isset($filePath)) \Illuminate\Support\Facades\Storage::disk('local')->delete($filePath);
            if (isset($docPath)) \Illuminate\Support\Facades\Storage::disk('local')->delete($docPath);
            return back()->with('error', 'Gagal membaca Excel: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // 1. PROSES BACA EXCEL & LEMPAR KE KARANTINA
    // =========================================================================
    public function processImport(Request $request)
    {
        $request->validate([
            'file_path' => 'required|string',
            'doc_path'  => 'nullable|string',
        ]);

        try {
            $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($request->file_path);
            $batchNumber = 'AST-IMP-' . date('Ymd-His');

            // Tangani Dokumen Pendukung BAST
            $finalDocPath = null;
            if ($request->doc_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($request->doc_path)) {
                $finalDocName = basename($request->doc_path);
                $finalDocPath = 'Upload_asset/' . $batchNumber . '/' . $finalDocName;
                \Illuminate\Support\Facades\Storage::disk('public')->put($finalDocPath, \Illuminate\Support\Facades\Storage::disk('local')->get($request->doc_path));
            }

            // 1. Buat Header Batch Karantina
            $batch = \App\Models\FixedAssetImportBatch::create([
                'batch_number' => $batchNumber,
                'user_id'      => auth()->id(),
                'status'       => 'draft',
                'file_path'    => $request->file_path,
                'support_doc'  => $finalDocPath,
            ]);

            // 2. Gunakan Kurir Staging untuk baca Excel
            \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\FixedAssetImport($batch->id), $fullPath);

            \Illuminate\Support\Facades\Storage::disk('local')->delete($request->file_path);
            if ($request->doc_path) \Illuminate\Support\Facades\Storage::disk('local')->delete($request->doc_path);

            return redirect()->route('fixed-assets.import_staging', $batch->id)->with('success', 'Data berhasil masuk ke Ruang Karantina!');
        } catch (\Exception $e) {
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

            // Validasi: Pastikan tidak ada baris error
            if ($batch->details->where('is_valid', 0)->count() > 0) {
                throw new \Exception("Ada baris data yang error. Harap hapus atau perbaiki.");
            }

            $batch->update(['status' => 'waiting_approval']);
            \App\Models\DocumentApproval::where('document_id', $batch->id)->where('document_type', get_class($batch))->delete();

            $workflow = \DB::table('approval_workflows')->where('document_type', get_class($batch))->where('is_active', 1)->first();
            if (!$workflow) throw new \Exception("Workflow Persetujuan Aset belum diaktifkan!");

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

                    // B. Jika Tidak Ketemu / Kosong, Eksekusi Smart Auto-Create
                    if (!$item) {
                        $namaAsetBaru = $row->nama_spesifik_aset;
                        $item = \App\Models\Item::lockForUpdate()->where('name', $namaAsetBaru)->first();

                        if (!$item) {
                            $lastItem = \App\Models\Item::orderBy('id', 'desc')->lockForUpdate()->first();
                            $nextId   = $lastItem ? $lastItem->id + 1 : 1;
                            $newCode  = 'ITM-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                            $slug     = \Illuminate\Support\Str::slug($namaAsetBaru) . '-' . \Illuminate\Support\Str::random(4);

                            // Master Kategori & Satuan Dasar untuk Aset
                            $cat = \App\Models\Category::firstOrCreate(['code' => 'AST'], ['name' => 'Aset Tetap']);
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
                                'specification'  => 'Aset dibuat otomatis via Smart Auto-Create'
                            ]);
                        }
                    }

                    // C. Tarik Data Relasi
                    $company = \App\Models\Company::where('name', 'like', "%{$row->nama_pt}%")->first();
                    $warehouse = \App\Models\Warehouse::where('name', 'like', "%{$row->nama_gudang}%")->first();
                    $status = \App\Models\Status::where('type', 'AST')->where('name', 'like', "%".explode('(', $row->status_aset)[0]."%")->first();

                    $assignedTo = null;
                    if ($status && $status->slug === 'in_use' && !empty($row->nama_peminjam)) {
                        $user = \App\Models\User::where('name', 'like', "%{$row->nama_peminjam}%")->first();
                        $assignedTo = $user ? $user->id : null;
                    }

                    $currency = \App\Models\Currency::where('code', strtoupper($row->mata_uang))->first();

                    // D. Bikin Nomor Aset
                    $lastAsset = \App\Models\FixedAsset::where('asset_number', 'like', "AST/{$yearMonth}/%")->orderBy('id', 'desc')->lockForUpdate()->first();
                    $nextSeq = $lastAsset ? ((int) substr($lastAsset->asset_number, -4)) + 1 : 1;
                    $assetNumber = "AST/{$yearMonth}/" . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

                    // E. Simpan Buku Aset
                    $asset = \App\Models\FixedAsset::create([
                        'asset_number'            => $assetNumber,
                        'item_id'                 => $item->id, // Menggunakan ID Master (Lama atau Baru)
                        'name'                    => $row->nama_spesifik_aset ?: $item->name,
                        'warehouse_id'            => $warehouse ? $warehouse->id : 1,
                        'company_id'              => $company ? $company->id : 1,
                        'serial_number'           => $row->serial_number,
                        'accounting_asset_number' => $row->label_akuntansi,
                        'status_id'               => $status ? $status->id : 1,
                        'assigned_to'             => $assignedTo,
                        'spesifikasi_detail'      => $row->spesifikasi,
                        'notes'                   => $row->catatan,
                        'acquisition_date'        => $row->tanggal_perolehan,
                        'purchase_price'          => $row->harga_beli ?: 0,
                        'currency_id'             => $currency ? $currency->id : 1,
                        'supporting_document'     => $batch->support_doc,
                        'batch_id'                => $batch->batch_number,
                    ]);

                    \App\Models\FixedAssetHistory::create([
                        'fixed_asset_id' => $asset->id, 'status' => $status ? $status->name : 'Unknown',
                        'assigned_to' => $assignedTo, 'notes' => 'Di-import via Karantina (Batch: '.$batch->batch_number.')', 'created_by' => auth()->id()
                    ]);

                    // F. Mutasi Stok
                    $currStock = (float) $item->current_stock;
                    $item->update(['current_stock' => $currStock + 1]);

                    \App\Models\InventoryStock::create([
                        'company_id' => $company ? $company->id : 1, 'warehouse_id' => $warehouse ? $warehouse->id : 1,
                        'item_id' => $item->id, 'stock_qty' => 1, 'reference_number' => $assetNumber, 'notes' => "Excel Import"
                    ]);

                    \App\Models\StockMutation::create([
                        'item_id' => $item->id, 'warehouse_id' => $warehouse ? $warehouse->id : 1,
                        'type' => 'IN', 'qty' => 1, 'balance_before' => $currStock, 'balance_after' => $currStock + 1,
                        'reference_number' => $assetNumber, 'notes' => "Penerimaan Aset Import", 'created_by' => auth()->id()
                    ]);
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
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($batch->support_doc)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($batch->support_doc);
        }
        $batch->details()->delete();
        $batch->delete();
        return redirect()->route('fixed-assets.index')->with('success', 'Draft Import Aset dibatalkan.');
    }


    // =========================================================================
    // 3. DOWNLOAD TEMPLATE
    // =========================================================================
    public function downloadTemplate()
    {
        return Excel::download(new \App\Exports\AssetsTemplateExport, 'Template_Import_Aset_Lengkap.xlsx');
    }


    // =========================================================================
    // 3. FUNGSI RIWAYAT: Menampilkan Tabel Log Import
    // =========================================================================
    public function importHistory()
    {
        // Tarik data batch, urutkan dari yang paling baru
        $batches = \App\Models\ImportBatch::with('uploader')->orderBy('created_at', 'desc')->paginate(10);

        return view('fixed_assets.import_history', compact('batches'));
    }

    // =========================================================================
    // 4. FUNGSI CETAK BAST: Generate PDF Berdasarkan Batch ID
    // =========================================================================
    public function printBastByBatch($batchId)
    {
        $batch = \App\Models\ImportBatch::where('batch_id', $batchId)->firstOrFail();

        // Tarik HANYA aset yang berstatus "In Use" (ada peminjamnya) pada Batch ini
        $assets = \App\Models\FixedAsset::where('batch_id', $batchId)
                    ->whereNotNull('assigned_to')
                    ->with(['item', 'user', 'company'])
                    ->get();

        if ($assets->isEmpty()) {
            return back()->with('error', 'Tidak ada aset berstatus "In Use" (Dipinjam) pada Batch ini untuk dicetak BAST-nya.');
        }

        // Pastikan Anda sudah install: composer require barryvdh/laravel-dompdf
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('fixed_assets.pdf_bast_massal', compact('assets', 'batch'))
                   ->setPaper('A4', 'portrait');

        return $pdf->download("BAST_{$batchId}.pdf");
    }

    // =========================================================================
    // 5. FUNGSI DETAIL BATCH: Menampilkan daftar aset dalam 1 Batch
    // =========================================================================
    public function showImportBatch($batchId)
    {
        // 1. Tarik Info Induk Batch
        $batch = \App\Models\ImportBatch::where('batch_id', $batchId)->with('uploader')->firstOrFail();

        // 2. Tarik Anak-Anak Aset yang masuk di Batch ini
        $assets = \App\Models\FixedAsset::where('batch_id', $batchId)
                    ->with(['item', 'user', 'company', 'status'])
                    ->paginate(20); // Dibuat paging agar kalau 1000 aset tidak berat

        return view('fixed_assets.import_history_show', compact('batch', 'assets'));
    }

    // =========================================================================
    // 6. FUNGSI CETAK QR: Mencetak Label QR Code (Satuan & Massal)
    // =========================================================================
    public function printQrLabel($id)
    {
        // Tarik 1 aset jadikan bentuk collection agar view-nya bisa dipakai ulang
        $assets = \App\Models\FixedAsset::with(['item', 'company'])->where('id', $id)->get();
        return view('fixed_assets.print_qr', compact('assets'));
    }

    public function printMassQrLabel($batchId)
    {
        // Tarik SEMUA aset dalam Batch tersebut
        $assets = \App\Models\FixedAsset::with(['item', 'company'])->where('batch_id', $batchId)->get();

        if ($assets->isEmpty()) {
            return back()->with('error', 'Tidak ada aset pada Batch ini untuk dicetak.');
        }

        return view('fixed_assets.print_qr', compact('assets', 'batchId'));
    }


    // =========================================================================
    // FUNGSI PENCARIAN BARANG KHUSUS REGISTER ASET TETAP (AJAX SELECT2)
    // =========================================================================
    public function searchItems(Request $request)
    {
        $search = $request->search;

        // Hanya cari barang yang bertipe "Aset Tetap" (is_asset = true)
        $items = \App\Models\Item::where('is_asset', true)
                    ->when($search, function($query) use ($search) {
                        $query->where(function($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                              ->orWhere('code', 'like', "%{$search}%");
                        });
                    })
                    ->limit(30) // Batasi 30 agar respons browser tetap kilat
                    ->get();

        // Format data agar sesuai dengan standar bacaan Select2
        $formattedItems = [];
        foreach ($items as $item) {
            $formattedItems[] = [
                'id'   => $item->id,
                'text' => '[' . $item->code . '] ' . $item->name
            ];
        }

        return response()->json($formattedItems);
    }


    // 🔥 FUNGSI BARU UNTUK HALAMAN DAFTAR RIWAYAT HIBAH 🔥
    public function hibahHistory()
    {
        // Mengelompokkan aset berdasarkan Batch ID Hibah
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

