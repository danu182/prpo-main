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
    // public function create()
    // {
    //     $companies = \App\Models\Company::all(); // Data PT
    //     $taxes = \App\Models\Tax::where('is_active', true)->orderBy('name')->get(); // Data Pajak
    //     $currencies = \App\Models\Currency::where('is_active', true)->orderBy('name')->get(); // Data Pajak

    //     return view('bills.create', compact('companies', 'taxes','currencies'));
    // }



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


    // --- 3. STORE (SIMPAN DATA) ---
    // public function store(Request $request)
    // {
    //     // 1. Validasi Input
    //     $request->validate([
    //         'paid_by_company_id'    => 'required|exists:companies,id',
    //         'currency_id'           => 'required|exists:currencies,id',
    //         'bill_date'             => 'required|date',
    //         'vendor_name'           => 'required|string|max:255',
    //         'items'                 => 'required|array|min:1',
    //         'is_recurring'          => 'required|boolean',
    //         'recurring_interval'    => 'nullable|required_if:is_recurring,1|numeric|min:1',
    //         'recurring_period'      => 'nullable|required_if:is_recurring,1|in:days,weeks,months,years',
    //     ]);

    //     DB::beginTransaction();
    //     try {
    //         $currency = \App\Models\Currency::findOrFail($request->currency_id);
    //         $billNumber = $this->generateBillNumber($request->paid_by_company_id);

    //         $bill = new \App\Models\BillRequest();
    //         $bill->bill_number      = $billNumber;
    //         $bill->user_id          = Auth::id();
    //         $bill->company_id       = $request->paid_by_company_id;
    //         $bill->vendor_name      = $request->vendor_name;
    //         $bill->invoice_date     = $request->bill_date;
    //         $bill->currency         = $currency->code;
    //         $bill->description      = $request->note;
    //         $bill->status           = 'PENDING';
    //         $bill->type             = 'Non-Project';
    //         $bill->category         = 'Operasional';
    //         $bill->title            = 'Tagihan ' . $request->vendor_name;
    //         $bill->due_date         = $request->due_date;
    //         $bill->current_approval_level = 1;

    //         // Inisialisasi awal
    //         $bill->amount           = 0;
    //         $bill->total_discount   = 0;

    //         // Logika Recurring
    //         if ($request->is_recurring == '1') {
    //             $bill->is_recurring = true;
    //             $bill->recurring_period = $request->recurring_period;
    //             $bill->recurring_interval = (int) $request->recurring_interval;
    //             $bill->next_generation_date = \Carbon\Carbon::parse($request->bill_date)
    //                 ->add($request->recurring_period, (int)$request->recurring_interval);
    //         }

    //         $bill->save();

    //         // --- PERBAIKAN LOGIKA ITEM & PAJAK ---
    //         $grandTotal = 0;

    //         foreach ($request->items as $itemData) {
    //             $qty = (float) $itemData['qty'];
    //             $price = (float) $itemData['price'];
    //             $subtotalKotor = $qty * $price;

    //             // Ambil data pajak dari master pajak
    //             $taxRate = 0;
    //             if (!empty($itemData['tax_id'])) {
    //                 $tax = \App\Models\Tax::find($itemData['tax_id']);
    //                 $taxRate = $tax ? $tax->percent : 0;
    //             }

    //             // Hitung Pajak
    //             $taxAmount = ($subtotalKotor * $taxRate) / 100;
    //             $subtotalAkhir = $subtotalKotor + $taxAmount;

    //             // Simpan ke bill_items sesuai struktur image_963395
    //             $bill->items()->create([
    //                 'name'                 => $itemData['name'],
    //                 'description'          => $itemData['description'] ?? null,
    //                 'qty'                  => $qty,
    //                 'price'                => $price,
    //                 'amount'               => $price, // Harga satuan
    //                 'tax_id'               => $itemData['tax_id'] ?? null,
    //                 'tax_percent_snapshot' => $taxRate,
    //                 'tax_amount'           => $taxAmount, // Ini yang menyimpan 111 (jika 11% dari 1000)
    //                 'subtotal'             => $subtotalAkhir, // Ini yang menyimpan 1111
    //             ]);

    //             $grandTotal += $subtotalAkhir;
    //         }

    //         // Update Total di Header (amount sekarang termasuk pajak)
    //         $bill->amount = $grandTotal;
    //         $bill->save();

    //         // Simpan Lampiran
    //         if ($request->hasFile('attachments')) {
    //             foreach ($request->file('attachments') as $file) {
    //                 $bill->addMedia($file)->toMediaCollection('bill_attachments');
    //             }
    //         }

    //         $this->logHistory($bill, 'CREATED', "Membuat tagihan baru No: {$bill->bill_number}");

    //         DB::commit();
    //         return redirect()->route('bills.index')->with('success', 'Tagihan berhasil disimpan.');

    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         return back()->withInput()->with('error', 'Gagal menyimpan: ' . $e->getMessage());
    //     }
    // }


    // public function store(Request $request)
    // {
    //     // 1. VALIDASI INPUT DASAR
    //     $request->validate([
    //         'paid_by_company_id' => 'required|exists:companies,id',
    //         'currency_id'        => 'required|exists:currencies,id',
    //         'bill_date'          => 'required|date',
    //         'due_date'           => 'required|date|after_or_equal:bill_date',
    //         'vendor_name'        => 'required|string|max:255',
    //         'items'              => 'required|array|min:1',
    //         'items.*.name'       => 'required|string',
    //         'items.*.qty'        => 'required|numeric|min:1',
    //         'items.*.price'      => 'required|numeric|min:0',
    //         'charges.*.amount'   => 'nullable|numeric|min:0',
    //         'discounts.*.amount' => 'nullable|numeric|min:0',
    //         'attachments.*'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
    //     ]);

    //     \DB::beginTransaction();
    //     try {
    //         // 2. GENERATE NOMOR TAGIHAN OTOMATIS (Contoh: BILL/OPX/2026/03/0001)
    //         $company = \App\Models\Company::find($request->paid_by_company_id);
    //         $companyCode = $company ? ($company->code ?? 'GEN') : 'GEN';
    //         $monthYear = \Carbon\Carbon::parse($request->bill_date)->format('Y/m');

    //         $prefix = "BILL/OPX/{$companyCode}/{$monthYear}/";
    //         $lastBill = \App\Models\BillRequest::where('bill_number', 'like', $prefix . '%')
    //                                            ->lockForUpdate()
    //                                            ->orderBy('id', 'desc')
    //                                            ->first();

    //         $newNumber = $lastBill ? ((int) substr($lastBill->bill_number, -4) + 1) : 1;
    //         $billNumber = $prefix . sprintf('%04d', $newNumber);

    //         // Ambil Kode Mata Uang
    //         $currency = \App\Models\Currency::find($request->currency_id)->code ?? 'IDR';

    //         // 3. INISIALISASI VARIABEL PERHITUNGAN
    //         $totalSubtotal = 0; // Total (Qty x Harga) seluruh item
    //         $totalItemDisc = 0; // Total diskon yang ada di baris item
    //         $totalTax      = 0; // Total pajak dari item
    //         $totalCharge   = 0; // Total biaya tambahan
    //         $totalExtDisc  = 0; // Total potongan tambahan (Global)

    //         // 4. BUAT DRAFT INDUK (BILL REQUEST) DULU UNTUK MENDAPATKAN ID
    //         $bill = \App\Models\BillRequest::create([
    //             'bill_number'        => $billNumber,
    //             'title'              => 'Tagihan Opex - ' . $request->vendor_name,
    //             'user_id'            => auth()->id(),
    //             'company_id'         => $request->paid_by_company_id,
    //             'type'               => 'OPEX', // Tandai sebagai OPEX
    //             'vendor_name'        => $request->vendor_name,
    //             'description'        => $request->note,
    //             'invoice_date'       => $request->bill_date,
    //             'due_date'           => $request->due_date,
    //             'currency'           => $currency,
    //             'status'             => 'PENDING', // Atau 'APPROVED' jika langsung potong hutang

    //             // Set default 0 dulu, akan diupdate setelah looping
    //             'subtotal'           => 0,
    //             'total_discount'     => 0,
    //             'total_tax'          => 0,
    //             'total_charge'       => 0,
    //             'amount'             => 0,

    //            // Pengaturan Recurring (Berulang)
    //             'is_recurring'       => $request->is_recurring == '1' ? true : false,
    //             'recurring_interval' => $request->is_recurring == '1' ? (int) $request->recurring_interval : null,
    //             'recurring_period'   => $request->is_recurring == '1' ? $request->recurring_period : null,
    //             'next_generation_date'=> $request->is_recurring == '1' ? \Carbon\Carbon::parse($request->bill_date)->add((int) $request->recurring_interval, $request->recurring_period) : null,            ]);

    //         // 5. LOOPING & SIMPAN BARIS ITEM
    //         foreach ($request->items as $item) {
    //             $qty = $item['qty'];
    //             $price = $item['price'];
    //             $gross = $qty * $price;

    //             // Hitung Diskon Item
    //             $discVal = $item['discount_value'] ?? 0;
    //             $discType = $item['discount_type'] ?? 'fixed';
    //             $discAmount = ($discType == 'percent') ? ($gross * $discVal / 100) : $discVal;

    //             // Hitung Pajak Item (DPP = Gross - Diskon)
    //             $dpp = $gross - $discAmount;
    //             $taxPercent = 0;
    //             if (!empty($item['tax_id'])) {
    //                 $taxData = \App\Models\Tax::find($item['tax_id']);
    //                 $taxPercent = $taxData ? $taxData->percent : 0;
    //             }
    //             $taxAmount = $dpp * ($taxPercent / 100);

    //             // Subtotal Baris Bersih
    //             $rowSubtotal = $dpp + $taxAmount;

    //             // Masukkan ke Database `bill_items`
    //             $bill->items()->create([
    //                 'name'                 => $item['name'],
    //                 'description'          => $item['description'],
    //                 'qty'                  => $qty,
    //                 'price'                => $price,
    //                 'amount'               => $rowSubtotal,
    //                 'discount_type'        => $discType,
    //                 'discount_value'       => $discVal,
    //                 'discount_amount'      => $discAmount,
    //                 'tax_id'               => $item['tax_id'] ?? null,
    //                 'tax_percent_snapshot' => $taxPercent,
    //                 'tax_amount'           => $taxAmount,
    //                 'subtotal'             => $gross,
    //             ]);

    //             // Akumulasi ke Grand Total
    //             $totalSubtotal += $gross;
    //             $totalItemDisc += $discAmount;
    //             $totalTax      += $taxAmount;
    //         }

    //         // 6. LOOPING & SIMPAN BIAYA TAMBAHAN (CHARGES)
    //         if ($request->has('charges') && is_array($request->charges)) {
    //             foreach ($request->charges as $charge) {
    //                 if (!empty($charge['charge_type_id']) && $charge['amount'] > 0) {
    //                     $bill->charges()->create([
    //                         'charge_type_id' => $charge['charge_type_id'],
    //                         'amount'         => $charge['amount'],
    //                         'note'           => $charge['note'] ?? null,
    //                     ]);
    //                     $totalCharge += $charge['amount'];
    //                 }
    //             }
    //         }

    //         // 7. LOOPING & SIMPAN POTONGAN TAMBAHAN (DISCOUNTS)
    //         if ($request->has('discounts') && is_array($request->discounts)) {
    //             foreach ($request->discounts as $discount) {
    //                 if (!empty($discount['discount_type_id']) && $discount['amount'] > 0) {
    //                     $bill->discounts()->create([
    //                         'discount_type_id' => $discount['discount_type_id'],
    //                         'amount'           => $discount['amount'],
    //                         'note'             => $discount['note'] ?? null,
    //                     ]);
    //                     $totalExtDisc += $discount['amount'];
    //                 }
    //             }
    //         }

    //         // 8. HITUNG GRAND TOTAL AKHIR & UPDATE INDUK
    //         // Rumus: (Total Harga Barang - Diskon Item + Pajak) + Biaya Tambahan - Potongan Global
    //         $dppGlobal = $totalSubtotal - $totalItemDisc;
    //         $grandTotal = $dppGlobal + $totalTax + $totalCharge - $totalExtDisc;

    //         // Cegah minus jika diskon terlalu besar
    //         if ($grandTotal < 0) { $grandTotal = 0; }

    //         $bill->update([
    //             'subtotal'       => $totalSubtotal,
    //             'total_discount' => $totalItemDisc + $totalExtDisc, // Gabungan diskon item & diskon global
    //             'total_tax'      => $totalTax,
    //             'total_charge'   => $totalCharge,
    //             'amount'         => $grandTotal,
    //         ]);

    //         // 9. UPLOAD LAMPIRAN (MediaLibrary Spatie)
    //         if ($request->hasFile('attachments')) {
    //             foreach ($request->file('attachments') as $file) {
    //                 $bill->addMedia($file)->toMediaCollection('bill_attachments', 'public');
    //             }
    //         }

    //         // 10. CATAT HISTORY AUDIT TRAIL
    //         \App\Models\History::create([
    //             'user_id'     => auth()->id(),
    //             'record_type' => \App\Models\BillRequest::class,
    //             'record_id'   => $bill->id,
    //             'action'      => 'CREATED',
    //             'note'        => "Tagihan {$billNumber} berhasil dibuat dengan nilai {$currency} " . number_format($grandTotal, 0, ',', '.')
    //         ]);

    //         \DB::commit();

    //         return redirect()->route('bills.index')->with('success', "Tagihan Opex berhasil disimpan! Nomor: {$billNumber}");

    //     } catch (\Exception $e) {
    //         \DB::rollback();
    //         return back()->withInput()->with('error', 'Gagal menyimpan tagihan: ' . $e->getMessage());
    //     }
    // }


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

