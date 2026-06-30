<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixedAssetImportDetail extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function batch()
    {
        return $this->belongsTo(FixedAssetImportBatch::class, 'batch_id');
    }
}
