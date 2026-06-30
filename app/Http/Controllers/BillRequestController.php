<?php

namespace App\Http\Controllers;

use App\Models\BillRequest;
use App\Models\History;
use App\Models\Company;
use App\Models\Currency;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillRequestController extends Controller
{

   /**
     * Helper Sakti untuk mencari status_id
     */
    private function getStatusId($slug)
    {
        $status = \App\Models\Status::where('type', 'OPEX')->where('slug', $slug)->first();
        return $status ? $status->id : null; // Pastikan data di Seeder sudah masuk
    }


    // --- HELPER NUMBER GENERATOR (MIRIP PR) ---
    /**
     * Helper untuk generate nomor tagihan otomatis
     * Format: BILL/CODE/YYYY/MM/DD/XXXX (Reset harian)
     */
    private function generateBillNumber($companyId)
    {
        // 1. Ambil Data Company
        $company = \App\Models\Company::find($companyId);

        // Ambil kode. Jika kolom 'code' kosong, ambil 3 huruf pertama nama PT
        if ($company && !empty($company->code)) {
            $code = strtoupper($company->code);
        } else {
            // Fallback: PT. Maju Jaya -> PTM
            $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $company->name ?? 'GEN');
            $code = strtoupper(substr($cleanName, 0, 3));
        }

        // 2. Format Tanggal (Harian): YYYY/MM/DD
        $now = now();
        $dateStr = $now->format('Y/m/d');

        // 3. Susun Prefix
        // Contoh: BILL/TLKM/2026/02/11/
        $prefix = "BILL/{$code}/{$dateStr}/";

        // 4. Cari Nomor Terakhir (Lock For Update agar aman saat traffic tinggi)
        $lastBill = \App\Models\BillRequest::where('bill_number', 'like', $prefix . '%')
                    ->orderBy('id', 'desc')
                    ->lockForUpdate()
                    ->first();

        if ($lastBill) {
            // Ambil 4 digit terakhir
            $lastNumber = (int) substr($lastBill->bill_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        // 5. Gabungkan (0001)
        return $prefix . sprintf('%04d', $newNumber);
    }


    public function getTaxRate($billDate)
    {
        return \App\Models\Tax::where('name', 'PPN')
            ->where('is_active', true)
            ->where('effective_date', '<=', $billDate) // Cari yang sudah berlaku pada tanggal bill
            ->orderBy('effective_date', 'desc')        // Ambil yang paling terbaru/mendekati
            ->first();
    }





    // --- 1. MENAMPILKAN LIST ---
    public function index(Request $request)
    {
        $companies = \App\Models\Company::orderBy('name')->get();

        // TAMBAHKAN RELASI 'status' DI SINI (Ganti 'statusData' jika nama relasi Anda berbeda di Model)
        $query = \App\Models\BillRequest::with(['company', 'user', 'status'])
                    ->latest();

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('vendor')) {
            $query->where('vendor_name', 'like', '%' . $request->vendor . '%');
        }

        // PERBAIKAN FILTER STATUS (Karena dropdown di form Anda mengirimkan Teks besar seperti 'PENDING')
        if ($request->filled('status')) {
            $slug = strtolower($request->status); // Ubah PENDING jadi pending
            $statusId = $this->getStatusId($slug);
            if($statusId) {
                $query->where('status_id', $statusId);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('bill_number', 'like', "%$search%")
                ->orWhere('description', 'like', "%$search%")
                ->orWhere('title', 'like', "%$search%");
            });
        }

        $bills = $query->paginate(10)->withQueryString();

        return view('bills.index', compact('bills', 'companies'));
    }


   // --- 2. FORM CREATE ---
    public function create()
    {
        $companies  = \App\Models\Company::all();
        $taxes      = \App\Models\Tax::where('is_active', true)->orderBy('name')->get();
        $currencies = \App\Models\Currency::where('is_active', true)->orderBy('name')->get();
        $vendors    = \App\Models\Vendor::orderBy('name')->get();

        // 🔥 PERBAIKAN: Gunakan item_type_code untuk mengambil Jasa/Non-Stok (OPEX)
        $opexItems  = \App\Models\Item::where('item_type_code', 'JSA') // JSA biasanya kode untuk Jasa/Non-Stok
                                      ->orWhereNull('item_type_code') // Jaga-jaga jika ada barang lawas yang belum di-set tipenya
                                      ->orderBy('name')
                                      ->get();


        // 🔥 PERBAIKAN: Filter buang yang sifatnya fisik gudang (STK) dan Aset Tetap (AST)
        // $opexItems  = \App\Models\Item::whereNotIn('item_type_code', ['AST', 'STK'])
        //                               ->orWhereNull('item_type_code')
        //                               ->orderBy('name')
        //                               ->get();

        // Panggil Master Biaya Tambahan (Charges)
        $chargeTypes = \App\Models\ChargeType::where('is_active', true)->orderBy('name')->get();

        // TAMBAHAN BARU: Panggil Master Potongan Harga (Discounts)
        $discountTypes = \App\Models\DiscountType::where('is_active', true)->orderBy('name')->get();

        return view('bills.create', compact('companies', 'taxes','currencies', 'vendors', 'opexItems', 'chargeTypes', 'discountTypes'));
    }


    // --- 4. APPROVAL LOGIC (SAMA SEPERTI PR) ---
    public function approveReject(Request $request, $id)
    {
        $bill = BillRequest::findOrFail($id);
        $user = Auth::user();
        $action = $request->action; // APPROVED / REJECTED
        $reason = $request->reason;

        // Security Gate (Sama seperti PR)
        if ($bill->current_approval_level == 0 && !$user->hasRole('Manager')) {
            return back()->with('error', 'Akses Ditolak: Giliran Manager.');
        }
        if ($bill->current_approval_level == 1 && !$user->hasRole('Director')) {
            return back()->with('error', 'Akses Ditolak: Giliran Director.');
        }

        // Logic Status
        if ($action == 'REJECTED') {
            $bill->update(['status' => 'REJECTED', 'rejection_reason' => $reason]);
            $this->logHistory($bill, 'REJECTED', "Ditolak oleh " . $user->name . ". Alasan: $reason");
        } else {
            // Jika Approved
            if ($bill->current_approval_level == 0) {
                // Manager Approve -> Lanjut ke Director
                $bill->update([
                    'current_approval_level' => 1,
                    'status' => 'APPROVED_MANAGER'
                ]);
                $this->logHistory($bill, 'APPROVED', 'Disetujui oleh Manager.');
            } else {
                // Director Approve -> Final
                $bill->update([
                    'current_approval_level' => 2,
                    'status' => 'APPROVED'
                ]);
                $this->logHistory($bill, 'APPROVED', 'Disetujui oleh Director (Final).');
            }
        }

        return back()->with('success', 'Keputusan berhasil disimpan.');
    }

    // --- 5. FUNGSI LOG HISTORY (PRIVATE) ---
    /**
     * Helper untuk mencatat Log History
     */
    private function logHistory($bill, $action, $note = null)
    {
        \App\Models\History::create([
            'user_id'     => auth()->id(),
            'record_type' => \App\Models\BillRequest::class, // Simpan nama Model
            'record_id'   => $bill->id,                      // Simpan ID Tagihan
            'action'      => $action,                        // Contoh: "Membuat Tagihan"
            'note'        => $note                           // Catatan tambahan (opsional)
        ]);
    }




    // Contoh di method approve/reject
    public function decide(Request $request, $id)
    {
        $bill = BillRequest::findOrFail($id);

        if ($request->action == 'APPROVED') {
            $bill->update(['status' => 'APPROVED']);

            // CATAT HISTORY
            $this->logHistory($bill, 'Menyetujui Tagihan', 'Disetujui oleh Manager/Director');

        } elseif ($request->action == 'REJECTED') {
            $bill->update(['status' => 'REJECTED', 'rejection_reason' => $request->reason]);

            // CATAT HISTORY
            $this->logHistory($bill, 'Menolak Tagihan', 'Alasan: ' . $request->reason);
        }

        return back();
    }

    // public function reject(Request $request, $slug)
    // {
    //     $request->validate(['rejection_reason' => 'required|string|min:5']);
    //     \DB::beginTransaction();
    //     try {
    //         // 🔥 PERBAIKAN: Cari berdasarkan bill_number
    //         $bill = \App\Models\BillRequest::with('status')->where('bill_number', $slug)->firstOrFail();

    //         // ... (Sisa kode reject tetap sama seperti sebelumnya) ...
    //         if ($bill->status && $bill->status->slug !== 'pending') {
    //             return back()->with('error', 'Tagihan ini sudah diproses sebelumnya.');
    //         }

    //         $currentApproval = \App\Models\DocumentApproval::with('role')
    //             ->where('document_id', $bill->id)
    //             ->where('document_type', get_class($bill))
    //             ->where('status', 'PENDING')
    //             ->orderBy('step_order', 'asc')
    //             ->first();

    //         $approverRoleName = $currentApproval && $currentApproval->role ? $currentApproval->role->name : 'Atasan';

    //         if ($currentApproval) {
    //             $currentApproval->update([
    //                 'status'      => 'REJECTED',
    //                 'approved_by' => auth()->id(),
    //                 'approved_at' => now()
    //             ]);
    //         }

    //         $bill->status_id = $this->getStatusId('rejected');
    //         $bill->rejection_reason = $request->rejection_reason;
    //         $bill->current_approval_level = 0;
    //         $bill->save();

    //         $this->logHistory($bill, 'Ditolak', "Menolak Tagihan ({$approverRoleName}). Alasan: {$request->rejection_reason}");

    //         \DB::commit();
    //         return back()->with('error', 'Tagihan OPEX telah ditolak dan dikembalikan.');

    //     } catch (\Exception $e) {
    //         \DB::rollback();
    //         return back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    //     }
    // }



    public function print($id)
    {
        $bill = \App\Models\BillRequest::with(['user', 'company', 'items', 'media'])->findOrFail($id);

        // Kita gunakan view khusus print
        return view('bills.print', compact('bill'));
    }


    public function markAsPaid($id)
    {
        $bill = \App\Models\BillRequest::findOrFail($id);

        // 1. Validasi: Hanya status APPROVED yang bisa dibayar
        if ($bill->status !== 'APPROVED') {
            return back()->with('error', 'Tagihan harus disetujui terlebih dahulu sebelum ditandai lunas.');
        }

        DB::beginTransaction();
        try {
            // 2. Update Status menjadi PAID
            $updateData = ['status' => 'PAID'];

            // 3. Logic Recurring: Jika tagihan rutin, siapkan jadwal berikutnya
            if ($bill->is_recurring && $bill->type == 'ROUTINE') {
                // Hitung tanggal generate berikutnya (berdasarkan recurring_period bulan)
                $updateData['next_generation_date'] = now()->addMonths($bill->recurring_period);
            }

            $bill->update($updateData);

            // 4. Catat ke Audit Trail (Tabel Histories)
            $this->logHistory($bill, 'Menandai Pembayaran Lunas', 'Finance telah mengonfirmasi pembayaran selesai.');

            DB::commit();
            return redirect()->route('bills.show', $bill->id)->with('success', 'Tagihan berhasil ditandai sebagai PAID.');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal memproses pembayaran: ' . $e->getMessage());
        }
    }


    // =========================================================================
    // 3. STORE (SIMPAN DATA BARU + GENERATE WORKFLOW)
    // =========================================================================
    public function store(Request $request)
    {
        $request->validate([
            'paid_by_company_id' => 'required|exists:companies,id',
            'currency_id'        => 'required|exists:currencies,id',
            'bill_date'          => 'required|date',
            'due_date'           => 'required|date|after_or_equal:bill_date',
            'vendor_name'        => 'required|string|max:255',
            'items'              => 'required|array|min:1',
            'items.*.name'       => 'required|string',
            'items.*.qty'        => 'required|numeric|min:1',
            'items.*.price'      => 'required|numeric|min:0',
        ]);

        \DB::beginTransaction();
        try {
            $company = \App\Models\Company::find($request->paid_by_company_id);
            $companyCode = $company ? ($company->code ?? 'GEN') : 'GEN';
            $monthYear = \Carbon\Carbon::parse($request->bill_date)->format('Y/m');

            $prefix = "BILL/OPX/{$companyCode}/{$monthYear}/";
            $lastBill = \App\Models\BillRequest::where('bill_number', 'like', $prefix . '%')
                                               ->lockForUpdate()
                                               ->orderBy('id', 'desc')->first();

            $newNumber = $lastBill ? ((int) substr($lastBill->bill_number, -4) + 1) : 1;
            $billNumber = $prefix . sprintf('%04d', $newNumber);
            $currency = \App\Models\Currency::find($request->currency_id)->code ?? 'IDR';

            $totalSubtotal = 0; $totalItemDisc = 0; $totalTax = 0; $totalCharge = 0; $totalExtDisc = 0;

            $bill = \App\Models\BillRequest::create([
                'bill_number'        => $billNumber,
                'title'              => 'Tagihan Opex - ' . $request->vendor_name,
                'user_id'            => auth()->id(),
                'company_id'         => $request->paid_by_company_id,
                'type'               => 'OPEX',
                'vendor_name'        => $request->vendor_name,
                'description'        => $request->note,
                'invoice_date'       => $request->bill_date,
                'due_date'           => $request->due_date,
                'currency'           => $currency,
                'status_id'          => $this->getStatusId('pending'),
                'subtotal'           => 0, 'total_discount' => 0, 'total_tax' => 0, 'total_charge' => 0, 'amount' => 0,
                'is_recurring'       => $request->is_recurring == '1',
                'recurring_interval' => $request->is_recurring == '1' ? (int)$request->recurring_interval : null,
                'recurring_period'   => $request->is_recurring == '1' ? $request->recurring_period : null,
                'next_generation_date'=> $request->is_recurring == '1' ? \Carbon\Carbon::parse($request->bill_date)->add((int)$request->recurring_interval, $request->recurring_period) : null,
            ]);

            foreach ($request->items as $item) {
                $qty = (float)$item['qty']; $price = (float)$item['price']; $gross = $qty * $price;
                $discVal = (float)($item['discount_value'] ?? 0); $discType = $item['discount_type'] ?? 'fixed';
                $discAmount = ($discType == 'percent') ? ($gross * $discVal / 100) : $discVal;
                $dpp = $gross - $discAmount;
                $taxPercent = 0;
                if (!empty($item['tax_id']) && $taxData = \App\Models\Tax::find($item['tax_id'])) $taxPercent = $taxData->percent;
                $taxAmount = $dpp * ($taxPercent / 100);

                $bill->items()->create([
                    'name' => $item['name'], 'description' => $item['description'] ?? null, 'qty' => $qty, 'price' => $price, 'amount' => $dpp + $taxAmount,
                    'discount_type' => $discType, 'discount_value' => $discVal, 'discount_amount' => $discAmount,
                    'tax_id' => $item['tax_id'] ?? null, 'tax_percent_snapshot' => $taxPercent, 'tax_amount' => $taxAmount, 'subtotal' => $gross,
                ]);

                $totalSubtotal += $gross; $totalItemDisc += $discAmount; $totalTax += $taxAmount;
            }

            if ($request->has('charges')) {
                foreach ($request->charges as $charge) {
                    if (!empty($charge['charge_type_id']) && $charge['amount'] > 0) {
                        $bill->charges()->create(['charge_type_id' => $charge['charge_type_id'], 'amount' => $charge['amount'], 'note' => $charge['note'] ?? null]);
                        $totalCharge += $charge['amount'];
                    }
                }
            }

            if ($request->has('discounts')) {
                foreach ($request->discounts as $discount) {
                    if (!empty($discount['discount_type_id']) && $discount['amount'] > 0) {
                        $bill->discounts()->create(['discount_type_id' => $discount['discount_type_id'], 'amount' => $discount['amount'], 'note' => $discount['note'] ?? null]);
                        $totalExtDisc += $discount['amount'];
                    }
                }
            }

            $grandTotal = max(0, ($totalSubtotal - $totalItemDisc) + $totalTax + $totalCharge - $totalExtDisc);
            $bill->update(['subtotal' => $totalSubtotal, 'total_discount' => $totalItemDisc + $totalExtDisc, 'total_tax' => $totalTax, 'total_charge' => $totalCharge, 'amount' => $grandTotal]);

            // 9. UPLOAD LAMPIRAN (MENGGUNAKAN KONSEP PR/PO)
            if ($request->hasFile('attachments')) {
                // Ambil path dari system_settings, fallback ke 'attachments/bills' jika belum diisi
                // Sesuaikan kuncinya menjadi path_bills_opex dan fallback-nya menjadi attachments/opex
                // Sesuaikan kuncinya menjadi path_bills_opex dan fallback-nya menjadi attachments/opex
                $basePath = \DB::table('system_settings')->where('setting_key', 'path_bills_opex')->value('setting_value') ?: 'attachments/opex';
                $safeBillNumber = str_replace(['/', '\\'], '-', $bill->bill_number);
                $storagePath = $basePath . '/' . $safeBillNumber;

                foreach ($request->file('attachments') as $file) {
                    $originalName = $file->getClientOriginalName();
                    // Simpan ke storage/app/public/attachments/...
                    $path = $file->storeAs($storagePath, time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName), 'public');

                    // Simpan ke tabel khusus lampiran
                    \DB::table('bill_attachments')->insert([
                        'bill_request_id' => $bill->id,
                        'file_name'       => $originalName,
                        'file_path'       => str_replace('\\', '/', $path),
                        'created_at'      => now(),
                        'updated_at'      => now()
                    ]);
                }
            } // <-- PERBAIKAN: Kurung kurawal penutup untuk blok if upload lampiran diletakkan di sini

            $this->logHistory($bill, 'CREATED', "Membuat tagihan baru No: {$billNumber}");

            // 🔥 SEEDING WORKFLOW AUTOMATION 🔥
            $workflow = \DB::table('approval_workflows')->whereIn('document_type', ['OPEX', 'App\Models\BillRequest'])->where('is_active', 1)->first();
            if ($workflow) {
                $steps = \DB::table('approval_workflow_steps')->where('approval_workflow_id', $workflow->id)->orderBy('step_order', 'asc')->get();
                foreach ($steps as $step) {
                    \App\Models\DocumentApproval::create([
                        'document_id' => $bill->id, 'document_type' => 'App\Models\BillRequest',
                        'role_id' => $step->role_id, 'step_order' => $step->step_order, 'status' => 'PENDING'
                    ]);
                }
            }

            \DB::commit();
            return redirect()->route('bills.index')->with('success', "Tagihan Opex berhasil disimpan! Nomor: {$billNumber}");
        } catch (\Exception $e) {
            \DB::rollback();
            return back()->withInput()->with('error', 'Gagal menyimpan tagihan: ' . $e->getMessage());
        }
    }


    // =========================================================================
    // 4. SHOW (DETAIL BERBASIS SLUG NOMOR DOKUMEN)
    // =========================================================================
    public function show($slug)
    {
        $bill = \App\Models\BillRequest::with([
            'status', 'items', 'company', 'user', 'histories.user', 'charges.chargeType', 'discounts.discountType'
        ])->where('bill_number', $slug)->firstOrFail();

        // 🔥 TARIK DATA LAMPIRAN DARI TABEL BARU 🔥
        $attachments = \DB::table('bill_attachments')->where('bill_request_id', $bill->id)->get();

        // 🔥 PASTIKAN $attachments MASUK KE DALAM compact() 🔥
        return view('bills.show', compact('bill', 'attachments'));
    }

    // =========================================================================
    // 5. EDIT (FORM EDIT BERBASIS SLUG)
    // =========================================================================
    public function edit($slug)
    {
        $bill = \App\Models\BillRequest::with(['items', 'charges', 'discounts', 'status'])->where('bill_number', $slug)->firstOrFail();

        if ($bill->status && !in_array($bill->status->slug, ['pending', 'draft'])) {
            return back()->with('error', 'Tagihan yang sudah disetujui atau diproses tidak dapat diedit!');
        }

        $companies  = \App\Models\Company::all();
        $taxes      = \App\Models\Tax::where('is_active', true)->orderBy('name')->get();
        $currencies = \App\Models\Currency::where('is_active', true)->orderBy('name')->get();
        $vendors    = \App\Models\Vendor::orderBy('name')->get();
        $chargeTypes = \App\Models\ChargeType::where('is_active', true)->orderBy('name')->get();
        $discountTypes = \App\Models\DiscountType::where('is_active', true)->orderBy('name')->get();

        $opexItems  = \App\Models\Item::whereNotIn('item_type_code', ['AST', 'STK'])->orWhereNull('item_type_code')->orderBy('name')->get();

        // 🔥 TARIK DATA LAMPIRAN DARI TABEL BARU 🔥
        $attachments = \DB::table('bill_attachments')->where('bill_request_id', $bill->id)->get();

        // 🔥 PASTIKAN $attachments MASUK KE DALAM compact() 🔥
        return view('bills.edit', compact('bill', 'companies', 'taxes', 'currencies', 'vendors', 'opexItems', 'chargeTypes', 'discountTypes', 'attachments'));
    }

    // =========================================================================
    // 6. UPDATE (SIMPAN REVISI BERBASIS SLUG)
    // =========================================================================
    public function update(Request $request, $slug)
    {
        $request->validate([
            'paid_by_company_id' => 'required|exists:companies,id',
            'currency_id'        => 'required|exists:currencies,id',
            'bill_date'          => 'required|date',
            'due_date'           => 'required|date|after_or_equal:bill_date',
            'vendor_name'        => 'required|string|max:255',
            'items'              => 'required|array|min:1',
        ]);

        DB::beginTransaction();
        try {
            $bill = \App\Models\BillRequest::where('bill_number', $slug)->firstOrFail();

            if ($bill->status && in_array($bill->status->slug, ['paid', 'partial'])) {
                return back()->with('error', 'Gagal! Tagihan ini sudah memiliki riwayat pembayaran.');
            }

            $currency = \App\Models\Currency::find($request->currency_id)->code ?? 'IDR';
            $bill->update([
                'company_id' => $request->paid_by_company_id, 'vendor_name' => $request->vendor_name, 'description' => $request->note,
                'invoice_date' => $request->bill_date, 'due_date' => $request->due_date, 'currency' => $currency,
                'is_recurring' => $request->is_recurring == '1',
                'recurring_interval' => $request->is_recurring == '1' ? (int)$request->recurring_interval : null,
                'recurring_period' => $request->is_recurring == '1' ? $request->recurring_period : null,
            ]);

            $bill->items()->delete(); $bill->charges()->delete(); $bill->discounts()->delete();
            $totalSubtotal = 0; $totalItemDisc = 0; $totalTax = 0; $totalCharge = 0; $totalExtDisc = 0;

            foreach ($request->items as $item) {
                $qty = (float)$item['qty']; $price = (float)$item['price']; $gross = $qty * $price;
                $discVal = (float)($item['discount_value'] ?? 0); $discType = $item['discount_type'] ?? 'fixed';
                $discAmount = ($discType == 'percent') ? ($gross * $discVal / 100) : $discVal;
                $dpp = $gross - $discAmount;
                $taxPercent = 0;
                if (!empty($item['tax_id']) && $taxData = \App\Models\Tax::find($item['tax_id'])) $taxPercent = (float)$taxData->percent;
                $taxAmount = $dpp * ($taxPercent / 100);

                $bill->items()->create([
                    'name' => $item['name'], 'description' => $item['description'], 'qty' => $qty, 'price' => $price, 'amount' => $dpp + $taxAmount,
                    'discount_type' => $discType, 'discount_value' => $discVal, 'discount_amount' => $discAmount,
                    'tax_id' => $item['tax_id'] ?? null, 'tax_percent_snapshot' => $taxPercent, 'tax_amount' => $taxAmount, 'subtotal' => $gross,
                ]);
                $totalSubtotal += $gross; $totalItemDisc += $discAmount; $totalTax += $taxAmount;
            }

            if ($request->has('charges')) {
                foreach ($request->charges as $charge) {
                    if (!empty($charge['charge_type_id']) && $charge['amount'] > 0) {
                        $bill->charges()->create(['charge_type_id' => $charge['charge_type_id'], 'amount' => $charge['amount'], 'note' => $charge['note'] ?? null]);
                        $totalCharge += $charge['amount'];
                    }
                }
            }

            if ($request->has('discounts')) {
                foreach ($request->discounts as $discount) {
                    if (!empty($discount['discount_type_id']) && $discount['amount'] > 0) {
                        $bill->discounts()->create(['discount_type_id' => $discount['discount_type_id'], 'amount' => $discount['amount'], 'note' => $discount['note'] ?? null]);
                        $totalExtDisc += $discount['amount'];
                    }
                }
            }

            $grandTotal = max(0, ($totalSubtotal - $totalItemDisc) + $totalTax + $totalCharge - $totalExtDisc);
            $bill->update(['subtotal' => $totalSubtotal, 'total_discount' => $totalItemDisc + $totalExtDisc, 'total_tax' => $totalTax, 'total_charge' => $totalCharge, 'amount' => $grandTotal]);

            // 🔥 PERBAIKAN: UPLOAD LAMPIRAN BARU (MENGGUNAKAN KONSEP MANDIRI) 🔥
            if ($request->hasFile('attachments')) {
                $basePath = \DB::table('system_settings')->where('setting_key', 'path_bills_opex')->value('setting_value') ?: 'attachments/opex';
                $safeBillNumber = str_replace(['/', '\\'], '-', $bill->bill_number);
                $storagePath = $basePath . '/' . $safeBillNumber;

                foreach ($request->file('attachments') as $file) {
                    $originalName = $file->getClientOriginalName();
                    // Simpan ke storage/app/public/attachments/opex/...
                    $path = $file->storeAs($storagePath, time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName), 'public');

                    // Masukkan data ke tabel bill_attachments
                    \DB::table('bill_attachments')->insert([
                        'bill_request_id' => $bill->id,
                        'file_name'       => $originalName,
                        'file_path'       => str_replace('\\', '/', $path),
                        'created_at'      => now(),
                        'updated_at'      => now()
                    ]);
                }
            }

            // 10. HAPUS LAMPIRAN LAMA (Tetap sama)
            if ($request->has('delete_media') && is_array($request->delete_media)) {
                $attachmentsToDelete = \DB::table('bill_attachments')->whereIn('id', $request->delete_media)->get();
                foreach ($attachmentsToDelete as $att) {
                    if (\Storage::disk('public')->exists($att->file_path)) {
                        \Storage::disk('public')->delete($att->file_path);
                    }
                    \DB::table('bill_attachments')->where('id', $att->id)->delete();
                }
            }

            // 10. HAPUS LAMPIRAN LAMA
            if ($request->has('delete_media') && is_array($request->delete_media)) {
                $attachmentsToDelete = \DB::table('bill_attachments')->whereIn('id', $request->delete_media)->get();
                foreach ($attachmentsToDelete as $att) {
                    if (\Storage::disk('public')->exists($att->file_path)) {
                        \Storage::disk('public')->delete($att->file_path);
                    }
                    \DB::table('bill_attachments')->where('id', $att->id)->delete();
                }
            }

            $this->logHistory($bill, 'UPDATED', "Merevisi dokumen tagihan. Total Baru: {$currency} " . number_format($grandTotal, 0, ',', '.'));

            // Reset Antrean Approval
            \App\Models\DocumentApproval::where('document_id', $bill->id)->where('document_type', 'App\Models\BillRequest')->delete();
            $workflow = \DB::table('approval_workflows')->whereIn('document_type', ['OPEX', 'App\Models\BillRequest'])->where('is_active', 1)->first();
            if ($workflow) {
                $steps = \DB::table('approval_workflow_steps')->where('approval_workflow_id', $workflow->id)->orderBy('step_order', 'asc')->get();
                foreach ($steps as $step) {
                    \App\Models\DocumentApproval::create([
                        'document_id' => $bill->id, 'document_type' => 'App\Models\BillRequest',
                        'role_id' => $step->role_id, 'step_order' => $step->step_order, 'status' => 'PENDING'
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('bills.show', $bill->bill_number)->with('success', "Tagihan Opex berhasil diperbarui!");
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()->with('error', 'Gagal update tagihan: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // 7. APPROVE (WORKFLOW DINAMIS BERBASIS SLUG + ANTI EMBARGO BYPASS)
    // =========================================================================
    public function approve($slug)
    {
        DB::beginTransaction();
        try {
            $bill = \App\Models\BillRequest::with('status')->where('bill_number', $slug)->firstOrFail();

            if ($bill->status && $bill->status->slug !== 'pending' && $bill->status->slug !== 'partial_approved') {
                return back()->with('error', 'Tagihan ini sudah diproses sebelumnya.');
            }

            $currentApproval = \App\Models\DocumentApproval::with('role')
                ->where('document_id', $bill->id)
                ->whereIn('document_type', ['App\Models\BillRequest', 'OPEX'])
                ->where('status', 'PENDING')
                ->orderBy('step_order', 'asc')->first();

            // 🔥 SISTEM BYPASS GUDANG DEWA FOR SUPER ADMIN 🔥
            if (!$currentApproval) {
                $isSuperAdmin = auth()->id() === 1 || auth()->user()->hasRole(['Super Administrator', 'Super Admin']);
                if (!$isSuperAdmin) {
                    return back()->with('error', 'Tidak ada antrean persetujuan yang aktif untuk Anda.');
                }
                $bill->update(['status_id' => $this->getStatusId('approved'), 'current_approval_level' => 99]);
                $this->logHistory($bill, 'APPROVED', 'Disetujui Langsung secara mutlak oleh Super Admin (Bypass Mode).');
                DB::commit();
                return back()->with('success', 'Hore! Tagihan OPEX berhasil disetujui secara FINAL (Bypass)!');
            }

            $approverRoleName = $currentApproval->role ? $currentApproval->role->name : 'Atasan';
            $currentApproval->update(['status' => 'APPROVED', 'approved_by' => auth()->id(), 'approved_at' => now()]);
            $bill->update(['current_approval_level' => $currentApproval->step_order]);

            $nextApproval = \App\Models\DocumentApproval::with('role')
                ->where('document_id', $bill->id)
                ->whereIn('document_type', ['App\Models\BillRequest', 'OPEX'])
                ->where('status', 'PENDING')
                ->orderBy('step_order', 'asc')->first();

            $actionText = 'Disetujui (' . strtoupper($approverRoleName) . ')';
            $catatan = "Tagihan disetujui pada tahap ini.\n";

            if ($nextApproval) {
                $nextRoleName = $nextApproval->role ? $nextApproval->role->name : 'Atasan Berikutnya';
                $bill->update(['status_id' => $this->getStatusId('pending')]); // Tetap pending/menunggu
                $catatan .= "Diteruskan ke: **" . strtoupper($nextRoleName) . "**\n";
                $successMsg = "Disetujui! Dokumen telah diteruskan ke {$nextRoleName}.";
            } else {
                $bill->update(['status_id' => $this->getStatusId('approved')]);
                $actionText = 'Disetujui Final';
                $catatan .= "Persetujuan Matriks telah SELESAI. Siap dibayarkan.\n";
                $successMsg = "Hore! Tagihan OPEX telah disetujui secara FINAL!";
            }

            $this->logHistory($bill, $actionText, $catatan);
            DB::commit();
            return back()->with('success', $successMsg);
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // 8. REJECT & 9. PRINT & 10. DESTROY ATTACHMENT
    // =========================================================================
    public function reject(Request $request, $slug)
    {
        $request->validate(['rejection_reason' => 'required|string|min:5']);
        DB::beginTransaction();
        try {
            $bill = \App\Models\BillRequest::with('status')->where('bill_number', $slug)->firstOrFail();
            $currentApproval = \App\Models\DocumentApproval::where('document_id', $bill->id)->where('status', 'PENDING')->first();
            if ($currentApproval) $currentApproval->update(['status' => 'REJECTED', 'approved_by' => auth()->id(), 'approved_at' => now()]);

            $bill->update(['status_id' => $this->getStatusId('rejected'), 'rejection_reason' => $request->rejection_reason, 'current_approval_level' => 0]);
            $this->logHistory($bill, 'REJECTED', "Ditolak oleh " . auth()->user()->name . ". Alasan: {$request->rejection_reason}");
            DB::commit();
            return back()->with('error', 'Tagihan OPEX telah ditolak.');
        } catch (\Exception $e) { DB::rollback(); return back()->with('error', $e->getMessage()); }
    }

    public function printPdf($slug)
    {
        $bill = \App\Models\BillRequest::with(['items', 'company', 'user', 'charges.chargeType', 'discounts.discountType'])->where('bill_number', $slug)->firstOrFail();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('bills.print_pdf', compact('bill'))->setPaper('A4', 'portrait');
        return $pdf->stream('Tagihan_Opex_' . str_replace('/', '_', $bill->bill_number) . '.pdf');
    }

    public function destroyAttachment($slug, $attachmentId)
    {
        try {
            $attachment = \DB::table('bill_attachments')->where('id', $attachmentId)->first();
            if ($attachment) {
                // Hapus file fisik dari folder
                if (\Storage::disk('public')->exists($attachment->file_path)) {
                    \Storage::disk('public')->delete($attachment->file_path);
                }
                // Hapus dari database
                \DB::table('bill_attachments')->where('id', $attachmentId)->delete();
            }
            return back()->with('success', 'File lampiran berhasil dihapus secara permanen!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus lampiran: ' . $e->getMessage());
        }
    }


    public function destroy($slug)
    {
        DB::beginTransaction();
        try {
            $bill = BillRequest::where('bill_number', $slug)->firstOrFail();
            $bill->items()->delete(); $bill->histories()->delete(); $bill->clearMediaCollection('bill_attachments');
            $bill->delete(); DB::commit();
            return redirect()->route('bills.index')->with('success', 'Tagihan berhasil dihapus.');
        } catch (\Exception $e) { DB::rollback(); return back()->with('error', $e->getMessage()); }
    }


    // =========================================================================
    // 11. SKENARIO 1: UPLOAD LAMPIRAN SUSULAN (LATE ATTACHMENT)
    // =========================================================================
    public function addLateAttachment(Request $request, $slug)
    {
        $request->validate([
            'attachments'   => 'required|array',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        DB::beginTransaction();
        try {
            $bill = \App\Models\BillRequest::where('bill_number', $slug)->firstOrFail();

            // Ambil path penyimpanan yang sama seperti fungsi Store/Update
            $basePath = \DB::table('system_settings')->where('setting_key', 'path_bills_opex')->value('setting_value') ?: 'attachments/opex';
            $safeBillNumber = str_replace(['/', '\\'], '-', $bill->bill_number);
            $storagePath = $basePath . '/' . $safeBillNumber;

            foreach ($request->file('attachments') as $file) {
                $originalName = $file->getClientOriginalName();
                $path = $file->storeAs($storagePath, time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName), 'public');

                \DB::table('bill_attachments')->insert([
                    'bill_request_id' => $bill->id,
                    'file_name'       => $originalName,
                    'file_path'       => str_replace('\\', '/', $path),
                    'created_at'      => now(),
                    'updated_at'      => now()
                ]);
            }

            // Catat di Audit Trail
            $this->logHistory($bill, 'UPLOAD SUSULAN', 'Staf menambahkan dokumen/bukti lampiran susulan setelah tagihan berstatus Lunas.');

            DB::commit();
            return back()->with('success', 'Lampiran bukti susulan berhasil ditambahkan!');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal mengunggah lampiran: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // 12. SKENARIO 2: BATALKAN PEMBAYARAN (VOID PAYMENT)
    // =========================================================================
    public function voidPayment(Request $request, $slug)
    {
        // Gembok khusus Super Admin & Manager
        $userRoles = auth()->user()->getRoleNames()->toArray();
        $canVoid = in_array('Super Administrator', $userRoles) || in_array('Super Admin', $userRoles) || in_array('manager', array_map('strtolower', $userRoles)) || auth()->id() === 1;

        if (!$canVoid) {
            abort(403, 'Anda tidak memiliki wewenang untuk membatalkan pembayaran ini.');
        }

        $request->validate([
            'void_reason' => 'required|string|min:10'
        ]);

        DB::beginTransaction();
        try {
            $bill = \App\Models\BillRequest::where('bill_number', $slug)->firstOrFail();

            if (strtoupper($bill->status) !== 'PAID' && optional($bill->status)->slug !== 'paid') {
                return back()->with('error', 'Hanya tagihan berstatus LUNAS (PAID) yang bisa dibatalkan.');
            }

            // Kembalikan statusnya ke APPROVED
            $bill->update([
                'status'    => 'APPROVED',
                'status_id' => $this->getStatusId('approved')
            ]);

            // Catat di Audit Trail secara detail
            $this->logHistory($bill, 'PEMBAYARAN DIBATALKAN (VOID)', 'Pembayaran telah ditarik kembali/dibatalkan. Alasan: ' . $request->void_reason);

            DB::commit();
            return redirect()->route('bills.show', $bill->bill_number)->with('success', 'Pembayaran berhasil dibatalkan. Tagihan kembali berstatus APPROVED.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal membatalkan pembayaran: ' . $e->getMessage());
        }
    }














}
