<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetPhoto extends Model
{
    protected $fillable = ['fixed_asset_id', 'file_path'];

    public function fixedAsset()
    {
        return $this->belongsTo(FixedAsset::class);
    }
}
