<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class BillRequest extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'bill_requests';

    // PERBAIKAN: Harus Array ['id'], bukan String 'id'
    protected $guarded = ['id'];

    // Relasi ke User
    public function user() {
        return $this->belongsTo(User::class);
    }

    // Relasi ke Company (Optional)
    public function company() {
        return $this->belongsTo(Company::class);
    }

    // Relasi Polymorphic ke History
    public function histories() {
        return $this->morphMany(History::class, 'record')->latest();
    }

    // Tambahkan relasi ini
    public function items()
    {
        return $this->hasMany(BillItem::class);
    }

    // Relasi ke tabel pembayaran
    public function payments()
    {
        return $this->hasMany(BillPayment::class, 'bill_request_id');
    }


    // Relasi ke tabel Biaya Tambahan
    public function charges()
    {
        return $this->hasMany(BillCharge::class, 'bill_request_id');
    }

    // Relasi ke tabel Diskon Global
    public function discounts()
    {
        return $this->hasMany(BillDiscount::class, 'bill_request_id');
    }

    // Di dalam file App\Models\BillRequest.php
    public function status()
    {
        return $this->belongsTo(\App\Models\Status::class, 'status_id');
    }


    public function attachments()
    {
        // Ganti 'BillAttachment' dengan nama Model lampiran yang Anda gunakan
        return $this->hasMany(\App\Models\BillAttachment::class, 'bill_request_id');
    }

}
