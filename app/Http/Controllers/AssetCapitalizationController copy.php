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
    public function index()
    {
        $assets = FixedAsset::with(['item', 'status'])->orderBy('created_at', 'desc')->paginate(10);
        return view('asset_capitalizations.index', compact('assets'));
    }

    public function show($id)
    {
        $asset = FixedAsset::with(['item', 'status'])->findOrFail($id);
        return view('asset_capitalizations.show', compact('asset'));
    }

    public function create()
    {
        $grs = GoodsReceipt::orderBy('received_date', 'desc')->limit(50)->get();
        return view('asset_capitalizations.create', compact('grs'));
    }

    public function getGrItems($gr_id)
    {
        // =========================================================================
        // 🔥 LOGIKA BERTINGKAT SESUAI REQUEST KOMANDAN (GR -> PO -> PO ITEMS) 🔥
        // =========================================================================
        $gr = GoodsReceipt::with([
            'items.item.uom',
            'warehouse',
            'purchaseOrder.items' // Kita load sekalian semua isi PO-nya!
        ])->findOrFail($gr_id);


        dd([
            '1_ID_PO_YANG_NYANGKUT_DI_GR' => $gr->purchase_order_id,
            '2_DATA_HEADER_PO'            => $gr->purchaseOrder ? $gr->purchaseOrder->toArray() : 'KOSONG / TIDAK ADA PO',
            '3_DATA_ITEM_DI_DALAM_PO'     => $gr->purchaseOrder ? $gr->purchaseOrder->items->toArray() : 'KOSONG',
            '4_DATA_ITEM_DI_DALAM_GR'     => $gr->items->toArray(),
        ]);




        $grDate = $gr->received_date;
        $items = [];

        foreach ($gr->items as $grItem) {
            $masterItem = $grItem->item;
            if (!$masterItem) continue;

            $masterName = $masterItem->name ?? '-';

            // 1. CARI PO ITEM SECARA BERTINGKAT
            $poItem = null;
            $poData = $gr->purchaseOrder;

            if ($poData && $poData->items) {
                // Cari item di PO yang cocok dengan item di GR
                $poItem = $poData->items->where('item_id', $masterItem->id)->first();

                // JIKA GAGAL (Mungkin ID beda saat ganti varian),
                // Kita "Paksa" ambil baris pertama dari PO tersebut!
                if (!$poItem) {
                    $poItem = $poData->items->first();
                }
            }

            // 2. HITUNG HARGA PEROLEHAN ASET (ACQUISITION COST)
            $specificName = $masterName;
            $netUnitPrice = 0;

            if ($poItem) {
                // Tangkap Nama Alias
                $specificName = $poItem->item_name ?? $masterName;

                // Hitung Harga Per Unit Bersih (Subtotal / Qty Pesan)
                $qtyOrdered = (float) ($poItem->qty_ordered ?? $poItem->qty ?? 1);
                if ($qtyOrdered <= 0) $qtyOrdered = 1; // Cegah error pembagian 0

                $subtotalBaris = (float) ($poItem->subtotal ?? 0);
                $unitPriceAsli = (float) ($poItem->unit_price ?? 0);
                $discountBaris = (float) ($poItem->discount_amount ?? 0);

                if ($subtotalBaris > 0) {
                    // Jika ada subtotal (misal 220 Jt), bagi dengan Qty (10) = 22 Juta!
                    $netUnitPrice = $subtotalBaris / $qtyOrdered;
                } else {
                    // Kalau subtotal kosong, pakai harga satuan - diskon
                    $netUnitPrice = $unitPriceAsli - $discountBaris;
                }
            } else {
                // Jika dokumen ini benar-benar tidak punya PO (Direct GR), baru pakai harga Master
                $netUnitPrice = (float) ($masterItem->purchase_price ?? 0);
            }

            // 3. KALKULASI SISA STOK FISIK YANG BISA DIAKUI
            $grConvRate = 1;
            if ($grItem->uom_id) {
                $uomDb = DB::table('item_uoms')->where('id', $grItem->uom_id)->first();
                if ($uomDb) $grConvRate = (float) $uomDb->conversion_qty;
            }

            $baseQtyReceived = ((float)$grItem->qty_received - (float)($grItem->qty_returned ?? 0)) * $grConvRate;
            if ($baseQtyReceived <= 0) continue;

            $currentStock = InventoryStock::where('item_id', $masterItem->id)->sum('stock_qty');

            $alreadyCapitalized = FixedAsset::where('goods_receipt_id', $gr->id)
                                            ->where('item_id', $masterItem->id)
                                            ->count();

            $maxCapitalizable = $baseQtyReceived - $alreadyCapitalized;

            // 4. AMBIL SERIAL NUMBER
            $availableSns = [];
            if (isset($masterItem->is_trackable) && $masterItem->is_trackable) {
                $availableSns = DB::table('item_serials')
                    ->where('item_id', $masterItem->id)
                    ->where('goods_receipt_id', $gr->id)
                    ->whereNotIn('status', ['CAPITALIZED', 'RETURNED'])
                    ->pluck('serial_number')
                    ->toArray();
            }

            if ($maxCapitalizable > 0) {
                $defaultSpec = $poItem ? ($poItem->description ?? '') : '';

                $items[] = [
                    'item_id'           => $masterItem->id,
                    'item_code'         => $masterItem->code,
                    'item_name'         => $specificName, // Motor Beat Street
                    'master_name'       => $masterName,
                    'specific_name'     => $specificName,
                    'base_uom'          => optional($masterItem->uom)->name ?? 'Unit',
                    'gr_qty'            => $baseQtyReceived,
                    'current_stock'     => $currentStock,
                    'max_capitalizable' => floor($maxCapitalizable),
                    'available_sns'     => $availableSns,
                    'default_price'     => round($netUnitPrice, 2), // Pasti Rp 22.000.000!
                    'default_date'      => date('Y-m-d', strtotime($grDate)),
                    'default_spec'      => $defaultSpec
                ];
            }
        }

        return response()->json([
            'warehouse_id'   => $gr->warehouse_id,
            'warehouse_name' => optional($gr->warehouse)->name ?? 'Gudang Global',
            'items'          => $items
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'goods_receipt_id' => 'required|exists:goods_receipts,id',
            'items'            => 'required|array',
            'items.*.qty'      => 'required|numeric|min:0',
            'items.*.details.*.accounting_no' => 'nullable|string|distinct|unique:fixed_assets,accounting_asset_number',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $gr = GoodsReceipt::with(['purchaseOrder', 'po'])->findOrFail($request->goods_receipt_id);
                $statusAvailableId = Status::where('type', 'AST')->where('slug', 'available')->value('id') ?? 31;
                $statusInUseId     = Status::where('type', 'AST')->where('slug', 'in_use')->value('id') ?? 32;

                $poData = $gr->purchaseOrder ?? $gr->po;
                $currencyCode = optional($poData)->currency ?? 'IDR';
                $currencyDb = DB::table('currencies')->where('code', $currencyCode)->first();
                $currencyId = $currencyDb ? $currencyDb->id : 1;
                $companyId  = optional($poData)->company_id ?? optional($poData)->bill_to_company_id ?? null;

                foreach ($request->items as $itemId => $data) {
                    $qtyToCapitalize = (int) ($data['qty'] ?? 0);
                    if ($qtyToCapitalize <= 0) continue;

                    $masterItem = Item::findOrFail($itemId);
                    $availableStocks = InventoryStock::where('item_id', $itemId)
                                        ->where('stock_qty', '>', 0)
                                        ->orderBy('created_at', 'asc')->lockForUpdate()->get();

                    $totalAvailable = $availableStocks->sum('stock_qty');
                    $qtyFromStock = min($qtyToCapitalize, $totalAvailable);
                    $actualWarehouseId = $gr->warehouse_id ?? 1;

                    if ($qtyFromStock > 0) {
                        $qtySisaPotong = $qtyFromStock;
                        $saldoTotalSaatIni = (float) $masterItem->current_stock;

                        foreach ($availableStocks as $stockRow) {
                            if ($qtySisaPotong <= 0) break;
                            $potong = min($stockRow->stock_qty, $qtySisaPotong);
                            $actualWarehouseId = $stockRow->warehouse_id;

                            $stockRow->decrement('stock_qty', $potong);
                            $qtySisaPotong -= $potong;

                            StockMutation::create([
                                'item_id'          => $masterItem->id,
                                'warehouse_id'     => $stockRow->warehouse_id,
                                'type'             => 'CAPITALIZE',
                                'qty'              => $potong,
                                'balance_before'   => $saldoTotalSaatIni,
                                'balance_after'    => $saldoTotalSaatIni,
                                'reference_number' => $gr->gr_number,
                                'notes'            => "Kapitalisasi menjadi Aset Tetap",
                                'created_by'       => auth()->id(),
                            ]);
                        }
                    }

                    $autoSns = [];
                    if (isset($masterItem->is_trackable) && $masterItem->is_trackable) {
                        $autoSns = DB::table('item_serials')
                            ->where('item_id', $masterItem->id)
                            ->where('goods_receipt_id', $gr->id)
                            ->whereNotIn('status', ['CAPITALIZED', 'RETURNED'])
                            ->limit($qtyToCapitalize)
                            ->pluck('serial_number')
                            ->toArray();

                        DB::table('item_serials')->whereIn('serial_number', $autoSns)->update(['status' => 'CAPITALIZED', 'updated_at' => now()]);
                    }

                    $details = $data['details'] ?? [];
                    for ($i = 0; $i < $qtyToCapitalize; $i++) {
                        $detail = $details[$i] ?? [];
                        $isRetroactive = $i >= $qtyFromStock;
                        $currentAssetStatus = $isRetroactive ? $statusInUseId : $statusAvailableId;

                        $year = date('Y'); $month = date('m');
                        $prefix = "AST/{$year}/{$month}/";
                        $lastAsset = FixedAsset::where('asset_number', 'like', "{$prefix}%")->orderBy('id', 'desc')->first();
                        $nextSeq = $lastAsset ? ((int) substr($lastAsset->asset_number, -4)) + 1 : 1;
                        $sysAssetNumber = $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

                        $serialNumber = $detail['serial_number'] ?? null;
                        if (empty($serialNumber) && isset($autoSns[$i])) {
                            $serialNumber = $autoSns[$i];
                        }

                        FixedAsset::create([
                            'asset_number'            => $sysAssetNumber,
                            'item_id'                 => $masterItem->id,
                            'warehouse_id'            => $actualWarehouseId,
                            'company_id'              => $companyId,
                            'goods_receipt_id'        => $gr->id,
                            'name'                    => $detail['specific_name'] ?? $masterItem->name,
                            'serial_number'           => $serialNumber,
                            'accounting_asset_number' => $detail['accounting_no'] ?? null,
                            'acquisition_date'        => $detail['acquisition_date'] ?? date('Y-m-d'),
                            'purchase_price'          => $detail['accounting_value'] ?? 0,
                            'currency_id'             => $currencyId,
                            'spesifikasi_detail'      => $detail['notes'] ?? '',
                            'status_id'               => $currentAssetStatus,
                            'notes'                   => "Diakui dari dokumen penerimaan: {$gr->gr_number}.",
                        ]);

                        FixedAssetHistory::create([
                            'fixed_asset_id' => FixedAsset::orderBy('id', 'desc')->value('id'),
                            'status'         => 'Registered (Terdaftar)',
                            'notes'          => "Aset diregistrasi dari Stok Gudang (Ref GR: {$gr->gr_number}).",
                            'created_by'     => auth()->id(),
                        ]);
                    }
                }
            });

            return redirect()->route('asset-capitalizations.create')->with('success', 'Luar biasa! Pengakuan Aset berhasil.');

        } catch (\Exception $e) {
            Log::error('Error Kapitalisasi Aset: ' . $e->getMessage());
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function voidAsset($id)
    {
        try {
            DB::beginTransaction();
            $asset = FixedAsset::with('item')->findOrFail($id);
            if ($asset->status_id != 30) throw new \Exception("GAGAL: Aset tidak bisa dibatalkan.");

            $masterItem = $asset->item;
            $invStock = InventoryStock::where('item_id', $masterItem->id)->where('warehouse_id', $asset->warehouse_id)->first();

            if ($invStock) {
                $invStock->increment('stock_qty', 1);
            } else {
                InventoryStock::create([
                    'company_id' => $asset->company_id, 'warehouse_id' => $asset->warehouse_id,
                    'item_id' => $masterItem->id, 'stock_qty' => 1,
                    'reference_number' => $asset->asset_number . '-VOID', 'notes' => 'Pengembalian Void'
                ]);
            }

            $asset->update(['status_id' => 43, 'notes' => $asset->notes . "\n[DIBATALKAN]"]);
            DB::commit();
            return back()->with('success', "Aset {$asset->asset_number} dibatalkan.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }
}
