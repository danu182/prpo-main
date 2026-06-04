<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeInventory extends Model
{
    protected $fillable = [
        'employee_name',
        'item_id',
        'specific_details', // <--- TAMBAHKAN INI
        'qty'
    ];

    // Relasi ke tabel Master Barang
    public function item()
    {
        return $this->belongsTo(Item::class);
    }


    /**
     * Relasi ke data Penerimaan Barang (GR)
     */
    public function goodsReceipt()
    {
        // Pastikan nama model GoodsReceipt sesuai dengan yang ada di aplikasi Komandan
        return $this->belongsTo(\App\Models\GoodsReceipt::class, 'goods_receipt_id');
    }
}
