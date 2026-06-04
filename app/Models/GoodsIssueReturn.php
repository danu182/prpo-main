<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsIssueReturn extends Model
{
    protected $fillable = [
        'goods_issue_id', 'return_number', 'return_date',
        'returned_by_name', 'received_by', 'notes',
        'warehouse_id', // 🔥 INI WAJIB ADA! JIKA TIDAK, AKAN JADI NULL DI DATABASE!
    ];

    public function goodsIssue()
    {
        return $this->belongsTo(GoodsIssue::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items()
    {
        return $this->hasMany(GoodsIssueReturnItem::class);
    }


    // 🔥 RELASI BARU: Ke tabel Gudang (INILAH YANG DIMINTA OLEH ERROR TADI) 🔥
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }



}
