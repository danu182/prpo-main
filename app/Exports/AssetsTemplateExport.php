<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\User;
use App\Models\Item;
use App\Models\Company;
use App\Models\Status;
use App\Models\Warehouse;
use App\Models\Currency;
use App\Models\AssetCategory;

class AssetsTemplateExport implements WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            // =======================================================
            // SHEET 1: FORM UTAMA (MENGGUNAKAN ID / KODE UNIK)
            // =======================================================
            new class implements FromArray, WithTitle {
                public function array(): array {
                    return [[
                        'Kode Barang', 'Nama Spesifik Aset', 'ID Kategori Aset',
                        'Serial Number', 'Label Akuntansi', 'ID PT', 'ID Gudang', 'ID Status', 'ID Peminjam',
                        'Tanggal Perolehan', 'Kode Mata Uang', 'Harga Beli Angka Murni', 'Spesifikasi', 'Catatan'
                    ]];
                }
                public function title(): string { return '1. Form Import Aset'; }
            },

            // =======================================================
            // SHEET 2: REFERENSI KARYAWAN (PEMINJAM)
            // =======================================================
            new class implements FromCollection, WithHeadings, WithTitle {
                public function collection() {
                    // 🔥 Tambahkan ID di paling depan
                    return User::select('id', 'name', 'email', 'job_title')->orderBy('name')->get();
                }
                public function headings(): array { return ['ID PEMINJAM (COPAS KE FORM)', 'NAMA PEMINJAM', 'EMAIL', 'JABATAN']; }
                public function title(): string { return '2. Referensi Karyawan'; }
            },

            // =======================================================
            // SHEET 3: REFERENSI BARANG (KATALOG) -> Sudah pakai Code
            // =======================================================
            new class implements FromCollection, WithHeadings, WithTitle {
                public function collection() {
                    return Item::select('code', 'name')
                               // ->where('item_type_code', 'AST') // 🔥 Filter ini dihapus agar semua item muncul
                               ->where('is_active', 1)
                               ->orderBy('name')
                               ->get();
                }
                public function headings(): array { return ['KODE BARANG (COPAS KE FORM)', 'NAMA ASET TETAP']; }
                public function title(): string { return '3. Referensi Barang'; }
            },

            // =======================================================
            // SHEET 4: REFERENSI PERUSAHAAN (PT)
            // =======================================================
            new class implements FromCollection, WithHeadings, WithTitle {
                public function collection() {
                    // 🔥 Tambahkan ID di paling depan
                    return Company::select('id', 'name')->orderBy('name')->get();
                }
                public function headings(): array { return ['ID PT (COPAS KE FORM)', 'NAMA PT / ENTITAS']; }
                public function title(): string { return '4. Referensi PT'; }
            },

            // =======================================================
            // SHEET 5: REFERENSI GUDANG
            // =======================================================
            new class implements FromCollection, WithHeadings, WithTitle {
                public function collection() {
                    // 🔥 Tambahkan ID di paling depan
                    return Warehouse::select('id', 'name')->orderBy('name')->get();
                }
                public function headings(): array { return ['ID GUDANG (COPAS KE FORM)', 'NAMA GUDANG']; }
                public function title(): string { return '5. Referensi Gudang'; }
            },

            // =======================================================
            // SHEET 6: REFERENSI STATUS ASET
            // =======================================================
            new class implements FromCollection, WithHeadings, WithTitle {
                public function collection() {
                    // 🔥 Tambahkan ID di paling depan
                    return Status::where('type', 'AST')->orderBy('sequence')->select('id', 'name', 'slug')->get();
                }
                public function headings(): array { return ['ID STATUS (COPAS KE FORM)', 'NAMA STATUS', 'KODE SISTEM (SLUG)']; }
                public function title(): string { return '6. Referensi Status'; }
            },

            // =======================================================
            // SHEET 7: REFERENSI MATA UANG -> Sudah pakai Code
            // =======================================================
            new class implements FromCollection, WithHeadings, WithTitle {
                public function collection() {
                    return Currency::where('is_active', 1)->select('code', 'name', 'symbol')->get();
                }
                public function headings(): array { return ['KODE MATA UANG (COPAS KE FORM)', 'NAMA MATA UANG', 'SIMBOL']; }
                public function title(): string { return '7. Referensi Mata Uang'; }
            },

            // =======================================================
            // SHEET 8: REFERENSI KATEGORI ASET
            // =======================================================
            new class implements FromCollection, WithHeadings, WithTitle {
                public function collection() {
                    // 🔥 Tambahkan ID di paling depan
                    return AssetCategory::where('is_active', 1)
                                        ->select('id', 'name', 'useful_life_years')
                                        ->orderBy('useful_life_years')
                                        ->get();
                }
                public function headings(): array { return ['ID KATEGORI (COPAS KE FORM)', 'NAMA KATEGORI ASET', 'UMUR EKONOMIS (TAHUN)']; }
                public function title(): string { return '8. Referensi Kategori Aset'; }
            },

            // =======================================================
            // SHEET 9: PANDUAN PENGISIAN
            // =======================================================
            new class implements FromArray, WithHeadings, WithTitle {
                public function array(): array {
                    return [
                        ['Kode Barang', 'OPSIONAL', 'Ambil dari Sheet 3. KOSONGKAN jika barang belum ada di Katalog, sistem akan membuatkannya otomatis.'],
                        ['Nama Spesifik Aset', 'WAJIB (Jika Kode Kosong)', 'Cth: Laptop Core i7. WAJIB DIISI jika Kode Barang di atas dikosongkan.'],
                        ['ID Kategori Aset', 'OPSIONAL', 'Ambil ANGKA ID dari Sheet 8. Jika kosong, otomatis masuk ke Kelompok 1 (4 Tahun).'],
                        ['Serial Number', 'OPSIONAL', 'Nomor Seri / S/N fisik dari barang.'],
                        ['Label Akuntansi', 'OPSIONAL', 'Nomor aset dari Keuangan (Cth: FA-001).'],
                        ['ID PT', 'WAJIB', 'Ambil ANGKA ID dari Sheet 4 (Referensi PT).'],
                        ['ID Gudang', 'WAJIB', 'Ambil ANGKA ID dari Sheet 5 (Referensi Gudang).'],
                        ['ID Status', 'WAJIB', 'Ambil ANGKA ID dari Sheet 6 (Referensi Status Aset).'],
                        ['ID Peminjam', 'OPSIONAL', 'Wajib diisi jika status "In Use". Ambil ANGKA ID dari Sheet 2.'],
                        ['Tanggal Perolehan', 'OPSIONAL', 'Format: YYYY-MM-DD atau format Date bawaan Excel.'],
                        ['Kode Mata Uang', 'OPSIONAL', 'Ketik IDR, USD, dll. Kosong = IDR. Ambil KODE dari Sheet 7.'],
                        ['Harga Beli Angka Murni', 'OPSIONAL', 'Hanya angka murni, tanpa titik/koma! (Cth: 15000000)'],
                        ['Spesifikasi', 'OPSIONAL', 'Detail spek khusus unit ini.'],
                        ['Catatan', 'OPSIONAL', 'Keterangan tambahan jika ada.'],
                    ];
                }
                public function headings(): array { return ['NAMA KOLOM', 'SIFAT', 'PANDUAN PENGISIAN (PASTIKAN COPAS ID BUKAN NAMA)']; }
                public function title(): string { return '9. Panduan Pengisian'; }
            }
        ];
    }
}
