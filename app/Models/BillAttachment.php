<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillAttachment extends Model
{
    // 🔥 PENTING: Ubah nama tabel di bawah ini sesuai dengan nama tabel lampiran tagihan di database Anda!
    // (Misalnya: 'bill_attachments', 'bill_request_attachments', atau 'media')
    protected $table = 'bill_attachments';

    // Buka gembok keamanan
    protected $guarded = [];

    // Relasi balik ke tagihan induknya
    public function billRequest()
    {
        return $this->belongsTo(BillRequest::class, 'bill_request_id');
    }
}
