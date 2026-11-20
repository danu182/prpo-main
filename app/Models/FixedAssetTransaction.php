<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedAssetTransaction extends Model
{
    protected $guarded = ['id'];

    public function details()
    {
        return $this->hasMany(FixedAssetTransactionDetail::class, 'fixed_asset_transaction_id');
    }

    public function asset()
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}