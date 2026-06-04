<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillItem extends Model
{
    protected $guarded = ['id'];

    public function billRequest()
    {
        return $this->belongsTo(BillRequest::class);
    }
}
