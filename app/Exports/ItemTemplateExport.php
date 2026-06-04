<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\Category;
use App\Models\Uom;

class ItemTemplateExport implements WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            // SHEET 1: FORM INPUT
            new class implements FromArray, WithTitle {
                public function array(): array {
                    return [[
                        // 🔥 'Kode Barang' DIHAPUS karena sudah Auto-Generate 🔥
                        'Nama Barang', 'Kode Kategori', 'Kode Satuan Dasar',
                        'Barang Stok', 'Aset Tetap', 'Lacak Inventaris',
                        'Stok Minimum', 'Stok Maksimum', 'Spesifikasi Bawaan'
                    ]];
                }
                public function title(): string { return '1. Form Master Barang'; }
            },

            // SHEET 2: REFERENSI KATEGORI (Dinamic dari Database)
            new class implements FromCollection, WithHeadings, WithTitle {
                public function collection() {
                    // Menampilkan Code dan Name, agar user gampang copy Code-nya
                    return Category::select('code', 'name')->orderBy('name')->get();
                }
                public function headings(): array { return ['KODE KATEGORI (COPAS KE FORM)', 'NAMA KATEGORI']; }
                public function title(): string { return '2. Referensi Kategori'; }
            },

            // SHEET 3: REFERENSI UOM (Dinamic dari Database)
            new class implements FromCollection, WithHeadings, WithTitle {
                public function collection() {
                    return Uom::select('code', 'name')->orderBy('code')->get();
                }
                public function headings(): array { return ['KODE SATUAN (COPAS KE FORM)', 'NAMA SATUAN']; }
                public function title(): string { return '3. Referensi UOM'; }
            },

            // SHEET 4: PANDUAN PENGISIAN
            new class implements FromArray, WithHeadings, WithTitle {
                public function array(): array {
                    return [
                        ['Nama Barang', 'WAJIB', 'Cth: Kertas HVS A4 80gr'],
                        ['Kode Kategori', 'WAJIB', 'Ambil KODE-nya dari Sheet Referensi Kategori (Cth: ATK)'],
                        ['Kode Satuan Dasar', 'WAJIB', 'Ambil KODE-nya dari Sheet Referensi UOM (Cth: RIM)'],
                        ['Barang Stok', 'WAJIB', 'Ketik YA jika barang fisik/disimpan. Ketik TIDAK untuk Jasa.'],
                        ['Aset Tetap', 'WAJIB', 'Ketik YA jika ini adalah aset tetap perusahaan.'],
                        ['Lacak Inventaris', 'WAJIB', 'Ketik YA jika barang pemegangnya perlu dilacak (S/N).'],
                        ['Stok Minimum', 'OPSIONAL', 'Hanya angka batas bawah peringatan (Cth: 5)'],
                        ['Stok Maksimum', 'OPSIONAL', 'Hanya angka batas atas overstock (Cth: 100)'],
                        ['Spesifikasi Bawaan', 'OPSIONAL', 'Keterangan teknis bawaan pabrik.'],
                    ];
                }
                public function headings(): array { return ['KOLOM', 'SIFAT', 'KETERANGAN']; }
                public function title(): string { return '4. Panduan'; }
            }
        ];
    }
}
