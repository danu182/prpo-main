<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnToVendor extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function returner()
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function items()
    {
        return $this->hasMany(ReturnToVendorItem::class);
    }


    // ==========================================
    // RELASI KE LAMPIRAN BUKTI RETUR
    // ==========================================
    public function attachments()
    {
        return $this->hasMany(ReturnToVendorAttachment::class, 'return_to_vendor_id');
    }


}
