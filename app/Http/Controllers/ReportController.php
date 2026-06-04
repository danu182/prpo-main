<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Exports\PoRecapExport;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    public function generate(Request $request)
    {
        $request->validate([
            'report_type' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $type = $request->report_type;
        $start = $request->start_date;
        $end = $request->end_date;

        // JIKA YANG DIMINTA ADALAH REKAP PO
        if ($type === 'po_recap') {
            $fileName = 'Laporan_Rekap_PO_' . date('Ymd', strtotime($start)) . '_sd_' . date('Ymd', strtotime($end)) . '.xlsx';
            return Excel::download(new PoRecapExport($start, $end), $fileName);
        }
        // ==========================================
        // SUNTIKKAN KODE INI UNTUK MENANGKAP GR
        // ==========================================
        if ($type === 'gr_recap') {
            $fileName = 'Laporan_Penerimaan_Barang_GR_' . date('Ymd', strtotime($start)) . '_sd_' . date('Ymd', strtotime($end)) . '.xlsx';
            return Excel::download(new \App\Exports\GrRecapExport($start, $end), $fileName);
        }
        // ==========================================
        // SUNTIKKAN KODE INI UNTUK MENANGKAP RTV
        // ==========================================
        if ($type === 'rtv_recap') {
            $fileName = 'Laporan_Retur_Vendor_RTV_' . date('Ymd', strtotime($start)) . '_sd_' . date('Ymd', strtotime($end)) . '.xlsx';
            return Excel::download(new \App\Exports\RtvRecapExport($start, $end), $fileName);
        }
        // ==========================================
        // ==========================================
        // SUNTIKKAN KODE INI UNTUK MENANGKAP PEMBAYARAN KASIR
        // ==========================================
        if ($type === 'payment_recap') {
            $fileName = 'Laporan_Pengeluaran_Kas_' . date('Ymd', strtotime($start)) . '_sd_' . date('Ymd', strtotime($end)) . '.xlsx';
            return Excel::download(new \App\Exports\PaymentRecapExport($start, $end), $fileName);
        }
        // ==========================================
        // ==========================================
        // SUNTIKKAN KODE INI UNTUK MENANGKAP TOP SPEND VENDOR
        // ==========================================
        if ($type === 'vendor_spend') {
            $fileName = 'Laporan_Top_Spend_Vendor_' . date('Ymd', strtotime($start)) . '_sd_' . date('Ymd', strtotime($end)) . '.xlsx';
            return Excel::download(new \App\Exports\VendorSpendExport($start, $end), $fileName);
        }
        // ==========================================
        // ==========================================
        // SUNTIKKAN KODE INI UNTUK MENANGKAP KARTU MUTASI
        // ==========================================
        if ($type === 'stock_mutation') {
            $fileName = 'Laporan_Kartu_Mutasi_Stok_' . date('Ymd', strtotime($start)) . '_sd_' . date('Ymd', strtotime($end)) . '.xlsx';
            return Excel::download(new \App\Exports\StockMutationExport($start, $end), $fileName);
        }
        // ==========================================
        // ==========================================
        // SUNTIKKAN KODE INI UNTUK MENANGKAP AGING HUTANG
        // ==========================================
        if ($type === 'aging_ap') {
            $fileName = 'Laporan_Aging_Hutang_Vendor_' . date('Ymd', strtotime($start)) . '_sd_' . date('Ymd', strtotime($end)) . '.xlsx';
            return Excel::download(new \App\Exports\AgingApExport($start, $end), $fileName);
        }
        // ==========================================

        // Jika jenis laporan belum kita buat:
        return back()->with('error', 'Laporan jenis ini masih dalam tahap perakitan di Markas Pusat, Komandan!');
    }
}
