<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransferItem extends Model
{
    protected $guarded = ['id'];

    public function stockTransfer() {
        return $this->belongsTo(StockTransfer::class);
    }

    public function item() {
        return $this->belongsTo(Item::class);
    }

    public function inventoryStock() {
        return $this->belongsTo(InventoryStock::class, 'inventory_stock_id');
    }
}
