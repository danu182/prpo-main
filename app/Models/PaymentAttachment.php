<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAttachment extends Model
{
    // 🔥 PENTING: Ubah nama tabel di bawah ini sesuai dengan nama tabel asli penyimpan bukti transfer di database Anda!
    // (Misalnya: 'bill_payment_attachments' atau 'payment_attachments')
    protected $table = 'payment_attachments';

    // Buka gembok keamanan
    protected $guarded = [];

    // Relasi balik ke pembayaran induknya
    public function billPayment()
    {
        return $this->belongsTo(BillPayment::class, 'bill_payment_id');
    }
}
