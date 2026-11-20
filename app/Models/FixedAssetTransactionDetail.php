<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedAssetTransactionDetail extends Model
{
    protected $guarded = ['id'];

    public function transaction()
    {
        return $this->belongsTo(FixedAssetTransaction::class, 'fixed_asset_transaction_id');
    }

    public function asset()
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function previousWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'previous_warehouse_id');
    }

    public function previousUser()
    {
        return $this->belongsTo(User::class, 'previous_user_id');
    }
}