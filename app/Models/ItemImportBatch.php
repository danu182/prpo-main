<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemImportBatch extends Model
{
    // 1. Izin pengisian kolom
    protected $fillable = [
        'batch_number', 'created_by', 'approved_by', 'status', 'reject_reason'
    ];

    // 2. Relasi ke detail (Isi baris Excel)
    public function details()
    {
        return $this->hasMany(ItemImportDetail::class);
    }

    // 3. Relasi ke lampiran (File PDF/Image)
    public function attachments()
    {
        return $this->hasMany(ItemImportAttachment::class);
    }

    // 4. Relasi ke pembuat dokumen (User)
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // 5. Relasi dinamis ke tabel Master Statuses
    public function statusInfo()
    {
        // Hubungkan kolom 'status' di tabel ini dengan kolom 'slug' di tabel statuses
        // Dan pastikan hanya mengambil yang type-nya 'IMPORT'
        return $this->belongsTo(Status::class, 'status', 'slug')
                    ->where('type', 'IMPORT');
    }


}
