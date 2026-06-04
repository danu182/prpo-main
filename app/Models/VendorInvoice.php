<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number', 'purchase_order_id', 'goods_receipt_id',
        'vendor_id', 'company_id', 'vendor_invoice_number',
        'invoice_date', 'due_date', 'subtotal', 'item_discount_total',
        'tax_amount', 'global_discount_total', 'charge_total',
        'grand_total', 'status_id', 'notes', 'created_by',
        'extra_discount_total'
    ];

    public function items() {
        return $this->hasMany(VendorInvoiceItem::class, 'vendor_invoice_id');
    }

    public function purchaseOrder() {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function goodsReceipt() {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function vendor() {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function company() {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    // NAMA DIKEMBALIKAN MENJADI STATUS
    public function status() {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function payments() {
        return $this->hasMany(VendorPayment::class, 'vendor_invoice_id');
    }

    // Relasi Laci Dokumen Tambahan (Faktur Pajak, Garansi, dll)
    public function attachments()
    {
        return $this->hasMany(VendorInvoiceAttachment::class, 'vendor_invoice_id');
    }
}
