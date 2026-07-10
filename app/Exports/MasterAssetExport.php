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

    public function __construct($filters)
    {
        $this->filters = $filters;
    }

    public function query()
    {
        // 🔥 TAMBAHAN: Kita load relasi 'goodsReceipt' agar data GR dan PO bisa ditarik
        $query = FixedAsset::query()->with([
            'item.category', 'assignee.department', 'company', 'department', 'status', 'warehouse', 'currency', 'goodsReceipt'
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

    public function headings(): array
    {
        return [
            'NO',
            'KODE ASET / INTERNAL',
            'KODE AKUNTANSI',
            'SERIAL NUMBER (S/N)',
            'KODE MASTER (SKU)',
            'NAMA MASTER BARANG',
            'NAMA ASET (SPESIFIK)',
            'KATEGORI / TYPE',
            'SPESIFIKASI',
            'PENGGUNA / GUDANG',
            'DEPARTEMEN',
            'ENTITAS / PT',
            'TANGGAL PEROLEHAN',
            'STATUS KONDISI',
            'MATA UANG',
            'HARGA BELI',
            'REF. PO (PURCHASE ORDER)',  // 🔥 KOLOM BARU 1
            'REF. GR (GOODS RECEIPT)',   // 🔥 KOLOM BARU 2
            'REF. IMPORT / HIBAH',       // 🔥 KOLOM BARU 3
            'CATATAN'
        ];
    }

    public function map($asset): array
    {
        $this->rowNumber++;

        // Logika Kategori
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
            : (optional($asset->assignee)->name ?? 'Unknown');

        $dept = empty($asset->assigned_to)
            ? '-'
            : (optional(optional($asset->assignee)->department)->name ?? optional($asset->department)->name ?? '-');

        // 🔥 LOGIKA PENARIKAN REFERENSI (PO, GR, HIBAH) 🔥

        // 1. Nomor GR (Mengambil dari tabel GoodsReceipt)
        $grNumber = optional($asset->goodsReceipt)->gr_number ?? optional($asset->goodsReceipt)->receipt_number ?? '-';

        // 2. Nomor PO (Mencoba menarik dari relasi GR -> PO, atau langsung dari GR jika ada)
        $poNumber = optional(optional($asset->goodsReceipt)->purchaseOrder)->po_number
                    ?? optional($asset->goodsReceipt)->po_number
                    ?? '-';

        // 3. Referensi Import / Hibah (Mengkombinasikan batch_id dan supporting_document)
        $importRefData = [];
        if (!empty($asset->batch_id)) {
            $importRefData[] = $asset->batch_id; // Biasanya berisi kode batch import
        }
        if (!empty($asset->supporting_document)) {
            $importRefData[] = $asset->supporting_document; // Dokumen pendukung BAST/Hibah
        }
        $refHibah = !empty($importRefData) ? implode(' | ', $importRefData) : '-';

        return [
            $this->rowNumber,
            $asset->asset_number ?? '-',
            $asset->accounting_asset_number ?? '-',
            $asset->serial_number ?? '-',
            optional($asset->item)->code ?? '-',
            optional($asset->item)->name ?? '-',
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
            $poNumber, // 🔥 OUTPUT KOLOM PO
            $grNumber, // 🔥 OUTPUT KOLOM GR
            $refHibah, // 🔥 OUTPUT KOLOM IMPORT/HIBAH
            $asset->notes ?? '-'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'color' => ['argb' => 'FFE0E0E0']]],
        ];
    }
}
