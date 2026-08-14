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

use Webklex\PDFMerger\Facades\PDFMergerFacade as PDFMerger;

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
                    'item_name'                => $itemData['item_name_override'] ?? null, // 🔥 SIMPAN NAMA PENDEK
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

                // 1. FILTER DAN KELOMPOKKAN VENDOR SECARA MANUAL
                $itemsByVendor = [];
                foreach ($request->po_items as $originalIndex => $itemData) {
                    if (isset($itemData['is_selected']) && trim($itemData['vendor_id'] ?? '') !== '' && (float)($itemData['qty'] ?? 0) > 0) {
                        $itemsByVendor[$itemData['vendor_id']][$originalIndex] = $itemData;
                    }
                }

                if (empty($itemsByVendor)) {
                    throw new \Exception('Anda harus memilih Vendor Aktual minimal untuk 1 barang!');
                }

                $paymentTermName = \App\Models\PaymentTerm::find($request->payment_term_id)->name ?? null;
                $prItemIdsToHeal = [];

                // 2. LOOPING PEMBUATAN PO PER VENDOR
                foreach ($itemsByVendor as $vendorId => $items) {
                    // A. Setup Nomor PO & Storage
                    $newPoNumber = $this->generatePoNumber($request->billing_company_id);
                    $storagePath = (\Illuminate\Support\Facades\DB::table('system_settings')->where('setting_key', 'path_po_attachment')->value('setting_value') ?: 'attachments/purchase_orders') . '/' . str_replace(['/', '\\'], '-', $newPoNumber);

                    $poSubtotalGross = 0; $poTotalItemDiscount = 0; $poTotalTaxItem = 0; $processedLineItems = [];

                    // B. HITUNG RINCIAN PER ITEM
                    foreach ($items as $originalIndex => $itemData) {
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

                        $taxVal = (float) ($itemData['tax_value'] ?? 0);
                        $taxType = strtoupper($itemData['tax_type'] ?? 'FIXED');
                        $taxAmt = ($taxType === 'PERCENT') ? ($dpp * $taxVal / 100) : $taxVal;

                        $poSubtotalGross += $gross; $poTotalItemDiscount += $discAmt; $poTotalTaxItem += $taxAmt;

                        $processedLineItems[] = [
                            'originalIndex' => $originalIndex,
                            'itemData' => $itemData, 'discAmt' => $discAmt, 'dpp' => $dpp,
                            'taxType' => $taxType, 'taxVal' => $taxVal, 'taxAmt' => $taxAmt, 'qty' => $qty, 'price' => $price,
                            'discType' => $discType, 'discVal' => $discVal
                        ];
                    }

                    // C. HITUNG DISKON GLOBAL KHUSUS VENDOR INI
                    $globalDiscType = 'FIXED'; $globalDiscVal = 0; $poGlobalDiscount = 0;
                    if ($request->has('global_discounts')) {
                        foreach ($request->global_discounts as $gDisc) {
                            if ($gDisc['vendor_id'] == 'ALL' || $gDisc['vendor_id'] == $vendorId) {
                                $globalDiscType = strtoupper($gDisc['type']);
                                $globalDiscVal = (float)$gDisc['value'];
                                $poGlobalDiscount += ($globalDiscType === 'PERCENT') ? (($poSubtotalGross - $poTotalItemDiscount) * ($globalDiscVal / 100)) : $globalDiscVal;
                            }
                        }
                    }
                    $dppAfterGlobalDisc = ($poSubtotalGross - $poTotalItemDiscount) - $poGlobalDiscount;

                    // D. HITUNG PAJAK GLOBAL MANUAL KHUSUS VENDOR INI
                    $globalTaxType = 'FIXED'; $globalTaxVal = 0; $poGlobalTax = 0;
                    if ($request->has('global_taxes')) {
                        foreach ($request->global_taxes as $gTax) {
                            if ($gTax['vendor_id'] == 'ALL' || $gTax['vendor_id'] == $vendorId) {
                                $globalTaxType = strtoupper($gTax['type']);
                                $globalTaxVal = (float)$gTax['value'];
                                $poGlobalTax += ($globalTaxType === 'PERCENT') ? ($dppAfterGlobalDisc * ($globalTaxVal / 100)) : $globalTaxVal;
                            }
                        }
                    }

                    // E. HITUNG BIAYA TAMBAHAN
                    $poChargeTotal = 0; $appliedCharges = [];
                    if ($request->has('charges')) {
                        foreach ($request->charges as $charge) {
                            if (!empty($charge['amount']) && ($charge['vendor_id'] == 'ALL' || $charge['vendor_id'] == $vendorId)) {
                                $poChargeTotal += (float)$charge['amount'];
                                $appliedCharges[] = $charge;
                            }
                        }
                    }

                    // F. HITUNG POTONGAN VOUCHER
                    $poExtraDiscountTotal = 0; $appliedExtraDiscs = [];
                    if ($request->has('extra_discounts')) {
                        foreach ($request->extra_discounts as $disc) {
                            if (!empty($disc['amount']) && ($disc['vendor_id'] == 'ALL' || $disc['vendor_id'] == $vendorId)) {
                                $poExtraDiscountTotal += (float)$disc['amount'];
                                $appliedExtraDiscs[] = $disc;
                            }
                        }
                    }

                    // G. REKAP GRAND TOTAL
                    $totalAllDiscounts = $poTotalItemDiscount + $poGlobalDiscount;
                    $totalAllTaxes = $poTotalTaxItem + $poGlobalTax;
                    $poGrandTotal = $dppAfterGlobalDisc + $totalAllTaxes + $poChargeTotal - $poExtraDiscountTotal;

                    // H. SIMPAN HEADER PO
                    $po = \App\Models\PurchaseOrder::create([
                        'po_number'             => $newPoNumber,
                        'purchase_request_id'   => $prRecord->id,
                        'vendor_id'             => $vendorId,
                        'bill_to_company_id'    => $request->billing_company_id,
                        'status_id'             => 1,
                        'po_date'               => $request->po_date ?? now(),
                        'created_by'            => auth()->id(),
                        'currency'              => $request->currency ?? 'IDR',
                        'shipping_address'      => $request->shipping_address,
                        'payment_terms'         => $paymentTermName,
                        'notes'                 => $request->notes,
                        'delivery_date'         => $request->delivery_date,
                        'global_discount_type'  => $globalDiscType,
                        'global_discount_value' => $globalDiscVal,
                        'global_tax_type'       => $globalTaxType,
                        'global_tax_value'      => $globalTaxVal,
                        'subtotal'              => $poSubtotalGross,
                        'discount_total'        => $totalAllDiscounts,
                        'tax_total'             => $totalAllTaxes,
                        'charge_total'          => $poChargeTotal,
                        'grand_total'           => $poGrandTotal,
                    ]);

                    // I. SIMPAN RINCIAN EXTRA
                    foreach ($appliedCharges as $charge) {
                        \Illuminate\Support\Facades\DB::table('purchase_order_charges')->insert([
                            'purchase_order_id' => $po->id, 'name' => $charge['charge_type_id'], 'amount' => $charge['amount'], 'created_at' => now(), 'updated_at' => now()
                        ]);
                    }
                    foreach ($appliedExtraDiscs as $disc) {
                        \Illuminate\Support\Facades\DB::table('purchase_order_discounts')->insert([
                            'purchase_order_id' => $po->id, 'name' => $disc['discount_type_id'], 'amount' => $disc['amount'], 'created_at' => now(), 'updated_at' => now()
                        ]);
                    }

                    // J. SIMPAN LAMPIRAN HEADER
                    if ($request->hasFile('header_attachments')) {
                        foreach ($request->file('header_attachments') as $file) {
                            if ($file instanceof \Illuminate\Http\UploadedFile) {
                                $path = $file->storeAs($storagePath, time() . '_' . uniqid() . '_' . str_replace(' ', '_', $file->getClientOriginalName()), 'public');
                                \Illuminate\Support\Facades\DB::table('purchase_order_attachments')->insert([
                                    'purchase_order_id' => $po->id,
                                    'file_name'         => $file->getClientOriginalName(),
                                    'file_path'         => str_replace('\\', '/', $path),
                                    'created_at'        => now(), 'updated_at' => now()
                                ]);
                            }
                        }
                    }

                    // K. SIMPAN BARIS ITEM
                    foreach ($processedLineItems as $line) {
                        $itemData = $line['itemData'];
                        $prItem = \App\Models\PurchaseRequestItem::find($itemData['pr_item_id']);

                        $newPoItem = \App\Models\PurchaseOrderItem::create([
                            'purchase_order_id'        => $po->id,
                            'item_id'                  => $itemData['item_id'],
                            'item_name'                => $itemData['item_name_override'] ?? ($prItem->item_name ?? null),
                            'purchase_request_item_id' => $itemData['pr_item_id'],
                            'uom_id'                   => $itemData['uom_id'] ?? null,
                            'uom'                      => $itemData['uom'] ?? ($prItem->uom_short ?? 'PCS'),
                            'description'              => $itemData['notes'] ?? (\App\Models\Item::find($itemData['item_id'])->name ?? '-'),
                            'tax_id'                   => null,
                            'qty_ordered'              => $line['qty'],
                            'unit_price'               => $line['price'],
                            'discount_type'            => $line['discType'],
                            'discount_value'           => $line['discVal'],
                            'discount_amount'          => $line['discAmt'],
                            'tax_type'                 => $line['taxType'],
                            'tax_value'                => $line['taxVal'],
                            'tax_amount'               => $line['taxAmt'],
                            'subtotal'                 => $line['dpp'],
                        ]);

                        $files = $request->file("po_items.{$line['originalIndex']}.attachments");
                        if (!empty($files)) {
                            foreach (is_array($files) ? $files : [$files] as $file) {
                                if ($file instanceof \Illuminate\Http\UploadedFile) {
                                    $path = $file->storeAs($storagePath, "item_{$itemData['item_id']}_" . uniqid() . time() . "." . $file->extension(), 'public');
                                    \Illuminate\Support\Facades\DB::table('purchase_order_item_attachments')->insert([
                                        'purchase_order_item_id' => $newPoItem->id,
                                        'file_name'              => $file->getClientOriginalName(),
                                        'file_path'              => str_replace('\\', '/', $path),
                                        'created_at'             => now(), 'updated_at' => now()
                                    ]);
                                }
                            }
                        }
                    }

                    // ====================================================================
                    // 🔥 L. JALANKAN WORKFLOW (APPROVAL) UNTUK MASING-MASING PO 🔥
                    // ====================================================================
                    $customWorkflowId = $request->input('custom_workflow_id');
                    $needsApproval = false;

                    if ($customWorkflowId) {
                        // A. Jalur Khusus (Dari Dropdown)
                        $workflow = \App\Models\ApprovalWorkflow::with('steps')->find($customWorkflowId);

                        if ($workflow && $workflow->steps->count() > 0) {
                            foreach ($workflow->steps as $step) {
                                // Ambil ID departemen target, entah itu tersimpan di kolom department_id atau target_department_id pada tabel master MATRIKS (approval_workflow_steps)
                                $targetDept = $step->target_department_id ?? $step->department_id ?? null;

                                \App\Models\DocumentApproval::create([
                                    'document_id'          => $po->id,
                                    'document_type'        => get_class($po),
                                    'role_id'              => $step->role_id,
                                    // 🔥 PERBAIKAN: HAPUS 'department_id', HANYA GUNAKAN 'target_department_id' 🔥
                                    'target_department_id' => $targetDept,
                                    'step_order'           => $step->step_order,
                                    'status'               => 'PENDING'
                                ]);
                            }
                            $needsApproval = true;
                            $this->logHistory($po->id, 'SYSTEM', "Menggunakan Rute Persetujuan Khusus: " . $workflow->name);
                        }
                    } else {
                        // B. Jalur Standar / Otomatis
                        $needsApproval = \App\Services\ApprovalService::generateWorkflow($po);
                        if ($needsApproval) {
                            $this->logHistory($po->id, 'SYSTEM', 'PO diterbitkan dari PR dan masuk antrean persetujuan (Workflow Standar).');
                        }
                    }

                    // M. UPDATE STATUS BERDASARKAN HASIL WORKFLOW
                    if ($needsApproval) {
                        $pendingStatusId = \App\Models\Status::where('type', 'PO')->whereIn('slug', ['pending_approval', 'pending'])->first()->id ?? 1;
                        $po->update(['status_id' => $pendingStatusId]);
                    } else {
                        $approvedStatusId = \App\Models\Status::where('type', 'PO')->where('slug', 'approved')->first()->id ?? 3;
                        $po->update(['status_id' => $approvedStatusId]);
                        $this->logHistory($po->id, 'APPROVED', 'PO Auto-Approved karena tidak ada aturan aktif atau nominal di bawah batas.');
                    }
                }

                // 4. EKSEKUSI SELF-HEALING PR
                if (!empty($prItemIdsToHeal)) {
                    foreach(array_unique($prItemIdsToHeal) as $pid) {
                        $this->recalculatePrItemFulfillment($pid);
                    }
                    $this->checkAndUpdatePrStatus($prRecord->id);
                }
            });

            return redirect()->route('po.index')->with('success', 'Purchase Order berhasil diterbitkan!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal memproses pembuatan PO: ' . $e->getMessage());
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
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $slug, $settingService) {
                $po = \App\Models\PurchaseOrder::with('items')->where('po_number', $slug)->firstOrFail();

                if (!in_array(strtolower(optional($po->status)->slug ?? ''), ['draft', 'pending_approval', 'pending', 'rejected', ''])) {
                    throw new \Exception('Gagal: PO ini sudah tidak dapat diedit karena statusnya ' . optional($po->status)->name);
                }

                $poSubtotalGross = 0;
                $poTotalItemDiscount = 0;
                $poTotalTax = 0;
                $safePoNumber = str_replace(['/', '\\'], '-', $po->po_number);
                $storagePath = (\Illuminate\Support\Facades\DB::table('system_settings')->where('setting_key', 'path_po_attachment')->value('setting_value') ?: 'attachments/purchase_orders') . '/' . $safePoNumber;

                // 1. UPDATE DETAIL PER ITEM
                foreach ($request->po_items as $itemId => $itemData) {
                    if (!$poItem = \App\Models\PurchaseOrderItem::find($itemId)) continue;

                    $newQty = (float) ($itemData['qty'] ?? 0);
                    $price = (float) ($itemData['unit_price'] ?? 0);
                    $gross = $newQty * $price;

                    $discVal = (float) ($itemData['discount_value'] ?? 0);
                    $discType = strtoupper($itemData['discount_type'] ?? 'FIXED');
                    $discAmt = ($discType === 'PERCENT') ? ($gross * ($discVal / 100)) : $discVal;

                    $dpp = $gross - $discAmt;

                    $taxVal = (float) ($itemData['tax_value'] ?? 0);
                    $taxType = strtoupper($itemData['tax_type'] ?? 'FIXED');
                    $taxAmt = ($taxType === 'PERCENT') ? ($dpp * ($taxVal / 100)) : $taxVal;

                    // UPLOAD LAMPIRAN ITEM
                    $files = $request->file("po_items.{$itemId}.attachments");
                    if (!empty($files)) {
                        foreach (is_array($files) ? $files : [$files] as $file) {
                            if ($file instanceof \Illuminate\Http\UploadedFile) {
                                $path = $file->storeAs($storagePath, "item_{$poItem->item_id}_" . uniqid() . time() . "." . $file->extension(), 'public');
                                \Illuminate\Support\Facades\DB::table('purchase_order_item_attachments')->insert([
                                    'purchase_order_item_id' => $poItem->id,
                                    'file_name'              => $file->getClientOriginalName(),
                                    'file_path'              => str_replace('\\', '/', $path),
                                    'created_at'             => now(), 'updated_at' => now()
                                ]);
                            }
                        }
                    }

                    $poSubtotalGross += $gross;
                    $poTotalItemDiscount += $discAmt;
                    $poTotalTax += $taxAmt;

                    $poItem->update([
                        'item_name'       => $itemData['item_name_override'] ?? $poItem->item_name,
                        'uom_id'          => $itemData['uom_id'] ?? $poItem->uom_id,
                        'uom'             => $itemData['uom'] ?? $poItem->uom,
                        'description'     => $itemData['notes'] ?? $poItem->description,
                        'vendor_id'       => $itemData['vendor_id'] ?? $poItem->vendor_id,
                        'tax_id'          => null,
                        'qty_ordered'     => $newQty,
                        'unit_price'      => $price,
                        'discount_type'   => $discType,
                        'discount_value'  => $discVal,
                        'discount_amount' => $discAmt,
                        'subtotal'        => $dpp,
                        'tax_amount'      => $taxAmt,
                    ]);
                }

                // 2. UPLOAD LAMPIRAN HEADER (KODE NYASAR SUDAH DIBERSIHKAN DARI SINI)
                if ($request->hasFile('header_attachments')) {
                    foreach ($request->file('header_attachments') as $file) {
                        if ($file instanceof \Illuminate\Http\UploadedFile) {
                            $path = $file->storeAs($storagePath, time() . '_' . uniqid() . '_' . str_replace(' ', '_', $file->getClientOriginalName()), 'public');
                            \Illuminate\Support\Facades\DB::table('purchase_order_attachments')->insert([
                                'purchase_order_id' => $po->id,
                                'file_name'          => $file->getClientOriginalName(),
                                'file_path'          => str_replace('\\', '/', $path),
                                'created_at'         => now(), 'updated_at' => now()
                            ]);
                        }
                    }
                }

                // 3. KALKULASI DISKON & PAJAK GLOBAL
                $globalDiscType = strtoupper($request->global_discount_type ?? 'FIXED');
                $globalDiscVal = (float) ($request->global_discount_value ?? 0);
                $poGlobalDiscount = ($globalDiscType === 'PERCENT') ? (($poSubtotalGross - $poTotalItemDiscount) * ($globalDiscVal / 100)) : $globalDiscVal;

                $dppAfterGlobalDisc = ($poSubtotalGross - $poTotalItemDiscount) - $poGlobalDiscount;

                $globalTaxType = strtoupper($request->global_tax_type ?? 'FIXED');
                $globalTaxVal = (float) ($request->global_tax_value ?? 0);
                $poGlobalTax = ($globalTaxType === 'PERCENT') ? ($dppAfterGlobalDisc * ($globalTaxVal / 100)) : $globalTaxVal;

                \Illuminate\Support\Facades\DB::table('purchase_order_charges')->where('purchase_order_id', $po->id)->delete();
                $poChargeTotal = 0;
                if ($request->has('charges')) {
                    foreach ($request->charges as $charge) {
                        if (!empty($charge['amount'])) {
                            \Illuminate\Support\Facades\DB::table('purchase_order_charges')->insert([
                                'purchase_order_id' => $po->id, 'name' => $charge['charge_type_id'], 'amount' => $charge['amount'], 'created_at' => now(), 'updated_at' => now()
                            ]);
                            $poChargeTotal += $charge['amount'];
                        }
                    }
                }

                \Illuminate\Support\Facades\DB::table('purchase_order_discounts')->where('purchase_order_id', $po->id)->delete();
                $poExtraDiscountTotal = 0;
                if ($request->has('extra_discounts')) {
                    foreach ($request->extra_discounts as $disc) {
                        if (!empty($disc['amount'])) {
                            \Illuminate\Support\Facades\DB::table('purchase_order_discounts')->insert([
                                'purchase_order_id' => $po->id, 'name' => $disc['discount_type_id'], 'amount' => $disc['amount'], 'created_at' => now(), 'updated_at' => now()
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
                    'tax_total'             => $poTotalTax + $poGlobalTax,
                    'charge_total'          => $poChargeTotal,
                    'grand_total'           => $dppAfterGlobalDisc + $poTotalTax + $poGlobalTax + $poChargeTotal - $poExtraDiscountTotal,
                ]);

                $prItemIds = $po->items->pluck('purchase_request_item_id')->filter()->unique()->toArray();
                if (!empty($prItemIds)) {
                    foreach($prItemIds as $pid) {
                        $this->recalculatePrItemFulfillment($pid);
                    }
                    $this->checkAndUpdatePrStatus($po->purchase_request_id);
                }

                // ====================================================================
                // 🔥 4. LOGIKA OVERRIDE WORKFLOW (UPDATE MATRIKS PERSINJIAN HINGGA TUNTAS) 🔥
                // ====================================================================
                $customWorkflowId = $request->input('custom_workflow_id');
                $needsApproval = false;

                // Wajib! Hapus matriks/persetujuan lama sebelum memasang matriks baru hasil revisi
                \App\Models\DocumentApproval::where('document_id', $po->id)
                    ->whereIn('document_type', [get_class($po), 'App\Models\PurchaseOrder', 'PO', 'PurchaseOrder'])
                    ->delete();

                if ($customWorkflowId) {
                    // A. Jalur Khusus Dilihat Dari Pilihan Dropdown Form
                    $workflow = \App\Models\ApprovalWorkflow::with('steps')->find($customWorkflowId);

                    if ($workflow && $workflow->steps->count() > 0) {
                        foreach ($workflow->steps as $step) {
                            $targetDept = $step->target_department_id ?? $step->department_id ?? null;

                            \App\Models\DocumentApproval::create([
                                'document_id'          => $po->id,
                                'document_type'        => get_class($po),
                                'role_id'              => $step->role_id,
                                'target_department_id' => $targetDept,
                                'step_order'           => $step->step_order,
                                'status'               => 'PENDING'
                            ]);
                        }
                        $needsApproval = true;
                        $this->logHistory($po->id, 'SYSTEM', "Menggunakan Rute Persetujuan Khusus (Update): " . $workflow->name);
                    }
                } else {
                    // B. Jalur Standar
                    $needsApproval = \App\Services\ApprovalService::generateWorkflow($po);
                    if ($needsApproval) {
                        $this->logHistory($po->id, 'SYSTEM', 'Rute persetujuan (Workflow) PO telah di-reset menyesuaikan data revisi (Standar Departemen).');
                    }
                }

                // UPDATE STATUS PO BERDASARKAN HASIL MATRIKS
                if ($needsApproval) {
                    $pendingStatus = \App\Models\Status::whereIn('slug', ['pending_approval', 'pending'])->first()->id ?? 1;
                    $po->update(['status_id' => $pendingStatus]);
                } else {
                    $approvedStatus = \App\Models\Status::where('slug', 'approved')->first()->id ?? 3;
                    $po->update(['status_id' => $approvedStatus]);
                    $this->logHistory($po->id, 'APPROVED', 'PO Auto-Approved karena tidak ada aturan aktif atau nominal di bawah batas.');
                }

                $this->logHistory($po->id, 'PO Direvisi', 'Perubahan telah disimpan.');

            });

            return redirect()->route('po.show', $slug)->with('success', 'Perubahan Purchase Order berhasil disimpan!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal menyimpan perubahan: ' . $e->getMessage());
        }
    }


    // =========================================================================
    // 6. EDIT PO PAGE
    // =========================================================================
    public function edit($slug)
    {
        $po = \App\Models\PurchaseOrder::with(['items.item.itemUoms', 'vendor', 'status', 'attachments'])->where('po_number',$slug)->firstOrFail();

        if (!in_array(strtolower(optional($po->status)->slug ?? ''), ['draft', 'pending_approval', 'pending', 'rejected', ''])) {
            return redirect()->route('po.index')->with('error', 'Gagal: PO ini sudah tidak dapat diedit karena statusnya ' . optional($po->status)->name);
        }

        $vendors = \App\Models\Vendor::all();
        $companies = \App\Models\Company::all();
        $paymentTerms = \App\Models\PaymentTerm::all();
        $taxes = \App\Models\Tax::all();
        $chargeTypes = \App\Models\ChargeType::where('is_active', 1)->get();
        $currencies = \App\Models\Currency::all();
        $discountTypes = \App\Models\DiscountType::where('is_active', 1)->get();
        $charges = \Illuminate\Support\Facades\DB::table('purchase_order_charges')->where('purchase_order_id',$po->id)->get();
        $extraDiscounts = \Illuminate\Support\Facades\DB::table('purchase_order_discounts')->where('purchase_order_id',$po->id)->get();

        $poItemIds =$po->items->pluck('id')->toArray();
        $itemAttachments = \Illuminate\Support\Facades\DB::table('purchase_order_item_attachments')->whereIn('purchase_order_item_id',$poItemIds)->get();
        foreach ($po->items as $item) {
            $item->raw_attachments = $itemAttachments->where('purchase_order_item_id',$item->id)->values();
        }

        // =========================================================================
        // 🔥 1. TARIK HANYA MATRIKS PO (SANGAT KETAT) 🔥
        // =========================================================================
        $customWorkflows = [];
        $selectedWorkflowId = null;

        if (class_exists('\App\Models\ApprovalWorkflow')) {
            $customWorkflows = \App\Models\ApprovalWorkflow::with('steps')
                ->where('is_active', true)
                ->whereIn('document_type', ['PO', 'App\Models\PurchaseOrder', 'PurchaseOrder'])
                ->get();

            // =========================================================================
            // 🔥 2. LOGIKA DETEKTIF UNTUK PO (Baca dari Tabel History PO/Umum) 🔥
            // =========================================================================

            // Cek di tabel History Umum dulu (kalau ada)
            $historyLog = \App\Models\History::where('record_id', $po->id)
                ->whereIn('record_type', [get_class($po), 'App\Models\PurchaseOrder', 'PO', 'PurchaseOrder'])
                ->where('action', 'SYSTEM')
                ->where(function($q) {
                    $q->where('note', 'like', 'Menggunakan Rute Persetujuan Khusus:%')
                      ->orWhere('note', 'like', 'Menggunakan Rute Persetujuan Khusus (Update):%');
                })
                ->orderBy('id', 'desc')
                ->first();

            // Jika tidak ketemu di tabel umum, cek di tabel PurchaseOrderHistory
            if (!$historyLog && class_exists('\App\Models\PurchaseOrderHistory')) {
                $historyLog = \App\Models\PurchaseOrderHistory::where('purchase_order_id', $po->id)
                    ->where('action', 'SYSTEM')
                    ->where(function($q) {
                        $q->where('note', 'like', 'Menggunakan Rute Persetujuan Khusus:%')
                          ->orWhere('note', 'like', 'Menggunakan Rute Persetujuan Khusus (Update):%');
                    })
                    ->orderBy('id', 'desc')
                    ->first();
            }

            if ($historyLog) {
                // Ekstrak nama matriks dari teks
                $workflowName = trim(str_replace(['Menggunakan Rute Persetujuan Khusus:', 'Menggunakan Rute Persetujuan Khusus (Update):'], '', $historyLog->note));

                // Cari ID-nya berdasarkan nama tersebut
                $matchedWorkflow = $customWorkflows->where('name', $workflowName)->first();
                if ($matchedWorkflow) {
                    $selectedWorkflowId = $matchedWorkflow->id;
                }
            }

            // Fallback: Jika di history benar-benar tidak ada, cocokkan jumlah step/jabatannya
            if (!$selectedWorkflowId) {
                $currentApprovals = \App\Models\DocumentApproval::where('document_id', $po->id)
                    ->whereIn('document_type', [get_class($po), 'App\Models\PurchaseOrder', 'PO', 'PurchaseOrder'])
                    ->orderBy('step_order', 'asc')
                    ->get();

                if ($currentApprovals->count() > 0 && $customWorkflows->count() > 0) {
                    foreach ($customWorkflows as $cw) {
                        $cwSteps = $cw->steps->sortBy('step_order')->values();
                        if ($cwSteps->count() === $currentApprovals->count() && $cwSteps->count() > 0) {
                            $isMatch = true;
                            foreach ($cwSteps as $index => $step) {
                                if ($step->role_id != $currentApprovals[$index]->role_id) {
                                    $isMatch = false; break;
                                }
                            }
                            if ($isMatch) {
                                $selectedWorkflowId = $cw->id; break;
                            }
                        }
                    }
                }
            }
        }

        return view('po.edit', compact(
            'po', 'vendors', 'companies', 'paymentTerms', 'taxes', 'chargeTypes',
            'currencies', 'charges', 'discountTypes', 'extraDiscounts',
            'customWorkflows', 'selectedWorkflowId'
        ));
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
    // HELPER: PERBAIKI STATUS PR OTOMATIS (MENDUKUNG PARSIAL QTY & PARSIAL ITEM)
    // =========================================================================
    private function checkAndUpdatePrStatus($prId)
    {
        $prRecord = \App\Models\PurchaseRequest::with('items')->find($prId);
        if (!$prRecord) return;

        // Cek jika status PR sudah Batal/Ditolak/Selesai/Closed, abaikan update
        $currentSlug = strtolower(optional($prRecord->status)->slug);
        if (in_array($currentSlug, ['cancelled', 'rejected', 'completed', 'closed'])) {
            return;
        }

        $anyItemProcessed = false;
        $allItemFulfilled = true;
        $validItemsCount  = 0;

        foreach ($prRecord->items as $item) {
            // Abaikan item jika status itemnya ditolak/dibatalkan oleh manajemen
            $itemStatus = strtoupper($item->status ?? '');
            if (in_array($itemStatus, ['REJECTED', 'CANCELED', 'CANCELLED'])) {
                continue;
            }

            $validItemsCount++;
            $targetQty = (float) $item->qty;
            $currentOrdered = (float) ($item->ordered_qty ?? 0);

            // Jika ada minimal 1 qty yang sudah di-PO-kan
            if (round($currentOrdered, 4) > 0) {
                $anyItemProcessed = true;
            }

            // Jika ada barang yang jumlah PO-nya masih kurang dari jumlah PR
            if (round($currentOrdered, 4) < round($targetQty, 4)) {
                $allItemFulfilled = false;
            }
        }

        // Jika semua item di dalam PR ditolak, biarkan saja (jangan diubah)
        if ($validItemsCount === 0) return;

        // =====================================================================
        // 🔥 LOGIKA PENENTUAN STATUS AKHIR PR 🔥
        // =====================================================================
        $statusApproved = \App\Models\Status::where('type', 'PR')->where('slug', 'approved')->first();
        $statusPartial  = \App\Models\Status::where('type', 'PR')->where('slug', 'partial_po')->first();
        $statusPoIssued = \App\Models\Status::where('type', 'PR')->where('slug', 'po_issued')->first();

        if (!$anyItemProcessed) {
            // Skenario 1: Belum ada yang dipesan sama sekali (Kondisi Awal)
            if ($statusApproved) $prRecord->update(['status_id' => $statusApproved->id]);

        } elseif (!$allItemFulfilled) {
            // Skenario 2: Dipesan sebagian (Bisa Parsial Item atau Parsial Qty) -> PR TETAP HIDUP!
            if ($statusPartial) $prRecord->update(['status_id' => $statusPartial->id]);

        } else {
            // Skenario 3: Semua barang dan kuantitas sudah lunas dibuatkan PO
            if ($statusPoIssued) $prRecord->update(['status_id' => $statusPoIssued->id]);
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
    // public function edit($slug)
    // {
    //     $po = \App\Models\PurchaseOrder::with(['items.item.itemUoms', 'vendor', 'status', 'attachments'])->where('po_number',$slug)->firstOrFail();

    //     if (!in_array(strtolower(optional($po->status)->slug ?? ''), ['draft', 'pending_approval', 'pending', 'rejected', ''])) {
    //         return redirect()->route('po.index')->with('error', 'Gagal: PO ini sudah tidak dapat diedit karena statusnya ' . optional($po->status)->name);
    //     }

    //     $vendors = \App\Models\Vendor::all();
    //     $companies = \App\Models\Company::all();
    //     $paymentTerms = \App\Models\PaymentTerm::all();
    //     $taxes = \App\Models\Tax::all();
    //     $chargeTypes = \App\Models\ChargeType::where('is_active', 1)->get();
    //     $currencies = \App\Models\Currency::all();
    //     $discountTypes = \App\Models\DiscountType::where('is_active', 1)->get();
    //     $charges = \Illuminate\Support\Facades\DB::table('purchase_order_charges')->where('purchase_order_id',$po->id)->get();
    //     $extraDiscounts = \Illuminate\Support\Facades\DB::table('purchase_order_discounts')->where('purchase_order_id',$po->id)->get();

    //     $poItemIds =$po->items->pluck('id')->toArray();
    //     $itemAttachments = \Illuminate\Support\Facades\DB::table('purchase_order_item_attachments')->whereIn('purchase_order_item_id',$poItemIds)->get();
    //     foreach ($po->items as $item) {
    //         $item->raw_attachments = $itemAttachments->where('purchase_order_item_id',$item->id)->values();
    //     }

    //     // =========================================================================
    //     // 🔥 1. TARIK HANYA MATRIKS PO (SANGAT KETAT) 🔥
    //     // =========================================================================
    //     $customWorkflows = [];
    //     $selectedWorkflowId = null;

    //     if (class_exists('\App\Models\ApprovalWorkflow')) {
    //         $customWorkflows = \App\Models\ApprovalWorkflow::with('steps')
    //             ->where('is_active', true)
    //             ->whereIn('document_type', ['PO', 'App\Models\PurchaseOrder', 'PurchaseOrder'])
    //             ->get();

    //         // =========================================================================
    //         // 🔥 2. LOGIKA DETEKTIF UNTUK PO (Baca dari Tabel History PO) 🔥
    //         // =========================================================================
    //         $historyLog = \App\Models\PurchaseOrderHistory::where('purchase_order_id', $po->id)
    //             ->where('action', 'SYSTEM')
    //             ->where('note', 'like', 'Menggunakan Rute Persetujuan Khusus:%')
    //             ->orderBy('id', 'desc')
    //             ->first();

    //         if ($historyLog) {
    //             $workflowName = trim(str_replace('Menggunakan Rute Persetujuan Khusus:', '', $historyLog->note));
    //             $matchedWorkflow = $customWorkflows->where('name', $workflowName)->first();
    //             if ($matchedWorkflow) {
    //                 $selectedWorkflowId = $matchedWorkflow->id;
    //             }
    //         }

    //         // Fallback: Jika di history tidak ada, cocokkan jumlah step/jabatannya
    //         if (!$selectedWorkflowId) {
    //             $currentApprovals = \App\Models\DocumentApproval::where('document_id', $po->id)
    //                 ->whereIn('document_type', [get_class($po), 'App\Models\PurchaseOrder', 'PO', 'PurchaseOrder'])
    //                 ->orderBy('step_order', 'asc')
    //                 ->get();

    //             if ($currentApprovals->count() > 0 && $customWorkflows->count() > 0) {
    //                 foreach ($customWorkflows as $cw) {
    //                     $cwSteps = $cw->steps->sortBy('step_order')->values();
    //                     if ($cwSteps->count() === $currentApprovals->count() && $cwSteps->count() > 0) {
    //                         $isMatch = true;
    //                         foreach ($cwSteps as $index => $step) {
    //                             if ($step->role_id != $currentApprovals[$index]->role_id) {
    //                                 $isMatch = false; break;
    //                             }
    //                         }
    //                         if ($isMatch) {
    //                             $selectedWorkflowId = $cw->id; break;
    //                         }
    //                     }
    //                 }
    //             }
    //         }
    //     }

    //     return view('po.edit', compact(
    //         'po', 'vendors', 'companies', 'paymentTerms', 'taxes', 'chargeTypes',
    //         'currencies', 'charges', 'discountTypes', 'extraDiscounts',
    //         'customWorkflows', 'selectedWorkflowId'
    //     ));
    // }

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
            'approvals.approver',
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
                                $fpdi = new \setasign\Fpdi\Fpdi();
                                $fpdi->setSourceFile($finalFilePath);
                                $oMerger->addPDF($finalFilePath, 'all');
                            } catch (\Exception $e) {
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
    // 8. HALAMAN INDEX PO & 🔥 VISIBILITAS APPROVAL (STRICT PRIVACY PO) 🔥
    // =========================================================================
    public function index(\Illuminate\Http\Request $request)
    {
        $search = $request->input('search');
        $user = auth()->user();
        $userRoleIds = $user->roles->pluck('id')->toArray();

        // --- TARIK ANTREAN PR (PR BEBAS DILIHAT OLEH YANG PUNYA AKSES KE HALAMAN INI) ---
        $statusApproved = \App\Models\Status::where('type', 'PR')->where('slug', 'approved')->first();
        $statusPartial  = \App\Models\Status::where('type', 'PR')->where('slug', 'partial_po')->first();

        $statusIds = array_filter([$statusApproved ? $statusApproved->id : null, $statusPartial ? $statusPartial->id : null]);

        $prQuery = \App\Models\PurchaseRequest::with(['user', 'status', 'company'])
            ->whereIn('status_id', $statusIds)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('pr_number', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($qUser) use ($search) { $qUser->where('name', 'like', "%{$search}%"); })
                      ->orWhereHas('company', function ($qComp) use ($search) { $qComp->where('name', 'like', "%{$search}%"); })
                      ->orWhereHas('items.vendorQuotes.vendor', function ($qVendor) use ($search) { $qVendor->where('name', 'like', "%{$search}%"); });
                });
            });

        // 🔥 PERBAIKAN FATAL: Gembok PR Dilepas Total. Siapapun yang bisa buka menu PO, boleh melihat antrean PR untuk diproses. 🔥
        $readyPrs = $prQuery->orderBy('created_at', 'desc')->paginate(10, ['*'], 'pr_page');

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

        // =========================================================================
        // 🔥 LOGIKA VISIBILITAS PO (ANTI INTIP TETAP AKTIF KHUSUS UNTUK PO) 🔥
        // =========================================================================
        if (!$user->hasAnyRole(['Super Admin', 'super-admin', 'Super Administrator'])) {
            $poQuery->where(function ($q) use ($user, $userRoleIds) {

                // 1. MUTLAK: Hanya boleh lihat jika DIA YANG MEMBUAT PO
                $q->where('created_by', $user->id)

                  // 2. MUTLAK: Boleh lihat jika sedang ANTRI DI MEJANYA (DITUJUKAN KEPADANYA)
                  ->orWhereHas('approvals', function ($qApprovals) use ($user, $userRoleIds) {
                      $qApprovals->where('status', 'PENDING')
                                 ->whereIn('role_id', $userRoleIds)
                                 ->where(function($qDept) use ($user) {
                                     // Hanya jika departemennya cocok dengan yang diminta matriks
                                     $qDept->where('target_department_id', $user->department_id)
                                           ->orWhere('target_department_id', 'all')
                                           ->orWhereNull('target_department_id');
                                 });
                  })

                  // 3. MUTLAK: Boleh lihat jika DIA SUDAH PERNAH MENYETUJUI dokumen tersebut (Sebagai Jejak Audit historis)
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

        // 🔥 PERBAIKAN: Hanya izinkan slug yang ada di database Komandan 🔥
        if (!in_array($prStatusSlug, ['approved', 'partial_po'])) {
            $prStatusText = strtoupper(trim(optional($pr->status)->name ?? $pr->status ?? ''));
            $isAllowed = false;
            foreach (['APPROVED', 'PARSIAL', 'DISETUJUI'] as $keyword) {
                if (str_contains($prStatusText, $keyword)) {
                    $isAllowed = true;
                    break;
                }
            }
            if (!$isAllowed) {
                return redirect()->back()->with('error', 'Akses Ditolak: PR ini berstatus "' . $prStatusText . '". Hanya PR yang Disetujui/Parsial yang bisa dibuatkan PO.');
            }
        }

        // =========================================================================
        // 🔥 KODE YANG HILANG: Tarik riwayat PO Parsial dari PR ini 🔥
        // =========================================================================
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

        // 🔥 TARIK DATA MATRIKS KHUSUS PO 🔥
        $customWorkflows = [];
        if (class_exists('\App\Models\ApprovalWorkflow')) {
            $customWorkflows = \App\Models\ApprovalWorkflow::with('steps')
                ->where('is_active', true)
                ->whereIn('document_type', [
                    'PO',
                    'App\Models\PurchaseOrder',
                    'PurchaseOrder'
                ])
                ->get();
        }

        // 🔥 PASTIKAN $customWorkflows DIKIRIM KE COMPACT 🔥
        return view('po.process_pr', compact('pr', 'existingPos', 'vendors', 'companies', 'paymentTerms', 'taxes', 'chargeTypes', 'currencies', 'discountTypes', 'selectedVendor', 'defaultCurrency', 'defaultShippingAddress', 'customWorkflows'));
    }

    // =========================================================================
    // 10. MENGAJUKAN PERSETUJUAN PO (DENGAN DAYA INGAT HISTORY & NOTIFIKASI)
    // =========================================================================
    public function submitApproval($slug)
    {
        DB::beginTransaction();
        try {
            $po = \App\Models\PurchaseOrder::where('po_number', $slug)->firstOrFail();
            $statusPending = \App\Models\Status::where('type', 'PO')->where('slug', 'pending_approval')->first();

            if ($statusPending) $po->update(['status_id' => $statusPending->id]);

            \App\Models\DocumentApproval::where('document_id', $po->id)->where('document_type', get_class($po))->delete();

            // 🔥 LOGIKA DETEKTIF: CARI TAHU MATRIKS APA YANG DIPILIH SAAT CREATE/EDIT 🔥
            $workflow = null;

            // 1. Cek riwayat untuk melihat apakah user memilih jalur khusus
            $historyLog = \App\Models\PurchaseOrderHistory::where('purchase_order_id', $po->id)
                ->where('action', 'SYSTEM')
                ->where(function($q) {
                    $q->where('note', 'like', 'Menggunakan Rute Persetujuan Khusus:%')
                      ->orWhere('note', 'like', 'Menggunakan Rute Persetujuan Khusus (Update):%');
                })
                ->orderBy('id', 'desc')
                ->first();

            if ($historyLog) {
                // Ekstrak nama matriks dari teks History
                $workflowName = trim(str_replace(['Menggunakan Rute Persetujuan Khusus:', 'Menggunakan Rute Persetujuan Khusus (Update):'], '', $historyLog->note));
                $workflow = \DB::table('approval_workflows')->where('name', $workflowName)->where('is_active', 1)->first();
            }

            // 2. Jika tidak ketemu di history (artinya User memilih Default saat create)
            if (!$workflow) {
                $workflow = \DB::table('approval_workflows')
                    ->whereIn('document_type', [get_class($po), 'PO', 'PurchaseOrder'])
                    ->where('is_active', 1)
                    ->whereNull('department_id') // Mencari aturan default/umum
                    ->orderBy('id', 'asc')
                    ->first();
            }

            if (!$workflow) throw new \Exception("Workflow Persetujuan untuk PO belum diaktifkan di Master Matriks!");

            $steps = \DB::table('approval_workflow_steps')
                ->where('approval_workflow_id', $workflow->id)
                ->where('min_amount', '<=', $po->grand_total)
                ->orderBy('step_order', 'asc')
                ->get();

            if ($steps->isEmpty()) throw new \Exception('Langkah Persetujuan (Steps) belum diatur untuk nominal PO ini.');

            $firstStepRoleId = null;
            $firstStepTargetDept = null;

            foreach ($steps as $index => $step) {
                // Simpan data langkah pertama untuk tembak notifikasi
                if ($index === 0) {
                    $firstStepRoleId = $step->role_id;
                    $firstStepTargetDept = $step->target_department_id ?? $step->department_id ?? null;
                }

                // 🔥 TEMBUS PAKSA FILLABLE LARAVEL MENGGUNAKAN DB::table 🔥
                $targetDept = $step->target_department_id ?? $step->department_id ?? null;
                \Illuminate\Support\Facades\DB::table('document_approvals')->insert([
                    'document_id'          => $po->id,
                    'document_type'        => get_class($po),
                    'role_id'              => $step->role_id,
                    'target_department_id' => $targetDept,
                    'step_order'           => $step->step_order,
                    'status'               => 'PENDING',
                    'created_at'           => now(),
                    'updated_at'           => now()
                ]);
            }

            // ====================================================================
            // 🔥 FITUR BARU: TEMBAK NOTIFIKASI KE MANAGER SAAT PO DIAJUKAN 🔥
            // ====================================================================
            if ($firstStepRoleId) {
                // Cari User yang memiliki Role sesuai $firstStep->role_id
                $targetManagers = \App\Models\User::whereHas('roles', function ($q) use ($firstStepRoleId) {
                    $q->where('id', $firstStepRoleId);
                })->get();

                // Filter berdasarkan target departemen
                if (!empty($firstStepTargetDept) && $firstStepTargetDept !== 'all') {
                    $targetManagers = $targetManagers->where('department_id', $firstStepTargetDept);
                } elseif (empty($firstStepTargetDept)) {
                    // Atasan Langsung
                    $pembuatPo = \App\Models\User::find($po->created_by);
                    if ($pembuatPo) {
                        $targetManagers = $targetManagers->where('department_id', $pembuatPo->department_id);
                    }
                }

                $urlNotifManager = route('po.show', $po->po_number);
                $pesanNotif = "Ada Dokumen PO Nomor {$po->po_number} yang membutuhkan persetujuan Anda.";

                foreach ($targetManagers as $manager) {
                    if (class_exists('\App\Notifications\DocumentApprovalNotification')) {
                        $manager->notify(new \App\Notifications\DocumentApprovalNotification('PO PENDING APPROVAL', $pesanNotif, $urlNotifManager));
                    } else {
                        \Illuminate\Support\Facades\DB::table('notifications')->insert([
                            'id'              => \Illuminate\Support\Str::uuid(),
                            'type'            => 'App\Notifications\DocumentApprovalNotification',
                            'notifiable_type' => 'App\Models\User',
                            'notifiable_id'   => $manager->id,
                            'data'            => json_encode([
                                'title'   => 'Antrean Persetujuan Baru',
                                'message' => $pesanNotif,
                                'url'     => $urlNotifManager
                            ]),
                            'read_at'         => null,
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ]);
                    }
                }
            }

            $this->logHistory($po->id, 'Diajukan Ulang', 'Dokumen PO diajukan menggunakan matriks: **' . $workflow->name . '**');
            DB::commit();
            return redirect()->route('po.show', $slug)->with('success', 'PO Berhasil diajukan menggunakan jalur: ' . $workflow->name);
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

                // ====================================================================
                // 🔥 FITUR BARU: TEMBAK NOTIFIKASI KE PEMBUAT PO SAAT FINAL 🔥
                // ====================================================================
                $pembuatPo = \App\Models\User::find($po->created_by);
                if ($pembuatPo) {
                    // Pastikan URL menggunakan PO NUMBER, bukan ID
                    $urlPo = route('po.show', $po->po_number);
                    $pesanNotif = "Hore! PO Nomor {$po->po_number} telah DISETUJUI SECARA FINAL dan siap diproses ke Vendor.";

                    // Jika Komandan punya class DocumentApprovalNotification
                    if (class_exists('\App\Notifications\DocumentApprovalNotification')) {
                        $pembuatPo->notify(new \App\Notifications\DocumentApprovalNotification('PO APPROVED', $pesanNotif, $urlPo));
                    } else {
                        // Jalur tembak langsung ke database bawaan Laravel (Fallback)
                        \Illuminate\Support\Facades\DB::table('notifications')->insert([
                            'id'              => \Illuminate\Support\Str::uuid(),
                            'type'            => 'App\Notifications\DocumentApprovalNotification',
                            'notifiable_type' => 'App\Models\User',
                            'notifiable_id'   => $pembuatPo->id,
                            'data'            => json_encode([
                                'title'   => 'PO Disetujui Final 🎉',
                                'message' => $pesanNotif,
                                'url'     => $urlPo
                            ]),
                            'read_at'         => null,
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ]);
                    }
                }
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

    public function printBprWithAttachments($slug)
    {
        $po = \App\Models\PurchaseOrder::with([
            'items.item', 'company', 'user', 'vendor', 'attachments', 'purchaseRequest'
        ])->where('po_number', $slug)->firstOrFail();

        // 🔥 TAMBAHAN WAJIB: Panggil data Biaya & Diskon agar muncul di PDF BPR 🔥
        $charges = \DB::table('purchase_order_charges')->where('purchase_order_id', $po->id)->get();
        $extraDiscounts = \DB::table('purchase_order_discounts')->where('purchase_order_id', $po->id)->get();

        $poItemIds = $po->items->pluck('id')->toArray();
        $itemAttachments = \DB::table('purchase_order_item_attachments')
                            ->whereIn('purchase_order_item_id', $poItemIds)
                            ->get();

        foreach ($po->items as $item) {
            $item->raw_attachments = $itemAttachments->where('purchase_order_item_id', $item->id)->values();
        }

        // 🔥 Jangan lupa selipkan variabel $charges dan $extraDiscounts di fungsi compact() 🔥
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('po.print_bpr_detail', compact('po', 'charges', 'extraDiscounts'))
                ->setPaper('A4', 'portrait');

        $tempMainPdfPath = storage_path('app/temp_po_bpr_' . uniqid() . '.pdf');
        $pdf->save($tempMainPdfPath);

        $merger = new \iio\libmergepdf\Merger();
        $merger->addFile($tempMainPdfPath);

        if ($po->attachments) {
            foreach ($po->attachments as $attachment) {
                $ext = strtolower(pathinfo($attachment->file_path, PATHINFO_EXTENSION));
                if ($ext === 'pdf') {
                    $pdfPath = public_path('storage/' . $attachment->file_path);
                    if (file_exists($pdfPath)) $merger->addFile($pdfPath);
                }
            }
        }

        foreach ($po->items as $item) {
            if (isset($item->raw_attachments) && count($item->raw_attachments) > 0) {
                foreach ($item->raw_attachments as $attachment) {
                    $ext = strtolower(pathinfo($attachment->file_name ?? $attachment->file_path, PATHINFO_EXTENSION));
                    if ($ext === 'pdf') {
                        $pdfPath = public_path('storage/' . $attachment->file_path);
                        if (file_exists($pdfPath)) $merger->addFile($pdfPath);
                    }
                }
            }
        }

        $mergedPdfData = $merger->merge();

        if (file_exists($tempMainPdfPath)) {
            unlink($tempMainPdfPath);
        }

        $filename = 'BPR_PO_' . str_replace('/', '_', $po->po_number) . '.pdf';

        return response($mergedPdfData)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    // =========================================================================
    // 🔥 FUNGSI HAPUS LAMPIRAN HEADER PO 🔥
    // =========================================================================
    public function deleteHeaderAttachment($id)
    {
        $attachment = \Illuminate\Support\Facades\DB::table('purchase_order_attachments')->where('id', $id)->first();
        if ($attachment) {
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($attachment->file_path)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->file_path);
            }
            \Illuminate\Support\Facades\DB::table('purchase_order_attachments')->where('id', $id)->delete();
        }
        return back()->with('success', 'File Lampiran Header berhasil dihapus.');
    }

    // =========================================================================
    // 🔥 FUNGSI HAPUS LAMPIRAN ITEM PO 🔥
    // =========================================================================
    public function deleteItemAttachment($id)
    {
        $attachment = \Illuminate\Support\Facades\DB::table('purchase_order_item_attachments')->where('id', $id)->first();
        if ($attachment) {
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($attachment->file_path)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->file_path);
            }
            \Illuminate\Support\Facades\DB::table('purchase_order_item_attachments')->where('id', $id)->delete();
        }
        return back()->with('success', 'File Lampiran Item berhasil dihapus.');
    }



    // =========================================================================
    // 🔥 1. HALAMAN LAPORAN OUTSTANDING PO (FIXED STATUS_ID) 🔥
    // =========================================================================
    public function outstanding(\Illuminate\Http\Request $request)
    {
        $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        $companyId = $request->input('company_id');
        $vendorId = $request->input('vendor_id');
        $search = $request->input('search');

        $start = \Carbon\Carbon::parse($startDate)->startOfDay();
        $end = \Carbon\Carbon::parse($endDate)->endOfDay();

        $companies = \App\Models\Company::orderBy('name')->get();
        $vendors = \App\Models\Vendor::orderBy('name')->get();

        // 🔥 PERBAIKAN: Ambil ID dari tabel 'statuses' untuk status PO yang masih menggantung
        // Mengacu pada gambar, slug yang menggantung adalah: issued, partial_received, partial_receipt
        $outstandingStatusIds = \Illuminate\Support\Facades\DB::table('statuses')
            ->where('type', 'PO')
            ->whereIn('slug', ['issued', 'partial_received', 'partial_receipt'])
            ->pluck('id')
            ->toArray();

        // 🔥 PERBAIKAN: Gunakan whereIn('status_id', ...) bukan whereIn('status', ...)
        $query = \App\Models\PurchaseOrder::with(['items.item', 'vendor', 'company'])
                    ->whereIn('status_id', $outstandingStatusIds)
                    ->whereBetween('created_at', [$start, $end]);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($vendorId) {
            $query->where('vendor_id', $vendorId);
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                  ->orWhereHas('items', function($qItems) use ($search) {
                      $qItems->where('item_name', 'like', "%{$search}%")
                             ->orWhereHas('item', function($qMaster) use ($search) {
                                 $qMaster->where('name', 'like', "%{$search}%")
                                         ->orWhere('code', 'like', "%{$search}%");
                             });
                  });
            });
        }

        $outstandingPos = $query->orderBy('created_at', 'asc')->paginate(20)->withQueryString();

        foreach ($outstandingPos as $po) {
            $po->total_qty_ordered = $po->items->sum('qty_ordered');
            $po->total_qty_received = $po->items->sum('qty_received');
            $po->qty_sisa = $po->total_qty_ordered - $po->total_qty_received;
        }

        return view('po.outstanding', compact('outstandingPos', 'startDate', 'endDate', 'companyId', 'vendorId', 'search', 'companies', 'vendors'));
    }



    // =========================================================================
    // 🔥 2. FUNGSI EKSEKUSI FORCE CLOSE (DENGAN LOG RIWAYAT/HISTORY) 🔥
    // =========================================================================
    public function forceClose(\Illuminate\Http\Request $request, $slug)
    {
        $po = \App\Models\PurchaseOrder::where('po_number', $slug)->firstOrFail();

        // Cek apakah PO sudah memiliki status final
        $finalStatusIds = \Illuminate\Support\Facades\DB::table('statuses')
            ->where('type', 'PO')
            ->whereIn('slug', ['completed', 'rejected', 'cancelled', 'canceled', 'fully_received', 'closed_short'])
            ->pluck('id')
            ->toArray();

        if (in_array($po->status_id, $finalStatusIds)) {
            return redirect()->back()->with('error', 'Gagal! Purchase Order ini sudah memiliki status Final.');
        }

        // Cari ID status khusus 'closed_short'
        $closedShortStatus = \Illuminate\Support\Facades\DB::table('statuses')
            ->where('type', 'PO')
            ->where('slug', 'closed_short')
            ->first();

        // Update status_id ke ID Closed Short (Jika belum buat di DB, fallback ke completed)
        if ($closedShortStatus) {
            $po->status_id = $closedShortStatus->id;
        } else {
            // Fallback darurat jika Komandan belum sempat input di database
            $fallbackStatus = \Illuminate\Support\Facades\DB::table('statuses')->where('type', 'PO')->where('slug', 'completed')->first();
            if ($fallbackStatus) $po->status_id = $fallbackStatus->id;
        }

        $alasan = $request->input('reason', 'Ditutup paksa tanpa alasan.');

        // 1. Menambahkan log ke teks Catatan PO
        $catatan = "\n\n=== FORCE CLOSED PADA " . now()->translatedFormat('d M Y H:i') . " ===\n";
        $catatan .= "Oleh: " . (auth()->user()->name ?? 'Sistem') . "\n";
        $catatan .= "Alasan: " . $alasan;
        $po->notes = $po->notes . $catatan;

        // Simpan perubahan ke tabel PO
        $po->save();

        // 🔥 2. TAMBAHAN BARU: MENYUNTIKKAN LOG KE TABEL RIWAYAT (TIMELINE) 🔥
        if (method_exists($po, 'histories')) {
            $po->histories()->create([
                'user_id' => auth()->id(),
                'action'  => 'Force Close PO',
                'note'    => "Dokumen ditutup paksa (Closed Short).\n**Alasan:** " . $alasan
            ]);
        }

        return redirect()->back()->with('success', "Purchase Order {$po->po_number} berhasil ditutup paksa (Closed Short)!");
    }




    // =========================================================================
    // 🔥 FUNGSI BARU: HALAMAN CREATE DIRECT PO (TANPA PR) 🔥
    // =========================================================================
    public function createDirect()
    {
        $vendors = \App\Models\Vendor::orderBy('name')->get();
        $companies = \App\Models\Company::orderBy('name')->get();
        $paymentTerms = \App\Models\PaymentTerm::orderBy('days')->get();
        $taxes = \App\Models\Tax::where('is_active', true)->orderBy('percent')->get();
        $chargeTypes = \App\Models\ChargeType::where('is_active', true)->get();
        $currencies = \App\Models\Currency::where('is_active', true)->get();
        $discountTypes = \App\Models\DiscountType::where('is_active', true)->get();

        // Tarik Master Item beserta konversi UOM-nya
        $masterItems = \App\Models\Item::with('itemUoms')->orderBy('name')->get();

        $headOffice = \App\Models\Company::where('is_head_office', true)->first();
        $defaultShippingAddress = $headOffice ? $headOffice->address : '';

        // Tarik Matriks Khusus PO
        $customWorkflows = [];
        if (class_exists('\App\Models\ApprovalWorkflow')) {
            $customWorkflows = \App\Models\ApprovalWorkflow::with('steps')
                ->where('is_active', true)
                ->whereIn('document_type', ['PO', 'App\Models\PurchaseOrder', 'PurchaseOrder'])
                ->get();
        }

        return view('po.create_direct', compact(
            'vendors', 'companies', 'paymentTerms', 'taxes', 'chargeTypes',
            'currencies', 'discountTypes', 'masterItems', 'defaultShippingAddress', 'customWorkflows'
        ));
    }

   // =========================================================================
    // 🔥 FUNGSI BARU: PROSES SIMPAN DIRECT PO (TANPA PR) 🔥
    // =========================================================================
    public function storeDirect(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'vendor_id'          => 'required|exists:vendors,id',
            'billing_company_id' => 'required|exists:companies,id',
            'payment_term_id'    => 'required|exists:payment_terms,id',
            'po_date'            => 'required|date',
            'delivery_date'      => 'required|date',
            'po_items'           => 'required|array|min:1',
        ]);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($request) {

                $newPoNumber = $this->generatePoNumber($request->billing_company_id);
                $storagePath = (\Illuminate\Support\Facades\DB::table('system_settings')->where('setting_key', 'path_po_attachment')->value('setting_value') ?: 'attachments/purchase_orders') . '/' . str_replace(['/', '\\'], '-', $newPoNumber);
                $paymentTermName = \App\Models\PaymentTerm::find($request->payment_term_id)->name ?? null;

                $poSubtotalGross = 0; $poTotalItemDiscount = 0; $poTotalTaxItem = 0;
                $processedLineItems = [];

                // 1. HITUNG BARIS ITEM
                foreach ($request->po_items as $index => $itemData) {
                    $qty = (float) ($itemData['qty'] ?? 0);
                    $price = (float) ($itemData['unit_price'] ?? 0);
                    if ($qty <= 0) continue;

                    $gross = $qty * $price;
                    $discVal = (float) ($itemData['discount_value'] ?? 0);
                    $discType = strtoupper($itemData['discount_type'] ?? 'FIXED');
                    $discAmt = ($discType === 'PERCENT') ? ($gross * $discVal / 100) : $discVal;
                    $dpp = $gross - $discAmt;

                    $taxVal = (float) ($itemData['tax_value'] ?? 0);
                    $taxType = strtoupper($itemData['tax_type'] ?? 'FIXED');
                    $taxAmt = ($taxType === 'PERCENT') ? ($dpp * $taxVal / 100) : $taxVal;

                    $poSubtotalGross += $gross;
                    $poTotalItemDiscount += $discAmt;
                    $poTotalTaxItem += $taxAmt;

                    $processedLineItems[] = [
                        'itemData' => $itemData, 'discAmt' => $discAmt, 'dpp' => $dpp,
                        'taxType' => $taxType, 'taxVal' => $taxVal, 'taxAmt' => $taxAmt, 'qty' => $qty, 'price' => $price,
                        'discType' => $discType, 'discVal' => $discVal, 'originalIndex' => $index
                    ];
                }

                if (empty($processedLineItems)) throw new \Exception('Minimal harus ada 1 barang yang valid (Kuantitas > 0).');

                // 2. HITUNG GLOBAL DISKON, PAJAK & BIAYA TAMBAHAN
                $globalDiscType = strtoupper($request->global_discount_type ?? 'FIXED');
                $globalDiscVal = (float) ($request->global_discount_value ?? 0);
                $poGlobalDiscount = ($globalDiscType === 'PERCENT') ? (($poSubtotalGross - $poTotalItemDiscount) * ($globalDiscVal / 100)) : $globalDiscVal;

                $dppAfterGlobalDisc = ($poSubtotalGross - $poTotalItemDiscount) - $poGlobalDiscount;

                $globalTaxType = strtoupper($request->global_tax_type ?? 'FIXED');
                $globalTaxVal = (float) ($request->global_tax_value ?? 0);
                $poGlobalTax = ($globalTaxType === 'PERCENT') ? ($dppAfterGlobalDisc * ($globalTaxVal / 100)) : $globalTaxVal;

                // 🔥 PERBAIKAN: LOGIKA CHARGES YANG TERTINDIH SUDAH DIKEMBALIKAN 🔥
                $poChargeTotal = 0; $appliedCharges = [];
                if ($request->has('charges')) {
                    foreach ($request->charges as $charge) {
                        if (!empty($charge['amount'])) {
                            $poChargeTotal += (float)$charge['amount'];
                            $appliedCharges[] = $charge;
                        }
                    }
                }

                $poExtraDiscountTotal = 0; $appliedExtraDiscs = [];
                if ($request->has('extra_discounts')) {
                    foreach ($request->extra_discounts as $disc) {
                        if (!empty($disc['amount'])) {
                            $poExtraDiscountTotal += (float)$disc['amount'];
                            $appliedExtraDiscs[] = $disc;
                        }
                    }
                }

                $totalAllDiscounts = $poTotalItemDiscount + $poGlobalDiscount;
                $totalAllTaxes = $poTotalTaxItem + $poGlobalTax;
                $poGrandTotal = $dppAfterGlobalDisc + $totalAllTaxes + $poChargeTotal - $poExtraDiscountTotal;

                // 3. SIMPAN HEADER PO
                $po = \App\Models\PurchaseOrder::create([
                    'po_number'             => $newPoNumber,
                    'purchase_request_id'   => null, // Murni Direct PO
                    'vendor_id'             => $request->vendor_id,
                    'bill_to_company_id'    => $request->billing_company_id,
                    'status_id'             => 1,
                    'po_date'               => $request->po_date ?? now(),
                    'created_by'            => auth()->id(),
                    'currency'              => $request->currency ?? 'IDR',
                    'shipping_address'      => $request->shipping_address,
                    'payment_terms'         => $paymentTermName,
                    'notes'                 => $request->notes,
                    'delivery_date'         => $request->delivery_date,
                    'global_discount_type'  => $globalDiscType,
                    'global_discount_value' => $globalDiscVal,
                    'global_tax_type'       => $globalTaxType,
                    'global_tax_value'      => $globalTaxVal,
                    'subtotal'              => $poSubtotalGross,
                    'discount_total'        => $totalAllDiscounts,
                    'tax_total'             => $totalAllTaxes,
                    'charge_total'          => $poChargeTotal,
                    'grand_total'           => $poGrandTotal,
                ]);

                // 4. SIMPAN BIAYA & DISKON EXTRA
                foreach ($appliedCharges as $charge) {
                    \Illuminate\Support\Facades\DB::table('purchase_order_charges')->insert([
                        'purchase_order_id' => $po->id, 'name' => $charge['charge_type_id'], 'amount' => $charge['amount'], 'created_at' => now(), 'updated_at' => now()
                    ]);
                }
                foreach ($appliedExtraDiscs as $disc) {
                    \Illuminate\Support\Facades\DB::table('purchase_order_discounts')->insert([
                        'purchase_order_id' => $po->id, 'name' => $disc['discount_type_id'], 'amount' => $disc['amount'], 'created_at' => now(), 'updated_at' => now()
                    ]);
                }

                // 5. SIMPAN LAMPIRAN HEADER
                if ($request->hasFile('header_attachments')) {
                    foreach ($request->file('header_attachments') as $file) {
                        if ($file instanceof \Illuminate\Http\UploadedFile) {
                            $path = $file->storeAs($storagePath, time() . '_' . uniqid() . '_' . str_replace(' ', '_', $file->getClientOriginalName()), 'public');
                            \Illuminate\Support\Facades\DB::table('purchase_order_attachments')->insert([
                                'purchase_order_id' => $po->id, 'file_name' => $file->getClientOriginalName(), 'file_path' => str_replace('\\', '/', $path), 'created_at' => now(), 'updated_at' => now()
                            ]);
                        }
                    }
                }

                // 6. SIMPAN BARIS ITEM
                foreach ($processedLineItems as $line) {
                    $itemData = $line['itemData'];
                    $masterItem = \App\Models\Item::find($itemData['item_id']);

                    $newPoItem = \App\Models\PurchaseOrderItem::create([
                        'purchase_order_id'        => $po->id,
                        'item_id'                  => $itemData['item_id'],
                        'item_name'                => $itemData['item_name_override'] ?? ($masterItem->name ?? '-'),
                        'purchase_request_item_id' => null,
                        'uom_id'                   => $itemData['uom_id'] ?? null,
                        'uom'                      => $itemData['uom'] ?? ($masterItem->unit ?? 'PCS'),
                        'description'              => $itemData['notes'] ?? '-',
                        'tax_id'                   => null,
                        'qty_ordered'              => $line['qty'],
                        'unit_price'               => $line['price'],
                        'discount_type'            => $line['discType'],
                        'discount_value'           => $line['discVal'],
                        'discount_amount'          => $line['discAmt'],
                        'tax_type'                 => $line['taxType'],
                        'tax_value'                => $line['taxVal'],
                        'tax_amount'               => $line['taxAmt'],
                        'subtotal'                 => $line['dpp'],
                    ]);

                    $files = $request->file("po_items.{$line['originalIndex']}.attachments");
                    if (!empty($files)) {
                        foreach (is_array($files) ? $files : [$files] as $file) {
                            if ($file instanceof \Illuminate\Http\UploadedFile) {
                                $path = $file->storeAs($storagePath, "item_{$itemData['item_id']}_" . uniqid() . time() . "." . $file->extension(), 'public');
                                \Illuminate\Support\Facades\DB::table('purchase_order_item_attachments')->insert([
                                    'purchase_order_item_id' => $newPoItem->id, 'file_name' => $file->getClientOriginalName(), 'file_path' => str_replace('\\', '/', $path), 'created_at' => now(), 'updated_at' => now()
                                ]);
                            }
                        }
                    }
                }

                $this->logHistory($po->id, 'CREATED', "Direct PO (Tanpa PR) berhasil dibuat.");

                // 7. JALANKAN MATRIKS APPROVAL
                $customWorkflowId = $request->input('custom_workflow_id');
                $needsApproval = false;

                if ($customWorkflowId) {
                    $workflow = \App\Models\ApprovalWorkflow::with('steps')->find($customWorkflowId);
                    if ($workflow && $workflow->steps->count() > 0) {
                        foreach ($workflow->steps as $step) {
                            // 🔥 TEMBUS PAKSA FILLABLE LARAVEL MENGGUNAKAN DB::table 🔥
                            $targetDept = $step->target_department_id ?? $step->department_id ?? null;
                            \Illuminate\Support\Facades\DB::table('document_approvals')->insert([
                                'document_id'          => $po->id,
                                'document_type'        => get_class($po),
                                'role_id'              => $step->role_id,
                                'target_department_id' => $targetDept,
                                'step_order'           => $step->step_order,
                                'status'               => 'PENDING',
                                'created_at'           => now(),
                                'updated_at'           => now()
                            ]);
                        }
                        $needsApproval = true;
                        $this->logHistory($po->id, 'SYSTEM', "Menggunakan Rute Persetujuan Khusus: " . $workflow->name);
                    }
                } else {
                    $needsApproval = \App\Services\ApprovalService::generateWorkflow($po);
                    if ($needsApproval) {
                        $this->logHistory($po->id, 'SYSTEM', 'Masuk antrean persetujuan (Workflow Standar).');
                    }
                }

                if ($needsApproval) {
                    $pendingStatusId = \App\Models\Status::where('type', 'PO')->whereIn('slug', ['pending_approval', 'pending'])->first()->id ?? 1;
                    $po->update(['status_id' => $pendingStatusId]);
                } else {
                    $approvedStatusId = \App\Models\Status::where('type', 'PO')->where('slug', 'approved')->first()->id ?? 3;
                    $po->update(['status_id' => $approvedStatusId]);
                    $this->logHistory($po->id, 'APPROVED', 'PO Auto-Approved karena tidak ada aturan matriks aktif.');
                }
            });

            return redirect()->route('po.index')->with('success', 'Direct PO berhasil diterbitkan!');

        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal memproses pembuatan PO: ' . $e->getMessage());
        }
    }



    // =========================================================================
    // 🔥 FUNGSI KHUSUS: UPDATE INVOICE & REKENING TANPA MERESET APPROVAL 🔥
    // =========================================================================
    public function updateBillingInfo(\Illuminate\Http\Request $request, $slug)
    {
        try {
            $po = \App\Models\PurchaseOrder::where('po_number', $slug)->firstOrFail();
            
            // Simpan data
            $po->update([
                'invoice_number' => $request->input('invoice_number'),
                'account_number' => $request->input('account_number'),
            ]);

            // Catat di riwayat agar transparan
            $this->logHistory($po->id, 'Info Tagihan Diupdate', 'Nomor Invoice atau Rekening Vendor telah diperbarui untuk keperluan pembayaran.');

            return back()->with('success', 'Informasi Invoice & Rekening berhasil disimpan!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menyimpan info tagihan: ' . $e->getMessage());
        }
    }


    // =========================================================================
    // 🔥 FUNGSI CETAK BPR DETAIL (RINCIAN BIAYA & DISKON) 🔥
    // =========================================================================
    public function printBprDetail($slug)
    {

        $po = \App\Models\PurchaseOrder::with([
            'items.item', 'company', 'user', 'vendor', 'attachments', 'purchaseRequest'
        ])->where('po_number', $slug)->firstOrFail();

        // 🔥 TAMBAHAN WAJIB: Panggil data Biaya & Diskon agar muncul di PDF BPR 🔥
        $charges = \DB::table('purchase_order_charges')->where('purchase_order_id', $po->id)->get();
        $extraDiscounts = \DB::table('purchase_order_discounts')->where('purchase_order_id', $po->id)->get();

        $poItemIds = $po->items->pluck('id')->toArray();
        $itemAttachments = \DB::table('purchase_order_item_attachments')
                            ->whereIn('purchase_order_item_id', $poItemIds)
                            ->get();

        foreach ($po->items as $item) {
            $item->raw_attachments = $itemAttachments->where('purchase_order_item_id', $item->id)->values();
        }

        // 🔥 Jangan lupa selipkan variabel $charges dan $extraDiscounts di fungsi compact() 🔥
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('po.print_bpr_detail', compact('po', 'charges', 'extraDiscounts'))
                ->setPaper('A4', 'portrait');

        $tempMainPdfPath = storage_path('app/temp_po_bpr_' . uniqid() . '.pdf');
        $pdf->save($tempMainPdfPath);

        $merger = new \iio\libmergepdf\Merger();
        $merger->addFile($tempMainPdfPath);

        if ($po->attachments) {
            foreach ($po->attachments as $attachment) {
                $ext = strtolower(pathinfo($attachment->file_path, PATHINFO_EXTENSION));
                if ($ext === 'pdf') {
                    $pdfPath = public_path('storage/' . $attachment->file_path);
                    if (file_exists($pdfPath)) $merger->addFile($pdfPath);
                }
            }
        }

        foreach ($po->items as $item) {
            if (isset($item->raw_attachments) && count($item->raw_attachments) > 0) {
                foreach ($item->raw_attachments as $attachment) {
                    $ext = strtolower(pathinfo($attachment->file_name ?? $attachment->file_path, PATHINFO_EXTENSION));
                    if ($ext === 'pdf') {
                        $pdfPath = public_path('storage/' . $attachment->file_path);
                        if (file_exists($pdfPath)) $merger->addFile($pdfPath);
                    }
                }
            }
        }

        $mergedPdfData = $merger->merge();

        if (file_exists($tempMainPdfPath)) {
            unlink($tempMainPdfPath);
        }

        $filename = 'BPR_PO_' . str_replace('/', '_', $po->po_number) . '.pdf';

        return response($mergedPdfData)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }
    


}
