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

        // 🔥 TAMBAHKAN 'warehouse' DI DALAM KURUNG SIKU INI 🔥
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
        $users = \App\Models\User::orderBy('name', 'asc')->get();
        $warehouses = Warehouse::orderBy('name', 'asc')->get();
        return view('goods_issues.create', compact('users', 'warehouses'));
    }

    // ==========================================
    // 2. PROSES SIMPAN PENGELUARAN BARANG (FINAL HYBRID + BAST READY)
    // ==========================================
    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id'   => 'required|exists:warehouses,id',
            'issue_date'     => 'required|date|before_or_equal:today',
            'requester_name' => 'required|string|max:255',
            'items'          => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
        ]);

        // 🔥 GEMBOK ANTI DUPLIKAT ASET TETAP
        $allSelectedAssets = [];
        foreach ($request->items as $data) {
            if (!empty($data['asset_ids'])) {
                $allSelectedAssets = array_merge($allSelectedAssets, $data['asset_ids']);
            }
        }

        if (count($allSelectedAssets) !== count(array_unique($allSelectedAssets))) {
            return back()->withInput()->with('error', 'GAGAL DARI SISTEM! Anda memilih Nomor Aset / SN yang sama. Satu barang fisik hanya bisa dikeluarkan satu kali!');
        }

        try {
            $gi = null;

            \DB::transaction(function () use ($request, &$gi) {
                // A. Generate Nomor GI
                $year = date('Y', strtotime($request->issue_date));
                $month = date('m', strtotime($request->issue_date));
                $lastGi = \App\Models\GoodsIssue::whereYear('created_at', $year)->whereMonth('created_at', $month)->orderBy('id', 'desc')->first();
                $nextId = $lastGi ? ((int) substr($lastGi->gi_number, -4)) + 1 : 1;
                $giNumber = 'GI-' . $year . '-' . $month . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

                // Tarik ID status Aktif
                $statusActiveId = \App\Models\Status::where('type', 'GI')->where('slug', 'active')->value('id') ?? 1;

                // B. Simpan Header Dokumen
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

                // C. Simpan Detail & POTONG STOK
                foreach ($request->items as $data) {
                    $item = \App\Models\Item::with(['uom'])->findOrFail($data['item_id']);
                    $satuanDasar = optional($item->uom)->name ?? 'Unit';

                    // 🔥 CEK HYBRID: Apakah staf memilih mengeluarkan Aset?
                    $isModeAsset = !empty($data['asset_ids']);

                    if ($isModeAsset) {
                        // ==========================================
                        // 1. LOGIKA PENGELUARAN ASET TETAP
                        // ==========================================
                        $assetIds = $data['asset_ids'];
                        $qtyRequested = count($assetIds); // Qty otomatis dari jumlah aset yang dipilih
                        
                        $assetDetails = \App\Models\FixedAsset::whereIn('id', $assetIds)->get();
                        $assetInfoArr = [];

                        $userPenerima = \App\Models\User::where('name', $request->requester_name)->first();
                        $assignedToId = $userPenerima ? $userPenerima->id : null;

                        // Cari ID status "In Use" (Default 31 jika tidak ketemu)
                        $statusInUse = \App\Models\Status::where('type', 'AST')->where('slug', 'in_use')->first();
                        $statusIdToUse = $statusInUse ? $statusInUse->id : 31;

                        foreach($assetDetails as $ad) {
                            $info = $ad->asset_number;
                            if($ad->serial_number) { $info .= " (SN: " . $ad->serial_number . ")"; }
                            if($ad->accounting_asset_number) { $info .= " [FA: " . $ad->accounting_asset_number . "]"; }
                            
                            $assetInfoArr[] = "- " . $info;

                            \App\Models\FixedAssetHistory::create([
                                'fixed_asset_id' => $ad->id,
                                'status'         => 'In Use (Dipakai)',
                                'assigned_to'    => $assignedToId,
                                'notes'          => "Dikeluarkan melalui Dokumen GI: {$giNumber}.",
                                'created_by'     => auth()->id(),
                            ]);

                            // 🔥 SUNTIKKAN GI NUMBER AGAR BAST BISA MELACAKNYA 🔥
                            $ad->update([
                                'assigned_to' => $assignedToId,
                                'status_id'   => $statusIdToUse,
                                'notes'       => $ad->notes . "\n[Dikeluarkan via GI: {$giNumber}]"
                            ]);
                        }

                        $itemNote = "Dikeluarkan Aset:\n" . implode("\n", $assetInfoArr);
                        if (!empty($data['notes'])) {
                            $itemNote .= "\nCatatan: " . $data['notes'];
                        }

                    } else {
                        // ==========================================
                        // 2. LOGIKA PENGELUARAN STOK BIASA (BULK)
                        // ==========================================
                        $uomInfo = $data['uom_info'] ?? 'Unit|1';
                        $uomParts = explode('|', $uomInfo);
                        $uomNameDikeluarkan = $uomParts[0];
                        $uomConversion = isset($uomParts[1]) ? (float)$uomParts[1] : 1;

                        $qtyInput = (float) ($data['qty_issued'] ?? 0);
                        $qtyRequested = $qtyInput * $uomConversion;

                        if (isset($item->is_trackable) && $item->is_trackable) {
                            if (empty($data['sn_list']) || count($data['sn_list']) < intval($qtyRequested)) {
                                throw new \Exception("Wajib mengisi Nomor Seri (SN) sebanyak " . intval($qtyRequested) . " unit!");
                            }
                            $itemNote = "SN Detail: " . implode(', ', $data['sn_list']);
                        } else {
                            $itemNote = $data['notes'] ?? '';
                        }
                        
                        $itemNote = trim($itemNote . " | Dikeluarkan fisik: {$qtyInput} {$uomNameDikeluarkan}", ' |');
                    }

                    // ==========================================
                    // 3. LOGIKA POTONG STOK FISIK GUDANG (BERLAKU UNTUK KEDUANYA)
                    // ==========================================
                    $issueMethod = $item->issue_method ?? 'FIFO';
                    $sortDirection = ($issueMethod === 'LIFO') ? 'desc' : 'asc';

                    $query = \App\Models\InventoryStock::where('warehouse_id', $request->warehouse_id)
                                ->where('item_id', $item->id)
                                ->where('stock_qty', '>', 0);

                    if (!empty($data['inventory_stock_id']) && !$isModeAsset) {
                        $query->where('id', $data['inventory_stock_id']);
                        $modeText = "Manual Batch";
                    } else {
                        $query->orderBy('created_at', $sortDirection);
                        $modeText = "Auto-{$issueMethod}";
                    }

                    $availableStocks = $query->lockForUpdate()->get();
                    $totalAvailable = $availableStocks->sum('stock_qty');

                    if ($totalAvailable < $qtyRequested) {
                        throw new \Exception("Stok {$item->name} tidak cukup! Diminta: {$qtyRequested} {$satuanDasar}, Sisa: {$totalAvailable} {$satuanDasar}");
                    }

                    $qtySisa = $qtyRequested;
                    $saldoTotalSaatIni = (float) $item->current_stock;

                    foreach ($availableStocks as $stockRow) {
                        if ($qtySisa <= 0) break;
                        $potong = min($stockRow->stock_qty, $qtySisa);

                        $balanceBefore = $saldoTotalSaatIni;
                        $balanceAfter = $saldoTotalSaatIni - $potong;
                        $saldoTotalSaatIni = $balanceAfter;

                        $stockRow->decrement('stock_qty', $potong);
                        $qtySisa -= $potong;

                        \App\Models\StockMutation::create([
                            'item_id'          => $item->id,
                            'warehouse_id'     => $request->warehouse_id,
                            'type'             => 'OUT',
                            'qty'              => $potong,
                            'balance_before'   => $balanceBefore,
                            'balance_after'    => $balanceAfter,
                            'reference_number' => $giNumber,
                            'notes'            => "GI ke {$request->requester_name}. [{$modeText}]",
                            'created_by'       => auth()->id(),
                        ]);
                    }

                    $item->update(['current_stock' => $saldoTotalSaatIni]);

                    // 🔥 SIMPAN KE DOKUMEN GI DENGAN QTY YANG BENAR 🔥
                    \App\Models\GoodsIssueItem::create([
                        'goods_issue_id' => $gi->id,
                        'item_id'        => $item->id,
                        'qty_issued'     => $qtyRequested, // Qty dijamin tidak 0 lagi
                        'notes'          => $itemNote,     // Catatan SN Aset terekam jelas
                    ]);

                    // Tracking Minor Asset (Jika bukan Fixed Asset)
                    if (!$isModeAsset && isset($item->is_trackable) && $item->is_trackable) {
                        $empInventory = \App\Models\EmployeeInventory::firstOrNew([
                            'employee_name'    => $request->requester_name,
                            'item_id'          => $item->id,
                            'specific_details' => $itemNote,
                        ]);
                        $empInventory->qty += $qtyRequested;
                        $empInventory->save();

                        \App\Models\EmployeeInventoryHistory::create([
                            'employee_name'    => $request->requester_name,
                            'item_id'          => $item->id,
                            'type'             => 'IN',
                            'qty'              => $qtyRequested,
                            'reference_number' => $giNumber,
                            'notes'            => "Diserahkan ke karyawan melalui GI: {$giNumber}.",
                        ]);
                    }
                }
            });

            return redirect()->route('goods-issues.show', $gi->gi_number)
                             ->with(['success' => 'Pengeluaran Berhasil! Stok dan Data Aset otomatis diperbarui.', 'print_gi_slug' => $gi->gi_number]);

        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }




