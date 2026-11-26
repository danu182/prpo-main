<?php

namespace App\Http\Controllers;

use App\Models\FixedAsset;
use App\Models\FixedAssetUpgrade;
use App\Models\FixedAssetHistory;
use App\Models\Item;
use App\Models\Warehouse;
use App\Models\InventoryStock;
use App\Models\StockMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetUpgradeController extends Controller
{
    public function index(Request $request)
    {
        $query = FixedAssetUpgrade::with([
            'asset.item', 'asset.company', 'item', 'warehouse', 'purchaseOrder', 'goodsReceipt', 'currency', 'creator'
        ]);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('upgrade_number', 'like', "%{$search}%")
                  ->orWhere('component_name', 'like', "%{$search}%")
                  ->orWhereHas('asset', function ($qa) use ($search) {
                      $qa->where('asset_number', 'like', "%{$search}%")
                         ->orWhere('name', 'like', "%{$search}%")
                         ->orWhere('accounting_asset_number', 'like', "%{$search}%");
                  })
                  ->orWhereHas('purchaseOrder', function ($qp) use ($search) {
                      $qp->where('po_number', 'like', "%{$search}%");
                  });
            });
        }

        $upgrades = $query->latest('upgrade_date')->latest('id')->paginate(15)->withQueryString();
        return view('fixed_assets.upgrades.index', compact('upgrades'));
    }

    public function create(Request $request)
    {
        $assets = FixedAsset::with(['item', 'company', 'assignee', 'status', 'assetCategory', 'upgrades'])
            ->where(function ($q) {
                $q->whereNull('notes')->orWhere('notes', 'not like', '%[DIBATALKAN%');
            })
            ->whereHas('status', function ($q) {
                $q->whereNotIn('slug', ['void', 'batal', 'canceled', 'cancelled', 'disposed']);
            })
            ->orderBy('asset_number', 'asc')
            ->get()
            ->map(function ($a) {
                $summary = $a->calculateDepreciationSummary();
                $a->computed_nbv              = $summary['net_book_value'];
                $a->computed_remaining_months = $summary['remaining_months'];
                $a->computed_total_cost       = $summary['total_historical_cost'];
                return $a;
            });

        // Daftar Aset Terdaftar (AST / No. FA) yang tersedia untuk digabungkan (Merge) sebagai komponen upgrade
        $mergeableAssets = FixedAsset::with(['item', 'company', 'warehouse', 'status'])
            ->where(function ($q) {
                $q->whereNull('notes')->orWhere('notes', 'not like', '%[DIBATALKAN%');
            })
            ->whereHas('status', function ($q) {
                $q->whereIn('slug', ['available', 'in_use']);
            })
            ->orderBy('asset_number', 'desc')
            ->get()
            ->map(function ($ast) {
                $summary = $ast->calculateDepreciationSummary();
                $ast->computed_nbv = $summary['net_book_value'] > 0
                    ? $summary['net_book_value']
                    : (float) ($ast->purchase_price ?? 0);
                return $ast;
            });

        $warehouses = Warehouse::orderBy('name')->get();
        $selectedAssetId = $request->query('asset_id');

        return view('fixed_assets.upgrades.create', compact('assets', 'mergeableAssets', 'warehouses', 'selectedAssetId'));
    }

    /**
     * AJAX: Ambil stok item tersedia di gudang beserta riwayat GR/PO terakhir & harga beli PO
     */
    public function getWarehouseItems($warehouseId)
    {
        $stocks = InventoryStock::with(['item.uom'])
            ->where('warehouse_id', $warehouseId)
            ->where('stock_qty', '>', 0)
            ->get();

        $griCols = \Illuminate\Support\Facades\Schema::getColumnListing('goods_receipt_items');
        $poiCols = \Illuminate\Support\Facades\Schema::getColumnListing('purchase_order_items');

        $result = [];
        foreach ($stocks as $st) {
            $item = $st->item;
            if (!$item) continue;

            $query = DB::table('goods_receipt_items as gri')
                ->join('goods_receipts as gr', 'gr.id', '=', 'gri.goods_receipt_id')
                ->leftJoin('purchase_orders as po', 'po.id', '=', 'gr.purchase_order_id')
                ->leftJoin('purchase_order_items as poi', function ($join) use ($item, $griCols) {
                    $join->on('poi.purchase_order_id', '=', 'po.id')
                         ->where('poi.item_id', '=', $item->id);
                    if (in_array('purchase_order_item_id', $griCols)) {
                        $join->on('poi.id', '=', 'gri.purchase_order_item_id');
                    }
                })
                ->where('gri.item_id', $item->id);

            if (\Illuminate\Support\Facades\Schema::hasColumn('goods_receipts', 'warehouse_id')) {
                $query->orderByRaw('CASE WHEN gr.warehouse_id = ? THEN 0 ELSE 1 END', [$warehouseId]);
            }

            $selects = [
                'gr.id as gr_id',
                'gr.gr_number',
                'gr.received_date',
                'po.id as po_id',
                'po.po_number',
                'poi.unit_price',
                'poi.subtotal',
                'poi.qty_ordered',
            ];
            if (in_array('item_name', $poiCols)) {
                $selects[] = 'poi.item_name as po_item_name';
            }
            if (in_array('description', $poiCols)) {
                $selects[] = 'poi.description as po_description';
            }
            if (in_array('item_name', $griCols)) {
                $selects[] = 'gri.item_name as gr_item_name';
            }
            if (in_array('notes', $griCols)) {
                $selects[] = 'gri.notes as gr_notes';
            }

            $grRows = $query->select($selects)
                ->orderBy('gr.received_date', 'desc')
                ->orderBy('gr.id', 'desc')
                ->limit(15)
                ->get();

            $poRefs = [];
            $variantNames = [];

            foreach ($grRows as $g) {
                $netPrice = (float) ($g->unit_price ?? 0);
                if (!empty($g->subtotal) && !empty($g->qty_ordered) && $g->qty_ordered > 0) {
                    $netPrice = (float) $g->subtotal / (float) $g->qty_ordered;
                }

                // Tentukan nama spesifik/alternatif part dari GR atau PO
                $specificName = trim((string) ($g->gr_item_name ?? $g->po_item_name ?? $g->po_description ?? ''));
                if ($specificName === '') {
                    $specificName = $item->name;
                }

                if (strtolower($specificName) !== strtolower(trim($item->name)) && !in_array($specificName, $variantNames)) {
                    $variantNames[] = $specificName;
                }

                $poRefs[] = [
                    'gr_id'         => $g->gr_id,
                    'gr_number'     => $g->gr_number,
                    'po_id'         => $g->po_id,
                    'po_number'     => $g->po_number ?: 'Tanpa PO',
                    'specific_name' => $specificName,
                    'date'          => $g->received_date,
                    'price'         => round($netPrice, 2),
                ];
            }

            $defaultPrice    = !empty($poRefs) ? $poRefs[0]['price'] : (float) ($item->purchase_price ?? 0);
            $defaultCompName = !empty($poRefs) ? $poRefs[0]['specific_name'] : $item->name;
            $variantSummary  = !empty($variantNames) ? implode(' / ', $variantNames) : null;

            $result[] = [
                'item_id'           => $item->id,
                'code'              => $item->code,
                'name'              => $item->name,
                'variant_summary'   => $variantSummary,
                'default_comp_name' => $defaultCompName,
                'uom'               => optional($item->uom)->name ?? 'PCS',
                'available_qty'     => (float) $st->stock_qty,
                'default_price'     => $defaultPrice,
                'po_refs'           => $poRefs,
            ];
        }

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $request->validate([
            'fixed_asset_id'              => 'required|exists:fixed_assets,id',
            'upgrade_date'                => 'required|date',
            'accounting_treatment'        => 'required|in:EXPENSE,CAPITALIZE_VALUE,CAPITALIZE_EXTEND',
            'new_remaining_months'        => 'nullable|required_if:accounting_treatment,CAPITALIZE_EXTEND|integer|min:1|max:600',
            'notes'                       => 'nullable|string|max:1000',
            'parts'                       => 'required|array|min:1',
            'parts.*.source_type'         => 'required|in:WAREHOUSE,DIRECT_SERVICE,MERGED_ASSET',
            'parts.*.component_name'      => 'required|string|max:255',
            'parts.*.qty'                 => 'required|numeric|min:0.01',
            'parts.*.unit_cost'           => 'required|numeric|min:0',
            'parts.*.warehouse_id'        => 'nullable|exists:warehouses,id',
            'parts.*.item_id'             => 'nullable|exists:items,id',
            'parts.*.merged_asset_id'     => 'nullable|exists:fixed_assets,id',
            'parts.*.purchase_order_id'   => 'nullable|exists:purchase_orders,id',
            'parts.*.goods_receipt_id'    => 'nullable|exists:goods_receipts,id',
            'parts.*.vendor_name'         => 'nullable|string|max:255',
        ]);

        // Cegah penggabungan aset ke dirinya sendiri
        foreach ($request->parts as $p) {
            if (($p['source_type'] ?? '') === 'MERGED_ASSET' && (int) ($p['merged_asset_id'] ?? 0) === (int) $request->fixed_asset_id) {
                return back()->withInput()->with('error', 'Aset komponen tidak boleh sama dengan Unit Aset Target (PC Induk).');
            }
        }

        DB::beginTransaction();
        try {
            $asset = FixedAsset::with(['assetCategory', 'upgrades', 'company', 'currency', 'status'])
                ->lockForUpdate()
                ->findOrFail($request->fixed_asset_id);

            $upgradeDate = Carbon::parse($request->upgrade_date);
            $treatment   = $request->accounting_treatment;

            $summaryBefore = $asset->calculateDepreciationSummary($upgradeDate);
            $nbvBefore     = (float) $summaryBefore['net_book_value'];
            $remBefore     = (int) $summaryBefore['remaining_months'];

            $totalUpgradeCost = 0.0;
            foreach ($request->parts as $p) {
                $totalUpgradeCost += ((float) $p['qty'] * (float) $p['unit_cost']);
            }

            $isCapitalized      = in_array($treatment, ['CAPITALIZE_VALUE', 'CAPITALIZE_EXTEND']);
            $newRemainingMonths = null;

            if ($treatment === 'CAPITALIZE_VALUE') {
                if ($remBefore <= 0 && $totalUpgradeCost > 0) {
                    DB::rollBack();
                    return back()->withInput()->with('error', 'Sisa umur ekonomis PC induk sudah 0 bulan. Silakan pilih opsi "Tambah Nilai + Setel Sisa Umur Baru (Bulan)" agar penyusutan PC dapat berjalan.');
                }
                $nbvAfter = $nbvBefore + $totalUpgradeCost;
                $remAfter = $remBefore;
            } elseif ($treatment === 'CAPITALIZE_EXTEND') {
                $newRemainingMonths = (int) $request->new_remaining_months;
                $nbvAfter           = $nbvBefore + $totalUpgradeCost;
                $remAfter           = $newRemainingMonths;
            } else {
                $nbvAfter = $nbvBefore;
                $remAfter = $remBefore;
            }

            $disposedStatus = \App\Models\Status::where('type', 'ASSET')->where('slug', 'disposed')->first()
                ?? \App\Models\Status::where('slug', 'disposed')->first();

            $runningNbv   = $nbvBefore;
            $createdCount = 0;

            foreach ($request->parts as $p) {
                $qty      = (float) $p['qty'];
                $unitCost = (float) $p['unit_cost'];
                $lineCost = round($qty * $unitCost, 2);
                $srcType  = $p['source_type'];

                $upgNumber = $this->generateUpgradeNumber($upgradeDate);
                $itemId    = !empty($p['item_id']) ? (int) $p['item_id'] : null;
                $whId      = !empty($p['warehouse_id']) ? (int) $p['warehouse_id'] : null;
                $poId      = !empty($p['purchase_order_id']) ? (int) $p['purchase_order_id'] : null;
                $grId      = !empty($p['goods_receipt_id']) ? (int) $p['goods_receipt_id'] : null;
                $vendorStr = $p['vendor_name'] ?? null;
                $compName  = trim((string) $p['component_name']);

                // =============================================================
                // KASUS 1: PENGELUARAN STOK GUDANG BIASA (WAREHOUSE)
                // =============================================================
                if ($srcType === 'WAREHOUSE') {
                    if (!$whId || !$itemId) {
                        throw new \Exception("Gudang dan Item wajib dipilih untuk pengeluaran dari stok.");
                    }

                    $stock = InventoryStock::where('warehouse_id', $whId)
                        ->where('item_id', $itemId)
                        ->lockForUpdate()
                        ->first();

                    if (!$stock || (float) $stock->stock_qty < $qty) {
                        $itemObj = Item::find($itemId);
                        $avail   = $stock ? (float) $stock->stock_qty : 0;
                        throw new \Exception("Stok tidak mencukupi untuk '{$itemObj->name}'. Tersedia: {$avail}, Diminta: {$qty}.");
                    }

                    $stock->decrement('stock_qty', $qty);

                    $holderInfo = $asset->assignee ? $asset->assignee->name : 'Gudang / Belum Diserahkan';
                    $faInfo     = $asset->accounting_asset_number ? " | No. FA: {$asset->accounting_asset_number}" : '';
                    $poInfo     = $poId ? (' | PO: ' . (PurchaseOrder::find($poId)->po_number ?? '-')) : '';
                    $grInfo     = $grId ? (' | GR: ' . (GoodsReceipt::find($grId)->gr_number ?? '-')) : '';

                    if (class_exists('\App\Models\StockMovement')) {
                        \App\Models\StockMovement::create([
                            'warehouse_id'     => $whId,
                            'item_id'          => $itemId,
                            'type'             => 'OUT',
                            'qty'              => $qty,
                            'reference_number' => $upgNumber,
                            'notes'            => "Dipasang ke Aset: {$asset->asset_number} - {$asset->name}{$faInfo} | Pemegang: {$holderInfo} | Nilai: Rp " . number_format($lineCost, 0, ',', '.') . "{$poInfo}{$grInfo}",
                            'user_id'          => auth()->id(),
                        ]);
                    }
                }

                // =============================================================
                // KASUS 2: MERGE FIXED ASSET (ASET KOMPONEN AST DIGABUNG KE PC INDUK)
                // Penyusutan komponen berhenti, nilainya melebur & menyusut ikut PC Induk!
                // =============================================================
                if ($srcType === 'MERGED_ASSET') {
                    $mergedAssetId = (int) ($p['merged_asset_id'] ?? 0);
                    if (!$mergedAssetId) {
                        throw new \Exception("Unit Aset Komponen wajib dipilih untuk mode Merge Fixed Asset.");
                    }

                    $compAsset = FixedAsset::with(['status', 'item', 'warehouse'])
                        ->lockForUpdate()
                        ->findOrFail($mergedAssetId);

                    if (optional($compAsset->status)->slug === 'disposed') {
                        throw new \Exception("Aset {$compAsset->asset_number} sudah berstatus Disposed/Merged.");
                    }

                    $itemId    = $compAsset->item_id;
                    $whId      = $compAsset->warehouse_id;
                    $poId      = $compAsset->purchase_order_id;
                    $grId      = $compAsset->goods_receipt_id;
                    $vendorStr = $compAsset->vendor_name;

                    $compFaLabel = $compAsset->accounting_asset_number ?: $compAsset->asset_number;
                    if (stripos($compName, $compAsset->asset_number) === false) {
                        $compName .= " [Merge FA: {$compFaLabel}]";
                    }

                    // Jika komponen sebelumnya masih tercatat sebagai Aset Terdaftar (Siap GI) di gudang, kurangi 1 dari saldo kartu stok
                    if (optional($compAsset->status)->slug === 'available' && empty($compAsset->assigned_to) && $compAsset->warehouse_id && $compAsset->item_id) {
                        $astStock = InventoryStock::where('warehouse_id', $compAsset->warehouse_id)
                            ->where('item_id', $compAsset->item_id)
                            ->lockForUpdate()
                            ->first();
                        if ($astStock && isset($astStock->asset_qty) && (float) $astStock->asset_qty >= 1) {
                            $astStock->decrement('asset_qty', 1);
                        }
                    }

                    // Tutup / nonaktifkan aset komponen mandiri agar tidak menyusut 2x
                    $oldCompNote = trim((string) $compAsset->notes);
                    $mergeNote   = "[MERGED KE ASET INDUK: {$asset->asset_number}" . ($asset->accounting_asset_number ? " / FA: {$asset->accounting_asset_number}" : "") . " pada " . $upgradeDate->format('d/m/Y') . " — Penyusutan mengikuti aset induk]";

                    $compAsset->update([
                        'status_id'      => $disposedStatus ? $disposedStatus->id : $compAsset->status_id,
                        'assigned_to'    => null,
                        'disposed_at'    => $upgradeDate->toDateString(),
                        'disposal_notes' => $mergeNote,
                        'notes'          => trim($oldCompNote . "\n" . $mergeNote),
                    ]);

                    if (class_exists('\App\Models\FixedAssetHistory')) {
                        \App\Models\FixedAssetHistory::create([
                            'fixed_asset_id' => $compAsset->id,
                            'action'         => 'MERGED_INTO_PARENT',
                            'description'    => "Aset komponen digabungkan (Merged) ke unit induk {$asset->asset_number} ({$asset->name}). Nilai Rp " . number_format($lineCost, 0, ',', '.') . " dipindahkan dan disusutkan mengikuti masa manfaat aset induk.",
                            'user_id'        => auth()->id(),
                        ]);
                    }
                }

                $lineNbvBefore = $runningNbv;
                $lineNbvAfter  = $isCapitalized ? ($runningNbv + $lineCost) : $runningNbv;
                $runningNbv    = $lineNbvAfter;

                $upgData = [
                    'fixed_asset_id'       => $asset->id,
                    'upgrade_number'       => $upgNumber,
                    'item_id'              => $itemId,
                    'warehouse_id'         => $whId,
                    'purchase_order_id'    => $poId,
                    'goods_receipt_id'     => $grId,
                    'source_type'          => $srcType,
                    'qty'                  => $qty,
                    'unit_cost'            => $unitCost,
                    'component_name'       => $compName,
                    'cost'                 => $lineCost,
                    'currency_id'          => $asset->currency_id,
                    'upgrade_date'         => $upgradeDate->toDateString(),
                    'is_capitalized'       => $isCapitalized,
                    'accounting_treatment' => $treatment,
                    'new_remaining_months' => $newRemainingMonths,
                    'book_value_before'    => $lineNbvBefore,
                    'book_value_after'     => $lineNbvAfter,
                    'vendor_name'          => $vendorStr,
                    'notes'                => $request->notes,
                    'status'               => 'ACTIVE',
                    'created_by'           => auth()->id(),
                ];

                if (\Illuminate\Support\Facades\Schema::hasColumn('fixed_asset_upgrades', 'merged_asset_id')) {
                    $upgData['merged_asset_id'] = !empty($p['merged_asset_id']) ? (int) $p['merged_asset_id'] : null;
                }

                FixedAssetUpgrade::create($upgData);

                $createdCount++;
            }

            if ($treatment === 'CAPITALIZE_EXTEND' && $newRemainingMonths !== null) {
                $monthsElapsedUntilUpgrade = $asset->acquisition_date
                    ? Carbon::parse($asset->acquisition_date)->startOfDay()->diffInMonths($upgradeDate->copy()->startOfDay())
                    : 0;
                $baseLifeMonths     = (int) ((optional($asset->assetCategory)->useful_life_years ?? 4) * 12);
                $totalTargetMonths  = $monthsElapsedUntilUpgrade + $newRemainingMonths;
                $extendedDiffMonths = max(0, $totalTargetMonths - $baseLifeMonths);

                $asset->update([
                    'extended_useful_life_months' => $extendedDiffMonths,
                    'last_revaluation_date'       => $upgradeDate->toDateString(),
                ]);
            } elseif ($treatment === 'CAPITALIZE_VALUE') {
                $asset->update([
                    'last_revaluation_date' => $upgradeDate->toDateString(),
                ]);
            }

            if (class_exists('\App\Models\FixedAssetHistory')) {
                $treatDesc = $isCapitalized
                    ? "Dikapitalisasi ke nilai PC induk (Sisa umur penyusutan PC: {$remAfter} bulan)."
                    : "Dicatat sebagai Beban Pemeliharaan (Expense).";

                \App\Models\FixedAssetHistory::create([
                    'fixed_asset_id' => $asset->id,
                    'action'         => 'UPGRADE',
                    'description'    => "Pemasangan {$createdCount} komponen upgrade senilai Rp " . number_format($totalUpgradeCost, 0, ',', '.') . ". {$treatDesc} Nilai buku: Rp " . number_format($nbvBefore, 0, ',', '.') . " -> Rp " . number_format($nbvAfter, 0, ',', '.'),
                    'user_id'        => auth()->id(),
                ]);
            }

            DB::commit();
            return redirect()->route('fixed-assets.upgrades.index')
                ->with('success', "Berhasil memproses {$createdCount} komponen upgrade pada aset {$asset->asset_number} (penyusutan mengikuti aset induk).");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Gagal memproses upgrade aset: ' . $e->getMessage());
        }
    }



    public function removePart(Request $request, $id)
    {
        $request->validate([
            'removal_condition'   => 'required|in:GOOD_RETURN_STOCK,DEFECTIVE_NO_STOCK',
            'removed_at'          => 'required|date',
            'return_warehouse_id' => 'required_if:removal_condition,GOOD_RETURN_STOCK|nullable|exists:warehouses,id',
            'removal_reason'      => 'required|string|max:500',
        ], [
            'return_warehouse_id.required_if' => 'Pilih gudang tujuan pengembalian stok karena part masih dalam kondisi bagus.',
            'removal_reason.required'         => 'Alasan pencopotan part wajib diisi untuk jejak audit.',
        ]);

        try {
            $removalNumber = null;

            DB::transaction(function () use ($request, $id, &$removalNumber) {
                $upgrade = FixedAssetUpgrade::with([
                        'asset.assetCategory',
                        'asset.upgrades',
                        'item',
                        'purchaseOrder',
                        'goodsReceipt'
                    ])
                    ->lockForUpdate()
                    ->findOrFail($id);

                // Perlakukan null / string kosong sebagai ACTIVE (untuk data sebelum migration kolom status)
                $currentStatus = trim((string) ($upgrade->status ?? '')) ?: 'ACTIVE';
                if (in_array($currentStatus, ['REMOVED_GOOD', 'REMOVED_DEFECTIVE'])) {
                    throw new \Exception('Part upgrade ini sudah pernah dicopot/dibatalkan sebelumnya.');
                }

                // Validasi tanggal pencopotan tidak mendahului tanggal pemasangan
                if ($upgrade->upgrade_date && \Carbon\Carbon::parse($request->removed_at)->lt($upgrade->upgrade_date->startOfDay())) {
                    throw new \Exception('Tanggal pencopotan (' . $request->removed_at . ') tidak boleh lebih awal dari tanggal pemasangan (' . $upgrade->upgrade_date->format('Y-m-d') . ').');
                }

                $asset = $upgrade->asset;
                if (!$asset) {
                    throw new \Exception('Data aset induk untuk komponen upgrade ini tidak ditemukan.');
                }

                $summaryBefore = $asset->calculateDepreciationSummary($request->removed_at);
                $nbvBefore     = $summaryBefore['net_book_value'];

                // Generate Nomor Dokumen Pencopotan Part (UNUPG/YYYY/MM/XXXX)
                $yearMonth = date('Y/m');
                $lastRem = FixedAssetUpgrade::where('removal_number', 'like', "UNUPG/{$yearMonth}/%")
                    ->orderBy('id', 'desc')->lockForUpdate()->first();
                $nextSeq = $lastRem ? ((int) substr($lastRem->removal_number, -4)) + 1 : 1;
                $removalNumber = "UNUPG/{$yearMonth}/" . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

                $isGoodReturn = ($request->removal_condition === 'GOOD_RETURN_STOCK');
                $newStatus    = $isGoodReturn ? 'REMOVED_GOOD' : 'REMOVED_DEFECTIVE';

                $upgrade->update([
                    'status'         => $newStatus,
                    'removed_at'     => $request->removed_at,
                    'removal_number' => $removalNumber,
                    'removal_reason' => trim($request->removal_reason),
                    'removed_by'     => auth()->id(),
                ]);

                // Hitung ulang nilai buku setelah part ini berstatus REMOVED
                $asset->load('upgrades');
                $summaryAfter = $asset->calculateDepreciationSummary($request->removed_at);
                $nbvAfter     = $summaryAfter['net_book_value'];

                // Jika part ini sebelumnya memperpanjang umur aset, cek apakah masih ada part aktif lain yang memperpanjang umur
                if (!empty($upgrade->new_remaining_months)) {
                    $otherActiveExtension = $asset->upgrades
                        ->whereNotIn('status', ['REMOVED_GOOD', 'REMOVED_DEFECTIVE'])
                        ->whereNotNull('new_remaining_months')
                        ->sortByDesc('upgrade_date')
                        ->first();

                    $asset->update([
                        'extended_useful_life_months' => $otherActiveExtension ? $otherActiveExtension->new_remaining_months : null,
                    ]);
                }

                $poNumberText = optional($upgrade->purchaseOrder)->po_number ?? 'Non-PO';
                $grNumberText = optional($upgrade->goodsReceipt)->gr_number ?? '-';
                $qty          = (float) ($upgrade->qty ?? 1);
                $unitCost     = (float) ($upgrade->unit_cost ?? ($qty > 0 ? $upgrade->cost / $qty : 0));
                $lineCost     = (float) ($upgrade->cost ?? 0);

                // Jika part berasal dari stok gudang dan kondisinya MASIH BAGUS -> Kembalikan stok & nilai ke Kartu Stok (IN)
                if ($isGoodReturn && $upgrade->item_id) {
                    $targetWhId = (int) ($request->return_warehouse_id ?: $upgrade->warehouse_id);
                    if ($targetWhId <= 0) {
                        throw new \Exception('Gudang tujuan pengembalian stok wajib dipilih.');
                    }

                    $item = Item::lockForUpdate()->findOrFail($upgrade->item_id);

                    $invStock = InventoryStock::where('item_id', $item->id)
                        ->where('warehouse_id', $targetWhId)
                        ->lockForUpdate()
                        ->first();

                    $invCols = \Illuminate\Support\Facades\Schema::getColumnListing('inventory_stocks');
                    $smCols  = \Illuminate\Support\Facades\Schema::getColumnListing('stock_mutations');

                    if ($invStock) {
                        $updInv = ['stock_qty' => (float) $invStock->stock_qty + $qty];
                        if (in_array('stock_value', $invCols)) {
                            $updInv['stock_value'] = (float) $invStock->stock_value + $lineCost;
                        }
                        if (in_array('total_value', $invCols)) {
                            $updInv['total_value'] = (float) $invStock->total_value + $lineCost;
                        }
                        $invStock->update($updInv);
                    } else {
                        $createInv = [
                            'company_id'       => $asset->company_id,
                            'warehouse_id'     => $targetWhId,
                            'item_id'          => $item->id,
                            'stock_qty'        => $qty,
                            'reference_number' => $removalNumber,
                            'notes'            => 'Pengembalian Part Upgrade Tidak Cocok',
                        ];
                        if (in_array('stock_value', $invCols)) $createInv['stock_value'] = $lineCost;
                        if (in_array('total_value', $invCols)) $createInv['total_value'] = $lineCost;
                        InventoryStock::create($createInv);
                    }

                    $beforeMaster = (float) $item->current_stock;
                    $afterMaster  = $beforeMaster + $qty;
                    $item->update(['current_stock' => $afterMaster]);

                    $stockNote = "Retur Part Upgrade dari Aset: {$asset->asset_number} - {$asset->name} | " .
                                 "Masuk kembali {$qty} unit (@ Rp " . number_format($unitCost, 0, ',', '.') .
                                 " = Rp " . number_format($lineCost, 0, ',', '.') . ") | " .
                                 "Ref Pasang: {$upgrade->upgrade_number} (PO: {$poNumberText} | GR: {$grNumberText}) | " .
                                 "Alasan: " . trim($request->removal_reason);

                    $mutData = [
                        'item_id'          => $item->id,
                        'warehouse_id'     => $targetWhId,
                        'type'             => 'IN',
                        'qty'              => $qty,
                        'balance_before'   => $beforeMaster,
                        'balance_after'    => $afterMaster,
                        'reference_number' => $removalNumber,
                        'notes'            => $stockNote,
                        'created_by'       => auth()->id(),
                    ];
                    if (in_array('unit_price', $smCols))  $mutData['unit_price']  = $unitCost;
                    if (in_array('unit_cost', $smCols))   $mutData['unit_cost']   = $unitCost;
                    if (in_array('total_price', $smCols)) $mutData['total_price'] = $lineCost;
                    if (in_array('total_cost', $smCols))  $mutData['total_cost']  = $lineCost;
                    if (in_array('total_value', $smCols)) $mutData['total_value'] = $lineCost;

                    StockMutation::create($mutData);
                }

                // Susun Log Audit di Timeline Aset
                $stockActionText = $isGoodReturn
                    ? ($upgrade->item_id
                        ? "Part BAGUS dikembalikan ke stok gudang ({$removalNumber})."
                        : "Part BAGUS (Non-Stok Gudang) dicopot ({$removalNumber}).")
                    : "Part RUSAK/CACAT tidak dikembalikan ke stok siap pakai (Karantina/RMA Vendor - {$removalNumber}).";

                $valueActionText = $upgrade->is_capitalized
                    ? "Nilai Buku Aset dikoreksi turun dari Rp " . number_format($nbvBefore, 0, ',', '.') . " menjadi Rp " . number_format($nbvAfter, 0, ',', '.') . "."
                    : "Nilai Buku Aset tetap Rp " . number_format($nbvBefore, 0, ',', '.') . " (sebelumnya dicatat sebagai beban).";

                FixedAssetHistory::create([
                    'fixed_asset_id' => $asset->id,
                    'status'         => 'UPGRADE_REMOVED',
                    'assigned_to'    => $asset->assigned_to,
                    'notes'          => "[{$removalNumber}] PENCOPOTAN PART UPGRADE: {$upgrade->component_name} (Ref Pasang: {$upgrade->upgrade_number}) | {$stockActionText} | {$valueActionText} | Alasan: " . trim($request->removal_reason),
                    'created_by'     => auth()->id(),
                ]);
            });

            return back()->with('success', "Part upgrade berhasil dicopot ({$removalNumber}). Nilai aset & kartu stok telah disesuaikan otomatis.");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memproses pencopotan part: ' . $e->getMessage());
        }
    }




}
