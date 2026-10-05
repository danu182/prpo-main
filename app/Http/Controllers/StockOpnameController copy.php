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

            Warehouse::where('id', $request->warehouse_id)->update(['is_frozen' => true]);

            $stocks = InventoryStock::with('item.uom')->where('warehouse_id', $request->warehouse_id)
                                    ->where('stock_qty', '>', 0)
                                    ->get()
                                    ->groupBy('item_id');

            $totalSystemValue = 0;

            foreach ($stocks as $itemId => $itemStocks) {
                $totalQty = $itemStocks->sum('stock_qty');
                $masterItem = $itemStocks->first()->item;

                $actualStockValue = $itemStocks->sum(function($stock) {
                    return $stock->stock_qty * ($stock->unit_price ?? 0);
                });

                $unitPrice = $totalQty > 0 ? ($actualStockValue / $totalQty) : 0;

                if ($unitPrice == 0) {
                    $unitPrice = $masterItem->unit_price ?? $masterItem->purchase_price ?? 0;
                }

                $systemValue = $totalQty * $unitPrice;

                StockOpnameItem::create([
                    'stock_opname_id' => $so->id,
                    'item_id' => $itemId,
                    'base_uom' => optional($masterItem->uom)->name ?? 'PCS',
                    'system_qty' => $totalQty,
                    'actual_qty' => 0,
                    'unit_price' => $unitPrice,
                    'system_value' => $systemValue,
                ]);

                $totalSystemValue += $systemValue;
            }

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

        $statusSlug = optional($opname->status)->slug;
        if ($statusSlug !== 'draft' && $statusSlug !== null && $statusSlug !== '') {
            return redirect()->route('stock-opnames.show', $id)->with('error', 'Dokumen ini sudah tidak bisa diedit karena sedang diajukan atau selesai.');
        }

        return view('stock_opnames.edit', compact('opname'));
    }

    // 5. Simpan Hasil Hitung Fisik (Kalkulasi Otomatis Selisih Qty & Rupiah)
    public function update(Request $request, $id)
    {
        $opname = StockOpname::with('items')->findOrFail($id);

        $totalSystemValue = 0;
        $totalActualValue = 0;
        $totalVarianceValue = 0;

        try {
            DB::beginTransaction();

            foreach ($request->items as $itemId => $data) {
                $soItem = StockOpnameItem::findOrFail($itemId);

                $actualQty = (float) ($data['actual_qty'] ?? 0);
                $unitPrice = (float) $soItem->unit_price;
                if ($unitPrice <= 0) {
                    $masterItem = \App\Models\Item::find($soItem->item_id);
                    if ($masterItem) {
                        $unitPrice = (float) ($masterItem->purchase_price ?? $masterItem->unit_price ?? 0);
                    }
                }

                $varianceQty = $actualQty - $soItem->system_qty;

                $systemValue = $soItem->system_qty * $unitPrice;
                $actualValue = $actualQty * $unitPrice;
                $varianceValue = $varianceQty * $unitPrice;

                $soItem->update([
                    'actual_qty' => $actualQty,
                    'variance_qty' => $varianceQty,
                    'unit_price' => $unitPrice,
                    'system_value' => $systemValue,
                    'actual_value' => $actualValue,
                    'variance_value' => $varianceValue,
                    'notes' => $data['notes'] ?? null,
                ]);

                $totalSystemValue += $systemValue;
                $totalActualValue += $actualValue;
                $totalVarianceValue += abs($varianceValue);
            }

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

            $attachments = StockOpnameAttachment::where('stock_opname_id', $id)->get();
            foreach ($attachments as $att) {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($att->file_path)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($att->file_path);
                }
            }

            StockOpnameItem::where('stock_opname_id', $id)->delete();
            $opname->forceDelete();

            $warehouseId = $opname->warehouse_id;

            Warehouse::where('id', $warehouseId)->update(['is_frozen' => false]);

            DB::commit();
            return redirect()->route('stock-opnames.index')->with('success', 'Sesi Stock Opname berhasil dibatalkan dan dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal membatalkan sesi: ' . $e->getMessage());
        }
    }


    // =========================================================================
    // 9. AJUKAN PERSETUJUAN (SUBMIT APPROVAL)
    // =========================================================================
    public function submitApproval($id)
    {
        $opname = StockOpname::findOrFail($id);

        try {
            DB::beginTransaction();

            \App\Models\DocumentApproval::where('document_id', $opname->id)
                ->where('document_type', get_class($opname))
                ->delete();

            $workflow = \App\Models\ApprovalWorkflow::where('document_type', 'App\Models\StockOpname')
                            ->orWhere('document_type', 'SO')
                            ->orWhere('document_type', 'StockOpname')
                            ->where('is_active', true)
                            ->first();

            if (!$workflow) {
                return back()->with('error', 'Gagal: Matriks Persetujuan untuk Stock Opname belum diatur oleh Administrator!');
            }

            $totalVariance = abs($opname->total_variance_value);

            $steps = \App\Models\ApprovalWorkflowStep::where('approval_workflow_id', $workflow->id)
                        ->orderBy('step_order', 'asc')
                        ->get();

            $approvalCreated = false;

            foreach ($steps as $step) {
                if ($totalVariance >= $step->min_amount) {
                    $opname->approvals()->create([
                        'role_id'              => $step->role_id,
                        'target_department_id' => $step->target_department_id ?? $step->department_id ?? null,
                        'step_order'           => $step->step_order,
                        'status'               => 'PENDING',
                    ]);
                    $approvalCreated = true;
                }
            }

            if (!$approvalCreated) {
                $statusApproved = Status::where('type', 'SO')->where('slug', 'approved')->first();
                $opname->update(['status_id' => $statusApproved ? $statusApproved->id : $opname->status_id]);

                // Jika langsung approved, jalankan finalisasi stok
                $this->finalizeStockOpname($opname);

                DB::commit();
                return redirect()->back()->with('success', 'Stock Opname otomatis disetujui karena selisih nilai tidak memerlukan persetujuan.');
            }

            $statusPending = Status::where('type', 'SO')->whereIn('slug', ['pending_approval', 'pending'])->first();
            $opname->update(['status_id' => $statusPending ? $statusPending->id : $opname->status_id]);

            DB::commit();
            return redirect()->back()->with('success', 'Luar Biasa! Hasil Stock Opname berhasil diajukan ke antrean persetujuan.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }


    // ====================================================
    // 10. PROSES APPROVE (SETUJUI) & POTONG STOK OTOMATIS
    // ====================================================
    public function approve(Request $request, $id)
    {
        $opname = StockOpname::with('items', 'approvals')->findOrFail($id);

        try {
            DB::beginTransaction();

            $approval = $opname->approvals()
                ->where('status', 'PENDING')
                ->orderBy('step_order', 'asc')
                ->first();

            if (!$approval) {
                return back()->with('error', 'Tidak ada antrean persetujuan untuk dokumen ini.');
            }

            $user = auth()->user();
            $isSuperAdmin = $user->hasRole(['Super Administrator', 'Super Admin']) || $user->id === 1;

            if ($approval->role_id != $user->roles->first()->id && !$isSuperAdmin) {
                return back()->with('error', 'Gagal: Anda tidak berhak menyetujui di tahap ini.');
            }

            $approval->update([
                'status' => 'APPROVED',
                'approved_by' => $user->id,
                'note' => $request->notes ?? 'Disetujui',
                'approved_at' => now(),
            ]);

            $remainingApprovals = $opname->approvals()
                ->where('status', 'PENDING')
                ->count();

            if ($remainingApprovals === 0) {
                $statusApproved = \App\Models\Status::where('type', 'SO')->where('slug', 'approved')->first();
                $opname->update(['status_id' => $statusApproved ? $statusApproved->id : $opname->status_id]);

                // Panggil Helper Finalisasi
                $this->finalizeStockOpname($opname);
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
                ->where('status', 'PENDING')
                ->orderBy('step_order', 'asc')
                ->first();

            if (!$approval) {
                return back()->with('error', 'Tidak ada antrean yang bisa ditolak.');
            }

            $user = auth()->user();
            $isSuperAdmin = $user->hasRole(['Super Administrator', 'Super Admin']) || $user->id === 1;

            if ($approval->role_id != $user->roles->first()->id && !$isSuperAdmin) {
                return back()->with('error', 'Gagal: Anda tidak berhak menolak dokumen ini.');
            }

            $approval->update([
                'status' => 'REJECTED',
                'approved_by' => $user->id,
                'note' => $request->notes ?? 'Ditolak',
                'approved_at' => now(),
            ]);

            $statusRejected = Status::where('type', 'SO')->where('slug', 'rejected')->first();
            $opname->update(['status_id' => $statusRejected ? $statusRejected->id : $opname->status_id]);

            $opname->approvals()->where('status', 'PENDING')->update(['status' => 'REJECTED']);

            // LEPAS GEMBOK
            Warehouse::where('id', $opname->warehouse_id)->update(['is_frozen' => false]);

            DB::commit();
            return redirect()->back()->with('success', 'Dokumen ditolak. Proses dihentikan.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error Reject Stock Opname: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    // ====================================================
    // 7. CETAK LAPORAN HASIL AUDIT STOK (PDF)
    // ====================================================
    public function cetakHasil($id)
    {
        $opname = StockOpname::with([
            'items.item',
            'warehouse',
            'company',
            'creator',
            'status',
            'approvals.role',
            'approvals.approver'
        ])->findOrFail($id);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('stock_opnames.cetak_hasil', compact('opname'))
                  ->setPaper('A4', 'portrait');

        $namaFile = 'Hasil_Audit_Stok_' . str_replace('/', '_', $opname->document_number) . '.pdf';
        return $pdf->stream($namaFile);
    }


    // ====================================================
    // HELPER: FINALISASI STOK GUDANG (DRY/CLEAN)
    // ====================================================
    private function finalizeStockOpname($opname)
    {
        foreach ($opname->items as $item) {
            if ($item->variance_qty == 0) continue;

            $masterItem = \App\Models\Item::find($item->item_id);
            if (!$masterItem) continue;

            $newStockBalance = $masterItem->current_stock + $item->variance_qty;

            if ($item->variance_qty > 0) {
                \App\Models\InventoryStock::create([
                    'company_id' => $opname->company_id,
                    'warehouse_id' => $opname->warehouse_id,
                    'item_id' => $item->item_id,
                    'stock_qty' => $item->variance_qty,
                    'unit_price' => $item->unit_price,
                    'reference_number' => $opname->document_number,
                    'notes' => 'Surplus Stock Opname',
                ]);
            } else {
                $qtyToDeduct = abs($item->variance_qty);
                $availableStocks = \App\Models\InventoryStock::where('warehouse_id', $opname->warehouse_id)
                                    ->where('item_id', $item->item_id)
                                    ->where('stock_qty', '>', 0)
                                    ->orderBy('id', 'asc')
                                    ->get();

                foreach ($availableStocks as $stock) {
                    if ($qtyToDeduct <= 0) break;

                    if ($stock->stock_qty <= $qtyToDeduct) {
                        $qtyToDeduct -= $stock->stock_qty;
                        $stock->update(['stock_qty' => 0]);
                    } else {
                        $stock->update(['stock_qty' => $stock->stock_qty - $qtyToDeduct]);
                        $qtyToDeduct = 0;
                    }
                }
            }

            $masterItem->update(['current_stock' => $newStockBalance]);
        }

        Warehouse::where('id', $opname->warehouse_id)->update(['is_frozen' => false]);
    }
}
