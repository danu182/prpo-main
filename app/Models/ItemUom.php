<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemUom extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'uom_name',
        'conversion_qty',
        'is_default_purchase',
        'is_default_issue',
        'barcode'
    ];

    // Relasi balik ke Master Barang
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
