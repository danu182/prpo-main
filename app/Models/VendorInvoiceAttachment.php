<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorInvoiceAttachment extends Model
{
    protected $guarded = [];


    // Relasi balik ke Invoice (Opsional tapi bagus untuk kerapian)
    public function invoice()
    {
        return $this->belongsTo(VendorInvoice::class, 'vendor_invoice_id');
    }
    

}
