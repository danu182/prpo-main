<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\ChargeType;
use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\PaymentTerm;
use Illuminate\Support\Facades\Auth;
use App\Models\Item;
use App\Models\Tax;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Vendor;
use App\Models\Status;
use App\Models\DiscountType;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Notifications\DocumentApprovalNotification;

use Webklex\PDFMerger\Facades\PDFMergerFacade as PDFMerger; // Pastikan panggil facade merger

class PurchaseOrderController extends Controller
{
    // =========================================================================
    // 1. TAMPILKAN ITEM SIAP PO
    // =========================================================================
    public function getItemsForPo()
    {
        $statusApproved = \App\Models\Status::where('type', 'PR')->where('slug', 'approved')->first();
        $statusPartial  = \App\Models\Status::where('type', 'PR')->where('slug', 'partial_po')->first();
        $validStatusIds = array_filter([$statusApproved->id ?? null, $statusPartial->id ?? null]);

        $pendingItems = PurchaseRequestItem::where('status', 'APPROVED')
                        ->whereHas('purchaseRequest', function($q) use ($validStatusIds) {
                            $q->whereIn('status_id', $validStatusIds);
                        })
                        ->whereRaw('qty > IFNULL(ordered_qty, 0)')
                        ->with(['item', 'purchaseRequest'])
                        ->get();

        $vendors = \App\Models\Vendor::all();
        $companies = \App\Models\Company::all();

        return view('po.create', compact('pendingItems', 'vendors', 'companies'));
    }

    public function create()
    {
        return $this->getItemsForPo();
    }

