<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillDiscount extends Model
{
    protected $guarded = ['id'];

    public function billRequest()
    {
        return $this->belongsTo(BillRequest::class);
    }

    public function discountType()
    {
        return $this->belongsTo(DiscountType::class);
    }
}
