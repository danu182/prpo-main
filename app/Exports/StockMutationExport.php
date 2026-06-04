<?php

namespace App\Exports;

use App\Models\StockMutation; // Sesuaikan jika nama model Anda berbeda
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class StockMutationExport implements FromView, ShouldAutoSize
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
        // Tarik data mutasi beserta relasi Barang dan User pembuatnya
        $mutations = StockMutation::with(['item', 'creator'])
            ->whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
            ->orderBy('created_at', 'asc')
            ->get();

        return view('reports.exports.stock_mutation', [
            'mutations' => $mutations,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate
        ]);
    }
}
