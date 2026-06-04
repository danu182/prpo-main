<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    protected $guarded = ['id'];

    // 🏠 Relasi ke Gudang
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    // 👤 Relasi ke User (Sesuai dengan 'adjuster' di Controller Komandan)
    public function adjuster()
    {
        // Menggunakan kolom 'adjusted_by' sebagai penghubung
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    // 📦 RELASI KRUSIAL: Ke Tabel Detail Items
    public function items()
    {
        // Ini yang dicari oleh withCount('items') di Controller
        return $this->hasMany(StockAdjustmentItem::class, 'stock_adjustment_id');
    }
}
