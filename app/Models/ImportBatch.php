<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    use HasFactory;

    // Tentukan nama tabel secara eksplisit agar aman
    protected $table = 'import_batches';

    // Kolom yang diizinkan untuk diisi secara massal (Mass Assignment)
    protected $fillable = [
        'batch_id',
        'file_name',
        'support_doc',
        'total_items',
        'created_by',
    ];

    /**
     * Relasi ke tabel Users:
     * Untuk mengetahui siapa Admin/User yang melakukan eksekusi Import ini.
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi ke tabel FixedAssets:
     * Untuk menarik semua aset yang tergabung dalam satu Batch ID yang sama.
     * Sangat berguna nanti untuk fitur "Cetak BAST Massal per Batch".
     */
    public function assets()
    {
        // Parameter: (Model Tujuan, foreign_key di tujuan, local_key di tabel ini)
        return $this->hasMany(FixedAsset::class, 'batch_id', 'batch_id');
    }
}
