<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsIssueReturn;
use App\Models\GoodsIssueReturnItem;
use App\Models\Item;
use App\Models\Warehouse;
use App\Models\InventoryStock;
use App\Models\StockMutation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoodsIssueReturnController extends Controller
{
    // 1. Tampilkan Daftar Riwayat Retur
    public function index(Request $request)
    {
        $search = $request->input('search');

        $returns = GoodsIssueReturn::with(['goodsIssue', 'receiver', 'warehouse'])
            ->when($search, function ($query) use ($search) {
                $query->where('return_number', 'like', "%{$search}%")
                      ->orWhere('returned_by_name', 'like', "%{$search}%")
                      ->orWhereHas('goodsIssue', function ($q) use ($search) {
                          $q->where('gi_number', 'like', "%{$search}%");
                      })
                      ->orWhereHas('warehouse', function ($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('goods_issue_returns.index', compact('returns', 'search'));
    }

    // 2. Tampilkan Form Retur
    public function create($gi_id)
    {
        $gi = GoodsIssue::with('items.item')->findOrFail($gi_id);

        $returnableItems = $gi->items->filter(function ($item) {
            $sisaBisaRetur = $item->qty_issued - ($item->qty_returned ?? 0);
            return $sisaBisaRetur > 0;
        });

        if ($returnableItems->isEmpty()) {
            return redirect()->route('goods-issues.show', $gi_id)
                ->with('error', 'Semua barang dari pengeluaran ini sudah dikembalikan penuh. Tidak ada yang bisa diretur lagi.');
        }

        $warehouses = Warehouse::orderBy('name', 'asc')->get();
        $asalGudangId = $gi->warehouse_id ?? null;

        if (!$asalGudangId) {
            $mutasiPengeluaran = \App\Models\StockMutation::where('reference_number', $gi->gi_number)
                                    ->where('type', 'OUT')
                                    ->first();
            if ($mutasiPengeluaran) $asalGudangId = $mutasiPengeluaran->warehouse_id;
        }

        $userPenerima = \App\Models\User::where('name', $gi->requester_name)->first();
        $assignedToId = $userPenerima ? $userPenerima->id : null;

        foreach ($returnableItems as $giItem) {
            if ($giItem->item && $giItem->item->is_asset) {
                $giItem->held_assets = \App\Models\FixedAsset::where('item_id', $giItem->item_id)
                                        ->where('assigned_to', $assignedToId)
                                        ->where('status_id', 31)
                                        ->get();
            }
        }

        return view('goods_issue_returns.create', compact('gi', 'returnableItems', 'warehouses', 'asalGudangId'));
    }

    // 3. Proses Simpan Retur & Kembalikan Stok (IN)
    public function store(Request $request, $gi_id)
    {
        $request->validate([
            'warehouse_id'     => 'required|exists:warehouses,id',
            'return_date'      => 'required|date|before_or_equal:today',
            'returned_by_name' => 'required|string|max:255',
            'notes'            => 'nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.gi_item_id' => 'required|exists:goods_issue_items,id',
            'items.*.qty_returned' => 'required|numeric|min:0',
        ]);

        try {
            $newReturn = null;

            DB::transaction(function () use ($request, $gi_id, &$newReturn) {
                $gi = GoodsIssue::findOrFail($gi_id);
                $targetWarehouseId = $request->warehouse_id;

                // A. Generate Nomor Retur
                $year = date('Y', strtotime($request->return_date));
                $month = date('m', strtotime($request->return_date));

                $lastRet = GoodsIssueReturn::whereYear('created_at', $year)
                            ->whereMonth('created_at', $month)
                            ->orderBy('id', 'desc')->first();

                $nextId = $lastRet ? ((int) substr($lastRet->return_number, -4)) + 1 : 1;
                $retNumber = 'RET-GI/' . $year . '/' . $month . '/' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

                // B. Simpan Header Retur
                $newReturn = GoodsIssueReturn::create([
                    'goods_issue_id'   => $gi->id,
                    'warehouse_id'     => $targetWarehouseId,
                    'return_number'    => $retNumber,
                    'return_date'      => $request->return_date,
                    'returned_by_name' => $request->returned_by_name,
                    'received_by'      => auth()->id(),
                    'notes'            => $request->notes,
                ]);

                // C. Proses Detail Item & TAMBAH STOK
                foreach ($request->items as $data) {
                    $qtyReturnedInput = (float) $data['qty_returned'];
                    if ($qtyReturnedInput <= 0) continue;

                    $giItem = GoodsIssueItem::findOrFail($data['gi_item_id']);
                    $masterItem = Item::with('itemUoms')->lockForUpdate()->findOrFail($giItem->item_id);
                    $baseUomName = optional($masterItem->uom)->name ?? 'PCS';

                    // =======================================================
                    // 🔥 1. LOGIKA EKSTRAKSI UOM DAN KONVERSI 🔥
                    // =======================================================

                    // A. Cari UOM & Konversi Asli saat Barang Dikeluarkan (GI)
                    $rawGiUom = $giItem->getRawOriginal('uom') ?: 'PCS';
                    $giConvFactor = 1;
                    if (preg_match('/Isi\s*([0-9.]+)/i', $rawGiUom, $matches)) {
                        $giConvFactor = (float) $matches[1];
                    } elseif ($giItem->uom_id) {
                        $giUomDb = collect($masterItem->itemUoms)->where('id', $giItem->uom_id)->first();
                        if ($giUomDb) $giConvFactor = (float) $giUomDb->conversion_qty;
                    }

                    // B. Cari UOM & Konversi Inputan Saat Diretur
                    $inputUom = $data['uom'] ?? $rawGiUom;
                    $cleanInputUom = trim(preg_replace('/ \(Isi:?.*\)/i', '', $inputUom));
                    $inputConvFactor = 1;
                    $inputUomDb = collect($masterItem->itemUoms)->where('uom_name', $cleanInputUom)->first();

                    if (preg_match('/Isi\s*([0-9.]+)/i', $inputUom, $matches)) {
                        $inputConvFactor = (float) $matches[1];
                    } elseif ($inputUomDb) {
                        $inputConvFactor = (float) $inputUomDb->conversion_qty;
                    }

                    // =======================================================
                    // 🔥 2. VALIDASI KETAT BERDASARKAN BASE QTY 🔥
                    // =======================================================
                    $baseQtyReturned = $qtyReturnedInput * $inputConvFactor;
                    $baseQtyIssuedSoFar = $giItem->qty_issued * $giConvFactor;
                    $baseQtyReturnedSoFar = ($giItem->qty_returned ?? 0) * $giConvFactor;

                    $sisaBaseBolehRetur = round($baseQtyIssuedSoFar - $baseQtyReturnedSoFar, 4);

                    if (round($baseQtyReturned, 4) > $sisaBaseBolehRetur) {
                        throw new \Exception("Gagal! Anda meretur {$baseQtyReturned} {$baseUomName}, padahal sisa maksimal yang boleh diretur untuk '{$masterItem->name}' hanya {$sisaBaseBolehRetur} {$baseUomName}.");
                    }

                    // Siapkan Teks Satuan untuk Disimpan
                    $finalUomString = $cleanInputUom;
                    if ($inputConvFactor > 1) {
                        $finalUomString .= ' (Isi ' . (float)$inputConvFactor . ' ' . $baseUomName . ')';
                    }

                    // =======================================================
                    // 🔥 3. CATAT DETAIL RETUR & KEMBALIKAN QTY GI 🔥
                    // =======================================================
                    GoodsIssueReturnItem::create([
                        'goods_issue_return_id' => $newReturn->id,
                        'goods_issue_item_id'   => $giItem->id,
                        'item_id'               => $masterItem->id,
                        'qty_returned'          => $qtyReturnedInput,
                        // Catat uom ke notes jika kolom 'uom' belum ada di tabel ini
                        'notes'                 => "Satuan: {$finalUomString} | " . ($data['notes'] ?? ''),
                    ]);

                    // Update qty_returned di dokumen GI (Kembalikan ke format GI aslinya)
                    $qtyToReturnFormatGi = $baseQtyReturned / $giConvFactor;
                    $giItem->increment('qty_returned', $qtyToReturnFormatGi);

                    // =======================================================
                    // 🔥 4. SIHIR BATCH STOK: MASUKKAN KE GUDANG BARU 🔥
                    // =======================================================
                    if ($masterItem->is_stockable) {
                        $balanceBefore = (float) $masterItem->current_stock;
                        $balanceAfter  = $balanceBefore + $baseQtyReturned; // Pakai Base Qty!

                        $invStock = InventoryStock::where('item_id', $masterItem->id)
                                                  ->where('warehouse_id', $targetWarehouseId)
                                                  ->first();

                        if ($invStock) {
                            $invStock->increment('stock_qty', $baseQtyReturned);
                        } else {
                            InventoryStock::create([
                                'company_id'       => auth()->user()->company_id ?? 1,
                                'warehouse_id'     => $targetWarehouseId,
                                'item_id'          => $masterItem->id,
                                'stock_qty'        => $baseQtyReturned,
                                'reference_number' => $retNumber,
                                'notes'            => "Retur Internal dari: {$request->returned_by_name}",
                            ]);
                        }

                        StockMutation::create([
                            'item_id'          => $masterItem->id,
                            'warehouse_id'     => $targetWarehouseId,
                            'type'             => 'IN',
                            'qty'              => $baseQtyReturned,
                            'balance_before'   => $balanceBefore,
                            'balance_after'    => $balanceAfter,
                            'reference_number' => $retNumber,
                            'notes'            => "Retur masuk dari: {$request->returned_by_name} (Ref GI: {$gi->gi_number})",
                            'created_by'       => auth()->id(),
                        ]);

                        $masterItem->update(['current_stock' => $balanceAfter]);
                    }

                    // =======================================================
                    // 🔥 5. KEMBALIKAN ASET TETAP 🔥
                    // =======================================================
                    if ($masterItem->is_asset) {
                        $selectedAssetNumbers = $data['returned_asset_numbers'] ?? [];
                        if (empty($selectedAssetNumbers)) throw new \Exception("Gagal: Pilih Nomor Aset yang dikembalikan untuk barang {$masterItem->name}.");

                        $assetsToReturn = \App\Models\FixedAsset::whereIn('asset_number', $selectedAssetNumbers)->get();
                        $statusIdAvailable = \App\Models\Status::where('type', 'AST')->where('slug', 'available')->value('id');

                        foreach ($assetsToReturn as $asset) {
                            \App\Models\FixedAssetHistory::create([
                                'fixed_asset_id' => $asset->id,
                                'status'         => 'Available (Tersedia)',
                                'assigned_to'    => null,
                                'notes'          => "Dikembalikan ke Gudang. Ref Doc: {$retNumber}",
                                'created_by'     => auth()->id(),
                            ]);

                            $asset->update([
                                'status_id'    => $statusIdAvailable,
                                'assigned_to'  => null,
                                'warehouse_id' => $targetWarehouseId,
                            ]);
                        }
                    }

                    // =======================================================
                    // 🔥 6. HAPUS TANGGUNGAN INVENTARIS KARYAWAN (MINOR) 🔥
                    // =======================================================
                    if (!$masterItem->is_asset && array_key_exists('is_trackable', $masterItem->getAttributes()) && $masterItem->is_trackable) {
                        $returnedSns = $data['returned_minor_sns'] ?? [];
                        if (empty($returnedSns)) throw new \Exception("Gagal: Pilih S/N yang dikembalikan untuk barang {$masterItem->name}.");

                        $empInventory = \App\Models\EmployeeInventory::where(['employee_name' => $gi->requester_name, 'item_id' => $masterItem->id])->first();
                        if ($empInventory) {
                            $currentSns = array_filter(array_map('trim', explode('|', $empInventory->specific_details)));
                            $empInventory->specific_details = implode(' | ', array_diff($currentSns, $returnedSns));
                            $empInventory->qty = max(0, $empInventory->qty - $baseQtyReturned);
                            $empInventory->save();

                            \App\Models\EmployeeInventoryHistory::create([
                                'employee_name'    => $gi->requester_name,
                                'item_id'          => $masterItem->id,
                                'type'             => 'OUT',
                                'qty'              => $baseQtyReturned,
                                'reference_number' => $retNumber,
                                'notes'            => "Mengembalikan SN: [" . implode(', ', $returnedSns) . "]",
                            ]);
                        }
                    }
                }

                // =======================================================
                // 🔥 7. UPDATE STATUS DOKUMEN INDUK (GOODS ISSUE) 🔥
                // =======================================================
                $giToUpdate = GoodsIssue::with('items')->find($gi->id);
                $totalIssued = $giToUpdate->items->sum('qty_issued');
                $totalReturned = $giToUpdate->items->sum('qty_returned');

                $newStatusSlug = 'active';
                if ($totalReturned >= $totalIssued) {
                    $newStatusSlug = 'full_return';
                } elseif ($totalReturned > 0) {
                    $newStatusSlug = 'partial_return';
                }

                $statusId = \App\Models\Status::where('type', 'GI')->where('slug', $newStatusSlug)->value('id');
                if ($statusId) {
                    $giToUpdate->update(['status_id' => $statusId]);
                }
            });

            if (!$newReturn) throw new \Exception("Gagal menyimpan dokumen retur.");

            return redirect()->route('goods-issue-returns.index')->with([
                'success' => 'Retur barang berhasil diproses! Stok dan status Aset telah dikembalikan ke gudang tujuan.',
                'print_ret_id' => $newReturn->id
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error Goods Issue Return: ' . $e->getMessage() . ' - L: ' . $e->getLine());
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // 4. Tampilkan Detail & Cetak Form Retur
    public function show($id)
    {
        $return = GoodsIssueReturn::with(['items.item', 'goodsIssue', 'receiver'])->findOrFail($id);
        return view('goods_issue_returns.show', compact('return'));
    }

    // 5. Void Transaksi
    public function voidTransaction($id)
    {
        $gi = GoodsIssue::findOrFail($id);
        $txMonthYear = \Carbon\Carbon::parse($gi->created_at)->format('Y-m');
        $currentMonthYear = \Carbon\Carbon::now()->format('Y-m');

        if ($txMonthYear !== $currentMonthYear) {
            return back()->with('error', '⚠️ GAGAL VOID: Transaksi ini terjadi pada bulan yang berbeda (' . $txMonthYear . '). Laporan bulan tersebut sudah ditutup. Untuk memperbaiki kesalahan, silakan gunakan menu "Retur Barang" atau "Penyesuaian Stok (Adjustment)".');
        }

        DB::transaction(function () use ($gi) {
            $gi->update(['status' => 'VOID', 'notes' => $gi->notes . ' [VOID]']);
            // Logika void stok dll
        });

        return back()->with('success', 'Transaksi berhasil di-Void. Stok telah dikembalikan ke Gudang asal.');
    }
}
