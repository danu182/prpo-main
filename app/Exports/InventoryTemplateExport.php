<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\Item;
use App\Models\Warehouse;
use App\Models\Currency;

class InventoryTemplateExport implements WithMultipleSheets
{
    public function sheets(): array {
        return [
            // ==========================================
            // SHEET 1: FORM INPUT UTAMA
            // ==========================================
            new class implements FromArray, WithTitle {
                public function array(): array {
                    return [[
                        'Kode Barang', 'Nama Barang', 'Nama Gudang', 'Jumlah (Qty)', 'Harga Satuan', 'Mata Uang', 'Catatan'
                    ]];
                }
                public function title(): string { return '1. Input Saldo Awal'; }
            },

            // ==========================================
            // SHEET 2: REFERENSI KATALOG BARANG (KHUSUS STOK)
            // ==========================================
            new class implements FromArray, WithHeadings, WithTitle {
                public function array(): array {
                    // 🔥 PERBAIKAN: Filter super ketat!
                    // Hanya tampilkan barang yang DILACAK DI GUDANG (is_stockable = 1)
                    // DAN BUKAN ASET TETAP (is_asset = 0)
                    return Item::select('code', 'name')
                               ->where('is_stockable', 1)
                               ->where('is_asset', 0)
                               ->orderBy('name')
                               ->get()
                               ->toArray();
                }
                public function headings(): array { return ['KODE BARANG (COPAS KE FORM)', 'NAMA BARANG']; }
                public function title(): string { return '2. Referensi Barang'; }
            },

            // ==========================================
            // SHEET 3: REFERENSI GUDANG
            // ==========================================
            new class implements FromArray, WithHeadings, WithTitle {
                public function array(): array {
                    return Warehouse::select('name')->orderBy('name')->get()->toArray();
                }
                public function headings(): array { return ['NAMA GUDANG (COPAS KE FORM)']; }
                public function title(): string { return '3. Referensi Gudang'; }
            },

            // ==========================================
            // 🔥 SHEET 4: REFERENSI MATA UANG 🔥
            // ==========================================
            new class implements FromArray, WithHeadings, WithTitle {
                public function array(): array {
                    // Tarik data dari database
                    $currencies = Currency::where('is_active', 1)->select('code', 'name', 'symbol')->orderBy('code')->get()->toArray();

                    // PANCINGAN: Jika database kosong, beri nilai default agar Sheet tetap muncul
                    if (empty($currencies)) {
                        return [
                            ['IDR', 'Indonesian Rupiah', 'Rp'],
                            ['USD', 'US Dollar', '$'],
                        ];
                    }

                    return $currencies;
                }
                public function headings(): array { return ['KODE MATA UANG (COPAS)', 'NAMA MATA UANG', 'SIMBOL']; }
                public function title(): string { return '4. Referensi Mata Uang'; }
            },

            // ==========================================
            // SHEET 5: PANDUAN PENGISIAN
            // ==========================================
            new class implements FromArray, WithHeadings, WithTitle {
                public function array(): array {
                    return [
                        ['Kode Barang', 'WAJIB', 'Copy-Paste dari Sheet "2. Referensi Barang". Sistem melacak stok berdasarkan kode ini.'],
                        ['Nama Barang', 'OPSIONAL', 'Hanya referensi agar mudah dibaca manusia. (Boleh dikosongkan)'],
                        ['Nama Gudang', 'WAJIB', 'Copy-Paste dari Sheet "3. Referensi Gudang". Harus sama persis penulisan hurufnya.'],
                        ['Jumlah (Qty)', 'WAJIB', 'Hanya masukkan angka riil. Boleh desimal (contoh: 10 atau 15.5).'],
                        ['Harga Satuan', 'WAJIB', 'Harga perolehan satuan (HPP). Hanya ketik angka tanpa titik/koma (contoh: 150000). Ketik 0 jika gratis.'],
                        ['Mata Uang', 'WAJIB', 'Copy-Paste KODE dari Sheet "4. Referensi Mata Uang" (Contoh: IDR, USD).'],
                        ['Catatan', 'OPSIONAL', 'Keterangan referensi (contoh: Saldo awal hasil Stock Opname Gudang April 2026).']
                    ];
                }
                public function headings(): array { return ['NAMA KOLOM DI SHEET 1', 'SIFAT', 'ATURAN & PANDUAN PENGISIAN']; }
                public function title(): string { return '5. Panduan'; }
            }
        ];
    }
}
