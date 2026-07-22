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


    // 9. Ajukan Hasil Opname ke Matriks Approval
    public function submitApproval($id)
    {
        $opname = StockOpname::findOrFail($id);

        try {
            DB::beginTransaction();

            // 1. Cari rule matriks yang aktif untuk Stock Opname
            $workflow = \App\Models\ApprovalWorkflow::where('document_type', 'App\Models\StockOpname')
                            ->where('is_active', true)
                            ->first();

            if (!$workflow) {
                return back()->with('error', 'Gagal: Matriks Persetujuan untuk Stock Opname belum diatur oleh Administrator!');
            }

            // 2. Ambil nilai selisih untuk trigger level approval (Gunakan nilai absolute)
            $totalVariance = abs($opname->total_variance_value);

            // 3. Tarik langkah-langkah approval-nya
            $steps = \App\Models\ApprovalWorkflowStep::where('approval_workflow_id', $workflow->id)
                        ->orderBy('step_order', 'asc')
                        ->get();

            $approvalCreated = false;

            foreach ($steps as $step) {
                // Hanya buat antrean jika selisihnya memenuhi batas min_amount matriks
                if ($totalVariance >= $step->min_amount) {
                    $opname->approvals()->create([
                        'role_id' => $step->role_id,
                        'step_order' => $step->step_order,
                        'status' => 'pending',
                    ]);
                    $approvalCreated = true;
                }
            }

            if (!$approvalCreated) {
                return back()->with('error', 'Gagal: Nilai selisih tidak memenuhi batas minimum untuk diajukan pada matriks manapun.');
            }

            // 4. Update status dokumen menjadi Pending Approval
            $statusPending = Status::where('type', 'SO')->where('slug', 'pending_approval')->first();
            $opname->update([
                'status_id' => $statusPending ? $statusPending->id : $opname->status_id,
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Luar Biasa! Hasil Stock Opname berhasil diajukan dan antrean persetujuan telah dibentuk.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
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

            // Ambil seluruh stok dari gudang yang dipilih dan simpan ke Opname Items
            $stocks = InventoryStock::with('item.uom')->where('warehouse_id', $request->warehouse_id)
                                    ->where('stock_qty', '>', 0)
                                    ->get()
                                    ->groupBy('item_id'); // Gabungkan lot/batch yang item_id-nya sama

            $totalSystemValue = 0;

            foreach ($stocks as $itemId => $itemStocks) {
                $totalQty = $itemStocks->sum('stock_qty');
                $masterItem = $itemStocks->first()->item;

                // 🔥 LOGIKA BARU: Hitung Valuasi Langsung Dari Tumpukan GR Aktual 🔥
                // Mengalikan Qty * Harga Beli masing-masing tumpukan stok
                $actualStockValue = $itemStocks->sum(function($stock) {
                    return $stock->stock_qty * ($stock->unit_price ?? 0);
                });

                // Hitung Harga Rata-Rata Tertimbang (Weighted Average Cost)
                $unitPrice = $totalQty > 0 ? ($actualStockValue / $totalQty) : 0;

                // Fallback: Jika di tumpukan gudang harganya 0, baru intip ke Master Item
                if ($unitPrice == 0) {
                    $unitPrice = $masterItem->unit_price ?? $masterItem->purchase_price ?? 0;
                }

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
                             ->with('success', 'Sesi Stock Opname berhasil dibuka! Saldo sistem telah difoto.');

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

        // 🔥 FIX BUG: Izinkan edit jika statusnya 'draft' ATAU kosong (null)
        $statusSlug = optional($opname->status)->slug;
        if ($statusSlug !== 'draft' && $statusSlug !== null && $statusSlug !== '') {
            return redirect()->route('stock-opnames.show', $id)->with('error', 'Dokumen ini sudah tidak bisa diedit karena sedang diajukan atau selesai.');
        }

        return view('stock_opnames.edit', compact('opname'));
    }

    // 8. BATALKAN SESI (Hapus Draft)
    public function destroy($id)
    {
        $opname = StockOpname::findOrFail($id);

        $statusSlug = optional($opname->status)->slug;
        if ($statusSlug !== 'draft' && $statusSlug !== null && $statusSlug !== '') {
            return back()->with('error', 'Hanya dokumen berstatus Draft yang bisa dibatalkan!');
        }

        try {
            DB::beginTransaction();

            // Hapus file bukti fisik jika sudah terlanjur di-upload
            $attachments = StockOpnameAttachment::where('stock_opname_id', $id)->get();
            foreach ($attachments as $att) {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($att->file_path)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($att->file_path);
                }
            }

            // Hapus paksa (hard delete) agar bersih dari database
            StockOpnameItem::where('stock_opname_id', $id)->delete();
            $opname->forceDelete();

            DB::commit();
            return redirect()->route('stock-opnames.index')->with('success', 'Sesi Stock Opname berhasil dibatalkan dan dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal membatalkan sesi: ' . $e->getMessage());
        }
    }

    // 5. Simpan Hasil Hitung Fisik (Kalkulasi Otomatis Selisih Qty & Rupiah)
    public function update(Request $request, $id)
    {
        $opname = StockOpname::with('items')->findOrFail($id);

        $totalSystemValue = 0;
        $totalActualValue = 0;
        $totalVarianceValue = 0; // Total Nilai Absolut Selisih untuk Workflow

        try {
            DB::beginTransaction();

            foreach ($request->items as $itemId => $data) {
                $soItem = StockOpnameItem::findOrFail($itemId);

                $actualQty = (float) ($data['actual_qty'] ?? 0);

                // 🔥 RADAR HARGA CERDAS: Jika harga di SO masih Rp 0, paksa cari ke Master Barang!
                $unitPrice = (float) $soItem->unit_price;
                if ($unitPrice <= 0) {
                    $masterItem = \App\Models\Item::find($soItem->item_id);
                    if ($masterItem) {
                        $unitPrice = (float) ($masterItem->purchase_price ?? $masterItem->unit_price ?? 0);
                    }
                }

                $varianceQty = $actualQty - $soItem->system_qty;

                // Kalkulasi ulang seluruh komponen valuasi dengan harga terbaru
                $systemValue = $soItem->system_qty * $unitPrice;
                $actualValue = $actualQty * $unitPrice;
                $varianceValue = $varianceQty * $unitPrice;

                $soItem->update([
                    'actual_qty' => $actualQty,
                    'variance_qty' => $varianceQty,
                    'unit_price' => $unitPrice, // Kunci harga baru ke tabel Opname
                    'system_value' => $systemValue, // Revisi nilai sistem
                    'actual_value' => $actualValue,
                    'variance_value' => $varianceValue,
                    'notes' => $data['notes'] ?? null,
                ]);

                $totalSystemValue += $systemValue;
                $totalActualValue += $actualValue;
                // Hitung absolute (selisih plus/minus tetap dianggap nominal variance)
                $totalVarianceValue += abs($varianceValue);
            }

            // Simpan Dokumen Upload Bukti Fisik
            if ($request->hasFile('attachments')) {
                $basePath = \App\Models\SystemSetting::where('setting_key', 'path_stock_opnames')->value('setting_value') ?? 'attachments/stock_opname';
                $path = $basePath . '/' . str_replace(['/', '\\'], '-', $opname->document_number);

                foreach ($request->file('attachments') as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $storedPath = $file->storeAs($path, time() . '_' . $file->getClientOriginalName(), 'public');
                        \App\Models\StockOpnameAttachment::create([
                            'stock_opname_id' => $opname->id,
                            'file_name' => $file->getClientOriginalName(),
                            'file_path' => str_replace('\\', '/', $storedPath)
                        ]);
                    }
                }
            }

            // Revisi Total Valuasi di Header Dokumen
            $opname->update([
                'total_system_value' => $totalSystemValue,
                'total_actual_value' => $totalActualValue,
                'total_variance_value' => $totalVarianceValue,
            ]);

            DB::commit();

            return redirect()->route('stock-opnames.show', $opname->id)->with('success', 'Luar Biasa! Hasil fisik disimpan dan Valuasi Harga telah direvisi otomatis!');

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


    // 7. Cetak Lembar Kerja Opname (Blind Count Sheet) - Format PDF
    public function print($id)
    {
        $opname = StockOpname::with(['items.item', 'warehouse', 'creator'])->findOrFail($id);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('stock_opnames.print', compact('opname'))
                  ->setPaper('A4', 'portrait');

        return $pdf->stream('Stock_Opname_' . str_replace('/', '_', $opname->document_number) . '.pdf');
    }




    // ====================================================
    // 10. PROSES APPROVE (SETUJUI) & POTONG STOK OTOMATIS
    // ====================================================
    public function approve(Request $request, $id)
    {
        $opname = StockOpname::with('items', 'approvals')->findOrFail($id);

        try {
            DB::beginTransaction();

            // 1. Cari antrean pertama yang statusnya masih 'pending' berdasarkan urutan (Level terendah)
            $approval = $opname->approvals()
                ->where('status', 'pending')
                ->orderBy('step_order', 'asc')
                ->first();

            if (!$approval) {
                return back()->with('error', 'Tidak ada antrean persetujuan untuk dokumen ini.');
            }

            // 2. CEK OTORISASI (Jabatan sesuai matriks ATAU hak istimewa Super Admin)
            $user = auth()->user();
            $roleName = strtolower(optional($user->role)->name ?? ''); // Tarik nama role dari database

            // Super admin bisa dideteksi dari ID 1, nama, ATAU nama rolenya
            $isSuperAdmin = ($user->role_id == 1 || strtolower($user->name) == 'super administrator' || $roleName == 'super admin');

            if ($approval->role_id != $user->role_id && !$isSuperAdmin) {
                return back()->with('error', 'Gagal: Saat ini adalah giliran jabatan lain (Level ' . $approval->step_order . ') untuk menyetujui.');
            }

            // 3. Update status antrean saat ini menjadi Approved
            $approval->update([
                'status' => 'approved',
                'approved_by' => $user->id, // 🔥 UBAH 'user_id' MENJADI 'approver_id' DI SINI
                'note' => $request->notes ?? 'Disetujui', // 🔥 PASTIKAN SEBELAH KIRI HANYA 'note' (TANPA S)
                'approved_at' => now(),
            ]);

            // 4. Cek apakah masih ada sisa antrean persetujuan lain setelah ini
            $remainingApprovals = $opname->approvals()->where('status', 'pending')->count();

            // 5. JIKA SEMUA LEVEL SUDAH SETUJU -> EKSEKUSI FINALISASI STOK
            if ($remainingApprovals === 0) {
                // Ubah status dokumen SO menjadi Completed (Selesai)
                $statusCompleted = Status::where('type', 'SO')->where('slug', 'completed')->first();
                $opname->update([
                    'status_id' => $statusCompleted ? $statusCompleted->id : $opname->status_id,
                ]);

                // Eksekusi Pemotongan / Penambahan Stok Riil
                foreach ($opname->items as $item) {
                    if ($item->variance_qty == 0) continue; // Jika cocok, lewati

                    $masterItem = \App\Models\Item::find($item->item_id);
                    $newStockBalance = $masterItem->current_stock + $item->variance_qty;

                    if ($item->variance_qty > 0) {
                        // KASUS A: SURPLUS (BARANG LEBIH) -> Masukkan stok baru ke gudang
                        InventoryStock::create([
                            'company_id' => $opname->company_id,
                            'warehouse_id' => $opname->warehouse_id,
                            'item_id' => $item->item_id,
                            'stock_qty' => $item->variance_qty,
                            'unit_price' => $item->unit_price,
                            'reference_number' => $opname->document_number,
                            'notes' => 'Surplus Stock Opname',
                        ]);
                    } else {
                        // KASUS B: DEFICIT (BARANG HILANG/MINUS) -> Potong stok pakai metode FIFO
                        $qtyToDeduct = abs($item->variance_qty);

                        $availableStocks = InventoryStock::where('warehouse_id', $opname->warehouse_id)
                                            ->where('item_id', $item->item_id)
                                            ->where('stock_qty', '>', 0)
                                            ->orderBy('id', 'asc') // FIFO: Tumpukan terlama
                                            ->get();

                        foreach ($availableStocks as $stock) {
                            if ($qtyToDeduct <= 0) break;

                            if ($stock->stock_qty <= $qtyToDeduct) {
                                // Habiskan tumpukan ini
                                $qtyToDeduct -= $stock->stock_qty;
                                $stock->update(['stock_qty' => 0]);
                            } else {
                                // Potong sebagian tumpukan ini
                                $stock->update(['stock_qty' => $stock->stock_qty - $qtyToDeduct]);
                                $qtyToDeduct = 0;
                            }
                        }
                    }

                    // Update total qty saat ini di Master Barang
                    $masterItem->update(['current_stock' => $newStockBalance]);
                }
            }

            DB::commit();
            return redirect()->back()->with('success', 'Berhasil disetujui! ' . ($remainingApprovals === 0 ? 'Stok gudang telah direvisi secara otomatis.' : 'Menunggu persetujuan level selanjutnya.'));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error Approve Stock Opname: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    // ====================================================
    // 11. PROSES REJECT (TOLAK)
    // ====================================================
    public function reject(Request $request, $id)
    {
        $opname = StockOpname::findOrFail($id);

        try {
            DB::beginTransaction();

            $approval = $opname->approvals()
                ->where('status', 'pending')
                ->orderBy('step_order', 'asc')
                ->first();

            if (!$approval) {
                return back()->with('error', 'Tidak ada antrean yang bisa ditolak.');
            }

            $user = auth()->user();
            $isSuperAdmin = ($user->role_id == 1 || strtolower($user->name) == 'Super Administrator');

            if ($approval->role_id != $user->role_id && !$isSuperAdmin) {
                return back()->with('error', 'Gagal: Anda tidak berhak menolak dokumen ini.');
            }

            $approval->update([
                'status' => 'rejected',
                'approved_by' => $user->id, // 🔥 UBAH 'user_id' MENJADI 'approver_id' DI SINI
                'note' => $request->notes ?? 'Disetujui', // 🔥 PASTIKAN SEBELAH KIRI HANYA 'note' (TANPA S)
                'approved_at' => now(),
            ]);

            $statusRejected = Status::where('type', 'SO')->where('slug', 'rejected')->first();
            $opname->update([
                'status_id' => $statusRejected ? $statusRejected->id : $opname->status_id,
            ]);

            $opname->approvals()->where('status', 'pending')->update(['status' => 'cancelled']);

            DB::commit();
            return redirect()->back()->with('success', 'Dokumen ditolak. Proses dihentikan.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error Reject Stock Opname: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }





}
