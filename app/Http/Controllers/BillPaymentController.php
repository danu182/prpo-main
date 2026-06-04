<?php

namespace App\Http\Controllers;

use App\Models\BillRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class BillPaymentController extends Controller
{
    // public function __construct()
    // {
    //     // Hanya user dengan permission 'view_payments' yang bisa akses controller ini
    //     $this->middleware('permission:view_payments');
    // }

    /**
     * Helper Sakti untuk mencari status_id khusus tagihan OPEX
     */
    private function getStatusId($slug)
    {
        $status = \App\Models\Status::where('type', 'OPEX')->where('slug', $slug)->first();
        return $status ? $status->id : null;
    }

    private function generatePaymentNumber($payingCompanyId)
    {
        // 1. Ambil Kode Perusahaan Pembayar
        $company = \App\Models\Company::find($payingCompanyId);
        // Jika ada kolom 'code', pakai itu. Jika tidak, ambil 3 huruf pertama nama.
        $code = ($company && !empty($company->code))
                ? strtoupper($company->code)
                : strtoupper(substr($company->name ?? 'GEN', 0, 3));

        // 2. Format Tanggal: YYYY/MM/DD
        $dateStr = now()->format('Y/m/d');

        // 3. Susun Prefix: PAY/TLKM/2026/02/12/
        $prefix = "PAY/{$code}/{$dateStr}/";

        // 4. Cari Nomor Terakhir (Lock For Update agar tidak duplikat)
        $lastPayment = \App\Models\BillPayment::where('payment_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        if ($lastPayment) {
            // Ambil 4 digit terakhir
            $lastNumber = (int) substr($lastPayment->payment_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        // 5. Hasil: PAY/TLKM/2026/02/12/0001
        return $prefix . sprintf('%04d', $newNumber);
    }


    public function index(Request $request)
    {
        // Default tab adalah 'unpaid' (Hutang Berjalan)
        $tab = $request->get('tab', 'unpaid');

        // PERBAIKAN: Tambahkan relasi 'status' ke eager loading
        $query = \App\Models\BillRequest::with(['company', 'user', 'payments', 'status'])
            ->latest();

        if ($tab == 'paid') {
            // PERBAIKAN: Tampilkan HANYA yang sudah LUNAS (pakai slug)
            $query->whereHas('status', function($q) {
                $q->where('slug', 'paid');
            });
        } else {
            // PERBAIKAN: Tampilkan yang MASIH HUTANG (Baru atau Nyicil)
            $query->whereHas('status', function($q) {
                $q->whereIn('slug', ['approved', 'partial']);
            });
        }

        $debts = $query->get();

        // Data filter dropdown
        $companies = \App\Models\Company::orderBy('name')->get();
        $vendors = $debts->pluck('vendor_name')->unique()->sort()->values();

        return view('payments.index', compact('debts', 'companies', 'vendors', 'tab'));
    }

    // --- Menampilkan Halaman Form Pembayaran ---
    public function process($id)
    {
        // PERBAIKAN: Load relasi status agar tampilan bisa memanggil $bill->status->name
        $bill = \App\Models\BillRequest::with(['items', 'payments.paymentMethod', 'media', 'status'])->findOrFail($id);
        $companies = \App\Models\Company::orderBy('name')->get();

        // AMBIL DATA METODE PEMBAYARAN YANG AKTIF SAJA
        $paymentMethods = \App\Models\PaymentMethod::where('is_active', true)->get();

        return view('payments.process', compact('bill', 'companies', 'paymentMethods'));
    }

    // --- Menyimpan Data Pembayaran (Logika Utama) ---
    public function store(Request $request, $id)
    {
        $bill = \App\Models\BillRequest::findOrFail($id);

        // Hitung sisa hutang
        $totalPaidBefore = $bill->payments->sum('amount_paid');
        $remaining = $bill->amount - $totalPaidBefore;

        // 1. VALIDASI INPUT
        $request->validate([
            'amount_paid' => [
            'required',
            'numeric',
            'min:1',
              // Custom validation: Gagal jika input > sisa hutang
                function ($attribute, $value, $fail) use ($remaining) {
                    if ($value > $remaining) {
                        $fail("Nominal pembayaran tidak boleh melebihi sisa hutang (" . number_format($remaining) . ").");
                    }
                },
            ],
            'paid_by_company_id' => 'required|exists:companies,id',
            'payment_method_id'  => 'required|exists:payment_methods,id', // Validasi tetap di sini
            'payment_date'       => 'required|date',
            'payment_proofs'     => 'nullable|array|min:1',
            'payment_proofs.*'   => 'nullable|file|max:5120',
        ]);

        // Cek apakah wajib referensi (Logic Backend)
        $method = \App\Models\PaymentMethod::find($request->payment_method_id);
        if ($method->require_reference && empty($request->transaction_reference)) {
             return back()->withInput()->withErrors(['transaction_reference' => 'Nomor Referensi wajib diisi untuk metode ini.']);
        }

        \DB::beginTransaction();
        try {
            // Generate Nomor Pembayaran
            $autoNumber = $this->generatePaymentNumber($request->paid_by_company_id);

            // 2. SIMPAN DATA
            $payment = $bill->payments()->create([
                'paid_by_company_id'    => $request->paid_by_company_id,
                'payment_number'        => $autoNumber,
                'payment_method_id'     => $request->payment_method_id,
                'transaction_reference' => $request->transaction_reference,
                'amount_paid'           => $request->amount_paid,
                'payment_date'          => $request->payment_date,
                'note'                  => $request->note,
            ]);

            // 3. Upload File
            if ($request->hasFile('payment_proofs')) {
                foreach ($request->file('payment_proofs') as $file) {
                    $payment->addMedia($file)->toMediaCollection('payment_proofs', 'public');
                }
            }

            // 4. Update Status Tagihan (PERBAIKAN MENGGUNAKAN STATUS ID)
            $totalPaidNow = $totalPaidBefore + $request->amount_paid;
            if ($totalPaidNow >= $bill->amount) {
                $bill->status_id = $this->getStatusId('paid');

                // Logika Recurring (Jatuh Tempo Berikutnya)
                if ($bill->is_recurring) {
                    $interval = $bill->recurring_interval ?? 1;
                    $period   = $bill->recurring_period ?? 'months'; // days, weeks, months, years
                    $bill->next_generation_date = now()->add($interval, $period);
                }
            } else {
                $bill->status_id = $this->getStatusId('partial');
            }
            $bill->save();

            // 5. Simpan History
            $curr = $bill->currency;
            $payerName = \App\Models\Company::find($request->paid_by_company_id)->name;

            \App\Models\History::create([
                'user_id'     => auth()->id(),
                'record_type' => \App\Models\BillRequest::class,
                'record_id'   => $bill->id,
                'action'      => 'Melakukan Pembayaran Termin',
                'note'        => "Via: {$method->name} | Ref: {$autoNumber} | {$curr} " . number_format($request->amount_paid)
            ]);

            \DB::commit();
            return redirect()->route('payments.index')->with('success', "Pembayaran berhasil. No Ref: $autoNumber");

        } catch (\Exception $e) {
            \DB::rollback();
            return back()->withInput()->with('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    }


    public function destroy(Request $request, $id)
    {
        // 1. Cari Data Pembayaran
        $payment = \App\Models\BillPayment::findOrFail($id);

        // 2. Cari Data Tagihan Induk
        $bill = \App\Models\BillRequest::findOrFail($payment->bill_request_id);

        \DB::beginTransaction();
        try {
            // Ambil alasan dari input form, jika kosong isi default
            $reason = $request->input('void_reason', 'Tanpa alasan');
            $amount = number_format($payment->amount_paid, 0, ',', '.');
            $user = auth()->user()->name;

            // A. CATAT KE HISTORY (AUDIT TRAIL)
            \App\Models\History::create([
                'user_id'     => auth()->id(),
                'record_type' => \App\Models\BillRequest::class,
                'record_id'   => $bill->id,
                'action'      => 'VOID PAYMENT',
                'note'        => "Void Ref: {$payment->payment_number} (IDR {$amount}). Alasan: {$reason} (oleh {$user})"
            ]);

            // B. HAPUS DATA PEMBAYARAN & FILE
            $payment->clearMediaCollection('payment_proofs');
            $payment->delete();

            // C. KALKULASI ULANG STATUS TAGIHAN (PERBAIKAN MENGGUNAKAN STATUS ID)
            $bill->load('payments'); // Refresh data
            $totalPaidNow = $bill->payments->sum('amount_paid');

            if ($totalPaidNow <= 0) {
                $bill->status_id = $this->getStatusId('approved'); // Kembali jadi hutang utuh
            } else {
                $bill->status_id = $this->getStatusId('partial'); // Masih ada cicilan lain
            }
            $bill->save();

            \DB::commit();
            return back()->with('success', 'Pembayaran berhasil dibatalkan (VOID).');

        } catch (\Exception $e) {
            \DB::rollback();
            return back()->with('error', 'Gagal membatalkan: ' . $e->getMessage());
        }
    }


    // Cetak Bukti Bayar Satuan (Kuitansi)
    public function printReceipt($payment_id)
    {
        $payment = \App\Models\BillPayment::with(['billRequest', 'paymentMethod', 'paidByCompany'])->findOrFail($payment_id);

        $pdf = \PDF::loadView('payments.print_receipt', compact('payment'));
        return $pdf->stream('Receipt-' . str_replace('/', '-', $payment->payment_number) . '.pdf');
    }

    // Cetak Rekapitulasi Pembayaran (Keseluruhan)
    public function printStatement(Request $request, $id)
    {
        $bill = \App\Models\BillRequest::with(['company', 'items', 'payments.paymentMethod'])->findOrFail($id);

        $paymentsQuery = $bill->payments();
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $paymentsQuery->whereBetween('payment_date', [$request->start_date, $request->end_date]);
        }
        $payments = $paymentsQuery->orderBy('payment_date', 'asc')->get();

        $pdf = \PDF::loadView('payments.print_statement', [
            'bill' => $bill,
            'payments' => $payments,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date
        ]);

        $fileName = 'Rekap-Pembayaran-' . str_replace('/', '-', $bill->bill_number) . '.pdf';

        return $pdf->stream($fileName);
    }
}
