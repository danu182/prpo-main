<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReturnToVendor;
use App\Models\ReturnToVendorItem;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrderItem;
use App\Models\Item;
use App\Models\InventoryStock;
use App\Models\StockMutation;
use App\Models\FixedAsset;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnToVendorController extends Controller
{
    /**
     * Generate Nomor RTV Dinamis (Contoh: RTV/PT-988/2026/03/0001)
     */
    private function generateRtvNumber($companyId)
    {
        $company = \App\Models\Company::find($companyId);
        $companyCode = $company && $company->code ? strtoupper($company->code) : 'UMUM';
        $year = date('Y');
        $month = date('m');

        $prefix = "RTV/{$companyCode}/{$year}/{$month}/";
        $lastRtv = ReturnToVendor::where('rtv_number', 'LIKE', "{$prefix}%")->orderBy('id', 'desc')->first();

        $nextSequence = $lastRtv ? ((int) substr($lastRtv->rtv_number, -4)) + 1 : 1;
        return $prefix . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Menampilkan Daftar Riwayat RTV
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $rtvs = ReturnToVendor::with(['vendor', 'goodsReceipt.po', 'returner'])
            ->when($search, function ($query) use ($search) {
                $query->where('rtv_number', 'like', "%{$search}%")
                      ->orWhere('delivery_note_number', 'like', "%{$search}%")
                      ->orWhereHas('vendor', function ($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      });
            })
            ->latest()
            ->paginate(10);

        return view('rtv.index', compact('rtvs'));
    }

    /**
     * Menampilkan Detail Spesifik Dokumen RTV
     */
    public function show($id)
    {
        $rtv = ReturnToVendor::with([
            'vendor',
            'goodsReceipt.po.company',
            'returner',
            'items.item'
        ])->findOrFail($id);

        return view('rtv.show', compact('rtv'));
    }

    /**
     * Halaman Form Pembuatan Retur (Berdasarkan Goods Receipt)
     */
    public function create($gr_id)
    {
        // Tarik data GR beserta Item dan PO-nya
        $gr = GoodsReceipt::with(['items.item', 'items.purchaseOrderItem', 'po.vendor', 'po.company'])->findOrFail($gr_id);
        $reasons = \App\Models\ReturnReason::where('is_active', true)->orderBy('name')->get();

        // 🔥 LOGIKA EMAS RTV: Cek Stok Gudang vs Jatah GR 🔥
        $returnableItems = $gr->items->filter(function ($item) {
            // 1. Sisa kuota penerimaan (GR) yang belum di-retur sebelumnya
            $sisaKuotaGR = $item->qty_received - ($item->qty_returned ?? 0);

            // 2. Cek fisik di gudang (Hanya berlaku untuk barang stok)
            if ($item->item && $item->item->is_stockable) {
                $stokGudang = $item->item->current_stock ?? 0;
                // Batas maksimal retur adalah angka TERKECIL antara Jatah GR dan Stok Gudang
                $item->max_returnable = min($sisaKuotaGR, $stokGudang);
            } else {
                // Jika jasa/non-stok, bebas retur sebatas kuota GR
                $item->max_returnable = $sisaKuotaGR;
            }

            // Hanya tampilkan di form jika masih ada yang bisa diretur
            return $item->max_returnable > 0;
        });

        if ($returnableItems->isEmpty()) {
            return redirect()->route('gr.index')->with('error', 'Semua barang dari GR ini sudah diretur ke vendor, ATAU stok fisiknya di gudang sedang kosong (sedang dipinjam karyawan).');
        }

        return view('rtv.create', compact('gr', 'reasons', 'returnableItems'));
    }

    /**
     * Eksekusi Proses Retur (CORE LOGIC)
     */
    public function store(Request $request, $gr_id)
    {
        $request->validate([
            'return_date' => 'required|date',
            'items' => 'required|array',
            'items.*.qty_returned' => 'required|numeric|min:0',
        ]);

        try {
            DB::transaction(function () use ($request, $gr_id) {
                $gr = GoodsReceipt::with('po')->findOrFail($gr_id);
                $companyId = $gr->po->bill_to_company_id;

                // 1. Buat Header Dokumen RTV
                $rtv = ReturnToVendor::create([
                    'rtv_number' => $this->generateRtvNumber($companyId),
                    'goods_receipt_id' => $gr->id,
                    'vendor_id' => $gr->po->vendor_id,
                    'return_date' => $request->return_date,
                    'delivery_note_number' => $request->delivery_note_number,
                    'returned_by' => auth()->id(),
                    'notes' => $request->notes,
                ]);

                $totalQtyReturnedInThisTransaction = 0;

                // 2. Looping Item yang Diretur
                foreach ($request->items as $grItemId => $data) {
                    $qtyReturned = (float) ($data['qty_returned'] ?? 0);

                    // Hanya proses yang qty return-nya di atas 0
                    if ($qtyReturned > 0) {
                        $totalQtyReturnedInThisTransaction += $qtyReturned;
                        $grItem = GoodsReceiptItem::findOrFail($grItemId);
                        $poItem = PurchaseOrderItem::findOrFail($grItem->purchase_order_item_id);
                        $masterItem = Item::findOrFail($grItem->item_id);

                        // ==============================================================
                        // 🔥 A. VALIDASI 1: Cek Sisa Jatah dari GR Ini
                        // ==============================================================
                        $sisaKuotaGR = $grItem->qty_received - ($grItem->qty_returned ?? 0);
                        if ($qtyReturned > $sisaKuotaGR) {
                            throw new \Exception("Jumlah retur {$masterItem->name} melebihi sisa penerimaan di dokumen GR ini!");
                        }

                        // ==============================================================
                        // 🔥 B. VALIDASI 2: Cek Fisik Gudang (Jika Barang Stok)
                        // ==============================================================
                        if ($masterItem->is_stockable) {
                            $balanceBefore = (float) $masterItem->current_stock;
                            if ($qtyReturned > $balanceBefore) {
                                throw new \Exception("Gagal! Anda mencoba me-retur {$qtyReturned} {$masterItem->unit}, tapi stok fisik '{$masterItem->name}' di gudang saat ini hanya tersisa {$balanceBefore} {$masterItem->unit} (Kemungkinan sedang dipinjam karyawan).");
                            }
                        }

                        // C. Ambil Nama Alasan Retur
                        $reasonName = 'Alasan Lainnya';
                        if (isset($data['return_reason_id']) && !empty($data['return_reason_id'])) {
                            $reasonModel = \App\Models\ReturnReason::find($data['return_reason_id']);
                            if ($reasonModel) {
                                $reasonName = $reasonModel->name;
                            }
                        }

                        // D. Simpan Data Item RTV
                        ReturnToVendorItem::create([
                            'return_to_vendor_id' => $rtv->id,
                            'goods_receipt_item_id' => $grItem->id,
                            'purchase_order_item_id' => $poItem->id,
                            'item_id' => $masterItem->id,
                            'qty_returned' => $qtyReturned,
                            'return_reason' => $reasonName,
                        ]);

                        // E. MUNDURKAN JATAH PO & TAMBAH QTY RETURNED DI GR
                        $poItem->decrement('qty_received', $qtyReturned);
                        $grItem->increment('qty_returned', $qtyReturned);

                        // ==============================================================
                        // F. LOGIKA CERDAS: STOK vs NON-STOK vs ASET
                        // ==============================================================
                        if ($masterItem->is_stockable) {
                            // Stok sudah divalidasi di atas, tinggal potong
                            $balanceAfter = $balanceBefore - $qtyReturned;

                            $masterItem->update(['current_stock' => $balanceAfter]);

                            // Catat Kartu Mutasi (OUT)
                            StockMutation::create([
                                'item_id' => $masterItem->id,
                                'type' => 'OUT',
                                'qty' => $qtyReturned,
                                'balance_before' => $balanceBefore,
                                'balance_after' => $balanceAfter,
                                'reference_number' => $rtv->rtv_number,
                                'notes' => "Retur ke Vendor (Recall/Cacat). Alasan: " . $reasonName,
                                'created_by' => auth()->id(),
                            ]);
                        }
                        elseif ($masterItem->is_asset) {
                            // JIKA BARANG ASET: Ubah status KTP Aset menjadi "Returned"
                            $assetsToReturn = FixedAsset::where('goods_receipt_id', $gr->id)
                                ->where('item_id', $masterItem->id)
                                ->where('status', '!=', 'Returned')
                                ->limit($qtyReturned)
                                ->get();

                            foreach($assetsToReturn as $ast) {
                                $ast->update([
                                    'status' => 'Returned',
                                    'notes' => 'Dikembalikan ke vendor via Dokumen RTV: ' . $rtv->rtv_number
                                ]);
                            }
                        }
                        // JIKA NON-STOK: Sistem tidak melakukan apapun di gudang. Bebas Error!
                    }
                }

                // Pengecekan Jika tidak ada barang yang diretur sama sekali
                if ($totalQtyReturnedInThisTransaction == 0) {
                    throw new \Exception("Anda harus mengisi minimal 1 qty barang yang akan diretur.");
                }

                // ==============================================================
                // G. EVALUASI STATUS PO KEMBALI
                // ==============================================================
                $po = $gr->po;
                $po->refresh();
                $allFullyReceived = true;

                foreach ($po->items as $item) {
                    if (($item->qty_received ?? 0) < $item->qty_ordered) {
                        $allFullyReceived = false;
                        break;
                    }
                }

                if (!$allFullyReceived && optional($po->status)->slug === 'fully_received') {
                    $statusPartial = Status::where('type', 'PO')->where('slug', 'partial_receipt')->first();
                    if ($statusPartial) {
                        $po->update(['status_id' => $statusPartial->id]);

                        // Catat histori mundurnya status PO
                        \App\Models\PurchaseOrderHistory::create([
                            'purchase_order_id' => $po->id,
                            'user_id' => auth()->id(),
                            'action' => 'RETURN TO VENDOR',
                            'note' => "Terdapat barang yang diretur (RTV: {$rtv->rtv_number}). Status PO dikembalikan menjadi Penerimaan Parsial agar vendor bisa mengirim ulang barang.",
                        ]);
                    }
                }
            });

            return redirect()->route('rtv.index')->with('success', 'Dokumen Return to Vendor (RTV) berhasil diterbitkan! Stok dan Status PO telah disesuaikan.');

        } catch (\Exception $e) {
            Log::error('Error Simpan RTV: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Gagal memproses Retur: ' . $e->getMessage());
        }
    }
}
