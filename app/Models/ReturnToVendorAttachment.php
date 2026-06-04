<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnToVendorAttachment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function returnToVendor()
    {
        return $this->belongsTo(ReturnToVendor::class, 'return_to_vendor_id');
    }
}