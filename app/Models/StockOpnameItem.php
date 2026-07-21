<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_opname_id', 'item_id', 'base_uom', 'system_qty',
        'actual_qty', 'variance_qty', 'unit_price', 'system_value',
        'actual_value', 'variance_value', 'input_qty', 'input_uom_id', 'notes'
    ];

    public function stockOpname() { return $this->belongsTo(StockOpname::class); }
    public function item() { return $this->belongsTo(Item::class); }
    public function inputUom() { return $this->belongsTo(ItemUom::class, 'input_uom_id'); }
}
