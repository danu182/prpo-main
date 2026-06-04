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

        // 🔥 PERBAIKAN: Tarik relasi 'warehouse' sekalian agar loadingnya ngebut!
        $returns = GoodsIssueReturn::with(['goodsIssue', 'receiver', 'warehouse'])
            ->when($search, function ($query) use ($search) {
                $query->where('return_number', 'like', "%{$search}%")
                      ->orWhere('returned_by_name', 'like', "%{$search}%")
                      ->orWhereHas('goodsIssue', function ($q) use ($search) {
                          $q->where('gi_number', 'like', "%{$search}%");
                      })
                      // 🔥 BISA CARI BERDASARKAN NAMA GUDANG
                      ->orWhereHas('warehouse', function ($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('goods_issue_returns.index', compact('returns', 'search'));
    }

    public function create($gi_id)
    {
        $gi = GoodsIssue::with('items.item')->findOrFail($gi_id);

        // Hanya tampilkan item yang masih bisa diretur (belum diretur semua)
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

        // ==============================================================
        // 🔥 LOGIKA BARU: CARI ASET YANG SEDANG DIPEGANG OLEH USER 🔥
        // ==============================================================
        // Cari user ID berdasarkan nama peminjam di dokumen GI
        $userPenerima = \App\Models\User::where('name', $gi->requester_name)->first();
        $assignedToId = $userPenerima ? $userPenerima->id : null;

        foreach ($returnableItems as $giItem) {
            if ($giItem->item && $giItem->item->is_asset) {
                // Ambil daftar aset spesifik yang jenisnya sama dan sedang dipegang user ini
                $giItem->held_assets = \App\Models\FixedAsset::where('item_id', $giItem->item_id)
                                        ->where('assigned_to', $assignedToId)
                                        // Pastikan hanya aset yang statusnya "In Use" (Sesuaikan ID status Anda, misal 31)
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
            'warehouse_id'     => 'required|exists:warehouses,id', // 🔥 Target Gudang Retur
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

                // 🔥 KUNCI GUDANG TUJUAN DI SINI AGAR TIDAK MENGGUNAKAN GUDANG ASAL!
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
                    'warehouse_id'     => $targetWarehouseId, // Simpan ke Header
                    'return_number'    => $retNumber,
                    'return_date'      => $request->return_date,
                    'returned_by_name' => $request->returned_by_name,
                    'received_by'      => auth()->id(),
                    'notes'            => $request->notes,
                ]);

                // C. Proses Detail Item & TAMBAH STOK
                foreach ($request->items as $data) {
                    $qtyReturned = (float) $data['qty_returned'];

                    if ($qtyReturned <= 0) continue;

                    $giItem = GoodsIssueItem::findOrFail($data['gi_item_id']);
                    $masterItem = Item::lockForUpdate()->findOrFail($giItem->item_id);

                    // VALIDASI
                    $sisaBolehRetur = (float) $giItem->qty_issued - (float) $giItem->qty_returned;
                    if ($qtyReturned > $sisaBolehRetur) {
                        throw new \Exception("Gagal! Anda mencoba meretur {$qtyReturned} unit untuk barang '{$masterItem->name}'. Maksimal retur: {$sisaBolehRetur} unit.");
                    }

                    // 1. Catat ke Detail Retur
                    GoodsIssueReturnItem::create([
                        'goods_issue_return_id' => $newReturn->id,
                        'goods_issue_item_id'   => $giItem->id,
                        'item_id'               => $masterItem->id,
                        'qty_returned'          => $qtyReturned,
                        'notes'                 => $data['notes'] ?? null,
                    ]);

                    // 2. Update status Qty Returned di histori pengeluaran asli
                    $giItem->increment('qty_returned', $qtyReturned);

                    // =======================================================
                    // 🔥 3. SIHIR BATCH STOK: PASTIKAN MASUK KE GUDANG BARU 🔥
                    // =======================================================
                    if ($masterItem->is_stockable) {
                        $balanceBefore = (float) $masterItem->current_stock;
                        $balanceAfter  = $balanceBefore + $qtyReturned;

                        // CEK KE GUDANG TUJUAN (Bukan gudang asal GI)
                        $invStock = InventoryStock::where('item_id', $masterItem->id)
                                                  ->where('warehouse_id', $targetWarehouseId) // 🔥 WAJIB $targetWarehouseId
                                                  ->first();

                        if ($invStock) {
                            $invStock->increment('stock_qty', $qtyReturned);
                        } else {
                            InventoryStock::create([
                                'company_id'       => auth()->user()->company_id ?? 1,
                                'warehouse_id'     => $targetWarehouseId, // 🔥 WAJIB $targetWarehouseId
                                'item_id'          => $masterItem->id,
                                'stock_qty'        => $qtyReturned,
                                'reference_number' => $retNumber,
                                'notes'            => "Retur Lintas Gudang dari: {$request->returned_by_name}",
                            ]);
                        }

                        // Catat di Kartu Stok Mutasi (IN)
                        StockMutation::create([
                            'item_id'          => $masterItem->id,
                            'warehouse_id'     => $targetWarehouseId, // 🔥 WAJIB $targetWarehouseId
                            'type'             => 'IN',
                            'qty'              => $qtyReturned,
                            'balance_before'   => $balanceBefore,
                            'balance_after'    => $balanceAfter,
                            'reference_number' => $retNumber,
                            'notes'            => "Retur masuk dari: {$request->returned_by_name} (Ref GI: {$gi->gi_number})",
                            'created_by'       => auth()->id(),
                        ]);

                        $masterItem->update(['current_stock' => $balanceAfter]);
                    }

                    // =======================================================
                    // 🔥 4. LOGIKA HISTORI ASSET (PINDAH KTP ASET) 🔥
                    // =======================================================
                    if ($masterItem->is_asset) {
                        $selectedAssetNumbers = $data['returned_asset_numbers'] ?? [];
                        if (empty($selectedAssetNumbers)) throw new \Exception("Gagal: Pilih Nomor Aset yang dikembalikan.");

                        $assetsToReturn = \App\Models\FixedAsset::whereIn('asset_number', $selectedAssetNumbers)->whereNotNull('assigned_to')->get();
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
                                'warehouse_id' => $targetWarehouseId, // 🔥 ASET MASUK KE GUDANG BARU
                            ]);
                        }
                    }

                    // =======================================================
                    // 🔥 5. HAPUS TANGGUNGAN INVENTARIS KARYAWAN (MINOR) 🔥
                    // =======================================================
                    if (!$masterItem->is_asset && array_key_exists('is_trackable', $masterItem->getAttributes()) && $masterItem->is_trackable) {
                        $returnedSns = $data['returned_minor_sns'] ?? [];
                        if (empty($returnedSns)) throw new \Exception("Gagal: Pilih S/N yang dikembalikan.");

                        $empInventory = \App\Models\EmployeeInventory::where(['employee_name' => $gi->requester_name, 'item_id' => $masterItem->id])->first();
                        if ($empInventory) {
                            $currentSns = array_filter(array_map('trim', explode('|', $empInventory->specific_details)));
                            $empInventory->specific_details = implode(' | ', array_diff($currentSns, $returnedSns));
                            $empInventory->qty = max(0, $empInventory->qty - $qtyReturned);
                            $empInventory->save();

                            \App\Models\EmployeeInventoryHistory::create([
                                'employee_name'    => $gi->requester_name,
                                'item_id'          => $masterItem->id,
                                'type'             => 'OUT',
                                'qty'              => $qtyReturned,
                                'reference_number' => $retNumber,
                                'notes'            => "Mengembalikan SN: [" . implode(', ', $returnedSns) . "]",
                            ]);
                        }
                    }
                }

                // =======================================================
                // 🔥 6. UPDATE STATUS DOKUMEN INDUK (GOODS ISSUE) 🔥
                // =======================================================
                // Tarik ulang data GI beserta itemnya agar perhitungannya fresh
                $giToUpdate = GoodsIssue::with('items')->find($gi->id);

                $totalIssued = $giToUpdate->items->sum('qty_issued');
                $totalReturned = $giToUpdate->items->sum('qty_returned');

                $newStatusSlug = 'active'; // Default

                if ($totalReturned >= $totalIssued) {
                    $newStatusSlug = 'full_return'; // Retur Penuh
                } elseif ($totalReturned > 0) {
                    $newStatusSlug = 'partial_return'; // Retur Sebagian
                }

                // Cari ID status di database berdasarkan slug
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
            \Illuminate\Support\Facades\Log::error('Error Goods Issue Return: ' . $e->getMessage());
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    // 4. Tampilkan Detail & Cetak Form Retur
    public function show($id)
    {
        $return = GoodsIssueReturn::with(['items.item', 'goodsIssue', 'receiver'])->findOrFail($id);
        return view('goods_issue_returns.show', compact('return'));
    }


    public function voidTransaction($id)
    {
        $gi = GoodsIssue::findOrFail($id);

        // 1. Ambil Bulan & Tahun Transaksi vs Bulan & Tahun Sekarang
        $txMonthYear = \Carbon\Carbon::parse($gi->created_at)->format('Y-m');
        $currentMonthYear = \Carbon\Carbon::now()->format('Y-m');

        // 2. VALIDASI KUNCI PERIODE (PERIOD LOCK)
        if ($txMonthYear !== $currentMonthYear) {
            return back()->with('error', '⚠️ GAGAL VOID: Transaksi ini terjadi pada bulan yang berbeda (' . $txMonthYear . '). Laporan bulan tersebut sudah ditutup. Untuk memperbaiki kesalahan, silakan gunakan menu "Retur Barang" atau "Penyesuaian Stok (Adjustment)".');
        }

        // 3. JIKA BULAN SAMA, LANJUTKAN PROSES VOID
        DB::transaction(function () use ($gi) {
            // A. Ubah Status Dokumen jadi 'VOID'
            $gi->update(['status' => 'VOID', 'notes' => $gi->notes . ' [VOID]']);

            // B. Kembalikan Stok (Looping detail item, increment stok di InventoryStock)
            // C. Catat di StockMutation (Tipe: 'VOID-IN') agar riwayatnya jelas kenapa stok nambah.
        });

        return back()->with('success', 'Transaksi berhasil di-Void. Stok telah dikembalikan ke Gudang asal.');
    }


}
