<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemImportDetail extends Model
{
    // 1. Izin pengisian kolom dari Excel
    protected $guarded = ['id'];

    // Relasi ke Batch (Header)
    public function batch()
    {
        return $this->belongsTo(ItemImportBatch::class, 'item_import_batch_id');
    }
}
