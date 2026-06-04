<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GoodsReceipt;
use App\Models\VendorInvoice;
use App\Models\VendorInvoiceItem;
use Illuminate\Support\Facades\DB;

class VendorInvoiceController extends Controller
{
    // ==========================================
    // 1. GENERATE NOMOR INVOICE (PAKAI STRIP)
    // ==========================================
    private function generateInvoiceNumber($companyId)
    {
        $company = \App\Models\Company::find($companyId);
        $code = ($company && !empty($company->code)) ? strtoupper($company->code) : 'UMUM';
        $dateStr = date('Y-m-d'); 
        
        $prefix = "INV-{$code}-{$dateStr}-";

        $lastInv = VendorInvoice::where('invoice_number', 'like', $prefix . '%')
                    ->orderBy('id', 'desc')
                    ->lockForUpdate()
                    ->first();

        $newSequence = $lastInv ? ((int) substr($lastInv->invoice_number, -4)) + 1 : 1;
        return $prefix . str_pad($newSequence, 4, '0', STR_PAD_LEFT);
    }

    // ==========================================
    // 2. TAMPILKAN INDEX & MODAL GR
    // ==========================================
    public function index(\Illuminate\Http\Request $request)
    {
        $search = $request->input('search');

        $invoices = VendorInvoice::with(['vendor', 'purchaseOrder', 'status'])
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

        $readyGrs = GoodsReceipt::with(['po.vendor', 'po.company', 'receiver'])
            ->whereDoesntHave('vendorInvoice') 
            ->latest()
            ->get();

        return view('vendor_invoices.index', compact('invoices', 'readyGrs', 'search'));
    }

    // ==========================================
    // 3. CREATE INVOICE BERDASARKAN GR (SINGLE)
    // ==========================================
    public function createFromGr($grSlug)
    {
        $gr = GoodsReceipt::with(['po', 'items.purchaseOrderItem', 'items.item'])
                ->where('gr_number', $grSlug)
                ->firstOrFail();

        $existingInvoice = VendorInvoice::where('goods_receipt_id', $gr->id)->first();
        if ($existingInvoice) {
            return redirect()->route('vendor-invoices.show', $existingInvoice->invoice_number)
                             ->with('info', 'Tagihan untuk Penerimaan Barang ini sudah pernah dibuat.');
        }

        DB::beginTransaction();
        try {
            $po = $gr->po;
            $subtotalGross = 0; $itemDiscountTotal = 0; $taxTotal = 0;
            $hasValidItems = false; 

            $statusDraft = \App\Models\Status::where('type', 'INV')->where('slug', 'draft')->first();

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

            foreach ($gr->items as $grItem) {
                $poItem = $grItem->purchaseOrderItem;

                $totalReturned = \App\Models\ReturnToVendorItem::where('goods_receipt_item_id', $grItem->id)
                                    ->sum('qty_returned');

                $qty = (float) $grItem->qty_received - (float) $totalReturned;
                if ($qty <= 0) continue;

                $hasValidItems = true;

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
                    'qty_invoiced'          => $qty, 
                    'price'                 => $price,
                    'discount_amount'       => $itemDiscount,
                    'tax_percent'           => round($taxPercent, 2),
                    'tax_amount'            => $itemTax,
                    'subtotal'              => $rowNetSubtotal,
                ]);
            }

            if (!$hasValidItems || $subtotalGross <= 0) {
                DB::rollback();
                return back()->with('error', 'Gagal membuat tagihan. Seluruh barang pada dokumen penerimaan ini telah diretur ke vendor.');
            }

            $headerProportion = $po->subtotal > 0 ? ($subtotalGross / (float) $po->subtotal) : 1;
            
            $poGlobalDiscount = \DB::table('purchase_order_discounts')->where('purchase_order_id', $po->id)->sum('amount');
            $poGlobalCharge   = \DB::table('purchase_order_charges')->where('purchase_order_id', $po->id)->sum('amount');
            $poExtraDiscount  = $po->extra_discount_total ?? 0; // Mengambil Potongan Tambahan

            $globalDiscount = (float) $poGlobalDiscount * $headerProportion;
            $globalCharge   = (float) $poGlobalCharge * $headerProportion;
            $extraDiscount  = (float) $poExtraDiscount * $headerProportion; // Kalkulasi Proporsional

            // 🔥 Hitung Grand Total baru dengan Extra Discount
            $grandTotal = $subtotalGross - $itemDiscountTotal + $taxTotal - $globalDiscount - $extraDiscount + $globalCharge;

            $invoice->update([
                'subtotal'              => $subtotalGross,
                'item_discount_total'   => $itemDiscountTotal,
                'tax_amount'            => $taxTotal,
                'global_discount_total' => $globalDiscount,
                'extra_discount_total'  => $extraDiscount, // 🔥 Laci Diskon Tambahan
                'charge_total'          => $globalCharge,
                'grand_total'           => $grandTotal,
            ]);

