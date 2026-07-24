<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodsIssueItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    // Relasi balik ke Header GI
    public function goodsIssue()
    {
        return $this->belongsTo(GoodsIssue::class);
    }

    // Relasi ke Master Barang
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
