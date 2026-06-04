<?php

namespace App\Exports;

use App\Models\ReturnToVendor;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class RtvRecapExport implements FromView, ShouldAutoSize
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
        // Tarik data RTV beserta relasi Vendor, GR, dan Rincian Barang yang diretur
        $rtvs = ReturnToVendor::with(['vendor', 'goodsReceipt', 'items.item'])
            ->whereBetween('return_date', [$this->startDate, $this->endDate])
            ->orderBy('return_date', 'asc')
            ->get();

        return view('reports.exports.rtv_recap', [
            'rtvs' => $rtvs,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate
        ]);
    }
}
