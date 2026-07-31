<?php

namespace App\Exports;

use App\Models\FixedAsset;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class MasterAssetExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    use Exportable;

    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        // 1. Kumpulkan ID Status Void agar tidak ikut ke-Export
        $voidStatusIds = \App\Models\Status::where('type', 'AST')
            ->where(function($q) {
                $q->where('slug', 'like', '%void%')
                  ->orWhere('slug', 'like', '%batal%')
                  ->orWhere('name', 'like', '%Void%')
                  ->orWhere('name', 'like', '%Batal%');
            })->pluck('id')->toArray();

        $query = FixedAsset::query()->with([
            'item', 'assetCategory', 'company', 'warehouse', 'status', 'assignee', 'currency'
        ]);

        // =========================================================================
        // 🔥 FILTER ANTI-VOID: Buang aset yang batal/void dari laporan Excel 🔥
        // =========================================================================
        if (!empty($voidStatusIds)) {
            $query->whereNotIn('status_id', $voidStatusIds);
        }
        $query->where(function($q) {
            $q->whereNull('notes')
              ->orWhere('notes', 'not like', '%[DIBATALKAN%');
        });
        // =========================================================================

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('asset_number', 'like', "%{$search}%")
                  ->orWhere('accounting_asset_number', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if (!empty($this->filters['warehouse_id'])) {
            $query->where('warehouse_id', $this->filters['warehouse_id']);
        }

        if (!empty($this->filters['status'])) {
            if ($this->filters['status'] === 'in_use') {
                $query->whereNotNull('assigned_to');
            } elseif ($this->filters['status'] === 'in_warehouse') {
                $query->whereNull('assigned_to');
            }
        }

        return $query->latest();
    }

    // =======================================================
    // 1. MAPPING DATA
    // =======================================================
    public function map($asset): array
    {
        $umurTahun = optional($asset->assetCategory)->useful_life_years ?? 4;

        // 🔥 PEMBERSIH TEKS EKSTREM: Menghapus Enter, Tab, dan Tag HTML agar baris Excel tidak hancur
        $rawSpesifikasi = $asset->spesifikasi_detail ? strip_tags($asset->spesifikasi_detail) : '-';
        $spesifikasi = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n", "\t"], ' ', $rawSpesifikasi)));

        $rawNama = $asset->name ?? optional($asset->item)->name ?? '-';
        $namaAset = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n", "\t"], ' ', $rawNama)));

        // 🔥 FORMAT UANG LANGSUNG DI PHP: Mencegah Excel menyembunyikan angka 0
        $mataUang = optional($asset->currency)->code ?? 'IDR';
        $hargaBeli = number_format((float)($asset->purchase_price ?? 0), 0, ',', '.');
        $bebanBulan = number_format((float)($asset->monthly_depreciation ?? 0), 0, ',', '.');

        // PENTING: Aset yang di-Disposed nilainya tetap Rp 0 atau mengikuti nilai sisa terakhirnya.
        $nilaiBuku = number_format((float)($asset->net_book_value ?? 0), 0, ',', '.');

        return [
            $asset->asset_number ?? '-',
            $asset->accounting_asset_number ?: '-',
            $namaAset,
            $asset->serial_number ?: '-',

            optional($asset->company)->name ?? '-',
            optional($asset->warehouse)->name ?? '-',
            optional($asset->assignee)->name ?? 'Di Gudang',
            optional($asset->status)->name ?? '-', // Akan otomatis mencetak "Disposed" jika rusak/dijual

            optional($asset->assetCategory)->name ?? 'Kelompok 1 (Default)',
            $umurTahun . ' Tahun',

            $asset->acquisition_date ? Carbon::parse($asset->acquisition_date)->format('d M Y') : '-',
            now()->format('F Y'),

            $mataUang,
            $mataUang . ' ' . $hargaBeli,   // Output: IDR 10.000.000
            $mataUang . ' ' . $bebanBulan,  // Output: IDR 208.333
            $mataUang . ' ' . $nilaiBuku,   // Output: IDR 7.083.338

            $spesifikasi,
        ];
    }

    // =======================================================
    // 2. HEADER TABEL
    // =======================================================
    public function headings(): array
    {
        return [
            'Nomor Aset Sistem',
            'Label Akuntansi',
            'Nama Spesifik Aset',
            'S/N Fisik',
            'Milik Entitas (PT)',
            'Lokasi Gudang',
            'Penanggung Jawab',
            'Status Saat Ini',
            'Kategori Penyusutan',
            'Umur Ekonomis',
            'Tanggal Perolehan',
            'Bulan & Tahun Laporan',
            'Mata Uang',
            'Nilai Perolehan (Harga Beli)',
            'Beban Penyusutan / Bulan',
            'Nilai Buku Saat Ini per ' . date('d M Y'),
            'Spesifikasi',
        ];
    }

    // =======================================================
    // 3. STYLING HEADER SAJA (Disederhanakan agar tidak bentrok)
    // =======================================================
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0284C7']
                ],
            ]
        ];
    }
}
