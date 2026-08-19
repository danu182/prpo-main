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





    // --- 1. MENAMPILKAN LIST & TAB LANGGANAN ---
    public function index(Request $request)
    {
        $companies = \App\Models\Company::orderBy('name')->get();

        $query = \App\Models\BillRequest::with(['company', 'user', 'status'])->latest();

        // 🔥 LOGIKA TAB: Jika membuka tab 'recurring', filter hanya tagihan berulang yang aktif
        if ($request->get('tab') == 'recurring') {
            $query->where('is_recurring', true);
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('vendor')) {
            $query->where('vendor_name', 'like', '%' . $request->vendor . '%');
        }

        if ($request->filled('status')) {
            $slug = strtolower($request->status);
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





    public function create()
    {
        $companies  = \App\Models\Company::all();
        $taxes      = \App\Models\Tax::where('is_active', true)->orderBy('name')->get();
        $currencies = \App\Models\Currency::where('is_active', true)->orderBy('name')->get();
        $vendors    = \App\Models\Vendor::orderBy('name')->get();
        $opexItems  = \App\Models\Item::where('item_type_code', 'JSA')->orWhereNull('item_type_code')->orderBy('name')->get();
        $chargeTypes = \App\Models\ChargeType::where('is_active', true)->orderBy('name')->get();
        $discountTypes = \App\Models\DiscountType::where('is_active', true)->orderBy('name')->get();

        // 🔥 PERBAIKAN DI SINI: Sesuaikan dengan isi database (App\Models\BillRequest) 🔥
        $customWorkflows = [];
        if (class_exists('\App\Models\ApprovalWorkflow')) {
            $customWorkflows = \App\Models\ApprovalWorkflow::where('document_type', 'App\Models\BillRequest')
                                ->orWhere('document_type', 'OPEX') // Fallback jaga-jaga
                                ->where('is_active', true)
                                ->get();
        }

        return view('bills.create', compact('companies', 'taxes','currencies', 'vendors', 'opexItems', 'chargeTypes', 'discountTypes', 'customWorkflows'));
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
    // 3. STORE (SIMPAN DATA BARU + OVERRIDE WORKFLOW)
    // =========================================================================
    public function store(Request $request)
    {
        $request->validate([
            'paid_by_company_id'    => 'required|exists:companies,id',
            'currency_id'           => 'required|exists:currencies,id',
            'bill_date'             => 'required|date',
            'due_date'              => 'required|date|after_or_equal:bill_date',
            'vendor_name'           => 'required|string|max:255',
            'vendor_invoice_number' => 'nullable|string|max:255',
            'account_number'        => 'nullable|string|max:255', // 🔥 VALIDASI KOLOM BARU 🔥
            'items'                 => 'required|array|min:1',
            'items.*.name'          => 'required|string',
            'items.*.qty'           => 'required|numeric|min:1',
            'items.*.price'         => 'required|numeric|min:0',
        ]);

        \DB::beginTransaction();
        try {
            $company = \App\Models\Company::find($request->paid_by_company_id);
            $companyCode = $company ? ($company->code ?? 'GEN') : 'GEN';
            $monthYear = \Carbon\Carbon::parse($request->bill_date)->format('Y/m');

            $prefix = "BILL/OPX/{$companyCode}/{$monthYear}/";
            $lastBill = \App\Models\BillRequest::where('bill_number', 'like', $prefix . '%')->lockForUpdate()->orderBy('id', 'desc')->first();

            $newNumber = $lastBill ? ((int) substr($lastBill->bill_number, -4) + 1) : 1;
            $billNumber = $prefix . sprintf('%04d', $newNumber);
            $currency = \App\Models\Currency::find($request->currency_id)->code ?? 'IDR';

            $totalSubtotal = 0; $totalItemDisc = 0; $totalTax = 0; $totalCharge = 0; $totalExtDisc = 0;

            // 1. Simpan Data Tagihan Utama
            $bill = \App\Models\BillRequest::create([
                'bill_number'           => $billNumber,
                'title'                 => 'Tagihan Opex - ' . $request->vendor_name,
                'user_id'               => auth()->id(),
                'company_id'            => $request->paid_by_company_id,
                'type'                  => 'OPEX',
                'vendor_name'           => $request->vendor_name,
                'vendor_invoice_number' => $request->vendor_invoice_number,
                'account_number'        => $request->account_number, // 🔥 SIMPAN KOLOM BARU 🔥
                'description'           => $request->note,
                'invoice_date'          => $request->bill_date,
                'due_date'              => $request->due_date,
                'currency'              => $currency,
                'status_id'             => $this->getStatusId('pending'),
                'subtotal'              => 0, 'total_discount' => 0, 'total_tax' => 0, 'total_charge' => 0, 'amount' => 0,
                'is_recurring'          => $request->is_recurring == '1',
                'recurring_interval'    => $request->is_recurring == '1' ? (int)$request->recurring_interval : null,
                'recurring_period'      => $request->is_recurring == '1' ? $request->recurring_period : null,
                'next_generation_date'  => $request->is_recurring == '1' ? \Carbon\Carbon::parse($request->bill_date)->add((int)$request->recurring_interval, $request->recurring_period) : null,
            ]);

            // 2. Simpan Data Item (Baris per Baris)
            foreach ($request->items as $item) {
                $qty = (float)$item['qty'];
                $price = (float)$item['price'];
                $gross = $qty * $price;

                // Diskon
                $discVal = (float)($item['discount_value'] ?? 0);
                $discType = $item['discount_type'] ?? 'fixed';
                $discAmount = ($discType == 'percent') ? ($gross * $discVal / 100) : $discVal;
                $dpp = $gross - $discAmount;

                // 🔥 UPDATE LOGIKA PAJAK (HYBRID) 🔥
                $taxVal = (float)($item['tax_value'] ?? 0);
                $taxType = $item['tax_type'] ?? 'percent';
                $taxId = $item['tax_id'] ?? null;

                // Pastikan jika tipenya manual persentase, kalkulasinya tetap pakai %
                if ($taxId === 'MANUAL_PERCENT') {
                    $taxType = 'percent';
                }

                $taxAmount = ($taxType == 'percent') ? ($dpp * $taxVal / 100) : $taxVal;

                $bill->items()->create([
                    'name'            => $item['name'],
                    'description'     => $item['description'] ?? null,
                    'qty'             => $qty,
                    'price'           => $price,
                    'amount'          => $dpp + $taxAmount,
                    'discount_type'   => $discType,
                    'discount_value'  => $discVal,
                    'discount_amount' => $discAmount,
                    'tax_id'          => is_numeric($taxId) ? $taxId : null, // 🔥 SIMPAN ID PAJAK MASTER 🔥
                    'tax_type'        => $taxType,
                    'tax_value'       => $taxVal,
                    'tax_amount'      => $taxAmount,
                    'subtotal'        => $gross,
                ]);

                $totalSubtotal += $gross; $totalItemDisc += $discAmount; $totalTax += $taxAmount;
            }

            // 3. Simpan Biaya Ekstra
            if ($request->has('charges')) {
                foreach ($request->charges as $charge) {
                    if (!empty($charge['charge_type_id']) && $charge['amount'] > 0) {
                        $bill->charges()->create(['charge_type_id' => $charge['charge_type_id'], 'amount' => $charge['amount'], 'note' => $charge['note'] ?? null]);
                        $totalCharge += $charge['amount'];
                    }
                }
            }

            // 4. Simpan Potongan Ekstra
            if ($request->has('discounts')) {
                foreach ($request->discounts as $discount) {
                    if (!empty($discount['discount_type_id']) && $discount['amount'] > 0) {
                        $bill->discounts()->create(['discount_type_id' => $discount['discount_type_id'], 'amount' => $discount['amount'], 'note' => $discount['note'] ?? null]);
                        $totalExtDisc += $discount['amount'];
                    }
                }
            }

            // 5. Kalkulasi Akhir Grand Total
            $grandTotal = max(0, ($totalSubtotal - $totalItemDisc) + $totalTax + $totalCharge - $totalExtDisc);
            $bill->update(['subtotal' => $totalSubtotal, 'total_discount' => $totalItemDisc + $totalExtDisc, 'total_tax' => $totalTax, 'total_charge' => $totalCharge, 'amount' => $grandTotal]);

            // 6. Upload Lampiran Jika Ada
            if ($request->hasFile('attachments')) {
                $basePath = \DB::table('system_settings')->where('setting_key', 'path_bills_opex')->value('setting_value') ?: 'attachments/opex';
                $safeBillNumber = str_replace(['/', '\\'], '-', $bill->bill_number);
                $storagePath = $basePath . '/' . $safeBillNumber;

                foreach ($request->file('attachments') as $file) {
                    $originalName = $file->getClientOriginalName();
                    $path = $file->storeAs($storagePath, time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName), 'public');
                    \DB::table('bill_attachments')->insert([
                        'bill_request_id' => $bill->id, 'file_name' => $originalName, 'file_path' => str_replace('\\', '/', $path),
                        'created_at' => now(), 'updated_at' => now()
                    ]);
                }
            }

            // Catat log dokumen dibuat
            $this->logHistory($bill, 'CREATED', "Membuat tagihan baru No: {$billNumber}");

            // ====================================================================
            // 🔥 7. LOGIKA OVERRIDE WORKFLOW (JALUR TIKUS / CUSTOM ROUTE) 🔥
            // ====================================================================
            $customWorkflowId = $request->input('custom_workflow_id');
            $needsApproval = false;

            if ($customWorkflowId) {
                // A. JIKA USER MEMILIH JALUR KHUSUS DI DROPDOWN
                // Sistem akan mem-bypass ApprovalService standar departemen
                $workflow = \App\Models\ApprovalWorkflow::with('steps')->find($customWorkflowId);

                if ($workflow && $workflow->steps->count() > 0) {
                    foreach ($workflow->steps as $step) {
                        // 🔥 AMBIL DEPARTEMEN DARI MATRIKS 🔥
                        $targetDept = $step->target_department_id ?? $step->department_id ?? null;

                        \App\Models\DocumentApproval::create([
                            'document_id'          => $bill->id,
                            'document_type'        => get_class($bill),
                            'role_id'              => $step->role_id,
                            'target_department_id' => $targetDept, // 🔥 SIMPAN KE DATABASE 🔥
                            'step_order'           => $step->step_order,
                            'status'               => 'PENDING'
                        ]);
                    }
                    $needsApproval = true;
                    // Logika di fungsi store sebelumnya:
                    $this->logHistory($bill, 'SYSTEM', "Menggunakan Rute Persetujuan Khusus: " . $workflow->name);
                } // 🔥 KURUNG KURAWAL INI YANG TERTINGGAL SEBELUMNYA 🔥
            } else {
                // B. JIKA DROPDOWN DIKOSONGKAN (Kembali ke Jalan Tol Utama / Default Departemen)
                // Memakai service otomatis yang sudah Komandan buat sebelumnya
                $needsApproval = \App\Services\ApprovalService::generateWorkflow($bill);
                if ($needsApproval) {
                    $this->logHistory($bill, 'SYSTEM', "Rute persetujuan standar (Departemen) berhasil di-generate.");
                }
            }

            // 8. Update status terakhir berdasarkan keberadaan matriks approval
            if ($needsApproval) {
                $bill->update(['status_id' => $this->getStatusId('pending') ?? 1]);
            } else {
                // Jika ternyata kosong (baik khusus maupun standar tidak ada), langsung disetujui
                $bill->update(['status_id' => $this->getStatusId('approved') ?? 3]);
                $this->logHistory($bill, 'APPROVED', "Auto-Approved karena tidak ada aturan/matriks persetujuan aktif.");
            }

            \DB::commit();
            return redirect()->route('bills.index')->with('success', "Tagihan Opex berhasil disimpan! Nomor: {$billNumber}");

        } catch (\Exception $e) {
            \DB::rollback();
            return back()->withInput()->with('error', 'Gagal menyimpan tagihan: ' . $e->getMessage());
        }
    }
    // =========================================================================
    // 5. EDIT (FORM EDIT BERBASIS SLUG)
    // =========================================================================
    public function edit($slug)
    {
        // 1. Tarik Data Tagihan Utama beserta Relasinya
        $bill = \App\Models\BillRequest::with(['items', 'charges', 'discounts', 'status'])->where('bill_number', $slug)->firstOrFail();

        // Cek Status (Hanya Pending/Draft yang boleh diedit)
        if ($bill->status && !in_array($bill->status->slug, ['pending', 'draft'])) {
            return back()->with('error', 'Tagihan yang sudah disetujui atau diproses tidak dapat diedit!');
        }

        // 2. Tarik Master Data
        $companies     = \App\Models\Company::all();
        $taxes         = \App\Models\Tax::where('is_active', true)->orderBy('name')->get(); // 🔥 MASTER PAJAK 🔥
        $currencies    = \App\Models\Currency::where('is_active', true)->orderBy('name')->get();
        $vendors       = \App\Models\Vendor::orderBy('name')->get();
        $chargeTypes   = \App\Models\ChargeType::where('is_active', true)->orderBy('name')->get();
        $discountTypes = \App\Models\DiscountType::where('is_active', true)->orderBy('name')->get();

        $opexItems     = \App\Models\Item::whereNotIn('item_type_code', ['AST', 'STK'])->orWhereNull('item_type_code')->orderBy('name')->get();

        // 3. Tarik Lampiran
        $attachments = \DB::table('bill_attachments')->where('bill_request_id', $bill->id)->get();

        // =========================================================================
        // 🔥 LOGIKA DETEKTIF MATRIKS (WORKFLOW) 🔥
        // =========================================================================
        $customWorkflows = [];
        $selectedWorkflowId = null;

        if (class_exists('\App\Models\ApprovalWorkflow')) {
            $customWorkflows = \App\Models\ApprovalWorkflow::with('steps')
                ->where('is_active', true)
                ->where(function($q) {
                    $q->where('document_type', 'like', '%BillRequest%')
                      ->orWhere('document_type', 'like', '%OPEX%')
                      ->orWhere('document_type', 'like', '%bill%');
                })->get();

            // A. Lacak dari History Utama
            $historyLog = \App\Models\History::where('record_id', $bill->id)
                ->whereIn('record_type', [get_class($bill), 'App\Models\BillRequest', 'OPEX'])
                ->where('action', 'SYSTEM')
                ->where('note', 'like', 'Menggunakan Rute Persetujuan Khusus:%')
                ->orderBy('id', 'desc')->first();

            // B. Lacak Silsilah Jika ini Tagihan Recurring (Berulang)
            if (!$historyLog) {
                $recurringLog = \App\Models\History::where('record_id', $bill->id)
                    ->whereIn('record_type', [get_class($bill), 'App\Models\BillRequest', 'OPEX'])
                    ->where('action', 'CREATED')
                    ->where('note', 'like', '%Recurring dari:%')->first();

                if ($recurringLog) {
                    preg_match('/Recurring dari:\s*([^)]+)/', $recurringLog->note, $matches);
                    if (!empty($matches[1])) {
                        $parentBill = \App\Models\BillRequest::where('bill_number', trim($matches[1]))->first();
                        if ($parentBill) {
                            $historyLog = \App\Models\History::where('record_id', $parentBill->id)
                                ->whereIn('record_type', [get_class($parentBill), 'App\Models\BillRequest', 'OPEX'])
                                ->where('action', 'SYSTEM')
                                ->where('note', 'like', 'Menggunakan Rute Persetujuan Khusus:%')
                                ->orderBy('id', 'desc')->first();
                        }
                    }
                }
            }

            // Jika ketemu nama workflow-nya
            if ($historyLog) {
                $workflowName = trim(str_replace('Menggunakan Rute Persetujuan Khusus:', '', $historyLog->note));
                $matchedWorkflow = $customWorkflows->where('name', $workflowName)->first();
                if ($matchedWorkflow) {
                    $selectedWorkflowId = $matchedWorkflow->id;
                }
            }

            // C. Fallback: Cocokkan jumlah Step (Jabatan)
            if (!$selectedWorkflowId) {
                $currentApprovals = \App\Models\DocumentApproval::where('document_id', $bill->id)
                    ->whereIn('document_type', [get_class($bill), 'App\Models\BillRequest', 'OPEX'])
                    ->orderBy('step_order', 'asc')->get();

                if ($currentApprovals->count() > 0 && $customWorkflows->count() > 0) {
                    foreach ($customWorkflows as $cw) {
                        $cwSteps = $cw->steps->sortBy('step_order')->values();
                        if ($cwSteps->count() === $currentApprovals->count() && $cwSteps->count() > 0) {
                            $isMatch = true;
                            foreach ($cwSteps as $index => $step) {
                                if ($step->role_id != $currentApprovals[$index]->role_id) {
                                    $isMatch = false; break;
                                }
                            }
                            if ($isMatch) {
                                $selectedWorkflowId = $cw->id; break;
                            }
                        }
                    }
                }
            }
        }

        // 4. Lemparkan semua variabel ke Blade!
        return view('bills.edit', compact(
            'bill', 'companies', 'taxes', 'currencies', 'vendors',
            'opexItems', 'chargeTypes', 'discountTypes', 'attachments',
            'customWorkflows', 'selectedWorkflowId'
        ));
    }



    // =========================================================================
    // 6. UPDATE (SIMPAN REVISI + OVERRIDE WORKFLOW)
    // =========================================================================
    public function update(Request $request, $slug)
    {
        $request->validate([
            'paid_by_company_id'    => 'required|exists:companies,id',
            'currency_id'           => 'required|exists:currencies,id',
            'bill_date'             => 'required|date',
            'due_date'              => 'required|date|after_or_equal:bill_date',
            'vendor_name'           => 'required|string|max:255',
            'vendor_invoice_number' => 'nullable|string|max:255',
            'account_number'        => 'nullable|string|max:255',
            'items'                 => 'required|array|min:1',
        ]);

        \DB::beginTransaction();
        try {
            $bill = \App\Models\BillRequest::where('bill_number', $slug)->firstOrFail();

            if ($bill->status && in_array($bill->status->slug, ['paid', 'partial'])) {
                return back()->with('error', 'Gagal! Tagihan ini sudah memiliki riwayat pembayaran.');
            }

            $currency = \App\Models\Currency::find($request->currency_id)->code ?? 'IDR';
            $bill->update([
                'company_id'            => $request->paid_by_company_id,
                'vendor_name'           => $request->vendor_name,
                'vendor_invoice_number' => $request->vendor_invoice_number,
                'account_number'        => $request->account_number,
                'description'           => $request->note,
                'invoice_date'          => $request->bill_date,
                'due_date'              => $request->due_date,
                'currency'              => $currency,
                'is_recurring'          => $request->is_recurring == '1',
                'recurring_interval'    => $request->is_recurring == '1' ? (int)$request->recurring_interval : null,
                'recurring_period'      => $request->is_recurring == '1' ? $request->recurring_period : null,
            ]);

            $bill->items()->delete(); $bill->charges()->delete(); $bill->discounts()->delete();
            $totalSubtotal = 0; $totalItemDisc = 0; $totalTax = 0; $totalCharge = 0; $totalExtDisc = 0;

            foreach ($request->items as $item) {
                $qty = (float)$item['qty']; $price = (float)$item['price']; $gross = $qty * $price;

                $discVal = (float)($item['discount_value'] ?? 0); $discType = $item['discount_type'] ?? 'fixed';
                $discAmount = ($discType == 'percent') ? ($gross * $discVal / 100) : $discVal;
                $dpp = $gross - $discAmount;

                $taxVal = (float)($item['tax_value'] ?? 0);
                $taxType = $item['tax_type'] ?? 'percent';
                $taxId = $item['tax_id'] ?? null;

                if ($taxId === 'MANUAL_PERCENT') {
                    $taxType = 'percent';
                }

                $taxAmount = ($taxType == 'percent') ? ($dpp * $taxVal / 100) : $taxVal;

                $bill->items()->create([
                    'name' => $item['name'], 'description' => $item['description'], 'qty' => $qty, 'price' => $price, 'amount' => $dpp + $taxAmount,
                    'discount_type' => $discType, 'discount_value' => $discVal, 'discount_amount' => $discAmount,
                    'tax_id' => is_numeric($taxId) ? $taxId : null,
                    'tax_type' => $taxType, 'tax_value' => $taxVal, 'tax_amount' => $taxAmount, 'subtotal' => $gross,
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

            if ($request->hasFile('attachments')) {
                $basePath = \DB::table('system_settings')->where('setting_key', 'path_bills_opex')->value('setting_value') ?: 'attachments/opex';
                $safeBillNumber = str_replace(['/', '\\'], '-', $bill->bill_number);
                $storagePath = $basePath . '/' . $safeBillNumber;

                foreach ($request->file('attachments') as $file) {
                    $originalName = $file->getClientOriginalName();
                    $path = $file->storeAs($storagePath, time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName), 'public');
                    \DB::table('bill_attachments')->insert([
                        'bill_request_id' => $bill->id, 'file_name' => $originalName, 'file_path' => str_replace('\\', '/', $path),
                        'created_at' => now(), 'updated_at' => now()
                    ]);
                }
            }

            // 🔥 LOGIKA MENGHAPUS LAMPIRAN LAMA YANG DICENTANG SAAT EDIT 🔥
            if ($request->has('delete_media')) {
                foreach ($request->delete_media as $mediaId) {
                    $attachment = \DB::table('bill_attachments')->where('id', $mediaId)->first();
                    if ($attachment) {
                        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($attachment->file_path)) {
                            \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->file_path);
                        }
                        \DB::table('bill_attachments')->where('id', $mediaId)->delete();
                    }
                }
            }

            $grandTotal = max(0, ($totalSubtotal - $totalItemDisc) + $totalTax + $totalCharge - $totalExtDisc);
            $bill->update(['subtotal' => $totalSubtotal, 'total_discount' => $totalItemDisc + $totalExtDisc, 'total_tax' => $totalTax, 'total_charge' => $totalCharge, 'amount' => $grandTotal]);

            $this->logHistory($bill, 'UPDATED', "Merevisi dokumen tagihan. Total Baru: {$currency} " . number_format($grandTotal, 0, ',', '.'));

            \App\Models\DocumentApproval::where('document_id', $bill->id)->where('document_type', get_class($bill))->delete();

            $customWorkflowId = $request->input('custom_workflow_id');
            $needsApproval = false;

            if ($customWorkflowId) {
                $workflow = \App\Models\ApprovalWorkflow::with('steps')->find($customWorkflowId);
                if ($workflow && $workflow->steps->count() > 0) {
                    foreach ($workflow->steps as $step) {
                        $targetDept = $step->target_department_id ?? $step->department_id ?? null;
                        \App\Models\DocumentApproval::create([
                            'document_id'          => $bill->id,
                            'document_type'        => get_class($bill),
                            'role_id'              => $step->role_id,
                            'target_department_id' => $targetDept,
                            'step_order'           => $step->step_order,
                            'status'               => 'PENDING'
                        ]);
                    }
                    $needsApproval = true;
                    $this->logHistory($bill, 'SYSTEM', "Revisi menggunakan Rute Persetujuan Khusus: " . $workflow->name);
                }
            } else {
                $needsApproval = \App\Services\ApprovalService::generateWorkflow($bill);
                if ($needsApproval) $this->logHistory($bill, 'SYSTEM', "Rute persetujuan direset menyesuaikan data revisi (Standar).");
            }

            if ($needsApproval) {
                $bill->update(['status_id' => $this->getStatusId('pending') ?? 1]);
            } else {
                $bill->update(['status_id' => $this->getStatusId('approved') ?? 3]);
            }

            \DB::commit();
            return redirect()->route('bills.show', $bill->bill_number)->with('success', "Tagihan Opex berhasil diperbarui!");
        } catch (\Exception $e) {
            \DB::rollback();
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

    // untuk print BPR
    public function prinBpr($slug)
    {
        // Pastikan relasi user dan company terpanggil agar nama PT dan nama Requester muncul
        $bill = \App\Models\BillRequest::with(['items', 'user', 'company'])->where('bill_number', $slug)->firstOrFail();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('bills.pdf_bpr', compact('bill'))
                ->setPaper('a4', 'portrait');

        // 🔥 PERBAIKAN: Ganti karakter '/' menjadi '_' agar tidak ditolak oleh sistem sebagai folder
        $safeFilename = 'BPR_' . str_replace('/', '_', $bill->bill_number) . '.pdf';

        return $pdf->stream($safeFilename);
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



    // =========================================================================
    // 13. MEMBATALKAN TAGIHAN SECARA KESELURUHAN (VOID BILL)
    // =========================================================================
    public function voidBill(Request $request, $slug)
    {
        // Gembok khusus Super Admin / Eksekutif
        $userRoles = auth()->user()->getRoleNames()->toArray();
        $isSuperAdmin = in_array('Super Administrator', $userRoles) || in_array('Super Admin', $userRoles) || auth()->id() === 1;

        if (!$isSuperAdmin) {
            abort(403, 'Hanya Super Admin yang berhak membatalkan keseluruhan tagihan.');
        }

        $request->validate([
            'void_reason' => 'required|string|min:5'
        ]);

        DB::beginTransaction();
        try {
            $bill = \App\Models\BillRequest::where('bill_number', $slug)->firstOrFail();

            // Cegah void jika sudah lunas, dicicil, atau sudah void/reject
            $statusSlug = strtolower(optional($bill->status)->slug);
            if (in_array($statusSlug, ['paid', 'lunas', 'void', 'cancelled', 'rejected', 'partial', 'partial_paid', 'dicicil']) || strtoupper($bill->status) === 'PAID') {
                return back()->with('error', 'Aksi Ditolak: Tagihan yang sudah memiliki riwayat pembayaran (Lunas/Dicicil) tidak dapat di-Void secara sepihak.');
            }

            // Batalkan seluruh antrean persetujuan (jika ada)
            \App\Models\DocumentApproval::where('document_id', $bill->id)
                ->whereIn('document_type', ['OPEX', 'App\Models\BillRequest'])
                ->delete();

            // Update status menjadi VOID / CANCELLED
            $bill->update([
                'status'    => 'VOID',
                'status_id' => $this->getStatusId('void') ?? $this->getStatusId('cancelled'), // Pastikan di seeder ada status void/cancelled
                'rejection_reason' => 'VOIDED: ' . $request->void_reason // Kita simpan alasannya di field rejection_reason
            ]);

            // Catat di Audit Trail secara detail
            $this->logHistory($bill, 'TAGIHAN DIBATALKAN (VOID)', "Tagihan dibatalkan secara permanen oleh Sistem/Atasan. Alasan: " . $request->void_reason);

            DB::commit();
            return redirect()->route('bills.show', $bill->bill_number)->with('success', 'Tagihan berhasil dibatalkan secara permanen (VOID).');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal melakukan Void: ' . $e->getMessage());
        }
    }



    // =========================================================================
    // 4. SHOW (DETAIL BERBASIS SLUG NOMOR DOKUMEN)
    // =========================================================================
    public function show($slug)
    {
        // 1. Tarik Data Utama + Relasi (Eager Loading agar performa cepat)
        $bill = \App\Models\BillRequest::with([
            'status',
            'items',
            'company',
            'user',
            'histories.user',
            'charges.chargeType',
            'discounts.discountType'
        ])->where('bill_number', $slug)->firstOrFail();

        // 2. Tarik Data Lampiran Fisik dari tabel terpisah
        $attachments = \DB::table('bill_attachments')
            ->where('bill_request_id', $bill->id)
            ->get();

        // 3. Lemparkan ke Halaman Blade
        return view('bills.show', compact('bill', 'attachments'));
    }


    // =========================================================================
    // 14. MENGHENTIKAN SIKLUS TAGIHAN BERULANG (STOP RECURRING)
    // =========================================================================
    public function stopRecurring(Request $request, $slug)
    {
        try {
            $bill = \App\Models\BillRequest::where('bill_number', $slug)->firstOrFail();

            if (!$bill->is_recurring) {
                return back()->with('error', 'Tagihan ini bukan tagihan berulang.');
            }

            // Matikan sakelar recurring dan kosongkan jadwal berikutnya
            $bill->update([
                'is_recurring' => false,
                'next_generation_date' => null
            ]);

            // Catat di Audit Trail agar riwayatnya jelas
            $this->logHistory($bill, 'STOP LANGGANAN', 'Siklus tagihan berulang telah dihentikan oleh pengguna. Sistem tidak akan meng-generate tagihan ini lagi di masa depan.');

            return back()->with('success', 'Siklus langganan berhasil dihentikan!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghentikan langganan: ' . $e->getMessage());
        }
    }



    // =========================================================================
    // CETAK BPR LENGKAP DENGAN LAMPIRAN (SMART MERGE / ANTI-BADAI)
    // =========================================================================
    public function printBprWithAttachments($slug)
    {
        $bill = \App\Models\BillRequest::with([
            'items', 'company', 'user', 'charges.chargeType', 'discounts.discountType'
        ])->where('bill_number', $slug)->firstOrFail();

        // 1. RENDER DOMPDF MENGGUNAKAN TEMPLATE BPR
        $attachments = \DB::table('bill_attachments')->where('bill_request_id', $bill->id)->get();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('bills.pdf_bpr', compact('bill', 'attachments'))
                ->setPaper('A4', 'portrait');

        // 2. SIAPKAN FOLDER SEMENTARA
        $tempDir = storage_path('app/public/temp_pdf');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        // 3. SIMPAN HASIL DOMPDF UTAMA
        $tempMainPdfName = 'main_bpr_' . $bill->id . '_' . time() . '.pdf';
        $tempMainPdfPath = $tempDir . '/' . $tempMainPdfName;
        file_put_contents($tempMainPdfPath, $pdf->output());

        // 4. INISIASI MESIN PENGGABUNG PDF
        $oMerger = \Webklex\PDFMerger\Facades\PDFMergerFacade::init();
        $oMerger->addPDF($tempMainPdfPath, 'all');

        $tempFilesToDelete = [$tempMainPdfPath];
        $totalLampiranDiDatabase = 0;

        // 5. PROSES LAMPIRAN
        if ($attachments && $attachments->count() > 0) {
            $totalLampiranDiDatabase = $attachments->count();

            foreach ($attachments as $file) {
                $cleanFilePath = ltrim($file->file_path, '/');
                $finalFilePath = storage_path('app/public/' . $cleanFilePath);

                // 🔥 JIKA FILE FISIK DITEMUKAN 🔥
                if (file_exists($finalFilePath)) {
                    $extension = strtolower(pathinfo($finalFilePath, PATHINFO_EXTENSION));

                    // A. PENANGANAN FILE PDF (DENGAN TRY-CATCH ANTI BADAI)
                    if ($extension === 'pdf') {
                        try {
                            $fpdi = new \setasign\Fpdi\Fpdi();
                            $fpdi->setSourceFile($finalFilePath);
                            $oMerger->addPDF($finalFilePath, 'all');
                        } catch (\Exception $e) {
                            // Jika PDF terkompresi / terkunci, buat halaman info
                            $html = "<div style='border:2px solid #0d6efd; padding:20px; text-align:center; font-family:sans-serif; margin-top:50px;'>
                                        <h2 style='color:#0d6efd;'>📄 LAMPIRAN PDF (TERENKRIPSI/TERKOMPRESI)</h2>
                                        <p>File pendukung bernama: <b>{$file->file_name}</b></p>
                                        <p>File ini menggunakan format PDF modern yang tidak bisa digabungkan ke dalam dokumen ini secara otomatis.</p>
                                        <p><i>Silakan lihat atau unduh file ini langsung melalui sistem ProcureApp.</i></p>
                                     </div>";
                            $infoPdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait');
                            $infoPath = $tempDir . '/info_' . uniqid() . '.pdf';
                            file_put_contents($infoPath, $infoPdf->output());
                            $oMerger->addPDF($infoPath, 'all');
                            $tempFilesToDelete[] = $infoPath;
                        }
                    }
                    // B. PENANGANAN FILE GAMBAR (UBAH KE PDF DULU SEBELUM DIGABUNG)
                    elseif (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                        $imageData = base64_encode(file_get_contents($finalFilePath));
                        $mime = mime_content_type($finalFilePath);
                        $base64Src = 'data:' . $mime . ';base64,' . $imageData;

                        $imgPdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML("
                            <html><head><style>@page{margin:0px;} body{margin:0;padding:20px;text-align:center;} img{max-width:100%;max-height:1050px;}</style></head>
                            <body><img src='" . $base64Src . "'></body></html>
                        ")->setPaper('a4', 'portrait');

                        $imgTempName = 'img_convert_' . uniqid() . '.pdf';
                        $imgTempPath = $tempDir . '/' . $imgTempName;
                        file_put_contents($imgTempPath, $imgPdf->output());
                        $oMerger->addPDF($imgTempPath, 'all');
                        $tempFilesToDelete[] = $imgTempPath;
                    }
                    // C. PENANGANAN FILE WORD / EXCEL / LAINNYA
                    else {
                        $html = "<div style='border:2px solid #198754; padding:20px; text-align:center; font-family:sans-serif; margin-top:50px;'>
                                    <h2 style='color:#198754;'>📎 LAMPIRAN BERKAS (".strtoupper($extension).")</h2>
                                    <p>File pendukung bernama: <b>{$file->file_name}</b></p>
                                    <p>File ini berformat Excel / Word / Lainnya sehingga tidak dapat ditampilkan sebagai halaman PDF.</p>
                                    <p><i>Silakan unduh lampiran ini melalui menu detail tagihan di sistem.</i></p>
                                 </div>";
                        $infoPdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait');
                        $infoPath = $tempDir . '/info_' . uniqid() . '.pdf';
                        file_put_contents($infoPath, $infoPdf->output());
                        $oMerger->addPDF($infoPath, 'all');
                        $tempFilesToDelete[] = $infoPath;
                    }
                }
                // 🔥 JIKA FILE FISIK HILANG DARI SERVER 🔥
                else {
                    $errorHtml = "<div style='border:2px solid red; padding:20px; text-align:center; font-family:sans-serif; margin-top:50px;'>
                                    <h2 style='color:red;'>⚠️ FILE FISIK HILANG ⚠️</h2>
                                    <p>Data lampiran <b>{$file->file_name}</b> tercatat di sistem, tapi file aslinya tidak ditemukan di server.</p>
                                  </div>";
                    $errorPdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($errorHtml)->setPaper('a4', 'portrait');
                    $errorTempPath = $tempDir . '/err_notfound_' . uniqid() . '.pdf';
                    file_put_contents($errorTempPath, $errorPdf->output());
                    $oMerger->addPDF($errorTempPath, 'all');
                    $tempFilesToDelete[] = $errorTempPath;
                }
            }
        }

        // JIKA DI DATABASE TIDAK ADA LAMPIRAN SAMA SEKALI
        if ($totalLampiranDiDatabase === 0) {
            $noDataHtml = "<div style='border: 2px solid orange; padding: 20px; font-family: sans-serif; text-align:center; margin-top:50px;'>
                            <h2 style='color: orange;'>⚠️ INFO SISTEM ⚠️</h2><p>TIDAK ADA DATA LAMPIRAN untuk Tagihan ini.</p></div>";
            $noDataPdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($noDataHtml)->setPaper('a4', 'portrait');
            $noDataTempPath = $tempDir . '/err_nodata_' . uniqid() . '.pdf';
            file_put_contents($noDataTempPath, $noDataPdf->output());
            $oMerger->addPDF($noDataTempPath, 'all');
            $tempFilesToDelete[] = $noDataTempPath;
        }

        // 6. JAHIT SEMUA PDF MENJADI SATU KESATUAN
        $oMerger->merge();
        $finalPdfOutput = $oMerger->output();

        // 7. BERSIHKAN FILE SEMENTARA
        foreach ($tempFilesToDelete as $trashPath) {
            if (file_exists($trashPath)) {
                unlink($trashPath);
            }
        }

        $filename = 'BPR_Lengkap_Dengan_Lampiran_' . str_replace('/', '_', $bill->bill_number) . '.pdf';

        return response($finalPdfOutput)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }







}
