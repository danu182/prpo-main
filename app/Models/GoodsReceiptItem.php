<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    use HasFactory;

    // WAJIB DITAMBAHKAN BAGIAN INI
    protected $guarded = ['id'];

    // 1. Relasi kembali ke Header Goods Receipt
    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    // 2. Relasi ke Master Barang (Ini yang menyebabkan error tadi)
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    // 3. Relasi ke Kondisi Barang (Baik, Rusak, dll)
    public function condition()
    {
        return $this->belongsTo(ItemCondition::class, 'condition_id');
    }

    // 4. Relasi kembali ke item PO (Untuk menampilkan Qty Pesan di kertas cetak)
    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }


}
