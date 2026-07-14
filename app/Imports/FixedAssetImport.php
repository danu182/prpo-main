<?php

namespace App\Imports;

use App\Models\FixedAssetImportDetail;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FixedAssetImport implements ToCollection, WithHeadingRow
{
    protected $batchId;

    public function __construct($batchId)
    {
        $this->batchId = $batchId;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // 1. Abaikan baris jika Kode Barang DAN Nama Spesifik kosong semua
            if (empty(trim($row['kode_barang'] ?? '')) && empty(trim($row['nama_spesifik_aset'] ?? ''))) {
                continue;
            }

            // 2. Format Tanggal Perolehan dari Excel
            $rawDate = $row['tanggal_perolehan'] ?? null;
            $acqDate = null;
            if (!empty($rawDate)) {
                if (is_numeric($rawDate)) {
                    $acqDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($rawDate)->format('Y-m-d');
                } else {
                    try {
                        $acqDate = \Carbon\Carbon::parse(str_replace('/', '-', $rawDate))->format('Y-m-d');
                    } catch (\Exception $e) {
                        $acqDate = $rawDate; // Fallback jika format aneh
                    }
                }
            }

            // 3. Validasi Dasar (Sistem tidak akan error, hanya menandai baris ini tidak valid di Karantina)
            $isValid = true;
            $errorMsg = [];

            if (empty(trim($row['nama_pt'] ?? ''))) {
                $isValid = false;
                $errorMsg[] = 'Nama PT kosong.';
            }
            if (empty(trim($row['nama_gudang'] ?? ''))) {
                $isValid = false;
                $errorMsg[] = 'Nama Gudang kosong.';
            }

            // 4. Masukkan ke Tabel Karantina (Staging Detail)
            FixedAssetImportDetail::create([
                'batch_id'           => $this->batchId,
                'kode_barang'        => trim($row['kode_barang'] ?? ''),
                'nama_spesifik_aset' => trim($row['nama_spesifik_aset'] ?? ''),
                'kategori_aset'      => trim($row['kategori_aset'] ?? ''), // 🔥 TAMBAHAN UNTUK MENANGKAP KATEGORI DARI EXCEL
                'serial_number'      => trim($row['serial_number'] ?? ''),
                'label_akuntansi'    => trim($row['label_akuntansi'] ?? ''),
                'nama_pt'            => trim($row['nama_pt'] ?? ''),
                'nama_gudang'        => trim($row['nama_gudang'] ?? ''),
                'status_aset'        => trim($row['status'] ?? 'Available (Tersedia)'),
                'nama_peminjam'      => trim($row['nama_peminjam'] ?? ''),
                'tanggal_perolehan'  => $acqDate,
                'mata_uang'          => strtoupper(trim($row['mata_uang'] ?? 'IDR')),
                'harga_beli'         => preg_replace('/[^0-9]/', '', $row['harga_beli_angka_murni'] ?? 0),
                'spesifikasi'        => trim($row['spesifikasi'] ?? ''),
                'catatan'            => trim($row['catatan'] ?? ''),
                'is_valid'           => $isValid,
                'validation_error'   => implode(' | ', $errorMsg),
            ]);
        }
    }
}
