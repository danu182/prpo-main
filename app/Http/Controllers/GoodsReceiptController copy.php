<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\ItemCondition;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoodsReceiptController extends Controller
{
    private function generateGrNumber($companyId)
    {
        $company = \App\Models\Company::find($companyId);
        $companyCode = $company && $company->code ? strtoupper($company->code) : 'UMUM';

        $year = date('Y');
        $month = date('m');
        $prefix = "GR/{$companyCode}/{$year}/{$month}/";

        $lastGr = \App\Models\GoodsReceipt::where('gr_number', 'LIKE', "{$prefix}%")
                    ->orderBy('id', 'desc')
                    ->first();

        $nextSequence = $lastGr ? ((int) substr($lastGr->gr_number, -4)) + 1 : 1;

        return $prefix . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
    }

    public function index(Request $request)
    {
        $search = $request->input('search');

        $grs = \App\Models\GoodsReceipt::with(['po.vendor', 'receiver', 'items'])
            ->withCount('returnToVendors')
            ->when($search, function ($query) use ($search) {
                $query->where('gr_number', 'like', "%{$search}%")
                      ->orWhere('delivery_note_number', 'like', "%{$search}%")
                      ->orWhereHas('po', function ($q) use ($search) {
                          $q->where('po_number', 'like', "%{$search}%")
                            ->orWhereHas('vendor', function ($q2) use ($search) {
                                $q2->where('name', 'like', "%{$search}%");
                            });
                      });
            })
            ->latest()
            ->paginate(10);

        $statusIds = \App\Models\Status::where('type', 'PO')
                        ->whereIn('slug', ['issued', 'partial_receipt'])
                        ->pluck('id');

        $readyPOs = \App\Models\PurchaseOrder::with(['vendor', 'company'])
                        ->whereIn('status_id', $statusIds)
                        ->orderBy('updated_at', 'desc')
                        ->get();

        return view('gr.index', compact('grs', 'readyPOs'));
    }

    public function create($po_id)
    {
        $po = PurchaseOrder::with(['vendor', 'items.item'])->findOrFail($po_id);

        $pendingItems = $po->items->filter(function ($item) {
            $received = $item->qty_received ?? 0;
            return ($item->qty_ordered - $received) > 0;
        });

        if ($pendingItems->isEmpty()) {
            return redirect()->route('po.show', $po_id)->with('error', 'Semua barang pada PO ini sudah diterima penuh.');
        }

        $conditions = ItemCondition::where('is_active', true)->get();

        // 🔥 PASTIKAN WAREHOUSES DIKIRIM KE VIEW 🔥
        $warehouses = \App\Models\Warehouse::orderBy('name', 'asc')->get();

        return view('gr.create', compact('po', 'pendingItems', 'conditions', 'warehouses'));
    }

    public function store(Request $request, $po_id)
    {
        $request->validate([
            'receipt_date'         => 'required|date|before_or_equal:today',
            'delivery_note_number' => 'required|string|max:255',
            'warehouse_id'         => 'nullable|exists:warehouses,id',
            'attachments'          => 'nullable|array',
            'attachments.*'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'items'                => 'required|array',
            'items.*.qty_received' => 'required|numeric|min:0',
            'items.*.condition_id' => 'required|exists:item_conditions,id',
        ]);

        try {
            DB::transaction(function () use ($request, $po_id) {
                $po = \App\Models\PurchaseOrder::with('items')->findOrFail($po_id);

                $newGrNumber = $this->generateGrNumber($po->bill_to_company_id);

                $gr = \App\Models\GoodsReceipt::create([
                    'purchase_order_id'    => $po->id,
                    'gr_number'            => $newGrNumber,
                    'delivery_note_number' => $request->delivery_note_number,
                    'received_date'        => $request->receipt_date,
                    'received_by'          => auth()->id(),
                    'notes'                => $request->notes,
                ]);

                if ($request->hasFile('attachments')) {
                    $safeGrNumber = str_replace('/', '-', $newGrNumber);

                    foreach ($request->file('attachments') as $file) {
                        if ($file->isValid()) {
                            $originalName = $file->getClientOriginalName();
                            $filename = time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName);
                            $path = 'gr_attachments/' . $safeGrNumber . '/' . $filename;

                            Storage::disk('public')->put($path, file_get_contents($file));

                            \App\Models\GoodsReceiptAttachment::create([
                                'goods_receipt_id' => $gr->id,
                                'file_name'        => $originalName,
                                'file_path'        => $path,
                            ]);
                        }
                    }
                }

                foreach ($request->items as $itemId => $data) {
                    $qtyReceived = (float) $data['qty_received'];

                    if ($qtyReceived > 0) {
                        $poItem = \App\Models\PurchaseOrderItem::findOrFail($itemId);
                        $masterItem = \App\Models\Item::findOrFail($data['item_id']);

                        $sisaYangBolehDiterima = $poItem->qty_ordered - ($poItem->qty_received ?? 0);
                        if ($qtyReceived > $sisaYangBolehDiterima) {
                            throw new \Exception("Kuantitas terima melebihi sisa pesanan PO untuk item: " . $masterItem->name);
                        }

                        \App\Models\GoodsReceiptItem::create([
                            'goods_receipt_id'       => $gr->id,
                            'purchase_order_item_id' => $poItem->id,
                            'item_id'                => $data['item_id'],
                            'qty_received'           => $qtyReceived,
                            'condition_id'           => $data['condition_id'],
                            'notes'                  => $data['notes'] ?? null,
                        ]);

                        $poItem->qty_received = ($poItem->qty_received ?? 0) + $qtyReceived;
                        $poItem->save();

                        if ($masterItem->is_stockable) {
                            $balanceBefore = (float) $masterItem->current_stock;
                            $balanceAfter  = $balanceBefore + $qtyReceived;

                            $namaSpesifik = $poItem->description ?? $masterItem->name;
                            $catatanItem  = $data['notes'] ?? 'Tanpa catatan';

                            \App\Models\InventoryStock::create([
                                'company_id'       => $po->bill_to_company_id,
                                'warehouse_id'     => $request->warehouse_id ?? 1,
                                'item_id'          => $masterItem->id,
                                'stock_qty'        => $qtyReceived,
                                'reference_number' => $newGrNumber,
                                'notes'            => "PO: {$po->po_number} | Ket: {$catatanItem}",
                            ]);

                            \App\Models\StockMutation::create([
                                'item_id'          => $masterItem->id,
                                'warehouse_id'     => $request->warehouse_id ?? 1,
                                'type'             => 'IN',
                                'qty'              => $qtyReceived,
                                'balance_before'   => $balanceBefore,
                                'balance_after'    => $balanceAfter,
                                'reference_number' => $newGrNumber,
                                'notes'            => "Masuk: {$namaSpesifik} (Ref PO: {$po->po_number})",
                                'created_by'       => auth()->id(),
                            ]);

                            $masterItem->update(['current_stock' => $balanceAfter]);
                        }

                        if ($masterItem->is_asset) {
                            $qtyInt = (int) $qtyReceived;
                            $yearMonth = date('Y/m');

                            $lastAsset = \App\Models\FixedAsset::where('asset_number', 'like', "AST/{$yearMonth}/%")
                                            ->orderBy('id', 'desc')->lockForUpdate()->first();

                            $nextSeq = $lastAsset ? ((int) substr($lastAsset->asset_number, -4)) + 1 : 1;

                            for ($i = 0; $i < $qtyInt; $i++) {
                                $assetNumber = "AST/{$yearMonth}/" . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

                                \App\Models\FixedAsset::create([
                                    'asset_number'     => $assetNumber,
                                    'item_id'          => $masterItem->id,
                                    'company_id'       => $po->bill_to_company_id,

                                    // 🔥 SUNTIKAN BARU: SIMPAN LOKASI GUDANG SECARA FISIK 🔥
                                    'warehouse_id'     => $request->warehouse_id,

                                    'name'             => $poItem->description ?? $masterItem->name,
                                    'goods_receipt_id' => $gr->id,
                                    'acquisition_date' => $request->receipt_date,
                                    'purchase_price'   => $poItem->unit_price,
                                    'status_id'        => 1,
                                    'notes'            => 'Aset masuk dari Penerimaan Barang GR: ' . $newGrNumber
                                ]);
                                $nextSeq++;
                            }
                        }
                    }
                }

                $po->refresh();
                $allFullyReceived = true;

                foreach ($po->items as $item) {
                    if (($item->qty_received ?? 0) < $item->qty_ordered) {
                        $allFullyReceived = false;
                        break;
                    }
                }

                $newStatusSlug = $allFullyReceived ? 'fully_received' : 'partial_receipt';
                $statusTarget = \App\Models\Status::where('type', 'PO')->where('slug', $newStatusSlug)->first();

                if ($statusTarget) {
                    $po->update(['status_id' => $statusTarget->id]);
                }

                $statusText = $allFullyReceived ? 'Penerimaan Penuh (Fully Received)' : 'Penerimaan Parsial (Partial Receipt)';
                \App\Models\PurchaseOrderHistory::create([
                    'purchase_order_id' => $po->id,
                    'user_id'           => auth()->id(),
                    'action'            => 'GOODS RECEIPT',
                    'note'              => "Penerimaan / Pekerjaan telah diterima. Status: {$statusText}.\nNo Ref (SJ/BAPP): {$request->delivery_note_number}\nNo GR: {$gr->gr_number}",
                ]);

                if ($po->purchase_request_id) {
                    $pr = \App\Models\PurchaseRequest::with('items')->find($po->purchase_request_id);

                    if ($pr) {
                        $semuaDipesan = true;
                        foreach($pr->items as $prItem) {
                            if ($prItem->status === 'APPROVED') {
                                if (($prItem->ordered_qty ?? 0) < $prItem->qty) {
                                    $semuaDipesan = false;
                                    break;
                                }
                            }
                        }

                        $semuaPoSelesai = true;
                        $relatedPos = \App\Models\PurchaseOrder::with('status')->where('purchase_request_id', $pr->id)->get();

                        foreach($relatedPos as $relatedPo) {
                            if (!in_array(optional($relatedPo->status)->slug, ['fully_received', 'canceled'])) {
                                $semuaPoSelesai = false;
                                break;
                            }
                        }

                        if ($semuaDipesan && $semuaPoSelesai) {
                            $statusSelesaiPr = \App\Models\Status::where('type', 'PR')->where('slug', 'completed')->first();

                            if ($statusSelesaiPr && optional($pr->status)->slug !== 'completed') {
                                $pr->update(['status_id' => $statusSelesaiPr->id]);

                                \App\Models\PurchaseRequestHistory::create([
                                    'purchase_request_id' => $pr->id,
                                    'user_id'             => auth()->id(),
                                    'action'              => 'COMPLETED',
                                    'note'                => "Siklus selesai. Semua barang/jasa telah diterima penuh (Ref PO: {$po->po_number})."
                                ]);
                            }
                        }
                    }
                }
            });

            return redirect()->route('gr.index')->with('success', 'Penerimaan Barang/Jasa berhasil disimpan dan dokumen telah diterbitkan!');

        } catch (\Exception $e) {
            Log::error('Error Simpan GR: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $gr = \App\Models\GoodsReceipt::with([
            'po.vendor',
            'po.company',
            'items.item',
            'items.condition',
            'receiver'
        ])->findOrFail($id);

        return view('gr.show', compact('gr'));
    }

    public function print($id)
    {
        $gr = \App\Models\GoodsReceipt::with([
            'po.vendor',
            'po.company',
            'items.item',
            'items.condition',
            'items.purchaseOrderItem',
            'receiver'
        ])->findOrFail($id);

        return view('gr.print', compact('gr'));
    }

    public function printLabels($id)
    {
        $gr = \App\Models\GoodsReceipt::with([
            'po.company',
            'items.item',
            'items.purchaseOrderItem'
        ])->findOrFail($id);

        $labelItems = $gr->items->filter(function ($grItem) {
            $masterItem = $grItem->item;
            return $masterItem && ($masterItem->is_asset || $masterItem->is_trackable);
        });

        if ($labelItems->isEmpty()) {
            return back()->with('error', 'Tidak ada Aset Tetap atau Inventaris Minor yang perlu dicetak labelnya pada dokumen GR ini.');
        }

        return view('gr.print_labels', compact('gr', 'labelItems'));
    }
}
