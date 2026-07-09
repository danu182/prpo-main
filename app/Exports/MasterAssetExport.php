<?php

namespace App\Exports;

use App\Models\FixedAsset;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MasterAssetExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    use Exportable;

    protected $filters;
    private $rowNumber = 0;

    // Menerima data filter dari Controller
    public function __construct($filters)
    {
        $this->filters = $filters;
    }

    // 1. Ambil Data (Sesuai dengan filter yang sedang aktif di halaman)
    public function query()
    {
        $query = FixedAsset::query()->with([
            'item.category', 'assignee.department', 'company', 'department', 'status', 'warehouse', 'currency'
        ]);

        if (!empty($this->filters['status'])) {
            if ($this->filters['status'] === 'in_use') {
                $query->whereNotNull('assigned_to');
            } elseif ($this->filters['status'] === 'in_warehouse') {
                $query->whereNull('assigned_to');
            }
        }

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('asset_number', 'like', "%{$search}%")
                  ->orWhere('accounting_asset_number', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('spesifikasi_detail', 'like', "%{$search}%")
                  ->orWhereHas('assignee', function($userQ) use ($search) {
                      $userQ->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('item', function($itemQ) use ($search) {
                      $itemQ->where('code', 'like', "%{$search}%");
                  });
            });
        }

        return $query->latest();
    }

    // 2. Judul Kolom Excel
    public function headings(): array
    {
        return [
            'NO',
            'KODE ASET / INTERNAL',
            'KODE AKUNTANSI',
            'SERIAL NUMBER (S/N)',
            'KODE MASTER (SKU)',
            'NAMA ASET',
            'KATEGORI / TYPE',
            'SPESIFIKASI',
            'PENGGUNA / GUDANG',
            'DEPARTEMEN',
            'ENTITAS / PT',
            'TANGGAL PEROLEHAN',
            'STATUS KONDISI',
            'MATA UANG',
            'HARGA BELI',
            'CATATAN'
        ];
    }

    // 3. Mapping Data ke Kolom
    public function map($asset): array
    {
        $this->rowNumber++;

        // Logika Kategori (Sama seperti di Blade)
        $subCategoryName = optional(optional($asset->item)->category)->name ?? '';
        if(empty($subCategoryName)) {
            $namaBarangLower = strtolower($asset->name);
            if(str_contains($namaBarangLower, 'laptop') || str_contains($namaBarangLower, 'macbook')) {
                $subCategoryName = 'Laptops';
            } elseif(str_contains($namaBarangLower, 'pc') || str_contains($namaBarangLower, 'desktop') || str_contains($namaBarangLower, 'imac')) {
                $subCategoryName = 'Elektronik & IT';
            } elseif(str_contains($namaBarangLower, 'iphone') || str_contains($namaBarangLower, 'phone') || str_contains($namaBarangLower, 'hp')) {
                $subCategoryName = 'Handphone';
            } else {
                $subCategoryName = 'Fixed Asset';
            }
        }

        // Logika Pengguna & Dept
        $pengguna = empty($asset->assigned_to)
            ? 'Gudang: ' . (optional($asset->warehouse)->name ?? 'Gudang Utama')
            :  (optional($asset->assignee)->name ?? 'Unknown');

        $dept = empty($asset->assigned_to)
            ? '-'
            : (optional(optional($asset->assignee)->department)->name ?? optional($asset->department)->name ?? '-');

        return [
            $this->rowNumber,
            $asset->asset_number ?? '-',
            $asset->accounting_asset_number ?? '-',
            $asset->serial_number ?? '-',
            optional($asset->item)->code ?? '-',
            $asset->name ?? optional($asset->item)->name ?? '-',
            $subCategoryName,
            $asset->spesifikasi_detail ?? '-',
            $pengguna,
            $dept,
            optional($asset->company)->name ?? '-',
            $asset->acquisition_date ? \Carbon\Carbon::parse($asset->acquisition_date)->format('Y-m-d') : '-',
            optional($asset->status)->name ?? 'Normal',
            optional($asset->currency)->code ?? 'IDR',
            $asset->purchase_price ?? 0,
            $asset->notes ?? '-'
        ];
    }

    // 4. Styling Header Excel (Warna latar, huruf tebal)
    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'color' => ['argb' => 'FFE0E0E0']]],
        ];
    }
}
