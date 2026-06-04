<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class Company extends Model
{
    use HasRoles; // Dari Spatie Permission


    protected $fillable = [
        'code',
        'name',
        'is_head_office',
        'address',
    ];


    public function company()
    {
        return $this->belongsTo(Company::class);
    }



    // Relasi ke stok barang di perusahaan ini
    public function inventoryStocks()
    {
        return $this->hasMany(InventoryStock::class);
    }
}
