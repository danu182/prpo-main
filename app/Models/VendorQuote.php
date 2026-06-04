<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;           // Jika pakai Spatie Media Library
use Spatie\MediaLibrary\InteractsWithMedia; // Jika pakai Spatie Media Library

class VendorQuote extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'purchase_request_item_id',
        'vendor_id',
        'quoted_price',
        'is_selected',
        'notes',
        'reference_link'
    ];

    protected $casts = [
        'is_selected' => 'boolean',
        'quoted_price' => 'decimal:2'
    ];

    // Relasi Balik ke Item PR
    public function prItem()
    {
        // Sesuaikan nama Model item Anda jika bukan 'PurchaseRequestItem'
        return $this->belongsTo(PurchaseRequestItem::class, 'purchase_request_item_id');
    }

    // Relasi ke Master Vendor
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
