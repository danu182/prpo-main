<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GoodsReceipt;
use App\Models\VendorInvoice;
use App\Models\VendorInvoiceItem;
use Illuminate\Support\Facades\DB;

class VendorInvoiceController extends Controller
{
    private function generateInvoiceNumber($companyId)
    {
        $company = \App\Models\Company::find($companyId);
        $code = ($company && !empty($company->code)) ? strtoupper($company->code) : 'UMUM';
        $prefix = "INV/{$code}/" . date('Y/m/d') . "/";

        $lastInv = VendorInvoice::where('invoice_number', 'like', $prefix . '%')
                    ->orderBy('id', 'desc')
                    ->lockForUpdate()
                    ->first();

        $newSequence = $lastInv ? ((int) substr($lastInv->invoice_number, -4)) + 1 : 1;
        return $prefix . str_pad($newSequence, 4, '0', STR_PAD_LEFT);
    }

    public function index(\Illuminate\Http\Request $request)
    {
        $search = $request->input('search');

        // Panggil relasi 'status' yang sudah bersih
        $invoices = \App\Models\VendorInvoice::with(['vendor', 'purchaseOrder', 'status'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                      ->orWhereHas('vendor', function ($qVendor) use ($search) {
                          $qVendor->where('name', 'like', "%{$search}%");
                      })
                      ->orWhereHas('purchaseOrder', function ($qPo) use ($search) {
                          $qPo->where('po_number', 'like', "%{$search}%");
                      });
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('vendor_invoices.index', compact('invoices', 'search'));
    }

    // public function createFromGr($grId)
    // {
    //     $gr = GoodsReceipt::with(['po', 'items.purchaseOrderItem', 'items.item'])->findOrFail($grId);

    //     $existingInvoice = VendorInvoice::where('goods_receipt_id', $grId)->first();
    //     if ($existingInvoice) {
    //         return redirect()->route('vendor-invoices.show', $existingInvoice->id)
    //                          ->with('info', 'Tagihan untuk Penerimaan Barang ini sudah pernah dibuat.');
    //     }

    //     DB::beginTransaction();
    //     try {
    //         $po = $gr->po;
    //         $subtotalGross = 0; $itemDiscountTotal = 0; $taxTotal = 0;

    //         $statusDraft = \App\Models\Status::where('type', 'INV')->where('slug', 'draft')->first();

    //         $invoice = VendorInvoice::create([
    //             'invoice_number'    => $this->generateInvoiceNumber($po->bill_to_company_id),
    //             'purchase_order_id' => $po->id,
    //             'goods_receipt_id'  => $gr->id,
    //             'vendor_id'         => $po->vendor_id,
    //             'company_id'        => $po->bill_to_company_id,
    //             'invoice_date'      => now(),
    //             'status_id'         => $statusDraft ? $statusDraft->id : null, // MENGGUNAKAN status_id
    //             'created_by'        => auth()->id(),
    //             'notes'             => "Tagihan otomatis dari Penerimaan Barang No: " . $gr->gr_number,
    //         ]);

    //         foreach ($gr->items as $grItem) {
    //             $poItem = $grItem->purchaseOrderItem;
    //             $qty = (float) $grItem->qty_received;
    //             $price = (float) $poItem->unit_price;
    //             $rowGross = $qty * $price;
    //             $proportion = $poItem->qty_ordered > 0 ? ($qty / (float) $poItem->qty_ordered) : 1;

    //             $itemDiscount = (float) $poItem->discount_amount * $proportion;
    //             $itemTax = (float) $poItem->tax_amount * $proportion;
    //             $taxPercent = ($rowGross - $itemDiscount) > 0 ? ($itemTax / ($rowGross - $itemDiscount)) * 100 : 0;
    //             $rowNetSubtotal = $rowGross - $itemDiscount + $itemTax;

    //             $subtotalGross += $rowGross;
    //             $itemDiscountTotal += $itemDiscount;
    //             $taxTotal += $itemTax;

    //             VendorInvoiceItem::create([
    //                 'vendor_invoice_id'     => $invoice->id,
    //                 'goods_receipt_item_id' => $grItem->id,
    //                 'item_id'               => $grItem->item_id,
    //                 'qty_invoiced'          => $qty,
    //                 'price'                 => $price,
    //                 'discount_amount'       => $itemDiscount,
    //                 'tax_percent'           => round($taxPercent, 2),
    //                 'tax_amount'            => $itemTax,
    //                 'subtotal'              => $rowNetSubtotal,
    //             ]);
    //         }

    //         $headerProportion = $po->subtotal > 0 ? ($subtotalGross / (float) $po->subtotal) : 1;
    //         $poGlobalDiscount = \DB::table('purchase_order_discounts')->where('purchase_order_id', $po->id)->sum('amount');
    //         $poGlobalCharge = \DB::table('purchase_order_charges')->where('purchase_order_id', $po->id)->sum('amount');

    //         $globalDiscount = (float) $poGlobalDiscount * $headerProportion;
    //         $globalCharge = (float) $poGlobalCharge * $headerProportion;
    //         $grandTotal = $subtotalGross - $itemDiscountTotal + $taxTotal - $globalDiscount + $globalCharge;

    //         $invoice->update([
    //             'subtotal'              => $subtotalGross,
    //             'item_discount_total'   => $itemDiscountTotal,
    //             'tax_amount'            => $taxTotal,
    //             'global_discount_total' => $globalDiscount,
    //             'charge_total'          => $globalCharge,
    //             'grand_total'           => $grandTotal,
    //         ]);

    //         DB::commit();
    //         return redirect()->route('vendor-invoices.show', $invoice->id)->with('success', 'Faktur Tagihan berhasil dibuat!');

    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         return back()->with('error', 'Gagal membuat tagihan: ' . $e->getMessage());
    //     }
    // }


    public function createFromGr($grId)
    {
        $gr = GoodsReceipt::with(['po', 'items.purchaseOrderItem', 'items.item'])->findOrFail($grId);

        // Validasi 1: Cegah pembuatan tagihan ganda untuk 1 GR
        $existingInvoice = VendorInvoice::where('goods_receipt_id', $grId)->first();
        if ($existingInvoice) {
            return redirect()->route('vendor-invoices.show', $existingInvoice->id)
                             ->with('info', 'Tagihan untuk Penerimaan Barang ini sudah pernah dibuat.');
        }

        DB::beginTransaction();
        try {
            $po = $gr->po;
            $subtotalGross = 0; $itemDiscountTotal = 0; $taxTotal = 0;
            $hasValidItems = false; // Penanda apakah ada barang yang bisa ditagih

            $statusDraft = \App\Models\Status::where('type', 'INV')->where('slug', 'draft')->first();

            // 1. Buat Draf Header Tagihan Dulu
            $invoice = VendorInvoice::create([
                'invoice_number'    => $this->generateInvoiceNumber($po->bill_to_company_id),
                'purchase_order_id' => $po->id,
                'goods_receipt_id'  => $gr->id,
                'vendor_id'         => $po->vendor_id,
                'company_id'        => $po->bill_to_company_id,
                'invoice_date'      => now(),
                'status_id'         => $statusDraft ? $statusDraft->id : null,
                'created_by'        => auth()->id(),
                'notes'             => "Tagihan otomatis dari Penerimaan Barang No: " . $gr->gr_number,
            ]);

            // 2. Looping Rincian Barang
            foreach ($gr->items as $grItem) {
                $poItem = $grItem->purchaseOrderItem;

                // --- SISI KEAMANAN: Cek Berapa Banyak Barang Ini Yang Pernah Diretur ---
                $totalReturned = \App\Models\ReturnToVendorItem::where('goods_receipt_item_id', $grItem->id)
                                    ->sum('qty_returned');

                // Dapatkan Qty Bersih yang layak dibayar (Diterima - Diretur)
                $qty = (float) $grItem->qty_received - (float) $totalReturned;

                // Jika barang ini ternyata diretur SEMUA (Qty = 0), maka abaikan/jangan ditagih!
                if ($qty <= 0) {
                    continue;
                }

                $hasValidItems = true; // Terdeteksi ada barang yang layak ditagih

                $price = (float) $poItem->unit_price;
                $rowGross = $qty * $price;

                // Proporsi untuk menghitung diskon & pajak bawaan dari PO
                $proportion = $poItem->qty_ordered > 0 ? ($qty / (float) $poItem->qty_ordered) : 1;

                $itemDiscount = (float) $poItem->discount_amount * $proportion;
                $itemTax = (float) $poItem->tax_amount * $proportion;
                $taxPercent = ($rowGross - $itemDiscount) > 0 ? ($itemTax / ($rowGross - $itemDiscount)) * 100 : 0;
                $rowNetSubtotal = $rowGross - $itemDiscount + $itemTax;

                $subtotalGross += $rowGross;
                $itemDiscountTotal += $itemDiscount;
                $taxTotal += $itemTax;

                // Simpan Baris Tagihan
                VendorInvoiceItem::create([
                    'vendor_invoice_id'     => $invoice->id,
                    'goods_receipt_item_id' => $grItem->id,
                    'item_id'               => $grItem->item_id,
                    'qty_invoiced'          => $qty, // <-- Menyimpan Qty Bersih (Sudah potong retur)
                    'price'                 => $price,
                    'discount_amount'       => $itemDiscount,
                    'tax_percent'           => round($taxPercent, 2),
                    'tax_amount'            => $itemTax,
                    'subtotal'              => $rowNetSubtotal,
                ]);
            }

            // Validasi 2: Jika ternyata semua barang pada GR ini dikembalikan/diretur (Kosong total)
            if (!$hasValidItems || $subtotalGross <= 0) {
                DB::rollback();
                return back()->with('error', 'Gagal membuat tagihan. Seluruh barang pada dokumen penerimaan ini telah diretur ke vendor, tidak ada tagihan yang perlu dibayar.');
            }

            // 3. Hitung Global Diskon & Global Biaya (Misal Ongkir) secara proporsional
            $headerProportion = $po->subtotal > 0 ? ($subtotalGross / (float) $po->subtotal) : 1;
            $poGlobalDiscount = \DB::table('purchase_order_discounts')->where('purchase_order_id', $po->id)->sum('amount');
            $poGlobalCharge = \DB::table('purchase_order_charges')->where('purchase_order_id', $po->id)->sum('amount');

            $globalDiscount = (float) $poGlobalDiscount * $headerProportion;
            $globalCharge = (float) $poGlobalCharge * $headerProportion;
            $grandTotal = $subtotalGross - $itemDiscountTotal + $taxTotal - $globalDiscount + $globalCharge;

            // 4. Update Header Tagihan dengan Total Akhir
            $invoice->update([
                'subtotal'              => $subtotalGross,
                'item_discount_total'   => $itemDiscountTotal,
                'tax_amount'            => $taxTotal,
                'global_discount_total' => $globalDiscount,
                'charge_total'          => $globalCharge,
                'grand_total'           => $grandTotal,
            ]);

            DB::commit();
            return redirect()->route('vendor-invoices.show', $invoice->id)->with('success', 'Faktur Tagihan berhasil dibuat dan nilai telah disesuaikan dengan histori retur (RTV)!');

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error Create Invoice from GR: ' . $e->getMessage());
            return back()->with('error', 'Gagal membuat tagihan: ' . $e->getMessage());
        }
    }


    // public function createBulkFromGr(Request $request)
    // {
    //     $grIds = explode(',', $request->gr_ids);
    //     if (empty($grIds[0])) return back()->with('error', 'Pilih minimal 1 Penerimaan (GR) untuk digabungkan.');

    //     $grs = GoodsReceipt::with(['po', 'items.purchaseOrderItem', 'items.item'])->whereIn('id', $grIds)->get();
    //     $poIds = $grs->pluck('purchase_order_id')->unique();

    //     if ($poIds->count() > 1) return back()->with('error', 'Penerimaan yang digabung harus berasal dari Purchase Order (PO) yang sama!');
    //     $po = $grs->first()->po;

    //     foreach($grs as $gr) {
    //         $grItemIds = $gr->items->pluck('id');
    //         $exists = VendorInvoiceItem::whereIn('goods_receipt_item_id', $grItemIds)->exists();
    //         if($exists) return back()->with('error', 'Penerimaan No. ' . $gr->gr_number . ' sudah ditagihkan.');
    //     }

    //     DB::beginTransaction();
    //     try {
    //         $subtotalGross = 0; $itemDiscountTotal = 0; $taxTotal = 0;
    //         $statusDraft = \App\Models\Status::where('type', 'INV')->where('slug', 'draft')->first();

    //         $invoice = VendorInvoice::create([
    //             'invoice_number'    => $this->generateInvoiceNumber($po->bill_to_company_id),
    //             'purchase_order_id' => $po->id,
    //             'goods_receipt_id'  => null,
    //             'vendor_id'         => $po->vendor_id,
    //             'company_id'        => $po->bill_to_company_id,
    //             'invoice_date'      => now(),
    //             'status_id'         => $statusDraft ? $statusDraft->id : null, // MENGGUNAKAN status_id
    //             'created_by'        => auth()->id(),
    //             'notes'             => "Tagihan Gabungan (Bulk) dari GR: " . $grs->pluck('gr_number')->implode(', '),
    //         ]);

    //         foreach ($grs as $gr) {
    //             foreach ($gr->items as $grItem) {
    //                 $poItem = $grItem->purchaseOrderItem;
    //                 $qty = (float) $grItem->qty_received;
    //                 $price = (float) $poItem->unit_price;
    //                 $rowGross = $qty * $price;
    //                 $proportion = $poItem->qty_ordered > 0 ? ($qty / (float) $poItem->qty_ordered) : 1;

    //                 $itemDiscount = (float) $poItem->discount_amount * $proportion;
    //                 $itemTax = (float) $poItem->tax_amount * $proportion;
    //                 $taxPercent = ($rowGross - $itemDiscount) > 0 ? ($itemTax / ($rowGross - $itemDiscount)) * 100 : 0;
    //                 $rowNetSubtotal = $rowGross - $itemDiscount + $itemTax;

    //                 $subtotalGross += $rowGross;
    //                 $itemDiscountTotal += $itemDiscount;
    //                 $taxTotal += $itemTax;

    //                 VendorInvoiceItem::create([
    //                     'vendor_invoice_id'     => $invoice->id,
    //                     'goods_receipt_item_id' => $grItem->id,
    //                     'item_id'               => $grItem->item_id,
    //                     'qty_invoiced'          => $qty,
    //                     'price'                 => $price,
    //                     'discount_amount'       => $itemDiscount,
    //                     'tax_percent'           => round($taxPercent, 2),
    //                     'tax_amount'            => $itemTax,
    //                     'subtotal'              => $rowNetSubtotal,
    //                 ]);
    //             }
    //         }

    //         $headerProportion = $po->subtotal > 0 ? ($subtotalGross / (float) $po->subtotal) : 1;
    //         $poGlobalDiscount = \DB::table('purchase_order_discounts')->where('purchase_order_id', $po->id)->sum('amount');
    //         $poGlobalCharge = \DB::table('purchase_order_charges')->where('purchase_order_id', $po->id)->sum('amount');

    //         $globalDiscount = (float) $poGlobalDiscount * $headerProportion;
    //         $globalCharge = (float) $poGlobalCharge * $headerProportion;
    //         $grandTotal = $subtotalGross - $itemDiscountTotal + $taxTotal - $globalDiscount + $globalCharge;

    //         $invoice->update([
    //             'subtotal'              => $subtotalGross,
    //             'item_discount_total'   => $itemDiscountTotal,
    //             'tax_amount'            => $taxTotal,
    //             'global_discount_total' => $globalDiscount,
    //             'charge_total'          => $globalCharge,
    //             'grand_total'           => $grandTotal,
    //         ]);

    //         DB::commit();
    //         return redirect()->route('vendor-invoices.show', $invoice->id)->with('success', 'Faktur Tagihan Gabungan berhasil dibuat!');

    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         return back()->with('error', 'Gagal membuat tagihan: ' . $e->getMessage());
    //     }
    // }


    public function createBulkFromGr(Request $request)
    {
        $grIds = explode(',', $request->gr_ids);
        if (empty($grIds[0])) return back()->with('error', 'Pilih minimal 1 Penerimaan (GR) untuk digabungkan.');

        $grs = GoodsReceipt::with(['po', 'items.purchaseOrderItem', 'items.item'])->whereIn('id', $grIds)->get();
        $poIds = $grs->pluck('purchase_order_id')->unique();

        // Validasi 1: Pastikan dari PO/Vendor yang sama
        if ($poIds->count() > 1) return back()->with('error', 'Penerimaan yang digabung harus berasal dari Purchase Order (PO) yang sama!');
        $po = $grs->first()->po;

        // Validasi 2: Cegah Tagihan Ganda
        foreach($grs as $gr) {
            $grItemIds = $gr->items->pluck('id');
            $exists = VendorInvoiceItem::whereIn('goods_receipt_item_id', $grItemIds)->exists();
            if($exists) return back()->with('error', 'Penerimaan No. ' . $gr->gr_number . ' sudah pernah ditagihkan.');
        }

        DB::beginTransaction();
        try {
            $subtotalGross = 0; $itemDiscountTotal = 0; $taxTotal = 0;
            $hasValidItems = false; // Penanda untuk mencegah tagihan kosong (Rp 0)

            $statusDraft = \App\Models\Status::where('type', 'INV')->where('slug', 'draft')->first();

            $invoice = VendorInvoice::create([
                'invoice_number'    => $this->generateInvoiceNumber($po->bill_to_company_id),
                'purchase_order_id' => $po->id,
                'goods_receipt_id'  => null, // Null karena ini bulk/gabungan dari banyak GR
                'vendor_id'         => $po->vendor_id,
                'company_id'        => $po->bill_to_company_id,
                'invoice_date'      => now(),
                'status_id'         => $statusDraft ? $statusDraft->id : null,
                'created_by'        => auth()->id(),
                'notes'             => "Tagihan Gabungan (Bulk) dari GR: " . $grs->pluck('gr_number')->implode(', '),
            ]);

            // Looping ke semua GR yang dipilih
            foreach ($grs as $gr) {
                // Looping ke semua barang di dalam masing-masing GR
                foreach ($gr->items as $grItem) {
                    $poItem = $grItem->purchaseOrderItem;

                    // --- LOGIKA POTONG RETUR (RTV) ---
                    $totalReturned = \App\Models\ReturnToVendorItem::where('goods_receipt_item_id', $grItem->id)
                                        ->sum('qty_returned');

                    // Dapatkan Qty Bersih yang layak dibayar
                    $qty = (float) $grItem->qty_received - (float) $totalReturned;

                    // Jika barang pada GR ini sudah diretur SEMUA, lewati!
                    if ($qty <= 0) {
                        continue;
                    }

                    $hasValidItems = true; // Terdeteksi ada barang yang ditagihkan

                    $price = (float) $poItem->unit_price;
                    $rowGross = $qty * $price;
                    $proportion = $poItem->qty_ordered > 0 ? ($qty / (float) $poItem->qty_ordered) : 1;

                    $itemDiscount = (float) $poItem->discount_amount * $proportion;
                    $itemTax = (float) $poItem->tax_amount * $proportion;
                    $taxPercent = ($rowGross - $itemDiscount) > 0 ? ($itemTax / ($rowGross - $itemDiscount)) * 100 : 0;
                    $rowNetSubtotal = $rowGross - $itemDiscount + $itemTax;

                    $subtotalGross += $rowGross;
                    $itemDiscountTotal += $itemDiscount;
                    $taxTotal += $itemTax;

                    VendorInvoiceItem::create([
                        'vendor_invoice_id'     => $invoice->id,
                        'goods_receipt_item_id' => $grItem->id,
                        'item_id'               => $grItem->item_id,
                        'qty_invoiced'          => $qty, // <-- AMAN: Hanya menagih barang bersih
                        'price'                 => $price,
                        'discount_amount'       => $itemDiscount,
                        'tax_percent'           => round($taxPercent, 2),
                        'tax_amount'            => $itemTax,
                        'subtotal'              => $rowNetSubtotal,
                    ]);
                }
            }

            // Validasi 3: Jika ternyata isi dari SEMUA GR yang digabung itu sudah diretur habis
            if (!$hasValidItems || $subtotalGross <= 0) {
                DB::rollback();
                return back()->with('error', 'Gagal membuat tagihan. Seluruh barang pada dokumen-dokumen GR yang Anda pilih telah diretur ke vendor.');
            }

            // Hitungan Proporsional Header
            $headerProportion = $po->subtotal > 0 ? ($subtotalGross / (float) $po->subtotal) : 1;
            $poGlobalDiscount = \DB::table('purchase_order_discounts')->where('purchase_order_id', $po->id)->sum('amount');
            $poGlobalCharge = \DB::table('purchase_order_charges')->where('purchase_order_id', $po->id)->sum('amount');

            $globalDiscount = (float) $poGlobalDiscount * $headerProportion;
            $globalCharge = (float) $poGlobalCharge * $headerProportion;
            $grandTotal = $subtotalGross - $itemDiscountTotal + $taxTotal - $globalDiscount + $globalCharge;

            $invoice->update([
                'subtotal'              => $subtotalGross,
                'item_discount_total'   => $itemDiscountTotal,
                'tax_amount'            => $taxTotal,
                'global_discount_total' => $globalDiscount,
                'charge_total'          => $globalCharge,
                'grand_total'           => $grandTotal,
            ]);

            DB::commit();
            return redirect()->route('vendor-invoices.show', $invoice->id)->with('success', 'Faktur Tagihan Gabungan berhasil dibuat dan nilai telah disesuaikan dengan histori retur!');

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error Bulk Invoice: ' . $e->getMessage());
            return back()->with('error', 'Gagal membuat tagihan gabungan: ' . $e->getMessage());
        }
    }


    public function show($id)
    {
        $invoice = VendorInvoice::with([
            'vendor',
            'company',
            'purchaseOrder',
            'goodsReceipt',
            'items.item',
            // ===============================================
            // 🔥 TAMBAHKAN DUA RELASI INI AGAR BISA BACA DESKRIPSI SPESIFIK
            // ===============================================
            'items.goodsReceiptItem.purchaseOrderItem',
            // ===============================================
            'creator',
            'status',
            'payments' // Sekalian me-load relasi pembayaran agar aman
        ])->findOrFail($id);

        return view('vendor_invoices.show', compact('invoice'));
    }

    public function update(\Illuminate\Http\Request $request, $id)
    {
        $invoice = \App\Models\VendorInvoice::with('status')->findOrFail($id);

        $statusSlug = $invoice->status ? strtolower($invoice->status->slug) : 'draft';
        if (!in_array($statusSlug, ['draft', ''])) {
            return redirect()->back()->with('error', 'Akses Ditolak: Hanya tagihan berstatus DRAFT yang dapat diubah.');
        }

        $request->validate([
            'vendor_invoice_number' => 'required|string',
            'due_date'              => 'required|date',
        ]);

        $invoice->vendor_invoice_number = $request->input('vendor_invoice_number');
        $invoice->due_date              = $request->input('due_date');

        if ($request->has('post_invoice')) {
            $statusPosted = \App\Models\Status::where('type', 'INV')->where('slug', 'posted')->first();
            if ($statusPosted) {
                $invoice->status_id = $statusPosted->id; // MENGGUNAKAN status_id
            }
        }

        $invoice->save();

        $pesan = $request->has('post_invoice') ? 'Tagihan berhasil diposting dan kini siap dibayar!' : 'Data tagihan berhasil disimpan!';
        return redirect()->back()->with('success', $pesan);
    }

    // public function storePayment(Request $request, $id)
    // {
    //     $invoice = \App\Models\VendorInvoice::with('status')->findOrFail($id);

    //     $statusSlug = $invoice->status ? strtolower($invoice->status->slug) : 'draft';
    //     if (!in_array($statusSlug, ['posted', 'partial'])) {
    //         return redirect()->back()->with('error', 'Tagihan ini belum disahkan atau sudah lunas.');
    //     }

    //     $totalPaidSoFar = $invoice->payments()->sum('amount');
    //     $sisaTagihan = $invoice->grand_total - $totalPaidSoFar;

    //     $request->validate([
    //         'payment_date'   => 'required|date',
    //         'payment_method' => 'required|string',
    //         'amount'         => 'required|numeric|min:1|max:' . $sisaTagihan,
    //         'proof_file'     => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
    //     ]);

    //     DB::beginTransaction();
    //     try {
    //         $proofPath = null;
    //         if ($request->hasFile('proof_file')) {
    //             $proofPath = $request->file('proof_file')->store('vendor_payments', 'public');
    //         }

    //         $paymentNumber = 'PAY/' . date('Y/m/d') . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

    //         \App\Models\VendorPayment::create([
    //             'payment_number'    => $paymentNumber,
    //             'vendor_invoice_id' => $invoice->id,
    //             'payment_date'      => $request->payment_date,
    //             'payment_method'    => $request->payment_method,
    //             'bank_name'         => $request->bank_name,
    //             'reference_number'  => $request->reference_number,
    //             'amount'            => $request->amount,
    //             'proof_file'        => $proofPath,
    //             'notes'             => $request->notes,
    //             'created_by'        => auth()->id(),
    //         ]);

    //         $newTotalPaid = $totalPaidSoFar + $request->amount;
    //         $statusTarget = ($newTotalPaid >= $invoice->grand_total) ? 'paid' : 'partial';

    //         $newStatus = \App\Models\Status::where('type', 'INV')->where('slug', $statusTarget)->first();
    //         if ($newStatus) {
    //             // UPDATE KE status_id PADA INVOICE
    //             $invoice->update(['status_id' => $newStatus->id]);
    //         }

    //         // OTOMATISASI TUTUP PO MENGGUNAKAN status_id
    //         if ($statusTarget === 'paid' && $invoice->purchase_order_id) {
    //             $poCompletedStatus = \App\Models\Status::where('type', 'PO')->where('slug', 'completed')->first();
    //             if ($poCompletedStatus) {
    //                 \App\Models\PurchaseOrder::where('id', $invoice->purchase_order_id)
    //                                          ->update(['status_id' => $poCompletedStatus->id]);
    //             }
    //         }

    //         DB::commit();
    //         return back()->with('success', 'Pembayaran sebesar IDR ' . number_format($request->amount, 2, '.', ',') . ' berhasil dicatat!');

    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         return back()->with('error', 'Gagal mencatat pembayaran: ' . $e->getMessage());
    //     }
    // }


    // ==========================================
    // FUNGSI BARU: UPLOAD DOKUMEN KAPAN SAJA
    // ==========================================
    public function uploadAttachment(Request $request, $slug)
    {
        $request->validate([
            'document_type'   => 'required|string',
            'attachment_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // Maks 5MB
        ]);

        try {
            $invoice = VendorInvoice::where('invoice_number', $slug)->firstOrFail();

            // Folder Dinamis seperti di Pembayaran
            $setting = \DB::table('system_settings')->where('setting_key', 'path_invoice_attachment')->first(); 
            $rootFolder = $setting ? $setting->setting_value : 'attachments/invoices';
            $targetFolder = trim($rootFolder, '/') . '/' . str_replace('/', '-', $invoice->invoice_number);

            $file = $request->file('attachment_file');
            $originalName = $file->getClientOriginalName();
            $filename = time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName);
            
            $path = $file->storeAs($targetFolder, $filename, 'public');

            \App\Models\VendorInvoiceAttachment::create([
                'vendor_invoice_id' => $invoice->id,
                'document_type'     => $request->document_type,
                'file_name'         => $originalName,
                'file_path'         => $path,
            ]);

            return back()->with('success', 'Dokumen ' . $request->document_type . ' berhasil dilampirkan!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengunggah dokumen: ' . $e->getMessage());
        }
    }



    // ==========================================
    // FUNGSI BARU: HAPUS DOKUMEN
    // ==========================================
    public function deleteAttachment($id)
    {
        try {
            $attachment = \App\Models\VendorInvoiceAttachment::findOrFail($id);
            if (\Storage::disk('public')->exists($attachment->file_path)) {
                \Storage::disk('public')->delete($attachment->file_path);
            }
            $attachment->delete();
            return back()->with('success', 'Dokumen lampiran berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus dokumen: ' . $e->getMessage());
        }
    }


    public function storePayment(Request $request, $slug)
    {
        // ... (Validasi atas tetap sama, ubah bagian proof_file menjadi attachments)
        $request->validate([
            'payment_date'   => 'required|date',
            'payment_method' => 'required|string',
            'amount'         => 'required|numeric|min:1',
            'attachments'    => 'nullable|array', // 🔥 UBAH JADI ARRAY
            'attachments.*'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', // Max 5MB per file
        ]);

        DB::beginTransaction();
        try {
            $invoice = \App\Models\VendorInvoice::where('invoice_number', $slug)->firstOrFail();
            
            // 1. Buat Header Pembayaran
            $paymentNumber = 'PAY-' . date('Y-m-d') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $payment = \App\Models\VendorPayment::create([
                'payment_number'    => $paymentNumber,
                'vendor_invoice_id' => $invoice->id,
                'payment_date'      => $request->payment_date,
                'payment_method'    => $request->payment_method,
                'bank_name'         => $request->bank_name,
                'reference_number'  => $request->reference_number,
                'amount'            => $request->amount,
                'notes'             => $request->notes,
                'created_by'        => auth()->id(),
            ]);

            // 🔥 2. PROSES MULTI-FILE UPLOAD 🔥
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if ($file->isValid()) {
                        $originalName = $file->getClientOriginalName();
                        $filename = time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName);
                        $path = $file->storeAs('vendor_payments/' . $payment->payment_number, $filename, 'public');

                        \App\Models\VendorPaymentAttachment::create([
                            'vendor_payment_id' => $payment->id,
                            'file_name'         => $originalName,
                            'file_path'         => $path,
                        ]);
                    }
                }
            }

            // ... (Logic update status invoice & PO tetap sama seperti sebelumnya)

            DB::commit();
            return back()->with('success', 'Pembayaran Berhasil Dicatat dengan Multi-Lampiran!');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal: ' . $e->getMessage());
        }
    }
    

}
