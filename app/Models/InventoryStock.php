<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryStock extends Model
{
    protected $guarded = ['id'];


    // Relasi ke Item Master
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    // Relasi ke Perusahaan pemilik stok
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Relasi ke History Pergerakan (Kartu Stok)
    // Digunakan untuk menampilkan tabel "Riwayat Transaksi" pada detail stok barang
    public function movements()
    {
        return $this->hasMany(InventoryMovement::class)->latest();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

}
