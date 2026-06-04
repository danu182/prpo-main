<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemSerial extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function item() {
        return $this->belongsTo(Item::class);
    }

    public function warehouse() {
        return $this->belongsTo(Warehouse::class);
    }

    public function currentUser() {
        return $this->belongsTo(User::class, 'current_user_id');
    }

    // Relasi ke dokumen penerimaan awal
    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    // Relasi ke dokumen retur jika barang ini dikembalikan
    public function returnToVendor()
    {
        return $this->belongsTo(ReturnToVendor::class, 'return_to_vendor_id');
    }


}