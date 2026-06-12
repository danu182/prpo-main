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
    // 1. Tampilkan Halaman Form
    public function create()
    {
        $grs = GoodsReceipt::orderBy('received_date', 'desc')->limit(50)->get();
        return view('asset_capitalizations.create', compact('grs'));
    }

    // 2. AJAX: Ambil Rincian Item
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

            // Hitung Harga Default
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

    // 3. Proses Simpan Aset
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
                $gr = GoodsReceipt::findOrFail($request->goods_receipt_id);
                $statusAvailableId = Status::where('type', 'AST')->where('slug', 'available')->value('id') ?? 31;

                // Tarik data PO dari database murni
                $poData = \DB::table('purchase_orders')->where('id', $gr->purchase_order_id)->first();

                // 🔥 JEMBATAN PENERJEMAH MATA UANG 🔥
                // 1. Ambil teks mata uang dari PO (Misal: 'USD' atau 'IDR')
                $currencyCode = $poData->currency ?? 'IDR';

                // 2. Cari angka ID-nya di tabel currencies berdasarkan teks tersebut
                $currencyDb = \DB::table('currencies')->where('code', $currencyCode)->first();

                // 3. Masukkan angka ID-nya (Jika ketemu 'USD', otomatis jadi 2)
                $currencyId = $currencyDb ? $currencyDb->id : 1;

                // Ambil Company ID
                $companyId  = $poData->company_id ?? $poData->bill_to_company_id ?? null;

                foreach ($request->items as $itemId => $data) {
                    $qtyToCapitalize = (int) ($data['qty'] ?? 0);
                    if ($qtyToCapitalize <= 0) continue;

                    $masterItem = Item::findOrFail($itemId);

                    // 1. Potong Stok Gudang
                    $availableStocks = InventoryStock::where('item_id', $itemId)
                                        ->where('stock_qty', '>', 0)
                                        ->orderBy('created_at', 'asc')->lockForUpdate()->get();

                    $totalAvailable = $availableStocks->sum('stock_qty');
                    if ($qtyToCapitalize > $totalAvailable) {
                        throw new \Exception("Stok fisik {$masterItem->name} tersisa {$totalAvailable}, tidak cukup untuk dijadikan aset.");
                    }

                    $qtySisaPotong = $qtyToCapitalize;
                    $saldoTotalSaatIni = (float) $masterItem->current_stock;
                    $actualWarehouseId = $gr->warehouse_id ?? 1;

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

                    // 2. Suntik Serial Number
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

                    // 3. Simpan ke Tabel `fixed_assets` secara Presisi
                    $details = $data['details'] ?? [];
                    for ($i = 0; $i < $qtyToCapitalize; $i++) {
                        $detail = $details[$i] ?? [];

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
                        $specDetail   = $detail['notes'] ?? ''; // Teks dari Quill

                        $serialNumber = $detail['serial_number'] ?? null;
                        if (empty($serialNumber) && isset($autoSns[$i])) {
                            $serialNumber = $autoSns[$i];
                        }

                        // Catatan Tambahan
                        $extraNote = "Diakui dari dokumen penerimaan: {$gr->gr_number}.";
                        if (!empty($usefulLife)) $extraNote .= " Estimasi Umur Ekonomis: {$usefulLife} Tahun.";

                        // 🔥 SESUAIKAN DENGAN STRUKTUR TABEL DATABASE ANDA 🔥
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
                            'status_id'               => $statusAvailableId,
                            'notes'                   => $extraNote,
                        ]);

                        FixedAssetHistory::create([
                            'fixed_asset_id' => $newAsset->id,
                            'status'         => 'Registered (Terdaftar)',
                            'notes'          => "Aset diregistrasi dari Stok (Ref GR: {$gr->gr_number}).",
                            'created_by'     => auth()->id(),
                        ]);
                    }
                }
            });

            return redirect()->route('asset-capitalizations.create')->with('success', 'Luar biasa! Stok berhasil dikonversi menjadi Aset Tetap secara akurat.');

        } catch (\Exception $e) {
            Log::error('Error Kapitalisasi Aset: ' . $e->getMessage());
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
