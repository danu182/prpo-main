<?php

namespace App\Imports;

use App\Models\{ItemImportDetail, Category, Uom, ItemType}; // 🔥 Tambah ItemType
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\{ToCollection, WithStartRow, WithMultipleSheets};

class ItemStagingImport implements ToCollection, WithStartRow, WithMultipleSheets
{
    protected $batchId;
    protected $categories;
    protected $uoms;
    protected $itemTypes; // 🔥 Variabel baru

    public function __construct($batchId)
    {
        $this->batchId = $batchId;
        $this->categories = Category::pluck('id', 'code')->mapWithKeys(fn($v, $k) => [strtoupper($k) => $v])->toArray();
        $this->uoms = Uom::pluck('id', 'code')->mapWithKeys(fn($v, $k) => [strtoupper($k) => $v])->toArray();
        // 🔥 Ambil master tipe barang yang aktif
        $this->itemTypes = ItemType::where('is_active', true)->pluck('id', 'code')->mapWithKeys(fn($v, $k) => [strtoupper($k) => $v])->toArray();
    }

    public function sheets(): array { return [0 => $this]; }
    public function startRow(): int { return 2; }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if (empty($row[0])) continue;

            $data = [
                'name' => strtoupper(trim($row[0])),
                'cat'  => strtoupper(trim($row[1] ?? '')),
                'uom'  => strtoupper(trim($row[2] ?? '')),
                'type' => strtoupper(trim($row[3] ?? '')), // 🔥 Kolom D sekarang baca Kode Tipe (STK/NST/JSA)
                'ast'  => strtoupper(trim($row[4] ?? '')) === 'YA' ? 1 : 0,
                'trc'  => strtoupper(trim($row[5] ?? '')) === 'YA' ? 1 : 0,
                'spec' => $row[8] ?? null,
            ];

            $errors = [];
            if (!isset($this->categories[$data['cat']])) $errors[] = "Kategori [{$data['cat']}] salah/tidak aktif";
            if (!isset($this->uoms[$data['uom']])) $errors[] = "Satuan [{$data['uom']}] salah/tidak aktif";
            if (!isset($this->itemTypes[$data['type']])) $errors[] = "Tipe Barang [{$data['type']}] salah/tidak aktif";

            // 🔥 Validasi Bisnis Baru 🔥
            if ($data['ast'] == 1 && $data['type'] === 'JSA') $errors[] = "Jasa tidak bisa dijadikan Aset Tetap";
            if ($data['type'] === 'JSA' && $data['trc'] == 1) $errors[] = "Jasa tidak bisa dilacak fisik";
            if ($data['ast'] == 1 && $data['trc'] == 0) $errors[] = "Barang Aset WAJIB Dilacak";

            ItemImportDetail::create([
                'item_import_batch_id' => $this->batchId,
                'name' => $data['name'],
                'category_code' => $data['cat'],
                'uom_code' => $data['uom'],
                'item_type_code' => $data['type'], // 🔥 Masukkan ke kolom baru
                'is_asset' => $data['ast'],
                'is_trackable' => $data['trc'],
                'specification' => $data['spec'],
                'is_valid' => empty($errors),
                'validation_error' => implode(', ', $errors)
            ]);
        }
    }
}
