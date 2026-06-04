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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnToVendorController extends Controller
{
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

    public function create($gr_id)
    {
        $gr = GoodsReceipt::with(['items.item', 'items.purchaseOrderItem', 'po.vendor', 'po.company'])->findOrFail($gr_id);
        $reasons = \App\Models\ReturnReason::where('is_active', true)->orderBy('name')->get();

        $returnableItems = $gr->items->filter(function ($item) {
            $sisaKuotaGR = $item->qty_received - ($item->qty_returned ?? 0);

            if ($item->item && $item->item->is_stockable) {
                // 🔥 BACA STOK DARI TABEL INVENTORY STOCK (Multi-Warehouse Ready)
                $stokGudang = \App\Models\InventoryStock::where('item_id', $item->item_id)->sum('stock_qty');
                $item->max_returnable = min($sisaKuotaGR, $stokGudang);
            } else {
                $item->max_returnable = $sisaKuotaGR;
            }

            return $item->max_returnable > 0;
        });

        if ($returnableItems->isEmpty()) {
            return redirect()->route('gr.index')->with('error', 'Semua barang dari GR ini sudah diretur, ATAU stok fisiknya di gudang sedang kosong (sedang dipinjam karyawan).');
        }

        return view('rtv.create', compact('gr', 'reasons', 'returnableItems'));
    }

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

                foreach ($request->items as $grItemId => $data) {
                    $qtyReturned = (float) ($data['qty_returned'] ?? 0);

                    if ($qtyReturned > 0) {
                        $totalQtyReturnedInThisTransaction += $qtyReturned;
                        $grItem = GoodsReceiptItem::findOrFail($grItemId);
                        $poItem = PurchaseOrderItem::findOrFail($grItem->purchase_order_item_id);
                        $masterItem = Item::findOrFail($grItem->item_id);

                        // 1. Validasi Sisa GR
                        $sisaKuotaGR = $grItem->qty_received - ($grItem->qty_returned ?? 0);
                        if ($qtyReturned > $sisaKuotaGR) {
                            throw new \Exception("Jumlah retur {$masterItem->name} melebihi sisa penerimaan di dokumen GR ini!");
                        }

                        $reasonName = 'Alasan Lainnya';
                        if (!empty($data['return_reason_id'])) {
                            $reasonModel = \App\Models\ReturnReason::find($data['return_reason_id']);
                            if ($reasonModel) $reasonName = $reasonModel->name;
                        }

                        // ==============================================================
                        // 🔥 2. PEMOTONGAN STOK GLOBAL DARI BATCH (INVENTORY STOCK) 🔥
                        // ==============================================================
                        if ($masterItem->is_stockable) {
                            // Cari batch stok yang tersedia (FIFO)
                            $availableStocks = \App\Models\InventoryStock::where('item_id', $masterItem->id)
                                                ->where('stock_qty', '>', 0)
                                                ->orderBy('created_at', 'asc') // Tarik yang paling lama dulu
                                                ->lockForUpdate()
                                                ->get();

                            $totalAvailable = $availableStocks->sum('stock_qty');

                            if ($qtyReturned > $totalAvailable) {
                                throw new \Exception("Gagal! Stok fisik '{$masterItem->name}' di sistem hanya tersisa {$totalAvailable}, tidak cukup untuk meretur {$qtyReturned} unit.");
                            }

                            $qtySisaRetur = $qtyReturned;

                            // Looping Potong Batch
                            foreach ($availableStocks as $stockRow) {
                                if ($qtySisaRetur <= 0) break;

                                $potong = min($stockRow->stock_qty, $qtySisaRetur);
                                $balanceBefore = (float) $stockRow->stock_qty;

                                $stockRow->decrement('stock_qty', $potong);
                                $balanceAfter = $balanceBefore - $potong;

                                $qtySisaRetur -= $potong;

                                // Catat Kartu Mutasi
                                StockMutation::create([
                                    'item_id' => $masterItem->id,
                                    'warehouse_id' => $stockRow->warehouse_id, // Ambil asal gudangnya
                                    'type' => 'OUT',
                                    'qty' => $potong,
                                    'balance_before' => $balanceBefore,
                                    'balance_after' => $balanceAfter,
                                    'reference_number' => $rtv->rtv_number,
                                    'notes' => "Retur ke Vendor. Alasan: {$reasonName} [Potong Batch: " . ($stockRow->reference_number ?? 'Awal') . "]",
                                    'created_by' => auth()->id(),
                                ]);
                            }

                            // Update Master Item untuk jaga-jaga
                            $masterItem->decrement('current_stock', $qtyReturned);
                        }
                        elseif ($masterItem->is_asset) {
                            // Logic Aset Tetap
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

                        // 3. Simpan Item RTV & Update GR/PO
                        ReturnToVendorItem::create([
                            'return_to_vendor_id' => $rtv->id,
                            'goods_receipt_item_id' => $grItem->id,
                            'purchase_order_item_id' => $poItem->id,
                            'item_id' => $masterItem->id,
                            'qty_returned' => $qtyReturned,
                            'return_reason' => $reasonName,
                        ]);

                        $poItem->decrement('qty_received', $qtyReturned);
                        $grItem->increment('qty_returned', $qtyReturned);
                    }
                }

                if ($totalQtyReturnedInThisTransaction == 0) {
                    throw new \Exception("Anda harus mengisi minimal 1 qty barang yang akan diretur.");
                }

                // ==============================================================
                // 4. EVALUASI STATUS PO KEMBALI
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

                        \App\Models\PurchaseOrderHistory::create([
                            'purchase_order_id' => $po->id,
                            'user_id' => auth()->id(),
                            'action' => 'RETURN TO VENDOR',
                            'note' => "Terdapat barang diretur (RTV: {$rtv->rtv_number}). Status PO dikembalikan menjadi Penerimaan Parsial agar vendor bisa mengirim ulang barang.",
                        ]);
                    }
                }
            });

            return redirect()->route('rtv.index')->with('success', 'Dokumen Return to Vendor (RTV) berhasil diterbitkan! Stok Gudang dan Status PO telah disesuaikan.');

        } catch (\Exception $e) {
            Log::error('Error Simpan RTV: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Gagal memproses Retur: ' . $e->getMessage());
        }
    }
}
