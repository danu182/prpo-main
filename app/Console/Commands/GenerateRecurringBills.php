<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BillRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateRecurringBills extends Command

{
    /**
     * Nama perintah untuk dipanggil di terminal (contoh: php artisan bills:generate-recurring)
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

        // Cari semua tagihan Induk yang 'is_recurring' = true dan tanggal generate-nya adalah hari ini atau sudah lewat (tapi belum di-generate)
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
                // 1. GENERATE NOMOR TAGIHAN BARU (Logic sama seperti di Controller)
                $companyCode = $masterBill->company ? ($masterBill->company->code ?? 'GEN') : 'GEN';
                $monthYear = Carbon::parse($masterBill->next_generation_date)->format('Y/m');
                $prefix = "BILL/OPX/{$companyCode}/{$monthYear}/";

                $lastBill = BillRequest::where('bill_number', 'like', $prefix . '%')
                                ->lockForUpdate()
                                ->orderBy('id', 'desc')->first();

                $newNumber = $lastBill ? ((int) substr($lastBill->bill_number, -4) + 1) : 1;
                $newBillNumber = $prefix . sprintf('%04d', $newNumber);

                // 2. DUPLIKASI DATA INDUK (Tapi jadikan tagihan biasa/bukan induk)
                $newBill = $masterBill->replicate();
                $newBill->bill_number = $newBillNumber;
                $newBill->invoice_date = $masterBill->next_generation_date;
                // Hitung jatuh tempo baru (jarak invoice_date ke due_date pada master)
                $daysToDue = Carbon::parse($masterBill->invoice_date)->diffInDays(Carbon::parse($masterBill->due_date));
                $newBill->due_date = Carbon::parse($masterBill->next_generation_date)->addDays($daysToDue);

                // Tagihan anak TIDAK is_recurring, karena yang jadi patokan tetap Master Bill
                $newBill->is_recurring = false;
                $newBill->recurring_interval = null;
                $newBill->recurring_period = null;
                $newBill->next_generation_date = null;

                // Cari status_id untuk 'pending'
                $statusPending = \App\Models\Status::where('type', 'OPEX')->where('slug', 'pending')->first();
                $newBill->status_id = $statusPending ? $statusPending->id : $masterBill->status_id;

                // HAPUS baris $newBill->status = 'PENDING'; karena kolomnya tidak ada di database

                $newBill->current_approval_level = 0;
                $newBill->rejection_reason = null; // 🔥 TAMBAHAN: Bersihkan alasan tolak/void agar tagihan baru ini bersih
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

                // 4. GENERATE WORKFLOW PERSETUJUAN (Sama seperti saat membuat tagihan manual)
                $workflow = \DB::table('approval_workflows')->whereIn('document_type', ['OPEX', 'App\Models\BillRequest'])->where('is_active', 1)->first();
                if ($workflow) {
                    $steps = \DB::table('approval_workflow_steps')->where('approval_workflow_id', $workflow->id)->orderBy('step_order', 'asc')->get();
                    foreach ($steps as $step) {
                        \App\Models\DocumentApproval::create([
                            'document_id' => $newBill->id, 'document_type' => 'App\Models\BillRequest',
                            'role_id' => $step->role_id, 'step_order' => $step->step_order, 'status' => 'PENDING'
                        ]);
                    }
                }

                // 5. UPDATE TANGGAL GENERATE BERIKUTNYA DI TAGIHAN INDUK
                $interval = $masterBill->recurring_interval ?? 1;
                $period = $masterBill->recurring_period ?? 'months'; // months, days, years
                $masterBill->next_generation_date = Carbon::parse($masterBill->next_generation_date)->add($interval, $period);
                $masterBill->save();

                // 6. CATAT DI AUDIT TRAIL INDUK DAN ANAK
                \App\Models\History::create([
                    'user_id' => 1, // ID 1 biasanya System/Super Admin
                    'record_type' => \App\Models\BillRequest::class, 'record_id' => $masterBill->id,
                    'action' => 'AUTO-GENERATE', 'note' => "Sistem berhasil membuat tagihan periode ini secara otomatis. (Ref Baru: {$newBillNumber})"
                ]);
                \App\Models\History::create([
                    'user_id' => 1,
                    'record_type' => \App\Models\BillRequest::class, 'record_id' => $newBill->id,
                    'action' => 'CREATED', 'note' => "Tagihan ini dibuat secara otomatis oleh Sistem (Recurring dari Ref: {$masterBill->bill_number})"
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
