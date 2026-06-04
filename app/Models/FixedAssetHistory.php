<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixedAssetHistory extends Model
{
    use HasFactory;

    protected $fillable = ['fixed_asset_id', 'status', 'assigned_to', 'notes', 'created_by'];

    public function assignee() {
        return $this->belongsTo(User::class, 'assigned_to');
    }
    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }


    // Tambahkan kode relasi ini: (HAPUS BAGIAN INI DARI FIXED ASSET)
    public function fixedAsset()
    {
        // Menyambungkan histori ini kembali ke Aset Tetap Induknya
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

}
