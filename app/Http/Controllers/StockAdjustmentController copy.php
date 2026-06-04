<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\StockAdjustment;
use App\Models\StockMutation;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{
    // 1. Tampilkan Form Koreksi Stok
    public function create()
    {
        // PERBAIKAN: Hanya ambil barang yang statusnya "Lacak di Gudang / Stockable"
        $items = Item::where('is_stockable', true)->orderBy('name', 'asc')->get();

        return view('stock_adjustments.create', compact('items'));
    }

    // 2. Proses Penyesuaian Stok
    public function store(Request $request)
    {
        $request->validate([
            'adjustment_date' => 'required|date|before_or_equal:today',
            'item_id'         => 'required|exists:items,id',
            'new_stock'       => 'required|numeric|min:0',
            'reason'          => 'required|string|max:255',
        ]);

        try {
            DB::transaction(function () use ($request) {
                // Lock item agar aman dari bentrok data
                $item = Item::lockForUpdate()->findOrFail($request->item_id);

                $previousStock = (float) $item->current_stock;
                $newStock      = (float) $request->new_stock;
                $difference    = $newStock - $previousStock;

                // Jika angkanya sama, tidak perlu ada penyesuaian!
                if ($difference == 0) {
                    throw new \Exception("Gagal! Stok fisik yang Anda masukkan sama persis dengan stok di sistem ({$previousStock}). Tidak ada penyesuaian yang perlu dilakukan.");
                }

                // A. Generate Nomor Adjustment (Cth: ADJ/2026/03/0001)
                $year = date('Y', strtotime($request->adjustment_date));
                $month = date('m', strtotime($request->adjustment_date));
                $lastAdj = StockAdjustment::whereYear('created_at', $year)
                                          ->whereMonth('created_at', $month)
                                          ->orderBy('id', 'desc')->first();

                $nextId = $lastAdj ? ((int) substr($lastAdj->adjustment_number, -4)) + 1 : 1;
                $adjNumber = 'ADJ/' . $year . '/' . $month . '/' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

                // B. Simpan Dokumen Adjustment
                StockAdjustment::create([
                    'adjustment_number' => $adjNumber,
                    'adjustment_date'   => $request->adjustment_date,
                    'item_id'           => $item->id,
                    'previous_stock'    => $previousStock,
                    'new_stock'         => $newStock,
                    'difference'        => $difference,
                    'reason'            => $request->reason,
                    'adjusted_by'       => auth()->id(),
                ]);

                // C. Catat di Kartu Stok
                // Jika selisihnya positif (+), berarti IN. Jika negatif (-), berarti OUT.
                $type = $difference > 0 ? 'IN' : 'OUT';
                $qtyToRecord = abs($difference); // Jadikan positif mutlak untuk kuantitas

                StockMutation::create([
                    'item_id'          => $item->id,
                    'type'             => $type,
                    'qty'              => $qtyToRecord,
                    'balance_before'   => $previousStock,
                    'balance_after'    => $newStock,
                    'reference_number' => $adjNumber,
                    'notes'            => "Stock Opname: {$request->reason}",
                    'created_by'       => auth()->id(),
                ]);

                // D. Update Angka Master Stok
                $item->update(['current_stock' => $newStock]);
            });

            // PERBAIKAN: Redirect ke halaman Inventory (Master Stok), BUKAN ke fixed-assets!
            return redirect()->route('inventory.index')->with('success', 'Penyesuaian stok berhasil disimpan dan Kartu Stok telah diperbarui!');

        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
