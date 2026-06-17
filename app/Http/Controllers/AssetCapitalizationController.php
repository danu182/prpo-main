<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\InventoryStock;
use App\Models\StockMutation;
use App\Models\FixedAsset;
use App\Models\FixedAssetHistory;
use App\Models\Status;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssetCapitalizationController extends Controller
{


    // =========================================================================
    // INDEX: Menampilkan Daftar Aset
    // =========================================================================
    public function index()
    {
        // Ambil data aset terbaru beserta relasi yang dibutuhkan (sesuaikan dengan nama relasi di model Anda)
        $assets = \App\Models\FixedAsset::with(['item', 'status'])
                    ->orderBy('created_at', 'desc')
                    ->paginate(10);

        return view('asset_capitalizations.index', compact('assets'));
    }

    // =========================================================================
    // SHOW: Menampilkan Detail 1 Aset
    // =========================================================================
    public function show($id)
    {
        // Cari aset berdasarkan ID
        $asset = \App\Models\FixedAsset::with(['item', 'status'])->findOrFail($id);

        return view('asset_capitalizations.show', compact('asset'));
    }




    public function create()
    {
        $grs = GoodsReceipt::orderBy('received_date', 'desc')->limit(50)->get();
        return view('asset_capitalizations.create', compact('grs'));
    }

    public function getGrItems($gr_id)
    {
        $gr = GoodsReceipt::with(['items.item.uom', 'items.purchaseOrderItem', 'warehouse'])->findOrFail($gr_id);
        $grDate = $gr->received_date;

        $items = [];
        foreach ($gr->items as $grItem) {
            $masterItem = $grItem->item;

            $grConvRate = 1;
            if (preg_match('/Isi\s*[:=]?\s*([0-9.]+)/i', $grItem->getRawOriginal('uom'), $matches)) {
                $grConvRate = (float) $matches[1];
            } elseif ($grItem->uom_id) {
                $uomDb = \App\Models\ItemUom::find($grItem->uom_id);
                if ($uomDb) $grConvRate = (float) $uomDb->conversion_qty;
            }

            $poItem = $grItem->purchaseOrderItem;
            $poPrice = $poItem ? (float) $poItem->unit_price : 0;
            $poConvFactor = 1;

            if ($poItem) {
                $rawPoUom = $poItem->getRawOriginal('uom') ?: 'Unit';
                if (preg_match('/Isi\s*[:=]?\s*([0-9.]+)/i', $rawPoUom, $matches)) {
                    $poConvFactor = (float) $matches[1];
                } elseif ($poItem->uom_id) {
                    $uomDb = \App\Models\ItemUom::find($poItem->uom_id);
                    if ($uomDb) $poConvFactor = (float) $uomDb->conversion_qty;
                }
            }
            $baseUnitPrice = $poConvFactor > 0 ? ($poPrice / $poConvFactor) : $poPrice;

            $baseQtyReceived = ($grItem->qty_received - ($grItem->qty_returned ?? 0)) * $grConvRate;
            if ($baseQtyReceived <= 0) continue;

            $currentStock = InventoryStock::where('item_id', $masterItem->id)->sum('stock_qty');

            // 🔥 LOGIKA RETROACTIVE: Hitung berapa yang SUDAH diakui sebagai aset dari GR ini 🔥
            $alreadyCapitalized = FixedAsset::where('goods_receipt_id', $gr->id)
                                            ->where('item_id', $masterItem->id)
                                            ->count();

            // Maksimal yang BISA diakui adalah: (Total Terima - Total Yang Sudah Jadi Aset)
            $maxCapitalizable = $baseQtyReceived - $alreadyCapitalized;

            $availableSns = [];
            if (isset($masterItem->is_trackable) && $masterItem->is_trackable) {
                // Tarik SN yang Available (Di Gudang) ATAU yang sudah di-Issued (Dipakai User)
                $availableSns = \DB::table('item_serials')
                    ->where('item_id', $masterItem->id)
                    ->where('goods_receipt_id', $gr->id)
                    ->whereNotIn('status', ['CAPITALIZED', 'RETURNED'])
                    ->pluck('serial_number')
                    ->toArray();
            }

            if ($maxCapitalizable > 0) {
                $defaultSpec = $poItem ? ($poItem->description ?? $poItem->specification ?? $poItem->notes ?? '') : '';

                $items[] = [
                    'item_id' => $masterItem->id,
                    'item_code' => $masterItem->code,
                    'item_name' => $masterItem->name,
                    'base_uom' => optional($masterItem->uom)->name ?? 'Unit',
                    'gr_qty' => $baseQtyReceived,
                    'current_stock' => $currentStock,
                    'max_capitalizable' => floor($maxCapitalizable),
                    'available_sns' => $availableSns,
                    'default_price' => round($baseUnitPrice, 2),
                    'default_date'  => date('Y-m-d', strtotime($grDate)),
                    'default_spec'  => $defaultSpec
                ];
            }
        }

        return response()->json([
            'warehouse_id' => $gr->warehouse_id,
            'warehouse_name' => 'Gudang Global (Cari Otomatis)',
            'items' => $items
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'goods_receipt_id' => 'required|exists:goods_receipts,id',
            'items'            => 'required|array',
            'items.*.qty'      => 'required|numeric|min:0',
            'items.*.details.*.accounting_no' => 'nullable|string|distinct|unique:fixed_assets,accounting_asset_number',
        ], [
            'items.*.details.*.accounting_no.distinct' => 'Nomor Akuntansi (FA) ada yang kembar di dalam form ini.',
            'items.*.details.*.accounting_no.unique'   => 'Nomor Akuntansi (FA) tersebut sudah dipakai oleh aset lain.',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $gr = GoodsReceipt::with(['purchaseOrder', 'po'])->findOrFail($request->goods_receipt_id);

                // Siapkan 2 Jenis Status Aset
                $statusAvailableId = Status::where('type', 'AST')->where('slug', 'available')->value('id') ?? 31;
                $statusInUseId     = Status::where('type', 'AST')->where('slug', 'in_use')->value('id') ?? 32;

                $poData = $gr->purchaseOrder ?? $gr->po;
                $currencyCode = optional($poData)->currency ?? 'IDR';
                $currencyDb = \DB::table('currencies')->where('code', $currencyCode)->first();
                $currencyId = $currencyDb ? $currencyDb->id : 1;
                $companyId  = optional($poData)->company_id ?? optional($poData)->bill_to_company_id ?? null;

                foreach ($request->items as $itemId => $data) {
                    $qtyToCapitalize = (int) ($data['qty'] ?? 0);
                    if ($qtyToCapitalize <= 0) continue;

                    $masterItem = Item::findOrFail($itemId);

                    // 1. Cek Stok Fisik yang Masih Ada di Gudang
                    $availableStocks = InventoryStock::where('item_id', $itemId)
                                        ->where('stock_qty', '>', 0)
                                        ->orderBy('created_at', 'asc')->lockForUpdate()->get();

                    $totalAvailable = $availableStocks->sum('stock_qty');

                    // 🔥 LOGIKA CROSSOVER (RETROACTIVE) 🔥
                    // Berapa unit yang potong stok, dan berapa yang langsung jadi "In Use"
                    $qtyFromStock = min($qtyToCapitalize, $totalAvailable);
                    $qtyRetroactive = $qtyToCapitalize - $qtyFromStock;

                    $actualWarehouseId = $gr->warehouse_id ?? 1;

                    // 2. Potong Stok Gudang HANYA untuk barang yang benar-benar masih ada
                    if ($qtyFromStock > 0) {
                        $qtySisaPotong = $qtyFromStock;
                        $saldoTotalSaatIni = (float) $masterItem->current_stock;

                        foreach ($availableStocks as $stockRow) {
                            if ($qtySisaPotong <= 0) break;
                            $potong = min($stockRow->stock_qty, $qtySisaPotong);
                            $actualWarehouseId = $stockRow->warehouse_id;

                            $saldoTotalSaatIni -= $potong;
                            $stockRow->decrement('stock_qty', $potong);
                            $qtySisaPotong -= $potong;

                            StockMutation::create([
                                'item_id'          => $masterItem->id,
                                'warehouse_id'     => $stockRow->warehouse_id,
                                'type'             => 'OUT',
                                'qty'              => $potong,
                                'balance_before'   => $saldoTotalSaatIni + $potong,
                                'balance_after'    => $saldoTotalSaatIni,
                                'reference_number' => $gr->gr_number,
                                'notes'            => "Kapitalisasi menjadi Aset Tetap",
                                'created_by'       => auth()->id(),
                            ]);
                        }
                        $masterItem->update(['current_stock' => $saldoTotalSaatIni]);
                    }

                    // 3. Suntik Serial Number
                    $autoSns = [];
                    if (isset($masterItem->is_trackable) && $masterItem->is_trackable) {
                        $autoSns = \DB::table('item_serials')
                            ->where('item_id', $masterItem->id)
                            ->where('goods_receipt_id', $gr->id)
                            ->whereNotIn('status', ['CAPITALIZED', 'RETURNED'])
                            ->limit($qtyToCapitalize)
                            ->pluck('serial_number')
                            ->toArray();

                        \DB::table('item_serials')
                            ->whereIn('serial_number', $autoSns)
                            ->update(['status' => 'CAPITALIZED', 'updated_at' => now()]);
                    }

                    // 4. Simpan ke Tabel `fixed_assets`
                    $details = $data['details'] ?? [];
                    for ($i = 0; $i < $qtyToCapitalize; $i++) {
                        $detail = $details[$i] ?? [];

                        // Tentukan apakah aset ini hasil telat input (Retroactive)
                        $isRetroactive = $i >= $qtyFromStock;
                        $currentAssetStatus = $isRetroactive ? $statusInUseId : $statusAvailableId;

                        // Nomor Auto Generate
                        $year = date('Y'); $month = date('m');
                        $prefix = "AST/{$year}/{$month}/";
                        $lastAsset = FixedAsset::where('asset_number', 'like', "{$prefix}%")->orderBy('id', 'desc')->first();
                        $nextSeq = $lastAsset ? ((int) substr($lastAsset->asset_number, -4)) + 1 : 1;
                        $sysAssetNumber = $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

                        // Ambil Data Form
                        $accountingNo = $detail['accounting_no'] ?? null;
                        $specificName = $detail['specific_name'] ?? $masterItem->name;
                        $acqDate      = $detail['acquisition_date'] ?? date('Y-m-d');
                        $accValue     = $detail['accounting_value'] ?? 0;
                        $usefulLife   = $detail['useful_life'] ?? '';
                        $specDetail   = $detail['notes'] ?? '';

                        $serialNumber = $detail['serial_number'] ?? null;
                        if (empty($serialNumber) && isset($autoSns[$i])) {
                            $serialNumber = $autoSns[$i];
                        }

                        // Catatan Tambahan Khusus Retroactive
                        $extraNote = "Diakui dari dokumen penerimaan: {$gr->gr_number}.";
                        if (!empty($usefulLife)) $extraNote .= " Estimasi Umur Ekonomis: {$usefulLife} Tahun.";
                        if ($isRetroactive) {
                            $extraNote .= " [RETROACTIVE: Diakui setelah barang didistribusikan / Goods Issue].";
                        }

                        $newAsset = FixedAsset::create([
                            'asset_number'            => $sysAssetNumber,
                            'item_id'                 => $masterItem->id,
                            'warehouse_id'            => $actualWarehouseId,
                            'company_id'              => $companyId,
                            'goods_receipt_id'        => $gr->id,
                            'name'                    => $specificName,
                            'serial_number'           => $serialNumber,
                            'accounting_asset_number' => $accountingNo,
                            'acquisition_date'        => $acqDate,
                            'purchase_price'          => $accValue,
                            'currency_id'             => $currencyId,
                            'spesifikasi_detail'      => $specDetail,
                            'status_id'               => $currentAssetStatus, // 🔥 Status In Use jika Retroactive!
                            'notes'                   => $extraNote,
                        ]);

                        $historyText = $isRetroactive ? "Aset diregistrasi secara Retroactive (Barang sudah dipakai user)." : "Aset diregistrasi dari Stok Gudang.";

                        FixedAssetHistory::create([
                            'fixed_asset_id' => $newAsset->id,
                            'status'         => 'Registered (Terdaftar)',
                            'notes'          => $historyText . " (Ref GR: {$gr->gr_number}).",
                            'created_by'     => auth()->id(),
                        ]);
                    }
                }
            });

            return redirect()->route('asset-capitalizations.create')->with('success', 'Luar biasa! Pengakuan Aset berhasil. Sistem telah menyesuaikan stok dan melacak aset yang sudah telanjur dikeluarkan.');

        } catch (\Exception $e) {
            Log::error('Error Kapitalisasi Aset: ' . $e->getMessage());
            return back()->withInput()->with('error', $e->getMessage());
        }
    }



    // =========================================================================
    // 3. VOID: Proses Pembatalan Pengakuan Aset (Backend Only)
    // =========================================================================
    public function voidAsset($id)
    {
        try {
            \DB::beginTransaction();

            $asset = \App\Models\FixedAsset::with('item')->findOrFail($id);

            // 1. SESUAIKAN ID STATUS AVAILABLE (ID = 30)
            if ($asset->status_id != 30) {
                throw new \Exception("GAGAL: Aset tidak bisa dibatalkan karena sedang digunakan atau sudah diproses.");
            }

            $masterItem = $asset->item;
            $balanceBefore = (float) $masterItem->current_stock;
            $balanceAfter = $balanceBefore + 1;

            // 2. KEMBALIKAN STOK FISIK
            $invStock = \App\Models\InventoryStock::where('item_id', $masterItem->id)
                            ->where('warehouse_id', $asset->warehouse_id)
                            ->first();

            if ($invStock) {
                $invStock->increment('stock_qty', 1);
            } else {
                \App\Models\InventoryStock::create([
                    'company_id'       => $asset->company_id,
                    'warehouse_id'     => $asset->warehouse_id,
                    'item_id'          => $masterItem->id,
                    'stock_qty'        => 1,
                    'reference_number' => $asset->asset_number . '-VOID',
                    'notes'            => 'Pengembalian dari Void Aset',
                ]);
            }

            $masterItem->update(['current_stock' => $balanceAfter]);

            // 3. CATAT MUTASI MASUK (Jika modelnya ada)
            if (class_exists(\App\Models\StockMutation::class)) {
                \App\Models\StockMutation::create([
                    'item_id'          => $masterItem->id,
                    'warehouse_id'     => $asset->warehouse_id,
                    'type'             => 'IN',
                    'qty'              => 1,
                    'balance_before'   => $balanceBefore,
                    'balance_after'    => $balanceAfter,
                    'reference_number' => $asset->asset_number,
                    'notes'            => "Pembatalan Pengakuan Aset (VOID).",
                    'created_by'       => auth()->id(),
                ]);
            }

            // 4. UBAH STATUS ASET MENJADI VOID
            // Ganti angka 43 di bawah ini dengan ID status VOID AST yang baru Anda buat di Langkah 2
            $statusVoidId = 43;

            $asset->update([
                'status_id' => $statusVoidId,
                'notes'     => $asset->notes . "\n[DIBATALKAN PADA " . date('d-m-Y H:i') . "]"
            ]);

            \DB::commit();
            return back()->with('success', "Aset {$asset->asset_number} berhasil dibatalkan dan stok dikembalikan.");

        } catch (\Exception $e) {
            \DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }


}
