<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderHistory extends Model
{
    protected $fillable = ['purchase_order_id', 'user_id', 'action', 'note'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
