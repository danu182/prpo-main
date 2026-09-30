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
        return $status ? $status->id : null;
    }


    // --- HELPER NUMBER GENERATOR (MIRIP PR) ---
    private function generateBillNumber($companyId)
    {
        $company = \App\Models\Company::find($companyId);

        if ($company && !empty($company->code)) {
            $code = strtoupper($company->code);
        } else {
            $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $company->name ?? 'GEN');
            $code = strtoupper(substr($cleanName, 0, 3));
        }

        $now = now();
        $dateStr = $now->format('Y/m/d');

        $prefix = "BILL/{$code}/{$dateStr}/";

        $lastBill = \App\Models\BillRequest::where('bill_number', 'like', $prefix . '%')
                    ->orderBy('id', 'desc')
                    ->lockForUpdate()
                    ->first();

        if ($lastBill) {
            $lastNumber = (int) substr($lastBill->bill_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . sprintf('%04d', $newNumber);
    }


    public function getTaxRate($billDate)
    {
        return \App\Models\Tax::where('name', 'PPN')
            ->where('is_active', true)
            ->where('effective_date', '<=', $billDate)
            ->orderBy('effective_date', 'desc')
            ->first();
    }


    // --- 1. MENAMPILKAN LIST & TAB LANGGANAN ---
    public function index(Request $request)
    {
        $companies = \App\Models\Company::orderBy('name')->get();

        $query = \App\Models\BillRequest::with(['company', 'user', 'status'])->latest();

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

        $customWorkflows = [];
        if (class_exists('\App\Models\ApprovalWorkflow')) {
            $customWorkflows = \App\Models\ApprovalWorkflow::where('document_type', 'App\Models\BillRequest')
                                ->orWhere('document_type', 'OPEX')
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
        $action = $request->action;
        $reason = $request->reason;

        if ($bill->current_approval_level == 0 && !$user->hasRole('Manager')) {
            return back()->with('error', 'Akses Ditolak: Giliran Manager.');
        }
        if ($bill->current_approval_level == 1 && !$user->hasRole('Director')) {
            return back()->with('error', 'Akses Ditolak: Giliran Director.');
        }

        if ($action == 'REJECTED') {
            $bill->update(['status' => 'REJECTED', 'rejection_reason' => $reason]);
            $this->logHistory($bill, 'REJECTED', "Ditolak oleh " . $user->name . ". Alasan: $reason");
        } else {
            if ($bill->current_approval_level == 0) {
                $bill->update([
                    'current_approval_level' => 1,
                    'status' => 'APPROVED_MANAGER'
                ]);
                $this->logHistory($bill, 'APPROVED', 'Disetujui oleh Manager.');
            } else {
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
    private function logHistory($bill, $action, $note = null)
    {
        \App\Models\History::create([
            'user_id'     => auth()->id(),
            'record_type' => \App\Models\BillRequest::class,
            'record_id'   => $bill->id,
            'action'      => $action,
            'note'        => $note
        ]);
    }


    public function decide(Request $request, $id)
    {
        $bill = BillRequest::findOrFail($id);

        if ($request->action == 'APPROVED') {
            $bill->update(['status' => 'APPROVED']);
            $this->logHistory($bill, 'Menyetujui Tagihan', 'Disetujui oleh Manager/Director');

        } elseif ($request->action == 'REJECTED') {
            $bill->update(['status' => 'REJECTED', 'rejection_reason' => $request->reason]);
            $this->logHistory($bill, 'Menolak Tagihan', 'Alasan: ' . $request->reason);
        }

        return back();
    }


    public function print($id)
    {
        $bill = \App\Models\BillRequest::with(['user', 'company', 'items', 'media'])->findOrFail($id);
        return view('bills.print', compact('bill'));
    }


    public function markAsPaid($id)
    {
        $bill = \App\Models\BillRequest::findOrFail($id);

        if ($bill->status !== 'APPROVED') {
            return back()->with('error', 'Tagihan harus disetujui terlebih dahulu sebelum ditandai lunas.');
        }

        DB::beginTransaction();
        try {
            $updateData = ['status' => 'PAID'];

            if ($bill->is_recurring && $bill->type == 'ROUTINE') {
                $updateData['next_generation_date'] = now()->addMonths($bill->recurring_period);
            }

            $bill->update($updateData);
            $this->logHistory($bill, 'Menandai Pembayaran Lunas', 'Finance telah mengonfirmasi pembayaran selesai.');

            DB::commit();
            return redirect()->route('bills.show', $bill->id)->with('success', 'Tagihan berhasil ditandai sebagai PAID.');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal memproses pembayaran: ' . $e->getMessage());
        }
    }


    // =========================================================================
    // 3. STORE (SIMPAN DATA BARU + PARENT-CHILD GOOGLE SHEET)
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
            'account_number'        => 'nullable|string|max:255',
            'items'                 => 'required|array|min:1',
            'items.*.name'          => 'required|string',
            'items.*.qty'           => 'required|numeric|min:1',
            'items.*.price'         => 'required|numeric|min:0',
        ]);

        \DB::beginTransaction();
        try {
            $company = \App\Models\Company::find($request->paid_by_company_id);
            $companyCode = $company ? ($company->code ?? 'GEN') : 'GEN';
            $companyName = $company ? $company->name : '-';
            $monthYear = \Carbon\Carbon::parse($request->bill_date)->format('Y/m');

            $prefix = "BILL/OPX/{$companyCode}/{$monthYear}/";
            $lastBill = \App\Models\BillRequest::where('bill_number', 'like', $prefix . '%')->lockForUpdate()->orderBy('id', 'desc')->first();

            $newNumber = $lastBill ? ((int) substr($lastBill->bill_number, -4) + 1) : 1;
            $billNumber = $prefix . sprintf('%04d', $newNumber);
            $currency = \App\Models\Currency::find($request->currency_id)->code ?? 'IDR';

            $totalSubtotal = 0; $totalItemDisc = 0; $totalTax = 0; $totalCharge = 0; $totalExtDisc = 0;

            $bill = \App\Models\BillRequest::create([
                'bill_number'           => $billNumber,
                'title'                 => 'Tagihan Opex - ' . $request->vendor_name,
                'user_id'               => auth()->id(),
                'company_id'            => $request->paid_by_company_id,
                'type'                  => 'OPEX',
                'vendor_name'           => $request->vendor_name,
                'vendor_invoice_number' => $request->vendor_invoice_number,
                'account_number'        => $request->account_number,
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

            // 🔥 KERANJANG UNTUK BARIS ANAK (CHILD ROWS) 🔥
            $childRows = [];

            // 1. PROSES ITEM UTAMA (CHILD)
            foreach ($request->items as $item) {
                $qty = (float)$item['qty']; $price = (float)$item['price']; $gross = $qty * $price;
                $discVal = (float)($item['discount_value'] ?? 0); $discType = $item['discount_type'] ?? 'fixed';
                $discAmount = ($discType == 'percent') ? ($gross * $discVal / 100) : $discVal;
                $dpp = $gross - $discAmount;

                $taxVal = (float)($item['tax_value'] ?? 0); $taxType = $item['tax_type'] ?? 'percent';
                $taxId = $item['tax_id'] ?? null;
                if ($taxId === 'MANUAL_PERCENT') $taxType = 'percent';
                $taxAmount = ($taxType == 'percent') ? ($dpp * $taxVal / 100) : $taxVal;

                $namaLayananCustom = $item['name_override'] ?? $item['item_name'] ?? $item['name'] ?? 'Layanan Tanpa Nama';

                // 🔥 PELACAKAN KATEGORI MASTER ITEM YANG DIPERBAIKI 🔥
                $masterItemId = null;
                if (!empty($item['item_id'])) {
                    $masterItemId = $item['item_id'];
                } elseif (!empty($item['code'])) {
                    $masterItem = \App\Models\Item::where('code', $item['code'])->first();
                    if ($masterItem) $masterItemId = $masterItem->id;
                } elseif (!empty($item['name'])) {
                    // Memecah teks dari dropdown form (Contoh: "JSA-0060 - Jasa IT")
                    $exploded = explode(' - ', $item['name']);
                    $potentialCode = trim($exploded[0]);
                    $masterItem = \App\Models\Item::where('code', $potentialCode)->orWhere('name', $item['name'])->first();
                    if ($masterItem) $masterItemId = $masterItem->id;
                }

                $categoryName = 'Lainnya';
                if ($masterItemId) {
                    $masterItemData = \App\Models\Item::find($masterItemId);
                    if ($masterItemData && $masterItemData->category_id) {
                        $kategori = \DB::table('categories')->where('id', $masterItemData->category_id)->first();
                        if ($kategori) $categoryName = $kategori->name;
                    }
                }

                $bill->items()->create([
                    'item_id'         => $masterItemId,
                    'name'            => $namaLayananCustom,
                    'description'     => $item['description'] ?? null,
                    'qty'             => $qty,
                    'price'           => $price,
                    'amount'          => $dpp + $taxAmount,
                    'discount_type'   => $discType,
                    'discount_value'  => $discVal,
                    'discount_amount' => $discAmount,
                    'tax_id'          => is_numeric($taxId) ? $taxId : null,
                    'tax_type'        => $taxType,
                    'tax_value'       => $taxVal,
                    'tax_amount'      => $taxAmount,
                    'subtotal'        => $gross,
                ]);

                $totalSubtotal += $gross; $totalItemDisc += $discAmount; $totalTax += $taxAmount;

                $childRows[] = [
                    \Carbon\Carbon::parse($request->bill_date)->format('d-M-Y'),
                    $companyName, $request->vendor_name,
                    "  ↳ [Item] " . $namaLayananCustom,
                    $categoryName,                             // 🔥 Kategori Item Akan Akurat Disini
                    $dpp,
                    $taxAmount,
                    $dpp + $taxAmount,
                    $request->vendor_invoice_number ?? '-', $request->account_number ?? '-', $billNumber, $companyName, '-'
                ];
            }

            // 2. PROSES BIAYA EKSTRA (CHILD)
            if ($request->has('charges')) {
                foreach ($request->charges as $charge) {
                    if (!empty($charge['charge_type_id']) && $charge['amount'] > 0) {
                        $bill->charges()->create(['charge_type_id' => $charge['charge_type_id'], 'amount' => $charge['amount'], 'note' => $charge['note'] ?? null]);
                        $totalCharge += $charge['amount'];

                        $chargeType = \DB::table('charge_types')->where('id', $charge['charge_type_id'])->first();
                        $chargeName = $chargeType ? $chargeType->name : 'Biaya Tambahan';
                        $note = !empty($charge['note']) ? ' (' . $charge['note'] . ')' : '';

                        $childRows[] = [
                            \Carbon\Carbon::parse($request->bill_date)->format('d-M-Y'),
                            $companyName, $request->vendor_name,
                            "  ↳ [+] " . $chargeName . $note,
                            'Biaya Tambahan',
                            $charge['amount'],
                            0,
                            $charge['amount'],
                            $request->vendor_invoice_number ?? '-', $request->account_number ?? '-', $billNumber, $companyName, '-'
                        ];
                    }
                }
            }

            // 3. PROSES POTONGAN EKSTRA (CHILD)
            if ($request->has('discounts')) {
                foreach ($request->discounts as $discount) {
                    if (!empty($discount['discount_type_id']) && $discount['amount'] > 0) {
                        $bill->discounts()->create(['discount_type_id' => $discount['discount_type_id'], 'amount' => $discount['amount'], 'note' => $discount['note'] ?? null]);
                        $totalExtDisc += $discount['amount'];

                        $discType = \DB::table('discount_types')->where('id', $discount['discount_type_id'])->first();
                        $discName = $discType ? $discType->name : 'Potongan';
                        $note = !empty($discount['note']) ? ' (' . $discount['note'] . ')' : '';

                        $childRows[] = [
                            \Carbon\Carbon::parse($request->bill_date)->format('d-M-Y'),
                            $companyName, $request->vendor_name,
                            "  ↳ [-] " . $discName . $note,
                            'Potongan / Diskon',
                            -abs($discount['amount']),
                            0,
                            -abs($discount['amount']),
                            $request->vendor_invoice_number ?? '-', $request->account_number ?? '-', $billNumber, $companyName, '-'
                        ];
                    }
                }
            }

            // 4. Kalkulasi Grand Total
            $grandTotal = max(0, ($totalSubtotal - $totalItemDisc) + $totalTax + $totalCharge - $totalExtDisc);
            $bill->update(['subtotal' => $totalSubtotal, 'total_discount' => $totalItemDisc + $totalExtDisc, 'total_tax' => $totalTax, 'total_charge' => $totalCharge, 'amount' => $grandTotal]);

            // 🔥 MEMBUAT BARIS INDUK (PARENT ROW) 🔥
            $parentRow = [
                \Carbon\Carbon::parse($request->bill_date)->format('d-M-Y'),
                $companyName,
                $request->vendor_name,
                "⭐ GRAND TOTAL",
                "SUMMARY",
                ($totalSubtotal - $totalItemDisc + $totalCharge - $totalExtDisc),
                $totalTax,
                $grandTotal,
                $request->vendor_invoice_number ?? '-',
                $request->account_number ?? '-',
                $billNumber,
                $companyName,
                '-',
            ];

            $googleSheetRows = array_merge([$parentRow], $childRows);

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

            $this->logHistory($bill, 'CREATED', "Membuat tagihan baru No: {$billNumber}");

            $customWorkflowId = $request->input('custom_workflow_id');
            $needsApproval = false;

            if ($customWorkflowId) {
                $workflow = \App\Models\ApprovalWorkflow::with('steps')->find($customWorkflowId);
                if ($workflow && $workflow->steps->count() > 0) {
                    foreach ($workflow->steps as $step) {
                        \App\Models\DocumentApproval::create([
                            'document_id' => $bill->id, 'document_type' => get_class($bill), 'role_id' => $step->role_id,
                            'target_department_id' => $step->target_department_id ?? $step->department_id ?? null,
                            'step_order' => $step->step_order, 'status' => 'PENDING'
                        ]);
                    }
                    $needsApproval = true;
                    $this->logHistory($bill, 'SYSTEM', "Menggunakan Rute Persetujuan Khusus: " . $workflow->name);
                }
            } else {
                $needsApproval = \App\Services\ApprovalService::generateWorkflow($bill);
            }

            if ($needsApproval) {
                $bill->update(['status_id' => $this->getStatusId('pending') ?? 1]);
            } else {
                $bill->update(['status_id' => $this->getStatusId('approved') ?? 3]);
            }

            \DB::commit();

            try {
                $sheetService = new \App\Services\GoogleSheetService();
                $tabName = env('GOOGLE_SHEET_OPEX_TAB_NAME', 'Sheet1');

                foreach ($googleSheetRows as $rowData) {
                    $sheetService->appendRow($tabName, $rowData);
                }
            } catch (\Exception $e) {
                \Log::error("Google Sheet Sync Error pada Bill {$billNumber}: " . $e->getMessage());
            }

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
        $bill = \App\Models\BillRequest::with(['items', 'charges', 'discounts', 'status'])->where('bill_number', $slug)->firstOrFail();

        if ($bill->status && !in_array($bill->status->slug, ['pending', 'draft'])) {
            return back()->with('error', 'Tagihan yang sudah disetujui atau diproses tidak dapat diedit!');
        }

        $companies     = \App\Models\Company::all();
        $taxes         = \App\Models\Tax::where('is_active', true)->orderBy('name')->get();
        $currencies    = \App\Models\Currency::where('is_active', true)->orderBy('name')->get();
        $vendors       = \App\Models\Vendor::orderBy('name')->get();
        $chargeTypes   = \App\Models\ChargeType::where('is_active', true)->orderBy('name')->get();
        $discountTypes = \App\Models\DiscountType::where('is_active', true)->orderBy('name')->get();

        $opexItems     = \App\Models\Item::whereNotIn('item_type_code', ['AST', 'STK'])->orWhereNull('item_type_code')->orderBy('name')->get();

        $attachments = \DB::table('bill_attachments')->where('bill_request_id', $bill->id)->get();

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

            $historyLog = \App\Models\History::where('record_id', $bill->id)
                ->whereIn('record_type', [get_class($bill), 'App\Models\BillRequest', 'OPEX'])
                ->where('action', 'SYSTEM')
                ->where('note', 'like', 'Menggunakan Rute Persetujuan Khusus:%')
                ->orderBy('id', 'desc')->first();

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

            if ($historyLog) {
                $workflowName = trim(str_replace('Menggunakan Rute Persetujuan Khusus:', '', $historyLog->note));
                $matchedWorkflow = $customWorkflows->where('name', $workflowName)->first();
                if ($matchedWorkflow) {
                    $selectedWorkflowId = $matchedWorkflow->id;
                }
            }

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

        return view('bills.edit', compact(
            'bill', 'companies', 'taxes', 'currencies', 'vendors',
            'opexItems', 'chargeTypes', 'discountTypes', 'attachments',
            'customWorkflows', 'selectedWorkflowId'
        ));
    }



    // =========================================================================
    // UPDATE (SIMPAN REVISI PO + PARENT-CHILD GOOGLE SHEET)
    // =========================================================================
    public function update(Request $request, $slug)
    {
        $request->validate([
            'company_id'            => 'required|exists:companies,id',
            'currency_id'           => 'required|exists:currencies,id',
            'po_date'               => 'required|date',
            'delivery_date'         => 'nullable|date|after_or_equal:po_date', // Contoh field tambahan PO
            'vendor_name'           => 'required|string|max:255',
            'vendor_invoice_number' => 'nullable|string|max:255', // Jika PO sudah ada invoice
            'account_number'        => 'nullable|string|max:255', // Account BPR
            'items'                 => 'required|array|min:1',
            // Tambahkan validasi lain sesuai kebutuhan form PO Anda
        ]);

        \DB::beginTransaction();
        try {
            // 1. Ambil Data PO Berdasarkan Slug (po_number)
            $po = \App\Models\PurchaseOrder::where('po_number', $slug)->firstOrFail();

            // Cek Status PO (Opsional, sesuaikan dengan aturan bisnis Anda)
            // Misal: Jika PO sudah dibayar atau diproses, tidak boleh di-update
            if ($po->status && in_array(strtolower($po->status->slug), ['paid', 'partial', 'completed'])) {
                return back()->with('error', 'Gagal! Purchase Order ini sudah diproses atau dibayar.');
            }

            // 2. Siapkan Variabel Pendukung
            $currency = \App\Models\Currency::find($request->currency_id)->code ?? 'IDR';
            $company = \App\Models\Company::find($request->company_id);
            $companyName = $company ? $company->name : '-';

            // 3. Update Data Induk PO (Header)
            $po->update([
                'company_id'            => $request->company_id,
                'vendor_name'           => $request->vendor_name,
                'vendor_invoice_number' => $request->vendor_invoice_number,
                'account_number'        => $request->account_number,
                'description'           => $request->note, // Catatan PO
                'po_date'               => $request->po_date,
                'delivery_date'         => $request->delivery_date,
                'currency'              => $currency,
                // Tambahkan field lain jika perlu, misal terms_of_payment
            ]);

            // 4. Bersihkan Relasi Lama (Items, Charges, Discounts)
            $po->items()->delete();
            $po->charges()->delete();    // Asumsi model PO punya relasi charges()
            $po->discounts()->delete();  // Asumsi model PO punya relasi discounts()

            $totalSubtotal = 0;
            $totalItemDisc = 0;
            $totalTax = 0;
            $totalCharge = 0;
            $totalExtDisc = 0;

            // Variabel penyimpan baris-baris Google Sheet
            $childRows = [];

            // ==========================================
            // A. PROSES ITEM UTAMA PO (CHILD)
            // ==========================================
            foreach ($request->items as $item) {
                $qty = (float)$item['qty'];
                $price = (float)$item['price'];
                $gross = $qty * $price;

                // Hitung Diskon Item
                $discVal = (float)($item['discount_value'] ?? 0);
                $discType = $item['discount_type'] ?? 'fixed';
                $discAmount = ($discType == 'percent') ? ($gross * $discVal / 100) : $discVal;

                $dpp = $gross - $discAmount; // Dasar Pengenaan Pajak (Setelah Diskon)

                // Hitung Pajak (Tax) Item
                $taxVal = (float)($item['tax_value'] ?? 0);
                $taxType = $item['tax_type'] ?? 'percent';
                $taxId = $item['tax_id'] ?? null;
                if ($taxId === 'MANUAL_PERCENT') $taxType = 'percent';

                $taxAmount = ($taxType == 'percent') ? ($dpp * $taxVal / 100) : $taxVal;

                $namaItemCustom = $item['name_override'] ?? $item['item_name'] ?? $item['name'] ?? 'Barang Tanpa Nama';

                // Pelacakan Kategori Master Item
                $masterItemId = null;
                $categoryName = 'Barang Stok (Inventory)'; // Default kategori PO

                if (!empty($item['item_id'])) {
                    $masterItemId = $item['item_id'];
                } elseif (!empty($item['code'])) {
                    $masterItem = \App\Models\Item::where('code', $item['code'])->first();
                    if ($masterItem) $masterItemId = $masterItem->id;
                } elseif (!empty($item['name'])) {
                    $exploded = explode(' - ', $item['name']);
                    $potentialCode = trim($exploded[0]);
                    $masterItem = \App\Models\Item::where('code', $potentialCode)->orWhere('name', $item['name'])->first();
                    if ($masterItem) $masterItemId = $masterItem->id;
                }

                if ($masterItemId) {
                    $masterItemData = \App\Models\Item::find($masterItemId);
                    if ($masterItemData && $masterItemData->category_id) {
                        $kategori = \DB::table('categories')->where('id', $masterItemData->category_id)->first();
                        if ($kategori) $categoryName = $kategori->name;
                    }
                }

                // Simpan Item ke Database
                $po->items()->create([
                    'item_id'         => $masterItemId,
                    'name'            => $namaItemCustom,
                    'description'     => $item['description'] ?? null,
                    'qty'             => $qty,
                    'price'           => $price,
                    'amount'          => $dpp + $taxAmount, // Total bersih item ini
                    'discount_type'   => $discType,
                    'discount_value'  => $discVal,
                    'discount_amount' => $discAmount,
                    'tax_id'          => is_numeric($taxId) ? $taxId : null,
                    'tax_type'        => $taxType,
                    'tax_value'       => $taxVal,
                    'tax_amount'      => $taxAmount,
                    'subtotal'        => $gross, // Total kotor item ini
                ]);

                // Akumulasi Total Induk
                $totalSubtotal += $gross;
                $totalItemDisc += $discAmount;
                $totalTax += $taxAmount;

                // Tambahkan ke Array Google Sheet (Child Row - Item)
                $childRows[] = [
                    \Carbon\Carbon::parse($request->po_date)->format('d-M-Y'),
                    $companyName,
                    $request->vendor_name,
                    "  ↳ [Item] " . $namaItemCustom,
                    $categoryName,
                    $dpp,
                    $taxAmount,
                    $dpp + $taxAmount,
                    $request->vendor_invoice_number ?? '-',
                    $request->account_number ?? '-',
                    $po->po_number, // Kolom K: ID Pencarian (nomer BPR)
                    auth()->user()->name ?? 'System' // Kolom L: Pembuat
                ];
            }

            // ==========================================
            // B. PROSES BIAYA TAMBAHAN (CHARGES)
            // ==========================================
            if ($request->has('charges')) {
                foreach ($request->charges as $charge) {
                    if (!empty($charge['charge_type_id']) && $charge['amount'] > 0) {
                        $po->charges()->create([
                            'charge_type_id' => $charge['charge_type_id'],
                            'amount'         => $charge['amount'],
                            'note'           => $charge['note'] ?? null
                        ]);

                        $totalCharge += $charge['amount'];

                        $chargeType = \DB::table('charge_types')->where('id', $charge['charge_type_id'])->first();
                        $chargeName = $chargeType ? $chargeType->name : 'Biaya Tambahan';
                        $note = !empty($charge['note']) ? ' (' . $charge['note'] . ')' : '';

                        // Tambahkan ke Array Google Sheet (Child Row - Charge)
                        $childRows[] = [
                            \Carbon\Carbon::parse($request->po_date)->format('d-M-Y'),
                            $companyName,
                            $request->vendor_name,
                            "  ↳ [+] " . $chargeName . $note,
                            'Biaya Tambahan',
                            $charge['amount'],
                            0, // Anggap charges tidak kena pajak (sesuaikan jika perlu)
                            $charge['amount'],
                            $request->vendor_invoice_number ?? '-',
                            $request->account_number ?? '-',
                            $po->po_number,
                            auth()->user()->name ?? 'System'
                        ];
                    }
                }
            }

            // ==========================================
            // C. PROSES POTONGAN (DISCOUNTS KESELURUHAN)
            // ==========================================
            if ($request->has('discounts')) {
                foreach ($request->discounts as $discount) {
                    if (!empty($discount['discount_type_id']) && $discount['amount'] > 0) {
                        $po->discounts()->create([
                            'discount_type_id' => $discount['discount_type_id'],
                            'amount'           => $discount['amount'],
                            'note'             => $discount['note'] ?? null
                        ]);

                        $totalExtDisc += $discount['amount'];

                        $discType = \DB::table('discount_types')->where('id', $discount['discount_type_id'])->first();
                        $discName = $discType ? $discType->name : 'Potongan';
                        $note = !empty($discount['note']) ? ' (' . $discount['note'] . ')' : '';

                        // Tambahkan ke Array Google Sheet (Child Row - Discount)
                        $childRows[] = [
                            \Carbon\Carbon::parse($request->po_date)->format('d-M-Y'),
                            $companyName,
                            $request->vendor_name,
                            "  ↳ [-] " . $discName . $note,
                            'Potongan / Diskon',
                            -abs($discount['amount']), // Nilai minus
                            0,
                            -abs($discount['amount']),
                            $request->vendor_invoice_number ?? '-',
                            $request->account_number ?? '-',
                            $po->po_number,
                            auth()->user()->name ?? 'System'
                        ];
                    }
                }
            }

            // ==========================================
            // 5. KALKULASI GRAND TOTAL PO
            // ==========================================
            $grandTotal = max(0, ($totalSubtotal - $totalItemDisc) + $totalTax + $totalCharge - $totalExtDisc);

            $po->update([
                'subtotal'       => $totalSubtotal,
                'total_discount' => $totalItemDisc + $totalExtDisc,
                'total_tax'      => $totalTax,
                'total_charge'   => $totalCharge,
                'grand_total'    => $grandTotal // atau 'amount' => $grandTotal sesuai nama field database
            ]);

            // ==========================================
            // 6. MEMBUAT BARIS INDUK GOOGLE SHEET (PARENT)
            // ==========================================
            $parentRow = [
                \Carbon\Carbon::parse($request->po_date)->format('d-M-Y'),
                $companyName,
                $request->vendor_name,
                "⭐ GRAND TOTAL PO",
                "SUMMARY",
                ($totalSubtotal - $totalItemDisc + $totalCharge - $totalExtDisc), // DPP Keseluruhan
                $totalTax,
                $grandTotal,
                $request->vendor_invoice_number ?? '-',
                $request->account_number ?? '-',
                $po->po_number,
                auth()->user()->name ?? 'System',
            ];

            // Gabungkan Parent dan Child
            $googleSheetRows = array_merge([$parentRow], $childRows);

            // ==========================================
            // 7. HANDLE ATTACHMENTS (Jika Ada File Baru/Hapus)
            // ==========================================
            if ($request->hasFile('attachments')) {
                // Asumsi nama tabel setting sama dengan opex, tapi key path-nya beda (contoh: path_po)
                $basePath = \DB::table('system_settings')->where('setting_key', 'path_po')->value('setting_value') ?: 'attachments/po';
                $storagePath = $basePath . '/' . str_replace(['/', '\\'], '-', $po->po_number);

                foreach ($request->file('attachments') as $file) {
                    $originalName = $file->getClientOriginalName();
                    $path = $file->storeAs($storagePath, time() . '_' . uniqid() . '_' . str_replace(' ', '_', $originalName), 'public');

                    \DB::table('po_attachments')->insert([ // Asumsi nama tabel: po_attachments
                        'purchase_order_id' => $po->id,
                        'file_name'         => $originalName,
                        'file_path'         => str_replace('\\', '/', $path),
                        'created_at'        => now(),
                        'updated_at'        => now()
                    ]);
                }
            }

            if ($request->has('delete_media')) {
                foreach ($request->delete_media as $mediaId) {
                    $attachment = \DB::table('po_attachments')->where('id', $mediaId)->first();
                    if ($attachment) {
                        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($attachment->file_path)) {
                            \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->file_path);
                        }
                        \DB::table('po_attachments')->where('id', $mediaId)->delete();
                    }
                }
            }

            // ==========================================
            // 8. LOG HISTORY & WORKFLOW APPROVAL
            // ==========================================
            $this->logHistory($po, 'UPDATED', "Merevisi dokumen PO. Total Baru: {$currency} " . number_format($grandTotal, 0, ',', '.'));

            // Hapus workflow lama
            \App\Models\DocumentApproval::where('document_id', $po->id)->where('document_type', get_class($po))->delete();

            $customWorkflowId = $request->input('custom_workflow_id');
            $needsApproval = false;

            if ($customWorkflowId) {
                // Workflow Khusus PO
                $workflow = \App\Models\ApprovalWorkflow::with('steps')->find($customWorkflowId);
                if ($workflow && $workflow->steps->count() > 0) {
                    foreach ($workflow->steps as $step) {
                        \App\Models\DocumentApproval::create([
                            'document_id'          => $po->id,
                            'document_type'        => get_class($po),
                            'role_id'              => $step->role_id,
                            'target_department_id' => $step->target_department_id ?? $step->department_id ?? null,
                            'step_order'           => $step->step_order,
                            'status'               => 'PENDING'
                        ]);
                    }
                    $needsApproval = true;
                    $this->logHistory($po, 'SYSTEM', "Revisi menggunakan Rute Persetujuan Khusus: " . $workflow->name);
                }
            } else {
                // Workflow Default PO (menggunakan ApprovalService)
                $needsApproval = \App\Services\ApprovalService::generateWorkflow($po);
            }

            // Update Status PO berdasarkan ada tidaknya approval
            if ($needsApproval) {
                $po->update(['status_id' => $this->getStatusId('pending') ?? 1]);
            } else {
                $po->update(['status_id' => $this->getStatusId('approved') ?? 3]);
            }

            \DB::commit();

            // ==========================================
            // 9. SINKRONISASI GOOGLE SHEET (ANTI DOBEL)
            // ==========================================
            try {
                $sheetService = new \App\Services\GoogleSheetService();
                $tabName = env('GOOGLE_SHEET_PO_TAB_NAME', 'PO_Sheet');

                // LAKUKAN PENGHAPUSAN BARIS LAMA TERLEBIH DAHULU (Berdasarkan Kolom K: Nomer BPR/PO)
                // Pastikan fungsi ini sudah diperbaiki di GoogleSheetService agar benar-benar menghapus!
                $sheetService->deleteRowsByBillNumber($tabName, $po->po_number);

                // INSERT BARIS YANG BARU
                if (!empty($googleSheetRows)) {
                    foreach ($googleSheetRows as $rowData) {
                        $sheetService->appendRow($tabName, $rowData);
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Google Sheet Sync Error pada PO Update {$po->po_number}: " . $e->getMessage());
            }

            return redirect()->route('po.show', $po->po_number)->with('success', "Purchase Order berhasil diperbarui!");

        } catch (\Exception $e) {
            \DB::rollback();
            return back()->withInput()->with('error', 'Gagal update PO: ' . $e->getMessage());
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
                $bill->update(['status_id' => $this->getStatusId('pending')]);
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

            // Hapus dari Laporan Google Sheet karena tagihan ditolak/dihapus/void
            try {
                $sheetService = new \App\Services\GoogleSheetService();
                $sheetService->deleteRowsByBillNumber(env('GOOGLE_SHEET_OPEX_TAB_NAME', 'Sheet1'), $bill->bill_number);
            } catch (\Exception $e) {}

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
                if (\Storage::disk('public')->exists($attachment->file_path)) {
                    \Storage::disk('public')->delete($attachment->file_path);
                }
                \DB::table('bill_attachments')->where('id', $attachmentId)->delete();
            }
            return back()->with('success', 'File lampiran berhasil dihapus secara permanen!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus lampiran: ' . $e->getMessage());
        }
    }


    // =========================================================================
    // 3. REJECT / VOID / DESTROY (HAPUS DATA DARI GOOGLE SHEET)
    // =========================================================================
    public function destroy($slug)
    {
        DB::beginTransaction();
        try {
            $po = PurchaseOrder::where('po_number', $slug)->firstOrFail();
            $poNumber = $po->po_number;

            // Hapus dari Database
            $po->items()->delete();
            $po->histories()->delete();
            $po->delete();

            DB::commit();

            // 🔥 HAPUS DARI GOOGLE SHEET 🔥
            try {
                $sheetService = new GoogleSheetService();
                $tabName = env('GOOGLE_SHEET_PO_TAB_NAME', 'PO_Sheet');
                $sheetService->deleteRowsByBillNumber($tabName, $poNumber);
            } catch (\Exception $e) {
                \Log::error("Gagal menghapus baris PO dari sheet: " . $e->getMessage());
            }

            return redirect()->route('po.index')->with('success', 'PO berhasil dihapus sepenuhnya.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', $e->getMessage());
        }
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

            $bill->update([
                'status'    => 'APPROVED',
                'status_id' => $this->getStatusId('approved')
            ]);

            $this->logHistory($bill, 'PEMBAYARAN DIBATALKAN (VOID)', 'Pembayaran telah ditarik kembali/dibatalkan. Alasan: ' . $request->void_reason);

            // Hapus dari Laporan Google Sheet karena tagihan ditolak/dihapus/void
            try {
                $sheetService = new \App\Services\GoogleSheetService();
                $sheetService->deleteRowsByBillNumber(env('GOOGLE_SHEET_OPEX_TAB_NAME', 'Sheet1'), $bill->bill_number);
            } catch (\Exception $e) {}

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

            $statusSlug = strtolower(optional($bill->status)->slug);
            if (in_array($statusSlug, ['paid', 'lunas', 'void', 'cancelled', 'rejected', 'partial', 'partial_paid', 'dicicil']) || strtoupper($bill->status) === 'PAID') {
                return back()->with('error', 'Aksi Ditolak: Tagihan yang sudah memiliki riwayat pembayaran (Lunas/Dicicil) tidak dapat di-Void secara sepihak.');
            }

            \App\Models\DocumentApproval::where('document_id', $bill->id)
                ->whereIn('document_type', ['OPEX', 'App\Models\BillRequest'])
                ->delete();

            $bill->update([
                'status'    => 'VOID',
                'status_id' => $this->getStatusId('void') ?? $this->getStatusId('cancelled'),
                'rejection_reason' => 'VOIDED: ' . $request->void_reason
            ]);

            $this->logHistory($bill, 'TAGIHAN DIBATALKAN (VOID)', "Tagihan dibatalkan secara permanen oleh Sistem/Atasan. Alasan: " . $request->void_reason);

            // Hapus dari Laporan Google Sheet karena tagihan ditolak/dihapus/void
            try {
                $sheetService = new \App\Services\GoogleSheetService();
                $sheetService->deleteRowsByBillNumber(env('GOOGLE_SHEET_OPEX_TAB_NAME', 'Sheet1'), $bill->bill_number);
            } catch (\Exception $e) {}

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
        $bill = \App\Models\BillRequest::with([
            'status',
            'items',
            'company',
            'user',
            'histories.user',
            'charges.chargeType',
            'discounts.discountType'
        ])->where('bill_number', $slug)->firstOrFail();

        $attachments = \DB::table('bill_attachments')
            ->where('bill_request_id', $bill->id)
            ->get();

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

            $bill->update([
                'is_recurring' => false,
                'next_generation_date' => null
            ]);

            $this->logHistory($bill, 'STOP LANGGANAN', 'Siklus tagihan berulang telah dihentikan oleh pengguna. Sistem tidak akan meng-generate tagihan ini lagi di masa depan.');

            return back()->with('success', 'Siklus langganan berhasil dihentikan!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghentikan langganan: ' . $e->getMessage());
        }
    }



    // =========================================================================
    // CETAK BPR BIASA (TANPA MERGE PDF)
    // =========================================================================
    public function prinBpr(\Illuminate\Http\Request $request, $slug)
    {
        $type = $request->query('type', 'digital');
        $viewTemplate = $type === 'manual' ? 'bills.pdf_bpr_manual' : 'bills.pdf_bpr_digital';

        $bill = \App\Models\BillRequest::with(['items', 'user', 'company'])->where('bill_number', $slug)->firstOrFail();

        $attachments = \DB::table('bill_attachments')->where('bill_request_id', $bill->id)->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($viewTemplate, compact('bill', 'attachments'))
                ->setPaper('a4', 'portrait');

        $safeFilename = 'BPR_' . ucfirst($type) . '_' . str_replace(['/', '\\'], '_', $bill->bill_number) . '.pdf';

        return $pdf->stream($safeFilename);
    }

    // =========================================================================
    // CETAK BPR LENGKAP DENGAN LAMPIRAN (SMART MERGE / ANTI-BADAI)
    // =========================================================================
    public function printBprWithAttachments(\Illuminate\Http\Request $request, $slug)
    {
        $type = $request->query('type', 'digital');
        $viewTemplate = $type === 'manual' ? 'bills.pdf_bpr_manual' : 'bills.pdf_bpr_digital';

        $bill = \App\Models\BillRequest::with([
            'items', 'company', 'user', 'charges.chargeType', 'discounts.discountType'
        ])->where('bill_number', $slug)->firstOrFail();

        $attachments = \DB::table('bill_attachments')->where('bill_request_id', $bill->id)->get();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($viewTemplate, compact('bill', 'attachments'))
                ->setPaper('A4', 'portrait');

        $tempDir = storage_path('app/public/temp_pdf');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $tempMainPdfName = 'main_bpr_' . $bill->id . '_' . time() . '.pdf';
        $tempMainPdfPath = $tempDir . '/' . $tempMainPdfName;
        file_put_contents($tempMainPdfPath, $pdf->output());

        $oMerger = \Webklex\PDFMerger\Facades\PDFMergerFacade::init();
        $oMerger->addPDF($tempMainPdfPath, 'all');

        $tempFilesToDelete = [$tempMainPdfPath];
        $totalLampiranDiDatabase = 0;

        if ($attachments && $attachments->count() > 0) {
            $totalLampiranDiDatabase = $attachments->count();

            foreach ($attachments as $file) {
                $cleanFilePath = ltrim($file->file_path, '/');
                $finalFilePath = storage_path('app/public/' . $cleanFilePath);

                if (file_exists($finalFilePath)) {
                    $extension = strtolower(pathinfo($finalFilePath, PATHINFO_EXTENSION));

                    if ($extension === 'pdf') {
                        try {
                            $fpdi = new \setasign\Fpdi\Fpdi();
                            $fpdi->setSourceFile($finalFilePath);
                            $oMerger->addPDF($finalFilePath, 'all');
                        } catch (\Exception $e) {
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
                    } elseif (in_array($extension, ['jpg', 'jpeg', 'png'])) {
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
                    } else {
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
                } else {
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

        if ($totalLampiranDiDatabase === 0) {
            $noDataHtml = "<div style='border: 2px solid orange; padding: 20px; font-family: sans-serif; text-align:center; margin-top:50px;'>
                            <h2 style='color: orange;'>⚠️ INFO SISTEM ⚠️</h2><p>TIDAK ADA DATA LAMPIRAN untuk Tagihan ini.</p></div>";
            $noDataPdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($noDataHtml)->setPaper('a4', 'portrait');
            $noDataTempPath = $tempDir . '/err_nodata_' . uniqid() . '.pdf';
            file_put_contents($noDataTempPath, $noDataPdf->output());
            $oMerger->addPDF($noDataTempPath, 'all');
            $tempFilesToDelete[] = $noDataTempPath;
        }

        $oMerger->merge();
        $finalPdfOutput = $oMerger->output();

        foreach ($tempFilesToDelete as $trashPath) {
            if (file_exists($trashPath)) {
                unlink($trashPath);
            }
        }

        $filename = 'BPR_' . ucfirst($type) . '_Lampiran_' . str_replace(['/', '\\'], '_', $bill->bill_number) . '.pdf';

        return response($finalPdfOutput)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    public function printBprManual($slug) {
        $bill = \App\Models\BillRequest::with(['items', 'user', 'company'])->where('bill_number', $slug)->firstOrFail();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('bills.pdf_bpr_manual', compact('bill'))->setPaper('a4', 'portrait');
        return $pdf->stream('BPR_Manual_' . str_replace('/', '_', $bill->bill_number) . '.pdf');
    }

    public function printBprDigital($slug) {
        $bill = \App\Models\BillRequest::with(['items', 'user', 'company'])->where('bill_number', $slug)->firstOrFail();
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('bills.pdf_bpr_digital', compact('bill'))->setPaper('a4', 'portrait');
        return $pdf->stream('BPR_Digital_' . str_replace('/', '_', $bill->bill_number) . '.pdf');
    }
}
