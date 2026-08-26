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
    // 1. TAMPILKAN SALDO STOK (MESIN MATEMATIKA ISOLASI ASET)
    // ==========================================
    public function index(Request $request)
    {
        $search = $request->input('search');
        $warehouseId = $request->input('warehouse_id');

        $warehouses = \App\Models\Warehouse::orderBy('name')->get();

        $allItems = \App\Models\Item::where(function($q) {
            $q->whereNotIn('item_type_code', ['JSA', 'NST'])
              ->orWhereNull('item_type_code');
        })->orderBy('name')->get();

        $criticalStocks = \App\Models\InventoryStock::with(['item.uom', 'warehouse'])
            ->whereHas('item', function($q) {
                $q->whereNotIn('item_type_code', ['JSA', 'NST'])
                  ->orWhereNull('item_type_code');
            })
            ->whereNotNull('min_stock')
            ->where('min_stock', '>', 0)
            ->whereColumn('stock_qty', '<=', 'min_stock')
            ->get();

        $stocks = \App\Models\Item::query()
            ->select('items.*')
            ->with('uom')
            ->where(function($q) {
                $q->whereNotIn('item_type_code', ['JSA', 'NST'])
                  ->orWhereNull('item_type_code');
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // =========================================================================
        // 🔥 MESIN ANTI BENTROK: PISAHKAN PENGURANGAN ASET DARI STOK FISIK 🔥
        // =========================================================================
        $stocks->getCollection()->transform(function ($item) use ($warehouseId) {

            $mutQuery = \App\Models\StockMutation::where('item_id', $item->id);
            if (!empty($warehouseId)) $mutQuery->where('warehouse_id', $warehouseId);

            // 🔥 HANYA HITUNG MASUK DARI GR & SA (Abaikan Aset Kembali / RET)
            $masuk = (clone $mutQuery)->where('type', 'IN')
                        ->where('reference_number', 'not like', 'RET%')->sum('qty');

            // 🔥 HANYA HITUNG KELUAR DARI KAPITALISASI & USAGE (Abaikan Aset Diserahkan / GI-AST)
            $keluar = (clone $mutQuery)->where('type', '!=', 'IN')
                        ->where('reference_number', 'not like', 'GI-AST%')->sum('qty');

            $trueBulkStock = $masuk - $keluar;

            // Hitung Aset Tersedia (Siap dipakai)
            $assetStock = 0;
            if (class_exists(\App\Models\FixedAsset::class)) {
                $assetQuery = \App\Models\FixedAsset::where('item_id', $item->id)
                    ->whereHas('status', function($q) {
                        $q->where('slug', 'available');
                    });
                if (!empty($warehouseId)) {
                    $assetQuery->where('warehouse_id', $warehouseId);
                }
                $assetStock = $assetQuery->count();
            }

            // Silent fix database InventoryStock jika angkanya rusak
            if (empty($warehouseId)) {
                $wrongStocks = \App\Models\InventoryStock::where('item_id', $item->id)->get();
                foreach($wrongStocks as $ws) {
                    $whIn = \App\Models\StockMutation::where('item_id', $item->id)->where('warehouse_id', $ws->warehouse_id)->where('type', 'IN')->where('reference_number', 'not like', 'RET%')->sum('qty');
                    $whOut = \App\Models\StockMutation::where('item_id', $item->id)->where('warehouse_id', $ws->warehouse_id)->where('type', '!=', 'IN')->where('reference_number', 'not like', 'GI-AST%')->sum('qty');
                    $whTrueStock = $whIn - $whOut;

                    if ($ws->stock_qty != $whTrueStock) $ws->update(['stock_qty' => $whTrueStock]);
                }
                \App\Models\Item::where('id', $item->id)->update(['current_stock' => $trueBulkStock]);
            }

            $item->total_stock = $trueBulkStock + $assetStock;
            $item->real_bulk_stock = $trueBulkStock;
            $item->real_asset_stock = $assetStock;

            return $item;
        });

        return view('inventory.index', compact('stocks', 'warehouses', 'search', 'warehouseId', 'allItems', 'criticalStocks'));
    }

    // ==========================================
    // 2. PEMAKAIAN STOK (USAGE)
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

                $stock = InventoryStock::where('company_id', $userCompanyId)
                            ->where('item_id', $request->item_id)
                            ->where('warehouse_id', $request->warehouse_id)
                            ->lockForUpdate()
                            ->firstOrFail();

                // Validasi Murni Anti GI-AST / RET Bug
                $trueIn = \App\Models\StockMutation::where('item_id', $request->item_id)->where('warehouse_id', $request->warehouse_id)->where('type', 'IN')->where('reference_number', 'not like', 'RET%')->sum('qty');
                $trueOut = \App\Models\StockMutation::where('item_id', $request->item_id)->where('warehouse_id', $request->warehouse_id)->where('type', '!=', 'IN')->where('reference_number', 'not like', 'GI-AST%')->sum('qty');
                $trueQty = $trueIn - $trueOut;

                if ($trueQty < $request->qty) {
                    throw new \Exception("Stok di gudang ini tidak mencukupi. Sisa riil: " . $trueQty);
                }

                $stock->update(['stock_qty' => $trueQty - $request->qty]);

                $item = Item::lockForUpdate()->findOrFail($request->item_id);
                $item->update(['current_stock' => $item->current_stock - $request->qty]);

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

                StockMutation::create([
                    'item_id'          => $request->item_id,
                    'warehouse_id'     => $request->warehouse_id,
                    'type'             => 'OUT',
                    'qty'              => $request->qty,
                    'balance_before'   => $trueQty,
                    'balance_after'    => $trueQty - $request->qty,
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
    // 3. TAMPILKAN KARTU STOK (RIWAYAT & TRANSLATOR USER)
    // ==========================================
    public function show($id, Request $request)
    {
        $warehouseId = $request->input('warehouse_id');
        $item = \App\Models\Item::with('uom')->where('item_type_code', 'STK')->findOrFail($id);

        $mutQuery = \App\Models\StockMutation::where('item_id', $id);
        if ($warehouseId) $mutQuery->where('warehouse_id', $warehouseId);

        // 🔥 HITUNG SALDO UNTUK KOTAK BIRU (HANYA FISIK) 🔥
        $masuk = (clone $mutQuery)->where('type', 'IN')->where('reference_number', 'not like', 'RET%')->sum('qty');
        $keluarFisik = (clone $mutQuery)->where('type', '!=', 'IN')->where('reference_number', 'not like', 'GI-AST%')->sum('qty');
        $bulkStock = $masuk - $keluarFisik;

        $assetStock = \App\Models\FixedAsset::where('item_id', $id)
            ->whereHas('status', function($q) { $q->where('slug', 'available'); })
            ->when($warehouseId, function($q) use ($warehouseId) { $q->where('warehouse_id', $warehouseId); })->count();

        $currentStock = $bulkStock + $assetStock;

        // Tarik SEMUA riwayat mutasi untuk ditampilkan di tabel
        $mutations = \App\Models\StockMutation::with('warehouse')
            ->where('item_id', $id)
            ->when($warehouseId, function($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        // 🔥 LOGIKA SALDO BERJALAN (TOTAL STOK: FISIK + ASET) 🔥
        if ($mutations->count() > 0) {
            $oldestMutationOnPage = $mutations->last();

            // Total IN masa lalu (Termasuk GR, SA, dan Aset Kembali/RET)
            $pastIn = \App\Models\StockMutation::where('item_id', $id)
                ->when($warehouseId, function($q) use ($warehouseId) { $q->where('warehouse_id', $warehouseId); })
                ->where(function($q) use ($oldestMutationOnPage) {
                    $q->where('created_at', '<', $oldestMutationOnPage->created_at)
                      ->orWhere(function($q2) use ($oldestMutationOnPage) {
                          $q2->where('created_at', '=', $oldestMutationOnPage->created_at)->where('id', '<', $oldestMutationOnPage->id);
                      });
                })->where('type', 'IN')->sum('qty');

            // Total OUT masa lalu (HANYA Usage dan Aset Keluar/GI-AST. Kapitalisasi ABAIKAN karena hanya pindah wadah)
            $pastOut = \App\Models\StockMutation::where('item_id', $id)
                ->when($warehouseId, function($q) use ($warehouseId) { $q->where('warehouse_id', $warehouseId); })
                ->where(function($q) use ($oldestMutationOnPage) {
                    $q->where('created_at', '<', $oldestMutationOnPage->created_at)
                      ->orWhere(function($q2) use ($oldestMutationOnPage) {
                          $q2->where('created_at', '=', $oldestMutationOnPage->created_at)->where('id', '<', $oldestMutationOnPage->id);
                      });
                })
                ->where('type', '!=', 'IN')
                ->where('notes', 'not like', '%[CAPITALIZE]%')
                ->where('notes', 'not like', '%Kapitalisasi%')
                ->sum('qty');

            $runningBalance = $pastIn - $pastOut;

            $reversed = $mutations->reverse();
            foreach ($reversed as $mut) {
                $isCapitalize = str_contains($mut->notes, '[CAPITALIZE]') || str_contains(strtolower($mut->notes), 'kapitalisasi');

                if ($isCapitalize) {
                    // Jika Kapitalisasi, Total Saldo tetap sama, tidak dipotong
                    $mut->dynamic_balance = $runningBalance;
                } else {
                    if ($mut->type === 'IN') {
                        $runningBalance += $mut->qty;
                    } else {
                        // Handover & Usage normal memotong saldo
                        $runningBalance -= $mut->qty;
                    }
                    $mut->dynamic_balance = $runningBalance;
                }

                // =========================================================
                // 🔥 TRANSLATOR OTOMATIS: UBAH USER ID MENJADI NAMA USER 🔥
                // =========================================================
                if (preg_match('/User ID:\s*(\d+)/i', $mut->notes, $matches)) {
                    $userId = $matches[1];
                    $userName = \Illuminate\Support\Facades\DB::table('users')->where('id', $userId)->value('name');
                    if ($userName) {
                        // Timpa teks di memori layar (tidak mengubah database)
                        $mut->notes = preg_replace('/User ID:\s*\d+/i', $userName, $mut->notes);
                    }
                }
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

                $trueIn = \App\Models\StockMutation::where('item_id', $request->item_id)->where('warehouse_id', $request->warehouse_id)->where('type', 'IN')->where('reference_number', 'not like', 'RET%')->sum('qty');
                $trueOut = \App\Models\StockMutation::where('item_id', $request->item_id)->where('warehouse_id', $request->warehouse_id)->where('type', '!=', 'IN')->where('reference_number', 'not like', 'GI-AST%')->sum('qty');
                $trueQty = $trueIn - $trueOut;

                StockMutation::create([
                    'item_id'          => $request->item_id,
                    'warehouse_id'     => $request->warehouse_id,
                    'type'             => 'IN',
                    'qty'              => $request->qty,
                    'balance_before'   => $trueQty,
                    'balance_after'    => $trueQty + $request->qty,
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

            $allItems = \App\Models\Item::select('id', 'code', 'name', 'item_type_code')
                ->get()->keyBy(function($item) { return strtolower(trim($item->code)); });

            $warehouses = \App\Models\Warehouse::pluck('id', 'name')->mapWithKeys(function ($id, $name) { return [strtolower(trim($name)) => $id]; })->toArray();
            $validCurrencies = \App\Models\Currency::where('is_active', 1)->pluck('id', 'code')->mapWithKeys(function ($id, $code) { return [strtoupper(trim($code)) => $id]; })->toArray();

            foreach ($rows as $index => $row) {
                if (empty($row['kode_barang']) && empty($row['jumlah_qty'])) continue;

                $isRowValid = true;
                $errorMessages = [];
                $realName = '-';

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

        $allItems = \App\Models\Item::select('id', 'code', 'item_type_code')->get()->keyBy(function($item) { return strtolower(trim($item->code)); });
        $warehouses = \App\Models\Warehouse::pluck('id', 'name')->mapWithKeys(function ($id, $name) { return [strtolower(trim($name)) => $id]; })->toArray();
        $validCurrencies = \App\Models\Currency::where('is_active', 1)->pluck('id', 'code')->mapWithKeys(function ($id, $code) { return [strtoupper(trim($code)) => $id]; })->toArray();

        $errorData = [];

        foreach ($rows as $row) {
            if (empty($row['kode_barang']) && empty($row['jumlah_qty'])) continue;

            $isRowValid = true;
            $errorMessages = [];

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

    public function capitalizeForm($slug)
    {
        $item = \App\Models\Item::with('uom')->where('code', $slug)->firstOrFail();
        $warehouses = \App\Models\Warehouse::orderBy('name')->get();

        foreach ($warehouses as $wh) {
            $totalPhysical = \App\Models\StockMutation::where('item_id', $item->id)
                ->where('warehouse_id', $wh->id)
                ->where('type', 'IN')
                ->where('reference_number', 'not like', 'RET%')
                ->selectRaw('COALESCE(SUM(qty), 0) as total')
                ->first()->total ?? 0;

            $totalOutPhysical = \App\Models\StockMutation::where('item_id', $item->id)
                ->where('warehouse_id', $wh->id)
                ->where('type', '!=', 'IN')
                ->where('reference_number', 'not like', 'GI-AST%')
                ->selectRaw('COALESCE(SUM(qty), 0) as total')
                ->first()->total ?? 0;

            $availableRegular = $totalPhysical - $totalOutPhysical;
            $wh->available_regular_stock = max(0, $availableRegular);
        }

        return view('inventory.capitalize', compact('item', 'warehouses'));
    }

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

                $trueIn = \App\Models\StockMutation::where('item_id', $item->id)->where('warehouse_id', $request->warehouse_id)->where('type', 'IN')->where('reference_number', 'not like', 'RET%')->sum('qty');
                $trueOut = \App\Models\StockMutation::where('item_id', $item->id)->where('warehouse_id', $request->warehouse_id)->where('type', '!=', 'IN')->where('reference_number', 'not like', 'GI-AST%')->sum('qty');
                $availableRegular = $trueIn - $trueOut;

                if ($qtyToConvert > $availableRegular) {
                    throw new \Exception("Gagal! Kuantitas konversi ({$qtyToConvert}) melebihi sisa stok biasa yang tersedia ({$availableRegular}).");
                }

                $statusAvailable = \App\Models\Status::where('type', 'AST')->where('slug', 'available')->first();

                $yearMonth = date('Y/m');
                $lastAsset = \App\Models\FixedAsset::where('asset_number', 'like', "AST/{$yearMonth}/%")->orderBy('id', 'desc')->lockForUpdate()->first();
                $nextSeq = $lastAsset ? ((int) substr($lastAsset->asset_number, -4)) + 1 : 1;

                for ($i = 0; $i < $qtyToConvert; $i++) {
                    $sysAssetNumber = "AST/{$yearMonth}/" . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

                    $accAssetNum = trim($request->acc_asset_number[$i]);
                    $currentSn   = trim($request->sn[$i]);
                    $currentSpec = trim($request->specs[$i] ?? '');
                    $currentNote = trim($request->notes[$i] ?? '');

                    \App\Models\FixedAsset::create([
                        'asset_number'             => $sysAssetNumber,
                        'accounting_asset_number'  => $accAssetNum,
                        'serial_number'            => $currentSn,
                        'spesifikasi_detail'       => $currentSpec,
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
    // 🔥 LAPORAN MUTASI & VALUASI 🔥
    // =========================================================================
    public function stockAsOf(\Illuminate\Http\Request $request)
    {
        $data = $this->calculateStockAsOfData($request, false);
        return view('inventory.stock_as_of', $data);
    }

    public function printStockAsOf(\Illuminate\Http\Request $request)
    {
        $data = $this->calculateStockAsOfData($request, true);
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

    private function calculateStockAsOfData(\Illuminate\Http\Request $request, $isPrint = false)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        $warehouseId = $request->input('warehouse_id');
        $search = $request->input('search');

        $start = \Carbon\Carbon::parse($startDate)->startOfDay();
        $end = \Carbon\Carbon::parse($endDate)->endOfDay();

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

            // Valuation hanya menghitung Stok Laporan Fisik Murni (Abaikan GI-AST & RET)
            $inBefore = (clone $mutations)->where('type', 'IN')->where('reference_number', 'not like', 'RET%')->where('created_at', '<', $start)->sum('qty');
            $outBefore = (clone $mutations)->where('type', '!=', 'IN')->where('reference_number', 'not like', 'GI-AST%')->where('created_at', '<', $start)->sum('qty');
            $item->saldo_awal = $inBefore - $outBefore;

            $item->mutasi_in = (clone $mutations)->where('type', 'IN')->where('reference_number', 'not like', 'RET%')->whereBetween('created_at', [$start, $end])->sum('qty');
            $item->mutasi_out = (clone $mutations)->where('type', '!=', 'IN')->where('reference_number', 'not like', 'GI-AST%')->whereBetween('created_at', [$start, $end])->sum('qty');

            $item->saldo_akhir = $item->saldo_awal + $item->mutasi_in - $item->mutasi_out;

            $latestPOItem = \Illuminate\Support\Facades\DB::table('purchase_order_items')
                                ->where('item_id', $item->id)
                                ->orderBy('created_at', 'desc')
                                ->first();

            $hargaSatuan = $latestPOItem ? $latestPOItem->unit_price : ($item->purchase_price ?? ($item->price ?? 0));

            $item->harga_satuan = $hargaSatuan;
            $item->nilai_awal = $item->saldo_awal * $hargaSatuan;
            $item->nilai_in = $item->mutasi_in * $hargaSatuan;
            $item->nilai_out = $item->mutasi_out * $hargaSatuan;
            $item->nilai_akhir = $item->saldo_akhir * $hargaSatuan;
        }

        return compact('items', 'startDate', 'endDate', 'warehouseId', 'warehouse', 'warehouses', 'search');
    }

    public function priceAnalysis(\Illuminate\Http\Request $request)
    {
        $search = $request->input('search');

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
            $poStats = \Illuminate\Support\Facades\DB::table('purchase_order_items')
                ->selectRaw('
                    MAX(unit_price) as max_price,
                    MIN(unit_price) as min_price,
                    AVG(unit_price) as avg_price,
                    SUM(qty_ordered) as total_qty
                ')
                ->where('item_id', $item->id)
                ->first();

            $latestPO = \Illuminate\Support\Facades\DB::table('purchase_order_items')
                ->where('item_id', $item->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($latestPO) {
                $item->harga_termahal = $poStats->max_price;
                $item->harga_termurah = $poStats->min_price;
                $item->harga_rata = $poStats->avg_price;
                $item->harga_terakhir = $latestPO->unit_price;
                $item->total_dibeli = $poStats->total_qty;
            } else {
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
            ->leftJoin('vendors as v', 'po.vendor_id', '=', 'v.id')
            ->select(
                'poi.id',
                'poi.created_at',
                'po.po_number',
                'v.name as supplier_name',
                'i.code as item_code',
                'i.name as item_name',
                'poi.item_name as po_item_name',
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
                  ->orWhere('v.name', 'like', "%{$search}%");
            });
        }

        $histories = $query->orderBy('poi.created_at', 'desc')->paginate(20)->withQueryString();

        return view('inventory.purchase_history', compact('histories', 'startDate', 'endDate', 'search'));
    }

    public function smartRestock(\Illuminate\Http\Request $request)
    {
        $warehouseId = $request->input('warehouse_id');

        $pendingPrQtys = \Illuminate\Support\Facades\DB::table('purchase_request_items')
            ->join('purchase_requests', 'purchase_request_items.purchase_request_id', '=', 'purchase_requests.id')
            ->leftJoin('statuses', 'purchase_requests.status_id', '=', 'statuses.id')
            ->whereNotIn('statuses.slug', [
                'rejected', 'ditolak',
                'canceled', 'cancelled', 'batal', 'dibatalkan',
                'completed', 'selesai'
            ])
            ->select('item_id', \Illuminate\Support\Facades\DB::raw('SUM(qty) as total_pending'))
            ->groupBy('item_id')
            ->pluck('total_pending', 'item_id');

        $candidatesQuery = \App\Models\InventoryStock::with(['item.uom', 'warehouse'])
            ->whereHas('item', function($q) {
                $q->whereNotIn('item_type_code', ['JSA', 'NST'])->orWhereNull('item_type_code');
            })
            ->whereNotNull('min_stock')
            ->where('min_stock', '>', 0);

        if (!empty($warehouseId)) {
            $candidatesQuery->where('warehouse_id', $warehouseId);
        }

        $candidates = $candidatesQuery->get();
        $criticalItems = collect();

        foreach ($candidates as $stock) {
            $trueIn = \App\Models\StockMutation::where('item_id', $stock->item_id)->where('warehouse_id', $stock->warehouse_id)->where('type', 'IN')->where('reference_number', 'not like', 'RET%')->sum('qty');
            $trueOut = \App\Models\StockMutation::where('item_id', $stock->item_id)->where('warehouse_id', $stock->warehouse_id)->where('type', '!=', 'IN')->where('reference_number', 'not like', 'GI-AST%')->sum('qty');
            $trueQty = $trueIn - $trueOut;

            $minQty = (float)$stock->min_stock;
            $pendingQty = (float)$pendingPrQtys->get($stock->item_id, 0);
            $virtualStock = $trueQty + $pendingQty;

            if ($virtualStock <= $minQty) {
                $stock->stock_qty = $trueQty;
                $stock->pending_qty = $pendingQty;
                $criticalItems->push($stock);
            }
        }

        $criticalItems = $criticalItems->sortBy(function($stock) {
            return optional($stock->warehouse)->name . '-' . optional($stock->item)->name;
        })->values();

        $companies = \App\Models\Company::orderBy('name')->get();
        $warehouses = \App\Models\Warehouse::orderBy('name')->get();

        return view('inventory.smart_restock', compact('criticalItems', 'companies', 'warehouses', 'warehouseId'));
    }

    public function generateMassPr(\Illuminate\Http\Request $request)
    {
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
                $masterItem = \App\Models\Item::find($data['item_id']);
                if (!$masterItem) continue;

                $alokasiTeks = "Rincian Alokasi:\n- " . $data['total_qty'] . " Pcs dialokasikan untuk " . $data['warehouse'];

                \Illuminate\Support\Facades\DB::table('purchase_request_items')->insert([
                    'purchase_request_id' => $pr->id,
                    'item_id'             => $masterItem->id,
                    'item_name'           => $masterItem->name,
                    'qty'                 => $data['total_qty'],
                    'uom_id'              => $masterItem->uom_id ?? null,
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

    private function generatePrNumber($companyId)
    {
        $company = \App\Models\Company::find($companyId);
        $companyCode = $company ? strtoupper($company->code ?? 'PT') : 'PT';
        $dateStr = date('Ymd');
        $prefix = "PR-{$companyCode}-{$dateStr}-";

        $latestPr = \App\Models\PurchaseRequest::where('pr_number', 'like', $prefix . '%')->orderBy('id', 'desc')->lockForUpdate()->first();

        $newSequence = 1;
        if ($latestPr && $latestPr->pr_number) {
            $parts = explode('-', $latestPr->pr_number);
            $newSequence = ((int) end($parts)) + 1;
        }

        return $prefix . str_pad($newSequence, 4, '0', STR_PAD_LEFT);
    }

    public function getStockLimits($itemId)
    {
        $item = \App\Models\Item::findOrFail($itemId);
        $warehouses = \App\Models\Warehouse::orderBy('name')->get();

        $existingStocks = \App\Models\InventoryStock::where('item_id', $item->id)->get()->keyBy('warehouse_id');

        $stockData = [];
        foreach ($warehouses as $warehouse) {
            $stock = $existingStocks->get($warehouse->id);

            $trueIn = \App\Models\StockMutation::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->where('type', 'IN')->where('reference_number', 'not like', 'RET%')->sum('qty');
            $trueOut = \App\Models\StockMutation::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->where('type', '!=', 'IN')->where('reference_number', 'not like', 'GI-AST%')->sum('qty');
            $trueQty = $trueIn - $trueOut;

            $stockData[] = [
                'warehouse_id' => $warehouse->id,
                'warehouse'    => $warehouse->name,
                'current_qty'  => $trueQty,
                'min_stock'    => $stock ? (float)$stock->min_stock : 0,
                'max_stock'    => $stock ? (float)$stock->max_stock : 0,
            ];
        }

        return response()->json([
            'item_name' => $item->name,
            'uom'       => $item->unit ?? 'PCS',
            'stocks'    => $stockData
        ]);
    }

    public function updateStockLimits(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'item_id'               => 'required|exists:items,id',
            'limits'                => 'required|array',
            'limits.*.warehouse_id' => 'required|exists:warehouses,id',
            'limits.*.min_stock'    => 'nullable|numeric|min:0',
            'limits.*.max_stock'    => 'nullable|numeric|min:0',
        ]);

        $companyId = auth()->user()->company_id ?? 1;

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            foreach ($request->limits as $limit) {
                \App\Models\InventoryStock::updateOrCreate(
                    [
                        'item_id'      => $request->item_id,
                        'warehouse_id' => $limit['warehouse_id']
                    ],
                    [
                        'min_stock'    => $limit['min_stock'] ?? 0,
                        'max_stock'    => $limit['max_stock'] ?? 0,
                        'company_id'   => $companyId,
                    ]
                );
            }
            \Illuminate\Support\Facades\DB::commit();
            return back()->with('success', 'Batas Minimum dan Maksimum Stok per Gudang berhasil diperbarui!');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollback();
            return back()->with('error', 'Gagal memperbarui batas stok: ' . $e->getMessage());
        }
    }
}
