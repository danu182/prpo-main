<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_invoice_id', 'goods_receipt_item_id', 'item_id',
        'qty_invoiced', 'price', 'discount_amount', 'tax_percent', 'tax_amount', 'subtotal'
    ];

    public function vendorInvoice() {
        return $this->belongsTo(VendorInvoice::class, 'vendor_invoice_id');
    }

    public function goodsReceiptItem() {
        return $this->belongsTo(GoodsReceiptItem::class, 'goods_receipt_item_id');
    }

    public function item() {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
