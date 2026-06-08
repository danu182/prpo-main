<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;




class GoodsReceipt extends Model implements HasMedia
{
    use InteractsWithMedia; // Fitur upload file

    protected $guarded = ['id'];
    use InteractsWithMedia;

    public function items()
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    // Relasi ke Purchase Order
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function po()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }


    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }


    // (Opsional) Konfigurasi Koleksi Media
    // Berguna jika Anda ingin membatasi jenis file atau membuat folder virtual khusus
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gr_documents')
             ->useDisk('public'); // Simpan di folder storage/app/public
    }

    // Tambahkan relasi ini
    public function attachments()
    {
        return $this->hasMany(GoodsReceiptAttachment::class, 'goods_receipt_id');
    }


    // Tambahkan fungsi ini di bagian bawah
    public function returnToVendors()
    {
        return $this->hasMany(ReturnToVendor::class);
    }


    // ====================================================
    // RELASI KE VENDOR INVOICE (TAGIHAN)
    // ====================================================
    public function vendorInvoice()
    {
        return $this->hasOne(VendorInvoice::class, 'goods_receipt_id');
    }


    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }


}
