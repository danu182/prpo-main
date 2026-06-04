<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventoryErrorExport implements FromArray, WithHeadings, WithStyles
{
    protected $errorData;

    public function __construct(array $errorData) {
        $this->errorData = $errorData;
    }

    public function array(): array {
        return $this->errorData;
    }

    public function headings(): array {
        return [
            'Kode Barang', 'Nama Barang', 'Nama Gudang', 'Jumlah (Qty)',
            'Harga Satuan', 'Mata Uang', 'Catatan', '🔥 KETERANGAN ERROR (WAJIB DIPERBAIKI) 🔥'
        ];
    }

    public function styles(Worksheet $sheet) {
        return [
            // Warnai Header menjadi Merah agar user sadar ini file error
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'DC3545']]],
        ];
    }
}
