<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $guarded = ['id'];

    // Pastikan is_active dibaca sebagai true/false
    protected $casts = [
        'is_active' => 'boolean',
    ];


    public function inventoryStocks()
    {
        return $this->hasMany(InventoryStock::class);
    }
}
