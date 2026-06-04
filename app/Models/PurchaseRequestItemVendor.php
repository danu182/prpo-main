<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequestItemVendor extends Model
{
    use HasFactory;

    // 1. Tentukan Nama Tabel dengan Jelas
    protected $table = 'purchase_request_item_vendors';

    // Mengizinkan semua kolom diisi (mass assignment) kecuali ID
    protected $guarded = ['id'];

    /**
     * RELASI: Vendor ini milik Item PR yang mana?
     */
    public function prItem()
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'pr_item_id', 'id');
    }

    /**
     * RELASI: Siapa nama vendornya?
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    /**
     * RELASI: Ke mata uang (Currency)
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    /**
     * RELASI: Ke tabel Lampiran Multi-file (Tabel Baru)
     */
    public function attachments()
    {
        return $this->hasMany(PrVendorAttachment::class, 'pr_item_vendor_id', 'id');
    }

    // Aksesor opsional
    public function getFormattedPriceAttribute()
    {
        return 'Rp ' . number_format($this->quoted_price, 0, ',', '.');
    }
}