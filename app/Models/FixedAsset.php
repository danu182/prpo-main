<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    use HasFactory;

    protected $guarded = [];

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


    // 1. Relasi ke Kategori Aset
    public function assetCategory()
    {
        return $this->belongsTo(\App\Models\AssetCategory::class, 'asset_category_id');
    }

    // 2. Hitung Penyusutan per Tahun
    public function getAnnualDepreciationAttribute()
    {
        $usefulLife = optional($this->assetCategory)->useful_life_years ?? 4;
        if ($usefulLife <= 0) return 0;
        return $this->purchase_price / $usefulLife;
    }

    // 3. Hitung Penyusutan per Bulan
    public function getMonthlyDepreciationAttribute()
    {
        return $this->annual_depreciation / 12;
    }

    // 4. Hitung Nilai Buku Saat Ini (Berdasarkan acquisition_date)
    public function getNetBookValueAttribute()
    {
        if (!$this->acquisition_date) return $this->purchase_price; // <-- Diubah ke acquisition_date

        $monthsUsed = \Carbon\Carbon::parse($this->acquisition_date)->diffInMonths(now());
        $accumulatedDepreciation = $monthsUsed * $this->monthly_depreciation;
        $netBookValue = $this->purchase_price - $accumulatedDepreciation;

        return $netBookValue > 0 ? $netBookValue : 0;
    }


}
