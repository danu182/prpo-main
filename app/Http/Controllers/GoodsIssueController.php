<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Item;
use App\Models\InventoryStock;
use App\Models\Warehouse;
use App\Models\StockMutation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoodsIssueController extends Controller
{
    // ==========================================
    // 1. HALAMAN UTAMA GOODS ISSUE (PENGELUARAN)
    // ==========================================
    public function index(Request $request)
    {
        $search = $request->input('search');

        $goodsIssues = GoodsIssue::with(['issuer', 'items.item', 'status', 'warehouse'])
            ->when($search, function ($query) use ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('gi_number', 'like', "%{$search}%")
                      ->orWhere('requester_name', 'like', "%{$search}%")
                      ->orWhere('department', 'like', "%{$search}%")
                      ->orWhere('notes', 'like', "%{$search}%")
                      ->orWhereHas('items.item', function ($qItem) use ($search) {
                          $qItem->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                      });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('goods_issues.index', compact('goodsIssues', 'search'));
    }

    public function create()
    {
        $users = \App\Models\User::with(['company', 'department'])->orderBy('name', 'asc')->get();
        $warehouses = \App\Models\Warehouse::orderBy('name', 'asc')->get();

        return view('goods_issues.create', compact('users', 'warehouses'));
    }

    // ==========================================
    // 2. PROSES SIMPAN PENGELUARAN BARANG
    // ==========================================
    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id'   => 'required|exists:warehouses,id',
            'issue_date'     => 'required|date|before_or_equal:today',
            'requester_name' => 'required|string|max:255',
            'items'          => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.item_name' => 'nullable|string|max:255',
        ]);

        $warehouse = \App\Models\Warehouse::find($request->warehouse_id);
        if ($warehouse && $warehouse->is_frozen) {
            return back()->withInput()->with('error', "GAGAL: Gudang {$warehouse->name} sedang dalam status DIBEKUKAN (Stock Opname). Anda tidak dapat mengeluarkan barang dari gudang ini sampai proses audit selesai!");
        }

        $allSelectedAssets = [];
        foreach ($request->items as $data) {
            if (!empty($data['asset_ids'])) {
                $allSelectedAssets = array_merge($allSelectedAssets, $data['asset_ids']);
            }
            if (!empty($data['sn_list'])) {
                $allSelectedAssets = array_merge($allSelectedAssets, $data['sn_list']);
            }
        }

        if (count($allSelectedAssets) !== count(array_unique($allSelectedAssets))) {
            return back()->withInput()->with('error', 'GAGAL DARI SISTEM! Anda memilih Nomor Aset / SN yang sama. Satu barang fisik hanya bisa dikeluarkan satu kali!');
        }

        try {
            $gi = null;

            \DB::transaction(function () use ($request, &$gi) {
                $year = date('Y', strtotime($request->issue_date));
                $month = date('m', strtotime($request->issue_date));
                $companyCode = auth()->user()->company->code ?? 'HO';
                $prefix = "GI-{$companyCode}-{$year}-{$month}-";

                $lastGi = \App\Models\GoodsIssue::where('gi_number', 'like', "{$prefix}%")
                                ->orderBy('id', 'desc')->first();

                $nextId = $lastGi ? ((int) substr($lastGi->gi_number, -4)) + 1 : 1;
                $giNumber = $prefix . str_pad($nextId, 4, '0', STR_PAD_LEFT);
                $statusActiveId = \App\Models\Status::where('type', 'GI')->where('slug', 'active')->value('id') ?? 1;

                $gi = \App\Models\GoodsIssue::create([
                    'gi_number'      => $giNumber,
                    'issue_date'     => $request->issue_date,
                    'requester_name' => $request->requester_name,
                    'department'     => $request->department,
                    'warehouse_id'   => $request->warehouse_id,
                    'notes'          => $request->notes,
                    'status_id'      => $statusActiveId,
                    'issued_by'      => auth()->id(),
                ]);

                foreach ($request->items as $data) {
                    $item = \App\Models\Item::with(['uom'])->findOrFail($data['item_id']);
                    $satuanDasar = optional($item->uom)->name ?? 'Pieces';

                    $namaSpesifik = $data['item_name'] ?? $item->name;

                    $isModeAsset = !empty($data['asset_ids']);
                    $finalUomString = $satuanDasar;
                    $uomId = null;

                    $daftarInventarisBaru = [];
                    $snStringForNote = '';

                    $itemNote = $data['notes'] ?? '';
                    $selectedBatchId = $data['inventory_stock_id'] ?? null;

                    if (!empty($selectedBatchId) && !is_numeric($selectedBatchId)) {
                        $itemNote = "Ref GR: " . $selectedBatchId . ($itemNote ? " | " . $itemNote : "");
                    }

                    $isManualRefOverride = preg_match('/Ref (GR|PO|RTV|GI|SA)/i', $itemNote);

                    if ($isModeAsset) {
                        $assetIds = $data['asset_ids'];
                        $qtyRequested = count($assetIds);
                        $qtyInput = $qtyRequested;

                        $assetDetails = \App\Models\FixedAsset::whereIn('id', $assetIds)->get();
                        $assetInfoArr = [];
                        $userPenerima = \App\Models\User::where('name', $request->requester_name)->first();
                        $assignedToId = $userPenerima ? $userPenerima->id : null;
                        $statusInUse = \App\Models\Status::where('type', 'AST')->where('slug', 'in_use')->first();
                        $statusIdToUse = $statusInUse ? $statusInUse->id : 32;

                        foreach($assetDetails as $ad) {
                            $info = $ad->asset_number;
                            if($ad->serial_number) { $info .= " (SN: " . $ad->serial_number . ")"; }
                            $assetInfoArr[] = "- " . $info;

                            \App\Models\FixedAssetHistory::create([
                                'fixed_asset_id' => $ad->id,
                                'status'         => 'In Use (Dipakai)',
                                'assigned_to'    => $assignedToId,
                                'notes'          => "Dikeluarkan melalui Dokumen GI: {$giNumber}. Unit: {$namaSpesifik}",
                                'created_by'     => auth()->id(),
                            ]);

                            $ad->update([
                                'assigned_to' => $assignedToId,
                                'status_id'   => $statusIdToUse,
                                'notes'       => $ad->notes . "\n[Dikeluarkan via GI: {$giNumber}]"
                            ]);
                        }

                        $itemNotePrefix = "Dikeluarkan Aset:\n" . implode("\n", $assetInfoArr);
                        $itemNote = $itemNote ? $itemNotePrefix . "\nCatatan: " . $itemNote : $itemNotePrefix;

                        $balanceBefore = (float) $item->current_stock;
                        $balanceAfter = $balanceBefore - $qtyRequested;

                        \App\Models\StockMutation::create([
                            'item_id'          => $item->id,
                            'warehouse_id'     => $request->warehouse_id,
                            'type'             => 'OUT',
                            'qty'              => $qtyRequested,
                            'balance_before'   => $balanceBefore,
                            'balance_after'    => $balanceAfter,
                            'reference_number' => $giNumber,
                            'notes'            => "Aset ({$namaSpesifik}) diserahkan ke {$request->requester_name}.",
                            'created_by'       => auth()->id(),
                        ]);

                        $item->update(['current_stock' => $balanceAfter]);

                    } else {
                        $qtyInput = (float) ($data['qty_issued'] ?? 0);
                        $uomId = $data['uom_info'] ?? null;
                        $conversionFactor = 1;
                        $cleanUomName = $satuanDasar;

                        if (!empty($uomId)) {
                            $uomDb = \App\Models\ItemUom::find($uomId);
                            if ($uomDb) {
                                $conversionFactor = (float) $uomDb->conversion_qty;
                                $cleanUomName = $uomDb->uom_name;
                            }
                        }

                        $finalUomString = $cleanUomName;
                        if ($conversionFactor > 1) {
                            $finalUomString .= ' (Isi ' . $conversionFactor . ' ' . $satuanDasar . ')';
                        }
                        $qtyRequested = $qtyInput * $conversionFactor;

                        // =========================================================================
                        // 🔥 BACKEND PROTECTION: CEK SISA BATCH JIKA MEMILIH MANUAL 🔥
                        // =========================================================================
                        if (!empty($selectedBatchId) && !is_numeric($selectedBatchId)) {
                            $totalOutForCheck = \App\Models\StockMutation::where('item_id', $item->id)
                                ->where('warehouse_id', $request->warehouse_id)
                                ->where('type', '!=', 'IN')
                                ->sum('qty');

                            $inMutationsForCheck = \App\Models\StockMutation::where('item_id', $item->id)
                                ->where('warehouse_id', $request->warehouse_id)
                                ->where('type', 'IN')
                                ->orderBy('created_at', 'asc')
                                ->orderBy('id', 'asc')
                                ->get();

                            $sisaBatchDipilih = 0;
                            foreach ($inMutationsForCheck as $mut) {
                                $qtyIn = (float) $mut->qty;
                                if ($totalOutForCheck >= $qtyIn) {
                                    $totalOutForCheck -= $qtyIn;
                                } else {
                                    $sisaQ = $qtyIn - $totalOutForCheck;
                                    $totalOutForCheck = 0;
                                    if ($mut->reference_number === $selectedBatchId) {
                                        $sisaBatchDipilih = $sisaQ;
                                        break;
                                    }
                                }
                            }

                            if ($qtyRequested > $sisaBatchDipilih) {
                                throw new \Exception("DITOLAK SISTEM: Stok pada Batch {$selectedBatchId} hanya tersisa {$sisaBatchDipilih} {$satuanDasar}, tetapi Anda mencoba mengeluarkan {$qtyRequested}. Silakan 'Tambah Barang' lagi untuk memecah kuantitas ke Batch yang lain, atau gunakan Mode Otomatis.");
                            }
                        }

                        if (isset($item->is_trackable) && $item->is_trackable) {
                            $snList = $data['sn_list'] ?? [];
                            if (empty($snList) || count($snList) < intval($qtyRequested)) {
                                throw new \Exception("Wajib memilih Serial Number (SN) sebanyak " . intval($qtyRequested) . " unit untuk barang " . $item->name);
                            }

                            \DB::table('item_serials')
                                ->whereIn('serial_number', $snList)
                                ->update([
                                    'status' => 'IN_USE',
                                    'updated_at' => now()
                                ]);

                            $kodeCompany = auth()->user()->company->code ?? 'HO';
                            $bulanTahun = date('ym');
                            foreach ($snList as $sn) {
                                $kodeUnik = strtoupper(substr(uniqid(), -4));
                                $daftarInventarisBaru[] = "INV-{$kodeCompany}-{$bulanTahun}-{$kodeUnik} [SN: " . trim($sn) . "]";
                            }

                            if (count($snList) > 3) {
                                $snStringForNote = implode(', ', array_slice($snList, 0, 3)) . " ... (+" . (count($snList) - 3) . " unit)";
                            } else {
                                $snStringForNote = implode(', ', $snList);
                            }
                        }

                        if ($snStringForNote) {
                            $itemNote .= ($itemNote ? " | " : "") . "SN: " . $snStringForNote;
                        }

                        $issueMethod = $item->issue_method ?? 'FIFO';
                        $sortDirection = ($issueMethod === 'LIFO') ? 'desc' : 'asc';

                        $query = \App\Models\InventoryStock::where('warehouse_id', $request->warehouse_id)
                                    ->where('item_id', $item->id)->where('stock_qty', '>', 0);

                        $query->orderBy('created_at', $sortDirection);

                        $availableStocks = $query->lockForUpdate()->get();
                        $totalAvailable = $availableStocks->sum('stock_qty');

                        if ($totalAvailable < $qtyRequested) {
                            throw new \Exception("Stok {$namaSpesifik} tidak cukup! Diminta: {$qtyRequested} {$satuanDasar}, Sisa: {$totalAvailable} {$satuanDasar}");
                        }

                        $qtySisa = $qtyRequested;
                        $saldoTotalSaatIni = (float) $item->current_stock;

                        $trueOutBefore = \App\Models\StockMutation::where('item_id', $item->id)
                            ->where('warehouse_id', $request->warehouse_id)
                            ->where('type', '!=', 'IN')
                            ->where('reference_number', 'not like', 'GI-AST%')
                            ->sum('qty');

                        $inMutations = \App\Models\StockMutation::where('item_id', $item->id)
                            ->where('warehouse_id', $request->warehouse_id)
                            ->where('type', 'IN')
                            ->orderBy('created_at', 'asc')
                            ->orderBy('id', 'asc')
                            ->get();

                        $allFifoRefsItem = [];

                        foreach ($availableStocks as $stockRow) {
                            if ($qtySisa <= 0) break;
                            $potong = min($stockRow->stock_qty, $qtySisa);
                            $balanceBefore = $saldoTotalSaatIni;
                            $balanceAfter = $saldoTotalSaatIni - $potong;
                            $saldoTotalSaatIni = $balanceAfter;

                            $stockRow->decrement('stock_qty', $potong);
                            $qtySisa -= $potong;

                            $fifoRefs = [];

                            if (!$isManualRefOverride) {
                                $qtyToFind = $potong;
                                $skipped = 0;
                                $tempTrueOut = $trueOutBefore;

                                foreach($inMutations as $inMut) {
                                    if ($skipped + $inMut->qty <= $tempTrueOut) {
                                        $skipped += $inMut->qty;
                                        continue;
                                    }

                                    $availableInThisMut = $inMut->qty - max(0, $tempTrueOut - $skipped);
                                    if ($availableInThisMut <= 0) {
                                        $skipped += $inMut->qty;
                                        continue;
                                    }

                                    $take = min($availableInThisMut, $qtyToFind);
                                    $qtyToFind -= $take;

                                    $ref = $inMut->reference_number;
                                    if ($ref && !in_array($ref, $fifoRefs)) {
                                        if (str_starts_with($ref, 'GR')) $fifoRefs[] = "Ref GR: $ref";
                                        elseif (str_starts_with($ref, 'SA')) $fifoRefs[] = "Ref GR: $ref (Saldo Awal)";
                                        else $fifoRefs[] = "Ref GR: $ref";
                                    }

                                    $tempTrueOut += $take;
                                    if ($qtyToFind <= 0) break;
                                }
                                $trueOutBefore += $potong;
                            }

                            $fifoString = !empty($fifoRefs) ? implode(', ', $fifoRefs) : '';
                            if (!empty($fifoRefs)) {
                                $allFifoRefsItem = array_merge($allFifoRefsItem, $fifoRefs);
                            }

                            $mutasiNoteExt = "";
                            if (!empty($snStringForNote)) {
                                $mutasiNoteExt .= " [SN: {$snStringForNote}]";
                            }

                            if (!empty($fifoString)) {
                                $mutasiNoteExt .= "<br><small class='text-muted'>(Auto-FIFO: {$fifoString})</small>";
                            }

                            $catatanUser = $itemNote ? " - " . $itemNote : "";

                            \App\Models\StockMutation::create([
                                'item_id'          => $item->id,
                                'warehouse_id'     => $request->warehouse_id,
                                'type'             => 'OUT',
                                'qty'              => $potong,
                                'balance_before'   => $balanceBefore,
                                'balance_after'    => $balanceAfter,
                                'reference_number' => $giNumber,
                                'notes'            => "Keluar ke {$request->requester_name}. Barang: {$namaSpesifik}{$catatanUser}{$mutasiNoteExt}",
                                'created_by'       => auth()->id(),
                            ]);
                        }

                        $item->update(['current_stock' => $saldoTotalSaatIni]);

                        $finalFifoText = implode(', ', array_unique($allFifoRefsItem));
                        if (!$isManualRefOverride && !empty($finalFifoText)) {
                            $itemNote = trim($itemNote . " | Auto-Trace: {$finalFifoText}", ' |');
                        }
                    }

                    \App\Models\GoodsIssueItem::create([
                        'goods_issue_id' => $gi->id,
                        'item_id'        => $item->id,
                        'item_name'      => $namaSpesifik,
                        'qty_issued'     => $qtyInput,
                        'uom_id'         => $uomId ?: null,
                        'uom'            => $finalUomString,
                        'notes'          => $itemNote,
                    ]);

                    if (!$isModeAsset && isset($item->is_trackable) && $item->is_trackable) {
                        foreach ($daftarInventarisBaru as $invRecord) {
                            \App\Models\EmployeeInventory::create([
                                'employee_name'    => $request->requester_name,
                                'item_id'          => $item->id,
                                'qty'              => 1,
                                'specific_details' => $invRecord,
                            ]);
                        }

                        $invStringForNote = count($daftarInventarisBaru) > 3
                            ? implode(', ', array_slice($daftarInventarisBaru, 0, 3)) . ' ... (+' . (count($daftarInventarisBaru) - 3) . ' unit)'
                            : implode(', ', $daftarInventarisBaru);

                        \App\Models\EmployeeInventoryHistory::create([
                            'employee_name'    => $request->requester_name,
                            'item_id'          => $item->id,
                            'type'             => 'IN',
                            'qty'              => $qtyRequested,
                            'reference_number' => $giNumber,
                            'notes'            => "Diserahkan ke karyawan via GI: {$giNumber}. Unit: " . $invStringForNote,
                        ]);
                    }
                }
            });

            return redirect()->route('goods-issues.show', $gi->gi_number)
                             ->with(['success' => 'Pengeluaran Berhasil! Stok fisik telah terpotong dan jejak referensi terekam sempurna.', 'print_gi_slug' => $gi->gi_number]);

        } catch (\Exception $e) {
            \Log::error('Error Simpan GI: ' . $e->getMessage() . ' - L: ' . $e->getLine());
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show($slug)
    {
        $gi = GoodsIssue::with(['items.item.uom', 'issuer', 'status', 'warehouse', 'returns.warehouse'])
                ->where('gi_number', $slug)
                ->firstOrFail();

        return view('goods_issues.show', compact('gi'));
    }

    public function printLabels($slug)
    {
        $gi = GoodsIssue::with(['items.item', 'issuer'])->where('gi_number', $slug)->firstOrFail();

        $labelItems = $gi->items->filter(function ($giItem) {
            $masterItem = $giItem->item;
            return $masterItem && ($masterItem->is_asset || $masterItem->is_trackable);
        });

        if ($labelItems->isEmpty()) {
            return back()->with('error', 'Cetak Ditolak: Tidak ada Aset Tetap atau Minor Asset di dokumen ini.');
        }

        return view('goods_issues.print_labels', compact('gi', 'labelItems'));
    }

    public function print($slug)
    {
        $gi = \App\Models\GoodsIssue::with(['items.item.uom', 'issuer', 'warehouse'])
                ->where('gi_number', $slug)
                ->firstOrFail();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('goods_issues.print', compact('gi'))
                    ->setPaper('A4', 'portrait');

        return $pdf->stream('Pengeluaran_Stok_' . str_replace('/', '_', $gi->gi_number) . '.pdf');
    }

    public function printBast($slug)
    {
        $gi = \App\Models\GoodsIssue::with(['items.item', 'warehouse'])
                ->where('gi_number', $slug)
                ->firstOrFail();

        $assets = \App\Models\FixedAsset::with('item')
                    ->where('notes', 'like', "%{$gi->gi_number}%")
                    ->get();

        if ($assets->isEmpty()) {
            return back()->with('error', 'Tidak ada Aset Tetap yang diserahkan pada dokumen GI ini.');
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('goods_issues.bast', compact('gi', 'assets'))
                    ->setPaper('A4', 'portrait');

        return $pdf->stream('BAST_' . str_replace('/', '_', $gi->gi_number) . '.pdf');
    }

    public function voidTransaction($slug)
    {
        try {
            DB::beginTransaction();

            $gi = \App\Models\GoodsIssue::with(['items.item', 'status'])->where('gi_number', $slug)->firstOrFail();

            if (optional($gi->status)->slug === 'void') {
                throw new \Exception("Transaksi ini sudah berstatus VOID.");
            }

            $hasReturns = \App\Models\GoodsIssueReturn::where('goods_issue_id', $gi->id)->exists();
            if ($hasReturns) {
                throw new \Exception("GAGAL VOID: Transaksi ini sudah memiliki riwayat Retur.");
            }

            $txMonthYear = \Carbon\Carbon::parse($gi->created_at)->format('Y-m');
            $currentMonthYear = \Carbon\Carbon::now()->format('Y-m');

            if ($txMonthYear !== $currentMonthYear) {
                throw new \Exception("GAGAL VOID: Laporan bulan {$txMonthYear} sudah ditutup! Gunakan fitur Retur atau Adjustment.");
            }

            foreach ($gi->items as $giItem) {
                $masterItem = \App\Models\Item::lockForUpdate()->find($giItem->item_id);
                if (!$masterItem) continue;

                preg_match_all('/AST\/[0-9]{4}\/[0-9]{2}\/[0-9]{4}/', $giItem->notes, $matches);
                $borrowedAssets = $matches[0];
                $isAssetIssue = !empty($borrowedAssets);

                if ($isAssetIssue) {
                    $assetsToRestore = \App\Models\FixedAsset::whereIn('asset_number', $borrowedAssets)->get();
                    $qtyToRestore = $assetsToRestore->count();
                    $statusIdAvailable = \App\Models\Status::where('type', 'AST')->where('slug', 'available')->value('id');

                    foreach ($assetsToRestore as $asset) {
                        \App\Models\FixedAssetHistory::create([
                            'fixed_asset_id' => $asset->id,
                            'status'         => 'Available (Tersedia)',
                            'assigned_to'    => null,
                            'notes'          => "Dikembalikan otomatis (Dokumen {$gi->gi_number} di-VOID).",
                            'created_by'     => auth()->id(),
                        ]);

                        $asset->update([
                            'status_id'    => $statusIdAvailable,
                            'assigned_to'  => null,
                            'warehouse_id' => $gi->warehouse_id,
                        ]);
                    }

                    $balanceBefore = (float) $masterItem->current_stock;
                    $balanceAfter = $balanceBefore + $qtyToRestore;

                    \App\Models\StockMutation::create([
                        'item_id'          => $masterItem->id,
                        'warehouse_id'     => $gi->warehouse_id,
                        'type'             => 'IN',
                        'qty'              => $qtyToRestore,
                        'balance_before'   => $balanceBefore,
                        'balance_after'    => $balanceAfter,
                        'reference_number' => $gi->gi_number . '-VOID',
                        'notes'            => "Pembatalan Transaksi Aset (VOID). Stok master dikembalikan.",
                        'created_by'       => auth()->id(),
                    ]);

                    $masterItem->update(['current_stock' => $balanceAfter]);

                } else {
                    $conversionFactor = 1;
                    if ($giItem->uom_id) {
                        $uomDb = \App\Models\ItemUom::find($giItem->uom_id);
                        if ($uomDb) $conversionFactor = (float) $uomDb->conversion_qty;
                    } elseif (preg_match('/Isi\s+([0-9.]+)/i', $giItem->uom, $matches)) {
                        $conversionFactor = (float) $matches[1];
                    }

                    $qtyToRestore = ((float) $giItem->qty_issued) * $conversionFactor;
                    $balanceBefore = (float) $masterItem->current_stock;
                    $balanceAfter = $balanceBefore + $qtyToRestore;

                    $invStock = \App\Models\InventoryStock::where('item_id', $masterItem->id)
                                    ->where('warehouse_id', $gi->warehouse_id)->first();

                    if ($invStock) {
                        $invStock->increment('stock_qty', $qtyToRestore);
                    } else {
                        \App\Models\InventoryStock::create([
                            'company_id'       => auth()->user()->company_id ?? 1,
                            'warehouse_id'     => $gi->warehouse_id,
                            'item_id'          => $masterItem->id,
                            'stock_qty'        => $qtyToRestore,
                            'reference_number' => $gi->gi_number . '-VOID',
                            'notes'            => 'Pengembalian Stok karena Batal (VOID)',
                        ]);
                    }

                    \App\Models\StockMutation::create([
                        'item_id'          => $masterItem->id,
                        'warehouse_id'     => $gi->warehouse_id,
                        'type'             => 'IN',
                        'qty'              => $qtyToRestore,
                        'balance_before'   => $balanceBefore,
                        'balance_after'    => $balanceAfter,
                        'reference_number' => $gi->gi_number . '-VOID',
                        'notes'            => "Pembatalan Transaksi (VOID). Stok dikembalikan.",
                        'created_by'       => auth()->id(),
                    ]);

                    $masterItem->update(['current_stock' => $balanceAfter]);

                    if (isset($masterItem->is_trackable) && $masterItem->is_trackable) {
                        preg_match_all('/SN:\s*([a-zA-Z0-9\-_]+)/', $giItem->notes, $snMatches);
                        $borrowedSns = $snMatches[1] ?? [];

                        if (!empty($borrowedSns)) {
                            \DB::table('item_serials')
                                ->whereIn('serial_number', $borrowedSns)
                                ->update(['status' => 'AVAILABLE', 'updated_at' => now()]);
                        }

                        $empInventory = \App\Models\EmployeeInventory::where(['employee_name' => $gi->requester_name, 'item_id' => $masterItem->id])->first();

                        if ($empInventory && !empty($borrowedSns)) {
                            $currentSns = array_filter(array_map('trim', explode('|', $empInventory->specific_details)));
                            $empInventory->specific_details = implode(' | ', array_diff($currentSns, $borrowedSns));
                            $empInventory->qty = max(0, $empInventory->qty - $qtyToRestore);
                            $empInventory->save();

                            \App\Models\EmployeeInventoryHistory::create([
                                'employee_name'    => $gi->requester_name,
                                'item_id'          => $masterItem->id,
                                'type'             => 'OUT',
                                'qty'              => $qtyToRestore,
                                'reference_number' => $gi->gi_number . '-VOID',
                                'notes'            => "Penarikan otomatis karena transaksi VOID. SN: " . implode(', ', $borrowedSns),
                            ]);
                        }
                    }
                }
            }

            $statusVoidId = \App\Models\Status::where('type', 'GI')->where('slug', 'void')->value('id');

            $gi->update([
                'status_id' => $statusVoidId,
                'notes'     => $gi->notes . ' | [DIBATALKAN PADA ' . date('d-m-Y H:i') . ']'
            ]);

            DB::commit();
            return back()->with('success', 'Hebat! Transaksi berhasil di-VOID dan seluruh status telah dikembalikan dengan presisi.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    public function searchItems(Request $request)
    {
        $search = $request->search;
        $warehouseId = $request->warehouse_id;

        $items = \App\Models\Item::where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            })
            ->with(['uom', 'uoms'])
            ->limit(10)
            ->get();

        $results = [];
        foreach ($items as $item) {
            $availableBulk = \App\Models\InventoryStock::where('item_id', $item->id)
                                ->where('warehouse_id', $warehouseId)
                                ->sum('stock_qty');

            $assetStock = \App\Models\FixedAsset::where('item_id', $item->id)
                                ->where('warehouse_id', $warehouseId)
                                ->whereHas('status', function($q) {
                                    $q->where('slug', 'available');
                                })->count();

            $totalStockDisplay = $availableBulk + $assetStock;

            if ($totalStockDisplay <= 0) continue;

            $historicalNames = \App\Models\PurchaseOrderItem::where('item_id', $item->id)
                                ->whereNotNull('item_name')
                                ->distinct()
                                ->pluck('item_name')
                                ->toArray();

            if (!in_array($item->name, $historicalNames)) {
                array_unshift($historicalNames, $item->name);
            }

            $text = "[{$item->code}] {$item->name} ";
            if ($assetStock > 0 || $availableBulk > 0) {
                $text .= "(Biasa: {$availableBulk} | Aset: {$assetStock})";
            }

            $results[] = [
                'id' => $item->id,
                'text' => $text,
                'raw_name' => $item->name,
                'historical_names' => $historicalNames,
                'is_asset' => $item->is_asset,
                'is_trackable' => $item->is_trackable,
                'stock' => $totalStockDisplay,
                'available_bulk' => $availableBulk,
                'available_asset' => $assetStock,
                'uoms' => $item->uoms,
                'base_uom_name' => optional($item->uom)->name ?? 'PCS'
            ];
        }

        return response()->json($results);
    }

    public function searchFixedAssets(Request $request)
    {
        $search = $request->search;
        $itemId = $request->item_id;
        $warehouseId = $request->warehouse_id;

        try {
            $assets = \App\Models\FixedAsset::where('item_id', $itemId)
                ->where(function($q) use ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId)->orWhereNull('warehouse_id');
                })
                ->where(function($q) {
                    $q->whereNull('assigned_to')->orWhereIn('status_id', [1, 30]);
                })
                ->when($search, function($query) use ($search) {
                    $query->where(function($q) use ($search) {
                        $q->where('asset_number', 'like', "%{$search}%")
                          ->orWhere('serial_number', 'like', "%{$search}%")
                          ->orWhere('accounting_asset_number', 'like', "%{$search}%")
                          ->orWhere('name', 'like', "%{$search}%");
                    });
                })
                ->limit(50)
                ->get();

            $formatted = [];
            foreach ($assets as $asset) {
                $text = $asset->asset_number . ' (' . $asset->name . ')';
                if (!empty($asset->accounting_asset_number)) $text .= ' | FA: ' . $asset->accounting_asset_number;
                if (!empty($asset->serial_number)) $text .= ' | SN: ' . $asset->serial_number;

                $formatted[] = ['id' => $asset->id, 'text' => $text];
            }

            return response()->json($formatted);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error Search Asset GI: " . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan sistem'], 500);
        }
    }

    // =========================================================================
    // 🔥 PENCARIAN BATCH: SANGAT CEPAT, ANTI LAG (MAKS 50 DATA) 🔥
    // =========================================================================
    public function searchBatches(Request $request)
    {
        try {
            $itemId = $request->item_id;
            $warehouseId = $request->warehouse_id;
            $search = strtolower($request->search ?? '');

            // 1. Dapatkan Total Barang Keluar
            $totalOut = \App\Models\StockMutation::where('item_id', $itemId)
                ->where('warehouse_id', $warehouseId)
                ->where('type', '!=', 'IN')
                ->sum('qty');

            // 2. Dapatkan Semua Barang Masuk (Ini dieksekusi DB dalam hitungan milidetik)
            $inMutations = \App\Models\StockMutation::where('item_id', $itemId)
                ->where('warehouse_id', $warehouseId)
                ->where('type', 'IN')
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $formatted = [];

            // Masukkan FIFO jika tidak sedang mencari spesifik, atau jika mengetik "otomatis/fifo"
            if (empty($search) || str_contains('fifo', $search) || str_contains('otomatis', $search)) {
                $formatted[] = ['id' => '', 'text' => '⚡ Mode Otomatis (FIFO)'];
            }

            $activeBatches = [];
            foreach ($inMutations as $mut) {
                $qtyIn = (float) $mut->qty;
                if ($totalOut >= $qtyIn) {
                    $totalOut -= $qtyIn; // GR ini sudah habis, buang!
                    continue;
                } else {
                    $sisaQty = $qtyIn - $totalOut;
                    $totalOut = 0;
                    $activeBatches[] = [
                        'mutation' => $mut,
                        'sisa' => $sisaQty
                    ];
                }
            }

            // Balik urutan agar GR terbaru tampil di atas
            $activeBatches = array_reverse($activeBatches);

            // Eager load info GR agar tidak terjadi query berulang (N+1 Problem) yang bikin lemot
            $grNumbers = [];
            foreach ($activeBatches as $batch) {
                if (str_starts_with($batch['mutation']->reference_number, 'GR')) {
                    $grNumbers[] = $batch['mutation']->reference_number;
                }
            }

            $grs = \App\Models\GoodsReceipt::with('purchaseOrder.vendor')
                        ->whereIn('gr_number', array_unique($grNumbers))
                        ->get()
                        ->keyBy('gr_number');

            $count = 0;
            foreach ($activeBatches as $batch) {
                if ($count >= 50) break; // LIMIT 50 DATA AGAR BROWSER TIDAK NGEHANG

                $mut = $batch['mutation'];
                $sisa = $batch['sisa'];

                $vendorName = '-';
                if (isset($grs[$mut->reference_number]) && $grs[$mut->reference_number]->purchaseOrder && $grs[$mut->reference_number]->purchaseOrder->vendor) {
                    $vendorName = $grs[$mut->reference_number]->purchaseOrder->vendor->name;
                }

                $date = \Carbon\Carbon::parse($mut->created_at)->format('d/m/Y');
                $text = "[Sisa: {$sisa}] | {$mut->reference_number} | Vendor: {$vendorName} | Tgl: {$date}";

                // Saring berdasarkan ketikan user (Live Search)
                if (!empty($search) && !str_contains(strtolower($text), $search)) {
                    continue;
                }

                $formatted[] = ['id' => $mut->reference_number, 'text' => $text, 'sisa' => $sisa];
                $count++;
            }

            return response()->json($formatted);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error Search Batches: " . $e->getMessage());
            return response()->json(['error' => 'Gagal memuat batch'], 500);
        }
    }

    public function searchSns(Request $request)
    {
        $search = $request->search;
        $itemId = $request->item_id;
        $warehouseId = $request->warehouse_id;

        $query = \DB::table('item_serials')
                    ->where('item_id', $itemId)
                    ->where('status', 'AVAILABLE');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($search) {
            $query->where('serial_number', 'like', "%{$search}%");
        }

        $sns = $query->limit(50)->get();

        $results = [];
        foreach ($sns as $sn) {
            $results[] = [
                'id'   => $sn->serial_number,
                'text' => $sn->serial_number
            ];
        }

        return response()->json($results);
    }
}
