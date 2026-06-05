<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/PurchaseRequestItem.php
class PurchaseRequestItem extends Model
{
    protected $guarded = ['id'];


    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
        // return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    // Menghitung sisa qty yang belum di-PO (PENTING UNTUK SPLIT PO)
    public function getRemainingQtyAttribute()
    {
        return $this->qty - $this->ordered_qty;
    }


    // Relasi ke tabel baru tadi
    // public function vendorQuotes()
    // {
    //     return $this->hasMany(PurchaseRequestItemVendor::class, 'pr_item_id');
    // }


    public function vendorQuotes2()
    {
        return $this->hasMany(VendorQuote::class, 'purchase_request_item_id');
    }

    // Relasi (Jika belum ada, tambahkan sekalian agar View Edit bisa memanggilnya)
    public function uom()
    {
        return $this->belongsTo(Uom::class, 'uom_id'); // Sesuaikan dengan nama model UOM Komandan
    }


    /**
     * ACCESSOR UNTUK LABEL STATUS BARANG
     * Mengubah string database menjadi Label & Warna Class Bootstrap
     */
    public function getStatusBadgeAttribute()
    {
        $statusData = [
            'PENDING'   => ['label' => 'MENUNGGU',   'color' => 'warning text-dark'],
            'APPROVED'  => ['label' => 'DISETUJUI',  'color' => 'success'],
            'REJECTED'  => ['label' => 'DITOLAK',    'color' => 'danger'],
            'CANCELLED' => ['label' => 'DIBATALKAN', 'color' => 'secondary'],
        ];

        // Jika status ada di array atas, panggil. Jika tidak, kembalikan warna default.
        return $statusData[$this->status] ?? ['label' => $this->status, 'color' => 'light text-dark'];
    }


    public function vendorQuotes()
    {
        return $this->hasMany(PurchaseRequestItemVendor::class, 'pr_item_id', 'id');
    }


    // 1. Fungsi untuk mengambil Satuan Singkat (Contoh: "Karung / Sak" atau "PCS")
    public function getUomShortAttribute()
    {
        $defaultUom = $this->getRawOriginal('uom') ?? 'PCS';
        if ($this->item && $this->uom_id != $this->item->uom_id) {
            $alt = $this->item->itemUoms->where('id', $this->uom_id)->first();
            return $alt ? ($alt->uom_name ?? $defaultUom) : $defaultUom;
        }
        return $defaultUom;
    }

    // 2. Fungsi untuk mengambil Detail Konversi (Contoh: "(Isi: 5 Kilogram)")
    public function getUomDetailAttribute()
    {
        if ($this->item && $this->uom_id != $this->item->uom_id) {
            $alt = $this->item->itemUoms->where('id', $this->uom_id)->first();
            if ($alt) {
                $uConv = (float)($alt->conversion_qty ?? 1);
                $baseUomName = optional($this->item->uom)->name ?? '';
                return "(Isi: {$uConv} {$baseUomName})";
            }
        }
        return "";
    }



    // Relasi untuk menarik data PO yang nyangkut ke Item PR ini
    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_request_item_id');
    }



}
