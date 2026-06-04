<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemImportDetail extends Model
{
    // 1. Izin pengisian kolom dari Excel
    protected $fillable = [
        'item_import_batch_id', 'name', 'category_code', 'uom_code',
        'is_stockable', 'is_asset', 'is_trackable',
        'min_stock', 'max_stock', 'specification',
        'is_valid', 'validation_error'
    ];

    // 2. Relasi balik ke Kepala Dokumen (Batch)
    public function batch()
    {
        return $this->belongsTo(ItemImportBatch::class, 'item_import_batch_id');
    }
}
