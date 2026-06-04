<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustmentItem extends Model
{
    protected $guarded = ['id'];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
