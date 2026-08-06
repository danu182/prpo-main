<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BillRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateRecurringBills extends Command
{
    /**
     * Nama perintah untuk dipanggil di terminal
     */
    protected $signature = 'bills:generate-recurring';

    /**
     * Deskripsi tugas robot ini
     */
    protected $description = 'Mengecek dan meng-generate tagihan berulang (OPEX) yang sudah jatuh tempo jadwal berikutnya.';

    /**
     * Logika utama robot
     */
    public function handle()
    {
        $today = Carbon::today()->toDateString();
        $this->info("Memulai pengecekan tagihan berulang untuk tanggal: {$today}...");

        // Cari semua tagihan Induk yang 'is_recurring' = true dan tanggal generate-nya adalah hari ini atau sudah lewat
        $recurringBills = BillRequest::with(['items', 'charges', 'discounts'])
                            ->where('is_recurring', true)
                            ->whereNotNull('next_generation_date')
                            ->whereDate('next_generation_date', '<=', $today)
                            ->get();

        if ($recurringBills->isEmpty()) {
            $this->info("Tidak ada jadwal tagihan berulang untuk hari ini.");
            return;
        }

        $countSuccess = 0;

        foreach ($recurringBills as $masterBill) {
            DB::beginTransaction();
            try {
                // 1. GENERATE NOMOR TAGIHAN BARU
                $companyCode = $masterBill->company ? ($masterBill->company->code ?? 'GEN') : 'GEN';
                $monthYear = Carbon::parse($masterBill->next_generation_date)->format('Y/m');
                $prefix = "BILL/OPX/{$companyCode}/{$monthYear}/";

                $lastBill = BillRequest::where('bill_number', 'like', $prefix . '%')
                                ->lockForUpdate()
                                ->orderBy('id', 'desc')->first();

                $newNumber = $lastBill ? ((int) substr($lastBill->bill_number, -4) + 1) : 1;
                $newBillNumber = $prefix . sprintf('%04d', $newNumber);

                // 2. DUPLIKASI DATA INDUK
                $newBill = $masterBill->replicate();
                $newBill->bill_number = $newBillNumber;
                $newBill->invoice_date = $masterBill->next_generation_date;

                // Hitung jatuh tempo baru
                $daysToDue = Carbon::parse($masterBill->invoice_date)->diffInDays(Carbon::parse($masterBill->due_date));
                $newBill->due_date = Carbon::parse($masterBill->next_generation_date)->addDays($daysToDue);

                // Tagihan anak TIDAK is_recurring
                $newBill->is_recurring = false;
                $newBill->recurring_interval = null;
                $newBill->recurring_period = null;
                $newBill->next_generation_date = null;

                // Cari status_id untuk 'pending'
                $statusPending = \App\Models\Status::where('type', 'OPEX')->where('slug', 'pending')->first();
                $newBill->status_id = $statusPending ? $statusPending->id : 1; // Default ke ID 1 jika tidak ketemu

                $newBill->current_approval_level = 0; // Reset ke level awal
                $newBill->rejection_reason = null;
                $newBill->save();

                // 3. DUPLIKASI ITEMS, CHARGES, DISCOUNTS
                foreach ($masterBill->items as $item) {
                    $newItem = $item->replicate();
                    $newItem->bill_request_id = $newBill->id;
                    $newItem->save();
                }
                foreach ($masterBill->charges as $charge) {
                    $newCharge = $charge->replicate();
                    $newCharge->bill_request_id = $newBill->id;
                    $newCharge->save();
                }
                foreach ($masterBill->discounts as $discount) {
                    $newDiscount = $discount->replicate();
                    $newDiscount->bill_request_id = $newBill->id;
                    $newDiscount->save();
                }

                // ====================================================================
                // 🔥 4. COPY WORKFLOW PERSETUJUAN DARI INDUKNYA (CUSTOM/DEFAULT) 🔥
                // ====================================================================
                $parentApprovals = \App\Models\DocumentApproval::where('document_id', $masterBill->id)
                                    ->where('document_type', get_class($masterBill))
                                    ->orderBy('step_order', 'asc')
                                    ->get();

                foreach ($parentApprovals as $approval) {
                    // PENTING: Jangan copy data orang yang meng-ACC dan waktu ACC dari tagihan lama!
                    $newApproval = $approval->replicate(['approved_by', 'approved_at', 'note', 'created_at', 'updated_at']);
                    $newApproval->document_id = $newBill->id;
                    $newApproval->status = 'PENDING'; // Ubah kembali ke status menunggu persetujuan
                    $newApproval->save();
                }

                // 5. UPDATE TANGGAL GENERATE BERIKUTNYA DI TAGIHAN INDUK
                $interval = $masterBill->recurring_interval ?? 1;
                $period = $masterBill->recurring_period ?? 'months'; // months, days, years
                $masterBill->next_generation_date = Carbon::parse($masterBill->next_generation_date)->add($interval, $period);
                $masterBill->save();

                // 6. CATAT DI AUDIT TRAIL
                \App\Models\History::create([
                    'user_id' => 1, // ID 1 = System
                    'record_type' => \App\Models\BillRequest::class, 'record_id' => $masterBill->id,
                    'action' => 'AUTO-GENERATE', 'note' => "Sistem berhasil membuat tagihan periode ini secara otomatis. (Ref Baru: {$newBillNumber})"
                ]);
                \App\Models\History::create([
                    'user_id' => 1,
                    'record_type' => \App\Models\BillRequest::class, 'record_id' => $newBill->id,
                    'action' => 'CREATED', 'note' => "Tagihan ini dibuat otomatis (Recurring dari: {$masterBill->bill_number}). Menunggu persetujuan."
                ]);

                DB::commit();
                $countSuccess++;
                $this->info("Berhasil meng-generate tagihan: {$newBillNumber}");

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Gagal memproses master bill {$masterBill->bill_number}: " . $e->getMessage());
            }
        }

        $this->info("Selesai! Total tagihan yang berhasil di-generate: {$countSuccess}");
    }
}



