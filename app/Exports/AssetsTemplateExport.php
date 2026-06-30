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

class AssetsTemplateExport implements WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            // =======================================================
            // SHEET 1: FORM UTAMA
            // =======================================================
            new class implements FromArray, WithTitle {
                public function array(): array {
                    return [[
                        'Kode Barang', 'Nama Spesifik Aset', 'Serial Number', 'Label Akuntansi',
                        'Nama PT', 'Nama Gudang', 'Status', 'Nama Peminjam',
                        'Tanggal Perolehan', 'Mata Uang', 'Harga Beli Angka Murni', 'Spesifikasi', 'Catatan'
                    ]];
                }
                public function title(): string { return '1. Form Import Aset'; }
            },

            // =======================================================
            // SHEET 2: REFERENSI KARYAWAN (PEMINJAM)
            // =======================================================
            new class implements FromCollection, WithHeadings, WithTitle {
                public function collection() {
                    return User::select('name', 'email', 'job_title')->orderBy('name')->get();
                }
                public function headings(): array { return ['NAMA PEMINJAM (COPAS KE FORM)', 'EMAIL', 'JABATAN']; }
                public function title(): string { return '2. Referensi Karyawan'; }
            },

            // =======================================================
            // SHEET 3: REFERENSI BARANG (KATALOG)
            // =======================================================
            new class implements FromCollection, WithHeadings, WithTitle {
                public function collection() {
                    // Hanya tampilkan yang tipenya Aset/Fisik jika diperlukan, atau semua item
                    return Item::select('code', 'name')->orderBy('name')->get();
                }
                public function headings(): array { return ['KODE BARANG (COPAS KE FORM)', 'NAMA ASET TETAP']; }
                public function title(): string { return '3. Referensi Barang'; }
            },

            // =======================================================
            // SHEET 4: REFERENSI PERUSAHAAN (PT)
            // =======================================================
            new class implements FromCollection, WithHeadings, WithTitle {
                public function collection() {
                    return Company::select('name')->orderBy('name')->get();
                }
                public function headings(): array { return ['NAMA PT / ENTITAS (COPAS KE FORM)']; }
                public function title(): string { return '4. Referensi PT'; }
            },

            // =======================================================
            // SHEET 5: REFERENSI GUDANG
            // =======================================================
            new class implements FromCollection, WithHeadings, WithTitle {
                public function collection() {
                    return Warehouse::select('name')->orderBy('name')->get();
                }
                public function headings(): array { return ['NAMA GUDANG (COPAS KE FORM)']; }
                public function title(): string { return '5. Referensi Gudang'; }
            },

            // =======================================================
            // SHEET 6: REFERENSI STATUS ASET
            // =======================================================
            new class implements FromCollection, WithHeadings, WithTitle {
                public function collection() {
                    return Status::where('type', 'AST')->orderBy('sequence')->select('name')->get();
                }
                public function headings(): array { return ['STATUS ASET (COPAS KE FORM)']; }
                public function title(): string { return '6. Referensi Status'; }
            },

            // =======================================================
            // SHEET 7: REFERENSI MATA UANG
            // =======================================================
            new class implements FromCollection, WithHeadings, WithTitle {
                public function collection() {
                    return Currency::where('is_active', 1)->select('code', 'name', 'symbol')->get();
                }
                public function headings(): array { return ['KODE (COPAS KE FORM)', 'NAMA MATA UANG', 'SIMBOL']; }
                public function title(): string { return '7. Referensi Mata Uang'; }
            },

            // =======================================================
            // 🔥 SHEET 8: PANDUAN PENGISIAN (DISESUAIKAN UNTUK SMART AUTO-CREATE) 🔥
            // =======================================================
            new class implements FromArray, WithHeadings, WithTitle {
                public function array(): array {
                    return [
                        // 🔥 PERBAIKAN: Kode Barang jadi OPSIONAL, Nama Spesifik jadi WAJIB jika kode kosong
                        ['Kode Barang', 'OPSIONAL', 'Ambil dari Sheet 3. KOSONGKAN jika barang belum ada di Katalog, sistem akan membuatkannya otomatis.'],
                        ['Nama Spesifik Aset', 'WAJIB (Jika Kode Kosong)', 'Cth: Laptop Core i7. WAJIB DIISI jika Kode Barang di atas dikosongkan.'],
                        ['Serial Number', 'OPSIONAL', 'Nomor Seri / S/N fisik.'],
                        ['Label Akuntansi', 'OPSIONAL', 'Nomor aset dari Keuangan (Cth: FA-001).'],
                        ['Nama PT', 'WAJIB', 'Ambil dari Sheet 4 (Referensi PT).'],
                        ['Nama Gudang', 'WAJIB', 'Ambil dari Sheet 5 (Referensi Gudang).'],
                        ['Status', 'WAJIB', 'Ambil dari Sheet 6. WAJIB COPAS!'],
                        ['Nama Peminjam', 'OPSIONAL', 'Wajib diisi jika status "In Use". Ambil dari Sheet 2.'],
                        ['Tanggal Perolehan', 'OPSIONAL', 'Format: YYYY-MM-DD atau format Date Excel.'],
                        ['Mata Uang', 'OPSIONAL', 'Ketik IDR, USD, dll. Kosong = IDR. Ambil dari Sheet 7.'],
                        ['Harga Beli Angka Murni', 'OPSIONAL', 'Hanya angka! (Cth: 15000000)'],
                        ['Spesifikasi', 'OPSIONAL', 'Detail spek khusus unit ini.'],
                        ['Catatan', 'OPSIONAL', 'Keterangan tambahan jika ada.'],
                    ];
                }
                public function headings(): array { return ['NAMA KOLOM', 'SIFAT', 'PANDUAN PENGISIAN']; }
                public function title(): string { return '8. Panduan Pengisian'; }
            }
        ];
    }
}
