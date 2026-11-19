<?php

namespace App\Imports;

use App\Models\FixedAsset;
use App\Models\FixedAssetImportDetail;
use App\Models\Company;
use App\Models\Warehouse;
use App\Models\Status;
use App\Models\AssetCategory;
use App\Models\User;
use App\Models\Item;
use App\Models\Currency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
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
        $defaultAvailableStatus = Status::where('type', 'AST')
            ->whereIn('slug', ['available', 'ready', 'in_warehouse', 'active', 'good'])
            ->orderBy('sequence', 'asc')
            ->first();

        $defaultInUseStatus = Status::where('type', 'AST')
            ->where('slug', 'in_use')
            ->first();

        // Penampung nomor aset akuntansi untuk deteksi duplikat antarbaris dalam 1 file Excel
        $seenAccountingNumbers = [];

        foreach ($rows as $rowIndex => $row) {
            $rawKodeBarang  = $row['kode_barang'] ?? $row['item_code'] ?? null;
            $rawNamaAset    = $row['nama_spesifik_aset'] ?? $row['nama_aset'] ?? null;
            $rawCompanyId   = $row['id_pt'] ?? $row['pt_id'] ?? $row['company_id'] ?? null;
            $rawWarehouseId = $row['id_gudang'] ?? $row['gudang_id'] ?? $row['warehouse_id'] ?? null;
            $rawCategoryId  = $row['id_kategori_aset'] ?? $row['kategori_aset_id'] ?? $row['asset_category_id'] ?? null;
            $rawStatusId    = $row['id_status'] ?? $row['status_id'] ?? null;
            $rawPeminjamId  = $row['id_peminjam'] ?? $row['peminjam_id'] ?? $row['user_id'] ?? null;

            // Lewati baris kosong melompong
            if (empty($rawCompanyId) && empty($rawNamaAset) && empty($rawKodeBarang)) {
                continue;
            }

            $errors = [];

            // 1. Validasi Nama Spesifik Aset atau Kode Barang wajib ada
            if (empty($rawNamaAset) && empty($rawKodeBarang)) {
                $errors[] = "Nama Spesifik Aset atau Kode Barang wajib diisi.";
            }

            // 2. Validasi Kode Barang (cek apakah ada di Master Item)
            if (!empty($rawKodeBarang)) {
                $itemExists = Item::where('code', trim($rawKodeBarang))->first();
                if (!$itemExists) {
                    $errors[] = "Kode Barang '{$rawKodeBarang}' tidak ditemukan di Master Item.";
                }
            }

            // 3. Validasi Duplikat Nomor Aset Akuntansi (Label Akuntansi)
            $labelAkuntansi = isset($row['label_akuntansi']) ? trim((string) $row['label_akuntansi']) : null;
            if (!empty($labelAkuntansi) && $labelAkuntansi !== '-') {
                $normalizedAcc = strtoupper($labelAkuntansi);

                // Cek 1: Apakah sudah pernah muncul di baris sebelumnya pada file Excel yang sama?
                if (isset($seenAccountingNumbers[$normalizedAcc])) {
                    $barisSebelumnya = $seenAccountingNumbers[$normalizedAcc];
                    $errors[] = "Nomor Aset Akuntansi '{$labelAkuntansi}' duplikat dengan baris ke-{$barisSebelumnya} di file Excel ini.";
                } else {
                    $seenAccountingNumbers[$normalizedAcc] = $rowIndex + 2; // +2 karena baris 1 adalah header Excel
                }

                // Cek 2: Apakah sudah terdaftar di Buku Induk Aset (fixed_assets) yang aktif?
                $existsInDb = FixedAsset::where('accounting_asset_number', $labelAkuntansi)
                    ->where(function ($q) {
                        $q->whereNull('notes')
                          ->orWhere('notes', 'not like', '%[DIBATALKAN%');
                    })
                    ->exists();

                if ($existsInDb) {
                    $errors[] = "Nomor Aset Akuntansi '{$labelAkuntansi}' sudah terdaftar di Master Aset.";
                }
            }

            // 4. Validasi PT
            $companyId = !empty($rawCompanyId) ? (int) $rawCompanyId : null;
            if (!$companyId || !Company::where('id', $companyId)->exists()) {
                $errors[] = "ID PT ({$rawCompanyId}) tidak valid / tidak ditemukan.";
            }

            // 5. Validasi Gudang
            $warehouseId = !empty($rawWarehouseId) ? (int) $rawWarehouseId : null;
            if (!$warehouseId || !Warehouse::where('id', $warehouseId)->exists()) {
                $errors[] = "ID Gudang ({$rawWarehouseId}) tidak valid / tidak ditemukan.";
            }

            // 6. Validasi Kategori Aset
            $catId = !empty($rawCategoryId) ? (int) $rawCategoryId : null;
            if (!$catId || !AssetCategory::where('id', $catId)->exists()) {
                $errors[] = "ID Kategori Aset ({$rawCategoryId}) tidak valid / tidak ditemukan.";
            }

            // 7. Validasi Peminjam
            $peminjamId = !empty($rawPeminjamId) ? (int) $rawPeminjamId : null;
            if ($peminjamId && !User::where('id', $peminjamId)->exists()) {
                $errors[] = "ID Peminjam ({$rawPeminjamId}) tidak ditemukan di Master User.";
            }

            // 8. Validasi Status Aset (HARUS type = 'AST')
            $statusId = !empty($rawStatusId) ? (int) $rawStatusId : null;
            $statusObj = $statusId ? Status::where('id', $statusId)->where('type', 'AST')->first() : null;

            if ($statusId && !$statusObj) {
                $errors[] = "ID Status ({$statusId}) bukan status Aset (AST) yang sah.";
            } elseif (!$statusObj) {
                $statusObj = $peminjamId ? $defaultInUseStatus : $defaultAvailableStatus;
                $statusId = $statusObj ? $statusObj->id : null;
            }

            // 9. Validasi Konsistensi Status vs Peminjam
            if ($statusObj) {
                if ($statusObj->slug === 'in_use' && empty($peminjamId)) {
                    $errors[] = "Status 'In Use' wajib mengisi ID Peminjam.";
                }
                if ($statusObj->slug !== 'in_use' && !empty($peminjamId)) {
                    $errors[] = "ID Peminjam diisi, tetapi Status bukan 'In Use' ({$statusObj->name}).";
                }
            }

            // 10. Validasi Mata Uang & Harga Beli (Support Desimal & Pemisah Ribuan)
            $currCode = strtoupper(trim($row['kode_mata_uang'] ?? 'IDR'));
            if (!Currency::where('code', $currCode)->exists()) {
                $errors[] = "Kode Mata Uang '{$currCode}' tidak terdaftar.";
            }

            $rawHarga = $row['harga_beli_angka_murni'] ?? 0;
            if (is_string($rawHarga)) {
                $trimmedHarga = trim($rawHarga);
                // Jika format Indonesia (contoh: 1.500,50 -> 1500.50)
                if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $trimmedHarga)) {
                    $rawHarga = str_replace(['.', ','], ['', '.'], $trimmedHarga);
                } else {
                    // Jika format internasional/Excel (contoh: 19,489,966 atau 758.12)
                    $rawHarga = str_replace(',', '', $trimmedHarga);
                }
            }

            // 11. Validasi Tanggal Perolehan
            $rawTanggal = $row['tanggal_perolehan'] ?? null;
            if (!empty($rawTanggal) && !is_numeric($rawTanggal) && strtotime($rawTanggal) === false) {
                $errors[] = "Format Tanggal Perolehan ('{$rawTanggal}') tidak valid.";
            }

            // Susun hasil validasi
            $isValid = empty($errors) ? 1 : 0;
            $errorJoined = empty($errors) ? null : implode(' | ', $errors);
            $catatanAsli = trim($row['catatan'] ?? '');
            $catatanFinal = $isValid
                ? $catatanAsli
                : '[ERR_LIST]' . implode('||', $errors) . '[/ERR_LIST]' . ($catatanAsli ? ' ' . $catatanAsli : '');

            $dataToCreate = [
                'batch_id'           => $this->batchId,
                'kode_barang'        => $rawKodeBarang,
                'nama_spesifik_aset' => $rawNamaAset,
                'kategori_aset'      => $catId,
                'serial_number'      => $row['serial_number'] ?? null,
                'label_akuntansi'    => $labelAkuntansi,
                'nama_pt'            => $companyId,
                'nama_gudang'        => $warehouseId,
                'status_aset'        => $statusId,
                'nama_peminjam'      => $peminjamId,
                'tanggal_perolehan'  => $rawTanggal,
                'mata_uang'          => $currCode,
                'harga_beli'         => is_numeric($rawHarga) ? (float) $rawHarga : 0,
                'spesifikasi'        => $row['spesifikasi'] ?? null,
                'catatan'            => $catatanFinal,
                'is_valid'           => $isValid,
            ];

            if (Schema::hasColumn('fixed_asset_import_details', 'validation_error')) {
                $dataToCreate['validation_error'] = $errorJoined;
            }
            if (Schema::hasColumn('fixed_asset_import_details', 'error_message')) {
                $dataToCreate['error_message'] = $errorJoined;
            }

            FixedAssetImportDetail::create($dataToCreate);
        }
    }
}
