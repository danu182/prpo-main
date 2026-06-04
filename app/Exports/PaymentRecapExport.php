<?php

namespace App\Exports;

use App\Models\VendorPayment; // Asumsi menggunakan model pembayaran AP Anda
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class PaymentRecapExport implements FromView, ShouldAutoSize
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
        // Tarik data pembayaran beserta relasi ke Invoice dan Vendor
        $payments = VendorPayment::with(['invoice.vendor'])
            ->whereBetween('payment_date', [$this->startDate, $this->endDate])
            ->orderBy('payment_date', 'asc')
            ->get();

        return view('reports.exports.payment_recap', [
            'payments' => $payments,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate
        ]);
    }
}
