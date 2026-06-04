<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class BillPayment extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;


    protected $table = 'bill_payments';

    protected $guarded = ['id'];

    // Relasi balik ke Tagihan

    // Relasi Balik: Pembayaran ini milik Tagihan siapa?
    public function billRequest()
    {
        return $this->belongsTo(BillRequest::class, 'bill_request_id');
    }

    // Tambahkan relasi ke metode pembayaran
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    // app/Models/BillPayment.php

    public function paidByCompany()
    {
        // Relasi ke PT yang melakukan pembayaran
        return $this->belongsTo(Company::class, 'paid_by_company_id');
    }


}
