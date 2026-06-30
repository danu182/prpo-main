<?php

namespace App\Http\Controllers;

use App\Models\BillRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class BillPaymentController extends Controller
{
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

        $query = \App\Models\BillRequest::with(['company', 'user', 'payments', 'status'])
            ->latest();

        if ($tab == 'paid') {
            $query->whereHas('status', function($q) {
                $q->where('slug', 'paid');
            });
        } else {
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
    public function process($slug)
    {
        // 🔥 PERBAIKAN: Cari berdasarkan bill_number
        $bill = \App\Models\BillRequest::with(['items', 'payments.paymentMethod', 'media', 'status'])
                    ->where('bill_number', $slug)->firstOrFail();

        $companies = \App\Models\Company::orderBy('name')->get();
        $paymentMethods = \App\Models\PaymentMethod::where('is_active', true)->get();

        return view('payments.process', compact('bill', 'companies', 'paymentMethods'));
    }

    // --- Menyimpan Data Pembayaran (Logika Utama) ---
    public function store(Request $request, $slug)
    {
        // 🔥 PERBAIKAN: Cari berdasarkan bill_number
        $bill = \App\Models\BillRequest::where('bill_number', $slug)->firstOrFail();

        // Hitung sisa hutang
        $totalPaidBefore = $bill->payments->sum('amount_paid');
        $remaining = $bill->amount - $totalPaidBefore;

        // 1. VALIDASI INPUT
        $request->validate([
            'amount_paid' => [
            'required',
            'numeric',
            'min:1',
                function ($attribute, $value, $fail) use ($remaining) {
                    if ($value > $remaining) {
                        $fail("Nominal pembayaran tidak boleh melebihi sisa hutang (" . number_format($remaining) . ").");
                    }
                },
            ],
            'paid_by_company_id' => 'required|exists:companies,id',
            'payment_method_id'  => 'required|exists:payment_methods,id',
            'payment_date'       => 'required|date',
            'payment_proofs'     => 'nullable|array|min:1',
            'payment_proofs.*'   => 'nullable|file|max:5120',
        ]);

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

            // 3. Upload File Bukti Bayar (KONSEP CUSTOM STORAGE)
            if ($request->hasFile('payment_proofs')) {
                // Ambil path dari system_settings sesuai gambar Anda
                $basePath = \DB::table('system_settings')->where('setting_key', 'path_payment_opex')->value('setting_value') ?: 'attachments/payment_opex';
                $safePaymentNumber = str_replace(['/', '\\'], '-', $autoNumber);
                $storagePath = $basePath . '/' . $safePaymentNumber;

                foreach ($request->file('payment_proofs') as $index => $file) {
                    $description = $request->payment_proof_descriptions[$index] ?? 'Dokumen Pendukung';
                    $originalName = $file->getClientOriginalName();

                    // Simpan ke storage public
                    $path = $file->storeAs($storagePath, time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName), 'public');

                    // Simpan ke tabel payment_attachments
                    \DB::table('payment_attachments')->insert([
                        'bill_payment_id' => $payment->id,
                        'file_name'       => $originalName,
                        'file_path'       => str_replace('\\', '/', $path),
                        'description'     => $description,
                        'created_at'      => now(),
                        'updated_at'      => now()
                    ]);
                }
            }

            // 4. Update Status Tagihan
            $totalPaidNow = $totalPaidBefore + $request->amount_paid;
            $isLunas = false; // Penanda apakah ini pelunasan

            if ($totalPaidNow >= $bill->amount) {
                $bill->status_id = $this->getStatusId('paid');
                $isLunas = true; // Set penanda menjadi True

                // Logika Recurring
                if ($bill->is_recurring) {
                    $interval = $bill->recurring_interval ?? 1;
                    $period   = $bill->recurring_period ?? 'months';
                    $bill->next_generation_date = now()->add($interval, $period);
                }
            } else {
                $bill->status_id = $this->getStatusId('partial');
            }
            $bill->save();

            // 5. Simpan History secara Dinamis
            $curr = $bill->currency;

            // 🔥 PERBAIKAN: Teks log mengikuti status lunas / belum
            $actionTitle = $isLunas ? 'Pembayaran Lunas' : 'Pembayaran Termin (Cicilan)';

            \App\Models\History::create([
                'user_id'     => auth()->id(),
                'record_type' => \App\Models\BillRequest::class,
                'record_id'   => $bill->id,
                'action'      => $actionTitle,
                'note'        => "Via: {$method->name} | Ref: {$autoNumber} | {$curr} " . number_format($request->amount_paid)
            ]);

            \DB::commit();

            \DB::commit();
            return redirect()->route('payments.index')->with('success', "Pembayaran berhasil. No Ref: $autoNumber");

        } catch (\Exception $e) {
            \DB::rollback();
            return back()->withInput()->with('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    }


    public function destroy(Request $request, $payment_slug)
    {
        // 🔥 PERBAIKAN: Cari Pembayaran berdasarkan payment_number
        $payment = \App\Models\BillPayment::where('payment_number', $payment_slug)->firstOrFail();
        $bill = \App\Models\BillRequest::findOrFail($payment->bill_request_id);

        \DB::beginTransaction();
        try {
            $reason = $request->input('void_reason', 'Tanpa alasan');
            $amount = number_format($payment->amount_paid, 0, ',', '.');
            $user = auth()->user()->name;

            \App\Models\History::create([
                'user_id'     => auth()->id(),
                'record_type' => \App\Models\BillRequest::class,
                'record_id'   => $bill->id,
                'action'      => 'VOID PAYMENT',
                'note'        => "Void Ref: {$payment->payment_number} ({$bill->currency} {$amount}). Alasan: {$reason} (oleh {$user})"
            ]);

            // B. HAPUS DATA PEMBAYARAN & FILE FISIK (KONSEP CUSTOM)
            $attachments = \DB::table('payment_attachments')->where('bill_payment_id', $payment->id)->get();
            foreach ($attachments as $att) {
                if (\Storage::disk('public')->exists($att->file_path)) {
                    \Storage::disk('public')->delete($att->file_path);
                }
            }
            \DB::table('payment_attachments')->where('bill_payment_id', $payment->id)->delete();
            $payment->delete();

            $bill->load('payments');
            $totalPaidNow = $bill->payments->sum('amount_paid');

            if ($totalPaidNow <= 0) {
                $bill->status_id = $this->getStatusId('approved');
            } else {
                $bill->status_id = $this->getStatusId('partial');
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
    public function printReceipt($payment_slug)
    {
        // 🔥 PERBAIKAN: Cari Pembayaran berdasarkan payment_number
        $payment = \App\Models\BillPayment::with(['billRequest', 'paymentMethod', 'paidByCompany'])
                        ->where('payment_number', $payment_slug)->firstOrFail();

        $pdf = \PDF::loadView('payments.print_receipt', compact('payment'));
        return $pdf->stream('Receipt-' . str_replace('/', '-', $payment->payment_number) . '.pdf');
    }

    // Cetak Rekapitulasi Pembayaran (Keseluruhan)
    public function printStatement(Request $request, $slug)
    {
        // 🔥 PERBAIKAN: Cari Tagihan berdasarkan bill_number
        $bill = \App\Models\BillRequest::with(['company', 'items', 'payments.paymentMethod'])
                    ->where('bill_number', $slug)->firstOrFail();

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


    public function voidPayment(Request $request, $payment_id)
    {
        // 1. Gembok fitur ini hanya untuk Manager & Super Admin
        if (!auth()->user()->hasAnyRole(['super-admin', 'manager', 'Super Administrator'])) {
            abort(403, 'Anda tidak memiliki wewenang membatalkan pembayaran.');
        }

        $request->validate([
            'void_reason' => 'required|string|min:10'
        ]);

        DB::beginTransaction();
        try {
            $payment = \App\Models\BillPayment::findOrFail($payment_id);
            $bill = \App\Models\BillRequest::findOrFail($payment->bill_request_id);

            // 2. Kembalikan status Bill menjadi Unpaid / Approved
            $statusUnpaid = \App\Models\Status::where('type', 'BILLS')->where('slug', 'approved')->first();
            $bill->update(['status_id' => $statusUnpaid->id]);

            // 3. Catat di Riwayat (Audit Trail) SIAPA yang membatalkan dan ALASANNYA
            \App\Models\BillHistory::create([
                'bill_request_id' => $bill->id,
                'user_id'         => auth()->id(),
                'action'          => 'Pembayaran Dibatalkan',
                'note'            => 'Nominal Rp ' . number_format($payment->amount, 0, ',', '.') . ' dibatalkan. Alasan: ' . $request->void_reason
            ]);

            // 4. Hapus data pembayaran (Atau ubah statusnya jadi 'void' jika pakai SoftDeletes)
            $payment->delete();

            DB::commit();
            return back()->with('success', 'Pembayaran berhasil dibatalkan. Tagihan kembali berstatus Belum Dibayar.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal membatalkan pembayaran: ' . $e->getMessage());
        }
    }

    public function addLateAttachment(Request $request, $payment_id)
    {
        $request->validate([
            'attachment' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'note'       => 'nullable|string'
        ]);

        try {
            $payment = \App\Models\BillPayment::findOrFail($payment_id);

            // Simpan file baru
            $file = $request->file('attachment');
            $path = $file->storeAs("bills_payment/{$payment->id}", $file->getClientOriginalName(), 'public');

            // Masukkan ke database lampiran pembayaran
            \App\Models\BillPaymentAttachment::create([
                'bill_payment_id' => $payment->id,
                'file_name'       => $file->getClientOriginalName(),
                'file_path'       => $path,
                'note'            => $request->note
            ]);

            return back()->with('success', 'Lampiran susulan berhasil ditambahkan!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengunggah lampiran: ' . $e->getMessage());
        }
    }

}