// ==========================================
    // 3. MENAMPILKAN DETAIL GI (MENGGUNAKAN SLUG)
    // ==========================================
    public function show($slug)
    {
        // 🔥 Pastikan ada 'warehouse' di dalam array with()
        $gi = GoodsIssue::with(['items.item.uom', 'issuer', 'status', 'warehouse', 'returns.warehouse'])
                ->where('gi_number', $slug)
                ->firstOrFail();


                // return $gi;
                
        return view('goods_issues.show', compact('gi'));
    }

    // ==========================================
    // 4. MENCETAK LABEL ASET (MENGGUNAKAN SLUG)
    // ==========================================
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

    // ==========================================
    // 5. MENCETAK DOKUMEN BAST (MENGGUNAKAN SLUG)
    // ==========================================
    public function print($slug)
    {
        // 🔥 PASTIKAN BERSIH DARI altUom JUGA DI SINI 🔥
        $gi = GoodsIssue::with(['items.item.uom', 'issuer'])->where('gi_number', $slug)->firstOrFail();
        return view('goods_issues.print', compact('gi'));
    }
    
    
    // 6. FUNGSI PEMBATALAN TRANSAKSI (MENGGUNAKAN SLUG)
    // ==========================================
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

            // PROSES KEMBALIKAN STOK & ASET (REVERSE LOGIC)
            foreach ($gi->items as $giItem) {
                $masterItem = \App\Models\Item::lockForUpdate()->find($giItem->item_id);
                if (!$masterItem) continue;

                $qtyToRestore = (float) $giItem->qty_issued;

                if ($masterItem->item_type_code === 'STK' || $masterItem->is_stockable) {
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
                }

                if ($masterItem->is_asset || $masterItem->item_type_code === 'AST') {
                    preg_match_all('/AST\/[0-9]{4}\/[0-9]{2}\/[0-9]{4}/', $giItem->notes, $matches);
                    $borrowedAssets = $matches[0];

                    if (!empty($borrowedAssets)) {
                        $assetsToRestore = \App\Models\FixedAsset::whereIn('asset_number', $borrowedAssets)->get();
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
                    }
                }

                if (!$masterItem->is_asset && isset($masterItem->is_trackable) && $masterItem->is_trackable) {
                    $borrowedSns = array_filter(array_map('trim', explode('|', preg_replace('/Satuan.*/', '', $giItem->notes))));
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

            $statusVoidId = \App\Models\Status::where('type', 'GI')->where('slug', 'void')->value('id');

            $gi->update([
                'status_id' => $statusVoidId, 
                'notes'     => $gi->notes . ' | [DIBATALKAN PADA ' . date('d-m-Y H:i') . ']'
            ]);

            DB::commit();
            return back()->with('success', 'Hebat! Transaksi berhasil di-VOID dan seluruh stok telah dikembalikan ke Gudang.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    // ==========================================
    // 7. PENCARIAN BARANG UNTUK FORM (MULTI-UOM VERSION)
    // ==========================================
    // public function searchItems(Request $request)
    // {
    //     try {
    //         $search = $request->search;
    //         $warehouseId = $request->warehouse_id;

    //         $itemIdsInStock = InventoryStock::where('warehouse_id', $warehouseId)
    //                             ->where('stock_qty', '>', 0)->pluck('item_id')->unique();

    //         $items = Item::whereIn('id', $itemIdsInStock)
    //             ->where(function($q) use ($search) {
    //                 $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
    //             })->limit(20)->get();

    //         $formattedItems = [];
    //         foreach ($items as $item) {
    //             $stock = InventoryStock::where('warehouse_id', $warehouseId)
    //                         ->where('item_id', $item->id)->sum('stock_qty');

    //             // 🔥 LOGIKA TARIK SATUAN DASAR & MULTI-KEMASAN 🔥
    //             $uomList = [];
    //             $baseUomName = 'Unit';
                
    //             // 1. Ambil Satuan Dasar (Pcs/Unit)
    //             if (!empty($item->uom_id)) {
    //                 $uom = \Illuminate\Support\Facades\DB::table('uoms')->where('id', $item->uom_id)->first();
    //                 if ($uom) { $baseUomName = $uom->name; }
    //             }
    //             $uomList[] = ['name' => $baseUomName, 'conversion' => 1];

    //             // 2. Ambil Kemasan Alternatif dari Tabel Relasi (item_uoms)
    //             try {
    //                 $altUoms = \Illuminate\Support\Facades\DB::table('item_uoms')
    //                     ->where('item_id', $item->id)
    //                     ->select('uom_name', 'conversion_qty')
    //                     ->get();

    //                 foreach ($altUoms as $alt) {
    //                     // 🔥 INI KUNCI UTAMANYA: MENEMPELKAN TEKS (Isi X) KE DALAM NAMA 🔥
    //                     $uomList[] = [
    //                         'name' => $alt->uom_name . ' (Isi ' . (float)$alt->conversion_qty . ')', 
    //                         'conversion' => (float)$alt->conversion_qty
    //                     ];
    //                 }
    //             } catch (\Exception $e) {
    //                 \Illuminate\Support\Facades\Log::warning("Tabel item_uoms error: " . $e->getMessage());
    //             }

    //             $stockInfo = "Stok: " . (float)$stock . " " . $baseUomName;
    //             $displayName = ($item->code ? '['.$item->code.'] ' : '') . $item->name . " ({$stockInfo})";

    //             $formattedItems[] = [
    //                 'id'           => $item->id,
    //                 'text'         => $displayName,
    //                 'stock'        => $stock,
    //                 'is_asset'     => $item->is_asset ?? ($item->item_type_code === 'AST' ? 1 : 0),
    //                 'is_trackable' => $item->is_trackable ?? 0,
    //                 'uoms'         => $uomList // Kirim semua array UOM ke layar depan
    //             ];
    //         }
    //         return response()->json($formattedItems);
    //     } catch (\Exception $e) {
    //         \Illuminate\Support\Facades\Log::error("AJAX Search Item Error: " . $e->getMessage());
    //         return response()->json([['id' => '', 'text' => '🚨 ERROR: ' . $e->getMessage()]]);
    //     }
    // }

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
            // 1. Cari Stok Bulk (Fisik Biasa)
            $bulkStock = \App\Models\InventoryStock::where('item_id', $item->id)->where('warehouse_id', $warehouseId)->sum('stock_qty');
            
            // 2. Cari Stok Aset (Siap Pakai)
            $assetStock = \App\Models\FixedAsset::where('item_id', $item->id)->where('warehouse_id', $warehouseId)
                ->whereHas('status', function($q) { $q->where('slug', 'available'); })->count();
            
            // 3. Kalkulasi Ketersediaan Sebenarnya
            $availableBulk = max(0, $bulkStock - $assetStock);
            $totalStockDisplay = $availableBulk + $assetStock;

            // Jangan tampilkan jika keduanya kosong
            if ($totalStockDisplay <= 0) continue;

            $text = "[{$item->code}] {$item->name} ";
            if ($assetStock > 0 || $availableBulk > 0) {
                $text .= "(Biasa: {$availableBulk} | Aset: {$assetStock})";
            }

            $results[] = [
                'id' => $item->id,
                'text' => $text,
                'is_asset' => $item->is_asset,
                'is_trackable' => $item->is_trackable,
                'stock' => $totalStockDisplay,
                'available_bulk' => $availableBulk, // Info dikirim ke Javascript
                'available_asset' => $assetStock,   // Info dikirim ke Javascript
                'uoms' => $item->uoms
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
                // 🔥 PERBAIKAN: BACA GUDANG SAAT INI + TOLERANSI DATA LAMA (NULL) 🔥
                ->where(function($q) use ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId)
                      ->orWhereNull('warehouse_id');
                })
                ->where(function($q) {
                    // Status 30 = Available, atau belum ada pemegangnya
                    $q->whereNull('assigned_to')
                      ->orWhere('status_id', 30)
                      ->orWhere('status_id', 1);
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

                if (!empty($asset->accounting_asset_number)) {
                    $text .= ' | FA: ' . $asset->accounting_asset_number;
                }

                if (!empty($asset->serial_number)) {
                    $text .= ' | SN: ' . $asset->serial_number;
                }

                $formatted[] = [
                    'id'   => $asset->id,
                    'text' => $text
                ];
            }

            return response()->json($formatted);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error Search Asset GI: " . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }






    public function searchBatches(Request $request)
    {
        try {
            $stocks = \App\Models\InventoryStock::where('item_id', $request->item_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->where('stock_qty', '>', 0)
                ->orderBy('created_at', 'asc')
                ->get();

            // 🔥 TRICK AJAIB: Cari PT Pembeli dari PO Terakhir (Sebagai Jaring Pengaman) 🔥
            $fallbackPtName = null;
            try {
                $latestPo = \App\Models\PurchaseOrder::with('company')
                    ->whereHas('items', function($q) use ($request) {
                        $q->where('item_id', $request->item_id);
                    })->latest('id')->first();

                if ($latestPo && $latestPo->company) {
                    $fallbackPtName = $latestPo->company->name;
                }
            } catch (\Exception $e) { }

            $formatted = [];
            foreach ($stocks as $stock) {
                $info = [];
                $ptName = null;

                if (!empty($stock->batch_id)) {
                    $info[] = "Batch: " . $stock->batch_id;
                }

                // Lacak Referensi & Nama PT Menggunakan Eloquent Asli
                if (!empty($stock->reference_number)) {
                    $info[] = "Ref: " . $stock->reference_number;

                    try {
                        if (str_starts_with($stock->reference_number, 'GR/')) {
                            $gr = \App\Models\GoodsReceipt::with('purchaseOrder.company')
                                    ->where('gr_number', $stock->reference_number)->first();

                            if ($gr && $gr->purchaseOrder && $gr->purchaseOrder->company) {
                                $ptName = $gr->purchaseOrder->company->name;
                            }
                        }
                    } catch (\Exception $e) { }

                } elseif (isset($stock->goodsReceipt) && !empty($stock->goodsReceipt->gr_number)) {
                    $info[] = "GR: " . $stock->goodsReceipt->gr_number;
                    try {
                        if ($stock->goodsReceipt->purchaseOrder && $stock->goodsReceipt->purchaseOrder->company) {
                            $ptName = $stock->goodsReceipt->purchaseOrder->company->name;
                        }
                    } catch (\Exception $e) { }
                }

                // Prioritaskan PT dari GR, kalau kosong/retur, pakai Fallback PT dari PO
                $finalPtName = $ptName ?? $fallbackPtName;

                if ($finalPtName) {
                    $info[] = "Milik: " . $finalPtName;
                }

                if ($stock->created_at) {
                    $info[] = "In: " . $stock->created_at->format('d/m/y');
                }

                $batchText = empty($info) ? "Stok Reguler" : implode(' | ', $info);
                $finalText = $batchText . ' ➔ Sisa: ' . (float)$stock->stock_qty;

                $formatted[] = [
                    'id'   => $stock->id,
                    'text' => $finalText
                ];
            }

            return response()->json($formatted);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error Search Batches: " . $e->getMessage());
            return response()->json(['error' => 'Gagal memuat batch'], 500);
        }
    }

    // public function printLabels($id)
    // {
    //     $gi = GoodsIssue::with(['items.item', 'issuer'])->findOrFail($id);

    //     $labelItems = $gi->items->filter(function ($giItem) {
    //         $masterItem = $giItem->item;
    //         return $masterItem && ($masterItem->is_asset || $masterItem->is_trackable);
    //     });

    //     if ($labelItems->isEmpty()) {
    //         return back()->with('error', 'Cetak Ditolak: Tidak ada Aset Tetap atau Minor Asset yang perlu dicetak labelnya.');
    //     }

    //     return view('goods_issues.print_labels', compact('gi', 'labelItems'));
    // }

    // public function print($id)
    // {
    //     $gi = GoodsIssue::with(['items.item', 'issuer'])->findOrFail($id);
    //     return view('goods_issues.print', compact('gi'));
    // }

    // =========================================================================
    // 🔥 FUNGSI PEMBATALAN TRANSAKSI (VOID) DENGAN PERIOD LOCK 🔥
    // =========================================================================
    // public function voidTransaction($id)
    // {
    //     try {
    //         DB::beginTransaction();

    //         $gi = \App\Models\GoodsIssue::with('items.item')->findOrFail($id);

    //         // 1. CEK STATUS: Jangan void yang sudah void
    //         if ($gi->status === 'VOID') {
    //             throw new \Exception("Transaksi ini sudah berstatus VOID.");
    //         }

    //         // 2. CEK RIWAYAT RETUR: Kalau sudah ada retur sebagian, haram di-void!
    //         $hasReturns = \App\Models\GoodsIssueReturn::where('goods_issue_id', $gi->id)->exists();
    //         if ($hasReturns) {
    //             throw new \Exception("GAGAL VOID: Transaksi ini sudah memiliki riwayat Retur. Void hanya bisa dilakukan jika barang belum pernah diretur sama sekali.");
    //         }

    //         // 3. 🔥 KUNCI PERIODE (PERIOD LOCK) 🔥
    //         $txMonthYear = \Carbon\Carbon::parse($gi->created_at)->format('Y-m');
    //         $currentMonthYear = \Carbon\Carbon::now()->format('Y-m');

    //         if ($txMonthYear !== $currentMonthYear) {
    //             throw new \Exception("GAGAL VOID: Transaksi terjadi di bulan berbeda ({$txMonthYear}). Laporan bulan tersebut sudah ditutup! Untuk memperbaiki kesalahan, silakan gunakan fitur [Retur Barang] atau [Penyesuaian Stok/Adjustment].");
    //         }

    //         // 4. PROSES KEMBALIKAN STOK & ASET (REVERSE LOGIC)
    //         foreach ($gi->items as $giItem) {
    //             $masterItem = \App\Models\Item::lockForUpdate()->find($giItem->item_id);
    //             if (!$masterItem) continue;

    //             $qtyToRestore = (float) $giItem->qty_issued;

    //             // A. Kembalikan Stok Gudang Fisik
    //             if ($masterItem->is_stockable) {
    //                 $balanceBefore = (float) $masterItem->current_stock;
    //                 $balanceAfter = $balanceBefore + $qtyToRestore;

    //                 $invStock = \App\Models\InventoryStock::where('item_id', $masterItem->id)
    //                                 ->where('warehouse_id', $gi->warehouse_id)->first();

    //                 if ($invStock) {
    //                     $invStock->increment('stock_qty', $qtyToRestore);
    //                 } else {
    //                     \App\Models\InventoryStock::create([
    //                         'company_id' => auth()->user()->company_id ?? 1,
    //                         'warehouse_id' => $gi->warehouse_id,
    //                         'item_id' => $masterItem->id,
    //                         'stock_qty' => $qtyToRestore,
    //                         'reference_number' => $gi->gi_number . '-VOID',
    //                         'notes' => 'Pengembalian Stok karena Batal (VOID)',
    //                     ]);
    //                 }

    //                 // Catat Kartu Mutasi Pembatalan
    //                 \App\Models\StockMutation::create([
    //                     'item_id' => $masterItem->id,
    //                     'warehouse_id' => $gi->warehouse_id,
    //                     'type' => 'IN',
    //                     'qty' => $qtyToRestore,
    //                     'balance_before' => $balanceBefore,
    //                     'balance_after' => $balanceAfter,
    //                     'reference_number' => $gi->gi_number . '-VOID',
    //                     'notes' => "Pembatalan Transaksi (VOID). Stok dikembalikan otomatis.",
    //                     'created_by' => auth()->id(),
    //                 ]);

    //                 $masterItem->update(['current_stock' => $balanceAfter]);
    //             }

    //             // B. Kembalikan Aset Tetap
    //             if ($masterItem->is_asset) {
    //                 preg_match_all('/AST\/[0-9]{4}\/[0-9]{2}\/[0-9]{4}/', $giItem->notes, $matches);
    //                 $borrowedAssets = $matches[0];

    //                 if (!empty($borrowedAssets)) {
    //                     $assetsToRestore = \App\Models\FixedAsset::whereIn('asset_number', $borrowedAssets)->get();
    //                     $statusIdAvailable = \App\Models\Status::where('type', 'AST')->where('slug', 'available')->value('id');

    //                     foreach ($assetsToRestore as $asset) {
    //                         \App\Models\FixedAssetHistory::create([
    //                             'fixed_asset_id' => $asset->id,
    //                             'status' => 'Available (Tersedia)',
    //                             'assigned_to' => null,
    //                             'notes' => "Dikembalikan otomatis (Dokumen {$gi->gi_number} di-VOID).",
    //                             'created_by' => auth()->id(),
    //                         ]);

    //                         $asset->update([
    //                             'status_id' => $statusIdAvailable,
    //                             'assigned_to' => null,
    //                             'warehouse_id' => $gi->warehouse_id,
    //                         ]);
    //                     }
    //                 }
    //             }

    //             // C. Tarik Kembali Inventaris (Minor) dari Karyawan
    //             if (!$masterItem->is_asset && array_key_exists('is_trackable', $masterItem->getAttributes()) && $masterItem->is_trackable) {
    //                 $borrowedSns = array_filter(array_map('trim', explode('|', $giItem->notes)));
    //                 $empInventory = \App\Models\EmployeeInventory::where(['employee_name' => $gi->requester_name, 'item_id' => $masterItem->id])->first();

    //                 if ($empInventory && !empty($borrowedSns)) {
    //                     $currentSns = array_filter(array_map('trim', explode('|', $empInventory->specific_details)));
    //                     $empInventory->specific_details = implode(' | ', array_diff($currentSns, $borrowedSns));
    //                     $empInventory->qty = max(0, $empInventory->qty - $qtyToRestore);
    //                     $empInventory->save();

    //                     \App\Models\EmployeeInventoryHistory::create([
    //                         'employee_name' => $gi->requester_name,
    //                         'item_id' => $masterItem->id,
    //                         'type' => 'OUT', // Menarik barang dari catatan user
    //                         'qty' => $qtyToRestore,
    //                         'reference_number' => $gi->gi_number . '-VOID',
    //                         'notes' => "Penarikan otomatis karena transaksi VOID. SN: " . implode(', ', $borrowedSns),
    //                     ]);
    //                 }
    //             }
    //         }

    //         // Tarik ID status VOID
    //         $statusVoidId = \App\Models\Status::where('type', 'GI')->where('slug', 'void')->value('id');

    //         // 5. Ubah Status GI menjadi VOID
    //         $gi->update([
    //             'status_id' => $statusVoidId, // 🔥 Pakai ID Status
    //             'notes' => $gi->notes . ' | [DIBATALKAN PADA ' . date('d-m-Y H:i') . ']'
    //         ]);

    //         DB::commit();
    //         return back()->with('success', 'Hebat! Transaksi berhasil di-VOID dan seluruh stok telah dikembalikan ke Gudang.');

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return back()->with('error', $e->getMessage());
    //     }
    // }



 public function printBast($slug)
    {
        // 🔥 Cari dokumen berdasarkan gi_number (slug), bukan id angka! 🔥
        $gi = \App\Models\GoodsIssue::with(['items.item', 'warehouse'])
                ->where('gi_number', $slug)
                ->firstOrFail();
        
        // Trik untuk mencari aset apa saja yang dikeluarkan di dokumen ini
        $assets = \App\Models\FixedAsset::where('notes', 'like', "%{$gi->gi_number}%")->get();

        return view('goods_issues.bast', compact('gi', 'assets'));
    }



}
