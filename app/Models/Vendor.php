<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $guarded = ['id'];



    // Fungsi untuk cek apakah vendor ini sudah pernah dipakai di transaksi (Contoh: PO)
    public function hasTransactions(): bool
    {
        // Nanti sesuaikan dengan tabel Purchase Order Komandan
        $inPO = \Illuminate\Support\Facades\DB::table('purchase_orders')->where('vendor_id', $this->id)->exists();
        // return $inPO;

        return $inPO; // Sementara false dulu sampai kita buat tabel PO-nya
    }


}
