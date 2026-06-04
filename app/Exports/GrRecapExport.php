<?php

namespace App\Exports;

use App\Models\GoodsReceipt;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class GrRecapExport implements FromView, ShouldAutoSize
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function view(): View
    {
        // Tarik data GR beserta relasi PO, Vendor, dan Rincian Barang (Items)
        // Kita gunakan created_at sebagai patokan Tanggal Terima
        $grs = GoodsReceipt::with(['po.vendor', 'items.item'])
            ->whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
            ->orderBy('created_at', 'asc')
            ->get();

        return view('reports.exports.gr_recap', [
            'grs' => $grs,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate
        ]);
    }
}
