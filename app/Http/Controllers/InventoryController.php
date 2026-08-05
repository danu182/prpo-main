<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\StockMutation;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Exports\InventoryTemplateExport;
use App\Imports\InventoryImport;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class InventoryController extends Controller
{

    // ==========================================
    // 1. Tampilkan Semua Saldo Stok (Diringkas per Barang & Gudang)
    // ==========================================
    public function index(Request $request)
    {
        $search = $request->input('search');
        $warehouseId = $request->input('warehouse_id');

        $warehouses = \App\Models\Warehouse::orderBy('name')->get();

        // 🔥 FILTER SUPER AMAN: Kecualikan JSA/NST, tapi TETAP BAWA kode yang KOSONG (NULL)
        $allItems = \App\Models\Item::where(function($q) {
            $q->whereNotIn('item_type_code', ['JSA', 'NST'])
              ->orWhereNull('item_type_code');
        })->orderBy('name')->get();

        // 🔥 RADAR BARANG KRITIS
        $criticalStocks = \App\Models\Item::with('uom')
            ->where(function($q) {
                $q->whereNotIn('item_type_code', ['JSA', 'NST'])
                  ->orWhereNull('item_type_code');
            })
            ->whereNotNull('min_stock')
            ->where('min_stock', '>', 0)
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->get();

        // 🔥 QUERY DAFTAR INVENTORY
        $stocks = \App\Models\Item::query()
            ->select('items.*')
            ->with('uom')
            ->where(function($q) {
                $q->whereNotIn('item_type_code', ['JSA', 'NST'])
                  ->orWhereNull('item_type_code'); // Mengatasi masalah data lama / null
            })
            ->addSelect([
                'total_stock' => \App\Models\InventoryStock::selectRaw('COALESCE(SUM(stock_qty), 0)')
                    ->whereColumn('item_id', 'items.id')
                    ->when($warehouseId, function($q) use ($warehouseId) {
                        $q->where('warehouse_id', $warehouseId);
                    })
            ])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('inventory.index', compact('stocks', 'warehouses', 'search', 'warehouseId', 'allItems', 'criticalStocks'));
    }


    // ==========================================
    // 2. Pemakaian Stok (Usage) Spesifik dari Gudang Tertentu
    // ==========================================
    public function storeUsage(Request $request)
    {
        $request->validate([
            'item_id'      => 'required',
            'warehouse_id' => 'required',
            'qty'          => 'required|integer|min:1',
            'notes'        => 'required'
        ]);

        try {
            DB::transaction(function () use ($request) {
                $userCompanyId = auth()->user()->company_id ?? 1;

                // Cek Stok Spesifik di Gudang Tersebut
                $stock = InventoryStock::where('company_id', $userCompanyId)
                            ->where('item_id', $request->item_id)
                            ->where('warehouse_id', $request->warehouse_id)
                            ->lockForUpdate()
                            ->firstOrFail();

                if ($stock->stock_qty < $request->qty) {
                    throw new \Exception("Stok di gudang ini tidak mencukupi. Sisa: " . $stock->stock_qty);
                }

                // Kurangi Stok
                $stock->decrement('stock_qty', $request->qty);

                // Kurangi Stok Global di Master Item
                $item = Item::lockForUpdate()->findOrFail($request->item_id);
                $balanceBefore = (float) $item->current_stock;
                $balanceAfter  = $balanceBefore - $request->qty;
                $item->update(['current_stock' => $balanceAfter]);

                // Catat Log Keluar
                if (class_exists(InventoryMovement::class)) {
                    InventoryMovement::create([
                        'inventory_stock_id' => $stock->id,
                        'warehouse_id'       => $request->warehouse_id,
                        'type'               => 'OUT',
                        'qty'                => $request->qty,
                        'reference_number'   => 'USE/' . date('YmdHis'),
                        'notes'              => $request->notes,
                        'created_by'         => auth()->id()
                    ]);
                }

                // Catat ke StockMutation
                StockMutation::create([
                    'item_id'          => $request->item_id,
                    'warehouse_id'     => $request->warehouse_id,
                    'type'             => 'OUT',
                    'qty'              => $request->qty,
                    'balance_before'   => $balanceBefore,
                    'balance_after'    => $balanceAfter,
                    'reference_number' => 'USE/' . date('YmdHis'),
                    'notes'            => $request->notes,
                    'created_by'       => auth()->id()
                ]);
            });

            return response()->json(['message' => 'Stok berhasil dikeluarkan']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    // ==========================================
    // 3. Tampilkan Kartu Stok (Riwayat) per Barang & Gudang
    // ==========================================
    public function show($id, Request $request)
    {
        $warehouseId = $request->input('warehouse_id');
        $item = \App\Models\Item::with('uom')->where('item_type_code', 'STK')->findOrFail($id);

        // 1. HITUNG SALDO TERKINI (Kotak Biru) - Mutlak dari Database Fisik
        $bulkStock = \App\Models\InventoryStock::where('item_id', $id)
            ->when($warehouseId, function($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            })->sum('stock_qty');

        $assetStock = \App\Models\FixedAsset::where('item_id', $id)
            ->whereHas('status', function($q) {
                $q->where('slug', 'available');
            })
            ->when($warehouseId, function($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            })->count();

        $currentStock = $bulkStock + $assetStock;

        // 2. TARIK RIWAYAT MUTASI
        $mutations = \App\Models\StockMutation::with('warehouse')
            ->where('item_id', $id)
            ->when($warehouseId, function($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        // 3. LOGIKA KARTU STOK DINAMIS (FORWARD CALCULATION = ANTI RANCU)
        if ($mutations->count() > 0) {
            $oldestMutationOnPage = $mutations->last();

            // Hitung total IN masa lalu sebelum mutasi paling tua di halaman ini
            $pastIn = \App\Models\StockMutation::where('item_id', $id)
                ->when($warehouseId, function($q) use ($warehouseId) { $q->where('warehouse_id', $warehouseId); })
                ->where(function($q) use ($oldestMutationOnPage) {
                    $q->where('created_at', '<', $oldestMutationOnPage->created_at)
                      ->orWhere(function($q2) use ($oldestMutationOnPage) {
                          $q2->where('created_at', '=', $oldestMutationOnPage->created_at)
                             ->where('id', '<', $oldestMutationOnPage->id);
                      });
                })->where('type', 'IN')->sum('qty');

            // Hitung total OUT masa lalu
            $pastOut = \App\Models\StockMutation::where('item_id', $id)
                ->when($warehouseId, function($q) use ($warehouseId) { $q->where('warehouse_id', $warehouseId); })
                ->where(function($q) use ($oldestMutationOnPage) {
                    $q->where('created_at', '<', $oldestMutationOnPage->created_at)
                      ->orWhere(function($q2) use ($oldestMutationOnPage) {
                          $q2->where('created_at', '=', $oldestMutationOnPage->created_at)
                             ->where('id', '<', $oldestMutationOnPage->id);
                      });
                })->where('type', 'OUT')->sum('qty');

            $runningBalance = $pastIn - $pastOut;

            // Loop dari yang tertua ke terbaru untuk halaman ini
            $reversed = $mutations->reverse();
            foreach ($reversed as $mut) {
                if ($mut->type === 'IN') {
                    $runningBalance += $mut->qty;
                } else {
                    $runningBalance -= $mut->qty;
                }
                $mut->dynamic_balance = $runningBalance;
            }
        }

        $warehouses = \App\Models\Warehouse::orderBy('name')->get();

        return view('inventory.show', compact('item', 'mutations', 'warehouses', 'warehouseId', 'currentStock', 'bulkStock', 'assetStock'));
    }

    // ==========================================
    // 4. FUNGSI REGISTER STOK / SALDO AWAL (MANUAL)
    // ==========================================
    public function storeAdjustment(Request $request)
    {
        $request->validate([
            'item_id' => 'required',
            'warehouse_id' => 'required',
            'qty' => 'required|numeric|min:0.1',
            'notes' => 'required'
        ]);

        try {
            DB::transaction(function () use ($request) {
                $userCompanyId = auth()->user()->company_id ?? 1;

                $item = Item::lockForUpdate()->findOrFail($request->item_id);

                $stock = InventoryStock::firstOrCreate(
                    [
                        'company_id'   => $userCompanyId,
                        'item_id'      => $request->item_id,
                        'warehouse_id' => $request->warehouse_id,
                    ],
                    ['stock_qty' => 0]
                );

                $stock->increment('stock_qty', $request->qty);

                $balanceBefore = (float) $item->current_stock;
                $balanceAfter  = $balanceBefore + $request->qty;

                $item->update(['current_stock' => $balanceAfter]);

                StockMutation::create([
                    'item_id'          => $request->item_id,
                    'warehouse_id'     => $request->warehouse_id,
                    'type'             => 'IN',
                    'qty'              => $request->qty,
                    'balance_before'   => $balanceBefore,
                    'balance_after'    => $balanceAfter,
                    'reference_number' => 'SA/' . date('YmdHis'),
                    'notes'            => $request->notes,
                    'created_by'       => auth()->id()
                ]);

                if (class_exists(InventoryMovement::class)) {
                    InventoryMovement::create([
                        'inventory_stock_id' => $stock->id,
                        'warehouse_id'       => $request->warehouse_id,
                        'type'               => 'IN',
                        'qty'                => $request->qty,
                        'reference_number'   => 'SA/' . date('YmdHis'),
                        'notes'              => $request->notes,
                        'created_by'         => auth()->id()
                    ]);
                }
            });

            return back()->with('success', 'Stok berhasil diregister ke Gudang!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mendaftar stok: ' . $e->getMessage());
        }
    }

    // ==========================================
    // 5. EXPORT / IMPORT SALDO AWAL (EXCEL)
    // ==========================================
    public function downloadTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\InventoryTemplateExport, 'Template_Saldo_Awal_Stok.xlsx');
    }

    public function importSaldoAwal(Request $request)
    {
        $request->validate(['import_file' => 'required|mimes:xlsx,xls']);

        try {
            \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\InventoryImport, $request->file('import_file'));
            return back()->with('success', 'Saldo awal stok berhasil disuntikkan ke gudang!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal import saldo: ' . $e->getMessage());
        }
    }

    public function previewImport(Request $request)
    {
        $request->validate([
            'import_file'     => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'attachment_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], ['import_file.required' => 'File Excel wajib diupload!']);

        try {
            $file = $request->file('import_file');
            $fileName = time() . '_INV_' . str_replace(['(', ')', ' '], '_', $file->getClientOriginalName());

            if (!\Illuminate\Support\Facades\Storage::disk('local')->exists('temp_imports')) {
                \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory('temp_imports');
            }
            $filePath = $file->storeAs('temp_imports', $fileName, 'local');
            $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($filePath);

            $attachmentPath = null;
            if ($request->hasFile('attachment_file')) {
                if (!\Illuminate\Support\Facades\Storage::disk('local')->exists('temp_attachments')) {
                    \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory('temp_attachments');
                }
                $attachmentPath = $request->file('attachment_file')->store('temp_attachments', 'local');
            }

            $importClass = new class implements \Maatwebsite\Excel\Concerns\ToArray, \Maatwebsite\Excel\Concerns\WithHeadingRow {
                public function array(array $array) {}
            };
            $rows = \Maatwebsite\Excel\Facades\Excel::toArray($importClass, $fullPath)[0];
            $previewData = [];
            $hasError = false;

            // 🔥 TARIK DATA MASTER (HANYA AMBIL item_type_code) 🔥
            $allItems = \App\Models\Item::select('id', 'code', 'name', 'item_type_code')
                ->get()->keyBy(function($item) { return strtolower(trim($item->code)); });

            $warehouses = \App\Models\Warehouse::pluck('id', 'name')->mapWithKeys(function ($id, $name) { return [strtolower(trim($name)) => $id]; })->toArray();
            $validCurrencies = \App\Models\Currency::where('is_active', 1)->pluck('id', 'code')->mapWithKeys(function ($id, $code) { return [strtoupper(trim($code)) => $id]; })->toArray();

            foreach ($rows as $index => $row) {
                if (empty($row['kode_barang']) && empty($row['jumlah_qty'])) continue;

                $isRowValid = true;
                $errorMessages = [];
                $realName = '-';

                // 🔥 VALIDASI KODE MENGGUNAKAN item_type_code 🔥
                $itemCode = strtolower(trim($row['kode_barang'] ?? ''));
                if (empty($itemCode)) {
                    $isRowValid = false; $errorMessages[] = 'Kode Barang KOSONG.';
                } elseif (!$allItems->has($itemCode)) {
                    $isRowValid = false; $errorMessages[] = "Kode '{$row['kode_barang']}' TIDAK TERDAFTAR.";
                } else {
                    $itemData = $allItems[$itemCode];
                    $realName = $itemData->name;

                    if ($itemData->item_type_code === 'JSA') {
                        $isRowValid = false; $errorMessages[] = "DITOLAK: Ini Jasa/Layanan.";
                    } elseif ($itemData->item_type_code === 'NST') {
                        $isRowValid = false; $errorMessages[] = "DITOLAK: Ini Barang Non-Stok.";
                    } elseif ($itemData->item_type_code !== 'STK') {
                        $isRowValid = false; $errorMessages[] = "DITOLAK: Tipe barang tidak valid masuk gudang.";
                    }
                }

                $warehouseName = strtolower(trim($row['nama_gudang'] ?? ''));
                if (empty($warehouseName) || !array_key_exists($warehouseName, $warehouses)) {
                    $isRowValid = false; $errorMessages[] = "Gudang '{$row['nama_gudang']}' tidak valid.";
                }

                $qty = $row['jumlah_qty'] ?? '';
                if ($qty === '' || !is_numeric($qty) || $qty < 0) { $isRowValid = false; $errorMessages[] = "Qty tidak valid."; }

                $price = $row['harga_satuan'] ?? '';
                if ($price === '' || !is_numeric($price) || $price < 0) { $isRowValid = false; $errorMessages[] = "Harga tidak valid."; }

                $currency = strtoupper(trim($row['mata_uang'] ?? ''));
                if (empty($currency) || !array_key_exists($currency, $validCurrencies)) { $isRowValid = false; $errorMessages[] = "Mata Uang '{$currency}' tidak valid."; }

                if (!$isRowValid) { $hasError = true; }

                $previewData[] = [
                    'kode_barang'  => $row['kode_barang'] ?? '-',
                    'nama_barang'  => $realName,
                    'nama_gudang'  => $row['nama_gudang'] ?? '-',
                    'qty'          => $qty !== '' ? (float)$qty : '-',
                    'harga'        => $price !== '' ? (float)$price : '-',
                    'currency'     => $currency ?: '-',
                    'catatan'      => $row['catatan'] ?? '-',
                    'is_row_valid' => $isRowValid,
                    'error_messages'=> $errorMessages
                ];
            }

            return view('inventory.preview', compact('previewData', 'filePath', 'hasError', 'attachmentPath'));

        } catch (\Exception $e) {
            if (isset($filePath)) \Illuminate\Support\Facades\Storage::disk('local')->delete($filePath);
            if (isset($attachmentPath)) \Illuminate\Support\Facades\Storage::disk('local')->delete($attachmentPath);
            return back()->with('error', 'Gagal membaca file Excel: ' . $e->getMessage());
        }
    }

    public function processImport(Request $request)
    {
        $tempExcelPath = $request->file_path;
        $tempAttachmentPath = $request->attachment_path;

        if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($tempExcelPath)) {
            return redirect()->route('inventory.index')->with('error', 'File import sudah kadaluarsa atau terhapus.');
        }

        try {
            $batchNumber = 'SA-' . date('YmdHis') . '-' . strtoupper(\Illuminate\Support\Str::random(3));
            $folderPath = 'saldo_awal_inventory/' . $batchNumber;

            \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory($folderPath);

            $excelExtension = pathinfo($tempExcelPath, PATHINFO_EXTENSION);
            $finalExcelPath = $folderPath . '/Data_Excel_' . $batchNumber . '.' . $excelExtension;

            $fileContent = \Illuminate\Support\Facades\Storage::disk('local')->get($tempExcelPath);
            \Illuminate\Support\Facades\Storage::disk('public')->put($finalExcelPath, $fileContent);

            $finalAttachmentPath = null;
            if ($tempAttachmentPath && \Illuminate\Support\Facades\Storage::disk('local')->exists($tempAttachmentPath)) {
                $attachmentExtension = pathinfo($tempAttachmentPath, PATHINFO_EXTENSION);
                $finalAttachmentPath = $folderPath . '/Dokumen_Bukti_' . $batchNumber . '.' . $attachmentExtension;
                $attachmentContent = \Illuminate\Support\Facades\Storage::disk('local')->get($tempAttachmentPath);
                \Illuminate\Support\Facades\Storage::disk('public')->put($finalAttachmentPath, $attachmentContent);
            }

            $fullExcelPath = storage_path('app/public/' . $finalExcelPath);
            \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\InventoryImport($batchNumber, $finalAttachmentPath), $fullExcelPath);

            \Illuminate\Support\Facades\Storage::disk('local')->delete($tempExcelPath);
            if ($tempAttachmentPath) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($tempAttachmentPath);
            }

            return redirect()->route('inventory.index')->with('success', "Hebat! Saldo Awal berhasil disuntikkan. Nomor Upload: $batchNumber. Semua file telah diarsipkan!");

        } catch (\Exception $e) {
            return redirect()->route('inventory.index')->with('error', 'Gagal memproses import: ' . $e->getMessage());
        }
    }

    public function downloadErrors(Request $request)
    {
        $filePath = $request->file_path;
        if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($filePath)) {
            return back()->with('error', 'File sumber tidak ditemukan, silakan upload ulang.');
        }

        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($filePath);
        $importClass = new class implements \Maatwebsite\Excel\Concerns\ToArray, \Maatwebsite\Excel\Concerns\WithHeadingRow {
            public function array(array $array) {}
        };
        $rows = \Maatwebsite\Excel\Facades\Excel::toArray($importClass, $fullPath)[0];

        // 🔥 GANTI KODINGAN INI UNTUK SESUAIKAN DENGAN STRUKTUR KOMANDAN
        $allItems = \App\Models\Item::select('id', 'code', 'item_type_code')->get()->keyBy(function($item) { return strtolower(trim($item->code)); });
        $warehouses = \App\Models\Warehouse::pluck('id', 'name')->mapWithKeys(function ($id, $name) { return [strtolower(trim($name)) => $id]; })->toArray();
        $validCurrencies = \App\Models\Currency::where('is_active', 1)->pluck('id', 'code')->mapWithKeys(function ($id, $code) { return [strtoupper(trim($code)) => $id]; })->toArray();

        $errorData = [];

        foreach ($rows as $row) {
            if (empty($row['kode_barang']) && empty($row['jumlah_qty'])) continue;

            $isRowValid = true;
            $errorMessages = [];

            // 🔥 VALIDASI ERROR DOWNLOAD MENGGUNAKAN item_type_code 🔥
            $itemCode = strtolower(trim($row['kode_barang'] ?? ''));
            if (empty($itemCode)) {
                $isRowValid = false; $errorMessages[] = 'Kode KOSONG';
            } elseif (!$allItems->has($itemCode)) {
                $isRowValid = false; $errorMessages[] = 'Kode TIDAK TERDAFTAR';
            } else {
                $itemData = $allItems[$itemCode];
                if ($itemData->item_type_code === 'JSA') {
                    $isRowValid = false; $errorMessages[] = 'DITOLAK: Ini JASA';
                } elseif ($itemData->item_type_code === 'NST') {
                    $isRowValid = false; $errorMessages[] = 'DITOLAK: Ini Non-Stok';
                } elseif ($itemData->item_type_code !== 'STK') {
                    $isRowValid = false; $errorMessages[] = 'DITOLAK: Tipe Tidak Valid';
                }
            }

            $warehouseName = strtolower(trim($row['nama_gudang'] ?? ''));
            if (empty($warehouseName) || !array_key_exists($warehouseName, $warehouses)) {
                $isRowValid = false; $errorMessages[] = 'Gudang SALAH/KOSONG';
            }

            $qty = $row['jumlah_qty'] ?? '';
            if ($qty === '' || !is_numeric($qty) || $qty < 0) { $isRowValid = false; $errorMessages[] = 'Qty INVALID'; }

            $price = $row['harga_satuan'] ?? '';
            if ($price === '' || !is_numeric($price) || $price < 0) { $isRowValid = false; $errorMessages[] = 'Harga INVALID'; }

            $currency = strtoupper(trim($row['mata_uang'] ?? ''));
            if (empty($currency) || !array_key_exists($currency, $validCurrencies)) { $isRowValid = false; $errorMessages[] = 'Mata Uang INVALID'; }

            if (!$isRowValid) {
                $errorData[] = [
                    $row['kode_barang'] ?? '',
                    $row['nama_barang'] ?? '',
                    $row['nama_gudang'] ?? '',
                    $row['jumlah_qty'] ?? '',
                    $row['harga_satuan'] ?? '',
                    $row['mata_uang'] ?? '',
                    $row['catatan'] ?? '',
                    implode(" | ", $errorMessages),
                ];
            }
        }

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\InventoryErrorExport($errorData), 'Error_Log_Saldo_Awal.xlsx');
    }


    // ==========================================
    // 2. TAMPILKAN FORM KAPITALISASI ASET (VERSI SLUG)
    // ==========================================
    public function capitalizeForm($slug)
    {
        // 🔥 Ubah pencarian: cari berdasarkan kolom 'code' (slug) 🔥
        $item = \App\Models\Item::with('uom')->where('code', $slug)->firstOrFail();
        $warehouses = \App\Models\Warehouse::orderBy('name')->get();

        // Hitung stok bulk yang tersedia di setiap gudang
        foreach ($warehouses as $wh) {
            $totalPhysical = \App\Models\InventoryStock::where('item_id', $item->id)->where('warehouse_id', $wh->id)->sum('stock_qty');

            $totalAsAsset = \App\Models\FixedAsset::where('item_id', $item->id)->where('warehouse_id', $wh->id)
                ->whereHas('status', function($q) { $q->where('slug', 'available'); })
                ->count();

            $wh->available_regular_stock = max(0, $totalPhysical - $totalAsAsset);
        }

        return view('inventory.capitalize', compact('item', 'warehouses'));
    }

    // ==========================================
    // 3. EKSEKUSI SULAP STOK BIASA -> ASET TETAP (VERSI SLUG)
    // ==========================================




   // ==========================================
    // 3. EKSEKUSI SULAP STOK BIASA -> ASET TETAP (MAPPING DATABASE SEMPURNA)
    // ==========================================
    public function capitalizeStore(Request $request, $slug)
    {
        $request->validate([
            'warehouse_id'         => 'required|exists:warehouses,id',
            'qty'                  => 'required|integer|min:1',
            'acc_asset_number'     => 'required|array',
            'acc_asset_number.*'   => 'required|string',
            'sn'                   => 'required|array',
            'sn.*'                 => 'required|string',
            'specs'                => 'nullable|array',
            'notes'                => 'nullable|array',
        ]);

        try {
            \DB::transaction(function () use ($request, $slug) {
                $item = \App\Models\Item::where('code', $slug)->firstOrFail();
                $qtyToConvert = (int) $request->qty;

                // 1. Validasi ketersediaan stok
                $totalPhysical = \App\Models\InventoryStock::where('item_id', $item->id)->where('warehouse_id', $request->warehouse_id)->sum('stock_qty');
                $totalAsAsset = \App\Models\FixedAsset::where('item_id', $item->id)->where('warehouse_id', $request->warehouse_id)
                    ->whereHas('status', function($q) { $q->where('slug', 'available'); })->count();
                $availableRegular = $totalPhysical - $totalAsAsset;

                if ($qtyToConvert > $availableRegular) {
                    throw new \Exception("Gagal! Kuantitas konversi ({$qtyToConvert}) melebihi sisa stok biasa yang tersedia ({$availableRegular}).");
                }

                $statusAvailable = \App\Models\Status::where('type', 'AST')->where('slug', 'available')->first();

                // 2. Mesin Pembuat Nomor Aset Sistem
                $yearMonth = date('Y/m');
                $lastAsset = \App\Models\FixedAsset::where('asset_number', 'like', "AST/{$yearMonth}/%")->orderBy('id', 'desc')->lockForUpdate()->first();
                $nextSeq = $lastAsset ? ((int) substr($lastAsset->asset_number, -4)) + 1 : 1;

                // 3. Daftarkan aset ke database (Mapping langsung ke kolom tabel)
                for ($i = 0; $i < $qtyToConvert; $i++) {
                    $sysAssetNumber = "AST/{$yearMonth}/" . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

                    $accAssetNum = trim($request->acc_asset_number[$i]);
                    $currentSn   = trim($request->sn[$i]);
                    $currentSpec = trim($request->specs[$i] ?? '');
                    $currentNote = trim($request->notes[$i] ?? '');

                    \App\Models\FixedAsset::create([
                        'asset_number'             => $sysAssetNumber,        // Kode Sistem IT
                        'accounting_asset_number'  => $accAssetNum,           // 🔥 MASUK KE KOLOM AKUNTANSI 🔥
                        'serial_number'            => $currentSn,             // SN Pabrik / Rangka
                        'spesifikasi_detail'       => $currentSpec,           // 🔥 MASUK KE KOLOM SPESIFIKASI 🔥
                        'item_id'                  => $item->id,
                        'warehouse_id'             => $request->warehouse_id,
                        'company_id'               => \App\Models\Warehouse::find($request->warehouse_id)->company_id ?? 1,
                        'name'                     => $item->name,
                        'acquisition_date'         => now()->toDateString(),
                        'purchase_price'           => 0,
                        'status_id'                => $statusAvailable->id ?? 1,
                        'notes'                    => $currentNote ?: "Kapitalisasi Mandiri dari Stok Gudang pada " . now()->format('d M Y H:i')
                    ]);

                    $nextSeq++;
                }
            });

            return redirect()->route('inventory.index')->with('success', 'Berhasil! Entitas Aset telah didaftarkan lengkap dengan No Akuntansi dan Spesifikasinya.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }





    // =========================================================================
    // 🔥 1. LAPORAN MUTASI STOK (QTY SAJA - UNTUK GUDANG) 🔥
    // =========================================================================
    public function stockAsOf(\Illuminate\Http\Request $request)
    {
        $data = $this->calculateStockAsOfData($request, false); // false = gunakan paginasi
        return view('inventory.stock_as_of', $data);
    }

    public function printStockAsOf(\Illuminate\Http\Request $request)
    {
        $data = $this->calculateStockAsOfData($request, true); // true = tarik semua data
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('inventory.print_stock_as_of', $data)->setPaper('A4', 'landscape');
        return $pdf->stream('Laporan_Mutasi_Inventory_' . date('Ymd_His') . '.pdf');
    }

    public function exportStockAsOf(\Illuminate\Http\Request $request)
    {
        $data = $this->calculateStockAsOfData($request, true);
        $fileName = 'Laporan_Mutasi_Inventory_' . date('Ymd_His') . '.xls';

        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=\"$fileName\"");
        header("Pragma: no-cache");
        header("Expires: 0");

        return view('inventory.print_stock_as_of', $data)->with('isExcel', true);
    }

    // =========================================================================
    // 🔥 2. LAPORAN VALUASI STOK (QTY + RUPIAH - UNTUK FINANCE) 🔥
    // =========================================================================
    public function valuation(\Illuminate\Http\Request $request)
    {
        $data = $this->calculateStockAsOfData($request, false);
        return view('inventory.valuation', $data);
    }

    public function printValuation(\Illuminate\Http\Request $request)
    {
        $data = $this->calculateStockAsOfData($request, true);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('inventory.print_valuation', $data)->setPaper('A4', 'landscape');
        return $pdf->stream('Laporan_Valuasi_Inventory_' . date('Ymd_His') . '.pdf');
    }

    public function exportValuation(\Illuminate\Http\Request $request)
    {
        $data = $this->calculateStockAsOfData($request, true);
        $fileName = 'Laporan_Valuasi_Inventory_' . date('Ymd_His') . '.xls';

        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=\"$fileName\"");
        header("Pragma: no-cache");
        header("Expires: 0");

        return view('inventory.print_valuation', $data)->with('isExcel', true);
    }

    // =========================================================================
    // 🔥 3. MESIN HITUNG SAKTI (DIGUNAKAN OLEH SEMUA LAPORAN DI ATAS) 🔥
    // =========================================================================
    private function calculateStockAsOfData(\Illuminate\Http\Request $request, $isPrint = false)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        $warehouseId = $request->input('warehouse_id');
        $search = $request->input('search');

        $start = \Carbon\Carbon::parse($startDate)->startOfDay();
        $end = \Carbon\Carbon::parse($endDate)->endOfDay();

        // 🔥 WAJIB ADA: Data Master untuk Dropdown dan Kop Surat Cetakan 🔥
        $warehouses = \App\Models\Warehouse::orderBy('name')->get();
        $warehouse = $warehouseId ? \App\Models\Warehouse::find($warehouseId) : null;

        $query = \App\Models\Item::with('category')->whereHas('category', function($q) {
            $q->where('code', '!=', 'AST');
        });

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // 🔥 LOGIKA PINTAR: Jika cetak, ambil semua (get). Jika tampilan web, gunakan paginasi (paginate) 🔥
        if ($isPrint) {
            $items = $query->orderBy('name', 'asc')->get();
        } else {
            $items = $query->orderBy('name', 'asc')->paginate(20)->withQueryString();
        }

        foreach ($items as $item) {
            $mutations = \App\Models\StockMutation::where('item_id', $item->id);
            if ($warehouseId) {
                $mutations->where('warehouse_id', $warehouseId);
            }

            // 1. QTY CALCULATIONS
            $inBefore = (clone $mutations)->where('type', 'IN')->where('created_at', '<', $start)->sum('qty');
            $outBefore = (clone $mutations)->where('type', 'OUT')->where('created_at', '<', $start)->sum('qty');
            $item->saldo_awal = $inBefore - $outBefore;

            $item->mutasi_in = (clone $mutations)->where('type', 'IN')->whereBetween('created_at', [$start, $end])->sum('qty');
            $item->mutasi_out = (clone $mutations)->where('type', 'OUT')->whereBetween('created_at', [$start, $end])->sum('qty');

            $item->saldo_akhir = $item->saldo_awal + $item->mutasi_in - $item->mutasi_out;

            // =================================================================
            // 2. RUPIAH VALUATION (DINAMIS & AMAN)
            // =================================================================

            // Cari harga beli TERAKHIR dari riwayat PO
            $latestPOItem = \Illuminate\Support\Facades\DB::table('purchase_order_items')
                                ->where('item_id', $item->id)
                                ->orderBy('created_at', 'desc')
                                ->first();

            // 🔥 LOGIKA CERDAS: Gunakan harga PO terakhir.
            // JIKA belum pernah di-PO (misal barang Saldo Awal), ambil harga dari tabel items
            $hargaSatuan = $latestPOItem
                            ? $latestPOItem->unit_price
                            : ($item->purchase_price ?? ($item->price ?? 0));

            $item->harga_satuan = $hargaSatuan;

            $item->nilai_awal = $item->saldo_awal * $hargaSatuan;
            $item->nilai_in = $item->mutasi_in * $hargaSatuan;
            $item->nilai_out = $item->mutasi_out * $hargaSatuan;
            $item->nilai_akhir = $item->saldo_akhir * $hargaSatuan;
        }

        // Lempar semua data yang dibutuhkan View
        return compact('items', 'startDate', 'endDate', 'warehouseId', 'warehouse', 'warehouses', 'search');
    }



    // =========================================================================
    // 🔥 LAPORAN ANALISIS HARGA PEMBELIAN (MIN, MAX, AVG, LATEST) 🔥
    // =========================================================================
    public function priceAnalysis(\Illuminate\Http\Request $request)
    {
        $search = $request->input('search');

        // Ambil Item Inventory (Abaikan Aset Tetap)
        $query = \App\Models\Item::with('category')->whereHas('category', function($q) {
            $q->where('code', '!=', 'AST');
        });

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $items = $query->paginate(20)->withQueryString();

        foreach ($items as $item) {
            // 1. Ambil agregat harga dari riwayat PO (Termahal, Termurah, Rata-rata)
            $poStats = \Illuminate\Support\Facades\DB::table('purchase_order_items')
                ->selectRaw('
                    MAX(unit_price) as max_price,
                    MIN(unit_price) as min_price,
                    AVG(unit_price) as avg_price,
                    SUM(qty_ordered) as total_qty
                ')
                ->where('item_id', $item->id)
                ->first();

            // 2. Ambil harga pembelian paling akhir (Latest)
            $latestPO = \Illuminate\Support\Facades\DB::table('purchase_order_items')
                ->where('item_id', $item->id)
                ->orderBy('created_at', 'desc')
                ->first();

            // 3. Masukkan ke dalam objek item
            if ($latestPO) {
                $item->harga_termahal = $poStats->max_price;
                $item->harga_termurah = $poStats->min_price;
                $item->harga_rata = $poStats->avg_price;
                $item->harga_terakhir = $latestPO->unit_price;
                $item->total_dibeli = $poStats->total_qty;
            } else {
                // Fallback jika belum pernah ada PO
                $basePrice = $item->purchase_price ?? ($item->price ?? 0);
                $item->harga_termahal = $basePrice;
                $item->harga_termurah = $basePrice;
                $item->harga_rata = $basePrice;
                $item->harga_terakhir = $basePrice;
                $item->total_dibeli = 0;
            }
        }

        return view('inventory.price_analysis', compact('items', 'search'));
    }



    // =========================================================================
    // 🔥 LAPORAN RIWAYAT HARGA BELI (UPDATE FIELD VENDOR_ID) 🔥
    // =========================================================================
    public function purchaseHistory(\Illuminate\Http\Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        $search = $request->input('search');

        $start = \Carbon\Carbon::parse($startDate)->startOfDay();
        $end = \Carbon\Carbon::parse($endDate)->endOfDay();

        $query = \Illuminate\Support\Facades\DB::table('purchase_order_items as poi')
            ->join('items as i', 'poi.item_id', '=', 'i.id')
            ->join('purchase_orders as po', 'poi.purchase_order_id', '=', 'po.id')

            // 🔥 REVISI: Menggunakan vendor_id (Asumsi nama tabel adalah 'vendors')
            ->leftJoin('vendors as v', 'po.vendor_id', '=', 'v.id')

            ->select(
                'poi.id',
                'poi.created_at',
                'po.po_number',
                'v.name as supplier_name',
                'i.code as item_code',
                'i.name as item_name', // Nama dari Master Barang
                'poi.item_name as po_item_name', // 🔥 TAMBAHAN: Nama spesifik yang diketik saat PO
                'poi.qty_received',
                'poi.unit_price',
                'poi.subtotal'
            )
            ->where('poi.qty_received', '>', 0)
            ->whereBetween('poi.created_at', [$start, $end]);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('i.name', 'like', "%{$search}%")
                  ->orWhere('i.code', 'like', "%{$search}%")
                  ->orWhere('po.po_number', 'like', "%{$search}%")
                  ->orWhere('v.name', 'like', "%{$search}%"); // 🔥 Pencarian berdasarkan nama vendor (v.name)
            });
        }

        $histories = $query->orderBy('poi.created_at', 'desc')->paginate(20)->withQueryString();

        return view('inventory.purchase_history', compact('histories', 'startDate', 'endDate', 'search'));
    }




}
