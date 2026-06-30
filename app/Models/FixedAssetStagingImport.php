<?php

namespace App\Imports;

use App\Models\FixedAssetImportDetail;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FixedAssetStagingImport implements ToCollection, WithHeadingRow
{
    protected $batchId;

    public function __construct($batchId)
    {
        $this->batchId = $batchId;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Abaikan jika nama dan kode kosong semua
            if (empty(trim($row['nama_spesifik_aset'] ?? '')) && empty(trim($row['kode_barang'] ?? ''))) {
                continue;
            }

            // Validasi Sederhana di Awal (Bisa disempurnakan di Karantina nanti)
            $isValid = true;
            $errorMsg = [];

            if (empty(trim($row['nama_pt'] ?? ''))) {
                $isValid = false;
                $errorMsg[] = 'PT Kosong.';
            }
            if (empty(trim($row['nama_gudang'] ?? ''))) {
                $isValid = false;
                $errorMsg[] = 'Gudang Kosong.';
            }

            // Masukkan ke tabel Detail Karantina
            FixedAssetImportDetail::create([
                'batch_id'           => $this->batchId,
                'kode_barang'        => trim($row['kode_barang'] ?? ''),
                'nama_spesifik_aset' => trim($row['nama_spesifik_aset'] ?? ''),
                'serial_number'      => trim($row['serial_number'] ?? ''),
                'label_akuntansi'    => trim($row['label_akuntansi'] ?? ''),
                'nama_pt'            => trim($row['nama_pt'] ?? ''),
                'nama_gudang'        => trim($row['nama_gudang'] ?? ''),
                'status_aset'        => trim($row['status'] ?? 'Available'),
                'nama_peminjam'      => trim($row['nama_peminjam'] ?? ''),
                'tanggal_perolehan'  => trim($row['tanggal_perolehan'] ?? ''),
                'mata_uang'          => trim($row['mata_uang'] ?? 'IDR'),
                'harga_beli'         => preg_replace('/[^0-9]/', '', $row['harga_beli_angka_murni'] ?? 0),
                'spesifikasi'        => trim($row['spesifikasi'] ?? ''),
                'catatan'            => trim($row['catatan'] ?? ''),
                'is_valid'           => $isValid,
                'validation_error'   => implode(' ', $errorMsg),
            ]);
        }
    }
}
