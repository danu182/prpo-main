<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillCharge extends Model
{
    protected $guarded = ['id'];

    public function billRequest()
    {
        return $this->belongsTo(BillRequest::class);
    }

    public function chargeType()
    {
        return $this->belongsTo(ChargeType::class);
    }
}
