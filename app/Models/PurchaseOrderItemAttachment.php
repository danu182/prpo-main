<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItemAttachment extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_item_attachments';

    protected $fillable = [
        'purchase_order_item_id',
        'file_name',
        'file_path',
    ];

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }
}