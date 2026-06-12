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
    public function create()
    {
        $grs = GoodsReceipt::orderBy('received_date', 'desc')->limit(50)->get();
        return view('asset_capitalizations.create', compact('grs'));
    }

    // 2. AJAX: Ambil Rincian Item dari GR beserta Harga & Sisa Stoknya
    public function getGrItems($gr_id)
    {
        // 🔥 Tambahkan relasi 'items.purchaseOrderItem' agar kita bisa intip harga PO-nya!
        $gr = GoodsReceipt::with(['items.item.uom', 'items.purchaseOrderItem', 'warehouse'])->findOrFail($gr_id);
        $grDate = $gr->received_date; // Tanggal GR sebagai Tanggal Perolehan Default

        $items = [];
        foreach ($gr->items as $grItem) {
            $masterItem = $grItem->item;

            // 1. Ekstrak Konversi GR
            $grConvRate = 1;
            if (preg_match('/Isi\s*[:=]?\s*([0-9.]+)/i', $grItem->getRawOriginal('uom'), $matches)) {
                $grConvRate = (float) $matches[1];
            } elseif ($grItem->uom_id) {
                $uomDb = \App\Models\ItemUom::find($grItem->uom_id);
                if ($uomDb) $grConvRate = (float) $uomDb->conversion_qty;
            }

            // 2. Kalkulasi Harga Perolehan Dasar (Base Unit Price)
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
            // Jika beli 1 Dus (isi 10) Rp 10 Juta, Harga per Unit-nya jadi Rp 1 Juta
            $baseUnitPrice = $poConvFactor > 0 ? ($poPrice / $poConvFactor) : $poPrice;

            // 3. Sisa yang belum diretur
            $baseQtyReceived = ($grItem->qty_received - ($grItem->qty_returned ?? 0)) * $grConvRate;
            if ($baseQtyReceived <= 0) continue;

            $currentStock = InventoryStock::where('item_id', $masterItem->id)->sum('stock_qty');

            $availableSns = [];
            if (isset($masterItem->is_trackable) && $masterItem->is_trackable) {
                $availableSns = \DB::table('item_serials')
                    ->where('item_id', $masterItem->id)
                    ->where('goods_receipt_id', $gr->id)
                    ->where('status', 'AVAILABLE')
                    ->pluck('serial_number')
                    ->toArray();

                $currentStock = min($currentStock, count($availableSns));
            }

            $maxCapitalizable = min($baseQtyReceived, $currentStock);

            if ($maxCapitalizable > 0) {
                $items[] = [
                    'item_id' => $masterItem->id,
                    'item_code' => $masterItem->code,
                    'item_name' => $masterItem->name,
                    'base_uom' => optional($masterItem->uom)->name ?? 'Unit',
                    'gr_qty' => $baseQtyReceived,
                    'current_stock' => $currentStock,
                    'max_capitalizable' => floor($maxCapitalizable),
                    'available_sns' => $availableSns,

                    // 🔥 DATA BARU UNTUK AUTO-FILL FORM 🔥
                    'default_price' => round($baseUnitPrice, 2),
                    'default_date'  => date('Y-m-d', strtotime($grDate))
                ];
            }
        }

        return response()->json([
            'warehouse_id' => $gr->warehouse_id,
            'warehouse_name' => 'Gudang Global (Cari Otomatis)',
            'items' => $items
        ]);
    }

    // 3. Proses Simpan Pengakuan Aset
    public function store(Request $request)
    {
        $request->validate([
            'goods_receipt_id' => 'required|exists:goods_receipts,id',
            'items'            => 'required|array',
            'items.*.qty'      => 'required|numeric|min:0',

            // 🔥 VALIDASI ANTI-DUPLIKAT NOMOR AKUNTANSI 🔥
            'items.*.details.*.accounting_no' => 'nullable|string|distinct|unique:fixed_assets,accounting_asset_number',
        ], [
            'items.*.details.*.accounting_no.distinct' => 'GAGAL: Nomor Akuntansi (FA) yang Anda ketik ada yang kembar di dalam form ini.',
            'items.*.details.*.accounting_no.unique'   => 'GAGAL: Nomor Akuntansi (FA) tersebut sudah pernah dipakai oleh aset lain di database. Gunakan nomor yang berbeda.',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $gr = GoodsReceipt::findOrFail($request->goods_receipt_id);
                $statusAvailableId = Status::where('type', 'AST')->where('slug', 'available')->value('id') ?? 31;

                foreach ($request->items as $itemId => $data) {
                    $qtyToCapitalize = (int) ($data['qty'] ?? 0);
                    if ($qtyToCapitalize <= 0) continue;

                    $masterItem = Item::findOrFail($itemId);
                    $baseUomName = optional($masterItem->uom)->name ?? 'Unit';

                    $availableStocks = InventoryStock::where('item_id', $itemId)
                                        ->where('stock_qty', '>', 0)
                                        ->orderBy('created_at', 'asc')->lockForUpdate()->get();

                    $totalAvailable = $availableStocks->sum('stock_qty');
                    if ($qtyToCapitalize > $totalAvailable) {
                        throw new \Exception("Stok fisik {$masterItem->name} tidak cukup. Diminta: {$qtyToCapitalize}, Tersedia: {$totalAvailable}.");
                    }

                    $qtySisaPotong = $qtyToCapitalize;
                    $saldoTotalSaatIni = (float) $masterItem->current_stock;
                    $actualWarehouseId = $gr->warehouse_id ?? 1;

                    foreach ($availableStocks as $stockRow) {
                        if ($qtySisaPotong <= 0) break;
                        $potong = min($stockRow->stock_qty, $qtySisaPotong);
                        $actualWarehouseId = $stockRow->warehouse_id;

                        $balanceBefore = $saldoTotalSaatIni;
                        $balanceAfter = $balanceBefore - $potong;
                        $saldoTotalSaatIni = $balanceAfter;

                        $stockRow->decrement('stock_qty', $potong);
                        $qtySisaPotong -= $potong;

                        StockMutation::create([
                            'item_id'          => $masterItem->id,
                            'warehouse_id'     => $stockRow->warehouse_id,
                            'type'             => 'OUT',
                            'qty'              => $potong,
                            'balance_before'   => $balanceBefore,
                            'balance_after'    => $balanceAfter,
                            'reference_number' => $gr->gr_number,
                            'notes'            => "Dikonversi menjadi Aset Tetap (Capitalized)",
                            'created_by'       => auth()->id(),
                        ]);
                    }
                    $masterItem->update(['current_stock' => $saldoTotalSaatIni]);

                    $autoSns = [];
                    if (isset($masterItem->is_trackable) && $masterItem->is_trackable) {
                        $autoSns = \DB::table('item_serials')
                            ->where('item_id', $masterItem->id)
                            ->where('goods_receipt_id', $gr->id)
                            ->where('status', 'AVAILABLE')
                            ->limit($qtyToCapitalize)
                            ->pluck('serial_number')
                            ->toArray();

                        \DB::table('item_serials')
                            ->whereIn('serial_number', $autoSns)
                            ->update(['status' => 'CAPITALIZED', 'updated_at' => now()]);
                    }

                    $details = $data['details'] ?? [];
                    for ($i = 0; $i < $qtyToCapitalize; $i++) {
                        $detail = $details[$i] ?? [];

                        $year = date('Y'); $month = date('m');
                        $prefix = "AST/{$year}/{$month}/";
                        $lastAsset = FixedAsset::where('asset_number', 'like', "{$prefix}%")->orderBy('id', 'desc')->first();
                        $nextSeq = $lastAsset ? ((int) substr($lastAsset->asset_number, -4)) + 1 : 1;
                        $sysAssetNumber = $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

                        $accountingNo = $detail['accounting_no'] ?? null;
                        $specificName = $detail['specific_name'] ?? $masterItem->name;
                        $notes = $detail['notes'] ?? null;

                        $accValue = $detail['accounting_value'] ?? 0;
                        $usefulLife = $detail['useful_life'] ?? 0;
                        $acqDate = $detail['acquisition_date'] ?? date('Y-m-d');

                        $serialNumber = $detail['serial_number'] ?? null;
                        if (empty($serialNumber) && isset($autoSns[$i])) {
                            $serialNumber = $autoSns[$i];
                        }

                        // Menyimpan Data Aset (Tergantung struktur tabel Anda)
                        $newAsset = FixedAsset::create([
                            'item_id'                 => $masterItem->id,
                            'warehouse_id'            => $actualWarehouseId,
                            'status_id'               => $statusAvailableId,
                            'asset_number'            => $sysAssetNumber,
                            'accounting_asset_number' => $accountingNo,
                            'name'                    => $specificName,
                            'serial_number'           => $serialNumber,
                            'accounting_value'        => $accValue,
                            'useful_life_years'       => $usefulLife,
                            // Jika ada kolom purchase_date: 'purchase_date' => $acqDate,
                            'notes'                   => "Tgl Perolehan: {$acqDate} | Diakui dari GR: {$gr->gr_number} | Nilai: Rp." . number_format($accValue,0,',','.') . " | " . $notes,
                        ]);

                        FixedAssetHistory::create([
                            'fixed_asset_id' => $newAsset->id,
                            'status'         => 'Registered (Terdaftar)',
                            'notes'          => "Aset diregistrasi & dikapitalisasi dari Stok (Ref GR: {$gr->gr_number}).",
                            'created_by'     => auth()->id(),
                        ]);
                    }
                }
            });

            return redirect()->route('asset-capitalizations.create')->with('success', 'Luar biasa! Stok berhasil dikonversi menjadi Aset Tetap secara spesifik.');

        } catch (\Exception $e) {
            Log::error('Error Kapitalisasi Aset: ' . $e->getMessage());
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
