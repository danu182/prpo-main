<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\Warehouse;
use App\Models\StockAdjustment;
use App\Models\StockMutatio;
use App\Models\InventoryStock;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{


    public function index()
    {
        // Kita ambil data Header, muat relasi gudang & uploader,
        // dan hitung jumlah item di dalamnya secara otomatis.
        $adjustments = StockAdjustment::with(['warehouse', 'adjuster'])
            ->withCount('items') // Ini yang akan mengisi data "100 Item" di tabel
            ->latest()
            ->paginate(10);

        return view('stock_adjustments.index', compact('adjustments'));
    }


    // 1. Tampilkan Form Koreksi Stok (Massal)
    public function create()
    {
        $warehouses = Warehouse::orderBy('name', 'asc')->get();
        return view('stock_adjustments.create', compact('warehouses'));
    }

    // 2. AJAX: Ambil Stok Sistem di Gudang Tertentu
    public function getWarehouseStock(Request $request)
    {
        $stock = InventoryStock::where('warehouse_id', $request->warehouse_id)
                    ->where('item_id', $request->item_id)
                    ->sum('stock_qty');

        return response()->json(['stock' => (float) $stock]);
    }

    public function store(Request $request)
    {
        // 1. VALIDASI INPUT
        $request->validate([
            'adjustment_date'      => 'required|date|before_or_equal:today',
            'warehouse_id'         => 'required|exists:warehouses,id',
            'reason'               => 'required|string|max:255',
            'items'                => 'required|array|min:1',
            'items.*.item_id'      => 'required|exists:items,id',
            'items.*.real_stock'   => 'required|numeric|min:0',
            'items.*.unit_price'   => 'nullable|numeric|min:0', // 🔥 Validasi untuk input HPP
        ]);

        // 🔥 2. GEMBOK PEMBEKUAN GUDANG (WAREHOUSE FREEZE VALIDATION) 🔥
        // Cek apakah ada Stock Opname di gudang ini yang masih Draft atau Pending
        $isFrozen = \App\Models\StockOpname::where('warehouse_id', $request->warehouse_id)
            ->where(function ($query) {
                // Cari yang statusnya masih Draft (atau belum punya status), atau sedang diajukan
                $query->whereNull('status_id')
                      ->orWhereHas('status', function ($q) {
                          $q->whereIn('slug', ['draft', 'pending', 'pending_approval']);
                      });
            })->exists();

        if ($isFrozen) {
            return back()->withInput()->with('error', 'GAGAL: Gudang ini sedang DIBEKUKAN karena ada sesi Audit/Stock Opname yang belum selesai. Selesaikan atau batalkan sesi Opname terlebih dahulu!');
        }

        try {
            return DB::transaction(function () use ($request) {
                $warehouse = \App\Models\Warehouse::findOrFail($request->warehouse_id);

                // 3. GENERATE NOMOR DOKUMEN (SEKALI SAJA)
                $year = date('Y', strtotime($request->adjustment_date));
                $month = date('m', strtotime($request->adjustment_date));
                $lastAdj = \App\Models\StockAdjustment::whereYear('created_at', $year)
                                            ->whereMonth('created_at', $month)
                                            ->orderBy('id', 'desc')->first();

                $nextId = $lastAdj ? ((int) substr($lastAdj->adjustment_number, -4)) + 1 : 1;
                $adjNumber = 'ADJ/' . $year . '/' . $month . '/' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

                // 4. SIMPAN HEADER (STOCK ADJUSTMENT)
                $adjustment = \App\Models\StockAdjustment::create([
                    'adjustment_number' => $adjNumber,
                    'adjustment_date'   => $request->adjustment_date,
                    'warehouse_id'      => $warehouse->id,
                    'reason'            => $request->reason,
                    'adjusted_by'       => auth()->id(),
                ]);

                $adaPerubahan = false;

                // 5. LOOPING UNTUK SIMPAN DETAIL BARANG
                foreach ($request->items as $data) {
                    $item = \App\Models\Item::lockForUpdate()->findOrFail($data['item_id']);

                    $newStockFisik = (float) $data['real_stock'];
                    $unitPrice = (float) ($data['unit_price'] ?? 0); // Tangkap harga dari form

                    // 🔥 LOGIKA CERDAS: Jika form HPP dikosongkan/0, tarik paksa dari Master Barang
                    if ($unitPrice <= 0) {
                        $unitPrice = $item->purchase_price ?? $item->unit_price ?? 0;
                    }

                    // Hitung stok sistem SAAT INI di gudang spesifik
                    $stockSistemDiGudang = \App\Models\InventoryStock::where('warehouse_id', $warehouse->id)
                                            ->where('item_id', $item->id)
                                            ->sum('stock_qty');

                    $difference = $newStockFisik - $stockSistemDiGudang;

                    // Skip jika tidak ada perbedaan angka
                    if ($difference == 0) continue;

                    $adaPerubahan = true;
                    $absDiff = abs($difference);

                    // A. SIMPAN KE TABEL DETAIL (StockAdjustmentItem)
                    // (Pastikan tabel database stock_adjustment_items memiliki kolom 'unit_price' jika ingin menyimpan histori harganya di sini)
                    \App\Models\StockAdjustmentItem::create([
                        'stock_adjustment_id' => $adjustment->id,
                        'item_id'             => $item->id,
                        'previous_stock'      => $stockSistemDiGudang,
                        'new_stock'           => $newStockFisik,
                        'difference'          => $difference,
                        'unit_price'          => $unitPrice, // Simpan harga perolehan
                    ]);

                    // B. LOGIKA UPDATE FISIK (INVENTORY STOCK)
                    if ($difference > 0) {
                        // KOREKSI PLUS (+): Tambah tumpukan stok di gudang ini beserta HPP-nya
                        \App\Models\InventoryStock::create([
                            'company_id'       => auth()->user()->company_id ?? 1,
                            'warehouse_id'     => $warehouse->id,
                            'item_id'          => $item->id,
                            'stock_qty'        => $absDiff,
                            'unit_price'       => $unitPrice, // 🔥 Menyimpan harga perolehan baru
                            'reference_number' => $adjNumber,
                        ]);

                        // Update stok global di Master Item
                        $item->increment('current_stock', $absDiff);

                        // 🔥 CERDAS: Jika harga di Master Barang masih Rp 0, otomatis update pakai harga temuan saat ini
                        if (($item->purchase_price == 0 || is_null($item->purchase_price)) && $unitPrice > 0) {
                            $item->update(['purchase_price' => $unitPrice]);
                        }
                    }
                    else {
                        // KOREKSI MINUS (-): Potong stok yang ada menggunakan FIFO (Harga tidak ikut diubah karena memotong stok lama)
                        $this->reduceStockFifo($warehouse->id, $item->id, $absDiff);

                        // Update stok global di Master Item
                        $item->decrement('current_stock', $absDiff);
                    }

                    // C. CATAT MUTASI (LOG KARTU STOK)
                    \App\Models\StockMutation::create([
                        'item_id'          => $item->id,
                        'warehouse_id'     => $warehouse->id,
                        'type'             => $difference > 0 ? 'IN' : 'OUT',
                        'qty'              => $absDiff,
                        'balance_before'   => ($difference > 0) ? $item->current_stock - $absDiff : $item->current_stock + $absDiff,
                        'balance_after'    => $item->current_stock,
                        'reference_number' => $adjNumber,
                        'notes'            => "Penyesuaian: " . $request->reason,
                        'created_by'       => auth()->id(),
                    ]);
                }

                if (!$adaPerubahan) {
                    throw new \Exception("Gagal! Tidak ada perbedaan antara stok sistem dan stok fisik pada barang yang Anda masukkan.");
                }

                return redirect()->route('stock-adjustments.index')->with('success', "Dokumen Penyesuaian Stok {$adjNumber} berhasil disimpan!");
            });

        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Terjadi Kesalahan: ' . $e->getMessage());
        }
    }


    public function show($id)
    {
        // Ambil data header beserta seluruh detail barangnya
        $adjustment = \App\Models\StockAdjustment::with([
            'warehouse',
            'adjuster',
            'items.item' // Memanggil detail items dan data master barangnya
        ])->findOrFail($id);

        return view('stock_adjustments.show', compact('adjustment'));
    }

    /**
     * 🔥 HELPER FIFO: Memotong stok dari tumpukan terkecil di satu gudang 🔥
     */
    private function reduceStockFifo($warehouseId, $itemId, $qtyToRemove)
    {
        $stocks = \App\Models\InventoryStock::where('warehouse_id', $warehouseId)
                    ->where('item_id', $itemId)
                    ->where('stock_qty', '>', 0)
                    ->orderBy('created_at', 'asc') // FIFO (Paling lama dipotong duluan)
                    ->get();

        foreach ($stocks as $s) {
            if ($qtyToRemove <= 0) break;

            if ($s->stock_qty <= $qtyToRemove) {
                $qtyToRemove -= $s->stock_qty;
                $s->update(['stock_qty' => 0]); // Habiskan baris ini
            } else {
                $s->decrement('stock_qty', $qtyToRemove); // Potong sebagian
                $qtyToRemove = 0;
            }
        }
    }


    // 5. AJAX: Pencarian Barang Khusus Stock Adjustment (Munculkan Semua Barang)
    public function searchItems(Request $request)
    {
        $search = $request->search;
        $selectedItems = $request->selected_items ?? []; // Tangkap array ID barang yang sudah dipilih

        // Ambil semua barang yang aktif, tanpa mempedulikan stok!
        $items = \App\Models\Item::where('is_active', true)
            ->when(!empty($selectedItems), function($query) use ($selectedItems) {
                // Sembunyikan barang yang ID-nya sudah ada di keranjang
                return $query->whereNotIn('id', $selectedItems);
            })
            ->when($search, function ($query, $search) {
                return $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->limit(50)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'text' => $item->code . ' - ' . $item->name,
                ];
            });

        return response()->json($items);
    }



    // 6. Cetak Berita Acara Opname (PDF)
    public function print($id)
    {
        $adjustment = \App\Models\StockAdjustment::with([
            'warehouse',
            'adjuster',
            'items.item'
        ])->findOrFail($id);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('stock_adjustments.print', compact('adjustment'))
                  ->setPaper('A4', 'portrait');

        // Unduh/Tampilkan file PDF dengan nama dinamis sesuai nomor dokumen
        $fileName = 'Berita_Acara_Opname_' . str_replace('/', '_', $adjustment->adjustment_number) . '.pdf';

        return $pdf->stream($fileName);
    }



}
