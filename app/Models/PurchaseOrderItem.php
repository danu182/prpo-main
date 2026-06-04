<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $guarded = ['id'];

    // Relasi balik ke PR Item (Traceability)
    public function prItem()
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'pr_item_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    // / Relasi ke tabel taxes (Pajak)
    public function tax()
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }


    /**
     * Relasi ke file lampiran per item PO
     */
    public function attachments()
    {
        return $this->hasMany(PurchaseOrderAttachment::class, 'purchase_order_id');
    }



}
