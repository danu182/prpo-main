<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_number',
        'item_id',
        'goods_receipt_id',
        'serial_number',
        'accounting_asset_number',
        'acquisition_date',
        'purchase_price', // <- Tambahkan ini
        'currency_id',
        'supporting_document', // 🔥 Tambahkan ini
        'spesifikasi_detail',
        'status_id', // <--- Ganti 'status' menjadi 'status_id'
        'assigned_to',
        'notes',
        'name', // <---- WAJIB DITAMBAHKAN DI SINI
        'company_id',
        'batch_id', // 🔥 Tambahkan ini agar bisa disimpan secara massal
        'warehouse_id',
    ];

    // Relasi ke Master Barang
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    // Relasi ke Penerimaan Barang
    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    // Relasi ke User (Pemegang Aset)
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }


    // Tambahkan ini di bawah relasi yang sudah ada
    public function histories()
    {
        return $this->hasMany(FixedAssetHistory::class)->orderBy('created_at', 'desc');
    }


    // Tambahkan kode relasi ini:
    public function fixedAsset()
    {
        // Menyambungkan histori ini kembali ke Aset Tetap Induknya
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }


    // Relasi ke Entitas / PT Pemilik Aset
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }


    // Relasi ke Master Status
    public function status()
    {
        return $this->belongsTo(\App\Models\Status::class, 'status_id');
    }


    public function importBatch()
    {
        // Parameter: (Model Tujuan, foreign_key di tabel aset, local_key di tabel tujuan)
        return $this->belongsTo(ImportBatch::class, 'batch_id', 'batch_id');
    }


    public function user()
    {
        // Karena kolom di database kita bernama 'assigned_to',
        // kita harus memberitahu Laravel secara spesifik.
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }


    // 🔥 SUNTIKAN BARU: RELASI KE TABEL GUDANG 🔥
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }


    /**
     * Relasi ke tabel Currency (Mata Uang)
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }


    // Accessor untuk Nomor BAST Individu
    public function getBastNumberAttribute()
    {
        // Gunakan tanggal aset dibuat (created_at) agar nomor tidak berubah saat dicetak beda hari
        $tanggal = \Carbon\Carbon::parse($this->created_at)->format('Y/m/d');
        $urutan = substr($this->asset_number, -4);

        return "BAST-AST/{$tanggal}/{$urutan}";
    }



    /**
     * Relasi ke tabel Department (Departemen / Divisi)
     */
    public function department()
    {
        return $this->belongsTo(\App\Models\Department::class, 'department_id');
    }



}
