<?php

namespace App\Imports;

use App\Models\{ItemImportDetail, Category, Uom, ItemType};
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\{ToCollection, WithStartRow, WithMultipleSheets};

class ItemStagingImport implements ToCollection, WithStartRow, WithMultipleSheets
{
    protected $batchId;
    protected $categories;
    protected $uoms;
    protected $itemTypes;

    public function __construct($batchId)
    {
        $this->batchId = $batchId;
        $this->categories = Category::pluck('id', 'code')->mapWithKeys(fn($v, $k) => [strtoupper($k) => $v])->toArray();
        $this->uoms = Uom::pluck('id', 'code')->mapWithKeys(fn($v, $k) => [strtoupper($k) => $v])->toArray();

        // 🔥 Ambil master tipe barang yang aktif
        $this->itemTypes = ItemType::where('is_active', true)->pluck('id', 'code')->mapWithKeys(fn($v, $k) => [strtoupper($k) => $v])->toArray();
    }

    public function sheets(): array { return [0 => $this]; }
    public function startRow(): int { return 2; } // Lewati Baris 1 (Header)

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Abaikan baris jika Nama Barang kosong
            if (empty($row[0])) continue;

            // 🔥 Mapping Index Excel Terbaru:
            // 0: Nama, 1: Kategori, 2: UOM, 3: Tipe (STK/JSA), 4: Lacak (Y/N), 5: Min Stok, 6: Max Stok, 7: Spek
            $data = [
                'name'      => strtoupper(trim($row[0])),
                'cat'       => strtoupper(trim($row[1] ?? '')),
                'uom'       => strtoupper(trim($row[2] ?? '')),
                'type'      => strtoupper(trim($row[3] ?? 'STK')),
                'trc'       => strtoupper(trim($row[4] ?? 'N')) === 'Y' ? 1 : 0, // Y jadi 1, sisanya 0
                'min_stock' => isset($row[5]) ? (float)$row[5] : 0,
                'max_stock' => isset($row[6]) ? (float)$row[6] : 0,
                'spec'      => $row[7] ?? null,
            ];

            // 🔥 Proses Validasi Data Master
            $errors = [];
            if (!isset($this->categories[$data['cat']])) $errors[] = "Kategori [{$data['cat']}] salah/tidak aktif";
            if (!isset($this->uoms[$data['uom']])) $errors[] = "Satuan [{$data['uom']}] salah/tidak aktif";
            if (!isset($this->itemTypes[$data['type']])) $errors[] = "Tipe Barang [{$data['type']}] salah/tidak aktif";

            // 🔥 Validasi Bisnis (Khusus Jasa)
            if ($data['type'] === 'JSA') {
                if ($data['trc'] == 1) $errors[] = "Jasa tidak bisa dilacak fisik (Tidak punya S/N)";
            }

            // Simpan ke tabel Staging (Karantina)
            ItemImportDetail::create([
                'item_import_batch_id' => $this->batchId,
                'name'                 => $data['name'],
                'category_code'        => $data['cat'],
                'uom_code'             => $data['uom'],
                'item_type_code'       => $data['type'],
                'is_trackable'         => $data['trc'],

                // Kolom Jadul (Aset/Stok) di-Nol-kan saja agar tidak error SQL jika kolomnya masih ada di database
                'is_asset'             => 0,
                // 'is_stockable'         => 0,

                'min_stock'            => $data['min_stock'],
                'max_stock'            => $data['max_stock'],
                'specification'        => $data['spec'],

                'is_valid'             => empty($errors),
                'validation_error'     => empty($errors) ? null : implode(', ', $errors)
            ]);
        }
    }
}
