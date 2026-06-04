<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BillRequest;
use App\Models\History;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateRecurringBills extends Command
{
    // Nama perintah untuk dijalankan di terminal
    protected $signature = 'bills:generate-recurring';

    // Deskripsi perintah
    protected $description = 'Otomatis membuat tagihan baru untuk Opex yang berulang (Recurring)';

    public function handle()
    {
        $this->info('Memulai pengecekan tagihan berulang...');

        $today = Carbon::today();

        // Cari tagihan yang is_recurring = true dan jadwal generate-nya adalah hari ini (atau terlewat)
        $recurringBills = BillRequest::where('is_recurring', true)
            ->whereNotNull('next_generation_date')
            ->whereDate('next_generation_date', '<=', $today)
            ->get();

        if ($recurringBills->isEmpty()) {
            $this->info('Tidak ada tagihan berulang untuk hari ini.');
            return;
        }

        $count = 0;

        foreach ($recurringBills as $oldBill) {
            DB::beginTransaction();
            try {
                // 1. Generate Nomor Tagihan Baru
                $companyCode = $oldBill->company->code ?? 'GEN';
                $monthYear = $today->format('Y/m');
                $prefix = "BILL/OPX/{$companyCode}/{$monthYear}/";

                $lastBill = BillRequest::where('bill_number', 'like', $prefix . '%')
                            ->orderBy('id', 'desc')->lockForUpdate()->first();
                $newNumber = $lastBill ? ((int) substr($lastBill->bill_number, -4) + 1) : 1;
                $billNumber = $prefix . sprintf('%04d', $newNumber);

                // Hitung Jarak (Gap) hari dari bill_date ke due_date lama
                $oldBillDate = Carbon::parse($oldBill->invoice_date);
                $oldDueDate = Carbon::parse($oldBill->due_date);
                $gapDays = $oldBillDate->diffInDays($oldDueDate);

                // 2. Buat Tagihan Baru (Duplikat Data Lama)
                $newBill = BillRequest::create([
                    'bill_number'        => $billNumber,
                    'title'              => $oldBill->title,
                    'user_id'            => $oldBill->user_id, // Tetap gunakan user pembuat awal
                    'company_id'         => $oldBill->company_id,
                    'type'               => 'OPEX',
                    'vendor_name'        => $oldBill->vendor_name,
                    'description'        => 'Tagihan Otomatis (Recurring) - ' . $oldBill->description,
                    'invoice_date'       => $today->format('Y-m-d'),
                    'due_date'           => $today->copy()->addDays($gapDays)->format('Y-m-d'), // Tanggal jatuh tempo menyesuaikan
                    'currency'           => $oldBill->currency,
                    'status'             => 'PENDING', // Harus PENDING agar diapprove ulang
                    'subtotal'           => $oldBill->subtotal,
                    'total_discount'     => $oldBill->total_discount,
                    'total_tax'          => $oldBill->total_tax,
                    'total_charge'       => $oldBill->total_charge,
                    'amount'             => $oldBill->amount,

                    // Pewarisan Sifat Recurring
                    'is_recurring'       => true,
                    'recurring_interval' => $oldBill->recurring_interval,
                    'recurring_period'   => $oldBill->recurring_period,

                    // Hitung next date untuk tagihan BARU ini
                    'next_generation_date'=> $today->copy()->add((int)$oldBill->recurring_interval, $oldBill->recurring_period),
                ]);

                // 3. Duplikat Rincian Item
                foreach ($oldBill->items as $item) {
                    $newItem = $item->replicate();
                    $newItem->bill_request_id = $newBill->id;
                    $newItem->save();
                }

                // 4. Duplikat Biaya Tambahan
                foreach ($oldBill->charges as $charge) {
                    $newCharge = $charge->replicate();
                    $newCharge->bill_request_id = $newBill->id;
                    $newCharge->save();
                }

                // 5. Duplikat Potongan Tambahan
                foreach ($oldBill->discounts as $discount) {
                    $newDiscount = $discount->replicate();
                    $newDiscount->bill_request_id = $newBill->id;
                    $newDiscount->save();
                }

                // 6. Matikan sifat recurring di tagihan LAMA agar tidak beranak-pinak dobel
                $oldBill->update([
                    'is_recurring' => false,
                    'next_generation_date' => null
                ]);

                // 7. Catat History
                History::create([
                    'user_id'     => $oldBill->user_id, // Null berarti Sistem/Robot
                    'record_type' => BillRequest::class,
                    'record_id'   => $newBill->id,
                    'action'      => 'SYSTEM GENERATED',
                    'note'        => "Dibuat otomatis oleh sistem dari tagihan induk: {$oldBill->bill_number}"
                ]);

                DB::commit();
                $count++;

            } catch (\Exception $e) {
                DB::rollback();
                $this->error("Gagal membuat duplikat untuk Tagihan {$oldBill->id}: " . $e->getMessage());
            }
        }

        $this->info("Berhasil men-generate {$count} tagihan berulang.");
    }
}
