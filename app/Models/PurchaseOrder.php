<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    // Perusahaan yang membayar tagihan
    public function billToCompany()
    {
        return $this->belongsTo(Company::class, 'bill_to_company_id');
    }


    /**
     * Relasi ke tabel document_approvals (Matriks Persetujuan PO)
     */
    public function approvals()
    {
        return $this->hasMany(DocumentApproval::class, 'document_id')
                    ->where('document_type', self::class)
                    ->orderBy('step_order', 'asc');
    }

    // public function approvals()
    // {
    //     return $this->morphMany(\App\Models\DocumentApproval::class, 'document');
    // }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }


    public function company() {
        return $this->belongsTo(Company::class, 'bill_to_company_id');
    }



    public function extras()
    {
        return $this->hasMany(PurchaseOrderExtra::class); // Pastikan buat model PurchaseOrderExtra dulu jika perlu, atau gunakan DB facade query builder
    }

    // App\Models\PurchaseOrder.php
    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }


    // app/Models/PurchaseOrder.php

    public function charges()
    {
        // Relasi ke tabel purchase_order_charges
        return $this->hasMany(PurchaseOrderCharge::class);
    }


    // Relasi ke tabel lampiran (Attachments)
    public function attachments()
    {
        return $this->hasMany(PurchaseOrderAttachment::class, 'purchase_order_id');
    }


    public function histories()
    {
        // Menampilkan riwayat dari yang terbaru
        return $this->hasMany(PurchaseOrderHistory::class)->latest();
    }



    /**
     * Relasi ke tabel users (Siapa yang membuat PO ini)
     */
    public function user()
    {
        // Asumsinya kolom di tabel purchase_orders adalah 'created_by'
        return $this->belongsTo(User::class, 'created_by');
    }


}
