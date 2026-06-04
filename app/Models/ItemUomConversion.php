<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemUomConversion extends Model
{
    use HasFactory;

    protected $table='item_uom_conversions';

    protected $fillable = ['item_id', 'alternate_uom_id', 'conversion_rate'];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function alternateUom()
    {
        return $this->belongsTo(Uom::class, 'alternate_uom_id');
    }
}
