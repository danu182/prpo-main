<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMutation extends Model
{
    use HasFactory;

    // protected $fillable = [
    //     'item_id',
    //     'type',
    //     'qty',
    //     'balance_before',
    //     'balance_after',
    //     'reference_number',
    //     'notes',
    //     'created_by'
    // ];


    protected $guarded = ['id'];

    // Relasi ke Master Barang
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    // Relasi ke User (Staf Gudang)
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }


    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
