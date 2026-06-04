<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Relasi ke Master Stok
     * Setiap pergerakan pasti milik satu record stok tertentu.
     */
    public function stock()
    {
        return $this->belongsTo(InventoryStock::class, 'inventory_stock_id');
    }

    /**
     * Relasi ke User
     * Siapa yang bertanggung jawab atas pergerakan stok ini (Admin Gudang / GA).
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Helper untuk akses Item secara langsung (shortcut)
     * Agar di tampilan history kita bisa langsung tahu ini barang apa tanpa query berulang.
     */
    public function getItemAttribute()
    {
        return $this->stock->item ?? null;
    }
}
