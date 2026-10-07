<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BillRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\GoogleSheetService;

class GenerateRecurringBills extends Command
{
    protected $signature = 'bills:generate-recurring {--start= : Tarikh Mula (YYYY-MM-DD)} {--end= : Tarikh Akhir (YYYY-MM-DD)}';

    protected $description = 'Mengecek dan meng-generate tagihan berulang (OPEX) berdasarkan tarikh tertentu, lalu mengirimkannya ke Google Sheet.';

    public function handle()
    {
        $startDate = $this->option('start');
        $endDate = $this->option('end');
        $today = Carbon::today()->toDateString();

        // 🔥 LOGIKA TARGET TARIKH MAKSIMAL 🔥
        if ($endDate) {
            $limitDate = Carbon::parse($endDate)->endOfDay();
        } elseif ($startDate) {
            // Jika hanya start date diberikan, pastikan limit tidak mundur dari hari ini
            $limitDate = Carbon::parse(max($today, $startDate))->endOfDay();
        } else {
            $limitDate = Carbon::parse($today)->endOfDay();
        }

        // Asas Query (Ambil semua tagihan induk yang aktif)
        $query = BillRequest::with(['items.item', 'charges.chargeType', 'discounts.discountType', 'company', 'user'])
                    ->where('is_recurring', true)
                    ->whereNotNull('next_generation_date');

        if ($startDate && $endDate) {
            $this->info("Memulakan semakan dari {$startDate} hingga {$endDate}...");
            $query->whereDate('next_generation_date', '<=', $endDate);
        } elseif ($startDate) {
            $this->info("Memulakan semakan bermula dari {$startDate}...");
            $query->whereDate('next_generation_date', '>=', $startDate);
        } elseif ($endDate) {
            $this->info("Memulakan semakan sehingga {$endDate}...");
            $query->whereDate('next_generation_date', '<=', $endDate);
        } else {
            $this->info("Memulakan semakan sehingga hari ini ({$today})...");
            $query->whereDate('next_generation_date', '<=', $today);
        }

        $recurringBills = $query->get();

        if ($recurringBills->isEmpty()) {
            $this->info("Tidak ada jadwal tagihan berulang untuk tempoh yang dipilih.");
            return;
        }

        $countSuccess = 0;

        foreach ($recurringBills as $masterBill) {
            $loopCount = 0; // Pengaman agar tidak terjadi infinity loop jika interval rusak (max 60 bulan/5 tahun sekaligus)

            // 🔥 KUNCI UTAMA: LOOPING "WHILE" 🔥
            // Selagi tanggal tagihan berikutnya masih di bawah atau sama dengan Limit Date, terus Generate!
            while (Carbon::parse($masterBill->next_generation_date)->lte($limitDate) && $loopCount < 60) {

                DB::beginTransaction();
                try {
                    $companyCode = $masterBill->company ? ($masterBill->company->code ?? 'GEN') : 'GEN';
                    $companyName = $masterBill->company ? $masterBill->company->name : '-';
                    $monthYear = Carbon::parse($masterBill->next_generation_date)->format('Y/m');
                    $prefix = "BILL/OPX/{$companyCode}/{$monthYear}/";

                    $lastBill = BillRequest::where('bill_number', 'like', $prefix . '%')
                                    ->lockForUpdate()
                                    ->orderBy('id', 'desc')->first();

                    $newNumber = $lastBill ? ((int) substr($lastBill->bill_number, -4) + 1) : 1;
                    $newBillNumber = $prefix . sprintf('%04d', $newNumber);

                    $newBill = $masterBill->replicate();
                    $newBill->bill_number = $newBillNumber;
                    $newBill->invoice_date = $masterBill->next_generation_date;

                    $daysToDue = Carbon::parse($masterBill->invoice_date)->diffInDays(Carbon::parse($masterBill->due_date));
                    $newBill->due_date = Carbon::parse($masterBill->next_generation_date)->addDays($daysToDue);

                    $newBill->is_recurring = false;
                    $newBill->recurring_interval = null;
                    $newBill->recurring_period = null;
                    $newBill->next_generation_date = null;
                    $newBill->current_approval_level = 0;
                    $newBill->rejection_reason = null;

                    // 🔥 OTOMATIS REPLACE BULAN PADA CATATAN GLOBAL 🔥
                    $oldMonthYear = Carbon::parse($masterBill->invoice_date)->translatedFormat('F Y');
                    $periodeBaru = Carbon::parse($newBill->invoice_date)->translatedFormat('F Y');

                    if (!empty($masterBill->description)) {
                        $newBill->description = str_ireplace($oldMonthYear, $periodeBaru, $masterBill->description);
                    }

                    $newBill->save();

                    $childRows = [];

                    foreach ($masterBill->items as $item) {
                        $newItem = $item->replicate();
                        $newItem->bill_request_id = $newBill->id;
                        $newItem->save();

                        $infoTambahan = "";
                        if ($newItem->discount_amount > 0) $infoTambahan .= (strtoupper($newItem->discount_type) === 'PERCENT') ? " (-Disc {$newItem->discount_value}%)" : " (-Diskon Rp" . number_format($newItem->discount_amount, 0, ',', '.') . ")";
                        if ($newItem->tax_amount > 0) $infoTambahan .= (strtoupper($newItem->tax_type) === 'PERCENT') ? " (+PPN {$newItem->tax_value}%)" : " (+Pajak Rp" . number_format($newItem->tax_amount, 0, ',', '.') . ")";

                        $dppSheet = $newItem->subtotal - $newItem->discount_amount;
                        $categoryName = optional(optional($newItem->item)->category)->name ?? 'Lainnya';

                        $childRows[] = [
                            \Carbon\Carbon::parse($newBill->invoice_date)->format('d-M-Y'), // A
                            $companyName, // B
                            $newBill->vendor_name, // C
                            "  ↳ [Item] " . $newItem->name . $infoTambahan, // D
                            $categoryName, // E
                            $dppSheet, // F
                            $newItem->tax_amount, // G
                            $newItem->amount, // H
                            $newBill->vendor_invoice_number ?? '-', // I
                            $newBill->account_number ?? '-', // J
                            $newBillNumber, // K
                            $masterBill->user->name ?? 'System', // L
                            '-',                          // 🔥 M: Keterangan BPR (Diberi strip)
                            $newItem->description ?? '-'  // 🔥 N: Spesifikasi Detail
                        ];
                    }

                    foreach ($masterBill->charges as $charge) {
                        $newCharge = $charge->replicate();
                        $newCharge->bill_request_id = $newBill->id;
                        $newCharge->save();

                        $chargeName = optional($newCharge->chargeType)->name ?? 'Biaya Tambahan';
                        $note = !empty($newCharge->note) ? ' (' . $newCharge->note . ')' : '';

                        $childRows[] = [
                            \Carbon\Carbon::parse($newBill->invoice_date)->format('d-M-Y'), // A
                            $companyName, // B
                            $newBill->vendor_name, // C
                            "  ↳ [+] " . $chargeName . $note, // D
                            'Biaya Tambahan', // E
                            $newCharge->amount, // F
                            0, // G
                            $newCharge->amount, // H
                            $newBill->vendor_invoice_number ?? '-', // I
                            $newBill->account_number ?? '-', // J
                            $newBillNumber, // K
                            $masterBill->user->name ?? 'System', // L
                            '-', // M: Keterangan BPR
                            $newCharge->note ?? '-' // N: Spesifikasi/Catatan Extra
                        ];
                    }

                    foreach ($masterBill->discounts as $discount) {
                        $newDiscount = $discount->replicate();
                        $newDiscount->bill_request_id = $newBill->id;
                        $newDiscount->save();

                        $discName = optional($newDiscount->discountType)->name ?? 'Potongan';
                        $note = !empty($newDiscount->note) ? ' (' . $newDiscount->note . ')' : '';

                        $childRows[] = [
                            \Carbon\Carbon::parse($newBill->invoice_date)->format('d-M-Y'), // A
                            $companyName, // B
                            $newBill->vendor_name, // C
                            "  ↳ [-] " . $discName . $note, // D
                            'Potongan / Diskon', // E
                            -abs($newDiscount->amount), // F
                            0, // G
                            -abs($newDiscount->amount), // H
                            $newBill->vendor_invoice_number ?? '-', // I
                            $newBill->account_number ?? '-', // J
                            $newBillNumber, // K
                            $masterBill->user->name ?? 'System', // L
                            '-', // M: Keterangan BPR
                            $newDiscount->note ?? '-' // N: Spesifikasi/Catatan Extra
                        ];
                    }

                    // COPY WORKFLOW
                    $historyLog = \App\Models\History::where('record_id', $masterBill->id)->where('record_type', get_class($masterBill))
                        ->where('action', 'SYSTEM')->where('note', 'like', 'Menggunakan Rute Persetujuan Khusus:%')->orderBy('id', 'desc')->first();

                    $needsApproval = false;
                    if ($historyLog) {
                        $workflowName = trim(str_replace('Menggunakan Rute Persetujuan Khusus:', '', $historyLog->note));
                        $workflow = \App\Models\ApprovalWorkflow::with('steps')->where('name', $workflowName)->where('is_active', true)->first();
                        if ($workflow && $workflow->steps->count() > 0) {
                            foreach ($workflow->steps as $step) {
                                \App\Models\DocumentApproval::create([
                                    'document_id' => $newBill->id,
                                    'document_type' => get_class($newBill),
                                    'role_id' => $step->role_id,

                                    // 🔥 INI PENYELAMATNYA: Copy ID User Spesifik ke Tagihan Baru 🔥
                                    'user_id' => $step->user_id ?? $step->specific_user_id ?? null,

                                    'target_department_id' => $step->target_department_id ?? $step->department_id ?? null,
                                    'step_order' => $step->step_order,
                                    'status' => 'PENDING'
                                ]);
                            }
                            $needsApproval = true;
                            \App\Models\History::create(['user_id' => $masterBill->user_id, 'record_type' => \App\Models\BillRequest::class, 'record_id' => $newBill->id, 'action' => 'SYSTEM', 'note' => "Menggunakan Rute Persetujuan Khusus TERBARU: " . $workflow->name]);
                        }
                    }

                    if (!$needsApproval) {
                        $needsApproval = \App\Services\ApprovalService::generateWorkflow($newBill);
                        if ($needsApproval) {
                            \App\Models\History::create(['user_id' => $masterBill->user_id, 'record_type' => \App\Models\BillRequest::class, 'record_id' => $newBill->id, 'action' => 'SYSTEM', 'note' => "Menggunakan Rute Persetujuan Departemen Standar yang berlaku saat ini."]);
                        }
                    }

                    if ($needsApproval) {
                        $statusPending = \App\Models\Status::where('type', 'OPEX')->where('slug', 'pending')->first();
                        $newBill->update(['status_id' => $statusPending ? $statusPending->id : 1]);
                    } else {
                        $statusApproved = \App\Models\Status::where('type', 'OPEX')->where('slug', 'approved')->first();
                        $newBill->update(['status_id' => $statusApproved ? $statusApproved->id : 3]);
                        \App\Models\History::create(['user_id' => $masterBill->user_id, 'record_type' => \App\Models\BillRequest::class, 'record_id' => $newBill->id, 'action' => 'AUTO-APPROVED', 'note' => "Tagihan langsung disetujui karena tidak ada aturan batas persetujuan yang aktif."]);
                    }

                    // 🔥 MAJUKAN TANGGAL INDUK UNTUK PUTARAN BERIKUTNYA 🔥
                    $interval = $masterBill->recurring_interval ?? 1;
                    $period = $masterBill->recurring_period ?? 'months';
                    $masterBill->next_generation_date = Carbon::parse($masterBill->next_generation_date)->add($interval, $period);
                    $masterBill->save();

                    \App\Models\History::create(['user_id' => $masterBill->user_id, 'record_type' => \App\Models\BillRequest::class, 'record_id' => $masterBill->id, 'action' => 'AUTO-GENERATE', 'note' => "Sistem berhasil membuat tagihan periode ini secara otomatis. (Ref Baru: {$newBillNumber})"]);
                    \App\Models\History::create(['user_id' => $masterBill->user_id, 'record_type' => \App\Models\BillRequest::class, 'record_id' => $newBill->id, 'action' => 'CREATED', 'note' => "Tagihan dibuat otomatis (Recurring dari: {$masterBill->bill_number})."]);

                    // 🔥 FORMAT PARENT SHEET 🔥
                    $catatanParent = trim(($newBill->description ?? '') . " (Periode: " . $periodeBaru . ")");

                    $parentRow = [
                        \Carbon\Carbon::parse($newBill->invoice_date)->format('d-M-Y'), // A
                        $companyName, // B
                        $newBill->vendor_name, // C
                        "⭐ GRAND TOTAL", // D
                        "SUMMARY", // E
                        ($newBill->subtotal - ($newBill->items->sum('discount_amount') ?? 0) + $newBill->total_charge - $newBill->discounts->sum('amount')), // F
                        $newBill->total_tax, // G
                        $newBill->amount, // H
                        $newBill->vendor_invoice_number ?? '-', // I
                        $newBill->account_number ?? '-', // J
                        $newBillNumber, // K
                        $masterBill->user->name ?? 'System', // L
                        $catatanParent, // 🔥 M: Kolom Keterangan BPR
                        '-'             // 🔥 N: Kolom Spesifikasi (Kosong)
                    ];

                    $googleSheetRows = array_merge([$parentRow], $childRows);

                    try {
                        $sheetService = new GoogleSheetService();
                        $tabName = env('GOOGLE_SHEET_OPEX_TAB_NAME', 'Sheet1');
                        foreach ($googleSheetRows as $rowData) {
                            $sheetService->appendRow($tabName, $rowData);
                        }
                    } catch (\Exception $e) {
                        \Log::error("Google Sheet Sync Error pada Auto-Recurring {$newBillNumber}: " . $e->getMessage());
                    }

                    DB::commit();
                    $countSuccess++;
                    $loopCount++; // Tambah angka putaran

                    $this->info("Berhasil meng-generate tagihan & sync Sheet: {$newBillNumber}");

                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("Gagal memproses master bill {$masterBill->bill_number}: " . $e->getMessage());
                    break; // Jika ada error pada iterasi ini, hentikan perulangan untuk Master Bill ini
                }
            } // <-- END OF WHILE LOOP
        }

        $this->info("Selesai! Total tagihan yang berhasil di-generate: {$countSuccess}");
    }
}
