<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_number', 'vendor_invoice_id', 'payment_date',
        'payment_method', 'bank_name', 'reference_number',
        'amount', 'proof_file', 'notes', 'created_by'
    ];

    // Relasi ke Tagihan
    public function invoice()
    {
        return $this->belongsTo(VendorInvoice::class, 'vendor_invoice_id');
    }

    // Relasi ke Pembuat (User)
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }


    public function attachments() 
    {
        return $this->hasMany(VendorPaymentAttachment::class, 'vendor_payment_id');
    }

}
