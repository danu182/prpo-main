<?php

namespace App\Exports;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class VendorSpendExport implements FromView, ShouldAutoSize
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
        // 1. Tarik semua PO di periode tersebut (Bisa ditambahkan validasi status agar PO Canceled tidak ikut dihitung jika mau)
        $pos = PurchaseOrder::with('vendor')
            ->whereBetween('po_date', [$this->startDate, $this->endDate])
            ->get();

        // 2. Logika Pemrosesan Grup & Ranking (Top Spend)
        $spends = [];
        foreach($pos as $po) {
            $vendorName = optional($po->vendor)->name ?? 'Tanpa Vendor';
            $currency = $po->currency ?? 'IDR';
            $key = $vendorName . '_' . $currency; // Kunci unik pemisah IDR dan Valas

            if(!isset($spends[$key])) {
                $spends[$key] = [
                    'vendor_name' => $vendorName,
                    'currency'    => $currency,
                    'total_po'    => 0,
                    'total_spend' => 0
                ];
            }
            $spends[$key]['total_po'] += 1;
            $spends[$key]['total_spend'] += (float) $po->grand_total;
        }

        // 3. Urutkan dari pengeluaran terbesar ke terkecil (Ranking)
        usort($spends, function($a, $b) {
            return $b['total_spend'] <=> $a['total_spend'];
        });

        return view('reports.exports.vendor_spend', [
            'spends' => $spends,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate
        ]);
    }
}
