<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\StockOpnameAttachment;
use App\Models\InventoryStock;
use App\Models\Warehouse;
use App\Models\Company;
use App\Models\Status;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockOpnameController extends Controller
{
    private function generateDocumentNumber($companyId)
    {
        $company = Company::find($companyId);
        $companyCode = $company ? strtoupper($company->code) : 'HO';
        $prefix = "SO-{$companyCode}-" . date('Ym') . "-";

        $lastDoc = StockOpname::where('document_number', 'like', "{$prefix}%")->orderBy('id', 'desc')->first();
        $nextSeq = $lastDoc ? ((int) substr($lastDoc->document_number, -4)) + 1 : 1;

        return $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
    }

    // 1. Tampilkan Daftar Opname
    public function index(Request $request)
    {
        $opnames = StockOpname::with(['warehouse', 'status', 'creator'])
                    ->when($request->search, function($q, $search){
                        $q->where('document_number', 'like', "%{$search}%");
                    })
                    ->orderBy('created_at', 'desc')
                    ->paginate(10);

        return view('stock_opnames.index', compact('opnames'));
    }

    // 2. Form Buka Sesi Opname (Pilih Gudang)
    public function create()
    {
        $warehouses = Warehouse::where('is_active', true)->get();
        $companies = Company::all();
        return view('stock_opnames.create', compact('warehouses', 'companies'));
    }

    // 3. GENERATE SNAPSHOT (Memotret Saldo Sistem Detik Ini)
    public function store(Request $request)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'start_date' => 'required|date'
        ]);

        try {
            DB::beginTransaction();

            // Status 1 = DRAFT / COUNTING (Sedang Menghitung)
            $statusDraft = Status::where('type', 'SO')->where('slug', 'draft')->first();

            $so = StockOpname::create([
                'document_number' => $this->generateDocumentNumber($request->company_id),
                'company_id' => $request->company_id,
                'warehouse_id' => $request->warehouse_id,
                'status_id' => $statusDraft ? $statusDraft->id : null,
                'start_date' => $request->start_date,
                'created_by' => auth()->id(),
                'notes' => $request->notes,
            ]);

            // 🔥 MAGIC: Ambil seluruh stok dari gudang yang dipilih dan simpan ke Opname Items
            $stocks = InventoryStock::with('item.uom')->where('warehouse_id', $request->warehouse_id)
                                    ->where('stock_qty', '>', 0)
                                    ->get()
                                    ->groupBy('item_id'); // Gabungkan jika ada lot/batch yang item_id-nya sama

            $totalSystemValue = 0;

            foreach ($stocks as $itemId => $itemStocks) {
                $totalQty = $itemStocks->sum('stock_qty');
                $masterItem = $itemStocks->first()->item;

                // Ambil harga HPP (Asumsi Anda punya field unit_price/hpp di tabel item)
                $unitPrice = $masterItem->unit_price ?? $masterItem->purchase_price ?? 0;
                $systemValue = $totalQty * $unitPrice;

                StockOpnameItem::create([
                    'stock_opname_id' => $so->id,
                    'item_id' => $itemId,
                    'base_uom' => optional($masterItem->uom)->name ?? 'PCS',
                    'system_qty' => $totalQty,
                    'actual_qty' => 0, // Belum diinput
                    'unit_price' => $unitPrice,
                    'system_value' => $systemValue,
                ]);

                $totalSystemValue += $systemValue;
            }

            // Update Total Valuasi Sistem ke Header
            $so->update(['total_system_value' => $totalSystemValue]);

            DB::commit();

            return redirect()->route('stock-opnames.show', $so->id)
                             ->with('success', 'Sesi Stock Opname berhasil dibuka! Saldo sistem telah difoto. Silakan cetak Lembar Kerja (Blind Count).');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error Generate Stock Opname: ' . $e->getMessage());
            return back()->with('error', 'Gagal membuka sesi: ' . $e->getMessage());
        }
    }

    // 4. Form Input Hasil Fisik (Data Entry & Upload File Bukti)
    public function edit($id)
    {
        $opname = StockOpname::with(['items.item.itemUoms', 'warehouse'])->findOrFail($id);

        // Cek status agar yang sudah dikirim ke Atasan tidak bisa diubah-ubah angkanya
        if (optional($opname->status)->slug !== 'draft') {
            return redirect()->route('stock-opnames.show', $id)->with('error', 'Dokumen ini sudah tidak bisa diedit karena sedang diajukan atau selesai.');
        }

        return view('stock_opnames.edit', compact('opname'));
    }

    // 5. Simpan Hasil Hitung Fisik (Kalkulasi Otomatis Selisih Qty & Rupiah)
    public function update(Request $request, $id)
    {
        $opname = StockOpname::with('items')->findOrFail($id);
        $totalActualValue = 0;
        $totalVarianceValue = 0; // Total Nilai Absolut Selisih untuk Workflow

        try {
            DB::beginTransaction();

            foreach ($request->items as $itemId => $data) {
                $soItem = StockOpnameItem::findOrFail($itemId);

                $actualQty = (float) ($data['actual_qty'] ?? 0);
                $unitPrice = $soItem->unit_price;

                $varianceQty = $actualQty - $soItem->system_qty;

                $actualValue = $actualQty * $unitPrice;
                $varianceValue = $varianceQty * $unitPrice;

                $soItem->update([
                    'actual_qty' => $actualQty,
                    'variance_qty' => $varianceQty,
                    'actual_value' => $actualValue,
                    'variance_value' => $varianceValue,
                    'notes' => $data['notes'] ?? null,
                ]);

                $totalActualValue += $actualValue;
                // Hitung absolute (selisih plus/minus tetap dianggap nominal variance)
                $totalVarianceValue += abs($varianceValue);
            }

            // Simpan Dokumen Upload Bukti Fisik
            if ($request->hasFile('attachments')) {
                $path = 'attachments/stock_opnames/' . str_replace(['/', '\\'], '-', $opname->document_number);
                foreach ($request->file('attachments') as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $storedPath = $file->storeAs($path, time() . '_' . $file->getClientOriginalName(), 'public');
                        StockOpnameAttachment::create([
                            'stock_opname_id' => $opname->id,
                            'file_name' => $file->getClientOriginalName(),
                            'file_path' => str_replace('\\', '/', $storedPath)
                        ]);
                    }
                }
            }

            $opname->update([
                'total_actual_value' => $totalActualValue,
                'total_variance_value' => $totalVarianceValue,
            ]);

            DB::commit();

            return redirect()->route('stock-opnames.show', $opname->id)->with('success', 'Hasil hitung fisik dan kalkulasi selisih (Variance) berhasil disimpan!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyimpan hasil: ' . $e->getMessage());
        }
    }

    // 6. Detail Review Opname
    public function show($id)
    {
        $opname = StockOpname::with(['items.item', 'attachments', 'warehouse', 'status', 'approvals.role', 'approvals.approver'])->findOrFail($id);
        return view('stock_opnames.show', compact('opname'));
    }


    // 7. Cetak Lembar Kerja Opname (Blind Count Sheet)
    public function print($id)
    {
        $opname = StockOpname::with(['items.item', 'warehouse', 'creator'])->findOrFail($id);
        return view('stock_opnames.print', compact('opname'));
    }




}
