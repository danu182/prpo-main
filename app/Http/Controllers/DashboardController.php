<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\BillRequest;

class DashboardController extends Controller
{
    public function index()
    {
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        // =========================================================
        // 1. DATA OPEX (INTI UTAMA) - Harus Jalan
        // =========================================================
        $opexBulanIni = BillRequest::whereMonth('invoice_date', $currentMonth)
            ->whereYear('invoice_date', $currentYear)
            ->whereHas('status', function($q) {
                $q->whereNotIn('slug', ['rejected', 'draft', 'cancelled']);
            })->sum('amount');

        $opexUnpaidBills = BillRequest::with('payments')->whereHas('status', function($q) {
            $q->whereIn('slug', ['approved', 'partial']);
        })->get();

        $opexUnpaid = $opexUnpaidBills->sum(function($bill) {
            return $bill->amount - $bill->payments->sum('amount_paid');
        });

        $monthlyOpex = BillRequest::select(DB::raw('MONTH(invoice_date) as month'), DB::raw('SUM(amount) as total'))
            ->whereYear('invoice_date', $currentYear)
            ->whereHas('status', function($q) {
                $q->whereNotIn('slug', ['rejected', 'draft']);
            })->groupBy('month')->pluck('total', 'month')->toArray();

        $urgentBills = BillRequest::with(['status', 'payments'])
            ->whereHas('status', function($q) {
                $q->whereIn('slug', ['approved', 'partial']);
            })->orderBy('due_date', 'asc')->limit(5)->get();

        // 🔥 FITUR BARU: [Tabel Kiri] Tagihan Baru & Recurring (Menunggu Approval)
        // Ambil ID status secara langsung agar 1000% akurat dan query lebih ringan
        $pendingStatusIds = \App\Models\Status::where('type', 'OPEX')
            ->whereIn('slug', ['pending', 'draft'])
            ->pluck('id');

        $pendingBills = BillRequest::with(['status', 'company'])
            ->whereIn('status_id', $pendingStatusIds)
            ->orderBy('created_at', 'desc')
            ->limit(5)->get();


        // =========================================================
        // 2. DATA PO (DIBERI PENGAMAN TRY-CATCH)
        // =========================================================
        $poBulanIni = 0;
        $monthlyPO = [];
        try {
            if (class_exists('\App\Models\PurchaseOrder')) {
                $poBulanIni = \App\Models\PurchaseOrder::whereMonth('po_date', $currentMonth)
                    ->whereYear('po_date', $currentYear)
                    ->whereNotIn('status_id', [13, 14])
                    ->sum('grand_total');

                $monthlyPO = \App\Models\PurchaseOrder::select(DB::raw('MONTH(po_date) as month'), DB::raw('SUM(grand_total) as total'))
                    ->whereYear('po_date', $currentYear)
                    ->whereNotIn('status_id', [13, 14])
                    ->groupBy('month')->pluck('total', 'month')->toArray();
            }
        } catch (\Exception $e) {
            // Jika tabel PO belum siap, abaikan agar Dashboard tidak mati
        }


        // =========================================================
        // 3. DATA A/P (DIBERI PENGAMAN TRY-CATCH)
        // =========================================================
        $apUnpaid = 0;
        $monthlyAP = [];
        $pendingApBills = collect(); // Penampung kosong bawaan
        $urgentApBills = collect();  // Penampung kosong bawaan

        try {
            if (class_exists('\App\Models\VendorInvoice')) {
                // Total Hutang AP
                $apUnpaid = \App\Models\VendorInvoice::whereIn('status_id', [19, 20])->sum('grand_total');

                $monthlyAP = \App\Models\VendorInvoice::select(DB::raw('MONTH(invoice_date) as month'), DB::raw('SUM(grand_total) as total'))
                    ->whereYear('invoice_date', $currentYear)
                    ->whereNotIn('status_id', [1, 5, 21, 22]) // Kecuali draft, canceled, paid
                    ->groupBy('month')->pluck('total', 'month')->toArray();

                // 🔥 [Tabel Kanan] Tagihan Vendor (A/P) Mendesak & Siap Bayar (Status ID 19 & 20)
                $urgentApBills = \App\Models\VendorInvoice::with(['status', 'vendor'])
                    ->whereIn('status_id', [19, 20])
                    ->orderBy('due_date', 'asc')
                    ->limit(5)->get();

                // 🔥 [Tabel Kiri] Tagihan Vendor (A/P) Menunggu Approval
                $pendingApBills = \App\Models\VendorInvoice::with(['status', 'vendor'])
                    ->whereHas('status', function($q) {
                        $q->whereIn('slug', ['pending', 'submitted', 'waiting_approval']);
                    })
                    ->orderBy('created_at', 'desc')
                    ->limit(5)->get();
            }
        } catch (\Exception $e) {
            // Jika modul AP belum siap, abaikan agar Dashboard tidak mati
        }


        // =========================================================
        // 4. SUSUN DATA GRAFIK
        // =========================================================
        $chartBulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        $chartDataOpex = []; $chartDataPO = []; $chartDataAP = [];

        for ($i = 1; $i <= 12; $i++) {
            $chartDataOpex[] = $monthlyOpex[$i] ?? 0;
            $chartDataPO[]   = $monthlyPO[$i] ?? 0;
            $chartDataAP[]   = $monthlyAP[$i] ?? 0;
        }

        // PASTIKAN VARIABLE A/P IKUT DIKIRIM KE VIEW
        return view('dashboard', compact(
            'opexBulanIni', 'poBulanIni', 'apUnpaid', 'opexUnpaid',
            'chartBulan', 'chartDataOpex', 'chartDataPO', 'chartDataAP',
            'urgentBills', 'pendingBills', 'urgentApBills', 'pendingApBills', 'currentYear'
        ));


        // =========================================================
        // 4. SUSUN DATA GRAFIK
        // =========================================================
        $chartBulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        $chartDataOpex = []; $chartDataPO = []; $chartDataAP = [];

        for ($i = 1; $i <= 12; $i++) {
            $chartDataOpex[] = $monthlyOpex[$i] ?? 0;
            $chartDataPO[]   = $monthlyPO[$i] ?? 0;
            $chartDataAP[]   = $monthlyAP[$i] ?? 0;
        }

        // Pastikan variabel $pendingBills ikut dikirim ke view
        return view('dashboard', compact(
            'opexBulanIni', 'poBulanIni', 'apUnpaid', 'opexUnpaid',
            'chartBulan', 'chartDataOpex', 'chartDataPO', 'chartDataAP',
            'urgentBills', 'pendingBills', 'currentYear'
        ));
    }

    // FUNGSI BARU: Membaca Notifikasi
    public function markNotificationAsRead($id)
    {
        // Cari notifikasi milik user yang sedang login berdasarkan ID-nya
        $notification = auth()->user()->notifications()->findOrFail($id);

        // Tandai sudah dibaca (Titik merah hilang)
        $notification->markAsRead();

        // Lempar user ke URL tujuan yang ada di dalam notifikasi tersebut (Halaman Detail PR/PO)
        return redirect($notification->data['url']);
    }

}
