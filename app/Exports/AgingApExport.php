<?php

namespace App\Exports;

use App\Models\VendorInvoice;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Carbon\Carbon;

class AgingApExport implements FromView, ShouldAutoSize
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
        // Tarik tagihan beserta pembayarannya
        $invoices = VendorInvoice::with(['vendor', 'payments'])
            ->whereBetween('invoice_date', [$this->startDate, $this->endDate])
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function ($invoice) {
                // Hitung sisa hutang
                $paid = $invoice->payments->sum('amount');
                $balance = $invoice->grand_total - $paid;

                // Hitung keterlambatan (Overdue)
                $dueDate = Carbon::parse($invoice->due_date);
                $today = Carbon::now();

                $daysOverdue = 0;
                if ($today->gt($dueDate)) {
                    $daysOverdue = $today->diffInDays($dueDate);
                }

                $invoice->unpaid_balance = $balance;
                $invoice->days_overdue = $daysOverdue;
                $invoice->is_overdue = $today->gt($dueDate);

                return $invoice;
            })
            ->filter(function ($invoice) {
                return $invoice->unpaid_balance > 0; // Hanya tampilkan yang masih punya hutang
            });

        return view('reports.exports.aging_ap', [
            'invoices' => $invoices,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate
        ]);
    }
}
