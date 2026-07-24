<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodsIssue extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    // Relasi ke detail item yang dikeluarkan
    public function items()
    {
        return $this->hasMany(GoodsIssueItem::class);
    }

    // Relasi ke User (Staf Gudang yang mengeluarkan)
    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }


    // 🔥 RELASI KE TABEL STATUS
    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }


    // 🔥 TAMBAHKAN RELASI GUDANG INI 🔥
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    // 🔥 TAMBAHKAN RELASI RIWAYAT RETUR 🔥
    public function returns()
    {
        return $this->hasMany(GoodsIssueReturn::class, 'goods_issue_id');
    }

    // Accessor untuk Nomor BAST Borongan
    public function getBastNumberAttribute()
    {
        // Gunakan tanggal pengeluaran (issue_date)
        $tanggal = \Carbon\Carbon::parse($this->issue_date)->format('Y/m/d');
        $urutan = substr($this->gi_number, -4);

        return "BAST-GI/{$tanggal}/{$urutan}";
    }


}
