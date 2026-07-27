<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class GoodsIssueReturn extends Model
{
    protected $guarded = ['id'];

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

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