    // =========================================================================
    // 2. STORE (SIMPAN DRAFT PO MANUAL)
    // =========================================================================
    public function store(Request $request)
    {
        $request->validate([
            'billing_company_id'   => 'required|exists:companies,id',
            'payment_term_id'      => 'required|exists:payment_terms,id',
            'po_items'             => 'required|array',
            'po_items.*.vendor_id' => 'required|integer',
        ], [
            'po_items.*.vendor_id.required' => 'Gagal: Anda harus memilih Vendor Aktual untuk semua barang!',
        ]);

        DB::beginTransaction();
        try {
            $poNumber = $this->generatePoNumber($request->billing_company_id);
            $paymentTermName = \App\Models\PaymentTerm::find($request->payment_term_id)->name ?? null;
            $statusDraft = \App\Models\Status::where('type', 'PO')->whereIn('slug', ['draft', 'pending_approval'])->first();
            $shippingAddress = $request->shipping_address ?: (\App\Models\Company::find($request->bill_to_company_id)->address ?? '-');

            $po = PurchaseOrder::create([
                'po_number'          => $poNumber,
                'purchase_request_id'=> $request->pr_id,
                'vendor_id'          => $request->vendor_id,
                'bill_to_company_id' => $request->bill_to_company_id,
                'status_id'          => $statusDraft->id ?? null,
                'po_date'            => $request->po_date,
                'delivery_date'      => $request->delivery_date,
                'due_date'           => $request->due_date,
                'payment_terms'      => $paymentTermName,
                'notes'              => $request->notes,
                'currency'           => $request->currency,
                'created_by'         => auth()->id(),
                'shipping_address'   => $shippingAddress,
            ]);

            $vendorName = \App\Models\Vendor::find($request->vendor_id)->name ?? 'Vendor';
            $this->logHistory($po->id, 'PO Diterbitkan', "Draft PO Nomor: **{$po->po_number}**\nVendor: **{$vendorName}**");

            $grandSubtotal = 0;
            $grandTax = 0;
            $prItemIdsToHeal = [];

            foreach ($request->input('items', []) as $itemData) {
                $qty = (float) ($itemData['qty'] ?? 0);
                if ($qty <= 0) continue;

                if (!empty($itemData['pr_item_id'])) {
                    $prItemIdsToHeal[] = $itemData['pr_item_id'];
                }

                $price = (float) $itemData['price'];
                $gross = $qty * $price;
                $discVal = (float) ($itemData['discount_value'] ?? 0);
                $discType = $itemData['discount_type'] ?? 'FIXED';
                $discAmt = ($discType == 'PERCENT') ? ($gross * $discVal / 100) : $discVal;
                $dpp = $gross - $discAmt;

                $taxAmt = 0;
                $taxId = $itemData['tax_id'] ?? null;
                if ($taxId && $tax = \App\Models\Tax::find($taxId)) {
                    $taxAmt = ($dpp * $tax->percent) / 100;
                }

                $masterItem = \App\Models\Item::find($itemData['item_id']);

                PurchaseOrderItem::create([
                    'purchase_order_id'        => $po->id,
                    'item_id'                  => $itemData['item_id'],
                    'purchase_request_item_id' => $itemData['pr_item_id'] ?? null,
                    'qty_ordered'              => $qty,
                    'unit_price'               => $price,
                    'uom_id'                   => $itemData['uom_id'] ?? null,
                    'uom'                      => $itemData['uom'] ?? ($masterItem->unit ?? 'Unit'),
                    'description'              => $itemData['notes'] ?? ($masterItem->name ?? '-'),
                    'discount_amount'          => $discAmt,
                    'tax_amount'               => $taxAmt,
                    'tax_id'                   => $taxId,
                    'subtotal'                 => $dpp,
                    'discount_type'            => $discType,
                    'discount_value'           => $discVal,
                ]);

                $grandSubtotal += $dpp;
                $grandTax += $taxAmt;
            }

            $totalCharges = 0;
            if ($request->has('charges_name')) {
                foreach ($request->charges_name as $idx => $name) {
                    $amount = (float) ($request->charges_amount[$idx] ?? 0);
                    if ($name && $amount > 0) {
                        DB::table('purchase_order_charges')->insert([
                            'purchase_order_id' => $po->id,
                            'name' => $name,
                            'amount' => $amount,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        $totalCharges += $amount;
                    }
                }
            }

            $globalDiscType = $request->global_discount_type ?? 'FIXED';
            $globalDiscVal  = (float) ($request->global_discount_value ?? 0);
            $globalDiscAmount = ($globalDiscType == 'PERCENT') ? ($grandSubtotal * $globalDiscVal / 100) : $globalDiscVal;

            $po->update([
                'subtotal'       => $grandSubtotal,
                'tax_total'      => $grandTax,
                'discount_total' => $globalDiscAmount,
                'grand_total'    => ($grandSubtotal - $globalDiscAmount) + $grandTax + $totalCharges
            ]);

            if (!empty($prItemIdsToHeal)) {
                foreach(array_unique($prItemIdsToHeal) as $pid) {
                    $this->recalculatePrItemFulfillment($pid);
                }
                $this->checkAndUpdatePrStatus($request->pr_id);
            }

            DB::commit();
            return redirect()->route('po.show', $po->id)->with('success', 'Draft Purchase Order berhasil dibuat!');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal membuat PO: ' . $e->getMessage())->withInput();
        }
    }

    // =========================================================================
    // 🔥 PROSES TERBITKAN PO DARI PR (STORE) 🔥
    // =========================================================================
    public function storeFromPr(\Illuminate\Http\Request $request, $slug, \App\Services\SystemSettingService $settingService)
    {
        $request->validate([
            'billing_company_id' => 'required|exists:companies,id',
            'payment_term_id'    => 'required|exists:payment_terms,id',
            'po_items'           => 'required|array',
            'delivery_date'      => 'required|date',
        ]);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $slug, $settingService) {
                $prRecord = \App\Models\PurchaseRequest::with('items')->where('pr_number', $slug)->firstOrFail();

                // 1. Filter item yang dipilih (ada vendor dan Qty > 0)
                $itemsToProcess = collect($request->po_items)->filter(function ($item) {
                    return trim($item['vendor_id'] ?? '') !== '' && (float) ($item['qty'] ?? 0) > 0;
                });

                if ($itemsToProcess->isEmpty()) {
                    throw new \Exception('Anda harus memilih Vendor Aktual minimal untuk 1 barang!');
                }

                // 2. Kelompokkan berdasarkan Vendor (Bisa pecah PO jika beda vendor)
                $itemsByVendor = $itemsToProcess->groupBy('vendor_id');
                $paymentTermName = \App\Models\PaymentTerm::find($request->payment_term_id)->name ?? null;

                $prItemIdsToHeal = [];

                // 3. Looping Pembuatan PO per Vendor
                foreach ($itemsByVendor as $vendorId => $items) {
                    $newPoNumber = $this->generatePoNumber($request->billing_company_id);
                    $poSubtotalGross = 0;
                    $poTotalItemDiscount = 0;
                    $poTotalTax = 0;
                    $processedLineItems = [];

                    $storagePath = (\Illuminate\Support\Facades\DB::table('system_settings')->where('setting_key', 'path_po_attachment')->value('setting_value') ?: 'attachments/purchase_orders') . '/' . str_replace(['/', '\\'], '-', $newPoNumber);

                    // A. Hitung Rincian per Item
                    foreach ($items as $itemIndex => $itemData) {
                        $qty = (float) ($itemData['qty'] ?? 0);
                        $price = (float) ($itemData['unit_price'] ?? 0);
                        $gross = $qty * $price;

                        if (!empty($itemData['pr_item_id'])) {
                            $prItemIdsToHeal[] = $itemData['pr_item_id'];
                        }

                        $discVal = (float) ($itemData['discount_value'] ?? 0);
                        $discType = strtoupper($itemData['discount_type'] ?? 'FIXED');
                        $discAmt = ($discType === 'PERCENT') ? ($gross * $discVal / 100) : $discVal;
                        $dpp = $gross - $discAmt;

                        $taxAmt = 0;
                        $taxId = $itemData['tax_id'] ?? null;
                        if ($taxId && $tax = \App\Models\Tax::find($taxId)) {
                            $taxAmt = $dpp * ($tax->percent / 100);
                        }

                        $poSubtotalGross += $gross;
                        $poTotalItemDiscount += $discAmt;
                        $poTotalTax += $taxAmt;
                        $processedLineItems[] = [
                            'itemIndex' => $itemIndex, 'itemData' => $itemData, 'discAmt' => $discAmt, 'dpp' => $dpp,
                            'taxId' => $taxId, 'taxAmt' => $taxAmt, 'qty' => $qty, 'price' => $price, 'discType' => $discType, 'discVal' => $discVal
                        ];
                    }

                    // B. Hitung Diskon Global (Header PO) dari Array HTML
                    $globalDiscArray = $request->input('global_discounts.0', []); // Ambil index ke-0
                    $globalDiscType = strtoupper($globalDiscArray['type'] ?? 'FIXED');
                    $globalDiscVal = (float) ($globalDiscArray['value'] ?? 0);
                    $poGlobalDiscount = ($globalDiscType === 'PERCENT') ? (($poSubtotalGross - $poTotalItemDiscount) * ($globalDiscVal / 100)) : $globalDiscVal;

                    // C. Hitung Diskon Ekstra Terpisah
                    $poExtraDiscountTotal = 0;
                    $extraDiscountData = [];
                    if ($request->has('extra_discounts')) {
                        foreach ($request->extra_discounts as $disc) {
                            if (!empty($disc['amount']) && $disc['amount'] > 0) {
                                $discountType = \App\Models\DiscountType::find($disc['discount_type_id']);
                                $discValueInput = (float) $disc['amount'];
                                $finalDiscountAmount = 0;

                                if ($discountType && strtoupper($discountType->type) === 'PERCENT') {
                                    $finalDiscountAmount = ($poSubtotalGross * $discValueInput) / 100;
                                } else {
                                    $finalDiscountAmount = $discValueInput;
                                }

                                $poExtraDiscountTotal += $finalDiscountAmount;
                                $extraDiscountData[] = [
                                    'name' => $disc['discount_type_id'], 'amount' => $finalDiscountAmount,
                                    'created_at' => now(), 'updated_at' => now()
                                ];
                            }
                        }
                    }

                    // D. Rekap Keseluruhan
                    $totalAllDiscounts = $poTotalItemDiscount + $poGlobalDiscount + $poExtraDiscountTotal;
                    $poGrandTotal = ($poSubtotalGross - $totalAllDiscounts) + $poTotalTax;

                    // E. Simpan Header PO
                    $po = \App\Models\PurchaseOrder::create([
                        'po_number'             => $newPoNumber,
                        'purchase_request_id'   => $prRecord->id,
                        'vendor_id'             => $vendorId,
                        'bill_to_company_id'    => $request->billing_company_id,
                        'status_id'             => null, // Akan diisi oleh Workflow Service nanti
                        'po_date'               => $request->po_date ?? now(),
                        'created_by'            => auth()->id(),
                        'currency'              => $request->currency ?? 'IDR',
                        'shipping_address'      => $request->shipping_address,
                        'payment_terms'         => $paymentTermName,
                        'notes'                 => $request->notes,
                        'delivery_date'         => $request->delivery_date,
                        'global_discount_type'  => $globalDiscType,
                        'global_discount_value' => $globalDiscVal,
                        'subtotal'              => $poSubtotalGross,
                        'discount_total'        => $totalAllDiscounts,
                        'tax_total'             => $poTotalTax,
                        'charge_total'          => 0,
                        'grand_total'           => $poGrandTotal,
                    ]);

                    // F. Simpan Diskon Ekstra ke Database
                    if (!empty($extraDiscountData)) {
                        foreach ($extraDiscountData as &$data) {
                            $data['purchase_order_id'] = $po->id;
                        }
                        \Illuminate\Support\Facades\DB::table('purchase_order_discounts')->insert($extraDiscountData);
                    }

                    // G. Simpan Line Items & Lampiran
                    foreach ($processedLineItems as $line) {
                        $itemData = $line['itemData'];
                        $newPoItem = \App\Models\PurchaseOrderItem::create([
                            'purchase_order_id'        => $po->id,
                            'item_id'                  => $itemData['item_id'],
                            'purchase_request_item_id' => $itemData['pr_item_id'],
                            'uom_id'                   => $itemData['uom_id'] ?? null,
                            'uom'                      => $itemData['uom'] ?? (\App\Models\PurchaseRequestItem::find($itemData['pr_item_id'])->uom_short ?? 'PCS'),
                            'description'              => $itemData['notes'] ?? (\App\Models\Item::find($itemData['item_id'])->name ?? '-'),
                            'tax_id'                   => $line['taxId'],
                            'qty_ordered'              => $line['qty'],
                            'unit_price'               => $line['price'],
                            'discount_type'            => $line['discType'],
                            'discount_value'           => $line['discVal'],
                            'discount_amount'          => $line['discAmt'],
                            'subtotal'                 => $line['dpp'],
                            'tax_amount'               => $line['taxAmt'],
                        ]);

                        if ($request->hasFile("po_items.{$line['itemIndex']}.attachments")) {
                            $files = $request->file("po_items.{$line['itemIndex']}.attachments");
                            foreach (is_array($files) ? $files : [$files] as $file) {
                                $path = $file->storeAs($storagePath, "po_item_{$itemData['item_id']}_" . uniqid() . time() . "." . $file->extension(), 'public');
                                \App\Models\PurchaseOrderItemAttachment::create([
                                    'purchase_order_item_id' => $newPoItem->id, 'file_name' => $file->getClientOriginalName(), 'file_path' => $path
                                ]);
                            }
                        }
                    }

                    // H. Simpan Biaya Tambahan (Charges) dan Update Grand Total Akhir
                    $poChargeTotal = 0;
                    if ($request->has('charges')) {
                        foreach ($request->charges as $charge) {
                            if (!empty($charge['amount'])) {
                                \Illuminate\Support\Facades\DB::table('purchase_order_charges')->insert([
                                    'purchase_order_id' => $po->id, 'name' => $charge['charge_type_id'], 'amount' => $charge['amount'],
                                    'created_at' => now(), 'updated_at' => now()
                                ]);
                                $poChargeTotal += $charge['amount'];
                            }
                        }
                    }

                    if ($poChargeTotal > 0) {
                        $po->update([
                            'charge_total' => $poChargeTotal, 'grand_total'  => $po->grand_total + $poChargeTotal
                        ]);
                    }

                    // =====================================================================
                    // 🔥 I. INISIASI WORKFLOW APPROVAL (VIA SERVICE) 🔥
                    // =====================================================================
                    $needsApproval = \App\Services\ApprovalService::generateWorkflow($po);

                    if ($needsApproval) {
                        $pendingStatusId = \App\Models\Status::where('type', 'PO')->where('slug', 'pending_approval')->first()->id ?? 1;
                        $po->update(['status_id' => $pendingStatusId]);
                        $this->logHistory($po->id, 'SYSTEM', 'Rute persetujuan (Workflow) PO berhasil di-generate.');
                    } else {
                        $approvedStatusId = \App\Models\Status::where('type', 'PO')->where('slug', 'approved')->first()->id ?? 3;
                        $po->update(['status_id' => $approvedStatusId]);
                        $this->logHistory($po->id, 'APPROVED', 'PO Auto-Approved karena tidak ada aturan aktif atau nominal di bawah batas.');
                    }
                    // =====================================================================

                    $this->logHistory($po->id, 'PO Diterbitkan', 'Dokumen PO diterbitkan dari PR #' . $prRecord->pr_number);
                }

                // J. EKSEKUSI SELF-HEALING PR (Update status PR jika Full/Partial)
                if (!empty($prItemIdsToHeal)) {
                    foreach(array_unique($prItemIdsToHeal) as $pid) {
                        $this->recalculatePrItemFulfillment($pid);
                    }
                    $this->checkAndUpdatePrStatus($prRecord->id);
                }
            });

            return redirect()->route('po.index')->with('success', 'Purchase Order berhasil diterbitkan!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal memproses PO: ' . $e->getMessage());
        }
    }


    // =========================================================================
    // 4. UPDATE PO (EDIT DATA PO)
    // =========================================================================
    public function update(Request $request, $slug, \App\Services\SystemSettingService $settingService)
    {
        $request->validate([
            'billing_company_id' => 'required|exists:companies,id',
            'payment_term_id'    => 'required|exists:payment_terms,id',
            'po_items'           => 'required|array',
            'delivery_date'      => 'required|date',
        ]);

        try {
            DB::transaction(function () use ($request, $slug, $settingService) {
                $po = \App\Models\PurchaseOrder::with('items')->where('po_number', $slug)->firstOrFail();

                if (!in_array(strtolower(optional($po->status)->slug ?? ''), ['draft', 'pending_approval', 'rejected', ''])) {
                    throw new \Exception('Gagal: PO ini sudah tidak dapat diedit karena statusnya ' . optional($po->status)->name);
                }

                $poSubtotalGross = 0;
                $poTotalItemDiscount = 0;
                $poTotalTax = 0;
                $safePoNumber = str_replace('/', '-', $po->po_number);
                $storagePath = (\DB::table('system_settings')->where('setting_key', 'path_po_attachment')->value('setting_value') ?: 'attachments/purchase_orders') . '/' . $safePoNumber;

                foreach ($request->po_items as $itemId => $itemData) {
                    if (!$poItem = \App\Models\PurchaseOrderItem::find($itemId)) continue;

                    $newQty = (float) ($itemData['qty'] ?? 0);
                    $price = (float) ($itemData['unit_price'] ?? 0);
                    $gross = $newQty * $price;
                    $discVal = (float) ($itemData['discount_value'] ?? 0);
                    $discType = strtoupper($itemData['discount_type'] ?? 'FIXED');
                    $discAmt = ($discType === 'PERCENT') ? ($gross * ($discVal / 100)) : $discVal;
                    $dpp = $gross - $discAmt;

                    $taxAmt = 0;
                    $taxId = $itemData['tax_id'] ?? null;
                    if ($taxId && $tax = \App\Models\Tax::find($taxId)) {
                        $taxAmt = $dpp * ($tax->percent / 100);
                    }

                    $fileInputName = "item_attachments_{$itemId}";
                    if ($request->hasFile($fileInputName)) {
                        foreach ($request->file($fileInputName) as $file) {
                            $path = $file->storeAs($storagePath, time() . '_' . uniqid() . '_' . str_replace(' ', '_', $file->getClientOriginalName()), 'public');
                            \DB::table('purchase_order_item_attachments')->insert([
                                'purchase_order_item_id' => $poItem->id,
                                'file_name' => $file->getClientOriginalName(),
                                'file_path' => str_replace('\\', '/', $path),
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                        }
                    }

                    $poSubtotalGross += $gross;
                    $poTotalItemDiscount += $discAmt;
                    $poTotalTax += $taxAmt;

                    $poItem->update([
                        'uom_id'          => $itemData['uom_id'] ?? $poItem->uom_id,
                        'uom'             => $itemData['uom'] ?? $poItem->uom,
                        'description'     => $itemData['notes'] ?? $poItem->description,
                        'vendor_id'       => $itemData['vendor_id'] ?? $poItem->vendor_id,
                        'tax_id'          => $taxId ?: null,
                        'qty_ordered'     => $newQty,
                        'unit_price'      => $price,
                        'discount_type'   => $discType,
                        'discount_value'  => $discVal,
                        'discount_amount' => $discAmt,
                        'subtotal'        => $dpp,
                        'tax_amount'      => $taxAmt,
                    ]);
                }

                if ($request->hasFile('header_attachments')) {
                    foreach ($request->file('header_attachments') as $file) {
                        $path = $file->storeAs($storagePath, time() . '_' . uniqid() . '_' . str_replace(' ', '_', $file->getClientOriginalName()), 'public');
                        \DB::table('purchase_order_attachments')->insert([
                            'purchase_order_id' => $po->id,
                            'file_name' => $file->getClientOriginalName(),
                            'file_path' => str_replace('\\', '/', $path),
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }

                $globalDiscType = strtoupper($request->global_discount_type ?? 'FIXED');
                $globalDiscVal = (float) ($request->global_discount_value ?? 0);
                $poGlobalDiscount = ($globalDiscType === 'PERCENT') ? (($poSubtotalGross - $poTotalItemDiscount) * ($globalDiscVal / 100)) : $globalDiscVal;

                \DB::table('purchase_order_charges')->where('purchase_order_id', $po->id)->delete();
                $poChargeTotal = 0;
                if ($request->has('charges')) {
                    foreach ($request->charges as $charge) {
                        if (!empty($charge['amount'])) {
                            \DB::table('purchase_order_charges')->insert([
                                'purchase_order_id' => $po->id,
                                'name' => $charge['charge_type_id'],
                                'amount' => $charge['amount'],
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                            $poChargeTotal += $charge['amount'];
                        }
                    }
                }

                \DB::table('purchase_order_discounts')->where('purchase_order_id', $po->id)->delete();
                $poExtraDiscountTotal = 0;
                if ($request->has('extra_discounts')) {
                    foreach ($request->extra_discounts as $disc) {
                        if (!empty($disc['amount'])) {
                            \DB::table('purchase_order_discounts')->insert([
                                'purchase_order_id' => $po->id,
                                'name' => $disc['discount_type_id'],
                                'amount' => $disc['amount'],
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                            $poExtraDiscountTotal += $disc['amount'];
                        }
                    }
                }

                $po->update([
                    'bill_to_company_id'    => $request->billing_company_id,
                    'shipping_address'      => $request->shipping_address,
                    'payment_terms'         => \App\Models\PaymentTerm::find($request->payment_term_id)->name ?? null,
                    'notes'                 => $request->notes,
                    'delivery_date'         => $request->delivery_date,
                    'po_date'               => $request->po_date,
                    'currency'              => $request->currency,
                    'global_discount_type'  => $globalDiscType,
                    'global_discount_value' => $globalDiscVal,
                    'subtotal'              => $poSubtotalGross,
                    'discount_total'        => $poTotalItemDiscount + $poGlobalDiscount,
                    'tax_total'             => $poTotalTax,
                    'charge_total'          => $poChargeTotal,
                    'grand_total'           => ($poSubtotalGross - ($poTotalItemDiscount + $poGlobalDiscount)) + $poTotalTax + $poChargeTotal - $poExtraDiscountTotal,
                ]);

                // 🔥 EKSEKUSI SELF-HEALING PR 🔥
                $prItemIds = $po->items->pluck('purchase_request_item_id')->filter()->unique()->toArray();
                if (!empty($prItemIds)) {
                    foreach($prItemIds as $pid) {
                        $this->recalculatePrItemFulfillment($pid);
                    }
                    $this->checkAndUpdatePrStatus($po->purchase_request_id);
                }

                // =====================================================================
                // 🔥 PEMANGGILAN SERVICE WORKFLOW (RESET ANTREAN SAAT PO DI-EDIT) 🔥
                // =====================================================================
                // Pastikan menggunakan variabel $po, bukan $bill
                $needsApproval = \App\Services\ApprovalService::generateWorkflow($po);

                if ($needsApproval) {
                    // Jika butuh persetujuan, ubah status PO menjadi PENDING
                    $pendingStatus = \App\Models\Status::where('slug', 'pending')->first()->id ?? 1;
                    $po->update(['status_id' => $pendingStatus]);

                    $this->logHistory($po->id, 'SYSTEM', 'Rute persetujuan (Workflow) PO telah di-reset menyesuaikan data revisi.');
                } else {
                    // Jika tidak butuh persetujuan, otomatis APPROVED
                    $approvedStatus = \App\Models\Status::where('slug', 'approved')->first()->id ?? 3;
                    $po->update(['status_id' => $approvedStatus]);

                    $this->logHistory($po->id, 'APPROVED', 'PO Auto-Approved karena tidak ada aturan aktif atau nominal di bawah batas.');
                }
                // =====================================================================

                $this->logHistory($po->id, 'PO Direvisi', 'Perubahan telah disimpan.');

            });

            return redirect()->route('po.show', $slug)->with('success', 'Perubahan Purchase Order berhasil disimpan!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal menyimpan perubahan: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // 5. CANCEL (BATALKAN PO)
    // =========================================================================
    public function cancel(Request $request, $slug)
    {
        DB::beginTransaction();
        try {
            $cancelReason = $request->input('cancel_reason', 'Dibatalkan tanpa alasan spesifik');
            $po = \App\Models\PurchaseOrder::with('items')->where('po_number', $slug)->firstOrFail();

            if(in_array(strtolower(optional($po->status)->slug), ['completed', 'fully_received', 'closed'])) {
                throw new \Exception('Gagal: PO yang sudah selesai/diterima gudang tidak bisa dibatalkan.');
            }

            $statusPoCanceled = \App\Models\Status::where('type', 'PO')->whereIn('slug', ['canceled', 'cancelled'])->first();
            $po->update(['status_id' => $statusPoCanceled->id]);

            // 🔥 JALANKAN SELF-HEALING PR OTOMATIS 🔥
            $prItemIds = $po->items->pluck('purchase_request_item_id')->filter()->unique()->toArray();
            if (!empty($prItemIds)) {
                foreach($prItemIds as $pid) {
                    $this->recalculatePrItemFulfillment($pid);
                }
                $this->checkAndUpdatePrStatus($po->purchase_request_id);
            }

            \App\Models\DocumentApproval::where('document_id', $po->id)
                ->where('document_type', get_class($po))
                ->update(['status' => 'REJECTED', 'note' => 'Batal Otomatis (PO di-Cancel). Alasan: ' . $cancelReason]);

            $this->logHistory($po->id, 'PO Dibatalkan', 'Dokumen PO telah dibatalkan. Alasan: **' . $cancelReason . '**');

            DB::commit();
            return redirect()->route('po.index')->with('success', 'PO Berhasil dibatalkan! Kuantitas PR telah kembali normal.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal membatalkan PO: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // HELPER 1: HITUNG ULANG QTY PR SECARA ABSOLUT MENGGUNAKAN UOM_ID
    // =========================================================================
    private function recalculatePrItemFulfillment($prItemId)
    {
        $prItem = \App\Models\PurchaseRequestItem::find($prItemId);
        if (!$prItem) return;

        $activePoIds = \App\Models\PurchaseOrder::whereHas('status', function($q) {
            $q->whereNotIn('slug', ['canceled', 'cancelled', 'rejected']);
        })->pluck('id');

        $poItems = \App\Models\PurchaseOrderItem::where('purchase_request_item_id', $prItemId)
            ->whereIn('purchase_order_id', $activePoIds)
            ->get();

        $totalOrderedInPrUom = 0;

        $prUomFactor = 1;
        if (!empty($prItem->uom_id)) {
            $uomDb = \App\Models\ItemUom::find($prItem->uom_id);
            if ($uomDb) $prUomFactor = (float) $uomDb->conversion_qty;
        } else {
            $prUomFactor = $this->getUomFactor($prItem->item_id, $prItem->uom);
        }
        if ($prUomFactor <= 0) $prUomFactor = 1;

        foreach ($poItems as $poItem) {
            $poUomFactor = 1;
            if (!empty($poItem->uom_id)) {
                $uomDb = \App\Models\ItemUom::find($poItem->uom_id);
                if ($uomDb) $poUomFactor = (float) $uomDb->conversion_qty;
            } else {
                $poUomFactor = $this->getUomFactor($poItem->item_id, $poItem->uom);
            }
            if ($poUomFactor <= 0) $poUomFactor = 1;

            $qtyInBase = $poItem->qty_ordered * $poUomFactor;
            $qtyInPrUnit = $qtyInBase / $prUomFactor;

            $totalOrderedInPrUom += $qtyInPrUnit;
        }

        $prItem->ordered_qty = $totalOrderedInPrUom;
        $prItem->save();
    }

    // =========================================================================
    // HELPER 2: PERBAIKI STATUS PR OTOMATIS
    // =========================================================================
    private function checkAndUpdatePrStatus($prId)
    {
        $prRecord = \App\Models\PurchaseRequest::with('items')->find($prId);
        if (!$prRecord) return;

        if (in_array(strtolower(optional($prRecord->status)->slug), ['canceled', 'cancelled', 'rejected'])) return;

        $anyItemProcessed = false;
        $allItemFulfilled = true;

        foreach ($prRecord->items as $item) {
            if (strtolower($item->status) !== 'approved') continue;

            $targetQty = (float) $item->qty;
            $currentOrdered = (float) ($item->ordered_qty ?? 0);

            if (round($currentOrdered, 2) > 0) { $anyItemProcessed = true; }
            if (round($currentOrdered, 2) < round($targetQty, 2)) { $allItemFulfilled = false; }
        }

        if (!$anyItemProcessed) {
            $st = \App\Models\Status::where('type', 'PR')->where('slug', 'approved')->first();
            if ($st) $prRecord->update(['status_id' => $st->id]);
        } elseif (!$allItemFulfilled) {
            $st = \App\Models\Status::where('type', 'PR')->where('slug', 'partial_po')->first();
            if ($st) $prRecord->update(['status_id' => $st->id]);
        } else {
            $st = \App\Models\Status::where('type', 'PR')->where('slug', 'po_issued')->first();
            if ($st) $prRecord->update(['status_id' => $st->id]);
        }
    }

    // =========================================================================
    // HELPER 3: BACA FAKTOR KONVERSI UOM
    // =========================================================================
    private function getUomFactor($itemId, $uomString)
    {
        if (empty($uomString)) return 1;

        if (is_numeric($uomString)) {
            $altUom = \App\Models\ItemUom::find($uomString);
            if ($altUom) return (float) $altUom->conversion_qty;
        }

        if (preg_match('/(?:Isi|Qty|Konversi)\s*[:=]?\s*([0-9.]+)/i', $uomString, $matches)) {
            return (float) $matches[1];
        }

        $cleanUom = trim(preg_replace('/[\[\(\{].*?[\]\)\}]/', '', $uomString));
        if (!empty($cleanUom)) {
            $altUom = \App\Models\ItemUom::where('item_id', $itemId)
                        ->whereRaw('LOWER(uom_name) = ?', [strtolower($cleanUom)])
                        ->first();
            if ($altUom) return (float) $altUom->conversion_qty;
        }

        return 1;
    }

    // =========================================================================
    // HELPER 4: GENERATOR NOMOR PO OTOMATIS
    // =========================================================================
    private function generatePoNumber($companyId)
    {
        $company = \App\Models\Company::find($companyId);
        $companyCode = $company ? strtoupper($company->code ?? 'PT') : 'PT';

        $year = date('Y');
        $month = date('m');
        $dateStr = date('Ymd');

        $prefix = "PO-{$companyCode}-{$dateStr}-";

        $latestPo = \App\Models\PurchaseOrder::where('po_number', 'like', $prefix . '%')->orderBy('id', 'desc')->lockForUpdate()->first();
        $newSequence = 1;
        if ($latestPo && $latestPo->po_number) {
            $parts = explode('-', $latestPo->po_number);
            $newSequence = ((int) end($parts)) + 1;
        }

        return $prefix . str_pad($newSequence, 4, '0', STR_PAD_LEFT);
    }

    // =========================================================================
    // 6. EDIT PO PAGE
    // =========================================================================
    public function edit($slug)
    {
        $po = \App\Models\PurchaseOrder::with(['items.item.itemUoms', 'vendor', 'status', 'attachments'])->where('po_number', $slug)->firstOrFail();
        if (!in_array(strtolower(optional($po->status)->slug ?? ''), ['draft', 'pending_approval', 'rejected', ''])) {
            return redirect()->route('po.index')->with('error', 'Gagal: PO ini sudah tidak dapat diedit.');
        }

        $vendors = \App\Models\Vendor::all();
        $companies = \App\Models\Company::all();
        $paymentTerms = \App\Models\PaymentTerm::all();
        $taxes = \App\Models\Tax::all();
        $chargeTypes = \App\Models\ChargeType::where('is_active', 1)->get();
        $currencies = \App\Models\Currency::all();
        $discountTypes = \App\Models\DiscountType::where('is_active', 1)->get();
        $charges = DB::table('purchase_order_charges')->where('purchase_order_id', $po->id)->get();
        $extraDiscounts = DB::table('purchase_order_discounts')->where('purchase_order_id', $po->id)->get();

        $poItemIds = $po->items->pluck('id')->toArray();
        $itemAttachments = \DB::table('purchase_order_item_attachments')->whereIn('purchase_order_item_id', $poItemIds)->get();
        foreach ($po->items as $item) {
            $item->raw_attachments = $itemAttachments->where('purchase_order_item_id', $item->id)->values();
        }

        return view('po.edit', compact('po', 'vendors', 'companies', 'paymentTerms', 'taxes', 'chargeTypes', 'currencies', 'charges', 'discountTypes', 'extraDiscounts'));
    }

    // =========================================================================
    // 7. SHOW PO DETAIL
    // =========================================================================
    public function show($slug)
    {
        $po = \App\Models\PurchaseOrder::with(['items.item.itemUoms', 'vendor', 'company', 'billToCompany', 'status', 'user', 'purchaseRequest', 'attachments', 'approvals.role', 'histories.user'])->where('po_number', $slug)->firstOrFail();

        $charges = \DB::table('purchase_order_charges')->where('purchase_order_id', $po->id)->get();
        $extraDiscounts = \DB::table('purchase_order_discounts')->where('purchase_order_id', $po->id)->get();

        $poItemIds = $po->items->pluck('id')->toArray();
        $itemAttachments = \DB::table('purchase_order_item_attachments')->whereIn('purchase_order_item_id', $poItemIds)->get();

        foreach ($po->items as $item) {
            $item->raw_attachments = $itemAttachments->where('purchase_order_item_id', $item->id)->values();
        }

        $hasBeenPartiallyApproved = $po->approvals->where('status', 'APPROVED')->isNotEmpty();

        return view('po.show', compact('po', 'charges', 'extraDiscounts', 'hasBeenPartiallyApproved'));
    }


    // =========================================================================
    // CETAK PURCHASE ORDER (PDF)
    // =========================================================================
    public function printPdf($slug)
    {
        $po = \App\Models\PurchaseOrder::with([
            'items.item.itemUoms',
            'vendor',
            'company',
            'billToCompany',
            'status',
            'user',
            'purchaseRequest.department',
            'approvals.approver', // 🔥 INI YANG DIPERBAIKI (sebelumnya approvals.user)
            'approvals.role'
        ])->where('po_number', $slug)->firstOrFail();

        $charges = \DB::table('purchase_order_charges')->where('purchase_order_id', $po->id)->get();
        $extraDiscounts = \DB::table('purchase_order_discounts')->where('purchase_order_id', $po->id)->get();

        $hasBeenApproved = $po->approvals->where('status', 'APPROVED')->isNotEmpty();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('po.print_pdf', compact('po', 'charges', 'extraDiscounts', 'hasBeenApproved'))
                  ->setPaper('A4', 'portrait');

        return $pdf->stream('Purchase_Order_' . str_replace('/', '_', $po->po_number) . '.pdf');
    }




    // =========================================================================
    // 🔥 CETAK PURCHASE ORDER + LAMPIRAN PENDUKUNG (ANTI-BADAI / SMART MERGE) 🔥
    // =========================================================================
    public function printCompletePdf($slug)
    {
        $po = \App\Models\PurchaseOrder::with([
            'items.item.itemUoms', 'vendor', 'company', 'status', 'user', 'approvals.approver', 'approvals.role'
        ])->where('po_number', $slug)->firstOrFail();

        $charges = \DB::table('purchase_order_charges')->where('purchase_order_id', $po->id)->get();
        $extraDiscounts = \DB::table('purchase_order_discounts')->where('purchase_order_id', $po->id)->get();
        $hasBeenApproved = $po->approvals->where('status', 'APPROVED')->isNotEmpty();

        $poPdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('po.print_pdf', compact('po', 'charges', 'extraDiscounts', 'hasBeenApproved'))
                 ->setPaper('A4', 'portrait');

        $tempDir = storage_path('app/public/temp_pdf');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $poFilename = 'main_po_' . $po->id . '_' . time() . '.pdf';
        $poPath = $tempDir . '/' . $poFilename;
        file_put_contents($poPath, $poPdf->output());

        $oMerger = \Webklex\PDFMerger\Facades\PDFMergerFacade::init();
        $oMerger->addPDF($poPath, 'all');

        $tempFilesToDelete = [$poPath];
        $totalLampiranDiDatabase = 0;

        foreach ($po->items as $item) {

            $attachments = \DB::table('purchase_order_item_attachments')
                              ->where('purchase_order_item_id', $item->id)
                              ->get();

            if ($attachments && $attachments->count() > 0) {

                $totalLampiranDiDatabase += $attachments->count();

                foreach ($attachments as $file) {
                    $cleanFilePath = ltrim($file->file_path, '/');
                    $finalFilePath = storage_path('app/public/' . $cleanFilePath);

                    // 🔥 JIKA FILE FISIK DITEMUKAN 🔥
                    if (file_exists($finalFilePath)) {
                        $extension = strtolower(pathinfo($finalFilePath, PATHINFO_EXTENSION));

                        // 1. PENANGANAN FILE PDF
                        if ($extension === 'pdf') {
                            try {
                                // TEST: Coba baca PDF-nya dulu, apakah kompresinya didukung?
                                $fpdi = new \setasign\Fpdi\Fpdi();
                                $fpdi->setSourceFile($finalFilePath);

                                // Jika lolos tanpa error, gabungkan!
                                $oMerger->addPDF($finalFilePath, 'all');
                            } catch (\Exception $e) {
                                // Jika PDF-nya terlalu canggih/terkompresi, buat halaman Info Pengganti
                                $html = "<div style='border:2px solid #0d6efd; padding:20px; text-align:center; font-family:sans-serif; margin-top:50px;'>
                                            <h2 style='color:#0d6efd;'>📄 LAMPIRAN PDF (TERENKRIPSI/TERKOMPRESI)</h2>
                                            <p>File pendukung bernama: <b>{$file->file_name}</b></p>
                                            <p>File ini menggunakan format PDF modern yang tidak bisa digabungkan ke dalam dokumen ini secara otomatis.</p>
                                            <p><i>Silakan lihat atau unduh file ini langsung melalui sistem ProcureApp.</i></p>
                                         </div>";
                                $infoPdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait');
                                $infoPath = $tempDir . '/info_' . uniqid() . '.pdf';
                                file_put_contents($infoPath, $infoPdf->output());
                                $oMerger->addPDF($infoPath, 'all');
                                $tempFilesToDelete[] = $infoPath;
                            }
                        }
                        // 2. PENANGANAN FILE GAMBAR
                        elseif (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                            $imageData = base64_encode(file_get_contents($finalFilePath));
                            $mime = mime_content_type($finalFilePath);
                            $base64Src = 'data:' . $mime . ';base64,' . $imageData;

                            $imgPdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML("
                                <html><head><style>@page{margin:0px;} body{margin:0;padding:20px;text-align:center;} img{max-width:100%;max-height:1050px;}</style></head>
                                <body><img src='" . $base64Src . "'></body></html>
                            ")->setPaper('a4', 'portrait');

                            $imgTempName = 'img_convert_' . uniqid() . '.pdf';
                            $imgTempPath = $tempDir . '/' . $imgTempName;
                            file_put_contents($imgTempPath, $imgPdf->output());
                            $oMerger->addPDF($imgTempPath, 'all');
                            $tempFilesToDelete[] = $imgTempPath;
                        }
                        // 3. PENANGANAN FILE WORD / EXCEL / LAINNYA
                        else {
                            $html = "<div style='border:2px solid #198754; padding:20px; text-align:center; font-family:sans-serif; margin-top:50px;'>
                                        <h2 style='color:#198754;'>📎 LAMPIRAN BERKAS ({strtoupper($extension)})</h2>
                                        <p>File pendukung bernama: <b>{$file->file_name}</b></p>
                                        <p>File ini berformat Excel / Word / Lainnya sehingga tidak dapat ditampilkan sebagai halaman PDF.</p>
                                        <p><i>Silakan unduh lampiran ini melalui menu detail PO di sistem.</i></p>
                                     </div>";
                            $infoPdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait');
                            $infoPath = $tempDir . '/info_' . uniqid() . '.pdf';
                            file_put_contents($infoPath, $infoPdf->output());
                            $oMerger->addPDF($infoPath, 'all');
                            $tempFilesToDelete[] = $infoPath;
                        }
                    }
                    // 🔥 JIKA FILE FISIK HILANG 🔥
                    else {
                        $errorHtml = "<div style='border:2px solid red; padding:20px; text-align:center; font-family:sans-serif; margin-top:50px;'>
                                        <h2 style='color:red;'>⚠️ FILE FISIK HILANG ⚠️</h2>
                                        <p>Data lampiran <b>{$file->file_name}</b> tercatat di sistem, tapi file aslinya tidak ditemukan di server.</p>
                                      </div>";
                        $errorPdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($errorHtml)->setPaper('a4', 'portrait');
                        $errorTempPath = $tempDir . '/err_notfound_' . uniqid() . '.pdf';
                        file_put_contents($errorTempPath, $errorPdf->output());
                        $oMerger->addPDF($errorTempPath, 'all');
                        $tempFilesToDelete[] = $errorTempPath;
                    }
                }
            }
        }

        if ($totalLampiranDiDatabase === 0) {
            $noDataHtml = "<div style='border: 2px solid orange; padding: 20px; font-family: sans-serif; text-align:center; margin-top:50px;'>
                            <h2 style='color: orange;'>⚠️ INFO SISTEM ⚠️</h2><p>TIDAK ADA DATA LAMPIRAN untuk PO ini.</p></div>";
            $noDataPdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($noDataHtml)->setPaper('a4', 'portrait');
            $noDataTempPath = $tempDir . '/err_nodata_' . uniqid() . '.pdf';
            file_put_contents($noDataTempPath, $noDataPdf->output());
            $oMerger->addPDF($noDataTempPath, 'all');
            $tempFilesToDelete[] = $noDataTempPath;
        }

        $oMerger->merge();
        $finalPdfOutput = $oMerger->output();

        foreach ($tempFilesToDelete as $trashPath) {
            if (file_exists($trashPath)) {
                unlink($trashPath);
            }
        }

        return response($finalPdfOutput)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="PO_Lengkap_' . str_replace('/', '_', $po->po_number) . '.pdf"');
    }


    // =========================================================================
    // 8. HALAMAN INDEX PO & 🔥 VISIBILITAS APPROVAL 🔥
    // =========================================================================
    public function index(\Illuminate\Http\Request $request)
    {
        $search = $request->input('search');
        $user = auth()->user();
        $userRoleIds = $user->roles->pluck('id')->toArray();

        // --- TARIK ANTRIAN PR ---
        $statusApproved = \App\Models\Status::where('type', 'PR')->where('slug', 'approved')->first();
        $statusPartial  = \App\Models\Status::where('type', 'PR')->where('slug', 'partial_po')->first();
        $statusIds = array_filter([$statusApproved ? $statusApproved->id : null, $statusPartial ? $statusPartial->id : null]);

        $readyPrs = \App\Models\PurchaseRequest::with(['user', 'status', 'company'])
            ->whereIn('status_id', $statusIds)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('pr_number', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($qUser) use ($search) { $qUser->where('name', 'like', "%{$search}%"); })
                      ->orWhereHas('company', function ($qComp) use ($search) { $qComp->where('name', 'like', "%{$search}%"); })
                      ->orWhereHas('items.vendorQuotes.vendor', function ($qVendor) use ($search) { $qVendor->where('name', 'like', "%{$search}%"); });
                });
            })->orderBy('created_at', 'desc')->paginate(10, ['*'], 'pr_page');

        // --- TARIK DAFTAR PO ---
        $poQuery = \App\Models\PurchaseOrder::with(['vendor', 'company', 'purchaseRequest.company', 'status', 'approvals.role'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('po_number', 'like', "%{$search}%")
                      ->orWhereHas('vendor', function ($qVendor) use ($search) { $qVendor->where('name', 'like', "%{$search}%"); })
                      ->orWhereHas('company', function ($qPoComp) use ($search) { $qPoComp->where('name', 'like', "%{$search}%"); })
                      ->orWhereHas('purchaseRequest', function ($qPr) use ($search) {
                          $qPr->where('pr_number', 'like', "%{$search}%")
                              ->orWhereHas('company', function ($qPrComp) use ($search) { $qPrComp->where('name', 'like', "%{$search}%"); });
                      });
                });
            });

        // 🔥 LOGIKA VISIBILITAS (PINTU AKSES APPROVAL) 🔥
        if (!$user->hasAnyRole(['Super Admin', 'super-admin', 'Super Administrator'])) {
            $poQuery->where(function ($q) use ($user, $userRoleIds) {
                // A. Pembuat PO (Misal: Purchasing)
                $q->where('created_by', $user->id)

                  // B. Atasan yang sedang ditunggu (PENDING)
                  ->orWhereHas('approvals', function ($qApprovals) use ($userRoleIds) {
                      $qApprovals->where('status', 'PENDING')
                                 ->whereIn('role_id', $userRoleIds);
                  })

                  // C. Riwayat Approver (Supaya yang sudah Setuju tetap bisa lihat datanya)
                  ->orWhereHas('approvals', function ($qApprovals) use ($user) {
                      $qApprovals->where('approved_by', $user->id);
                  });
            });
        }

        $purchaseOrders = $poQuery->orderBy('created_at', 'desc')->paginate(10, ['*'], 'po_page');

        return view('po.index', compact('readyPrs', 'purchaseOrders', 'search'));
    }

    // =========================================================================
    // 9. PROCESS PR PAGE
    // =========================================================================
    public function processPr($slug)
    {
        $pr = \App\Models\PurchaseRequest::with(['items.item.uom', 'items.item.itemUoms', 'items.vendorQuotes.vendor', 'user', 'company'])->where('pr_number', $slug)->firstOrFail();
        $prStatusSlug = strtolower(optional($pr->status)->slug ?? '');

        if (!in_array($prStatusSlug, ['approved', 'partial_po'])) {
            $prStatusText = strtoupper(trim(optional($pr->status)->name ?? $pr->status ?? ''));
            $isAllowed = false;
            foreach (['APPROVED', 'PARTIAL', 'DISETUJUI', 'FINAL'] as $keyword) {
                if (str_contains($prStatusText, $keyword)) {
                    $isAllowed = true;
                    break;
                }
            }
            if (!$isAllowed) {
                return redirect()->back()->with('error', 'Akses Ditolak: PR ini berstatus "' . $prStatusText . '". Hanya PR yang Disetujui/Partial yang bisa dibuatkan PO.');
            }
        }

        $existingPos = \App\Models\PurchaseOrder::with(['vendor', 'status', 'items.item'])
            ->where('purchase_request_id', $pr->id)
            ->whereHas('status', function($q) {
                $q->whereNotIn('slug', ['canceled', 'cancelled', 'rejected']);
            })->orderBy('created_at', 'desc')->get();

        $selectedVendor = null;
        $firstQuote = $pr->items->flatMap->vendorQuotes->first();

        if ($firstQuote && $firstQuote->vendor) {
            $selectedVendor = $firstQuote->vendor;
        }

        $defaultCurrency = 'IDR';
        if ($firstQuote && !empty($firstQuote->currency)) {
            $currObj = \App\Models\Currency::find($firstQuote->currency_id);
            if($currObj) $defaultCurrency = $currObj->code;
        }

        $defaultShippingAddress = $pr->items->first()->delivery_address ?? null;
        if (empty($defaultShippingAddress)) {
            if ($pr->company && !empty($pr->company->address)) {
                $defaultShippingAddress = $pr->company->address;
            } else {
                $headOffice = \App\Models\Company::where('is_head_office', true)->first();
                $defaultShippingAddress = $headOffice ? $headOffice->address : '';
            }
        }

        $vendors = \App\Models\Vendor::orderBy('name')->get();
        $companies = \App\Models\Company::orderBy('name')->get();
        $paymentTerms = \App\Models\PaymentTerm::orderBy('days')->get();
        $taxes = \App\Models\Tax::where('is_active', true)->orderBy('percent')->get();
        $chargeTypes = \App\Models\ChargeType::where('is_active', true)->get();
        $currencies = \App\Models\Currency::where('is_active', true)->get();
        $discountTypes = \App\Models\DiscountType::where('is_active', true)->get();

        return view('po.process_pr', compact('pr', 'existingPos', 'vendors', 'companies', 'paymentTerms', 'taxes', 'chargeTypes', 'currencies', 'discountTypes', 'selectedVendor', 'defaultCurrency', 'defaultShippingAddress'));
    }

    // =========================================================================
    // 10. MENGAJUKAN PERSETUJUAN PO
    // =========================================================================
    public function submitApproval($slug)
    {
        DB::beginTransaction();
        try {
            $po = \App\Models\PurchaseOrder::where('po_number', $slug)->firstOrFail();
            $statusPending = \App\Models\Status::where('type', 'PO')->where('slug', 'pending_approval')->first();

            if ($statusPending) $po->update(['status_id' => $statusPending->id]);

            \App\Models\DocumentApproval::where('document_id', $po->id)->where('document_type', get_class($po))->delete();
            $workflow = \DB::table('approval_workflows')->where('document_type', get_class($po))->where('is_active', 1)->first();

            if (!$workflow) throw new \Exception("Workflow Persetujuan untuk PO belum diaktifkan di Master Matriks!");

            $steps = \DB::table('approval_workflow_steps')
                ->where('approval_workflow_id', $workflow->id)
                ->where('min_amount', '<=', $po->grand_total)
                ->orderBy('step_order', 'asc')
                ->get();

            if ($steps->isEmpty()) throw new \Exception('Langkah Persetujuan (Steps) belum diatur untuk nominal PO ini.');

            foreach ($steps as $step) {
                \App\Models\DocumentApproval::create([
                    'document_id' => $po->id,
                    'document_type' => get_class($po),
                    'role_id' => $step->role_id,
                    'step_order' => $step->step_order,
                    'status' => 'PENDING'
                ]);
            }

            $this->logHistory($po->id, 'Diajukan Ulang', 'Dokumen PO telah diajukan ke antrean Matriks Persetujuan.');
            DB::commit();
            return redirect()->route('po.show', $slug)->with('success', 'PO Berhasil diajukan! Menunggu di-ACC atasan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal mengajukan PO: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // 11. KEPUTUSAN (APPROVE / REJECT) PO
    // =========================================================================
    public function decide(Request $request, string $slug)
    {
        DB::beginTransaction();
        try {
            $po = \App\Models\PurchaseOrder::where('po_number', $slug)->firstOrFail();

            foreach(auth()->user()->unreadNotifications as $notification) {
                if(isset($notification->data['url']) && str_contains($notification->data['url'], route('po.show', $po->po_number))) {
                    $notification->markAsRead();
                }
            }

            $currentApproval = \App\Models\DocumentApproval::with('role')
                ->where('document_id', $po->id)
                ->where('document_type', get_class($po))
                ->where('status', 'PENDING')
                ->orderBy('step_order', 'asc')
                ->first();

            $action = strtoupper($request->input('action', ''));

            if (!$currentApproval && $action !== 'REJECT') {
                DB::rollBack();
                return redirect()->back()->with('error', 'Dokumen ini tidak menunggu persetujuan apapun saat ini.');
            }

            if ($currentApproval && !auth()->user()->hasRole($currentApproval->role->name) && !auth()->user()->hasRole(['Super Admin', 'super-admin', 'Super Administrator'])) {
                DB::rollBack();
                return redirect()->back()->with('error', 'AKSES DITOLAK: Giliran persetujuan saat ini adalah wewenang ' . $currentApproval->role->name . '. Anda tidak memiliki hak!');
            }

            $approverRoleName = $currentApproval && $currentApproval->role ? $currentApproval->role->name : 'Atasan';

            if ($action === 'REJECT') {
                if ($currentApproval) $currentApproval->update(['status' => 'REJECTED', 'approved_by' => auth()->id(), 'approved_at' => now()]);
                $statusRejected = \App\Models\Status::where('type', 'PO')->whereIn('slug', ['draft', 'rejected'])->first();
                if ($statusRejected) $po->update(['status_id' => $statusRejected->id]);
                if (\Schema::hasColumn('purchase_orders', 'current_approval_level')) $po->update(['current_approval_level' => 0]);

                $reason = $request->input('note', 'Ditolak secara global');
                $this->logHistory($po->id, 'Ditolak', "Dokumen ditolak oleh " . auth()->user()->name . " (Sebagai $approverRoleName). Alasan: $reason");
                DB::commit();
                return redirect()->route('po.show', $po->po_number)->with('error', 'PO ditolak dan dikembalikan ke Draft.');
            }

            $currentApproval->update(['status' => 'APPROVED', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            if (\Schema::hasColumn('purchase_orders', 'current_approval_level')) $po->update(['current_approval_level' => $currentApproval->step_order]);

            $nextApproval = \App\Models\DocumentApproval::with('role')
                ->where('document_id', $po->id)
                ->where('document_type', get_class($po))
                ->where('status', 'PENDING')
                ->orderBy('step_order', 'asc')
                ->first();

            $actionText = 'Disetujui (' . strtoupper($approverRoleName) . ')';
            $catatan = "Dokumen disetujui pada tahap ini oleh " . auth()->user()->name . ".\n";

            if ($nextApproval) {
                $nextRoleName = $nextApproval->role ? $nextApproval->role->name : 'Atasan Berikutnya';
                $catatan .= "Diteruskan ke: **" . strtoupper($nextRoleName) . "**\n";
                $successMsg = "Disetujui! Diteruskan ke $nextRoleName.";
            } else {
                $statusFinal = \App\Models\Status::where('type', 'PO')->whereIn('slug', ['issued', 'approved'])->first();
                if ($statusFinal) $po->update(['status_id' => $statusFinal->id]);
                $actionText = 'Disetujui Final';
                $catatan .= "Persetujuan Matriks telah SELESAI.\n";
                $successMsg = "Hore! Dokumen PO telah disetujui secara FINAL!";
            }

            $this->logHistory($po->id, $actionText, $catatan);
            DB::commit();
            return redirect()->route('po.show', $po->po_number)->with('success', $successMsg);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // 12. FUNGSI HAPUS ATTACHMENTS
    // =========================================================================
    public function deleteAttachment($id)
    {
        try {
            $attachment = \App\Models\PurchaseOrderAttachment::findOrFail($id);
            if (\Storage::disk('public')->exists($attachment->file_path)) {
                \Storage::disk('public')->delete($attachment->file_path);
            }
            $attachment->delete();
            $this->logHistory($attachment->purchase_order_id, 'Lampiran Dihapus', "File '{$attachment->file_name}' telah dihapus.");
            return redirect()->back()->with('success', 'File berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghapus file: ' . $e->getMessage());
        }
    }

    public function deleteItemAttachment($id)
    {
        try {
            $attachment = \DB::table('purchase_order_item_attachments')->where('id', $id)->first();
            if ($attachment) {
                if (\Storage::disk('public')->exists($attachment->file_path)) {
                    \Storage::disk('public')->delete($attachment->file_path);
                }
                \DB::table('purchase_order_item_attachments')->where('id', $id)->delete();
            }
            return back()->with('success', 'Lampiran Barang berhasil dihapus secara permanen!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus lampiran: ' . $e->getMessage());
        }
    }

    public function deleteHeaderAttachment($id)
    {
        try {
            $attachment = \DB::table('purchase_order_attachments')->where('id', $id)->first();
            if ($attachment) {
                if (\Storage::disk('public')->exists($attachment->file_path)) {
                    \Storage::disk('public')->delete($attachment->file_path);
                }
                \DB::table('purchase_order_attachments')->where('id', $id)->delete();
            }
            return back()->with('success', 'Lampiran Header PO berhasil dihapus secara permanen!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus lampiran: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // HELPER 5: LOG HISTORY
    // =========================================================================
    private function logHistory($poId, $action, $note = null)
    {
        \App\Models\PurchaseOrderHistory::create([
            'purchase_order_id' => $poId,
            'user_id' => auth()->id(),
            'action' => $action,
            'note' => $note
        ]);
    }




    // =========================================================================
    // CETAK BPR LENGKAP DENGAN LAMPIRAN (PDF MERGER) UNTUK PO
    // =========================================================================
    public function printBprWithAttachments($slug)
    {
        $po = \App\Models\PurchaseOrder::with([
            'items.item', 'company', 'user', 'vendor', 'attachments', 'purchaseRequest'
        ])->where('po_number', $slug)->firstOrFail();

        // 🔥 WAJIB: Tarik manual lampiran item (Sama seperti di fungsi show)
        $poItemIds = $po->items->pluck('id')->toArray();
        $itemAttachments = \DB::table('purchase_order_item_attachments')
                            ->whereIn('purchase_order_item_id', $poItemIds)
                            ->get();

        foreach ($po->items as $item) {
            $item->raw_attachments = $itemAttachments->where('purchase_order_item_id', $item->id)->values();
        }

        // 1. RENDER DOMPDF MENGGUNAKAN TEMPLATE BPR PO
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('po.pdf_bpr', compact('po'))
                ->setPaper('A4', 'portrait');

        // 2. SIMPAN HASIL DOMPDF SEMENTARA DI FOLDER STORAGE
        $tempMainPdfPath = storage_path('app/temp_po_bpr_' . uniqid() . '.pdf');
        $pdf->save($tempMainPdfPath);

        // 3. INISIASI MESIN PENGGABUNG PDF (MERGER)
        $merger = new \iio\libmergepdf\Merger();
        $merger->addFile($tempMainPdfPath);

        // 4. CARI SEMUA LAMPIRAN BERFORMAT PDF & MASUKKAN KE MERGER
        // A. Dari Header
        if ($po->attachments) {
            foreach ($po->attachments as $attachment) {
                $ext = strtolower(pathinfo($attachment->file_path, PATHINFO_EXTENSION));
                if ($ext === 'pdf') {
                    $pdfPath = public_path('storage/' . $attachment->file_path);
                    if (file_exists($pdfPath)) $merger->addFile($pdfPath);
                }
            }
        }

        // B. Dari Item (Berdasarkan raw_attachments yang baru ditarik)
        foreach ($po->items as $item) {
            if (isset($item->raw_attachments) && count($item->raw_attachments) > 0) {
                foreach ($item->raw_attachments as $attachment) {
                    // Cek ekstensi file (asumsi kolom file_name atau file_path tersedia)
                    $ext = strtolower(pathinfo($attachment->file_name ?? $attachment->file_path, PATHINFO_EXTENSION));
                    if ($ext === 'pdf') {
                        $pdfPath = public_path('storage/' . $attachment->file_path);
                        if (file_exists($pdfPath)) $merger->addFile($pdfPath);
                    }
                }
            }
        }

        // 5. JAHIT/GABUNGKAN SEMUA PDF MENJADI SATU KESATUAN
        $mergedPdfData = $merger->merge();

        // 6. BERSIHKAN FILE SEMENTARA
        if (file_exists($tempMainPdfPath)) {
            unlink($tempMainPdfPath);
        }

        $filename = 'BPR_PO_' . str_replace('/', '_', $po->po_number) . '.pdf';

        return response($mergedPdfData)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }


}
