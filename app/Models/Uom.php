<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Uom extends Model
{
    use HasFactory;

    protected $table='uoms';

    protected $fillable = ['code', 'name', 'description'];

    // Relasi: 1 UOM bisa dipakai oleh banyak Barang (sebagai satuan dasar)
    public function items()
    {
        return $this->hasMany(Item::class, 'uom_id');
    }
}
