<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnToVendorItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function returnToVendor()
    {
        return $this->belongsTo(ReturnToVendor::class);
    }

    public function goodsReceiptItem()
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
