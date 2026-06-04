<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class   PurchaseOrderAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'file_name',
        'file_path',
    ];

    // Relasi balik ke Purchase Order
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}
