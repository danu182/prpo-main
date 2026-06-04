<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/Item.php
class Item extends Model
{
    protected $guarded = ['id'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Scope untuk mempermudah query
    public function scopeAssets($query)
    {
        return $query->where('is_asset', true);
    }


    // Relasi ke riwayat stok (Satu barang punya banyak riwayat keluar-masuk)
    public function stockMutations()
    {
        return $this->hasMany(StockMutation::class)->orderBy('created_at', 'desc');
    }


    public function fixedAssets() {
        return $this->hasMany(FixedAsset::class, 'item_id');
    }



    // Relasi ke Satuan Alternatif (Multi-UOM)
    public function uomConversions()
    {
        return $this->hasMany(ItemUomConversion::class);
    }


    // 🔥 SIHIR LARAVEL: Jadikan 'slug' sebagai default ID di URL 🔥
    public function getRouteKeyName()
    {
        return 'slug';
    }


    // 1. Relasi ke Satuan Dasar (Tabel uoms) - Singular (uom)
    public function uom()
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    // 2. Relasi ke Kemasan Alternatif (Tabel item_uoms) - Plural (uoms)
    public function uoms()
    {
        return $this->hasMany(ItemUom::class, 'item_id');
    }

    public function itemUoms()
    {
        // Relasi: Satu barang punya banyak pilihan kemasan/satuan alternatif
        return $this->hasMany(ItemUom::class, 'item_id');
    }

    // 3. Relasi ke itemType (Tabel item_types)
    public function itemType()
    {
        // Harus ditambah 'code' di ujungnya agar Laravel tidak mencari ke kolom 'id'
        return $this->belongsTo(ItemType::class, 'item_type_code', 'code');
    }


    // Fungsi untuk mengecek apakah barang sudah dipakai transaksi
    public function hasTransactions(): bool
    {
        // Contoh: Cek apakah item ini sudah ada di tabel PR Detail, PO Detail, atau Stok Gudang
        // Ganti nama tabel di bawah sesuai dengan nama tabel transaksi yang Komandan buat nanti
        $inPR = \Illuminate\Support\Facades\DB::table('purchase_request_items')->where('item_id', $this->id)->exists();
        $inPO = \Illuminate\Support\Facades\DB::table('purchase_order_items')->where('item_id', $this->id)->exists();
        // $inInventory = \Illuminate\Support\Facades\DB::table('inventory_ledgers')->where('item_id', $this->id)->exists();
        $inInventory = \Illuminate\Support\Facades\DB::table('inventory_stocks')->where('item_id', $this->id)->exists();

        return $inPR || $inPO || $inInventory; // Mengembalikan nilai TRUE jika minimal ada 1 transaksi
    }



    // ==========================================================
    // 🔥 ACCESSORS (KOLOM VIRTUAL) UNTUK LOGIKA GUDANG & ASET 🔥
    // ==========================================================

    /**
     * Mengecek apakah barang ini butuh masuk gudang fisik (Stok)
     * Kita anggap semua barang dengan tipe 'STK' adalah barang fisik.
     */
    public function getIsStockableAttribute()
    {
        return $this->item_type_code === 'STK';
    }

    /**
     * Mengecek apakah barang ini adalah Aset / Inventaris
     * Kita ambil dari kolom is_trackable (Jika 1 berarti butuh dilacak/dicetak label asetnya)
     */
    public function getIsAssetAttribute()
    {
        return $this->is_trackable == 1;
    }

    // ==========================================
    // RELASI KE TABEL PELACAKAN SERIAL NUMBER
    // ==========================================
    public function serials()
    {
        return $this->hasMany(ItemSerial::class, 'item_id');
    }

    

}