//    public function show($id)
//     {
//         // PENTING: Tambahkan relasi charges.chargeType dan discounts.discountType
//         $bill = \App\Models\BillRequest::with([
//             'items',
//             'company',
//             'user',
//             'histories.user',
//             'media',
//             'charges.chargeType',      // Relasi baru
//             'discounts.discountType'   // Relasi baru
//         ])->findOrFail($id);

//         return view('bills.show', compact('bill'));
//     }

    // --- 1. HALAMAN EDIT ---
    // --- FORM EDIT ---
    // public function edit($id)
    // {
    //     // 1. Tarik tagihan beserta anak-anaknya
    //     $bill = \App\Models\BillRequest::with(['items', 'charges', 'discounts'])->findOrFail($id);

    //     // Validasi: Hanya bisa diedit jika statusnya PENDING atau DRAFT
    //     if (!in_array($bill->status, ['PENDING', 'DRAFT'])) {
    //         return back()->with('error', 'Tagihan yang sudah disetujui atau diproses tidak dapat diedit!');
    //     }

    //     // 2. Tarik Master Data untuk Dropdown
    //     $companies   = \App\Models\Company::all();
    //     $taxes       = \App\Models\Tax::where('is_active', true)->orderBy('name')->get();
    //     $currencies  = \App\Models\Currency::where('is_active', true)->orderBy('name')->get();
    //     $vendors     = \App\Models\Vendor::orderBy('name')->get();

    //     $opexItems   = \App\Models\Item::where('is_stockable', false)->where('is_asset', false)->orderBy('name')->get();
    //     $chargeTypes = \App\Models\ChargeType::where('is_active', true)->orderBy('name')->get();
    //     $discountTypes = \App\Models\DiscountType::where('is_active', true)->orderBy('name')->get();

    //     return view('bills.edit', compact('bill', 'companies', 'taxes', 'currencies', 'vendors', 'opexItems', 'chargeTypes', 'discountTypes'));
    // }

    // --- PROSES UPDATE TAGIHAN ---
    // public function update(Request $request, $id)
    // {
    //     // 1. VALIDASI KETAT (Sama dengan Store)
    //     $request->validate([
    //         'paid_by_company_id' => 'required|exists:companies,id',
    //         'currency_id'        => 'required|exists:currencies,id',
    //         'bill_date'          => 'required|date',
    //         'due_date'           => 'required|date|after_or_equal:bill_date',
    //         'vendor_name'        => 'required|string|max:255',
    //         'items'              => 'required|array|min:1',
    //         'items.*.name'       => 'required|string',
    //         'items.*.qty'        => 'required|numeric|min:1',
    //         'items.*.price'      => 'required|numeric|min:0',
    //         'charges.*.amount'   => 'nullable|numeric|min:0',
    //         'discounts.*.amount' => 'nullable|numeric|min:0',
    //         'attachments.*'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
    //     ]);

    //     \DB::beginTransaction();
    //     try {
    //         $bill = \App\Models\BillRequest::findOrFail($id);

    //         // Validasi Keamanan: Jika sudah lunas/dibayar, HARAM diedit!
    //         if (in_array($bill->status, ['PAID', 'PARTIAL'])) {
    //             return back()->with('error', 'Gagal! Tagihan ini sudah memiliki riwayat pembayaran.');
    //         }

    //         // 2. UPDATE HEADER & RECURRING
    //         $currency = \App\Models\Currency::find($request->currency_id)->code ?? 'IDR';

    //         $bill->company_id  = $request->paid_by_company_id;
    //         $bill->vendor_name = $request->vendor_name;
    //         $bill->description = $request->note;
    //         $bill->invoice_date= $request->bill_date;
    //         $bill->due_date    = $request->due_date;
    //         $bill->currency    = $currency;

    //         // Update Logika Recurring (Berulang)
    //         if ($request->is_recurring == '1') {
    //             $bill->is_recurring = true;
    //             $bill->recurring_interval = (int) $request->recurring_interval;
    //             $bill->recurring_period = $request->recurring_period;
    //             $bill->next_generation_date = \Carbon\Carbon::parse($request->bill_date)->add((int) $request->recurring_interval, $request->recurring_period);
    //         } else {
    //             $bill->is_recurring = false;
    //             $bill->recurring_interval = null;
    //             $bill->recurring_period = null;
    //             $bill->next_generation_date = null;
    //         }

    //         // 3. INISIALISASI VARIABEL PERHITUNGAN ULANG
    //         $totalSubtotal = 0;
    //         $totalItemDisc = 0;
    //         $totalTax      = 0;
    //         $totalCharge   = 0;
    //         $totalExtDisc  = 0;

    //         // 4. RESET (HAPUS) DATA LAMA - STRATEGI PALING AMAN DI ERP
    //         $bill->items()->delete();
    //         $bill->charges()->delete();
    //         $bill->discounts()->delete();

    //         // 5. LOOPING & SIMPAN BARIS ITEM BARU
    //         foreach ($request->items as $item) {
    //             $qty = (float) $item['qty'];
    //             $price = (float) $item['price'];
    //             $gross = $qty * $price;

    //             $discVal = (float) ($item['discount_value'] ?? 0);
    //             $discType = $item['discount_type'] ?? 'fixed';
    //             $discAmount = ($discType == 'percent') ? ($gross * $discVal / 100) : $discVal;

    //             $dpp = $gross - $discAmount;
    //             $taxPercent = 0;
    //             if (!empty($item['tax_id'])) {
    //                 $taxData = \App\Models\Tax::find($item['tax_id']);
    //                 $taxPercent = $taxData ? (float) $taxData->percent : 0;
    //             }
    //             $taxAmount = $dpp * ($taxPercent / 100);

    //             $rowSubtotal = $dpp + $taxAmount;

    //             $bill->items()->create([
    //                 'name'                 => $item['name'],
    //                 'description'          => $item['description'],
    //                 'qty'                  => $qty,
    //                 'price'                => $price,
    //                 'amount'               => $rowSubtotal,
    //                 'discount_type'        => $discType,
    //                 'discount_value'       => $discVal,
    //                 'discount_amount'      => $discAmount,
    //                 'tax_id'               => $item['tax_id'] ?? null,
    //                 'tax_percent_snapshot' => $taxPercent,
    //                 'tax_amount'           => $taxAmount,
    //                 'subtotal'             => $gross,
    //             ]);

    //             $totalSubtotal += $gross;
    //             $totalItemDisc += $discAmount;
    //             $totalTax      += $taxAmount;
    //         }

    //         // 6. LOOPING & SIMPAN BIAYA TAMBAHAN (CHARGES)
    //         if ($request->has('charges') && is_array($request->charges)) {
    //             foreach ($request->charges as $charge) {
    //                 $chargeAmt = (float) ($charge['amount'] ?? 0);
    //                 if (!empty($charge['charge_type_id']) && $chargeAmt > 0) {
    //                     $bill->charges()->create([
    //                         'charge_type_id' => $charge['charge_type_id'],
    //                         'amount'         => $chargeAmt,
    //                         'note'           => $charge['note'] ?? null,
    //                     ]);
    //                     $totalCharge += $chargeAmt;
    //                 }
    //             }
    //         }

    //         // 7. LOOPING & SIMPAN POTONGAN TAMBAHAN (DISCOUNTS)
    //         if ($request->has('discounts') && is_array($request->discounts)) {
    //             foreach ($request->discounts as $discount) {
    //                 $discAmt = (float) ($discount['amount'] ?? 0);
    //                 if (!empty($discount['discount_type_id']) && $discAmt > 0) {
    //                     $bill->discounts()->create([
    //                         'discount_type_id' => $discount['discount_type_id'],
    //                         'amount'           => $discAmt,
    //                         'note'             => $discount['note'] ?? null,
    //                     ]);
    //                     $totalExtDisc += $discAmt;
    //                 }
    //             }
    //         }

    //         // 8. HITUNG GRAND TOTAL AKHIR & UPDATE INDUK
    //         $dppGlobal = $totalSubtotal - $totalItemDisc;
    //         $grandTotal = $dppGlobal + $totalTax + $totalCharge - $totalExtDisc;

    //         if ($grandTotal < 0) { $grandTotal = 0; }

    //         $bill->subtotal       = $totalSubtotal;
    //         $bill->total_discount = $totalItemDisc + $totalExtDisc;
    //         $bill->total_tax      = $totalTax;
    //         $bill->total_charge   = $totalCharge;
    //         $bill->amount         = $grandTotal;
    //         $bill->save();

    //         // 9. UPLOAD LAMPIRAN BARU (Jika ada)
    //         if ($request->hasFile('attachments')) {
    //             foreach ($request->file('attachments') as $file) {
    //                 $bill->addMedia($file)->toMediaCollection('bill_attachments', 'public');
    //             }
    //         }

    //         // 10. HAPUS LAMPIRAN LAMA (Jika user mencentang checkbox hapus)
    //         if ($request->has('delete_media') && is_array($request->delete_media)) {
    //             // Menggunakan Spatie Media Library delete() agar file fisik di storage juga ikut terhapus
    //             $mediaItems = $bill->getMedia('bill_attachments')->whereIn('id', $request->delete_media);
    //             foreach ($mediaItems as $media) {
    //                 $media->delete();
    //             }
    //         }

    //         // 11. CATAT HISTORY AUDIT TRAIL
    //         \App\Models\History::create([
    //             'user_id'     => auth()->id(),
    //             'record_type' => \App\Models\BillRequest::class,
    //             'record_id'   => $bill->id,
    //             'action'      => 'UPDATED',
    //             'note'        => "Melakukan revisi Tagihan. Grand Total Baru: {$currency} " . number_format($grandTotal, 0, ',', '.')
    //         ]);

    //         \DB::commit();

    //         return redirect()->route('bills.show', $bill->id)->with('success', "Tagihan Opex berhasil diperbarui!");

    //     } catch (\Exception $e) {
    //         \DB::rollback();
    //         return back()->withInput()->with('error', 'Gagal update tagihan: ' . $e->getMessage());
    //     }
    // }

    // --- 3. HAPUS FILE LAMPIRAN (VIA AJAX/DIRECT) ---
    public function destroyAttachment($id, $mediaId)
    {
        $bill = BillRequest::findOrFail($id);

        // Cari media spesifik
        $media = $bill->getMedia('bill_proofs')->where('id', $mediaId)->first();

        if ($media) {
            $media->delete();
            return back()->with('success', 'File lampiran berhasil dihapus.');
        }

        return back()->with('error', 'File tidak ditemukan.');
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


    // =========================================================================
    // PERSETUJUAN WORKFLOW DINAMIS (APPROVE & REJECT)
    // =========================================================================
    public function approve($id)
    {
        \DB::beginTransaction();
        try {
            $bill = \App\Models\BillRequest::with('status')->findOrFail($id);

            // 1. Cek apakah status masih PENDING
            if ($bill->status && $bill->status->slug !== 'pending') {
                return back()->with('error', 'Tagihan ini sudah diproses sebelumnya.');
            }

            // 2. Cari antrean Approval Saat Ini (Berdasarkan urutan / step_order)
            $currentApproval = \App\Models\DocumentApproval::with('role')
                ->where('document_id', $bill->id)
                ->where('document_type', get_class($bill))
                ->where('status', 'PENDING')
                ->orderBy('step_order', 'asc')
                ->first();

            if (!$currentApproval) {
                return back()->with('error', 'Tidak ada antrean persetujuan untuk Anda pada dokumen ini.');
            }

            $approverRoleName = $currentApproval->role ? $currentApproval->role->name : 'Atasan';

            // 3. Eksekusi Persetujuan Tahap Ini
            $currentApproval->update([
                'status'      => 'APPROVED',
                'approved_by' => auth()->id(),
                'approved_at' => now()
            ]);

            // Naikkan Level Dokumen sesuai Step yang baru saja di-ACC
            $bill->update(['current_approval_level' => $currentApproval->step_order]);

            // 4. Cek apakah MASIH ADA atasan selanjutnya di antrean?
            $nextApproval = \App\Models\DocumentApproval::with('role')
                ->where('document_id', $bill->id)
                ->where('document_type', get_class($bill))
                ->where('status', 'PENDING')
                ->orderBy('step_order', 'asc')
                ->first();

            $actionText = 'Disetujui (' . strtoupper($approverRoleName) . ')';
            $catatan = "Tagihan disetujui pada tahap ini.\n";

            if ($nextApproval) {
                // JIKA MASIH ADA ATASAN: Status tetap PENDING, oper ke atasan berikutnya
                $nextRoleName = $nextApproval->role ? $nextApproval->role->name : 'Atasan Berikutnya';
                $catatan .= "Diteruskan ke: **" . strtoupper($nextRoleName) . "**\n";
                $successMsg = "Disetujui! Dokumen telah diteruskan ke {$nextRoleName}.";
            } else {
                // JIKA FINAL (Semua tahapan tuntas)
                $bill->update(['status_id' => $this->getStatusId('approved')]); // Status jadi Siap Bayar
                $actionText = 'Disetujui Final';
                $catatan .= "Persetujuan Matriks telah SELESAI. Tagihan siap dibayarkan oleh Finance.\n";
                $successMsg = "Hore! Tagihan OPEX telah disetujui secara FINAL!";
            }

            // Catat Log Audit
            $this->logHistory($bill, $actionText, $catatan);

            \DB::commit();
            return back()->with('success', $successMsg);

        } catch (\Exception $e) {
            \DB::rollback();
            return back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }


    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|min:5',
        ]);

        \DB::beginTransaction();
        try {
            $bill = \App\Models\BillRequest::with('status')->findOrFail($id);

            if ($bill->status && $bill->status->slug !== 'pending') {
                return back()->with('error', 'Tagihan ini sudah diproses sebelumnya.');
            }

            $currentApproval = \App\Models\DocumentApproval::with('role')
                ->where('document_id', $bill->id)
                ->where('document_type', get_class($bill))
                ->where('status', 'PENDING')
                ->orderBy('step_order', 'asc')
                ->first();

            $approverRoleName = $currentApproval && $currentApproval->role ? $currentApproval->role->name : 'Atasan';

            if ($currentApproval) {
                $currentApproval->update([
                    'status'      => 'REJECTED',
                    'approved_by' => auth()->id(),
                    'approved_at' => now()
                ]);
            }

            // UPDATE STATUS TAGIHAN JADI REJECTED & RESET LEVEL KEMBALI KE 0
            $bill->status_id = $this->getStatusId('rejected');
            $bill->rejection_reason = $request->rejection_reason;
            $bill->current_approval_level = 0;
            $bill->save();

            // Catat Log
            $this->logHistory($bill, 'Ditolak', "Menolak Tagihan ({$approverRoleName}). Alasan: {$request->rejection_reason}");

            \DB::commit();
            return back()->with('error', 'Tagihan OPEX telah ditolak dan dikembalikan.');

        } catch (\Exception $e) {
            \DB::rollback();
            return back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }



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





    public function destroy($id)
    {
        $bill = \App\Models\BillRequest::findOrFail($id);

        // 1. Cek Status (Safety)
        if ($bill->status != 'PENDING' && $bill->status != 'REJECTED') {
            return back()->with('error', 'Hanya tagihan PENDING/REJECTED yang boleh dihapus.');
        }

        \DB::beginTransaction();
        try {
            // A. Hapus Semua Item (Wajib)
            $bill->items()->delete();

            // B. Hapus History / Audit Trail (Wajib)
            $bill->histories()->delete();

            // C. Hapus File Fisik di Server (Wajib jika pakai Spatie Media Library)
            // Ini akan menghapus file JPG/PDF dari folder storage
            $bill->clearMediaCollection('bill_attachments');

            // D. Terakhir, Hapus Header Tagihan
            $bill->delete();

            \DB::commit();
            return redirect()->route('bills.index')->with('success', 'Tagihan beserta item dan lampirannya berhasil dihapus.');

        } catch (\Exception $e) {
            \DB::rollback();
            return back()->with('error', 'Gagal hapus: ' . $e->getMessage());
        }
    }


    // --- CETAK PDF INVOICE OPEX ---
    public function printPdf($id)
    {
        // 1. Tarik semua data relasi yang dibutuhkan
        $bill = \App\Models\BillRequest::with([
            'items',
            'company',
            'user',
            'charges.chargeType',
            'discounts.discountType'
        ])->findOrFail($id);

        // 2. Load View khusus PDF (Kita buat di langkah 3)
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('bills.print_pdf', compact('bill'));

        // 3. Set ukuran kertas (A4 Portrait)
        $pdf->setPaper('A4', 'portrait');

        // 4. Return stream agar langsung terbuka di browser (tidak langsung terdownload)
        $fileName = 'Tagihan_Opex_' . str_replace('/', '_', $bill->bill_number) . '.pdf';
        return $pdf->stream($fileName);
    }


    public function store(Request $request)
    {
        // 1. VALIDASI INPUT DASAR
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
            'charges.*.amount'   => 'nullable|numeric|min:0',
            'discounts.*.amount' => 'nullable|numeric|min:0',
            'attachments.*'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        \DB::beginTransaction();
        try {
            // 2. GENERATE NOMOR TAGIHAN OTOMATIS (Contoh: BILL/OPX/2026/03/0001)
            $company = \App\Models\Company::find($request->paid_by_company_id);
            $companyCode = $company ? ($company->code ?? 'GEN') : 'GEN';
            $monthYear = \Carbon\Carbon::parse($request->bill_date)->format('Y/m');

            $prefix = "BILL/OPX/{$companyCode}/{$monthYear}/";
            $lastBill = \App\Models\BillRequest::where('bill_number', 'like', $prefix . '%')
                                               ->lockForUpdate()
                                               ->orderBy('id', 'desc')
                                               ->first();

            $newNumber = $lastBill ? ((int) substr($lastBill->bill_number, -4) + 1) : 1;
            $billNumber = $prefix . sprintf('%04d', $newNumber);

            // Ambil Kode Mata Uang
            $currency = \App\Models\Currency::find($request->currency_id)->code ?? 'IDR';

            // 3. INISIALISASI VARIABEL PERHITUNGAN
            $totalSubtotal = 0; // Total (Qty x Harga) seluruh item
            $totalItemDisc = 0; // Total diskon yang ada di baris item
            $totalTax      = 0; // Total pajak dari item
            $totalCharge   = 0; // Total biaya tambahan
            $totalExtDisc  = 0; // Total potongan tambahan (Global)

            // 4. BUAT DRAFT INDUK DENGAN STATUS ID (PENDING)
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

                // MENGGUNAKAN RELASI STATUS_ID
                'status_id'          => $this->getStatusId('pending'),

                'subtotal'           => 0,
                'total_discount'     => 0,
                'total_tax'          => 0,
                'total_charge'       => 0,
                'amount'             => 0,

               // Pengaturan Recurring (Berulang)
                'is_recurring'       => $request->is_recurring == '1' ? true : false,
                'recurring_interval' => $request->is_recurring == '1' ? (int) $request->recurring_interval : null,
                'recurring_period'   => $request->is_recurring == '1' ? $request->recurring_period : null,
                'next_generation_date'=> $request->is_recurring == '1' ? \Carbon\Carbon::parse($request->bill_date)->add((int) $request->recurring_interval, $request->recurring_period) : null,
            ]);

            // 5. LOOPING & SIMPAN BARIS ITEM
            foreach ($request->items as $item) {
                $qty = $item['qty'];
                $price = $item['price'];
                $gross = $qty * $price;

                // Hitung Diskon Item
                $discVal = $item['discount_value'] ?? 0;
                $discType = $item['discount_type'] ?? 'fixed';
                $discAmount = ($discType == 'percent') ? ($gross * $discVal / 100) : $discVal;

                // Hitung Pajak Item (DPP = Gross - Diskon)
                $dpp = $gross - $discAmount;
                $taxPercent = 0;
                if (!empty($item['tax_id'])) {
                    $taxData = \App\Models\Tax::find($item['tax_id']);
                    $taxPercent = $taxData ? $taxData->percent : 0;
                }
                $taxAmount = $dpp * ($taxPercent / 100);

                // Subtotal Baris Bersih
                $rowSubtotal = $dpp + $taxAmount;

                // Masukkan ke Database `bill_items`
                $bill->items()->create([
                    'name'                 => $item['name'],
                    'description'          => $item['description'],
                    'qty'                  => $qty,
                    'price'                => $price,
                    'amount'               => $rowSubtotal,
                    'discount_type'        => $discType,
                    'discount_value'       => $discVal,
                    'discount_amount'      => $discAmount,
                    'tax_id'               => $item['tax_id'] ?? null,
                    'tax_percent_snapshot' => $taxPercent,
                    'tax_amount'           => $taxAmount,
                    'subtotal'             => $gross,
                ]);

                // Akumulasi ke Grand Total
                $totalSubtotal += $gross;
                $totalItemDisc += $discAmount;
                $totalTax      += $taxAmount;
            }

            // 6. LOOPING & SIMPAN BIAYA TAMBAHAN (CHARGES)
            if ($request->has('charges') && is_array($request->charges)) {
                foreach ($request->charges as $charge) {
                    if (!empty($charge['charge_type_id']) && $charge['amount'] > 0) {
                        $bill->charges()->create([
                            'charge_type_id' => $charge['charge_type_id'],
                            'amount'         => $charge['amount'],
                            'note'           => $charge['note'] ?? null,
                        ]);
                        $totalCharge += $charge['amount'];
                    }
                }
            }

            // 7. LOOPING & SIMPAN POTONGAN TAMBAHAN (DISCOUNTS)
            if ($request->has('discounts') && is_array($request->discounts)) {
                foreach ($request->discounts as $discount) {
                    if (!empty($discount['discount_type_id']) && $discount['amount'] > 0) {
                        $bill->discounts()->create([
                            'discount_type_id' => $discount['discount_type_id'],
                            'amount'           => $discount['amount'],
                            'note'             => $discount['note'] ?? null,
                        ]);
                        $totalExtDisc += $discount['amount'];
                    }
                }
            }

            // 8. HITUNG GRAND TOTAL AKHIR & UPDATE INDUK
            // Rumus: (Total Harga Barang - Diskon Item + Pajak) + Biaya Tambahan - Potongan Global
            $dppGlobal = $totalSubtotal - $totalItemDisc;
            $grandTotal = $dppGlobal + $totalTax + $totalCharge - $totalExtDisc;

            // Cegah minus jika diskon terlalu besar
            if ($grandTotal < 0) { $grandTotal = 0; }

            $bill->update([
                'subtotal'       => $totalSubtotal,
                'total_discount' => $totalItemDisc + $totalExtDisc, // Gabungan diskon item & diskon global
                'total_tax'      => $totalTax,
                'total_charge'   => $totalCharge,
                'amount'         => $grandTotal,
            ]);

            // 9. UPLOAD LAMPIRAN (MediaLibrary Spatie)
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $bill->addMedia($file)->toMediaCollection('bill_attachments', 'public');
                }
            }

            // 10. CATAT HISTORY AUDIT TRAIL
            \App\Models\History::create([
                'user_id'     => auth()->id(),
                'record_type' => \App\Models\BillRequest::class,
                'record_id'   => $bill->id,
                'action'      => 'CREATED',
                'note'        => "Tagihan {$billNumber} berhasil dibuat dengan nilai {$currency} " . number_format($grandTotal, 0, ',', '.')
            ]);

            // 🔥 PERBAIKAN: Gunakan Tipe Dokumen yang Sama Dengan Tabel Workflow ('OPEX')
        $workflow = \DB::table('approval_workflows')
            ->where('document_type', 'OPEX') // Pastikan di master matriks tipenya OPEX
            ->where('is_active', 1)->first();

            if ($workflow) {
                // Ambil langkah (steps) berdasarkan nominal tagihan (amount)
                $steps = \DB::table('approval_workflow_steps')
                    ->where('approval_workflow_id', $workflow->id)
                    ->where('min_amount', '<=', $bill->amount)
                    ->orderBy('step_order', 'asc')->get();

                foreach ($steps as $step) {
                    \App\Models\DocumentApproval::create([
                        'document_id'   => $bill->id,
                        'document_type' => get_class($bill),
                        'role_id'       => $step->role_id,
                        'step_order'    => $step->step_order,
                        'status'        => 'PENDING'
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


    public function edit($id)
    {
        // 1. Tarik tagihan beserta anak-anaknya dan RELASI STATUS
        $bill = \App\Models\BillRequest::with(['items', 'charges', 'discounts', 'status'])->findOrFail($id);

        // 2. Validasi Keamanan: Hanya bisa diedit jika statusnya PENDING atau DRAFT (menggunakan SLUG)
        if ($bill->status && !in_array($bill->status->slug, ['pending', 'draft'])) {
            return back()->with('error', 'Tagihan yang sudah disetujui atau diproses tidak dapat diedit!');
        }

        // 3. Tarik Master Data untuk Dropdown
        $companies     = \App\Models\Company::all();
        $taxes         = \App\Models\Tax::where('is_active', true)->orderBy('name')->get();
        $currencies    = \App\Models\Currency::where('is_active', true)->orderBy('name')->get();
        $vendors       = \App\Models\Vendor::orderBy('name')->get();

        $opexItems     = \App\Models\Item::where('is_stockable', false)->where('is_asset', false)->orderBy('name')->get();
        $chargeTypes   = \App\Models\ChargeType::where('is_active', true)->orderBy('name')->get();
        $discountTypes = \App\Models\DiscountType::where('is_active', true)->orderBy('name')->get();

        return view('bills.edit', compact('bill', 'companies', 'taxes', 'currencies', 'vendors', 'opexItems', 'chargeTypes', 'discountTypes'));
    }


    public function update(Request $request, $id)
    {
        // 1. VALIDASI KETAT
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
            'charges.*.amount'   => 'nullable|numeric|min:0',
            'discounts.*.amount' => 'nullable|numeric|min:0',
            'attachments.*'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        \DB::beginTransaction();
        try {
            // PERBAIKAN: Tambahkan relasi status saat mencari data
            $bill = \App\Models\BillRequest::with('status')->findOrFail($id);

            // Validasi Keamanan: Jika sudah lunas/dibayar sebagian, HARAM diedit!
            if ($bill->status && in_array($bill->status->slug, ['paid', 'partial'])) {
                return back()->with('error', 'Gagal! Tagihan ini sudah memiliki riwayat pembayaran.');
            }

            // 2. UPDATE HEADER & RECURRING
            $currency = \App\Models\Currency::find($request->currency_id)->code ?? 'IDR';

            $bill->company_id  = $request->paid_by_company_id;
            $bill->vendor_name = $request->vendor_name;
            $bill->description = $request->note;
            $bill->invoice_date= $request->bill_date;
            $bill->due_date    = $request->due_date;
            $bill->currency    = $currency;

            // Update Logika Recurring (Berulang)
            if ($request->is_recurring == '1') {
                $bill->is_recurring = true;
                $bill->recurring_interval = (int) $request->recurring_interval;
                $bill->recurring_period = $request->recurring_period;
                $bill->next_generation_date = \Carbon\Carbon::parse($request->bill_date)->add((int) $request->recurring_interval, $request->recurring_period);
            } else {
                $bill->is_recurring = false;
                $bill->recurring_interval = null;
                $bill->recurring_period = null;
                $bill->next_generation_date = null;
            }

            // 3. INISIALISASI VARIABEL PERHITUNGAN ULANG
            $totalSubtotal = 0;
            $totalItemDisc = 0;
            $totalTax      = 0;
            $totalCharge   = 0;
            $totalExtDisc  = 0;

            // 4. RESET (HAPUS) DATA LAMA - STRATEGI PALING AMAN DI ERP
            $bill->items()->delete();
            $bill->charges()->delete();
            $bill->discounts()->delete();

            // 5. LOOPING & SIMPAN BARIS ITEM BARU
            foreach ($request->items as $item) {
                $qty = (float) $item['qty'];
                $price = (float) $item['price'];
                $gross = $qty * $price;

                $discVal = (float) ($item['discount_value'] ?? 0);
                $discType = $item['discount_type'] ?? 'fixed';
                $discAmount = ($discType == 'percent') ? ($gross * $discVal / 100) : $discVal;

                $dpp = $gross - $discAmount;
                $taxPercent = 0;
                if (!empty($item['tax_id'])) {
                    $taxData = \App\Models\Tax::find($item['tax_id']);
                    $taxPercent = $taxData ? (float) $taxData->percent : 0;
                }
                $taxAmount = $dpp * ($taxPercent / 100);

                $rowSubtotal = $dpp + $taxAmount;

                $bill->items()->create([
                    'name'                 => $item['name'],
                    'description'          => $item['description'],
                    'qty'                  => $qty,
                    'price'                => $price,
                    'amount'               => $rowSubtotal,
                    'discount_type'        => $discType,
                    'discount_value'       => $discVal,
                    'discount_amount'      => $discAmount,
                    'tax_id'               => $item['tax_id'] ?? null,
                    'tax_percent_snapshot' => $taxPercent,
                    'tax_amount'           => $taxAmount,
                    'subtotal'             => $gross,
                ]);

                $totalSubtotal += $gross;
                $totalItemDisc += $discAmount;
                $totalTax      += $taxAmount;
            }

            // 6. LOOPING & SIMPAN BIAYA TAMBAHAN (CHARGES)
            if ($request->has('charges') && is_array($request->charges)) {
                foreach ($request->charges as $charge) {
                    $chargeAmt = (float) ($charge['amount'] ?? 0);
                    if (!empty($charge['charge_type_id']) && $chargeAmt > 0) {
                        $bill->charges()->create([
                            'charge_type_id' => $charge['charge_type_id'],
                            'amount'         => $chargeAmt,
                            'note'           => $charge['note'] ?? null,
                        ]);
                        $totalCharge += $chargeAmt;
                    }
                }
            }

            // 7. LOOPING & SIMPAN POTONGAN TAMBAHAN (DISCOUNTS)
            if ($request->has('discounts') && is_array($request->discounts)) {
                foreach ($request->discounts as $discount) {
                    $discAmt = (float) ($discount['amount'] ?? 0);
                    if (!empty($discount['discount_type_id']) && $discAmt > 0) {
                        $bill->discounts()->create([
                            'discount_type_id' => $discount['discount_type_id'],
                            'amount'           => $discAmt,
                            'note'             => $discount['note'] ?? null,
                        ]);
                        $totalExtDisc += $discAmt;
                    }
                }
            }

            // 8. HITUNG GRAND TOTAL AKHIR & UPDATE INDUK
            $dppGlobal = $totalSubtotal - $totalItemDisc;
            $grandTotal = $dppGlobal + $totalTax + $totalCharge - $totalExtDisc;

            if ($grandTotal < 0) { $grandTotal = 0; }

            $bill->subtotal       = $totalSubtotal;
            $bill->total_discount = $totalItemDisc + $totalExtDisc;
            $bill->total_tax      = $totalTax;
            $bill->total_charge   = $totalCharge;
            $bill->amount         = $grandTotal;
            $bill->save();

            // 9. UPLOAD LAMPIRAN BARU (Jika ada)
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $bill->addMedia($file)->toMediaCollection('bill_attachments', 'public');
                }
            }

            // 10. HAPUS LAMPIRAN LAMA (Jika user mencentang checkbox hapus)
            if ($request->has('delete_media') && is_array($request->delete_media)) {
                $mediaItems = $bill->getMedia('bill_attachments')->whereIn('id', $request->delete_media);
                foreach ($mediaItems as $media) {
                    $media->delete();
                }
            }

            // 11. CATAT HISTORY AUDIT TRAIL
            \App\Models\History::create([
                'user_id'     => auth()->id(),
                'record_type' => \App\Models\BillRequest::class,
                'record_id'   => $bill->id,
                'action'      => 'UPDATED',
                'note'        => "Melakukan revisi Tagihan. Grand Total Baru: {$currency} " . number_format($grandTotal, 0, ',', '.')
            ]);

            \DB::commit();

            return redirect()->route('bills.show', $bill->id)->with('success', "Tagihan Opex berhasil diperbarui!");

        } catch (\Exception $e) {
            \DB::rollback();
            return back()->withInput()->with('error', 'Gagal update tagihan: ' . $e->getMessage());
        }
    }



    public function show($id)
    {
        // PERBAIKAN: Tambahkan 'status' ke dalam daftar eager loading (with)
        $bill = \App\Models\BillRequest::with([
            'status', // Relasi baru
            'items',
            'company',
            'user',
            'histories.user',
            'media',
            'charges.chargeType',
            'discounts.discountType'
        ])->findOrFail($id);

        return view('bills.show', compact('bill'));
    }








}
