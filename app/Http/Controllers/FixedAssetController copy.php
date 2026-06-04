<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FixedAsset;
use App\Models\User;
use App\Models\Status;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
    // 1. FUNGSI PREVIEW: Membaca Excel (Mata Uang & Gudang ditarik dari Excel)
    // =========================================================================
    public function previewImport(Request $request)
    {
        // 🔥 VALIDASI DILONGGARKAN AGAR TIDAK DITENDANG LARAVEL 🔥
        $request->validate([
            'import_file' => 'required|file|max:10240',
            'support_doc' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'import_file.required' => 'File Excel wajib diupload!',
            'import_file.max' => 'Ukuran file Excel maksimal 10 MB!'
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
                    'currency_symbol' => $currencySymbol,
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
    // 2. FUNGSI PROSES IMPORT
    // =========================================================================
    public function processImport(Request $request)
    {
        // 🔥 VALIDASI DILONGGARKAN (TANPA MATA UANG) 🔥
        $request->validate([
            'file_path'    => 'required|string',
            'doc_path'     => 'nullable|string',
        ]);

        $filePath = $request->file_path;
        $docPath = $request->doc_path;

        if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($filePath)) {
            return redirect()->route('fixed-assets.index')->with('error', 'File import sudah kadaluarsa.');
        }

        try {
            $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($filePath);
            $batchId = 'BATCH-' . date('His');
            $fullBatchCode = date('Ymd') . '-' . $batchId;

            $finalDocPath = null;
            if ($docPath && \Illuminate\Support\Facades\Storage::disk('local')->exists($docPath)) {
                $finalDocName = basename($docPath);
                $folderPath = 'Upload_asset/' . date('Y-m-d') . '-' . $batchId;
                $finalDocPath = $folderPath . '/' . $finalDocName;
                \Illuminate\Support\Facades\Storage::disk('public')->put($finalDocPath, \Illuminate\Support\Facades\Storage::disk('local')->get($docPath));
                \Illuminate\Support\Facades\Storage::disk('local')->delete($docPath);
            }

            \App\Models\ImportBatch::create([
                'batch_id'    => $fullBatchCode,
                'file_name'   => basename($filePath),
                'support_doc' => $finalDocPath,
                'total_items' => 0,
                'created_by'  => auth()->id(),
            ]);

            // 🔥 EKSEKUSI EXCEL (Hanya melempar Batch ID & Doc Path) 🔥
            \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\FixedAssetImport($fullBatchCode, $finalDocPath), $fullPath);

            $totalMasuk = \App\Models\FixedAsset::where('batch_id', $fullBatchCode)->count();
            \App\Models\ImportBatch::where('batch_id', $fullBatchCode)->update(['total_items' => $totalMasuk]);

            \Illuminate\Support\Facades\Storage::disk('local')->delete($filePath);

            return redirect()->route('fixed-assets.index')->with('success', "Data aset berhasil di-import! (Batch: {$fullBatchCode})");
        } catch (\Exception $e) {
            \App\Models\ImportBatch::where('batch_id', $fullBatchCode ?? '')->delete();
            return redirect()->route('fixed-assets.index')->with('error', 'Gagal memproses import: ' . $e->getMessage());
        }
    }


    public function downloadTemplate()
    {
        return Excel::download(new \App\Exports\AssetsTemplateExport, 'Template_Import_Aset_Lengkap.xlsx');
    }

    public function importHistory()
    {
        $batches = \App\Models\ImportBatch::with('uploader')->orderBy('created_at', 'desc')->paginate(10);
        return view('fixed_assets.import_history', compact('batches'));
    }

    public function printBastByBatch($batchId)
    {
        $batch = \App\Models\ImportBatch::where('batch_id', $batchId)->firstOrFail();
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
        $batch = \App\Models\ImportBatch::where('batch_id', $batchId)->with('uploader')->firstOrFail();
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
        $items = \App\Models\Item::where('is_asset', true)
                    ->when($search, function($query) use ($search) {
                        $query->where(function($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                              ->orWhere('code', 'like', "%{$search}%");
                        });
                    })
                    ->limit(30)->get();

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
