<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemImportAttachment extends Model
{
    // 1. Izin pengisian nama dan lokasi file
    protected $fillable = [
        'item_import_batch_id', 'file_name', 'file_path'
    ];

    // 2. Relasi balik ke Kepala Dokumen (Batch)
    public function batch()
    {
        return $this->belongsTo(ItemImportBatch::class, 'item_import_batch_id');
    }
}
