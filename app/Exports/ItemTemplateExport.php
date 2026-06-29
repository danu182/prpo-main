<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Models\Category;
use App\Models\Uom;
use App\Models\ItemType;

class ItemTemplateExport implements WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            // SHEET 1: FORM INPUT UTAMA
            new class implements FromArray, WithHeadings, WithTitle, WithStyles {
                public function headings(): array {
                    return [
                        'Nama Barang', 'Kode Kategori', 'Kode Satuan Dasar',
                        'Kode Tipe Barang (STK/AST/JSA)', 'Lacak Inventaris (Y/N)',
                        'Stok Minimum', 'Stok Maksimum', 'Spesifikasi Bawaan'
                    ];
                }
                public function array(): array {
                    return [
                        ['Laptop Dell XPS 13', 'IT-EQP', 'PCS', 'AST', 'Y', '0', '0', 'Core i7, 16GB RAM, SSD 512GB'],
                    ];
                }
                public function title(): string { return '1. Form Master Barang'; }
                public function styles(Worksheet $sheet) {
                    return [
                        1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FF000000']], 'fill' => ['fillType' => 'solid', 'color' => ['argb' => 'FFE0E0E0']]],
                    ];
                }
            },

            // SHEET 2: REFERENSI KATEGORI
            new class implements FromCollection, WithHeadings, WithTitle {
                public function collection() { return Category::select('code', 'name')->orderBy('name')->get(); }
                public function headings(): array { return ['KODE KATEGORI (COPAS KE FORM)', 'NAMA KATEGORI']; }
                public function title(): string { return '2. Referensi Kategori'; }
            },

            // SHEET 3: REFERENSI UOM
            new class implements FromCollection, WithHeadings, WithTitle {
                public function collection() { return Uom::select('code', 'name')->orderBy('code')->get(); }
                public function headings(): array { return ['KODE SATUAN (COPAS KE FORM)', 'NAMA SATUAN']; }
                public function title(): string { return '3. Referensi UOM'; }
            },

            // SHEET 4: REFERENSI TIPE BARANG
            new class implements FromCollection, WithHeadings, WithTitle {
                public function collection() {
                    return ItemType::select('code', 'name')->where('is_active', true)->orderBy('code')->get();
                }
                public function headings(): array { return ['KODE TIPE BARANG (COPAS KE FORM)', 'DESKRIPSI TIPE BARANG']; }
                public function title(): string { return '4. Referensi Tipe Barang'; }
            },

            // SHEET 5: PANDUAN PENGISIAN
            new class implements FromArray, WithHeadings, WithTitle {
                public function array(): array {
                    return [
                        ['Nama Barang', 'WAJIB', 'Cth: Kertas HVS A4 80gr / Printer Canon G2010'],
                        ['Kode Kategori', 'WAJIB', 'Ambil KODE-nya dari Sheet 2'],
                        ['Kode Satuan Dasar', 'WAJIB', 'Ambil KODE-nya dari Sheet 3'],
                        ['Kode Tipe Barang', 'WAJIB', 'Ambil KODE-nya dari Sheet 4 (STK / AST / JSA)'],
                        ['Lacak Inventaris', 'WAJIB', 'Ketik Y jika butuh pelacakan S/N. Ketik N jika tidak.'],
                        ['Stok Minimum', 'OPSIONAL', 'Angka batas bawah peringatan restock.'],
                        ['Stok Maksimum', 'OPSIONAL', 'Angka batas atas kuota gudang.'],
                        ['Spesifikasi Bawaan', 'OPSIONAL', 'Keterangan teknis bawaan.'],
                    ];
                }
                public function headings(): array { return ['NAMA KOLOM', 'SIFAT', 'PANDUAN DETAIL PENGISIAN']; }
                public function title(): string { return '5. Panduan Pengisian'; }
            }
        ];
    }
}
