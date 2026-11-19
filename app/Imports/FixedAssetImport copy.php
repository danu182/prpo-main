<?php

namespace App\Imports;

use App\Models\FixedAssetImportDetail;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class FixedAssetImport implements ToCollection, WithHeadingRow, WithCalculatedFormulas
{
    protected $batchId;

    public function __construct($batchId)
    {
        $this->batchId = $batchId;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Lewati baris yang kosong melompong di Excel
            if (empty($row['id_pt']) && empty($row['nama_spesifik_aset']) && empty($row['kode_barang'])) {
                continue;
            }

            // Trik Senior: Kita simpan ID Angka dari Excel ke dalam kolom string yang sudah ada di tabel Staging Anda.
            FixedAssetImportDetail::create([
                'batch_id'           => $this->batchId,
                'kode_barang'        => $row['kode_barang'] ?? null,
                'nama_spesifik_aset' => $row['nama_spesifik_aset'] ?? null,
                'kategori_aset'      => $row['id_kategori_aset'] ?? null, // Berisi Angka ID
                'serial_number'      => $row['serial_number'] ?? null,
                'label_akuntansi'    => $row['label_akuntansi'] ?? null,
                'nama_pt'            => $row['id_pt'] ?? null,            // Berisi Angka ID
                'nama_gudang'        => $row['id_gudang'] ?? null,        // Berisi Angka ID
                'status_aset'        => $row['id_status'] ?? null,        // Berisi Angka ID
                'nama_peminjam'      => $row['id_peminjam'] ?? null,      // Berisi Angka ID
                'tanggal_perolehan'  => $row['tanggal_perolehan'] ?? null,
                'mata_uang'          => $row['kode_mata_uang'] ?? 'IDR',
                'harga_beli'         => $row['harga_beli_angka_murni'] ?? 0,
                'spesifikasi'        => $row['spesifikasi'] ?? null,
                'catatan'            => $row['catatan'] ?? null,
                'is_valid'           => 1, // Bebas error ketik karena pakai ID
            ]);
        }
    }
}
