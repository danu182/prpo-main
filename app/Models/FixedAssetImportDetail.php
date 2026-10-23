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

    // Relasi untuk menarik Nama PT berdasarkan ID yang disimpan di kolom nama_pt
    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class, 'nama_pt', 'id');
    }

    // Relasi untuk menarik Nama Gudang berdasarkan ID yang disimpan di kolom nama_gudang
    public function warehouse()
    {
        return $this->belongsTo(\App\Models\Warehouse::class, 'nama_gudang', 'id');
    }

    // Relasi untuk menarik Nama Status berdasarkan ID yang disimpan di kolom status_aset
    public function statusAsset()
    {
        return $this->belongsTo(\App\Models\Status::class, 'status_aset', 'id');
    }

    // Relasi untuk menarik Nama User / Peminjam berdasarkan ID
    public function assignee()
    {
        return $this->belongsTo(\App\Models\User::class, 'nama_peminjam', 'id');
    }

}
