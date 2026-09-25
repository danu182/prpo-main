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

        // 🔥 LOGIKA EMAS RTV: Cek Stok Gudang vs Jatah Dokumen GR 🔥
        $returnableItems = $gr->items->filter(function ($item) use ($gr) {
            $masterItem = $item->item;
            if (!$masterItem) return false;

            $baseUomId = $masterItem->uom_id ?? null;

            // 1. CARI TAHU KONVERSI UOM GR KE BASE UOM (PIECES)
            $grUomId = $item->uom_id;
            $grConvRate = 1;
            if ($grUomId && $grUomId != $baseUomId) {
                $altGr = \Illuminate\Support\Facades\DB::table('item_uoms')->where('id', $grUomId)->first();
                if ($altGr) $grConvRate = (float)$altGr->conversion_qty;
            } elseif (preg_match('/\(Isi:?\s*([0-9.]+)/i', $item->uom, $m)) {
                $grConvRate = (float)$m[1];
            }

            // Variabel Wajib untuk Blade
            $item->gr_uom_text = $item->uom ?? 'PCS';
            $item->gr_conv_rate = $grConvRate;

            // 2. CARI GUDANG TEMPAT BARANG INI DITERIMA DULU
            $inMutation = \App\Models\StockMutation::where('reference_number', $gr->gr_number)
                ->where('item_id', $item->item_id)
                ->where('type', 'IN')
                ->first();
            $warehouseId = $inMutation ? $inMutation->warehouse_id : 1;
            $item->detected_warehouse_id = $warehouseId;

            // 3. HITUNG SISA JATAH DOKUMEN
            $sisaKuotaGR = (float)$item->qty_received - (float)($item->qty_returned ?? 0);

            // 4. HITUNG SISA FISIK GUDANG & TENTUKAN BATAS MAKSIMAL
            if ($masterItem->is_stockable) {
                $invStock = \App\Models\InventoryStock::where('item_id', $item->item_id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();
                
                $stokGudangBase = $invStock ? (float)$invStock->stock_qty : 0;
                $stokGudangDalamUomGr = $stokGudangBase / $grConvRate; // Ubah fisik ke UOM GR

                // Max Return adalah angka TERKECIL antara Jatah GR dan Sisa Fisik Gudang
                $item->max_returnable = min($sisaKuotaGR, $stokGudangDalamUomGr);
            } else {
                $item->max_returnable = $sisaKuotaGR;
            }

            // 5. BAWA DAFTAR SN (JIKA ADA)
            if ($masterItem->is_trackable || $masterItem->is_asset) {
                $item->available_sn_list = \Illuminate\Support\Facades\DB::table('item_serials')
                    ->where('goods_receipt_id', $gr->id)
                    ->where('item_id', $item->item_id)
                    ->where('status', 'AVAILABLE')
                    ->pluck('serial_number')
                    ->toArray();
            } else {
                $item->available_sn_list = [];
            }

            return round($item->max_returnable, 4) > 0;
        });

        if ($returnableItems->isEmpty()) {
            return redirect()->route('gr.index')->with('error', 'Semua barang dari GR ini sudah diretur, ATAU stok fisiknya di gudang saat ini kosong (sudah dipakai via GI).');
        }

        $warehouses = \App\Models\Warehouse::orderBy('name')->get();

        return view('rtv.create', compact('gr', 'reasons', 'returnableItems', 'warehouses'));
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
                    $qtyReturnedInput = (float) ($data['qty_returned'] ?? 0);

                    if ($qtyReturnedInput > 0) {
                        $totalQtyReturnedInThisTransaction += $qtyReturnedInput;
                        $grItem = GoodsReceiptItem::findOrFail($grItemId);
                        $poItem = PurchaseOrderItem::findOrFail($grItem->purchase_order_item_id);
                        $masterItem = Item::findOrFail($grItem->item_id);
                        $baseUomId = $masterItem->uom_id ?? null;

                        // 1. CARI TAHU KONVERSI UOM GR KE BASE
                        $grConvRate = 1;
                        $grUomId = $grItem->uom_id;
                        if ($grUomId && $grUomId != $baseUomId) {
                            $altGr = \Illuminate\Support\Facades\DB::table('item_uoms')->where('id', $grUomId)->first();
                            if ($altGr) $grConvRate = (float)$altGr->conversion_qty;
                        } elseif (preg_match('/\(Isi:?\s*([0-9.]+)/i', $grItem->uom, $m)) {
                            $grConvRate = (float)$m[1];
                        }

                        $baseQtyReturned = $qtyReturnedInput * $grConvRate;

                        // 2. VALIDASI KUOTA GR
                        $sisaKuotaGR = (float)$grItem->qty_received - (float)($grItem->qty_returned ?? 0);
                        if (round($qtyReturnedInput, 4) > round($sisaKuotaGR, 4)) {
                            throw new \Exception("Jumlah retur melebihi sisa penerimaan di dokumen GR!");
                        }

                        // 3. CARI GUDANG & VALIDASI FISIK (JIKA STOK)
                        $inMutation = \App\Models\StockMutation::where('reference_number', $gr->gr_number)
                            ->where('item_id', $masterItem->id)
                            ->where('type', 'IN')
                            ->first();
                        $warehouseId = $inMutation ? $inMutation->warehouse_id : 1;

                        if ($masterItem->is_stockable) {
                            $invStock = \App\Models\InventoryStock::where('item_id', $masterItem->id)
                                ->where('warehouse_id', $warehouseId)
                                ->first();

                            $stockGudangBase = $invStock ? (float)$invStock->stock_qty : 0;

                            if (round($baseQtyReturned, 4) > round($stockGudangBase, 4)) {
                                throw new \Exception("Gagal! Stok fisik '{$masterItem->name}' di gudang saat ini hanya tersisa {$stockGudangBase} (Kemungkinan besar barang sudah dipakai via GI).");
                            }

                            // POTONG STOK FISIK DI LOKASI GUDANG TERSEBUT
                            $balanceBeforeWh = $invStock->stock_qty;
                            $invStock->decrement('stock_qty', $baseQtyReturned);
                            
                            $balanceBeforeGlobal = $masterItem->current_stock;
                            $masterItem->decrement('current_stock', $baseQtyReturned);

                            StockMutation::create([
                                'item_id' => $masterItem->id,
                                'warehouse_id' => $warehouseId,
                                'type' => 'OUT',
                                'qty' => $baseQtyReturned,
                                'balance_before' => $balanceBeforeGlobal,
                                'balance_after' => $balanceBeforeGlobal - $baseQtyReturned,
                                'reference_number' => $rtv->rtv_number,
                                'notes' => "Retur ke Vendor. Ref Penerimaan: {$gr->gr_number}",
                                'created_by' => auth()->id(),
                            ]);
                        }

                        $reasonName = 'Alasan Lainnya';
                        if (!empty($data['return_reason_id'])) {
                            $reasonModel = \App\Models\ReturnReason::find($data['return_reason_id']);
                            if ($reasonModel) $reasonName = $reasonModel->name;
                        }

                        ReturnToVendorItem::create([
                            'return_to_vendor_id' => $rtv->id,
                            'goods_receipt_item_id' => $grItem->id,
                            'purchase_order_item_id' => $poItem->id,
                            'item_id' => $masterItem->id,
                            'qty_returned' => $qtyReturnedInput,
                            'return_reason' => $reasonName,
                            'notes' => $data['notes'] ?? null
                        ]);

                        // 4. SESUAIKAN KEMBALI JATAH PO BERDASARKAN RASIO UOM PO
                        $poConvFactor = 1;
                        $poUomId = $poItem->uom_id;
                        if ($poUomId && $poUomId != $baseUomId) {
                            $altPo = \Illuminate\Support\Facades\DB::table('item_uoms')->where('id', $poUomId)->first();
                            if ($altPo) $poConvFactor = (float)$altPo->conversion_qty;
                        } elseif (preg_match('/\(Isi:?\s*([0-9.]+)/i', $poItem->uom, $m)) {
                            $poConvFactor = (float)$m[1];
                        }
                        
                        $poQtyToDeduct = $baseQtyReturned / ($poConvFactor > 0 ? $poConvFactor : 1);
                        
                        $poItem->decrement('qty_received', $poQtyToDeduct);
                        $grItem->increment('qty_returned', $qtyReturnedInput);

                        // 5. UPDATE SERIAL NUMBER MENJADI RETURNED
                        if ($masterItem->is_trackable || $masterItem->is_asset) {
                            $sns = $data['sn'] ?? [];
                            if (!empty($sns)) {
                                \DB::table('item_serials')
                                    ->whereIn('serial_number', $sns)
                                    ->update(['status' => 'RETURNED']);
                            }
                        }
                    }
                }

                if ($totalQtyReturnedInThisTransaction == 0) {
                    throw new \Exception("Anda harus mengisi minimal 1 qty barang yang akan diretur.");
                }

                $po = $gr->po;
                $po->refresh();
                $allFullyReceived = true;

                foreach ($po->items as $item) {
                    if (round((float)$item->qty_received, 4) < round((float)$item->qty_ordered, 4)) {
                        $allFullyReceived = false; break;
                    }
                }

                if (!$allFullyReceived && optional($po->status)->slug === 'fully_received') {
                    $statusPartial = Status::where('type', 'PO')->where('slug', 'partial_receipt')->first();
                    if ($statusPartial) {
                        $po->update(['status_id' => $statusPartial->id]);
                    }
                }
            });

            return redirect()->route('rtv.index')->with('success', 'Dokumen RTV diterbitkan! Stok Gudang dan Jatah PO telah dikoreksi sempurna.');

        } catch (\Exception $e) {
            Log::error('Error Simpan RTV: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Gagal memproses Retur: ' . $e->getMessage());
        }
    }
}