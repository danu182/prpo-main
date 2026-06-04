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
// use Illuminate\Container\Attributes\DB;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Notifications\DocumentApprovalNotification; // <-- Panggil class Notifikasi kita

class PurchaseOrderController extends Controller
{

    // 1. Tampilkan Item yang SIAP di-PO (Untuk dipilih Procurement)
    public function getItemsForPo()
    {
        // Cari item dari PR yang sudah APPROVED tapi belum habis di-PO (sisa > 0)
        // $items = PurchaseRequestItem::whereHas('purchaseRequest', function($q) {
        //             $q->where('status', 'APPROVED');
        //          })
        //          ->whereRaw('qty > ordered_qty')
        //          ->with(['item', 'purchaseRequest.company'])
        //          ->get();

        // return response()->json($items);

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

    /**
     * Display a listing of the resource.
     */
    // public function index()
    // {
    //     // Ambil PO dengan relasi (Eager Loading) biar cepat
    //     $orders = PurchaseOrder::with(['vendor', 'status', 'creator'])
    //                 ->latest()
    //                 ->get();

    //     // Ambil Master Status KHUSUS tipe 'PO'
    //     $statuses = \App\Models\Status::where('type', 'PO')->orderBy('sequence')->get();

    //     // Ambil Vendor untuk filter
    //     $vendors = \App\Models\Vendor::orderBy('name')->get();

    //     return view('po.index', compact('orders', 'statuses', 'vendors'));
    // }


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
            'pr',
            'selectedVendor',
            'vendors',
            'all_items',
            'companies',
            'taxes',
            'currencies',
            'chargeTypes',
            'defaultCurrency',
            'paymentTerms',
            'defaultShippingAddress'
        ));
    }



    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Ambil Item PR yang SUDAH APPROVED tapi BELUM habis dipesan
        // Ini inti dari fitur SPLIT PO
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
            // Pesan error custom jika vendor lupa diisi
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
                        // PERBAIKAN: Gunakan nama kolom 'ordered_qty' sesuai database
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
                    // Hanya pedulikan item yang statusnya APPROVED
                    if ($item->status === 'APPROVED') {
                        $orderedQty = $item->ordered_qty ?? 0;
                        if ($orderedQty < $item->qty) {
                            $allFulfilled = false;
                            break; // Ada satu barang saja yang belum komplit, langsung break
                        }
                    }
                }

                // Jika sudah terpenuhi semua, status jadi po_issued.
                // Jika belum terpenuhi (Partial), kita set jadi 'partial_po' (jika ada), atau BIAKARKAN tetap 'approved'
                if ($allFulfilled) {
                    $statusTarget = \App\Models\Status::where('type', 'PR')->where('slug', 'po_issued')->first();
                    if ($statusTarget) {
                        $pr->update(['status_id' => $statusTarget->id]);
                    }
                } else {
                    // Cari status 'partial_po'. Jika tidak ketemu di DB, PR akan tetap di status 'approved'
                    $statusTarget = \App\Models\Status::where('type', 'PR')->where('slug', 'partial_po')->first();
                    if ($statusTarget) {
                        $pr->update(['status_id' => $statusTarget->id]);
                    }
                }
            }
            // ==========================================================

            DB::commit();
            return redirect()->route('po.show', $po->id)->with('success', 'Purchase Order berhasil diterbitkan!');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal membuat PO: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    // public function show($id)
    // {
    //     // 1. Ambil PO beserta relasinya
    //     // Pastikan nama relasi 'items', 'vendor', 'company' sesuai di Model PurchaseOrder
    //     $po = PurchaseOrder::with([
    //         'vendor',
    //         'company',
    //         'items.item', // Relasi ke master item dari tabel po_items
    //         'creator'     // Relasi ke user pembuat (optional)
    //     ])->findOrFail($id);

    //     // 2. Ambil Charges (Biaya Tambahan)
    //     // Jika Anda menggunakan DB::table di method store, ambil manual:
    //     $charges = \Illuminate\Support\Facades\DB::table('purchase_order_charges')
    //                 ->where('purchase_order_id', $id)
    //                 ->get();

    //     // 3. Return View
    //     // Pastikan folder view-nya benar (misal: resources/views/po/show.blade.php)
    //     return view('po.show', compact('po', 'charges'));
    // }

    // Proses Approval / Rejection
    // public function approve($id)
    // {
    //     \Illuminate\Support\Facades\DB::beginTransaction();

    //     try {
    //         $po = \App\Models\PurchaseOrder::with(['vendor', 'items', 'status'])->findOrFail($id);
    //         $user = auth()->user();

    //         // Deteksi Role
    //         // $isManager  = $user->hasRole('Manager') || $user->role == 'manager';
    //         // $isDirector = $user->hasRole('Director') || $user->role == 'director';

    //         $nextStatus = null;
    //         $actionName = '';
    //         $logNote = '';

    //         // =========================================================
    //         // LOGIKA PERSETUJUAN BERTINGKAT
    //         // =========================================================
    //         if ($user->can('approve_manager_po') && $po->status->slug === 'pending_approval') {

    //             // MANAGER APPROVE
    //             $nextStatus = \App\Models\Status::where('type', 'PO')->where('slug', 'approved_manager')->first();
    //             $actionName = 'PO APPROVED (MANAGER)';
    //             $logNote = "Manager telah menyetujui PO Nomor **{$po->po_number}**.\nStatus saat ini: Menunggu persetujuan akhir dari Direktur.";

    //         } elseif ($user->can('approve_director_po') && in_array($po->status->slug, ['pending_approval', 'approved_manager'])) {

    //             // DIREKTUR APPROVE
    //             $nextStatus = \App\Models\Status::where('type', 'PO')->where('slug', 'issued')->first();
    //             $actionName = 'PO ISSUED (DIREKTUR)';
    //             $logNote = "Direktur telah memberikan persetujuan final untuk PO Nomor **{$po->po_number}**.\nDokumen PO kini sah dan siap dikirim ke vendor.";

    //             $po->approved_by = $user->id;
    //             $po->approved_at = now();

    //         } else {
    //             return back()->with('error', 'Anda tidak memiliki Izin (Permission) atau status PO tidak valid.');
    //         }

    //         // Simpan perubahan status PO
    //         $po->status_id = $nextStatus->id;
    //         $po->save();

    //         // =========================================================
    //         // CATAT LOG KE PR Induk (MENGGUNAKAN QUERY BUILDER)
    //         // =========================================================
    //         $prItemIds = $po->items->pluck('purchase_request_item_id')->filter()->unique();
    //         $prIds = \Illuminate\Support\Facades\DB::table('purchase_request_items')
    //                     ->whereIn('id', $prItemIds)
    //                     ->pluck('purchase_request_id')
    //                     ->unique();

    //         foreach ($prIds as $prId) {
    //             \App\Models\PurchaseRequestHistory::create([
    //                 'purchase_request_id' => $prId,
    //                 'user_id' => $user->id,
    //                 'action' => $actionName,
    //                 'note' => $logNote,
    //             ]);
    //         }

    //         \Illuminate\Support\Facades\DB::commit();
    //         return back()->with('success', 'Berhasil memproses persetujuan PO.');

    //     } catch (\Exception $e) {
    //         \Illuminate\Support\Facades\DB::rollBack();
    //         return back()->with('error', 'Terjadi kesalahan saat memproses PO: ' . $e->getMessage());
    //     }
    // }

    // public function reject(Request $request, $id)
    // {
    //     $po = PurchaseOrder::findOrFail($id);

    //     $request->validate([
    //         'reject_reason' => 'required|string|max:255',
    //     ]);

    //     // 1. Cari ID Status 'REJECTED'
    //     $statusRejected = Status::where('type', 'PO')->where('slug', 'rejected')->first();

    //     // 2. Update Status & Catatan
    //     $po->update([
    //         'status_id' => $statusRejected->id,
    //         'notes'     => $po->notes . "\n[DITOLAK]: " . $request->reject_reason, // Append alasan ke notes
    //     ]);

    //     return back()->with('success', 'Purchase Order telah ditolak.');
    // }

    /**
     * Show the form for editing the specified resource.
     */
    // public function edit($id)
    // {
    //     $po = \App\Models\PurchaseOrder::with(['items.item', 'vendor', 'status'])->findOrFail($id);

    //     if (!in_array(optional($po->status)->slug, ['draft', 'pending_approval'])) {
    //         return redirect()->route('po.show', $id)->with('error', 'Gagal: PO ini sudah tidak dapat diedit karena statusnya sudah ' . optional($po->status)->name);
    //     }

    //     $vendors = \App\Models\Vendor::all();
    //     $companies = \App\Models\Company::all();
    //     $paymentTerms = \App\Models\PaymentTerm::all();
    //     $taxes = \App\Models\Tax::all();
    //     $chargeTypes = \App\Models\ChargeType::where('is_active', 1)->get();
    //     $currencies = \App\Models\Currency::all();
    //     $discountTypes = \App\Models\DiscountType::where('is_active', 1)->get();

    //     $charges = DB::table('purchase_order_charges')->where('purchase_order_id', $id)->get();

    //     // TAMBAHAN: Ambil data potongan tambahan sebelumnya
    //     $extraDiscounts = DB::table('purchase_order_discounts')->where('purchase_order_id', $id)->get();

    //     return view('po.edit', compact('po', 'vendors', 'companies', 'paymentTerms', 'taxes', 'chargeTypes', 'currencies', 'charges', 'discountTypes', 'extraDiscounts'));
    // }

    // /**
    //  * Update the specified resource in storage.
    //  */
    // public function update(Request $request, $id)
    // {
    //     $request->validate([
    //         'billing_company_id'   => 'required|exists:companies,id',
    //         'payment_term_id'      => 'required|exists:payment_terms,id',
    //         'po_items'             => 'required|array',
    //     ]);

    //     try {
    //         DB::transaction(function () use ($request, $id) {
    //             $po = \App\Models\PurchaseOrder::with('items')->findOrFail($id);

    //             if (!in_array(optional($po->status)->slug, ['draft', 'pending_approval'])) {
    //                 throw new \Exception('PO sudah diproses dan tidak bisa diedit lagi.');
    //             }

    //             $paymentTermName = null;
    //             if ($request->payment_term_id) {
    //                 $term = \App\Models\PaymentTerm::find($request->payment_term_id);
    //                 $paymentTermName = $term ? $term->name : null;
    //             }

    //             $poSubtotalGross = 0;
    //             $poTotalItemDiscount = 0;
    //             $poTotalTax = 0;

    //             // 1. UPDATE ITEM BARANG
    //             foreach ($request->po_items as $itemId => $itemData) {
    //                 $poItem = \App\Models\PurchaseOrderItem::find($itemId);
    //                 if (!$poItem) continue;

    //                 $newQty = (float) ($itemData['qty'] ?? 0);
    //                 $price = (float) ($itemData['unit_price'] ?? 0);
    //                 $gross = $newQty * $price;

    //                 $discVal = (float) ($itemData['discount_value'] ?? 0);
    //                 $discType = $itemData['discount_type'] ?? 'fixed';
    //                 $discAmt = ($discType === 'percent') ? ($gross * ($discVal / 100)) : $discVal;

    //                 $dpp = $gross - $discAmt;

    //                 $taxAmt = 0;
    //                 $taxId = $itemData['tax_id'] ?? null;
    //                 if (!empty($taxId)) {
    //                     $tax = \App\Models\Tax::find($taxId);
    //                     if ($tax) $taxAmt = $dpp * ($tax->percent / 100);
    //                 }

    //                 $poSubtotalGross += $gross;
    //                 $poTotalItemDiscount += $discAmt;
    //                 $poTotalTax += $taxAmt;

    //                 $qtyDifference = $newQty - $poItem->qty_ordered;
    //                 if ($qtyDifference != 0) {
    //                     $prItem = \App\Models\PurchaseRequestItem::find($poItem->purchase_request_item_id);
    //                     if ($prItem) {
    //                         $prItem->ordered_qty = ($prItem->ordered_qty ?? 0) + $qtyDifference;
    //                         $prItem->save();
    //                     }
    //                 }

    //                 $poItem->update([
    //                     'tax_id'          => $taxId ?: null,
    //                     'qty_ordered'     => $newQty,
    //                     'unit_price'      => $price,
    //                     'discount_type'   => $discType,
    //                     'discount_value'  => $discVal,
    //                     'discount_amount' => $discAmt,
    //                     'subtotal'        => $dpp,
    //                     'tax_amount'      => $taxAmt,
    //                 ]);
    //             }

    //             // 2. KALKULASI DISKON GLOBAL
    //             $globalDiscType = $request->global_discount_type ?? 'fixed';
    //             $globalDiscVal = (float) ($request->global_discount_value ?? 0);
    //             $poGlobalDiscount = ($globalDiscType === 'percent')
    //                                 ? (($poSubtotalGross - $poTotalItemDiscount) * ($globalDiscVal / 100))
    //                                 : $globalDiscVal;

    //             $poTotalDiscount = $poTotalItemDiscount + $poGlobalDiscount;

    //             // 3. UPDATE BIAYA TAMBAHAN
    //             DB::table('purchase_order_charges')->where('purchase_order_id', $po->id)->delete();
    //             $poChargeTotal = 0;

    //             if ($request->has('charges')) {
    //                 foreach ($request->charges as $charge) {
    //                     if (!empty($charge['charge_type_id']) && !empty($charge['amount'])) {
    //                         $chargeMaster = \App\Models\ChargeType::find($charge['charge_type_id']);
    //                         $amt = (float) $charge['amount'];

    //                         DB::table('purchase_order_charges')->insert([
    //                             'purchase_order_id' => $po->id,
    //                             'name'              => $chargeMaster ? $chargeMaster->name : 'Biaya Tambahan',
    //                             'amount'            => $amt,
    //                             'created_at'        => now(),
    //                             'updated_at'        => now(),
    //                         ]);
    //                         $poChargeTotal += $amt;
    //                     }
    //                 }
    //             }

    //             // 4. UPDATE POTONGAN TAMBAHAN (VOUCHER/MEMBER)
    //             DB::table('purchase_order_discounts')->where('purchase_order_id', $po->id)->delete();
    //             $poExtraDiscountTotal = 0;

    //             if ($request->has('extra_discounts')) {
    //                 foreach ($request->extra_discounts as $disc) {
    //                     if (!empty($disc['discount_type_id']) && !empty($disc['amount'])) {
    //                         $discMaster = \App\Models\DiscountType::find($disc['discount_type_id']);
    //                         $amt = (float) $disc['amount'];

    //                         DB::table('purchase_order_discounts')->insert([
    //                             'purchase_order_id' => $po->id,
    //                             'name'              => $discMaster ? $discMaster->name : 'Potongan Tambahan',
    //                             'amount'            => $amt,
    //                             'created_at'        => now(),
    //                             'updated_at'        => now(),
    //                         ]);
    //                         $poExtraDiscountTotal += $amt;
    //                     }
    //                 }
    //             }

    //             // RUMUS FINAL GRAND TOTAL
    //             $poGrandTotal = ($poSubtotalGross - $poTotalDiscount) + $poTotalTax + $poChargeTotal - $poExtraDiscountTotal;

    //             // 5. UPDATE HEADER PO
    //             $po->update([
    //                 'bill_to_company_id'    => $request->billing_company_id,
    //                 'shipping_address'      => $request->shipping_address,
    //                 'payment_terms'         => $paymentTermName,
    //                 'notes'                 => $request->notes,
    //                 'global_discount_type'  => $globalDiscType,
    //                 'global_discount_value' => $globalDiscVal,
    //                 'subtotal'              => $poSubtotalGross,
    //                 'discount_total'        => $poTotalDiscount,
    //                 'tax_total'             => $poTotalTax,
    //                 'charge_total'          => $poChargeTotal,
    //                 'grand_total'           => $poGrandTotal,
    //             ]);

    //         });

    //         return redirect()->route('po.show', $id)->with('success', 'Perubahan Purchase Order berhasil disimpan!');

    //     } catch (\Exception $e) {
    //         \Log::error('Error Update PO: ' . $e->getMessage());
    //         return back()->withInput()->with('error', $e->getMessage());
    //     }
    // }


    public function edit($id)
    {
        // PERBAIKAN: Panggil relasi 'attachments' agar file lama bisa diload (jika dibutuhkan di view)
        $po = \App\Models\PurchaseOrder::with(['items.item', 'vendor', 'status', 'attachments'])->findOrFail($id);

        // Keamanan: Tolak jika status PO sudah bukan Draft / Pending
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

    // public function update(Request $request, $id)
    // {
    //     // 1. Validasi dasar
    //     $request->validate([
    //         'billing_company_id'       => 'required|exists:companies,id',
    //         'payment_term_id'          => 'required|exists:payment_terms,id',
    //         'po_items'                 => 'required|array',
    //         // Validasi file baru yang diupload saat edit
    //         'po_items.*.attachments.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',
    //     ]);

    //     try {
    //         DB::transaction(function () use ($request, $id) {
    //             $po = \App\Models\PurchaseOrder::with('items')->findOrFail($id);

    //             if (!in_array(optional($po->status)->slug, ['draft', 'pending_approval'])) {
    //                 throw new \Exception('PO sudah diproses dan tidak bisa diedit lagi.');
    //             }

    //             $paymentTermName = null;
    //             if ($request->payment_term_id) {
    //                 $term = \App\Models\PaymentTerm::find($request->payment_term_id);
    //                 $paymentTermName = $term ? $term->name : null;
    //             }

    //             $poSubtotalGross = 0;
    //             $poTotalItemDiscount = 0;
    //             $poTotalTax = 0;

    //             $safePoNumber = str_replace('/', '-', $po->po_number);

    //             // 1. UPDATE ITEM BARANG & PROSES UPLOAD LAMPIRAN BARU
    //             foreach ($request->po_items as $itemId => $itemData) {
    //                 $poItem = \App\Models\PurchaseOrderItem::find($itemId);
    //                 if (!$poItem) continue;

    //                 $newQty = (float) ($itemData['qty'] ?? 0);
    //                 $price = (float) ($itemData['unit_price'] ?? 0);
    //                 $gross = $newQty * $price;

    //                 $discVal = (float) ($itemData['discount_value'] ?? 0);
    //                 $discType = $itemData['discount_type'] ?? 'fixed';
    //                 $discAmt = ($discType === 'percent') ? ($gross * ($discVal / 100)) : $discVal;

    //                 $dpp = $gross - $discAmt;

    //                 $taxAmt = 0;
    //                 $taxId = $itemData['tax_id'] ?? null;
    //                 if (!empty($taxId)) {
    //                     $tax = \App\Models\Tax::find($taxId);
    //                     if ($tax) $taxAmt = $dpp * ($tax->percent / 100);
    //                 }

    //                 // =======================================================
    //                 // TANGKAP FILE UPLOAD BARU (JIKA ADMIN MENAMBAH FILE)
    //                 // =======================================================
    //                 if (isset($itemData['attachments']) && is_array($itemData['attachments'])) {
    //                     foreach ($itemData['attachments'] as $file) {
    //                         if (is_file($file) && $file->isValid()) {
    //                             $originalName = $file->getClientOriginalName();
    //                             $filename = time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName);

    //                             // Simpan ke folder spesifik PO ini
    //                             $path = 'po_attachments/' . $safePoNumber . '/' . $filename;
    //                             Storage::disk('public')->put($path, file_get_contents($file));

    //                             // Catat di database
    //                             \App\Models\PurchaseOrderAttachment::create([
    //                                 'purchase_order_id' => $po->id,
    //                                 'file_name'         => $originalName,
    //                                 'file_path'         => $path,
    //                             ]);
    //                         }
    //                     }
    //                 }

    //                 $poSubtotalGross += $gross;
    //                 $poTotalItemDiscount += $discAmt;
    //                 $poTotalTax += $taxAmt;

    //                 // KOREKSI QTY DI PR ITEM (Jika Admin mengubah jumlah pesanan saat Edit PO)
    //                 $qtyDifference = $newQty - $poItem->qty_ordered;
    //                 if ($qtyDifference != 0) {
    //                     $prItem = \App\Models\PurchaseRequestItem::find($poItem->purchase_request_item_id);
    //                     if ($prItem) {
    //                         $prItem->ordered_qty = ($prItem->ordered_qty ?? 0) + $qtyDifference;
    //                         $prItem->save();
    //                     }
    //                 }

    //                 // Simpan perubahan ke item PO
    //                 $poItem->update([
    //                     'tax_id'          => $taxId ?: null,
    //                     'qty_ordered'     => $newQty,
    //                     'unit_price'      => $price,
    //                     'discount_type'   => $discType,
    //                     'discount_value'  => $discVal,
    //                     'discount_amount' => $discAmt,
    //                     'subtotal'        => $dpp,
    //                     'tax_amount'      => $taxAmt,
    //                 ]);
    //             }

    //             // 2. KALKULASI DISKON GLOBAL
    //             $globalDiscType = $request->global_discount_type ?? 'fixed';
    //             $globalDiscVal = (float) ($request->global_discount_value ?? 0);
    //             $poGlobalDiscount = ($globalDiscType === 'percent')
    //                                 ? (($poSubtotalGross - $poTotalItemDiscount) * ($globalDiscVal / 100))
    //                                 : $globalDiscVal;

    //             $poTotalDiscount = $poTotalItemDiscount + $poGlobalDiscount;

    //             // 3. UPDATE BIAYA TAMBAHAN (Hapus yang lama, simpan yang baru)
    //             DB::table('purchase_order_charges')->where('purchase_order_id', $po->id)->delete();
    //             $poChargeTotal = 0;

    //             if ($request->has('charges')) {
    //                 foreach ($request->charges as $charge) {
    //                     if (!empty($charge['charge_type_id']) && !empty($charge['amount'])) {
    //                         $chargeMaster = \App\Models\ChargeType::find($charge['charge_type_id']);
    //                         $amt = (float) $charge['amount'];

    //                         DB::table('purchase_order_charges')->insert([
    //                             'purchase_order_id' => $po->id,
    //                             'name'              => $chargeMaster ? $chargeMaster->name : 'Biaya Tambahan',
    //                             'amount'            => $amt,
    //                             'created_at'        => now(),
    //                             'updated_at'        => now(),
    //                         ]);
    //                         $poChargeTotal += $amt;
    //                     }
    //                 }
    //             }

    //             // 4. UPDATE POTONGAN TAMBAHAN (VOUCHER/MEMBER)
    //             \DB::table('purchase_order_discounts')->where('purchase_order_id', $po->id)->delete();
    //             $poExtraDiscountTotal = 0;

    //             if ($request->has('extra_discounts')) {
    //                 foreach ($request->extra_discounts as $disc) {
    //                     if (!empty($disc['discount_type_id']) && !empty($disc['amount'])) {
    //                         $discMaster = \App\Models\DiscountType::find($disc['discount_type_id']);
    //                         $amt = (float) $disc['amount'];

    //                         \DB::table('purchase_order_discounts')->insert([
    //                             'purchase_order_id' => $po->id,
    //                             'name'              => $discMaster ? $discMaster->name : 'Potongan Tambahan',
    //                             'amount'            => $amt,
    //                             'created_at'        => now(),
    //                             'updated_at'        => now(),
    //                         ]);
    //                         $poExtraDiscountTotal += $amt;
    //                     }
    //                 }
    //             }

    //             // RUMUS FINAL GRAND TOTAL
    //             $poGrandTotal = ($poSubtotalGross - $poTotalDiscount) + $poTotalTax + $poChargeTotal - $poExtraDiscountTotal;

    //             // 5. UPDATE HEADER PO
    //             $po->update([
    //                 'bill_to_company_id'    => $request->billing_company_id,
    //                 'shipping_address'      => $request->shipping_address,
    //                 'payment_terms'         => $paymentTermName,
    //                 'notes'                 => $request->notes,
    //                 'global_discount_type'  => $globalDiscType,
    //                 'global_discount_value' => $globalDiscVal,
    //                 'subtotal'              => $poSubtotalGross,
    //                 'discount_total'        => $poTotalDiscount,
    //                 'tax_total'             => $poTotalTax,
    //                 'charge_total'          => $poChargeTotal,
    //                 'grand_total'           => $poGrandTotal,
    //             ]);

    //         });

    //         return redirect()->route('po.show', $id)->with('success', 'Perubahan Purchase Order dan File Lampiran berhasil disimpan!');

    //     } catch (\Exception $e) {
    //         Log::error('Error Update PO: ' . $e->getMessage());
    //         return back()->withInput()->with('error', $e->getMessage());
    //     }
    // }


    // public function update(Request $request, $id)
    // {
    //     // 1. Validasi dasar
    //     $request->validate([
    //         'billing_company_id'        => 'required|exists:companies,id',
    //         'payment_term_id'           => 'required|exists:payment_terms,id',
    //         'po_items'                  => 'required|array',
    //         // Validasi file baru yang diupload saat edit
    //         'po_items.*.attachments.*'  => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:5120',
    //         'delivery_date'             => 'required|date',
    //     ]);

    //     try {
    //         DB::transaction(function () use ($request, $id) {
    //             $po = \App\Models\PurchaseOrder::with('items')->findOrFail($id);

    //             if (!in_array(optional($po->status)->slug, ['draft', 'pending_approval'])) {
    //                 throw new \Exception('PO sudah diproses dan tidak bisa diedit lagi.');
    //             }

    //             $paymentTermName = null;
    //             if ($request->payment_term_id) {
    //                 $term = \App\Models\PaymentTerm::find($request->payment_term_id);
    //                 $paymentTermName = $term ? $term->name : null;
    //             }

    //             $poSubtotalGross = 0;
    //             $poTotalItemDiscount = 0;
    //             $poTotalTax = 0;

    //             $safePoNumber = str_replace('/', '-', $po->po_number);

    //             // 1. UPDATE ITEM BARANG & PROSES UPLOAD LAMPIRAN BARU
    //             foreach ($request->po_items as $itemId => $itemData) {
    //                 $poItem = \App\Models\PurchaseOrderItem::find($itemId);
    //                 if (!$poItem) continue;

    //                 $newQty = (float) ($itemData['qty'] ?? 0);
    //                 $price = (float) ($itemData['unit_price'] ?? 0);
    //                 $gross = $newQty * $price;

    //                 $discVal = (float) ($itemData['discount_value'] ?? 0);
    //                 $discType = $itemData['discount_type'] ?? 'fixed';
    //                 $discAmt = ($discType === 'percent') ? ($gross * ($discVal / 100)) : $discVal;

    //                 $dpp = $gross - $discAmt;

    //                 $taxAmt = 0;
    //                 $taxId = $itemData['tax_id'] ?? null;
    //                 if (!empty($taxId)) {
    //                     $tax = \App\Models\Tax::find($taxId);
    //                     if ($tax) $taxAmt = $dpp * ($tax->percent / 100);
    //                 }

    //                 // =======================================================
    //                 // TANGKAP FILE UPLOAD BARU (JIKA ADMIN MENAMBAH FILE)
    //                 // =======================================================
    //                 if (isset($itemData['attachments']) && is_array($itemData['attachments'])) {
    //                     foreach ($itemData['attachments'] as $file) {
    //                         if (is_file($file) && $file->isValid()) {
    //                             $originalName = $file->getClientOriginalName();
    //                             $filename = time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName);

    //                             // Simpan ke folder spesifik PO ini
    //                             $path = 'po_attachments/' . $safePoNumber . '/' . $filename;
    //                             Storage::disk('public')->put($path, file_get_contents($file));

    //                             // Catat di database
    //                             \App\Models\PurchaseOrderAttachment::create([
    //                                 'purchase_order_id' => $po->id,
    //                                 'file_name'         => $originalName,
    //                                 'file_path'         => $path,
    //                             ]);
    //                         }
    //                     }
    //                 }

    //                 $poSubtotalGross += $gross;
    //                 $poTotalItemDiscount += $discAmt;
    //                 $poTotalTax += $taxAmt;

    //                 // KOREKSI QTY DI PR ITEM (Jika Admin mengubah jumlah pesanan saat Edit PO)
    //                 $qtyDifference = $newQty - $poItem->qty_ordered;
    //                 if ($qtyDifference != 0) {
    //                     $prItem = \App\Models\PurchaseRequestItem::find($poItem->purchase_request_item_id);
    //                     if ($prItem) {
    //                         $prItem->ordered_qty = ($prItem->ordered_qty ?? 0) + $qtyDifference;
    //                         $prItem->save();
    //                     }
    //                 }

    //                 // Simpan perubahan ke item PO
    //                 $poItem->update([
    //                     'tax_id'          => $taxId ?: null,
    //                     'qty_ordered'     => $newQty,
    //                     'unit_price'      => $price,
    //                     'discount_type'   => $discType,
    //                     'discount_value'  => $discVal,
    //                     'discount_amount' => $discAmt,
    //                     'subtotal'        => $dpp,
    //                     'tax_amount'      => $taxAmt,
    //                     'delivery_date' => $request->delivery_date,
    //                 ]);
    //             }

    //             // 2. KALKULASI DISKON GLOBAL
    //             $globalDiscType = $request->global_discount_type ?? 'fixed';
    //             $globalDiscVal = (float) ($request->global_discount_value ?? 0);
    //             $poGlobalDiscount = ($globalDiscType === 'percent')
    //                                 ? (($poSubtotalGross - $poTotalItemDiscount) * ($globalDiscVal / 100))
    //                                 : $globalDiscVal;

    //             $poTotalDiscount = $poTotalItemDiscount + $poGlobalDiscount;

    //             // 3. UPDATE BIAYA TAMBAHAN (Hapus yang lama, simpan yang baru)
    //             \DB::table('purchase_order_charges')->where('purchase_order_id', $po->id)->delete();
    //             $poChargeTotal = 0;

    //             if ($request->has('charges')) {
    //                 foreach ($request->charges as $charge) {
    //                     if (!empty($charge['charge_type_id']) && !empty($charge['amount'])) {
    //                         $chargeMaster = \App\Models\ChargeType::find($charge['charge_type_id']);
    //                         $amt = (float) $charge['amount'];

    //                         \DB::table('purchase_order_charges')->insert([
    //                             'purchase_order_id' => $po->id,
    //                             'name'              => $chargeMaster ? $chargeMaster->name : 'Biaya Tambahan',
    //                             'amount'            => $amt,
    //                             'created_at'        => now(),
    //                             'updated_at'        => now(),
    //                         ]);
    //                         $poChargeTotal += $amt;
    //                     }
    //                 }
    //             }

    //             // 4. UPDATE POTONGAN TAMBAHAN (VOUCHER/MEMBER)
    //             \DB::table('purchase_order_discounts')->where('purchase_order_id', $po->id)->delete();
    //             $poExtraDiscountTotal = 0;

    //             if ($request->has('extra_discounts')) {
    //                 foreach ($request->extra_discounts as $disc) {
    //                     if (!empty($disc['discount_type_id']) && !empty($disc['amount'])) {
    //                         $discMaster = \App\Models\DiscountType::find($disc['discount_type_id']);
    //                         $amt = (float) $disc['amount'];

    //                         \DB::table('purchase_order_discounts')->insert([
    //                             'purchase_order_id' => $po->id,
    //                             'name'              => $discMaster ? $discMaster->name : 'Potongan Tambahan',
    //                             'amount'            => $amt,
    //                             'created_at'        => now(),
    //                             'updated_at'        => now(),
    //                         ]);
    //                         $poExtraDiscountTotal += $amt;
    //                     }
    //                 }
    //             }

    //             // RUMUS FINAL GRAND TOTAL
    //             $poGrandTotal = ($poSubtotalGross - $poTotalDiscount) + $poTotalTax + $poChargeTotal - $poExtraDiscountTotal;

    //             // 5. UPDATE HEADER PO
    //             $po->update([
    //                 'bill_to_company_id'    => $request->billing_company_id,
    //                 'shipping_address'      => $request->shipping_address,
    //                 'payment_terms'         => $paymentTermName,
    //                 'notes'                 => $request->notes,
    //                 'global_discount_type'  => $globalDiscType,
    //                 'global_discount_value' => $globalDiscVal,
    //                 'subtotal'              => $poSubtotalGross,
    //                 'discount_total'        => $poTotalDiscount,
    //                 'tax_total'             => $poTotalTax,
    //                 'charge_total'          => $poChargeTotal,
    //                 'grand_total'           => $poGrandTotal,
    //             ]);

    //             // 6. CATAT LOG RIWAYAT
    //             // Kita gunakan method class logHistory yang sudah kita buat.
    //             $this->logHistory($po->id, 'PO Direvisi', 'Perubahan pada rincian item, harga, diskon, atau lampiran telah disimpan.');

    //         });

    //         return redirect()->route('po.show', $id)->with('success', 'Perubahan Purchase Order dan File Lampiran berhasil disimpan!');

    //     } catch (\Exception $e) {
    //         \Log::error('Error Update PO: ' . $e->getMessage());
    //         return back()->withInput()->with('error', $e->getMessage());
    //     }
    // }


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

                    // =========================================================================
                    // TAMBAHAN: PROTEKSI QTY ORDER TIDAK MELEBIHI QTY PR
                    // =========================================================================
                    $prItem = \App\Models\PurchaseRequestItem::with('item')->find($poItem->purchase_request_item_id);
                    if ($prItem) {
                        // Hitung sisa kuantitas yang masih boleh di-PO-kan dari PR tersebut.
                        // Caranya: (Total diminta di PR) - (Total yang sudah di-PO di tempat lain)
                        // + (Qty dari PO ini *sebelum* diedit, karena ini mau dikembalikan/ditimpa)
                        $qtyAlreadyOrderedElsewhere = ($prItem->ordered_qty ?? 0) - $poItem->qty_ordered;
                        $maxAllowedQty = $prItem->qty - $qtyAlreadyOrderedElsewhere;

                        if ($newQty > $maxAllowedQty) {
                            $itemName = $prItem->item->name ?? 'Item';
                            throw new \Exception("Gagal: Kuantitas '{$itemName}' ({$newQty}) melebihi batas maksimal yang diizinkan ({$maxAllowedQty}).");
                        }

                        // KOREKSI QTY DI PR ITEM (Update nilai akumulasi ordered_qty)
                        $qtyDifference = $newQty - $poItem->qty_ordered;
                        if ($qtyDifference != 0) {
                            $prItem->ordered_qty = ($prItem->ordered_qty ?? 0) + $qtyDifference;
                            $prItem->save();
                        }
                    }
                    // =========================================================================

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

                    // =======================================================
                    // TANGKAP FILE UPLOAD BARU (JIKA ADMIN MENAMBAH FILE)
                    // =======================================================
                    if (isset($itemData['attachments']) && is_array($itemData['attachments'])) {
                        foreach ($itemData['attachments'] as $file) {
                            if (is_file($file) && $file->isValid()) {
                                $originalName = $file->getClientOriginalName();
                                $filename = time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName);

                                // Simpan ke folder spesifik PO ini
                                $path = 'po_attachments/' . $safePoNumber . '/' . $filename;
                                Storage::disk('public')->put($path, file_get_contents($file));

                                // Catat di database
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

                    // Simpan perubahan ke item PO
                    $poItem->update([
                        'tax_id'          => $taxId ?: null,
                        'qty_ordered'     => $newQty,
                        'unit_price'      => $price,
                        'discount_type'   => $discType,
                        'discount_value'  => $discVal,
                        'discount_amount' => $discAmt,
                        'subtotal'        => $dpp,
                        'tax_amount'      => $taxAmt,
                        // Pastikan delivery_date ditangkap jika form edit per item memilikinya
                        // Jika delivery_date adalah global untuk 1 PO, pindahkan ini ke Header PO (po->update)
                        // 'delivery_date' => $request->delivery_date,
                    ]);
                }

                // 2. KALKULASI DISKON GLOBAL
                $globalDiscType = $request->global_discount_type ?? 'fixed';
                $globalDiscVal = (float) ($request->global_discount_value ?? 0);
                $poGlobalDiscount = ($globalDiscType === 'percent')
                                    ? (($poSubtotalGross - $poTotalItemDiscount) * ($globalDiscVal / 100))
                                    : $globalDiscVal;

                $poTotalDiscount = $poTotalItemDiscount + $poGlobalDiscount;

                // 3. UPDATE BIAYA TAMBAHAN (Hapus yang lama, simpan yang baru)
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

                // 4. UPDATE POTONGAN TAMBAHAN (VOUCHER/MEMBER)
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

                // RUMUS FINAL GRAND TOTAL
                $poGrandTotal = ($poSubtotalGross - $poTotalDiscount) + $poTotalTax + $poChargeTotal - $poExtraDiscountTotal;

                // 5. UPDATE HEADER PO
                $po->update([
                    'bill_to_company_id'    => $request->billing_company_id,
                    'shipping_address'      => $request->shipping_address,
                    'payment_terms'         => $paymentTermName,
                    'notes'                 => $request->notes,
                    'delivery_date'         => $request->delivery_date, // Menyimpan Target Pengiriman di Header
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



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }


    // PurchaseOrderController.php

    // public function approve($id)
    // {
    //     $po = PurchaseOrder::findOrFail($id);

    //     // Validasi: Hanya bisa approve jika status masih Pending
    //     if ($po->status !== 'PENDING_APPROVAL') {
    //         return back()->with('error', 'PO ini sudah diproses sebelumnya.');
    //     }

    //     $po->update([
    //         'status'      => 'ISSUED', // Status Final
    //         'approved_by' => auth()->id(),
    //         'approved_at' => now(),
    //     ]);

    //     // Opsional: Kirim Email Notifikasi ke Vendor atau Pembuat PO di sini

    //     return back()->with('success', 'Purchase Order berhasil disetujui (ISSUED).');
    // }

    // public function reject(Request $request, $id)
    // {
    //     $po = PurchaseOrder::findOrFail($id);

    //     if ($po->status !== 'PENDING_APPROVAL') {
    //         return back()->with('error', 'PO ini sudah diproses sebelumnya.');
    //     }

    //     // Validasi alasan penolakan
    //     $request->validate(['reject_reason' => 'required|string|max:255']);

    //     $po->update([
    //         'status' => 'REJECTED',
    //         'notes'  => $po->notes . "\n[REJECTED]: " . $request->reject_reason, // Tambahkan alasan ke notes
    //     ]);

    //     return back()->with('success', 'Purchase Order ditolak.');
    // }


   public function index(\Illuminate\Http\Request $request)
    {
        // Tangkap inputan dari kotak pencarian
        $search = $request->input('search');

        // 1. AMBIL DATA PR SIAP DIPROSES (Approved & Partial PO)
        $statusApproved = \App\Models\Status::where('type', 'PR')->where('slug', 'approved')->first();
        $statusPartial  = \App\Models\Status::where('type', 'PR')->where('slug', 'partial_po')->first();

        $statusIds = array_filter([
            $statusApproved ? $statusApproved->id : null,
            $statusPartial ? $statusPartial->id : null
        ]);

        // 2. QUERY PR
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

        // 3. QUERY RIWAYAT PO (Kini Lebih Tajam: Mencari di PT Penanggung PO juga!)
        $purchaseOrders = \App\Models\PurchaseOrder::with(['vendor', 'company', 'purchaseRequest.company', 'status'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    // Cari di Nomor PO
                    $q->where('po_number', 'like', "%{$search}%")
                      // Cari di Nama Vendor
                      ->orWhereHas('vendor', function ($qVendor) use ($search) {
                          $qVendor->where('name', 'like', "%{$search}%");
                      })
                      // Fitur Baru: Cari di PT Penanggung (Billing Entity) milik PO!
                      ->orWhereHas('company', function ($qPoComp) use ($search) {
                          $qPoComp->where('name', 'like', "%{$search}%");
                      })
                      // Cari di data PR yang terhubung
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

        // 4. KIRIM KEDUA DATA KE VIEW
        return view('po.index', compact('readyPrs', 'purchaseOrders'));
    }


    public function processPr($id)
    {
        // 1. Ambil data PR beserta item dan relasinya
        $pr = \App\Models\PurchaseRequest::with(['items.item', 'items.vendorQuotes.vendor', 'user', 'company'])->findOrFail($id);

        // ====================================================================
        // PENGECEKAN STATUS ANTI-GAGAL (FLEXIBLE DETECTION)
        // ====================================================================
        // Kita ubah huruf kapital semua agar seragam
        $prStatus = strtoupper(trim($pr->status ?? ''));

        // Kata kunci yang diizinkan lewat
        $allowedKeywords = ['APPROVED', 'PARTIAL', 'DISETUJUI', 'FINAL'];

        $isAllowed = false;
        foreach ($allowedKeywords as $keyword) {
            // Cek apakah di dalam status PR terdapat salah satu kata kunci di atas
            if (str_contains($prStatus, $keyword)) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            return redirect()->back()->with('error', 'Akses Ditolak: PR ini tidak bisa diproses karena status sistemnya mendeteksi: "' . $prStatus . '". Hanya PR yang Approved/Disetujui yang bisa dibuatkan PO.');
        }
        // ====================================================================

        // 2. Ambil Riwayat PO yang terkait dengan PR ini
        $existingPOs = \App\Models\PurchaseOrder::with(['vendor', 'status'])
                        ->where('purchase_request_id', $id)
                        ->orderBy('created_at', 'desc')
                        ->get();

        // 3. Tentukan Vendor Terpilih (Default dari penawaran vendor pertama jika ada)
        $selectedVendor = null;
        $firstQuote = $pr->items->flatMap->vendorQuotes->first();
        if ($firstQuote && $firstQuote->vendor) {
            $selectedVendor = $firstQuote->vendor;
        }

        // 4. Deteksi Mata Uang Default
        $defaultCurrency = 'IDR';
        if ($firstQuote && !empty($firstQuote->currency)) {
            $defaultCurrency = $firstQuote->currency;
        }

        // 5. Setup Alamat Pengiriman Default
        $defaultShippingAddress = $pr->items->first()->delivery_address ?? null;
        if (empty($defaultShippingAddress)) {
            if ($pr->company && !empty($pr->company->address)) {
                $defaultShippingAddress = $pr->company->address;
            } else {
                $headOffice = \App\Models\Company::where('is_head_office', true)->first();
                $defaultShippingAddress = $headOffice ? $headOffice->address : '';
            }
        }

        // 6. Load data master pendukung untuk form dropdown
        $vendors = \App\Models\Vendor::orderBy('name')->get();
        $companies = \App\Models\Company::orderBy('name')->get();
        $paymentTerms = \App\Models\PaymentTerm::orderBy('days')->get();
        $taxes = \App\Models\Tax::where('is_active', true)->orderBy('percent')->get();
        $chargeTypes = \App\Models\ChargeType::where('is_active', true)->get();
        $currencies = \App\Models\Currency::where('is_active', true)->get();
        $discountTypes = \App\Models\DiscountType::where('is_active', true)->get();

        $all_items = \App\Models\Item::orderBy('name')->get();

        return view('po.process_pr', compact(
            'pr',
            'existingPOs',
            'vendors',
            'companies',
            'paymentTerms',
            'taxes',
            'chargeTypes',
            'currencies',
            'discountTypes',
            'selectedVendor',
            'defaultCurrency',
            'defaultShippingAddress',
            'all_items'
        ));
    }

    // public function storeFromPr(Request $request, $id)
    // {
    //     // 1. Validasi dasar
    //     $request->validate([
    //         'billing_company_id'   => 'required|exists:companies,id',
    //         'payment_term_id'      => 'required|exists:payment_terms,id',
    //         'po_items'             => 'required|array',
    //     ]);

    //     try {
    //         DB::transaction(function () use ($request, $id) {

    //             // FILTER: Buang item yang vendornya KOSONG atau Qty = 0
    //             $itemsToProcess = collect($request->po_items)->filter(function ($item) {
    //                 $vendorId = trim($item['vendor_id'] ?? '');
    //                 $qty = (float) ($item['qty'] ?? 0);
    //                 return $vendorId !== '' && $qty > 0;
    //             });

    //             if ($itemsToProcess->isEmpty()) {
    //                 throw new \Exception('Gagal: Anda harus memilih Vendor Aktual minimal untuk 1 barang!');
    //             }

    //             // Proteksi Ganda (Anti Double Order)
    //             foreach ($itemsToProcess as $itemData) {
    //                 $prItem = \App\Models\PurchaseRequestItem::with('item')->find($itemData['pr_item_id']);
    //                 if ($prItem) {
    //                     $orderedQty = $prItem->ordered_qty ?? 0;
    //                     $sisaQty = $prItem->qty - $orderedQty;
    //                     $reqQty = (float) $itemData['qty'];
    //                     if ($reqQty > $sisaQty) {
    //                         $itemName = $prItem->item->name ?? 'Item Tidak Diketahui';
    //                         throw new \Exception("Gagal: Qty pesanan untuk '{$itemName}' ({$reqQty}) melebihi sisa barang yang tersedia di PR ({$sisaQty}).");
    //                     }
    //                 }
    //             }

    //             // Kelompokkan sisa item yang valid berdasarkan Vendor
    //             $itemsByVendor = $itemsToProcess->groupBy('vendor_id');

    //             $statusPending = \App\Models\Status::where('type', 'PO')
    //                                 ->whereIn('slug', ['draft', 'pending_approval'])
    //                                 ->first();

    //             $paymentTermName = null;
    //             if ($request->payment_term_id) {
    //                 $term = \App\Models\PaymentTerm::find($request->payment_term_id);
    //                 $paymentTermName = $term ? $term->name : null;
    //             }

    //             // LOOPING PER VENDOR (Setiap Vendor = 1 PO)
    //             foreach ($itemsByVendor as $vendorId => $items) {

    //                 $newPoNumber = $this->generatePoNumber($request->billing_company_id);
    //                 $firstItem = collect($items)->first();
    //                 $poCurrency = $firstItem['currency'] ?? 'IDR';

    //                 $poSubtotalGross = 0;
    //                 $poTotalItemDiscount = 0;
    //                 $poTotalTax = 0;

    //                 $processedLineItems = [];
    //                 // Array sementara untuk menampung file dari semua item di PO ini agar disave belakangan
    //                 $filesToSave = [];

    //                 $safePoNumber = str_replace('/', '-', $newPoNumber);

    //                 foreach ($items as $itemData) {
    //                     $qty = (float) ($itemData['qty'] ?? 0);
    //                     $price = (float) ($itemData['unit_price'] ?? 0);
    //                     $gross = $qty * $price;

    //                     $discVal = (float) ($itemData['discount_value'] ?? 0);
    //                     $discType = $itemData['discount_type'] ?? 'fixed';
    //                     $discAmt = ($discType === 'percent') ? ($gross * ($discVal / 100)) : $discVal;

    //                     $dpp = $gross - $discAmt;

    //                     $taxAmt = 0;
    //                     $taxId = $itemData['tax_id'] ?? null;
    //                     if (!empty($taxId)) {
    //                         $tax = \App\Models\Tax::find($taxId);
    //                         if ($tax) {
    //                             $taxAmt = $dpp * ($tax->percent / 100);
    //                         }
    //                     }

    //                     // =======================================================
    //                     // TANGKAP FILE UPLOAD PER-BARIS ITEM (Jika Ada)
    //                     // =======================================================
    //                     if (isset($itemData['attachments']) && is_array($itemData['attachments'])) {
    //                         foreach ($itemData['attachments'] as $file) {
    //                             if ($file->isValid()) {
    //                                 $originalName = $file->getClientOriginalName();
    //                                 $filename = time() . '_' . str_replace(' ', '_', $originalName);
    //                                 // Lokasi: po_attachments/PO-HO-2026-02-0001/file.pdf
    //                                 $path = 'po_attachments/' . $safePoNumber . '/' . $filename;

    //                                 // Simpan fisik file ke folder public
    //                                 Storage::disk('public')->put($path, file_get_contents($file));

    //                                 // Simpan info ke array untuk di-insert setelah $po di-create
    //                                 $filesToSave[] = [
    //                                     'file_name' => $originalName,
    //                                     'file_path' => $path
    //                                 ];
    //                             }
    //                         }
    //                     }

    //                     $poSubtotalGross += $gross;
    //                     $poTotalItemDiscount += $discAmt;
    //                     $poTotalTax += $taxAmt;

    //                     $processedLineItems[] = [
    //                         'itemData' => $itemData, 'discAmt' => $discAmt, 'dpp' => $dpp,
    //                         'taxId' => $taxId, 'taxAmt' => $taxAmt, 'qty' => $qty,
    //                         'price' => $price, 'discType' => $discType, 'discVal' => $discVal
    //                     ];
    //                 }

    //                 // Perhitungan Diskon Global
    //                 $globalDiscType = $request->global_discount_type ?? 'fixed';
    //                 $globalDiscVal = (float) ($request->global_discount_value ?? 0);
    //                 $poGlobalDiscount = ($globalDiscType === 'percent')
    //                                     ? (($poSubtotalGross - $poTotalItemDiscount) * ($globalDiscVal / 100))
    //                                     : $globalDiscVal;

    //                 $poTotalDiscount = $poTotalItemDiscount + $poGlobalDiscount;
    //                 $poGrandTotal = ($poSubtotalGross - $poTotalDiscount) + $poTotalTax;

    //                 // A. BUAT HEADER PO
    //                 $po = \App\Models\PurchaseOrder::create([
    //                     'po_number'             => $newPoNumber,
    //                     'purchase_request_id'   => $id,
    //                     'vendor_id'             => $vendorId,
    //                     'bill_to_company_id'    => $request->billing_company_id,
    //                     'status_id'             => $statusPending ? $statusPending->id : null,
    //                     'po_date'               => now(),
    //                     'created_by'            => auth()->id(),
    //                     'is_sent'               => false,
    //                     'currency'              => $poCurrency,
    //                     'shipping_address'      => $request->shipping_address,
    //                     'payment_terms'         => $paymentTermName,
    //                     'notes'                 => $request->notes,

    //                     'global_discount_type'  => $globalDiscType,
    //                     'global_discount_value' => $globalDiscVal,
    //                     'subtotal'              => $poSubtotalGross,
    //                     'discount_total'        => $poTotalDiscount,
    //                     'tax_total'             => $poTotalTax,
    //                     'charge_total'          => 0,
    //                     'grand_total'           => $poGrandTotal,
    //                 ]);

    //                 // B. SIMPAN ITEM BARANG
    //                 foreach ($processedLineItems as $line) {
    //                     \App\Models\PurchaseOrderItem::create([
    //                         'purchase_order_id'        => $po->id,
    //                         'item_id'                  => $line['itemData']['item_id'],
    //                         'purchase_request_item_id' => $line['itemData']['pr_item_id'],
    //                         'tax_id'                   => $line['taxId'] ?: null,
    //                         'qty_ordered'              => $line['qty'],
    //                         'qty_received'             => 0,
    //                         'unit_price'               => $line['price'],
    //                         'discount_type'            => $line['discType'],
    //                         'discount_value'           => $line['discVal'],
    //                         'discount_amount'          => $line['discAmt'],
    //                         'subtotal'                 => $line['dpp'],
    //                         'tax_amount'               => $line['taxAmt'],
    //                     ]);

    //                     $prItem = \App\Models\PurchaseRequestItem::find($line['itemData']['pr_item_id']);
    //                     if ($prItem) {
    //                         $prItem->ordered_qty = ($prItem->ordered_qty ?? 0) + $line['qty'];
    //                         $prItem->save();
    //                     }
    //                 }

    //                 // C. SIMPAN CHARGES (BIAYA TAMBAHAN)
    //                 $poChargeTotal = 0;
    //                 if ($request->has('charges')) {
    //                     foreach ($request->charges as $charge) {
    //                         if (!empty($charge['charge_type_id']) && !empty($charge['amount'])) {
    //                             $chargeMaster = \App\Models\ChargeType::find($charge['charge_type_id']);
    //                             $amt = (float) $charge['amount'];

    //                             DB::table('purchase_order_charges')->insert([
    //                                 'purchase_order_id' => $po->id,
    //                                 'name'              => $chargeMaster ? $chargeMaster->name : 'Biaya Tambahan',
    //                                 'amount'            => $amt,
    //                                 'created_at'        => now(),
    //                                 'updated_at'        => now(),
    //                             ]);
    //                             $poChargeTotal += $amt;
    //                         }
    //                     }
    //                 }

    //                 // D. SIMPAN DISCOUNTS (POTONGAN TAMBAHAN VOUCHER/MEMBER)
    //                 $poExtraDiscountTotal = 0;
    //                 if ($request->has('extra_discounts')) {
    //                     foreach ($request->extra_discounts as $disc) {
    //                         if (!empty($disc['discount_type_id']) && !empty($disc['amount'])) {
    //                             $discMaster = \App\Models\DiscountType::find($disc['discount_type_id']);
    //                             $amt = (float) $disc['amount'];

    //                             DB::table('purchase_order_discounts')->insert([
    //                                 'purchase_order_id' => $po->id,
    //                                 'name'              => $discMaster ? $discMaster->name : 'Potongan Tambahan',
    //                                 'amount'            => $amt,
    //                                 'created_at'        => now(),
    //                                 'updated_at'        => now(),
    //                             ]);
    //                             $poExtraDiscountTotal += $amt;
    //                         }
    //                     }
    //                 }

    //                 // E. UPDATE FINAL GRAND TOTAL JIKA ADA ONGKIR ATAU POTONGAN TAMBAHAN
    //                 if ($poChargeTotal > 0 || $poExtraDiscountTotal > 0) {
    //                     $po->update([
    //                         'charge_total' => $poChargeTotal,
    //                         'grand_total'  => $po->grand_total + $poChargeTotal - $poExtraDiscountTotal
    //                     ]);
    //                 }

    //                 // ==========================================================
    //                 // F. SIMPAN DATA LAMPIRAN KE DATABASE PO INI
    //                 // ==========================================================
    //                 if (!empty($filesToSave)) {
    //                     foreach ($filesToSave as $uFile) {
    //                         \App\Models\PurchaseOrderAttachment::create([
    //                             'purchase_order_id' => $po->id,
    //                             'file_name'         => $uFile['file_name'],
    //                             'file_path'         => $uFile['file_path'],
    //                         ]);
    //                     }
    //                 }

    //             } // END LOOP VENDOR

    //             // G. UPDATE STATUS PR OTOMATIS (Full vs Partial)
    //             $pr = \App\Models\PurchaseRequest::with('items')->find($id);
    //             if ($pr) {
    //                 $allFulfilled = true;
    //                 foreach ($pr->items as $item) {
    //                     $orderedQty = $item->ordered_qty ?? 0;
    //                     if ($orderedQty < $item->qty) {
    //                         $allFulfilled = false;
    //                         break;
    //                     }
    //                 }

    //                 if ($allFulfilled) {
    //                     $statusPoIssued = \App\Models\Status::where('type', 'PR')->where('slug', 'po_issued')->first();
    //                     if ($statusPoIssued) $pr->update(['status_id' => $statusPoIssued->id]);
    //                 } else {
    //                     $statusPartial = \App\Models\Status::where('type', 'PR')->where('slug', 'partial_po')->first();
    //                     if ($statusPartial) $pr->update(['status_id' => $statusPartial->id]);
    //                 }
    //             }
    //         });

    //         $this->logHistory($po->id, 'PO Diterbitkan', 'PO baru diterbitkan dari PR #' . $pr->pr_number);
    //         return redirect()->route('po.index')->with('success', 'Purchase Order berhasil diterbitkan beserta lampirannya!');

    //     } catch (\Exception $e) {
    //         Log::error('Error Terbitkan PO: ' . $e->getMessage());
    //         return back()->withInput()->with('error', $e->getMessage());
    //     }
    // }


    public function storeFromPr(Request $request, $id)
    {
        // 1. Validasi dasar
        $request->validate([
            'billing_company_id'   => 'required|exists:companies,id',
            'payment_term_id'      => 'required|exists:payment_terms,id',
            'po_items'             => 'required|array',
            'delivery_date' => 'required|date',
        ]);

        try {
            DB::transaction(function () use ($request, $id) {

                // FILTER: Buang item yang vendornya KOSONG atau Qty = 0
                $itemsToProcess = collect($request->po_items)->filter(function ($item) {
                    $vendorId = trim($item['vendor_id'] ?? '');
                    $qty = (float) ($item['qty'] ?? 0);
                    return $vendorId !== '' && $qty > 0;
                });

                if ($itemsToProcess->isEmpty()) {
                    throw new \Exception('Gagal: Anda harus memilih Vendor Aktual minimal untuk 1 barang!');
                }

                // Proteksi Ganda (Anti Double Order)
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

                // Kelompokkan sisa item yang valid berdasarkan Vendor
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

                // LOOPING PER VENDOR (Setiap Vendor = 1 PO)
                foreach ($itemsByVendor as $vendorId => $items) {

                    $newPoNumber = $this->generatePoNumber($request->billing_company_id);
                    $firstItem = collect($items)->first();
                    $poCurrency = $firstItem['currency'] ?? 'IDR';

                    $poSubtotalGross = 0;
                    $poTotalItemDiscount = 0;
                    $poTotalTax = 0;

                    $processedLineItems = [];
                    // Array sementara untuk menampung file dari semua item di PO ini agar disave belakangan
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

                        // =======================================================
                        // TANGKAP FILE UPLOAD PER-BARIS ITEM (Jika Ada)
                        // =======================================================
                        if (isset($itemData['attachments']) && is_array($itemData['attachments'])) {
                            foreach ($itemData['attachments'] as $file) {
                                if (is_file($file) && $file->isValid()) {
                                    $originalName = $file->getClientOriginalName();
                                    $filename = time() . '_' . str_replace(' ', '_', $originalName);
                                    // Lokasi: po_attachments/PO-HO-2026-02-0001/file.pdf
                                    $path = 'po_attachments/' . $safePoNumber . '/' . $filename;

                                    // Simpan fisik file ke folder public
                                    Storage::disk('public')->put($path, file_get_contents($file));

                                    // Simpan info ke array untuk di-insert setelah $po di-create
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

                    // Perhitungan Diskon Global
                    $globalDiscType = $request->global_discount_type ?? 'fixed';
                    $globalDiscVal = (float) ($request->global_discount_value ?? 0);
                    $poGlobalDiscount = ($globalDiscType === 'percent')
                                        ? (($poSubtotalGross - $poTotalItemDiscount) * ($globalDiscVal / 100))
                                        : $globalDiscVal;

                    $poTotalDiscount = $poTotalItemDiscount + $poGlobalDiscount;
                    $poGrandTotal = ($poSubtotalGross - $poTotalDiscount) + $poTotalTax;

                    // A. BUAT HEADER PO
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

                    // B. SIMPAN ITEM BARANG
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

                    // C. SIMPAN CHARGES (BIAYA TAMBAHAN)
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

                    // D. SIMPAN DISCOUNTS (POTONGAN TAMBAHAN VOUCHER/MEMBER)
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

                    // E. UPDATE FINAL GRAND TOTAL JIKA ADA ONGKIR ATAU POTONGAN TAMBAHAN
                    if ($poChargeTotal > 0 || $poExtraDiscountTotal > 0) {
                        $po->update([
                            'charge_total' => $poChargeTotal,
                            'grand_total'  => $po->grand_total + $poChargeTotal - $poExtraDiscountTotal
                        ]);
                    }

                    // ==========================================================
                    // F. SIMPAN DATA LAMPIRAN KE DATABASE PO INI
                    // ==========================================================
                    if (!empty($filesToSave)) {
                        foreach ($filesToSave as $uFile) {
                            \App\Models\PurchaseOrderAttachment::create([
                                'purchase_order_id' => $po->id,
                                'file_name'         => $uFile['file_name'],
                                'file_path'         => $uFile['file_path'],
                            ]);
                        }
                    }

                    // PERBAIKAN: LOG HISTORY DILETAKKAN DI SINI, DI DALAM LOOP VENDOR
                    // Memastikan setiap PO baru yang tercetak mendapatkan log-nya masing-masing
                    $this->logHistory($po->id, 'PO Diterbitkan', 'PO baru diterbitkan dari PR #' . $prNumber);

                } // END LOOP VENDOR

                // G. UPDATE STATUS PR OTOMATIS (Full vs Partial)
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


    /**
     * Generate Nomor PO Otomatis
     * Format: PO/KODE_PT/TAHUN/BULAN/NOMOR_URUT (Contoh: PO/HO/2026/02/0001)
     */
    private function generatePoNumber($companyId)
    {
        $company = \App\Models\Company::find($companyId);
        $companyCode = $company ? strtoupper($company->code ?? 'PT') : 'PT';

        $year = date('Y');
        $month = date('m');

        // PERBAIKAN: Ganti 'billing_company_id' menjadi 'company_id'
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
        // 1. Ambil data PO beserta relasi utamanya
        $po = \App\Models\PurchaseOrder::with([
            'vendor',
            'purchaseRequest.user',
            'status',
            'items.item',
            'items.tax' // Pastikan relasi ini sudah Anda tambahkan sebelumnya, atau hapus baris ini jika masih error
        ])->findOrFail($id);

        // 2. Ambil data perusahaan penanggung (Bill To)
        $company = \App\Models\Company::find($po->bill_to_company_id);

        // 3. Ambil data biaya tambahan (Charges)
        $charges = DB::table('purchase_order_charges')
                    ->where('purchase_order_id', $id)
                    ->get();

        // 4. TAMBAHAN BARU: Ambil data potongan tambahan (Discounts/Voucher)
        $extraDiscounts = DB::table('purchase_order_discounts')
                            ->where('purchase_order_id', $id)
                            ->get();

        // 5. Kirim semua data ke View
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
                return redirect()->back()->with('success', 'Purchase Order berhasil diajukan untuk proses persetujuan!');
            }
            return redirect()->back()->with('error', 'Gagal: Status Pending Approval tidak ditemukan di database.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // 2. Atasan menyetujui PO
//     public function approve($id)
//     {
//         try {
//             $po = \App\Models\PurchaseOrder::findOrFail($id);

//             // PERBAIKAN: Ganti 'approved' menjadi 'issued' sesuai dengan ID 9 di database Anda
//             $statusApproved = \App\Models\Status::where('type', 'PO')->where('slug', 'issued')->first();

//             if ($statusApproved) {
//                 $po->update([
//                     'status_id'   => $statusApproved->id,
//                     'approved_by' => auth()->id(), // Mencatat ID user yang menyetujui
//                     // 'approved_at' => now(), // Buka komentar ini jika Anda punya kolom approved_at di tabel
//                 ]);
//                 return redirect()->back()->with('success', 'Hebat! Purchase Order telah resmi disetujui dan berstatus Terbit (Issued).');
//             }
//             return redirect()->back()->with('error', 'Gagal: Status Issued (Terbit) tidak ditemukan di database.');
//         } catch (\Exception $e) {
//             return redirect()->back()->with('error', $e->getMessage());
//         }
//     }

//     // 3. Atasan menolak PO
//     public function reject($id)
//     {
//         try {
//             $po = \App\Models\PurchaseOrder::findOrFail($id);
//             $statusRejected = \App\Models\Status::where('type', 'PO')->where('slug', 'rejected')->first();

//             if ($statusRejected) {
//                 $po->update(['status_id' => $statusRejected->id]);
//                 return redirect()->back()->with('warning', 'Purchase Order telah ditolak dan dikembalikan.');
//             }
//             return redirect()->back()->with('error', 'Gagal: Status Rejected tidak ditemukan di database.');
//         } catch (\Exception $e) {
//             return redirect()->back()->with('error', $e->getMessage());
//         }
//     }


//     // ==========================================
//     // FUNGSI HAPUS FILE LAMPIRAN PO
//     // ==========================================
//     public function deleteAttachment($id)
//     {
//         try {
//             $attachment = \App\Models\PurchaseOrderAttachment::findOrFail($id);

//             // 1. Hapus fisik file dari brankas server (storage)
//             if (Storage::disk('public')->exists($attachment->file_path)) {
//                 Storage::disk('public')->delete($attachment->file_path);
//             }

//             // 2. Hapus rekam jejak dari database
//             $attachment->delete();

//             return redirect()->back()->with('success', 'File lampiran berhasil dihapus secara permanen.');
//         } catch (\Exception $e) {
//             Log::error('Error Hapus Lampiran: ' . $e->getMessage());
//             return redirect()->back()->with('error', 'Gagal menghapus file: ' . $e->getMessage());
//         }
//     }


//     // ==========================================
//     // FUNGSI MEMBATALKAN (CANCEL) PO
//     // ==========================================
//   public function cancel($id)
//     {
//         try {
//             DB::transaction(function () use ($id) {
//                 $po = \App\Models\PurchaseOrder::with('items')->findOrFail($id);

//                 // 1. PERBAIKAN: Gunakan slug 'canceled' sesuai dengan ID 14 di database Anda
//                 $statusCanceled = \App\Models\Status::where('type', 'PO')->where('slug', 'canceled')->first();

//                 if (!$statusCanceled) {
//                     throw new \Exception('Status pembatalan tidak ditemukan di database.');
//                 }

//                 // 2. Kembalikan (Rollback) Qty ke PR Item
//                 foreach ($po->items as $poItem) {
//                     if ($poItem->purchase_request_item_id) {
//                         $prItem = \App\Models\PurchaseRequestItem::find($poItem->purchase_request_item_id);
//                         if ($prItem) {
//                             // Kurangi ordered_qty dengan qty yang batal dipesan (jangan sampai minus)
//                             $prItem->ordered_qty = max(0, $prItem->ordered_qty - $poItem->qty_ordered);
//                             $prItem->save();
//                         }
//                     }
//                 }

//                 // 3. Update Status PR Induk (Jika semua PR Item kembali kosong = 'approved', jika sebagian = 'partial_po')
//                 if ($po->purchase_request_id) {
//                     $pr = \App\Models\PurchaseRequest::with('items')->find($po->purchase_request_id);
//                     if ($pr) {
//                         $totalOrdered = $pr->items->sum('ordered_qty');

//                         if ($totalOrdered == 0) {
//                             // Semua pesanan batal, kembali murni ke status Approved
//                             $statusPrApproved = \App\Models\Status::where('type', 'PR')->where('slug', 'approved')->first();
//                             if ($statusPrApproved) $pr->update(['status_id' => $statusPrApproved->id]);
//                         } else {
//                             // Masih ada PO lain dari PR ini yang aktif
//                             $statusPrPartial = \App\Models\Status::where('type', 'PR')->where('slug', 'partial_po')->first();
//                             if ($statusPrPartial) $pr->update(['status_id' => $statusPrPartial->id]);
//                         }
//                     }
//                 }

//                 // 4. Ubah status PO ini menjadi Canceled
//                 $po->update(['status_id' => $statusCanceled->id]);
//             });

//             return redirect()->back()->with('success', 'Purchase Order berhasil DIBATALKAN. Kuantitas barang telah dikembalikan ke PR.');

//         } catch (\Exception $e) {
//             \Log::error('Error Cancel PO: ' . $e->getMessage());
//             return redirect()->back()->with('error', 'Gagal membatalkan PO: ' . $e->getMessage());
//         }
//     }



    // ==========================================
    // 2. Atasan menyetujui PO
    // ==========================================
    public function approve($id)
    {
        try {
            $po = \App\Models\PurchaseOrder::findOrFail($id);

            // PERBAIKAN: Ganti 'approved' menjadi 'issued' sesuai dengan ID 9 di database Anda
            $statusApproved = \App\Models\Status::where('type', 'PO')->where('slug', 'issued')->first();

            if ($statusApproved) {
                $po->update([
                    'status_id'   => $statusApproved->id,
                    'approved_by' => auth()->id(), // Mencatat ID user yang menyetujui
                    // 'approved_at' => now(), // Buka komentar ini jika Anda punya kolom approved_at di tabel
                ]);

                // CATAT LOG RIWAYAT
                $this->logHistory($po->id, 'PO Disetujui', 'PO telah disetujui oleh atasan dan resmi berstatus Terbit (Issued).');

                return redirect()->back()->with('success', 'Hebat! Purchase Order telah resmi disetujui dan berstatus Terbit (Issued).');
            }
            return redirect()->back()->with('error', 'Gagal: Status Issued (Terbit) tidak ditemukan di database.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // ==========================================
    // 3. Atasan menolak PO
    // ==========================================
    public function reject($id)
    {
        try {
            $po = \App\Models\PurchaseOrder::findOrFail($id);
            $statusRejected = \App\Models\Status::where('type', 'PO')->where('slug', 'rejected')->first();

            if ($statusRejected) {
                $po->update(['status_id' => $statusRejected->id]);

                // CATAT LOG RIWAYAT
                $this->logHistory($po->id, 'PO Ditolak', 'PO telah ditolak oleh atasan dan dikembalikan.');

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

            // Simpan info ID PO dan Nama File sebelum data di-delete untuk keperluan log
            $poId = $attachment->purchase_order_id;
            $fileName = $attachment->file_name;

            // 1. Hapus fisik file dari brankas server (storage)
            if (\Storage::disk('public')->exists($attachment->file_path)) {
                \Storage::disk('public')->delete($attachment->file_path);
            }

            // 2. Hapus rekam jejak dari database
            $attachment->delete();

            // 3. CATAT LOG RIWAYAT PENGHAPUSAN FILE
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

                // 1. PERBAIKAN: Gunakan slug 'canceled' sesuai dengan ID 14 di database Anda
                $statusCanceled = \App\Models\Status::where('type', 'PO')->where('slug', 'canceled')->first();

                if (!$statusCanceled) {
                    throw new \Exception('Status pembatalan tidak ditemukan di database.');
                }

                // 2. Kembalikan (Rollback) Qty ke PR Item
                foreach ($po->items as $poItem) {
                    if ($poItem->purchase_request_item_id) {
                        $prItem = \App\Models\PurchaseRequestItem::find($poItem->purchase_request_item_id);
                        if ($prItem) {
                            // Kurangi ordered_qty dengan qty yang batal dipesan (jangan sampai minus)
                            $prItem->ordered_qty = max(0, $prItem->ordered_qty - $poItem->qty_ordered);
                            $prItem->save();
                        }
                    }
                }

                // 3. Update Status PR Induk (Jika semua PR Item kembali kosong = 'approved', jika sebagian = 'partial_po')
                if ($po->purchase_request_id) {
                    $pr = \App\Models\PurchaseRequest::with('items')->find($po->purchase_request_id);
                    if ($pr) {
                        $totalOrdered = $pr->items->sum('ordered_qty');

                        if ($totalOrdered == 0) {
                            // Semua pesanan batal, kembali murni ke status Approved
                            $statusPrApproved = \App\Models\Status::where('type', 'PR')->where('slug', 'approved')->first();
                            if ($statusPrApproved) $pr->update(['status_id' => $statusPrApproved->id]);
                        } else {
                            // Masih ada PO lain dari PR ini yang aktif
                            $statusPrPartial = \App\Models\Status::where('type', 'PR')->where('slug', 'partial_po')->first();
                            if ($statusPrPartial) $pr->update(['status_id' => $statusPrPartial->id]);
                        }
                    }
                }

                // 4. Ubah status PO ini menjadi Canceled
                $po->update(['status_id' => $statusCanceled->id]);

                // 5. CATAT LOG RIWAYAT
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




    // public function processPr($id)
    // {
    //     // 1. Ambil data PR beserta relasinya
    //     $pr = PurchaseRequest::with(['company', 'user', 'items' => function($query) {
    //         // TAMBAHKAN 'vendorQuotes' DI SINI AGAR HARGA PENAWARAN IKUT TERBAWA
    //         $query->where('status', 'APPROVED')->with(['item', 'vendorQuotes']);
    //     }])->findOrFail($id);

    //     // return $pr;

    //     // 2. Proteksi: Cegah PR yang belum disetujui masuk ke sini
    //     if ($pr->status->slug !== 'approved') {
    //         return redirect()->route('pr.index')->with('error', 'Hanya PR yang sudah disetujui final (Approved) yang bisa diproses menjadi PO.');
    //     }

    //     // 3. Proteksi: Pastikan ada item yang disetujui
    //     if ($pr->items->isEmpty()) {
    //         return redirect()->route('pr.index')->with('error', 'Tidak ada barang yang disetujui di dalam PR ini untuk dibuatkan PO.');
    //     }

    //     // 4. Siapkan data master untuk form dropdown
    //     $vendors = Vendor::orderBy('name', 'asc')->get();
    //     $currencies = \App\Models\Currency::where('is_active', 1)->get();
    //     $chargeTypes = ChargeType::where('is_active', true)->get();

    //     // TAMBAHKAN INI: Ambil semua PT/Cabang untuk penentuan penanggung biaya
    //     $companies = Company::orderBy('name', 'asc')->get();

    //     $taxes = Tax::all();
    //     $paymentTerms =PaymentTerm::all();

    //     return view('po.process_pr', compact('pr', 'vendors', 'chargeTypes','currencies','taxes','companies','paymentTerms'));
    // }



    // Cari fungsi yang mirip seperti ini di Controller Anda (yang me-return view 'po.process_pr')
    // public function processPr($id)
    // {
    //     // 1. Ambil data PR beserta item dan relasinya
    //     $pr = \App\Models\PurchaseRequest::with(['items.item', 'items.vendorQuotes.vendor', 'user'])->findOrFail($id);

    //     // ====================================================================
    //     // PERBAIKAN DI SINI: Izinkan status 'approved' DAN 'partial_po'
    //     // ====================================================================
    //     $allowedStatuses = ['approved', 'partial_po']; // Sesuaikan slug ini dengan database tabel status Anda

    //     if (!in_array(optional($pr->status)->slug, $allowedStatuses)) {
    //         return redirect()->back()->with('error', 'Hanya PR yang sudah disetujui final (Approved) atau Parsial yang bisa diproses menjadi PO.');
    //     }

    //     // 2. Load data master pendukung untuk form dropdown
    //     $vendors = \App\Models\Vendor::all();
    //     $companies = \App\Models\Company::all();
    //     $paymentTerms = \App\Models\PaymentTerm::all();
    //     $taxes = \App\Models\Tax::all();
    //     $chargeTypes = \App\Models\ChargeType::where('is_active', 1)->get(); // Master biaya tambahan

    //     // 3. Tampilkan halaman form
    //     return view('po.process_pr', compact('pr', 'vendors', 'companies', 'paymentTerms', 'taxes', 'chargeTypes'));
    // }







}
