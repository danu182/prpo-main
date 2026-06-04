<?php

namespace App\Exports;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class PoRecapExport implements FromView, ShouldAutoSize
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
        // Tarik data PO berdasarkan rentang tanggal
        $pos = PurchaseOrder::with(['vendor', 'status', 'company'])
            ->whereBetween('po_date', [$this->startDate, $this->endDate])
            ->orderBy('po_date', 'asc')
            ->get();

        return view('reports.exports.po_recap', [
            'pos' => $pos,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate
        ]);
    }
}
