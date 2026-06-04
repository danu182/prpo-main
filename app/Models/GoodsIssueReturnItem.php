<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsIssueReturnItem extends Model
{
    protected $fillable = [
        'goods_issue_return_id', 'goods_issue_item_id',
        'item_id', 'qty_returned', 'notes'
    ];

    public function returnHeader()
    {
        return $this->belongsTo(GoodsIssueReturn::class, 'goods_issue_return_id');
    }

    public function goodsIssueItem()
    {
        return $this->belongsTo(GoodsIssueItem::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