            DB::commit();
            return redirect()->route('vendor-invoices.show', $invoice->invoice_number)->with('success', 'Faktur Tagihan berhasil dibuat!');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal membuat tagihan: ' . $e->getMessage());
        }
    }

    // ==========================================
    // 4. CREATE INVOICE GABUNGAN (BULK GR)
    // ==========================================
    public function createBulkFromGr(Request $request)
    {
        $grIds = explode(',', $request->gr_ids);
        if (empty($grIds[0])) return back()->with('error', 'Pilih minimal 1 Penerimaan (GR) untuk digabungkan.');

        $grs = GoodsReceipt::with(['po', 'items.purchaseOrderItem', 'items.item'])->whereIn('id', $grIds)->get();
        $poIds = $grs->pluck('purchase_order_id')->unique();

        if ($poIds->count() > 1) return back()->with('error', 'Penerimaan yang digabung harus berasal dari Purchase Order (PO) yang sama!');
        $po = $grs->first()->po;

        foreach($grs as $gr) {
            $grItemIds = $gr->items->pluck('id');
            $exists = VendorInvoiceItem::whereIn('goods_receipt_item_id', $grItemIds)->exists();
            if($exists) return back()->with('error', 'Penerimaan No. ' . $gr->gr_number . ' sudah pernah ditagihkan.');
        }

        DB::beginTransaction();
        try {
            $subtotalGross = 0; $itemDiscountTotal = 0; $taxTotal = 0;
            $hasValidItems = false; 

            $statusDraft = \App\Models\Status::where('type', 'INV')->where('slug', 'draft')->first();

            $invoice = VendorInvoice::create([
                'invoice_number'    => $this->generateInvoiceNumber($po->bill_to_company_id),
                'purchase_order_id' => $po->id,
                'goods_receipt_id'  => null, 
                'vendor_id'         => $po->vendor_id,
                'company_id'        => $po->bill_to_company_id,
                'invoice_date'      => now(),
                'status_id'         => $statusDraft ? $statusDraft->id : null,
                'created_by'        => auth()->id(),
                'notes'             => "Tagihan Gabungan (Bulk) dari GR: " . $grs->pluck('gr_number')->implode(', '),
            ]);

            foreach ($grs as $gr) {
                foreach ($gr->items as $grItem) {
                    $poItem = $grItem->purchaseOrderItem;

                    $totalReturned = \App\Models\ReturnToVendorItem::where('goods_receipt_item_id', $grItem->id)
                                        ->sum('qty_returned');

                    $qty = (float) $grItem->qty_received - (float) $totalReturned;
                    if ($qty <= 0) continue;

                    $hasValidItems = true; 

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
                        'qty_invoiced'          => $qty, 
                        'price'                 => $price,
                        'discount_amount'       => $itemDiscount,
                        'tax_percent'           => round($taxPercent, 2),
                        'tax_amount'            => $itemTax,
                        'subtotal'              => $rowNetSubtotal,
                    ]);
                }
            }

            if (!$hasValidItems || $subtotalGross <= 0) {
                DB::rollback();
                return back()->with('error', 'Gagal membuat tagihan. Seluruh barang pada dokumen-dokumen GR yang Anda pilih telah diretur ke vendor.');
            }

            $headerProportion = $po->subtotal > 0 ? ($subtotalGross / (float) $po->subtotal) : 1;
            
            $poGlobalDiscount = \DB::table('purchase_order_discounts')->where('purchase_order_id', $po->id)->sum('amount');
            $poGlobalCharge   = \DB::table('purchase_order_charges')->where('purchase_order_id', $po->id)->sum('amount');
            $poExtraDiscount  = $po->extra_discount_total ?? 0; // Mengambil Potongan Tambahan

            $globalDiscount = (float) $poGlobalDiscount * $headerProportion;
            $globalCharge   = (float) $poGlobalCharge * $headerProportion;
            $extraDiscount  = (float) $poExtraDiscount * $headerProportion;

            // 🔥 Hitung Grand Total baru dengan Extra Discount
            $grandTotal = $subtotalGross - $itemDiscountTotal + $taxTotal - $globalDiscount - $extraDiscount + $globalCharge;

            $invoice->update([
                'subtotal'              => $subtotalGross,
                'item_discount_total'   => $itemDiscountTotal,
                'tax_amount'            => $taxTotal,
                'global_discount_total' => $globalDiscount,
                'extra_discount_total'  => $extraDiscount, // 🔥 Laci Diskon Tambahan
                'charge_total'          => $globalCharge,
                'grand_total'           => $grandTotal,
            ]);

            DB::commit();
            return redirect()->route('vendor-invoices.show', $invoice->invoice_number)->with('success', 'Faktur Tagihan Gabungan berhasil dibuat!');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal membuat tagihan gabungan: ' . $e->getMessage());
        }
    }

    // ==========================================
    // 5. TAMPILKAN INVOICE LENGKAP (SHOW)
    // ==========================================
    public function show($slug)
    {
        $invoice = VendorInvoice::with([
            'vendor',
            'company',
            'purchaseOrder',
            'goodsReceipt',
            'items.item',
            'items.goodsReceiptItem.purchaseOrderItem',
            'creator',
            'status',
            'payments',
            'attachments' // 🔥 Relasi Lampiran Dipanggil di sini!
        ])->where('invoice_number', $slug)->firstOrFail();

        return view('vendor_invoices.show', compact('invoice'));
    }

    // ==========================================
    // 6. UPDATE DATA INVOICE FISIK
    // ==========================================
    public function update(\Illuminate\Http\Request $request, $slug)
    {
        $invoice = VendorInvoice::with('status')->where('invoice_number', $slug)->firstOrFail();

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
                $invoice->status_id = $statusPosted->id; 
            }
        }

        $invoice->save();

        $pesan = $request->has('post_invoice') ? 'Tagihan berhasil diposting dan kini siap dibayar!' : 'Data tagihan berhasil disimpan!';
        return redirect()->back()->with('success', $pesan);
    }

    // ==========================================
    // 7. BAYAR INVOICE (DENGAN MULTI ATTACHMENT DINAMIS)
    // ==========================================
    public function storePayment(Request $request, $slug)
    {
        $request->validate([
            'payment_date'   => 'required|date',
            'payment_method' => 'required|string',
            'amount'         => 'required|numeric|min:1',
            'attachments'    => 'nullable|array', 
            'attachments.*'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', 
        ]);

        DB::beginTransaction();
        try {
            $invoice = VendorInvoice::where('invoice_number', $slug)->firstOrFail();
            
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

            // Ambil dari tabel setting (Payment Attachment)
            if ($request->hasFile('attachments')) {
                $setting = \DB::table('system_settings')->where('setting_key', 'path_payment_attachment')->first(); 
                $rootFolder = $setting ? $setting->setting_value : 'attachments/payments';
                $targetFolder = trim($rootFolder, '/') . '/' . str_replace('/', '-', $payment->payment_number);

                foreach ($request->file('attachments') as $file) {
                    if ($file->isValid()) {
                        $originalName = $file->getClientOriginalName();
                        $filename = time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName);
                        $path = $file->storeAs($targetFolder, $filename, 'public');

                        \App\Models\VendorPaymentAttachment::create([
                            'vendor_payment_id' => $payment->id,
                            'file_name'         => $originalName,
                            'file_path'         => $path,
                        ]);
                    }
                }
            }

            // Logic Update Status Invoice (Partial / Paid)
            $totalPaidSoFar = $invoice->payments()->sum('amount');
            $statusTarget = ($totalPaidSoFar >= $invoice->grand_total) ? 'paid' : 'partial';

            $newStatus = \App\Models\Status::where('type', 'INV')->where('slug', $statusTarget)->first();
            if ($newStatus) {
                $invoice->update(['status_id' => $newStatus->id]);
            }

            // Update PO Status jika lunas
            if ($statusTarget === 'paid' && $invoice->purchase_order_id) {
                $poCompletedStatus = \App\Models\Status::where('type', 'PO')->where('slug', 'completed')->first();
                if ($poCompletedStatus) {
                    \App\Models\PurchaseOrder::where('id', $invoice->purchase_order_id)
                                             ->update(['status_id' => $poCompletedStatus->id]);
                }
            }

            DB::commit();
            return back()->with('success', 'Pembayaran Berhasil Dicatat dengan Multi-Lampiran!');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal mencatat pembayaran: ' . $e->getMessage());
        }
    }

    // ==========================================
    // 8. FUNGSI BARU: UPLOAD DOKUMEN INVOICE (MULTI BANYAK FILE)
    // ==========================================
    public function uploadAttachment(Request $request, $slug)
    {
        // Validasi Array
        $request->validate([
            'document_types'     => 'required|array',
            'document_types.*'   => 'required|string',
            'attachment_files'   => 'required|array',
            'attachment_files.*' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // Maks 5MB
        ]);

        try {
            $invoice = VendorInvoice::where('invoice_number', $slug)->firstOrFail();

            // Membaca lokasi folder dari tabel
            $setting = \DB::table('system_settings')->where('setting_key', 'path_invoice_attachment')->first(); 
            $rootFolder = $setting ? $setting->setting_value : 'attachments/invoices';
            $targetFolder = trim($rootFolder, '/') . '/' . str_replace('/', '-', $invoice->invoice_number);

            // Looping (Berputar) sebanyak file yang di-upload
            foreach ($request->file('attachment_files') as $index => $file) {
                if ($file->isValid()) {
                    // Ambil jenis dokumen sesuai urutan baris
                    $docType = $request->document_types[$index] ?? 'Dokumen Lainnya';
                    
                    $originalName = $file->getClientOriginalName();
                    $filename = time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName);
                    
                    // Simpan fisik file
                    $path = $file->storeAs($targetFolder, $filename, 'public');

                    // Simpan jejak di database
                    \App\Models\VendorInvoiceAttachment::create([
                        'vendor_invoice_id' => $invoice->id,
                        'document_type'     => $docType,
                        'file_name'         => $originalName,
                        'file_path'         => $path,
                    ]);
                }
            }

            $jumlah = count($request->file('attachment_files'));
            return back()->with('success', $jumlah . ' Dokumen berhasil dilampirkan!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengunggah dokumen: ' . $e->getMessage());
        }
    }

    // ==========================================
    // 9. FUNGSI BARU: HAPUS DOKUMEN INVOICE
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



    // ==========================================
    // 10. FUNGSI BARU: BATALKAN PEMBAYARAN (VOID PAYMENT) + ALASAN
    // ==========================================
    public function cancelPayment(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $payment = \App\Models\VendorPayment::with(['invoice.purchaseOrder', 'attachments'])->findOrFail($id);
            $invoice = $payment->invoice;
            
            // Tangkap alasan dari Pop-up SweetAlert
            $reason = $request->input('cancel_reason', 'Tanpa Keterangan');

            // 1. Hapus fisik file bukti bayar jika ada
            foreach ($payment->attachments as $att) {
                if (\Storage::disk('public')->exists($att->file_path)) {
                    \Storage::disk('public')->delete($att->file_path);
                }
            }
            \App\Models\VendorPaymentAttachment::where('vendor_payment_id', $payment->id)->delete();
            
            // 🔥 JEJAK AUDIT: Catat alasan batal di kolom notes invoice
            $auditTrail = "\n\n[VOID] Pembayaran " . $payment->payment_number . " senilai " . number_format($payment->amount, 0, ',', '.') . " dibatalkan pada " . date('d/m/Y H:i') . ".\nAlasan: " . $reason;
            $invoice->notes = $invoice->notes . $auditTrail;
            $invoice->save();

            // Hapus data pembayaran
            $payment->delete();

            // 2. Hitung ulang sisa tagihan & Status Invoice
            $totalPaidSoFar = $invoice->payments()->sum('amount');
            $statusTarget = ($totalPaidSoFar == 0) ? 'posted' : 'partial';

            $newInvStatus = \App\Models\Status::where('type', 'INV')->where('slug', $statusTarget)->first();
            if ($newInvStatus) {
                $invoice->update(['status_id' => $newInvStatus->id]);
            }

            // 3. Kembalikan Status PO
            if ($invoice->purchaseOrder && strtolower(optional($invoice->purchaseOrder->status)->slug) === 'completed') {
                $poProcessingStatus = \App\Models\Status::where('type', 'PO')->where('slug', 'processing')->first();
                if ($poProcessingStatus) {
                    $invoice->purchaseOrder->update(['status_id' => $poProcessingStatus->id]);
                }
            }

            DB::commit();
            return back()->with('success', 'Pembayaran dibatalkan! Alasan telah dicatat di sistem.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal membatalkan pembayaran: ' . $e->getMessage());
        }
    }

    // ==========================================
    // 11. UPDATE: BATALKAN TAGIHAN (VOID INVOICE) + ALASAN
    // ==========================================
    public function cancelInvoice(Request $request, $slug)
    {
        try {
            $invoice = VendorInvoice::with(['payments', 'attachments'])->where('invoice_number', $slug)->firstOrFail();

            // Kunci Pengaman: Tidak boleh hapus jika sudah ada uang yang dibayar!
            if ($invoice->payments->count() > 0) {
                return back()->with('error', 'Akses Ditolak: Tagihan ini sudah memiliki riwayat pembayaran.');
            }

            $reason = $request->input('cancel_reason', 'Tanpa Alasan');

            // --- LOGIKA AUDIT (Opsional) ---
            // \Log::info("Invoice $slug dibatalkan oleh " . auth()->user()->name . ". Alasan: $reason");

            // Hapus fisik file di laci dokumen
            foreach ($invoice->attachments as $att) {
                if (\Storage::disk('public')->exists($att->file_path)) {
                    \Storage::disk('public')->delete($att->file_path);
                }
            }

            // Hapus tagihan
            $invoice->delete();

            return redirect()->route('vendor-invoices.index')->with('success', "Tagihan $slug berhasil dibatalkan. Alasan: $reason");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal membatalkan tagihan: ' . $e->getMessage());
        }
    }


}