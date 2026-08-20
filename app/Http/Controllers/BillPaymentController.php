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


    // =========================================================================
    // 12. SKENARIO 2: BATALKAN PEMBAYARAN (VOID PAYMENT) DARI KASIR
    // =========================================================================
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

            // 2. Kembalikan status Bill menjadi Unpaid / Approved (Gunakan Helper Sakti)
            $bill->update(['status_id' => $this->getStatusId('approved')]);

            // 3. Catat di Riwayat (Audit Trail) SIAPA yang membatalkan dan ALASANNYA
            // 🔥 Perbaikan: Gunakan Model History yang benar
            \App\Models\History::create([
                'user_id'     => auth()->id(),
                'record_type' => \App\Models\BillRequest::class,
                'record_id'   => $bill->id,
                'action'      => 'Pembayaran Dibatalkan',
                'note'        => 'Nominal Rp ' . number_format($payment->amount_paid, 0, ',', '.') . ' dibatalkan. Alasan: ' . $request->void_reason
            ]);

            // 4. Hapus data pembayaran
            $payment->delete();

            DB::commit();
            return back()->with('success', 'Pembayaran berhasil dibatalkan. Tagihan kembali berstatus Belum Dibayar (Approved).');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal membatalkan pembayaran: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // 13. SKENARIO 1: UPLOAD BUKTI BAYAR SUSULAN DARI KASIR
    // =========================================================================
    public function addLateAttachment(Request $request, $payment_id)
    {
        $request->validate([
            'attachment' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'note'       => 'nullable|string'
        ]);

        try {
            $payment = \App\Models\BillPayment::findOrFail($payment_id);

            // 🔥 1. Ambil path dinamis dari system_settings (Sama seperti PO/PR)
            $basePath = \DB::table('system_settings')->where('setting_key', 'path_payment_opex')->value('setting_value') ?: 'attachments/payment_opex';
            $safePaymentNumber = str_replace(['/', '\\'], '-', $payment->payment_number);
            $storagePath = $basePath . '/' . $safePaymentNumber;

            // 2. Simpan file fisik
            $file = $request->file('attachment');
            $originalName = $file->getClientOriginalName();
            $path = $file->storeAs($storagePath, time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName), 'public');

            // 🔥 3. Masukkan ke database menggunakan DB Builder agar seragam dengan fungsi store()
            \DB::table('payment_attachments')->insert([
                'bill_payment_id' => $payment->id,
                'file_name'       => $originalName,
                'file_path'       => str_replace('\\', '/', $path),
                'description'     => $request->note ?? 'Lampiran Susulan',
                'created_at'      => now(),
                'updated_at'      => now()
            ]);

            return back()->with('success', 'Lampiran bukti susulan berhasil ditambahkan!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengunggah lampiran: ' . $e->getMessage());
        }
    }




    public function printReceiptWithAttachments($payment_slug)
    {
        // Sesuaikan nama Model dan Relasi lampiran Anda (Misal: BillPayment dan attachments)
        $payment = \App\Models\BillPayment::with(['billRequest', 'attachments'])->where('payment_number', $payment_slug)->firstOrFail();

        // 1. RENDER KUITANSI UTAMA
        // Pastikan Anda membuat/menyiapkan view ini di langkah selanjutnya
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('payments.receipt_pdf_with_attachments', compact('payment'))
                ->setPaper('A4', 'portrait');

        // 2. SIMPAN HASIL SEMENTARA
        $tempMainPdfPath = storage_path('app/temp_payment_' . uniqid() . '.pdf');
        $pdf->save($tempMainPdfPath);

        // 3. INISIASI PENGGABUNG PDF
        $merger = new \iio\libmergepdf\Merger();
        $merger->addFile($tempMainPdfPath);

        // 4. JAHIT LAMPIRAN PDF (JIKA ADA)
        if ($payment->attachments) {
            foreach ($payment->attachments as $attachment) {
                $ext = strtolower(pathinfo($attachment->file_path, PATHINFO_EXTENSION));
                if ($ext === 'pdf') {
                    $pdfAttachmentPath = public_path('storage/' . $attachment->file_path);
                    if (file_exists($pdfAttachmentPath)) {
                        $merger->addFile($pdfAttachmentPath);
                    }
                }
            }
        }

        // 5. GABUNGKAN
        $mergedPdfData = $merger->merge();

        // 6. BERSIHKAN FILE SEMENTARA
        if (file_exists($tempMainPdfPath)) {
            unlink($tempMainPdfPath);
        }

        // 7. TAMPILKAN HASIL
        $filename = 'Kuitansi_Lengkap_' . str_replace('/', '_', $payment->payment_number) . '.pdf';

        return response($mergedPdfData)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }




    // =========================================================================
    // CETAK BPR STANDAR (RINGKAS)
    // =========================================================================
    public function printBpr(Request $request, $slug)
    {
        $type = $request->query('type', 'digital');

        // 🔥 PERBAIKAN: Hapus eager load 'approvals' karena belum ada di Model BillRequest
        $bill = \App\Models\BillRequest::with(['items', 'company', 'user', 'payments'])
            ->where('bill_number', $slug)->firstOrFail();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('payments.print_bpr', compact('bill', 'type'))
                ->setPaper('A4', 'portrait');

        $prefix = $type === 'manual' ? 'Manual_' : 'Digital_';
        return $pdf->stream('BPR_OPEX_' . $prefix . str_replace(['/', '\\'], '_', $bill->bill_number) . '.pdf');
    }

    // =========================================================================
    // CETAK BPR DETAIL (BIAYA & DISKON)
    // =========================================================================
    public function printBprDetail(Request $request, $slug)
    {
        $type = $request->query('type', 'digital');

        // 🔥 PERBAIKAN: Hapus eager load 'approvals' karena belum ada di Model BillRequest
        $bill = \App\Models\BillRequest::with(['items', 'company', 'user', 'payments', 'charges', 'discounts'])
            ->where('bill_number', $slug)->firstOrFail();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('payments.print_bpr_detail', compact('bill', 'type'))
                ->setPaper('A4', 'portrait');

        $prefix = $type === 'manual' ? 'Manual_' : 'Digital_';
        return $pdf->stream('BPR_OPEX_Detail_' . $prefix . str_replace(['/', '\\'], '_', $bill->bill_number) . '.pdf');
    }

    
    // =========================================================================
    // CETAK BPR + LAMPIRAN (SMART MERGER SUPER OPEX)
    // =========================================================================
    public function printBprWithAttachments(Request $request, $slug)
    {
        $type = $request->query('type', 'digital');
        $format = $request->query('format', 'standar'); // standar / detail

        // 🔥 PERBAIKAN FINAL: Hapus 'approvals.role' dan 'approvals.approver' dari query with()
        if ($format == 'detail') {
            $bill = \App\Models\BillRequest::with(['items', 'company', 'user', 'payments', 'charges', 'discounts', 'media'])
                ->where('bill_number', $slug)->firstOrFail();
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('payments.print_bpr_detail', compact('bill', 'type'))->setPaper('A4', 'portrait');
            $fileNamePrefix = 'BPR_OPEX_Detail_';
        } else {
            $bill = \App\Models\BillRequest::with(['items', 'company', 'user', 'payments', 'media'])
                ->where('bill_number', $slug)->firstOrFail();
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('payments.print_bpr', compact('bill', 'type'))->setPaper('A4', 'portrait');
            $fileNamePrefix = 'BPR_OPEX_Standar_';
        }

        $tempDir = storage_path('app/public/temp_pdf');
        if (!file_exists($tempDir)) mkdir($tempDir, 0777, true);

        $tempMainPdfPath = $tempDir . '/temp_opex_bpr_' . uniqid() . '.pdf';
        file_put_contents($tempMainPdfPath, $pdf->output());

        $oMerger = \Webklex\PDFMerger\Facades\PDFMergerFacade::init();
        $oMerger->addPDF($tempMainPdfPath, 'all');
        $tempFilesToDelete = [$tempMainPdfPath];
        
        $totalLampiran = 0;
        $allFiles = [];

        // 1. TARIK LAMPIRAN DARI SPATIE MEDIA LIBRARY (UTAMA)
        if ($bill->media && $bill->media->count() > 0) {
            foreach ($bill->media as $media) {
                if (method_exists($media, 'getPath')) {
                    $allFiles[] = ['path' => $media->getPath(), 'name' => $media->file_name];
                } else {
                    $allFiles[] = ['path' => storage_path('app/public/' . ltrim($media->id . '/' . $media->file_name, '/')), 'name' => $media->file_name];
                }
            }
        }

        // 2. TARIK LAMPIRAN DARI TABEL CUSTOM (FALLBACK JIKA ADA)
        if (\Illuminate\Support\Facades\Schema::hasTable('bill_attachments')) {
            $dbAttachments = \DB::table('bill_attachments')->where('bill_request_id', $bill->id)->get();
            foreach ($dbAttachments as $att) {
                $allFiles[] = ['path' => storage_path('app/public/' . ltrim($att->file_path, '/')), 'name' => $att->file_name ?? 'Lampiran'];
            }
        }

        // 3. TARIK BUKTI TRANSFER/PEMBAYARAN KASIR (BONUS OTOMATIS)
        $paymentAttachments = \DB::table('payment_attachments')->whereIn('bill_payment_id', $bill->payments->pluck('id')->toArray())->get();
        foreach ($paymentAttachments as $att) {
            $allFiles[] = ['path' => storage_path('app/public/' . ltrim($att->file_path, '/')), 'name' => 'Bukti_Transfer_' . ($att->file_name ?? '')];
        }

        // PROSES PENGGABUNGAN SEMUA FILE
        if (count($allFiles) > 0) {
            foreach ($allFiles as $file) {
                $finalFilePath = $file['path'];
                $fileName = $file['name'];

                if (file_exists($finalFilePath)) {
                    $totalLampiran++;
                    $extension = strtolower(pathinfo($finalFilePath, PATHINFO_EXTENSION));

                    if ($extension === 'pdf') {
                        try {
                            $fpdi = new \setasign\Fpdi\Fpdi();
                            $fpdi->setSourceFile($finalFilePath);
                            $oMerger->addPDF($finalFilePath, 'all');
                        } catch (\Exception $e) { 
                             // Jika PDF Terkunci / Enkripsi
                             $html = "<div style='border:2px solid #0d6efd; padding:20px; text-align:center; font-family:sans-serif; margin-top:50px;'>
                                        <h2 style='color:#0d6efd;'>📄 LAMPIRAN PDF (TERENKRIPSI/TERKOMPRESI)</h2>
                                        <p>File pendukung bernama: <b>{$fileName}</b></p>
                                        <p>File ini menggunakan format PDF terkunci yang tidak bisa digabungkan secara otomatis oleh sistem.</p>
                                     </div>";
                             $infoPdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait');
                             $infoPath = $tempDir . '/info_' . uniqid() . '.pdf';
                             file_put_contents($infoPath, $infoPdf->output());
                             $oMerger->addPDF($infoPath, 'all');
                             $tempFilesToDelete[] = $infoPath;
                        }
                    } elseif (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                        // Ubah Gambar jadi Halaman PDF
                        $imgPdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML("<html><body style='margin:0;text-align:center;'><img src='data:" . mime_content_type($finalFilePath) . ";base64," . base64_encode(file_get_contents($finalFilePath)) . "' style='max-width:100%;'></body></html>")->setPaper('a4', 'portrait');
                        $imgTempPath = $tempDir . '/img_' . uniqid() . '.pdf';
                        file_put_contents($imgTempPath, $imgPdf->output());
                        $oMerger->addPDF($imgTempPath, 'all');
                        $tempFilesToDelete[] = $imgTempPath;
                    }
                }
            }
        }

        // JIKA TETAP KOSONG
        if ($totalLampiran === 0) {
            $noDataPdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML("<div style='border: 2px solid orange; padding: 20px; font-family: sans-serif; text-align:center; margin-top:50px;'><h2 style='color: orange;'>⚠️ INFO SISTEM ⚠️</h2><p>TIDAK ADA DATA LAMPIRAN (Dokumen atau Bukti Transfer) untuk Tagihan ini.</p></div>")->setPaper('a4', 'portrait');
            $noDataTempPath = $tempDir . '/err_nodata_' . uniqid() . '.pdf';
            file_put_contents($noDataTempPath, $noDataPdf->output());
            $oMerger->addPDF($noDataTempPath, 'all');
            $tempFilesToDelete[] = $noDataTempPath;
        }

        $oMerger->merge();
        $finalPdfOutput = $oMerger->output();

        // Bersihkan Sampah Temporary
        foreach ($tempFilesToDelete as $trashPath) {
            if (file_exists($trashPath)) unlink($trashPath);
        }

        $prefix = $type === 'manual' ? 'Manual_' : 'Digital_';
        return response($finalPdfOutput)->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'inline; filename="' . $fileNamePrefix . $prefix . str_replace('/', '_', $bill->bill_number) . '.pdf"');
    }


}
