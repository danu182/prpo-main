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
// use App\Models\User;
// use App\Notifications\DocumentApprovalNotification;


// ==========================================
// AMUNISI NOTIFIKASI DITAMBAHKAN DI SINI
// ==========================================
use App\Models\User;
use App\Notifications\DocumentApprovalNotification;

class PurchaseOrderController extends Controller
{
    // 1. Tampilkan Item yang SIAP di-PO (Untuk dipilih Procurement)
    public function getItemsForPo()
    {
        // Ambil Item PR yang SUDAH APPROVED tapi BELUM habis dipesan
        // Ini inti dari fitur SPLIT PO
        $pendingItems = PurchaseRequestItem::whereHas('purchaseRequest', function($q){
                            $q->where('status', 'APPROVED');
                        })
                        ->whereRaw('qty > ordered_qty') // Logic Sisa Qty
                        ->with(['item', 'purchaseRequest'])
                        ->get();

        $vendors = \App\Models\Vendor::all();
        $companies = \App\Models\Company::all(); // Untuk Bill To

        return view('po.create', compact('pendingItems', 'vendors', 'companies'));
    }

    // 1. TAMPILKAN FORM DRAFT PO (Pre-filled dari data PR)
    public function createFromPr($pr_id, $vendor_id = null)
    {
        // 1. Ambil Data PR Awal
        $pr = PurchaseRequest::with(['company', 'user'])->findOrFail($pr_id);

        // 2. Filter Item Berdasarkan Status APPROVED dan Vendor (Jika Ada)
        if ($vendor_id) {
            $pr->load(['items' => function($query) use ($vendor_id) {
                $query->where('status', 'APPROVED')
                      ->whereHas('vendorQuotes', function($q) use ($vendor_id) {
                          $q->where('vendor_id', $vendor_id);
                      })
                      ->with(['item', 'vendorQuotes' => function($q) use ($vendor_id) {
                          // Pastikan hanya menarik harga spesifik dari vendor ini
                          $q->where('vendor_id', $vendor_id);
                      }]);
            }]);
        } else {
            // Fallback: Ambil semua item yang APPROVED saja
            $pr->load(['items' => function($query) {
                $query->where('status', 'APPROVED')->with(['item', 'vendorQuotes']);
            }]);
        }

        // 3. Tentukan Vendor Terpilih (Untuk Dropdown & Harga)
        $selectedVendor = null;
        if ($vendor_id) {
            $selectedVendor = \App\Models\Vendor::find($vendor_id);
        } else {
            $firstQuote = $pr->items->flatMap->vendorQuotes->first();
            $selectedVendor = $firstQuote ? $firstQuote->vendor : null;
        }

        // 4. Kalkulasi Sisa Qty & Buang Item yang Sudah Habis Dipesan
        $validItems = collect(); // Koleksi baru untuk item yang benar-benar siap di-PO

        foreach ($pr->items as $item) {
            // Memastikan pembacaan dari database akurat (sesuai nama kolom Anda)
            // Coba pakai ordered_qty, jika null pakai qty_ordered, jika tetap null pakai 0
            $ordered = $item->ordered_qty ?? ($item->qty_ordered ?? 0);

            // Hitung sisa
            $remaining = max(0, $item->qty - $ordered);
            $item->remaining_qty = $remaining;

            // HANYA masukkan item ke daftar final jika masih ada sisa barang untuk dipesan!
            if ($item->remaining_qty > 0) {
                $validItems->push($item);
            }
        }

        // Timpa koleksi items bawaan dengan koleksi valid yang sudah difilter
        $pr->setRelation('items', $validItems);

        // 5. Cek Apakah Masih Ada Barang Tersisa
        if ($pr->items->isEmpty()) {
            return back()->with('error', 'Semua barang dari vendor ini sudah selesai dibuatkan PO, atau tidak ada yang disetujui.');
        }

        // 6. Deteksi Mata Uang Default
        $defaultCurrency = 'IDR';
        if ($selectedVendor) {
            $firstItem = $pr->items->first();
            if ($firstItem) {
                $quote = $firstItem->vendorQuotes->where('vendor_id', $selectedVendor->id)->first();
                if ($quote && !empty($quote->currency)) {
                    $defaultCurrency = $quote->currency;
                }
            }
        }

        // 7. Ambil Data Master & Alamat
        $vendors     = \App\Models\Vendor::orderBy('name')->get();
        $all_items   = \App\Models\Item::orderBy('name')->get();
        $companies   = \App\Models\Company::orderBy('name')->get();

        $defaultShippingAddress = $pr->items->first()->delivery_address ?? null;
        if (empty($defaultShippingAddress)) {
            if ($pr->company && !empty($pr->company->address)) {
                $defaultShippingAddress = $pr->company->address;
            } else {
                $headOffice = \App\Models\Company::where('is_head_office', true)->first();
                $defaultShippingAddress = $headOffice ? $headOffice->address : '';
            }
        }

        $taxes        = \App\Models\Tax::where('is_active', true)->orderBy('percent')->get();
        $currencies   = \App\Models\Currency::where('is_active', true)->get();
        $chargeTypes  = \App\Models\ChargeType::where('is_active', true)->orderBy('name')->get();
        $paymentTerms = \App\Models\PaymentTerm::where('is_active', true)->orderBy('days')->get();

        return view('po.create_from_pr', compact(
            'pr', 'selectedVendor', 'vendors', 'all_items', 'companies',
            'taxes', 'currencies', 'chargeTypes', 'defaultCurrency',
            'paymentTerms', 'defaultShippingAddress'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $pendingItems = \App\Models\PurchaseRequestItem::whereHas('purchaseRequest', function($q){
                            $q->where('status', 'APPROVED');
                        })
                        ->whereRaw('qty > ordered_qty') // Logic Sisa Qty
                        ->with(['item', 'purchaseRequest'])
                        ->get();

        $vendors = \App\Models\Vendor::all();
        $companies = \App\Models\Company::all(); // Untuk Bill To

        return view('po.create', compact('pendingItems', 'vendors', 'companies'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
       // 1. Validasi dasar diperketat
        $request->validate([
            'billing_company_id'   => 'required|exists:companies,id',
            'payment_term_id'      => 'required|exists:payment_terms,id',
            'po_items'             => 'required|array',
            'po_items.*.vendor_id' => 'required|integer', // <--- Mencegah vendor kosong masuk ke DB
        ], [
            'po_items.*.vendor_id.required' => 'Gagal: Anda harus memilih Vendor Aktual untuk semua barang!',
            'po_items.*.vendor_id.integer'  => 'Gagal: Vendor yang dipilih tidak valid.'
        ]);

        DB::beginTransaction();
        try {
            // 2. Generate Nomor PO Otomatis
            $romawi = ["I","II","III","IV","V","VI","VII","VIII","IX","X","XI","XII"];
            $month = (int) date('m');
            $year = date('Y');
            $lastPo = PurchaseOrder::whereYear('created_at', $year)->max('id');
            $nextNo = str_pad(($lastPo + 1), 4, '0', STR_PAD_LEFT);
            $poNumber = "PO/{$year}/" . $romawi[$month - 1] . "/{$nextNo}";

            // 3. Ambil Nama Termin Pembayaran
            $paymentTermName = null;
            if ($request->payment_term_id) {
                $term = \App\Models\PaymentTerm::find($request->payment_term_id);
                $paymentTermName = $term ? $term->name : $request->payment_term_id;
            }

            // 4. Cari Status Awal PO (Pending Approval)
            $statusPending = \App\Models\Status::where('type', 'PO')->where('slug', 'pending_approval')->first();

            // Ambil alamat dari input manual atau Company (Bill To)
            $shippingAddress = $request->shipping_address;
            if (empty($shippingAddress)) {
                $company = \App\Models\Company::find($request->bill_to_company_id);
                $shippingAddress = $company ? $company->address : 'Alamat kantor belum disetting.';
            }

            // Simpan Header PO
            $po = PurchaseOrder::create([
                'po_number'          => $poNumber,
                'purchase_request_id'=> $request->pr_id,
                'vendor_id'          => $request->vendor_id,
                'bill_to_company_id' => $request->bill_to_company_id,
                'status_id'          => $statusPending->id ?? null,
                'po_date'            => $request->po_date,
                'delivery_date'      => $request->delivery_date,
                'due_date'           => $request->due_date,
                'payment_terms'      => $paymentTermName,
                'notes'              => $request->notes,
                'currency'           => $request->currency,
                'created_by'         => auth()->id(),
                'is_sent'            => false,
                'subtotal'           => 0,
                'tax_total'          => 0,
                'discount_total'     => 0,
                'grand_total'        => 0,
                'shipping_address'   => $shippingAddress,
            ]);


            // Ambil Nama Vendor untuk keperluan Log
            $vendorName = \App\Models\Vendor::find($request->vendor_id)->name ?? 'Vendor';

            // 9. CATAT RIWAYAT KE PR
            \App\Models\PurchaseRequestHistory::create([
                'purchase_request_id' => $request->pr_id,
                'user_id' => auth()->id(),
                'action' => 'PO GENERATED',
                'note' => "Berhasil menerbitkan PO Nomor: **{$po->po_number}**\nVendor: **{$vendorName}**\n\nItem yang diproses dalam PO ini akan dikunci dari penawaran vendor lain.",
            ]);

            $grandSubtotal = 0;
            $grandTax = 0;

            $inputItems = $request->input('items', []);

            // 5. Simpan Item PO & Update PR Item
            foreach ($inputItems as $itemData) {
                // Skip jika item qty nya 0 atau kosong
                $qty = (float) ($itemData['qty'] ?? 0);
                if ($qty <= 0) continue;

                $price = (float) $itemData['price'];
                $grossTotal = $qty * $price;

                // --- A. Hitung Diskon Item ---
                $discType = $itemData['discount_type'] ?? 'FIXED';
                $discVal  = (float) ($itemData['discount_value'] ?? 0);
                $discAmount = ($discType == 'PERCENT') ? ($grossTotal * $discVal / 100) : $discVal;

                // --- B. Hitung Pajak Item ---
                $taxId = $itemData['tax_id'] ?? null;
                $taxAmount = 0;
                $taxBase = $grossTotal - $discAmount;

                if ($taxId) {
                    $tax = \App\Models\Tax::find($taxId);
                    if ($tax) {
                        $taxAmount = ($taxBase * $tax->percent) / 100;
                    }
                }

                $lineTotal = $taxBase + $taxAmount;

                // --- C. Simpan ke Tabel purchase_order_items ---
                $masterItem = \App\Models\Item::find($itemData['item_id']);

                PurchaseOrderItem::create([
                    'purchase_order_id'        => $po->id,
                    'item_id'                  => $itemData['item_id'],
                    'purchase_request_item_id' => $itemData['pr_item_id'] ?? null,
                    'qty_ordered'              => $qty,
                    'qty_received'             => 0,
                    'unit_price'               => $price,
                    'uom'                      => $masterItem->unit ?? 'Unit',
                    'description'              => $masterItem->name ?? '-',
                    'discount_amount'          => $discAmount,
                    'tax_amount'               => $taxAmount,
                    'tax_id'                   => $taxId,
                    'notes'                    => $itemData['notes'] ?? null,
                    'subtotal'                 => $lineTotal,
                    'discount_type'            => $discType,
                    'discount_value'           => $discVal,
                ]);

                // --- D. Update Sisa Qty di PR (Partial PO Logic) ---
                if (!empty($itemData['pr_item_id'])) {
                    $prItem = \App\Models\PurchaseRequestItem::find($itemData['pr_item_id']);
                    if ($prItem) {
                        $currentOrdered = $prItem->ordered_qty ?? 0;
                        $prItem->ordered_qty = $currentOrdered + $qty;
                        $prItem->save();
                    }
                }

                $grandSubtotal += ($grossTotal - $discAmount);
                $grandTax += $taxAmount;
            }

            // 6. Simpan Biaya Tambahan (Charges)
            $totalCharges = 0;
            if ($request->has('charges_name')) {
                foreach ($request->charges_name as $idx => $name) {
                    $amount = (float) ($request->charges_amount[$idx] ?? 0);
                    if ($name && $amount > 0) {
                        DB::table('purchase_order_charges')->insert([
                            'purchase_order_id' => $po->id,
                            'name'              => $name,
                            'amount'            => $amount,
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ]);
                        $totalCharges += $amount;
                    }
                }
            }

            // 7. Hitung Global Discount & Grand Total
            $globalDiscType = $request->global_discount_type ?? 'FIXED';
            $globalDiscVal  = (float) ($request->global_discount_value ?? 0);
            $globalDiscAmount = ($globalDiscType == 'PERCENT') ? ($grandSubtotal * $globalDiscVal / 100) : $globalDiscVal;

            $grandTotal = ($grandSubtotal - $globalDiscAmount) + $grandTax + $totalCharges;

            // 8. Update Header PO Final
            $po->update([
                'subtotal'       => $grandSubtotal,
                'tax_total'      => $grandTax,
                'discount_total' => $globalDiscAmount,
                'grand_total'    => $grandTotal
            ]);

            // ==========================================================
            // 9. UPDATE STATUS PR -> "PO TERBIT" ATAU TETAP "APPROVED" (SPLIT PO)
            // ==========================================================
            $pr = PurchaseRequest::with('items')->find($request->pr_id);

            if ($pr) {
                // Cek apakah semua item di PR yang disetujui sudah terpenuhi 100%?
                $allFulfilled = true;

                foreach($pr->items as $item) {
                    if ($item->status === 'APPROVED') {
                        $orderedQty = $item->ordered_qty ?? 0;
                        if ($orderedQty < $item->qty) {
                            $allFulfilled = false;
                            break;
                        }
                    }
                }

                if ($allFulfilled) {
                    $statusTarget = \App\Models\Status::where('type', 'PR')->where('slug', 'po_issued')->first();
                    if ($statusTarget) {
                        $pr->update(['status_id' => $statusTarget->id]);
                    }
                } else {
                    $statusTarget = \App\Models\Status::where('type', 'PR')->where('slug', 'partial_po')->first();
                    if ($statusTarget) {
                        $pr->update(['status_id' => $statusTarget->id]);
                    }
                }
            }

            DB::commit();
            return redirect()->route('po.show', $po->id)->with('success', 'Purchase Order berhasil diterbitkan!');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal membuat PO: ' . $e->getMessage())->withInput();
        }
    }

    public function edit($id)
    {
        $po = \App\Models\PurchaseOrder::with(['items.item', 'vendor', 'status', 'attachments'])->findOrFail($id);

        if (!in_array(optional($po->status)->slug, ['draft', 'pending_approval'])) {
            return redirect()->route('po.show', $id)->with('error', 'Gagal: PO ini sudah tidak dapat diedit karena statusnya sudah ' . optional($po->status)->name);
        }

        $vendors = \App\Models\Vendor::all();
        $companies = \App\Models\Company::all();
        $paymentTerms = \App\Models\PaymentTerm::all();
        $taxes = \App\Models\Tax::all();
        $chargeTypes = \App\Models\ChargeType::where('is_active', 1)->get();
        $currencies = \App\Models\Currency::all();
        $discountTypes = \App\Models\DiscountType::where('is_active', 1)->get();

        $charges = DB::table('purchase_order_charges')->where('purchase_order_id', $id)->get();
        $extraDiscounts = DB::table('purchase_order_discounts')->where('purchase_order_id', $id)->get();

        return view('po.edit', compact('po', 'vendors', 'companies', 'paymentTerms', 'taxes', 'chargeTypes', 'currencies', 'charges', 'discountTypes', 'extraDiscounts'));
    }

    public function update(Request $request, $id)
    {
        // 1. Validasi dasar
        $request->validate([
            'billing_company_id'        => 'required|exists:companies,id',
            'payment_term_id'           => 'required|exists:payment_terms,id',
            'po_items'                  => 'required|array',
            'po_items.*.attachments.*'  => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',
            'delivery_date'             => 'required|date',
        ]);

        try {
            DB::transaction(function () use ($request, $id) {
                $po = \App\Models\PurchaseOrder::with('items')->findOrFail($id);

                if (!in_array(optional($po->status)->slug, ['draft', 'pending_approval'])) {
                    throw new \Exception('PO sudah diproses dan tidak bisa diedit lagi.');
                }

                $paymentTermName = null;
                if ($request->payment_term_id) {
                    $term = \App\Models\PaymentTerm::find($request->payment_term_id);
                    $paymentTermName = $term ? $term->name : null;
                }

                $poSubtotalGross = 0;
                $poTotalItemDiscount = 0;
                $poTotalTax = 0;

                $safePoNumber = str_replace('/', '-', $po->po_number);

                // 1. UPDATE ITEM BARANG & PROSES UPLOAD LAMPIRAN BARU
                foreach ($request->po_items as $itemId => $itemData) {
                    $poItem = \App\Models\PurchaseOrderItem::find($itemId);
                    if (!$poItem) continue;

                    $newQty = (float) ($itemData['qty'] ?? 0);

                    // PROTEKSI QTY ORDER TIDAK MELEBIHI QTY PR
                    $prItem = \App\Models\PurchaseRequestItem::with('item')->find($poItem->purchase_request_item_id);
                    if ($prItem) {
                        $qtyAlreadyOrderedElsewhere = ($prItem->ordered_qty ?? 0) - $poItem->qty_ordered;
                        $maxAllowedQty = $prItem->qty - $qtyAlreadyOrderedElsewhere;

                        if ($newQty > $maxAllowedQty) {
                            $itemName = $prItem->item->name ?? 'Item';
                            throw new \Exception("Gagal: Kuantitas '{$itemName}' ({$newQty}) melebihi batas maksimal yang diizinkan ({$maxAllowedQty}).");
                        }

                        $qtyDifference = $newQty - $poItem->qty_ordered;
                        if ($qtyDifference != 0) {
                            $prItem->ordered_qty = ($prItem->ordered_qty ?? 0) + $qtyDifference;
                            $prItem->save();
                        }
                    }

                    $price = (float) ($itemData['unit_price'] ?? 0);
                    $gross = $newQty * $price;

                    $discVal = (float) ($itemData['discount_value'] ?? 0);
                    $discType = $itemData['discount_type'] ?? 'fixed';
                    $discAmt = ($discType === 'percent') ? ($gross * ($discVal / 100)) : $discVal;

                    $dpp = $gross - $discAmt;

                    $taxAmt = 0;
                    $taxId = $itemData['tax_id'] ?? null;
                    if (!empty($taxId)) {
                        $tax = \App\Models\Tax::find($taxId);
                        if ($tax) $taxAmt = $dpp * ($tax->percent / 100);
                    }

                    // TANGKAP FILE UPLOAD BARU
                    if (isset($itemData['attachments']) && is_array($itemData['attachments'])) {
                        foreach ($itemData['attachments'] as $file) {
                            if (is_file($file) && $file->isValid()) {
                                $originalName = $file->getClientOriginalName();
                                $filename = time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName);
                                $path = 'po_attachments/' . $safePoNumber . '/' . $filename;
                                Storage::disk('public')->put($path, file_get_contents($file));

                                \App\Models\PurchaseOrderAttachment::create([
                                    'purchase_order_id' => $po->id,
                                    'file_name'         => $originalName,
                                    'file_path'         => $path,
                                ]);
                            }
                        }
                    }

                    $poSubtotalGross += $gross;
                    $poTotalItemDiscount += $discAmt;
                    $poTotalTax += $taxAmt;

                    $poItem->update([
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

                // 2. KALKULASI DISKON GLOBAL
                $globalDiscType = $request->global_discount_type ?? 'fixed';
                $globalDiscVal = (float) ($request->global_discount_value ?? 0);
                $poGlobalDiscount = ($globalDiscType === 'percent')
                                    ? (($poSubtotalGross - $poTotalItemDiscount) * ($globalDiscVal / 100))
                                    : $globalDiscVal;

                $poTotalDiscount = $poTotalItemDiscount + $poGlobalDiscount;

                // 3. UPDATE BIAYA TAMBAHAN
                \DB::table('purchase_order_charges')->where('purchase_order_id', $po->id)->delete();
                $poChargeTotal = 0;

                if ($request->has('charges')) {
                    foreach ($request->charges as $charge) {
                        if (!empty($charge['charge_type_id']) && !empty($charge['amount'])) {
                            $chargeMaster = \App\Models\ChargeType::find($charge['charge_type_id']);
                            $amt = (float) $charge['amount'];

                            \DB::table('purchase_order_charges')->insert([
                                'purchase_order_id' => $po->id,
                                'name'              => $chargeMaster ? $chargeMaster->name : 'Biaya Tambahan',
                                'amount'            => $amt,
                                'created_at'        => now(),
                                'updated_at'        => now(),
                            ]);
                            $poChargeTotal += $amt;
                        }
                    }
                }

                // 4. UPDATE POTONGAN TAMBAHAN
                \DB::table('purchase_order_discounts')->where('purchase_order_id', $po->id)->delete();
                $poExtraDiscountTotal = 0;

                if ($request->has('extra_discounts')) {
                    foreach ($request->extra_discounts as $disc) {
                        if (!empty($disc['discount_type_id']) && !empty($disc['amount'])) {
                            $discMaster = \App\Models\DiscountType::find($disc['discount_type_id']);
                            $amt = (float) $disc['amount'];

                            \DB::table('purchase_order_discounts')->insert([
                                'purchase_order_id' => $po->id,
                                'name'              => $discMaster ? $discMaster->name : 'Potongan Tambahan',
                                'amount'            => $amt,
                                'created_at'        => now(),
                                'updated_at'        => now(),
                            ]);
                            $poExtraDiscountTotal += $amt;
                        }
                    }
                }

                $poGrandTotal = ($poSubtotalGross - $poTotalDiscount) + $poTotalTax + $poChargeTotal - $poExtraDiscountTotal;

                // 5. UPDATE HEADER PO
                $po->update([
                    'bill_to_company_id'    => $request->billing_company_id,
                    'shipping_address'      => $request->shipping_address,
                    
                    // 🔥 TAMBAHKAN 2 BARIS INI DI SINI 🔥
                    'invoice_number'        => $request->invoice_number,
                    'account_number'        => $request->account_number,

                    'payment_terms'         => $paymentTermName,
                    'notes'                 => $request->notes,
                    'delivery_date'         => $request->delivery_date,
                    'global_discount_type'  => $globalDiscType,
                    'global_discount_value' => $globalDiscVal,
                    'subtotal'              => $poSubtotalGross,
                    'discount_total'        => $poTotalDiscount,
                    'tax_total'             => $poTotalTax,
                    'charge_total'          => $poChargeTotal,
                    'grand_total'           => $poGrandTotal,
                ]);

                // 6. CATAT LOG RIWAYAT
                $this->logHistory($po->id, 'PO Direvisi', 'Perubahan pada rincian item, harga, diskon, atau lampiran telah disimpan.');

            });

            return redirect()->route('po.show', $id)->with('success', 'Perubahan Purchase Order berhasil disimpan!');

        } catch (\Exception $e) {
            \Log::error('Error Update PO: ' . $e->getMessage());
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        //
    }

    public function index(\Illuminate\Http\Request $request)
    {
        $search = $request->input('search');

        $statusApproved = \App\Models\Status::where('type', 'PR')->where('slug', 'approved')->first();
        $statusPartial  = \App\Models\Status::where('type', 'PR')->where('slug', 'partial_po')->first();

        $statusIds = array_filter([
            $statusApproved ? $statusApproved->id : null,
            $statusPartial ? $statusPartial->id : null
        ]);

        $readyPrs = \App\Models\PurchaseRequest::with(['user', 'status', 'company'])
            ->whereIn('status_id', $statusIds)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('pr_number', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($qUser) use ($search) {
                          $qUser->where('name', 'like', "%{$search}%");
                      })
                      ->orWhereHas('company', function ($qComp) use ($search) {
                          $qComp->where('name', 'like', "%{$search}%");
                      })
                      ->orWhereHas('items.vendorQuotes.vendor', function ($qVendor) use ($search) {
                          $qVendor->where('name', 'like', "%{$search}%");
                      });
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10, ['*'], 'pr_page');

        $purchaseOrders = \App\Models\PurchaseOrder::with(['vendor', 'company', 'purchaseRequest.company', 'status'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('po_number', 'like', "%{$search}%")
                      ->orWhereHas('vendor', function ($qVendor) use ($search) {
                          $qVendor->where('name', 'like', "%{$search}%");
                      })
                      ->orWhereHas('company', function ($qPoComp) use ($search) {
                          $qPoComp->where('name', 'like', "%{$search}%");
                      })
                      ->orWhereHas('purchaseRequest', function ($qPr) use ($search) {
                          $qPr->where('pr_number', 'like', "%{$search}%")
                             ->orWhereHas('company', function ($qPrComp) use ($search) {
                                 $qPrComp->where('name', 'like', "%{$search}%");
                             });
                      });
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10, ['*'], 'po_page');

        return view('po.index', compact('readyPrs', 'purchaseOrders'));
    }

    public function processPr($slug)
    {
        return $slug;
        $pr = \App\Models\PurchaseRequest::with(['items.item', 'items.vendorQuotes.vendor', 'user', 'company'])->findOrFail($slug);

        $prStatus = strtoupper(trim($pr->status ?? ''));
        $allowedKeywords = ['APPROVED', 'PARTIAL', 'DISETUJUI', 'FINAL'];

        $isAllowed = false;
        foreach ($allowedKeywords as $keyword) {
            if (str_contains($prStatus, $keyword)) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            return redirect()->back()->with('error', 'Akses Ditolak: PR ini tidak bisa diproses karena status sistemnya mendeteksi: "' . $prStatus . '". Hanya PR yang Approved/Disetujui yang bisa dibuatkan PO.');
        }

        $existingPOs = \App\Models\PurchaseOrder::with(['vendor', 'status'])
                        ->where('purchase_request_id', $id)
                        ->orderBy('created_at', 'desc')
                        ->get();

        $selectedVendor = null;
        $firstQuote = $pr->items->flatMap->vendorQuotes->first();
        if ($firstQuote && $firstQuote->vendor) {
            $selectedVendor = $firstQuote->vendor;
        }

        $defaultCurrency = 'IDR';
        if ($firstQuote && !empty($firstQuote->currency)) {
            $defaultCurrency = $firstQuote->currency;
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
        $all_items = \App\Models\Item::orderBy('name')->get();

        return view('po.process_pr', compact(
            'pr', 'existingPOs', 'vendors', 'companies', 'paymentTerms',
            'taxes', 'chargeTypes', 'currencies', 'discountTypes',
            'selectedVendor', 'defaultCurrency', 'defaultShippingAddress', 'all_items'
        ));
    }

    public function storeFromPr(Request $request, $id)
    {
        $request->validate([
            'billing_company_id'   => 'required|exists:companies,id',
            'payment_term_id'      => 'required|exists:payment_terms,id',
            'po_items'             => 'required|array',
            'delivery_date' => 'required|date',
        ]);

        try {
            DB::transaction(function () use ($request, $id) {

                $itemsToProcess = collect($request->po_items)->filter(function ($item) {
                    $vendorId = trim($item['vendor_id'] ?? '');
                    $qty = (float) ($item['qty'] ?? 0);
                    return $vendorId !== '' && $qty > 0;
                });

                if ($itemsToProcess->isEmpty()) {
                    throw new \Exception('Gagal: Anda harus memilih Vendor Aktual minimal untuk 1 barang!');
                }

                foreach ($itemsToProcess as $itemData) {
                    $prItem = \App\Models\PurchaseRequestItem::with('item')->find($itemData['pr_item_id']);
                    if ($prItem) {
                        $orderedQty = $prItem->ordered_qty ?? 0;
                        $sisaQty = $prItem->qty - $orderedQty;
                        $reqQty = (float) $itemData['qty'];
                        if ($reqQty > $sisaQty) {
                            $itemName = $prItem->item->name ?? 'Item Tidak Diketahui';
                            throw new \Exception("Gagal: Qty pesanan untuk '{$itemName}' ({$reqQty}) melebihi sisa barang yang tersedia di PR ({$sisaQty}).");
                        }
                    }
                }

                $itemsByVendor = $itemsToProcess->groupBy('vendor_id');
                $statusPending = \App\Models\Status::where('type', 'PO')
                                    ->whereIn('slug', ['draft', 'pending_approval'])
                                    ->first();

                $paymentTermName = null;
                if ($request->payment_term_id) {
                    $term = \App\Models\PaymentTerm::find($request->payment_term_id);
                    $paymentTermName = $term ? $term->name : null;
                }

                $prRecord = \App\Models\PurchaseRequest::find($id);
                $prNumber = $prRecord ? $prRecord->pr_number : 'Unknown';

                foreach ($itemsByVendor as $vendorId => $items) {

                    $newPoNumber = $this->generatePoNumber($request->billing_company_id);
                    $firstItem = collect($items)->first();
                    $poCurrency = $firstItem['currency'] ?? 'IDR';

                    $poSubtotalGross = 0;
                    $poTotalItemDiscount = 0;
                    $poTotalTax = 0;

                    $processedLineItems = [];
                    $filesToSave = [];
                    $safePoNumber = str_replace('/', '-', $newPoNumber);

                    foreach ($items as $itemData) {
                        $qty = (float) ($itemData['qty'] ?? 0);
                        $price = (float) ($itemData['unit_price'] ?? 0);
                        $gross = $qty * $price;

                        $discVal = (float) ($itemData['discount_value'] ?? 0);
                        $discType = $itemData['discount_type'] ?? 'fixed';
                        $discAmt = ($discType === 'percent') ? ($gross * ($discVal / 100)) : $discVal;

                        $dpp = $gross - $discAmt;
                        $taxAmt = 0;
                        $taxId = $itemData['tax_id'] ?? null;
                        if (!empty($taxId)) {
                            $tax = \App\Models\Tax::find($taxId);
                            if ($tax) {
                                $taxAmt = $dpp * ($tax->percent / 100);
                            }
                        }

                        if (isset($itemData['attachments']) && is_array($itemData['attachments'])) {
                            foreach ($itemData['attachments'] as $file) {
                                if (is_file($file) && $file->isValid()) {
                                    $originalName = $file->getClientOriginalName();
                                    $filename = time() . '_' . str_replace(' ', '_', $originalName);
                                    $path = 'po_attachments/' . $safePoNumber . '/' . $filename;
                                    Storage::disk('public')->put($path, file_get_contents($file));

                                    $filesToSave[] = [
                                        'file_name' => $originalName,
                                        'file_path' => $path
                                    ];
                                }
                            }
                        }

                        $poSubtotalGross += $gross;
                        $poTotalItemDiscount += $discAmt;
                        $poTotalTax += $taxAmt;

                        $processedLineItems[] = [
                            'itemData' => $itemData, 'discAmt' => $discAmt, 'dpp' => $dpp,
                            'taxId' => $taxId, 'taxAmt' => $taxAmt, 'qty' => $qty,
                            'price' => $price, 'discType' => $discType, 'discVal' => $discVal
                        ];
                    }

                    $globalDiscType = $request->global_discount_type ?? 'fixed';
                    $globalDiscVal = (float) ($request->global_discount_value ?? 0);
                    $poGlobalDiscount = ($globalDiscType === 'percent')
                                        ? (($poSubtotalGross - $poTotalItemDiscount) * ($globalDiscVal / 100))
                                        : $globalDiscVal;

                    $poTotalDiscount = $poTotalItemDiscount + $poGlobalDiscount;
                    $poGrandTotal = ($poSubtotalGross - $poTotalDiscount) + $poTotalTax;

                    $po = \App\Models\PurchaseOrder::create([
                        'po_number'             => $newPoNumber,
                        'purchase_request_id'   => $id,
                        'vendor_id'             => $vendorId,
                        'bill_to_company_id'    => $request->billing_company_id,
                        'status_id'             => $statusPending ? $statusPending->id : null,
                        'po_date'               => now(),
                        'created_by'            => auth()->id(),
                        'is_sent'               => false,
                        'currency'              => $poCurrency,
                        'shipping_address'      => $request->shipping_address,
                        'payment_terms'         => $paymentTermName,
                        'notes'                 => $request->notes,
                        'delivery_date' => $request->delivery_date,
                        'global_discount_type'  => $globalDiscType,
                        'global_discount_value' => $globalDiscVal,
                        'subtotal'              => $poSubtotalGross,
                        'discount_total'        => $poTotalDiscount,
                        'tax_total'             => $poTotalTax,
                        'charge_total'          => 0,
                        'grand_total'           => $poGrandTotal,
                    ]);

                    foreach ($processedLineItems as $line) {
                        \App\Models\PurchaseOrderItem::create([
                            'purchase_order_id'        => $po->id,
                            'item_id'                  => $line['itemData']['item_id'],
                            'purchase_request_item_id' => $line['itemData']['pr_item_id'],
                            'tax_id'                   => $line['taxId'] ?: null,
                            'qty_ordered'              => $line['qty'],
                            'qty_received'             => 0,
                            'unit_price'               => $line['price'],
                            'discount_type'            => $line['discType'],
                            'discount_value'           => $line['discVal'],
                            'discount_amount'          => $line['discAmt'],
                            'subtotal'                 => $line['dpp'],
                            'tax_amount'               => $line['taxAmt'],
                        ]);

                        $prItem = \App\Models\PurchaseRequestItem::find($line['itemData']['pr_item_id']);
                        if ($prItem) {
                            $prItem->ordered_qty = ($prItem->ordered_qty ?? 0) + $line['qty'];
                            $prItem->save();
                        }
                    }

                    $poChargeTotal = 0;
                    if ($request->has('charges')) {
                        foreach ($request->charges as $charge) {
                            if (!empty($charge['charge_type_id']) && !empty($charge['amount'])) {
                                $chargeMaster = \App\Models\ChargeType::find($charge['charge_type_id']);
                                $amt = (float) $charge['amount'];

                                DB::table('purchase_order_charges')->insert([
                                    'purchase_order_id' => $po->id,
                                    'name'              => $chargeMaster ? $chargeMaster->name : 'Biaya Tambahan',
                                    'amount'            => $amt,
                                    'created_at'        => now(),
                                    'updated_at'        => now(),
                                ]);
                                $poChargeTotal += $amt;
                            }
                        }
                    }

                    $poExtraDiscountTotal = 0;
                    if ($request->has('extra_discounts')) {
                        foreach ($request->extra_discounts as $disc) {
                            if (!empty($disc['discount_type_id']) && !empty($disc['amount'])) {
                                $discMaster = \App\Models\DiscountType::find($disc['discount_type_id']);
                                $amt = (float) $disc['amount'];

                                DB::table('purchase_order_discounts')->insert([
                                    'purchase_order_id' => $po->id,
                                    'name'              => $discMaster ? $discMaster->name : 'Potongan Tambahan',
                                    'amount'            => $amt,
                                    'created_at'        => now(),
                                    'updated_at'        => now(),
                                ]);
                                $poExtraDiscountTotal += $amt;
                            }
                        }
                    }

                    if ($poChargeTotal > 0 || $poExtraDiscountTotal > 0) {
                        $po->update([
                            'charge_total' => $poChargeTotal,
                            'grand_total'  => $po->grand_total + $poChargeTotal - $poExtraDiscountTotal
                        ]);
                    }

                    if (!empty($filesToSave)) {
                        foreach ($filesToSave as $uFile) {
                            \App\Models\PurchaseOrderAttachment::create([
                                'purchase_order_id' => $po->id,
                                'file_name'         => $uFile['file_name'],
                                'file_path'         => $uFile['file_path'],
                            ]);
                        }
                    }

                    $this->logHistory($po->id, 'PO Diterbitkan', 'PO baru diterbitkan dari PR #' . $prNumber);

                } // END LOOP VENDOR

                $pr = \App\Models\PurchaseRequest::with('items')->find($id);
                if ($pr) {
                    $allFulfilled = true;
                    foreach ($pr->items as $item) {
                        $orderedQty = $item->ordered_qty ?? 0;
                        if ($orderedQty < $item->qty) {
                            $allFulfilled = false;
                            break;
                        }
                    }

                    if ($allFulfilled) {
                        $statusPoIssued = \App\Models\Status::where('type', 'PR')->where('slug', 'po_issued')->first();
                        if ($statusPoIssued) $pr->update(['status_id' => $statusPoIssued->id]);
                    } else {
                        $statusPartial = \App\Models\Status::where('type', 'PR')->where('slug', 'partial_po')->first();
                        if ($statusPartial) $pr->update(['status_id' => $statusPartial->id]);
                    }
                }
            });

            return redirect()->route('po.index')->with('success', 'Purchase Order berhasil diterbitkan beserta lampirannya!');

        } catch (\Exception $e) {
            \Log::error('Error Terbitkan PO: ' . $e->getMessage());
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    private function generatePoNumber($companyId)
    {
        $company = \App\Models\Company::find($companyId);
        $companyCode = $company ? strtoupper($company->code ?? 'PT') : 'PT';

        $year = date('Y');
        $month = date('m');

        $latestPo = \App\Models\PurchaseOrder::where('bill_to_company_id', $companyId)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        if ($latestPo && $latestPo->po_number) {
            $parts = explode('/', $latestPo->po_number);
            $lastNumber = (int) end($parts);
            $newSequence = $lastNumber + 1;
        } else {
            $newSequence = 1;
        }

        $formattedSequence = str_pad($newSequence, 4, '0', STR_PAD_LEFT);

        return "PO/{$companyCode}/{$year}/{$month}/{$formattedSequence}";
    }

    public function show($id)
    {
        $po = \App\Models\PurchaseOrder::with([
            'vendor',
            'purchaseRequest.user',
            'status',
            'items.item',
            'items.tax'
        ])->findOrFail($id);

        $company = \App\Models\Company::find($po->bill_to_company_id);

        $charges = DB::table('purchase_order_charges')
                    ->where('purchase_order_id', $id)
                    ->get();

        $extraDiscounts = DB::table('purchase_order_discounts')
                            ->where('purchase_order_id', $id)
                            ->get();

        return view('po.show', compact('po', 'company', 'charges', 'extraDiscounts'));
    }

    // ==========================================
    // FUNGSI PERSETUJUAN (APPROVAL) PO
    // ==========================================

    // 1. Admin mengajukan ke Atasan
    public function submitApproval($id)
    {
        try {
            $po = \App\Models\PurchaseOrder::findOrFail($id);
            $statusPending = \App\Models\Status::where('type', 'PO')->where('slug', 'pending_approval')->first();

            if ($statusPending) {
                $po->update(['status_id' => $statusPending->id]);

                // ==========================================
                // 🔔 TEMBAKKAN NOTIFIKASI KE PARA BOS (ATASAN)
                // ==========================================
                // Pastikan nama role sesuai dengan database Anda
                $paraBos = User::role(['Super Admin', 'direktur', 'manager'])->get();

                foreach($paraBos as $bos) {
                    $bos->notify(new DocumentApprovalNotification(
                        'PO Butuh Persetujuan 📝',
                        "PO Nomor {$po->po_number} senilai Rp " . number_format($po->grand_total, 0, ',', '.') . " butuh tanda tangan Anda.",
                        route('po.show', $po->id)
                    ));
                }
                // ==========================================

                return redirect()->back()->with('success', 'Purchase Order berhasil diajukan untuk proses persetujuan!');
            }
            return redirect()->back()->with('error', 'Gagal: Status Pending Approval tidak ditemukan di database.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // 2. Atasan menyetujui PO
    public function approve($id)
    {
        try {
            $po = \App\Models\PurchaseOrder::findOrFail($id);
            $statusApproved = \App\Models\Status::where('type', 'PO')->where('slug', 'issued')->first();

            if ($statusApproved) {
                $po->update([
                    'status_id'   => $statusApproved->id,
                    'approved_by' => auth()->id(),
                ]);

                $this->logHistory($po->id, 'PO Disetujui', 'PO telah disetujui oleh atasan dan resmi berstatus Terbit (Issued).');

                // ==========================================
                // 🔔 TEMBAKKAN NOTIFIKASI KE PEMBUAT PO
                // ==========================================
                $pembuatPO = User::find($po->created_by);
                if ($pembuatPO) {
                    $pembuatPO->notify(new DocumentApprovalNotification(
                        'PO Disetujui ✅',
                        "Hore! PO Nomor {$po->po_number} telah disetujui oleh Atasan dan siap dikirim ke Vendor.",
                        route('po.show', $po->id)
                    ));
                }
                // ==========================================

                return redirect()->back()->with('success', 'Hebat! Purchase Order telah resmi disetujui dan berstatus Terbit (Issued).');
            }
            return redirect()->back()->with('error', 'Gagal: Status Issued (Terbit) tidak ditemukan di database.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // 3. Atasan menolak PO
    public function reject($id)
    {
        try {
            $po = \App\Models\PurchaseOrder::findOrFail($id);
            $statusRejected = \App\Models\Status::where('type', 'PO')->where('slug', 'rejected')->first();

            if ($statusRejected) {
                $po->update(['status_id' => $statusRejected->id]);

                $this->logHistory($po->id, 'PO Ditolak', 'PO telah ditolak oleh atasan dan dikembalikan.');

                // ==========================================
                // 🔔 TEMBAKKAN NOTIFIKASI KE PEMBUAT PO
                // ==========================================
                $pembuatPO = User::find($po->created_by);
                if ($pembuatPO) {
                    $pembuatPO->notify(new DocumentApprovalNotification(
                        'PO Ditolak ❌',
                        "Mohon maaf, PO Nomor {$po->po_number} dikembalikan/ditolak oleh Atasan.",
                        route('po.show', $po->id)
                    ));
                }
                // ==========================================

                return redirect()->back()->with('warning', 'Purchase Order telah ditolak dan dikembalikan.');
            }
            return redirect()->back()->with('error', 'Gagal: Status Rejected tidak ditemukan di database.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // ==========================================
    // FUNGSI HAPUS FILE LAMPIRAN PO
    // ==========================================
    public function deleteAttachment($id)
    {
        try {
            $attachment = \App\Models\PurchaseOrderAttachment::findOrFail($id);

            $poId = $attachment->purchase_order_id;
            $fileName = $attachment->file_name;

            if (\Storage::disk('public')->exists($attachment->file_path)) {
                \Storage::disk('public')->delete($attachment->file_path);
            }

            $attachment->delete();

            $this->logHistory($poId, 'Lampiran Dihapus', "File lampiran bernama '{$fileName}' telah dihapus secara permanen.");

            return redirect()->back()->with('success', 'File lampiran berhasil dihapus secara permanen.');
        } catch (\Exception $e) {
            \Log::error('Error Hapus Lampiran: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menghapus file: ' . $e->getMessage());
        }
    }

    // ==========================================
    // FUNGSI MEMBATALKAN (CANCEL) PO
    // ==========================================
    public function cancel($id)
    {
        try {
            \DB::transaction(function () use ($id) {
                $po = \App\Models\PurchaseOrder::with('items')->findOrFail($id);

                $statusCanceled = \App\Models\Status::where('type', 'PO')->where('slug', 'canceled')->first();

                if (!$statusCanceled) {
                    throw new \Exception('Status pembatalan tidak ditemukan di database.');
                }

                foreach ($po->items as $poItem) {
                    if ($poItem->purchase_request_item_id) {
                        $prItem = \App\Models\PurchaseRequestItem::find($poItem->purchase_request_item_id);
                        if ($prItem) {
                            $prItem->ordered_qty = max(0, $prItem->ordered_qty - $poItem->qty_ordered);
                            $prItem->save();
                        }
                    }
                }

                if ($po->purchase_request_id) {
                    $pr = \App\Models\PurchaseRequest::with('items')->find($po->purchase_request_id);
                    if ($pr) {
                        $totalOrdered = $pr->items->sum('ordered_qty');

                        if ($totalOrdered == 0) {
                            $statusPrApproved = \App\Models\Status::where('type', 'PR')->where('slug', 'approved')->first();
                            if ($statusPrApproved) $pr->update(['status_id' => $statusPrApproved->id]);
                        } else {
                            $statusPrPartial = \App\Models\Status::where('type', 'PR')->where('slug', 'partial_po')->first();
                            if ($statusPrPartial) $pr->update(['status_id' => $statusPrPartial->id]);
                        }
                    }
                }

                $po->update(['status_id' => $statusCanceled->id]);

                $this->logHistory($po->id, 'PO Dibatalkan', 'PO dibatalkan secara permanen. Kuantitas pesanan barang telah dikembalikan ke PR.');
            });

            return redirect()->back()->with('success', 'Purchase Order berhasil DIBATALKAN. Kuantitas barang telah dikembalikan ke PR.');

        } catch (\Exception $e) {
            \Log::error('Error Cancel PO: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal membatalkan PO: ' . $e->getMessage());
        }
    }

    // ==========================================
    // FUNGSI PENCATAT RIWAYAT (HELPER)
    // ==========================================
    private function logHistory($poId, $action, $note = null)
    {
        \App\Models\PurchaseOrderHistory::create([
            'purchase_order_id' => $poId,
            'user_id'           => auth()->id(),
            'action'            => $action,
            'note'              => $note
        ]);
    }
}
