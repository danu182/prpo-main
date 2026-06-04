<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodsReceiptAttachment extends Model
{
    use HasFactory;

    // WAJIB DITAMBAHKAN AGAR TIDAK ERROR
    protected $fillable = [
        'goods_receipt_id',
        'file_name',
        'file_path'
    ];
}
