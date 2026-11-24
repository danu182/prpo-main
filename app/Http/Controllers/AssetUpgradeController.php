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
                $a->computed_nbv = $summary['net_book_value'];
                $a->computed_remaining_months = $summary['remaining_months'];
                $a->computed_total_cost = $summary['total_historical_cost'];
                return $a;
            });

        $warehouses = Warehouse::orderBy('name')->get();
        $selectedAssetId = $request->query('asset_id');

        return view('fixed_assets.upgrades.create', compact('assets', 'warehouses', 'selectedAssetId'));
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
            'fixed_asset_id'             => 'required|exists:fixed_assets,id',
            'upgrade_date'               => 'required|date',
            'accounting_treatment'       => 'required|in:EXPENSE,CAPITALIZE_VALUE,CAPITALIZE_EXTEND',
            'new_remaining_months'       => 'required_if:accounting_treatment,CAPITALIZE_EXTEND|nullable|integer|min:1|max:600',
            'notes'                      => 'nullable|string',
            'parts'                      => 'required|array|min:1',
            'parts.*.source_type'        => 'required|in:WAREHOUSE,DIRECT_SERVICE',
            'parts.*.warehouse_id'       => 'required_if:parts.*.source_type,WAREHOUSE|nullable|exists:warehouses,id',
            'parts.*.item_id'            => 'required_if:parts.*.source_type,WAREHOUSE|nullable|exists:items,id',
            'parts.*.component_name'     => 'required|string|max:255',
            'parts.*.qty'                => 'required|numeric|min:0.01',
            'parts.*.unit_cost'          => 'required|numeric|min:0',
            'parts.*.purchase_order_id'  => 'nullable|exists:purchase_orders,id',
            'parts.*.goods_receipt_id'   => 'nullable|exists:goods_receipts,id',
        ], [
            'parts.required'                     => 'Tambahkan minimal 1 komponen / part upgrade.',
            'new_remaining_months.required_if'   => 'Estimasi sisa umur ekonomis baru (bulan) wajib diisi jika memilih perpanjangan umur aset.',
        ]);

        try {
            $upgNumber = null;

            DB::transaction(function () use ($request, &$upgNumber) {
                $asset = FixedAsset::with(['assetCategory', 'upgrades', 'currency'])->lockForUpdate()->findOrFail($request->fixed_asset_id);

                // Hitung posisi nilai buku & sisa umur tepat pada tanggal upgrade
                $summaryBefore   = $asset->calculateDepreciationSummary($request->upgrade_date);
                $nbvBefore       = $summaryBefore['net_book_value'];
                $remMonthsBefore = $summaryBefore['remaining_months'];

                $treatment     = $request->accounting_treatment;
                $isCapitalized = in_array($treatment, ['CAPITALIZE_VALUE', 'CAPITALIZE_EXTEND']);

                // Pengaman Akuntansi: Jika sisa umur aset sudah 0 (misal umur 10 thn > 4 thn) dan user pilih CAPITALIZE_VALUE tanpa umur baru
                if ($treatment === 'CAPITALIZE_VALUE' && $remMonthsBefore <= 0) {
                    throw new \Exception(
                        "VALIDASI AKUNTANSI: Sisa umur ekonomis aset ini sudah habis (0 bulan, Nilai Buku Rp " .
                        number_format($nbvBefore, 0, ',', '.') .
                        "). Jika ingin mengkapitalisasi biaya upgrade ke nilai aset, pilih opsi 'Kapitalisasi Nilai + Setel/Perpanjang Sisa Umur Baru (Bulan)', atau pilih 'Beban Pemeliharaan' jika tidak menambah nilai aset."
                    );
                }

                // Hitung total biaya seluruh part yang diinput
                $totalUpgradeCost = 0;
                foreach ($request->parts as $p) {
                    $totalUpgradeCost += ((float) $p['qty']) * ((float) $p['unit_cost']);
                }

                $nbvAfter     = $isCapitalized ? ($nbvBefore + $totalUpgradeCost) : $nbvBefore;
                $newRemMonths = ($treatment === 'CAPITALIZE_EXTEND') ? (int) $request->new_remaining_months : null;

                // Generate Nomor Dokumen Pengeluaran Upgrade (UPG/YYYY/MM/XXXX)
                $yearMonth = date('Y/m');
                $lastUpg = FixedAssetUpgrade::where('upgrade_number', 'like', "UPG/{$yearMonth}/%")
                    ->orderBy('id', 'desc')->lockForUpdate()->first();
                $nextSeq = $lastUpg ? ((int) substr($lastUpg->upgrade_number, -4)) + 1 : 1;
                $upgNumber = "UPG/{$yearMonth}/" . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

                // Cek kolom valuasi pada stock_mutations & inventory_stocks
                $smCols  = \Illuminate\Support\Facades\Schema::getColumnListing('stock_mutations');
                $invCols = \Illuminate\Support\Facades\Schema::getColumnListing('inventory_stocks');

                $partDescriptions = [];
                $faLabel = $asset->accounting_asset_number ? " / FA: {$asset->accounting_asset_number}" : "";

                foreach ($request->parts as $p) {
                    $qty      = (float) $p['qty'];
                    $unitCost = (float) $p['unit_cost'];
                    $lineCost = $qty * $unitCost;
                    $compName = trim($p['component_name']);
                    $poId     = !empty($p['purchase_order_id']) ? (int) $p['purchase_order_id'] : null;
                    $grId     = !empty($p['goods_receipt_id']) ? (int) $p['goods_receipt_id'] : null;

                    $poNumberText = $poId ? (DB::table('purchase_orders')->where('id', $poId)->value('po_number') ?? '-') : 'Non-PO/Manual';
                    $grNumberText = $grId ? (DB::table('goods_receipts')->where('id', $grId)->value('gr_number') ?? '-') : '-';

                    if ($p['source_type'] === 'WAREHOUSE') {
                        $whId   = (int) $p['warehouse_id'];
                        $itemId = (int) $p['item_id'];

                        $item = Item::lockForUpdate()->findOrFail($itemId);
                        $invStock = InventoryStock::where('item_id', $itemId)
                            ->where('warehouse_id', $whId)
                            ->lockForUpdate()
                            ->first();

                        if (!$invStock || (float) $invStock->stock_qty < $qty) {
                            $avail = $invStock ? (float) $invStock->stock_qty : 0;
                            throw new \Exception("Stok '{$item->name}' di gudang tidak mencukupi (Tersedia: {$avail}, Diminta: {$qty}).");
                        }

                        // 1. Potong kuantitas & nilai valuasi pada InventoryStock gudang
                        $whQtyBefore = (float) $invStock->stock_qty;
                        $whQtyAfter  = max(0, $whQtyBefore - $qty);
                        $invUpdateData = ['stock_qty' => $whQtyAfter];

                        if (in_array('stock_value', $invCols)) {
                            $invUpdateData['stock_value'] = max(0, (float) $invStock->stock_value - $lineCost);
                        }
                        if (in_array('total_value', $invCols)) {
                            $invUpdateData['total_value'] = max(0, (float) $invStock->total_value - $lineCost);
                        }
                        $invStock->update($invUpdateData);

                        // 2. Potong kuantitas pada Master Item
                        $beforeMaster = (float) $item->current_stock;
                        $afterMaster  = max(0, $beforeMaster - $qty);
                        $item->update(['current_stock' => $afterMaster]);

                        // 3. Susun catatan kartu stok yang lengkap & eksplisit (Qty + Nilai + Referensi PO/GR + Aset Tujuan)
                        $formattedUnitCost = number_format($unitCost, 0, ',', '.');
                        $formattedLineCost = number_format($lineCost, 0, ',', '.');
                        $treatmentShort    = $isCapitalized ? 'Kapitalisasi ke Aset' : 'Beban Pemeliharaan';
                        $holderName        = optional($asset->assignee)->name ? " (User: {$asset->assignee->name})" : "";
                        $faInfo            = $asset->accounting_asset_number ? " | No. FA: {$asset->accounting_asset_number}" : "";

                        $stockCardNote = "Upgrade Aset: {$asset->asset_number} - {$asset->name}{$faInfo}{$holderName} | " .
                                         "Komponen: {$compName} ({$qty} unit @ Rp {$formattedUnitCost} = Rp {$formattedLineCost}) | " .
                                         "Asal PO: {$poNumberText} (GR: {$grNumberText}) | Status: {$treatmentShort}";

                        $mutationData = [
                            'item_id'          => $item->id,
                            'warehouse_id'     => $whId,
                            'type'             => 'OUT',
                            'qty'              => $qty,
                            'balance_before'   => $beforeMaster,
                            'balance_after'    => $afterMaster,
                            'reference_number' => $upgNumber,
                            'notes'            => $stockCardNote,
                            'created_by'       => auth()->id(),
                        ];

                        // Isi kolom harga/nilai jika tersedia di tabel stock_mutations
                        if (in_array('unit_price', $smCols)) {
                            $mutationData['unit_price'] = $unitCost;
                        }
                        if (in_array('unit_cost', $smCols)) {
                            $mutationData['unit_cost'] = $unitCost;
                        }
                        if (in_array('total_price', $smCols)) {
                            $mutationData['total_price'] = $lineCost;
                        }
                        if (in_array('total_cost', $smCols)) {
                            $mutationData['total_cost'] = $lineCost;
                        }
                        if (in_array('total_value', $smCols)) {
                            $mutationData['total_value'] = $lineCost;
                        }

                        StockMutation::create($mutationData);

                        // 4. Simpan Rincian Upgrade Aset
                        FixedAssetUpgrade::create([
                            'upgrade_number'       => $upgNumber,
                            'fixed_asset_id'       => $asset->id,
                            'item_id'              => $item->id,
                            'warehouse_id'         => $whId,
                            'purchase_order_id'    => $poId,
                            'goods_receipt_id'     => $grId,
                            'upgrade_date'         => $request->upgrade_date,
                            'component_name'       => $compName,
                            'qty'                  => $qty,
                            'unit_cost'            => $unitCost,
                            'cost'                 => $lineCost,
                            'accounting_treatment' => $treatment,
                            'is_capitalized'       => $isCapitalized,
                            'book_value_before'    => $nbvBefore,
                            'book_value_after'     => $nbvAfter,
                            'new_remaining_months' => $newRemMonths,
                            'currency_id'          => $asset->currency_id ?? 1,
                            'vendor_or_executor'   => "Gudang (PO: {$poNumberText} | GR: {$grNumberText})",
                            'notes'                => $request->notes,
                            'created_by'           => auth()->id(),
                        ]);

                        $partDescriptions[] = "{$compName} ({$qty} unit @ Rp {$formattedUnitCost} = Rp {$formattedLineCost} | PO: {$poNumberText})";
                    } else {
                        // Jasa / Part Langsung Vendor tanpa stok gudang
                        $formattedLineCost = number_format($lineCost, 0, ',', '.');
                        FixedAssetUpgrade::create([
                            'upgrade_number'       => $upgNumber,
                            'fixed_asset_id'       => $asset->id,
                            'item_id'              => null,
                            'warehouse_id'         => null,
                            'purchase_order_id'    => $poId,
                            'goods_receipt_id'     => $grId,
                            'upgrade_date'         => $request->upgrade_date,
                            'component_name'       => $compName,
                            'qty'                  => $qty,
                            'unit_cost'            => $unitCost,
                            'cost'                 => $lineCost,
                            'accounting_treatment' => $treatment,
                            'is_capitalized'       => $isCapitalized,
                            'book_value_before'    => $nbvBefore,
                            'book_value_after'     => $nbvAfter,
                            'new_remaining_months' => $newRemMonths,
                            'currency_id'          => $asset->currency_id ?? 1,
                            'vendor_or_executor'   => $p['vendor_name'] ?? "Vendor / Jasa (PO: {$poNumberText})",
                            'notes'                => $request->notes,
                            'created_by'           => auth()->id(),
                        ]);

                        $partDescriptions[] = "{$compName} (Rp {$formattedLineCost} | PO: {$poNumberText})";
                    }
                }

                // Update keterangan perpanjangan umur di header aset jika dipilih
                if ($treatment === 'CAPITALIZE_EXTEND' && $newRemMonths) {
                    $asset->update(['extended_useful_life_months' => $newRemMonths]);
                }

                // 5. Susun Log Audit yang jelas di Timeline Riwayat Aset
                $treatmentSummary = match ($treatment) {
                    'CAPITALIZE_VALUE'  => "KAPITALISASI NILAI: Nilai Buku naik dari Rp " . number_format($nbvBefore, 0, ',', '.') . " menjadi Rp " . number_format($nbvAfter, 0, ',', '.') . " (Sisa umur tetap {$remMonthsBefore} bulan).",
                    'CAPITALIZE_EXTEND' => "KAPITALISASI + PERPANJANG UMUR: Nilai Buku naik dari Rp " . number_format($nbvBefore, 0, ',', '.') . " menjadi Rp " . number_format($nbvAfter, 0, ',', '.') . " & Sisa Umur Ekonomis ditetapkan menjadi {$newRemMonths} bulan sejak {$request->upgrade_date}.",
                    default             => "BEBAN PEMELIHARAAN (EXPENSE): Biaya Rp " . number_format($totalUpgradeCost, 0, ',', '.') . " dicatat sebagai perawatan tanpa mengubah Nilai Buku (tetap Rp " . number_format($nbvBefore, 0, ',', '.') . ") maupun umur aset.",
                };

                $historyNote = "[{$upgNumber}] UPGRADE KOMPONEN ASET: " . implode('; ', $partDescriptions) .
                               " | Perlakuan Akuntansi: {$treatmentSummary}" .
                               ($request->filled('notes') ? " | Keterangan: " . trim($request->notes) : "");

                FixedAssetHistory::create([
                    'fixed_asset_id' => $asset->id,
                    'status'         => 'UPGRADED',
                    'assigned_to'    => $asset->assigned_to,
                    'notes'          => $historyNote,
                    'created_by'     => auth()->id(),
                ]);
            });

            return redirect()->route('fixed-assets.upgrades.index')
                ->with('success', "Dokumen Upgrade Aset ({$upgNumber}) berhasil disimpan! Stok fisik & nilai pada Kartu Stok telah terpotong otomatis.");

        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
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
