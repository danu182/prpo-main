<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BillRequest;
use App\Models\Status;
use Carbon\Carbon;

class FinanceReportController extends Controller
{
    /**
     * Menampilkan Halaman Filter & Tabel Laporan
     */
    public function index(Request $request)
    {
        // 1. Default Tanggal: Dari tanggal 1 bulan ini sampai hari terakhir bulan ini
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $statusSlug = $request->input('status', 'all');

        // 2. Mulai Query Pencarian
        $query = BillRequest::with(['company', 'status', 'payments'])
            ->whereBetween('invoice_date', [$startDate, $endDate]);

        // 3. Filter berdasarkan Status (Jika bukan 'all')
        if ($statusSlug !== 'all') {
            $query->whereHas('status', function($q) use ($statusSlug) {
                $q->where('slug', $statusSlug);
            });
        }

        $bills = $query->orderBy('invoice_date', 'asc')->get();

        // 4. Kalkulasi Ringkasan (Summary) untuk Direksi
        $totalExpense = $bills->sum('amount'); // Total Keseluruhan Tagihan
        $totalPaid = $bills->sum(function($bill) {
            return $bill->payments->sum('amount_paid'); // Total yang sudah dibayar
        });
        $totalUnpaid = $totalExpense - $totalPaid; // Sisa Hutang

        // 5. Ambil Master Status untuk Dropdown Filter
        $statuses = Status::where('type', 'OPEX')->orderBy('sequence')->get();

        return view('reports.finance', compact(
            'bills', 'startDate', 'endDate', 'statusSlug',
            'totalExpense', 'totalPaid', 'totalUnpaid', 'statuses'
        ));
    }

    /**
     * Nanti kita isi untuk Export PDF
     */
    public function exportPdf(Request $request)
    {
        // 1. Ambil parameter filter yang sama persis dengan tampilan index
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $statusSlug = $request->input('status', 'all');

        // 2. Query Data
        $query = BillRequest::with(['company', 'status', 'payments'])
            ->whereBetween('invoice_date', [$startDate, $endDate]);

        if ($statusSlug !== 'all') {
            $query->whereHas('status', function($q) use ($statusSlug) {
                $q->where('slug', $statusSlug);
            });
        }

        $bills = $query->orderBy('invoice_date', 'asc')->get();

        // 3. Kalkulasi Total
        $totalExpense = $bills->sum('amount');
        $totalPaid = $bills->sum(function($bill) {
            return $bill->payments->sum('amount_paid');
        });
        $totalUnpaid = $totalExpense - $totalPaid;

        // 4. Generate PDF menggunakan package dompdf (Pastikan \PDF sudah terinstall)
        $pdf = \PDF::loadView('reports.finance_pdf', compact(
            'bills', 'startDate', 'endDate', 'statusSlug',
            'totalExpense', 'totalPaid', 'totalUnpaid'
        ));

        // Format nama file: Laporan_Opex_2026-03-01_sd_2026-03-31.pdf
        $fileName = 'Laporan_Opex_' . $startDate . '_sd_' . $endDate . '.pdf';

        return $pdf->stream($fileName); // Gunakan stream() untuk preview, download() untuk langsung unduh
    }

    /**
     * Nanti kita isi untuk Export Excel
     */
    public function exportExcel(Request $request)
    {
        // 1. Ambil parameter filter
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $statusSlug = $request->input('status', 'all');

        // 2. Query Data
        $query = BillRequest::with(['company', 'status', 'payments'])
            ->whereBetween('invoice_date', [$startDate, $endDate]);

        if ($statusSlug !== 'all') {
            $query->whereHas('status', function($q) use ($statusSlug) {
                $q->where('slug', $statusSlug);
            });
        }

        $bills = $query->orderBy('invoice_date', 'asc')->get();

        // 3. Setup Header untuk Download File CSV
        $fileName = 'Laporan_Opex_' . $startDate . '_sd_' . $endDate . '.csv';
        $headers = array(
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        // 4. Proses Tulis Data ke File
        $callback = function() use($bills) {
            $file = fopen('php://output', 'w');

            // SUNTIKAN RAHASIA 1: UTF-8 BOM agar Excel tidak buta huruf
            fputs($file, "\xEF\xBB\xBF");

            // SUNTIKAN RAHASIA 2: Gunakan Titik Koma (;) sebagai pemisah kolom
            $delimiter = ';';

            // Tulis Judul Kolom (Header Excel)
            fputcsv($file, ['No', 'No. Tagihan', 'Tanggal', 'Vendor', 'PT Penanggung Jawab', 'Total Tagihan', 'Telah Dibayar', 'Sisa Hutang', 'Status'], $delimiter);

            // Tulis Baris Data
            foreach ($bills as $index => $bill) {
                $paidAmount = $bill->payments->sum('amount_paid');
                $sisaHutang = $bill->amount - $paidAmount;

                fputcsv($file, [
                    $index + 1,
                    $bill->bill_number,
                    \Carbon\Carbon::parse($bill->invoice_date)->format('Y-m-d'),
                    $bill->vendor_name,
                    $bill->company->name ?? '-',
                    $bill->amount,
                    $paidAmount,
                    $sisaHutang,
                    strtoupper(optional($bill->status)->name ?? 'UNKNOWN')
                ], $delimiter); // Gunakan delimiter titik koma di sini juga
            }
            fclose($file);
        };

        // Kirim response download
        return response()->stream($callback, 200, $headers);
    }
}
