<?php

namespace App\Exports;

use App\Models\FixedAsset;
use App\Models\FixedAssetTransaction;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Carbon\Carbon;

class MasterAssetExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithColumnFormatting, WithCustomStartCell, WithEvents
{
    use Exportable;

    protected $filters;
    protected $rowNumber = 0;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Header tabel dimulai pada baris ke-5 agar baris 1-3 dipakai untuk Kop/Tanggal Export
     */
    public function startCell(): string
    {
        return 'A5';
    }

    public function query()
    {
        $voidStatusIds = \App\Models\Status::where('type', 'AST')
            ->whereIn('slug', ['void', 'batal', 'canceled', 'cancelled'])
            ->pluck('id')->toArray();

        $query = FixedAsset::query()->with([
            'item.category', 'assetCategory', 'company', 'warehouse',
            'status', 'assignee.department', 'currency', 'histories'
        ]);

        if (!empty($voidStatusIds)) {
            $query->whereNotIn('status_id', $voidStatusIds);
        }
        $query->where(function ($q) {
            $q->whereNull('notes')
              ->orWhere('notes', 'not like', '%[DIBATALKAN%');
        });

        if (!empty($this->filters['company_id'])) {
            $query->where('company_id', $this->filters['company_id']);
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

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('asset_number', 'like', "%{$search}%")
                  ->orWhere('accounting_asset_number', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('spesifikasi_detail', 'like', "%{$search}%")
                  ->orWhereHas('assignee', function ($userQ) use ($search) {
                      $userQ->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('item', function ($itemQ) use ($search) {
                      $itemQ->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('company', function ($compQ) use ($search) {
                      $compQ->where('name', 'like', "%{$search}%");
                  });
            });
        }

        return $query->latest();
    }

    public function map($asset): array
    {
        $this->rowNumber++;
        $umurTahun = optional($asset->assetCategory)->useful_life_years ?? 4;

        $cleanText = function ($text) {
            if (empty($text)) return '-';
            return trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n", "\t"], ' ', strip_tags((string) $text))));
        };

        $namaAset    = $cleanText($asset->name ?? optional($asset->item)->name);
        $spesifikasi = $cleanText($asset->spesifikasi_detail);
        $catatan     = $cleanText($asset->notes);

        // Deteksi Mata Uang
        $currObj  = is_object($asset->currency) ? $asset->currency : null;
        $mataUang = strtoupper($currObj->code ?? (is_string($asset->currency) ? $asset->currency : 'IDR'));

        $hargaBeli  = (float) ($asset->purchase_price ?? 0);
        $bebanBulan = (float) ($asset->monthly_depreciation ?? 0);
        $nilaiBuku  = (float) ($asset->net_book_value ?? $asset->purchase_price ?? 0);

        // Pemisahan Status, Pemakai, Tanggal Pemakaian & Lama Pemakaian
        $isAssigned       = !empty($asset->assigned_to);
        $statusKeberadaan = $isAssigned ? 'Digunakan Staff' : 'Tersedia di Gudang';
        $statusKondisi    = optional($asset->status)->name ?? '-';
        $namaPemakai      = $isAssigned ? (optional($asset->assignee)->name ?? '-') : '-';
        $jabatanPemakai   = $isAssigned ? (optional($asset->assignee)->job_title ?? '-') : '-';
        $departemen       = $isAssigned ? (optional(optional($asset->assignee)->department)->name ?? '-') : '-';
        $namaGudang       = optional($asset->warehouse)->name ?? '-';

        $tglMulaiPakaiText = '-';
        $lamaPemakaianText = '-';

        if ($isAssigned) {
            $lastHandover = FixedAssetTransaction::where('transaction_type', 'HANDOVER')
                ->where('to_user_id', $asset->assigned_to)
                ->where(function ($q) use ($asset) {
                    $q->where('fixed_asset_id', $asset->id)
                      ->orWhereHas('details', fn($qd) => $qd->where('fixed_asset_id', $asset->id));
                })
                ->orderBy('transaction_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $startDateRaw = $lastHandover?->transaction_date
                ?? $asset->assigned_at
                ?? optional($asset->histories->where('status', 'HANDOVER')->sortByDesc('created_at')->first())->created_at
                ?? $asset->updated_at;

            if ($startDateRaw) {
                $startDate = Carbon::parse($startDateRaw)->startOfDay();
                $nowDate   = Carbon::now()->startOfDay();

                $tglMulaiPakaiText = $startDate->format('d M Y');

                if ($startDate->lte($nowDate)) {
                    $diff = $startDate->diff($nowDate);
                    $parts = [];
                    if ($diff->y > 0) $parts[] = $diff->y . ' Tahun';
                    if ($diff->m > 0) $parts[] = $diff->m . ' Bulan';
                    if ($diff->y === 0 && $diff->d > 0) $parts[] = $diff->d . ' Hari';
                    $lamaPemakaianText = !empty($parts) ? implode(' ', $parts) : 'Baru Hari Ini (0 Hari)';
                } else {
                    $lamaPemakaianText = '0 Hari';
                }
            }
        }

        return [
            $this->rowNumber,                                           // A: No
            $asset->asset_number ?? '-',                                // B: Nomor Aset Otomatis
            $asset->accounting_asset_number ?: '-',                     // C: Nomor Aset Akuntansi
            $asset->serial_number ?: '-',                               // D: Serial Number (S/N)
            optional($asset->item)->code ?? '-',                        // E: Kode Master Item
            optional($asset->item)->name ?? '-',                        // F: Nama Master Item
            optional(optional($asset->item)->category)->name ?? '-',    // G: Kategori Barang
            $namaAset,                                                  // H: Nama Spesifik Aset
            $spesifikasi,                                               // I: Spesifikasi Detail
            optional($asset->company)->name ?? '-',                     // J: Perusahaan (Entitas PT)
            $statusKeberadaan,                                          // K: Status Keberadaan
            $statusKondisi,                                             // L: Status Kondisi Aset
            $namaPemakai,                                               // M: Nama Pemakai Aset
            $jabatanPemakai,                                            // N: Jabatan Pemakai
            $departemen,                                                // O: Departemen Pemakai
            $tglMulaiPakaiText,                                         // P: Tanggal Mulai Pemakaian
            $lamaPemakaianText,                                         // Q: Lama Pemakaian User
            $namaGudang,                                                // R: Lokasi Gudang
            optional($asset->assetCategory)->name ?? 'Kelompok 1 (Default)', // S: Kategori Penyusutan
            $umurTahun . ' Tahun',                                      // T: Umur Ekonomis
            $asset->acquisition_date ? Carbon::parse($asset->acquisition_date)->format('d M Y') : '-', // U: Tanggal Perolehan
            now()->format('F Y'),                                       // V: Periode Laporan
            $mataUang,                                                  // W: Mata Uang
            $hargaBeli,                                                 // X: Nilai Perolehan Awal
            $bebanBulan,                                                // Y: Beban Penyusutan / Bulan
            $nilaiBuku,                                                 // Z: Nilai Buku Saat Ini
            $catatan,                                                   // AA: Catatan
        ];
    }

    public function headings(): array
    {
        return [
            'No',
            'Nomor Aset Otomatis',
            'Nomor Aset Akuntansi',
            'Serial Number (S/N)',
            'Kode Master Item',
            'Nama Master Item',
            'Kategori Barang',
            'Nama Spesifik Aset',
            'Spesifikasi Detail',
            'Perusahaan (Entitas PT)',
            'Status Keberadaan',
            'Status Kondisi Aset',
            'Nama Pemakai Aset',
            'Jabatan Pemakai',
            'Departemen Pemakai',
            'Tanggal Mulai Pemakaian',
            'Lama Pemakaian User',
            'Lokasi Gudang',
            'Kategori Penyusutan',
            'Umur Ekonomis',
            'Tanggal Perolehan',
            'Periode Laporan',
            'Mata Uang',
            'Nilai Perolehan Awal',
            'Beban Penyusutan / Bulan',
            'Nilai Buku Saat Ini per ' . Carbon::now()->translatedFormat('d F Y'),
            'Catatan',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'X' => '#,##0.00',
            'Y' => '#,##0.00',
            'Z' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0284C7']
                ],
            ]
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $companyLabel = 'Semua Perusahaan';
                if (!empty($this->filters['company_id'])) {
                    $comp = \App\Models\Company::find($this->filters['company_id']);
                    if ($comp) $companyLabel = $comp->name;
                }

                $statusLabel = 'Semua Status';
                if (!empty($this->filters['status'])) {
                    $statusLabel = $this->filters['status'] === 'in_use' ? 'Digunakan Staff' : 'Tersedia di Gudang';
                }

                $exportTime = Carbon::now()->translatedFormat('d F Y, H:i:s') . ' WIB';

                // Baris 1: Judul Utama Laporan
                $sheet->setCellValue('A1', 'LAPORAN INDUK & PENYUSUTAN FIXED ASSET');
                $sheet->mergeCells('A1:H1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
                ]);

                // Baris 2: Tanggal & Waktu Export (Acuan Nilai Buku & Lama Pemakaian)
                $sheet->setCellValue('A2', 'Tanggal Export : ' . $exportTime . ' (Acuan Perhitungan Nilai Buku & Lama Pemakaian)');
                $sheet->mergeCells('A2:H2');
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '0284C7']],
                ]);

                // Baris 3: Informasi Filter yang Diterapkan
                $sheet->setCellValue('A3', 'Entitas / PT : ' . $companyLabel . '   |   Status : ' . $statusLabel);
                $sheet->mergeCells('A3:H3');
                $sheet->getStyle('A3')->applyFromArray([
                    'font' => ['italic' => true, 'size' => 9.5, 'color' => ['rgb' => '475569']],
                ]);

                // Border tipis untuk seluruh area tabel data (Baris 5 s/d baris terakhir)
                $highestRow = $sheet->getHighestRow();
                if ($highestRow >= 5) {
                    $sheet->getStyle('A5:AA' . $highestRow)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'CBD5E1'],
                            ],
                        ],
                    ]);
                }

                // Freeze pane tepat di bawah header tabel (baris ke-6) agar header tetap terlihat saat scroll
                $sheet->freezePane('A6');
            },
        ];
    }
}