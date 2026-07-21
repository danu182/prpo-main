<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpnameAttachment extends Model
{
    use HasFactory;

    protected $fillable = ['stock_opname_id', 'file_name', 'file_path'];

    public function stockOpname() { return $this->belongsTo(StockOpname::class); }
}
