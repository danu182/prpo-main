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
        $asset = \App\Models\FixedAsset::with(['item', 'status'])->findOrFail($id);
        return view('asset_capitalizations.show', compact('asset'));
    }


    public function create()
    {
        // 1. Ambil 100 GR terbaru beserta relasinya
        $rawGrs = \App\Models\GoodsReceipt::with(['items.item', 'po.vendor'])
                    ->orderBy('received_date', 'desc')
                    ->limit(100)
                    ->get();

        $grIds = $rawGrs->pluck('id')->toArray();

        // 2. Hitung jumlah aset yang SUDAH dikapitalisasi per GR dan per Item (Cepat & Anti-Lemot)
        $capitalizedData = \App\Models\FixedAsset::whereIn('goods_receipt_id', $grIds)
            ->select('goods_receipt_id', 'item_id', \Illuminate\Support\Facades\DB::raw('count(*) as total_capitalized'))
            ->groupBy('goods_receipt_id', 'item_id')
            ->get();

        $validGrs = collect();

        // 3. Filter: Hanya simpan GR yang MASIH PUNYA sisa barang ke dalam $validGrs
        foreach ($rawGrs as $gr) {
            $hasSisa = false;

            foreach ($gr->items as $grItem) {
                // Hitung Qty Base (Eceran)
                $grConvRate = 1;
                $rawUom = $grItem->getRawOriginal('uom') ?: '';
                if (preg_match('/Isi\s*[:=]?\s*([0-9.]+)/i', $rawUom, $matches)) {
                    $grConvRate = (float) $matches[1];
                } elseif ($grItem->uom_id) {
                    $uomDb = \App\Models\ItemUom::find($grItem->uom_id);
                    if ($uomDb) $grConvRate = (float) $uomDb->conversion_qty;
                }

                // Total barang yang diterima dari Gudang
                $baseQtyReceived = ($grItem->qty_received - ($grItem->qty_returned ?? 0)) * $grConvRate;

                // Cari total yang sudah dikapitalisasi untuk item ini di GR ini
                $cap = $capitalizedData->where('goods_receipt_id', $gr->id)
                                       ->where('item_id', $grItem->item_id)
                                       ->first();

                $alreadyCapitalized = $cap ? $cap->total_capitalized : 0;

                // Jika masih ada sisa barang yang belum diakui jadi aset
                if (($baseQtyReceived - $alreadyCapitalized) > 0) {
                    $hasSisa = true;
                    break; // Cukup temukan 1 item yang punya sisa, GR ini layak tampil di Dropdown
                }
            }

            // Jika GR ini punya sisa barang, masukkan ke daftar dropdown
            if ($hasSisa) {
                $validGrs->push($gr);
            }
        }

        $assetCategories = \App\Models\AssetCategory::where('is_active', true)->get();

        // PENTING: Gunakan $validGrs (GR yang sudah disaring) dan ganti variabel $grs
        return view('asset_capitalizations.create', [
            'grs' => $validGrs,
            'assetCategories' => $assetCategories
        ]);
    }

    public function getGrItems($gr_id)
    {
        try {
            // 🔥 LOAD RELASI PO DAN ITEMS-NYA SEKALIGUS (Untuk Perhitungan Akurat) 🔥
            $gr = \App\Models\GoodsReceipt::with([
                'items.item.uom',
                'warehouse',
                'purchaseOrder.items',
                'po.items'
            ])->findOrFail($gr_id);

            $grDate = $gr->received_date;
            $po = $gr->purchaseOrder ?? $gr->po;

            // =========================================================================
            // 🔥 LOGIKA AKUNTANSI: MENGHITUNG BIAYA TAMBAHAN (PRORATA) 🔥
            // =========================================================================
            $biayaTambahanPerUnit = 0;
            if ($po) {
                $totalBiayaTambahan = (float) ($po->additional_cost ?? $po->shipping_fee ?? 0);

                $poItems = $po->items ?? $po->details ?? collect([]);
                $totalQtyPO = $poItems->sum('qty');

                if ($totalQtyPO <= 0) {
                    $totalQtyPO = 1; // Hindari pembagian dengan 0
                }

                $biayaTambahanPerUnit = $totalBiayaTambahan / $totalQtyPO;
            }
            // =========================================================================

            $items = [];
            foreach ($gr->items as $grItem) {
                $masterItem = $grItem->item;
                if (!$masterItem) continue;

                $grConvRate = 1;
                if (preg_match('/Isi\s*[:=]?\s*([0-9.]+)/i', $grItem->getRawOriginal('uom'), $matches)) {
                    $grConvRate = (float) $matches[1];
                } elseif ($grItem->uom_id) {
                    $uomDb = \App\Models\ItemUom::find($grItem->uom_id);
                    if ($uomDb) $grConvRate = (float) $uomDb->conversion_qty;
                }

                // =====================================================================
                // 🔥 OPERASI BEDAH: HAPUS RELASI BERACUN & HITUNG HARGA MURNI 🔥
                // =====================================================================
                $poItem = null;
                if ($po) {
                    $poItems = $po->items ?? $po->details ?? collect([]);
                    // Cari item di PO secara paksa pakai Item ID
                    $poItem = $poItems->where('item_id', $masterItem->id)->first();
                    if (!$poItem) $poItem = $poItems->first();
                }

                $netUnitPrice = 0;
                $specificName = $masterItem->name;

                if ($poItem) {
                    $specificName = $poItem->item_name ?? $masterItem->name;

                    $qtyOrdered = (float) ($poItem->qty_ordered ?? $poItem->qty ?? 1);
                    if ($qtyOrdered <= 0) $qtyOrdered = 1;

                    $subtotalBaris = (float) ($poItem->subtotal ?? 0);
                    $unitPriceAsli = (float) ($poItem->unit_price ?? 0);
                    $discountBaris = (float) ($poItem->discount_amount ?? $poItem->discount ?? 0);

                    // RUMUS AKUNTANSI: Jika ada subtotal, itu pasti harga final yang paling akurat
                    if ($subtotalBaris > 0) {
                        $netUnitPrice = $subtotalBaris / $qtyOrdered;
                    } else {
                        $netUnitPrice = $unitPriceAsli - $discountBaris;
                    }
                } else {
                    // Kalau tidak ada PO sama sekali
                    $netUnitPrice = (float) ($masterItem->purchase_price ?? 0);
                }

                // 🔥 RUMUS HARGA PEROLEHAN FINAL (LANDED COST) 🔥
                // Bebas dari error pembagian UOM!
                $hargaPerolehan = $netUnitPrice + $biayaTambahanPerUnit;
                // =====================================================================

                $baseQtyReceived = ($grItem->qty_received - ($grItem->qty_returned ?? 0)) * $grConvRate;
                if ($baseQtyReceived <= 0) continue;

                $currentStock = \App\Models\InventoryStock::where('item_id', $masterItem->id)->sum('stock_qty');

                $alreadyCapitalized = \App\Models\FixedAsset::where('goods_receipt_id', $gr->id)
                                                ->where('item_id', $masterItem->id)
                                                ->count();

                $maxCapitalizable = $baseQtyReceived - $alreadyCapitalized;

                $availableSns = [];
                if (isset($masterItem->is_trackable) && $masterItem->is_trackable) {
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
                        'item_id'           => $masterItem->id,
                        'item_code'         => $masterItem->code,
                        'item_name'         => $specificName,
                        'master_name'       => $masterItem->name, // Dikirim ke view Blade
                        'specific_name'     => $specificName, // Dikirim ke view Blade
                        'base_uom'          => optional($masterItem->uom)->name ?? 'Unit',
                        'gr_qty'            => $baseQtyReceived,
                        'current_stock'     => $currentStock,
                        'max_capitalizable' => floor($maxCapitalizable),
                        'available_sns'     => $availableSns,
                        'default_price'     => round($hargaPerolehan, 2), // Pasti Muncul 22.000.000!
                        'default_date'      => date('Y-m-d', strtotime($grDate)),
                        'default_spec'      => $defaultSpec
                    ];
                }
            }

            return response()->json([
                'warehouse_id' => $gr->warehouse_id,
                'warehouse_name' => optional($gr->warehouse)->name ?? 'Gudang Global',
                'items' => $items
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'goods_receipt_id' => 'required|exists:goods_receipts,id',
            'items'            => 'required|array',
            'items.*.qty'      => 'required|numeric|min:0',
            'items.*.details.*.accounting_no'     => 'nullable|string|distinct|unique:fixed_assets,accounting_asset_number',
            'items.*.details.*.asset_category_id' => 'required|exists:asset_categories,id',
        ], [
            'items.*.details.*.accounting_no.distinct' => 'Nomor Akuntansi (FA) ada yang kembar di dalam form ini.',
            'items.*.details.*.accounting_no.unique'   => 'Nomor Akuntansi (FA) tersebut sudah dipakai oleh aset lain.',
            'items.*.details.*.asset_category_id.required' => 'Kategori penyusutan wajib dipilih untuk setiap unit.',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $gr = GoodsReceipt::with(['purchaseOrder', 'po'])->findOrFail($request->goods_receipt_id);

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

                    // LOGIKA CROSSOVER (RETROACTIVE)
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

                            $stockRow->decrement('stock_qty', $potong);
                            $qtySisaPotong -= $potong;

                            StockMutation::create([
                                'item_id'          => $masterItem->id,
                                'warehouse_id'     => $stockRow->warehouse_id,
                                'type'             => 'OUT',
                                'qty'              => $potong,
                                'balance_before'   => $saldoTotalSaatIni,
                                'balance_after'    => $saldoTotalSaatIni,
                                'reference_number' => $gr->gr_number,
                                'notes'            => "[CAPITALIZE] Kapitalisasi menjadi Aset Tetap",
                                'created_by'       => auth()->id(),
                            ]);
                        }
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

                        $isRetroactive = $i >= $qtyFromStock;
                        $currentAssetStatus = $isRetroactive ? $statusInUseId : $statusAvailableId;

                        $year = date('Y'); $month = date('m');
                        $prefix = "AST/{$year}/{$month}/";
                        $lastAsset = FixedAsset::where('asset_number', 'like', "{$prefix}%")->orderBy('id', 'desc')->first();
                        $nextSeq = $lastAsset ? ((int) substr($lastAsset->asset_number, -4)) + 1 : 1;
                        $sysAssetNumber = $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

                        $accountingNo = $detail['accounting_no'] ?? null;
                        $specificName = $detail['specific_name'] ?? $masterItem->name;
                        $acqDate      = $detail['acquisition_date'] ?? date('Y-m-d');
                        $accValue     = $detail['accounting_value'] ?? 0;
                        $categoryId   = $detail['asset_category_id'] ?? null;
                        $specDetail   = $detail['notes'] ?? '';

                        $serialNumber = $detail['serial_number'] ?? null;
                        if (empty($serialNumber) && isset($autoSns[$i])) {
                            $serialNumber = $autoSns[$i];
                        }

                        $extraNote = "Diakui dari dokumen penerimaan: {$gr->gr_number}.";
                        if ($isRetroactive) {
                            $extraNote .= " [RETROACTIVE: Diakui setelah barang didistribusikan / Goods Issue].";
                        }

                        $newAsset = FixedAsset::create([
                            'asset_number'            => $sysAssetNumber,
                            'item_id'                 => $masterItem->id,
                            'asset_category_id'       => $categoryId,
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
                            'status_id'               => $currentAssetStatus,
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

            return redirect()->route('asset-capitalizations.create')->with('success', 'Luar biasa! Pengakuan Aset berhasil. Sistem telah mengaitkan kategori penyusutan secara otomatis.');

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
            // Saldo tidak perlu ditambah +1 lagi di sini karena saat kapitalisasi juga tidak dipotong
            $balanceAfter = $balanceBefore;

            // 2. KEMBALIKAN STOK FISIK KE GUDANG BIASA
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

            // 3. CATAT MUTASI REVERT (Tipe: IN, TAPI ADA KODE [DE-CAPITALIZE])
            if (class_exists(\App\Models\StockMutation::class)) {
                \App\Models\StockMutation::create([
                    'item_id'          => $masterItem->id,
                    'warehouse_id'     => $asset->warehouse_id,
                    'type'             => 'IN',
                    'qty'              => 1,
                    'balance_before'   => $balanceBefore,
                    'balance_after'    => $balanceAfter, // Saldo Utama Tetap Sama
                    'reference_number' => $asset->asset_number,
                    'notes'            => "[DE-CAPITALIZE] Pembatalan Pengakuan Aset (VOID).",
                    'created_by'       => auth()->id(),
                ]);
            }

            // 4. UBAH STATUS ASET MENJADI VOID (ID 43)
            $statusVoidId = 43;

            $asset->update([
                'status_id' => $statusVoidId,
                'notes'     => $asset->notes . "\n[DIBATALKAN PADA " . date('d-m-Y H:i') . "]"
            ]);

            \DB::commit();
            return back()->with('success', "Aset {$asset->asset_number} berhasil dibatalkan dan dikembalikan sebagai stok biasa.");

        } catch (\Exception $e) {
            \DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }
}
