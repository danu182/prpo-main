<?php

namespace App\Jobs;

use App\Models\BillRequest;
use App\Services\GoogleSheetService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncBillToGoogleSheet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $bill;
    public $action; // 'SYNC' untuk Insert/Update, 'DELETE' untuk Void/Reject
    public $tries = 5;

    public function __construct(BillRequest $bill, $action = 'SYNC')
    {
        $this->bill = $bill;
        $this->action = $action;
    }

    public function handle()
    {
        try {
            $sheetService = new GoogleSheetService();
            $tabName = env('GOOGLE_SHEET_OPEX_TAB_NAME', 'Sheet1');

            // 1. IDEMPOTENT: Selalu hapus baris lama sebelum Insert/Update
            $sheetService->deleteRowsByBillNumber($tabName, $this->bill->bill_number);

            // Jika perintahnya hanya untuk menghapus (Void / Reject), kita berhenti di sini
            if ($this->action === 'DELETE') {
                return;
            }

            // 2. LOAD SEMUA RELASI (Agar perhitungan akurat)
            $this->bill->load(['items.item.category', 'company', 'user', 'charges.chargeType', 'discounts.discountType']);

            $companyName = $this->bill->company ? $this->bill->company->name : '-';
            $periodeTagihan = \Carbon\Carbon::parse($this->bill->invoice_date)->translatedFormat('F Y');
            $childRows = [];

            // --- A. LOOPING ITEM ---
            foreach ($this->bill->items as $item) {
                $infoTambahan = "";
                if ($item->discount_amount > 0) $infoTambahan .= (strtoupper($item->discount_type) === 'PERCENT') ? " (-Disc {$item->discount_value}%)" : " (-Diskon Rp" . number_format($item->discount_amount, 0, ',', '.') . ")";
                if ($item->tax_amount > 0) $infoTambahan .= (strtoupper($item->tax_type) === 'PERCENT') ? " (+PPN {$item->tax_value}%)" : " (+Pajak Rp" . number_format($item->tax_amount, 0, ',', '.') . ")";

                $dppSheet = $item->subtotal - $item->discount_amount;
                $categoryName = optional(optional($item->item)->category)->name ?? 'Lainnya';

                $childRows[] = [
                    \Carbon\Carbon::parse($this->bill->invoice_date)->format('d-M-Y'), $companyName, $this->bill->vendor_name,
                    "  ↳ [Item] " . $item->name . $infoTambahan, $categoryName, $dppSheet, $item->tax_amount, $item->amount,
                    $this->bill->vendor_invoice_number ?? '-', $this->bill->account_number ?? '-', $this->bill->bill_number,
                    $this->bill->user->name ?? 'System', '-', $item->description ?? '-'
                ];
            }

            // --- B. LOOPING BIAYA TAMBAHAN ---
            foreach ($this->bill->charges as $charge) {
                $chargeName = optional($charge->chargeType)->name ?? 'Biaya Tambahan';
                $note = !empty($charge->note) ? ' (' . $charge->note . ')' : '';
                $childRows[] = [
                    \Carbon\Carbon::parse($this->bill->invoice_date)->format('d-M-Y'), $companyName, $this->bill->vendor_name,
                    "  ↳ [+] " . $chargeName . $note, 'Biaya Tambahan', $charge->amount, 0, $charge->amount,
                    $this->bill->vendor_invoice_number ?? '-', $this->bill->account_number ?? '-', $this->bill->bill_number,
                    $this->bill->user->name ?? 'System', '-', $charge->note ?? '-'
                ];
            }

            // --- C. LOOPING POTONGAN/DISKON ---
            foreach ($this->bill->discounts as $discount) {
                $discName = optional($discount->discountType)->name ?? 'Potongan';
                $note = !empty($discount->note) ? ' (' . $discount->note . ')' : '';
                $childRows[] = [
                    \Carbon\Carbon::parse($this->bill->invoice_date)->format('d-M-Y'), $companyName, $this->bill->vendor_name,
                    "  ↳ [-] " . $discName . $note, 'Potongan / Diskon', -abs($discount->amount), 0, -abs($discount->amount),
                    $this->bill->vendor_invoice_number ?? '-', $this->bill->account_number ?? '-', $this->bill->bill_number,
                    $this->bill->user->name ?? 'System', '-', $discount->note ?? '-'
                ];
            }

            // --- D. DETEKSI DISKON/PAJAK GLOBAL ---
            $sumItemDisc = $this->bill->items->sum('discount_amount');
            $actualGlobalDisc = (float)($this->bill->total_discount ?? 0) - $sumItemDisc - ($this->bill->discounts->sum('amount') ?? 0);
            $globalDiscLabel = "";
            if ($actualGlobalDisc > 0) {
                $globalDiscLabel = " (-Disc Global Rp" . number_format($actualGlobalDisc, 0, ',', '.') . ")";
                $childRows[] = [
                    \Carbon\Carbon::parse($this->bill->invoice_date)->format('d-M-Y'), $companyName, $this->bill->vendor_name,
                    "  ↳ [-] Diskon Komersial (Global)", 'Potongan / Diskon', -$actualGlobalDisc, 0, -$actualGlobalDisc,
                    $this->bill->vendor_invoice_number ?? '-', $this->bill->account_number ?? '-', $this->bill->bill_number,
                    $this->bill->user->name ?? 'System', '-', '-'
                ];
            }

            $sumItemTax = $this->bill->items->sum('tax_amount');
            $actualGlobalTax = (float)($this->bill->total_tax ?? 0) - $sumItemTax;
            $globalTaxLabel = "";
            if ($actualGlobalTax > 0) {
                $globalTaxLabel = " (+Pajak Global Rp" . number_format($actualGlobalTax, 0, ',', '.') . ")";
                $childRows[] = [
                    \Carbon\Carbon::parse($this->bill->invoice_date)->format('d-M-Y'), $companyName, $this->bill->vendor_name,
                    "  ↳ [+] Pajak Header (VAT/PPN)", 'Pajak', $actualGlobalTax, 0, $actualGlobalTax,
                    $this->bill->vendor_invoice_number ?? '-', $this->bill->account_number ?? '-', $this->bill->bill_number,
                    $this->bill->user->name ?? 'System', '-', '-'
                ];
            }

            // --- E. BARIS PARENT SUMMARY ---
            $judulParent = "⭐ GRAND TOTAL" . $globalTaxLabel . $globalDiscLabel;
            $catatanParent = trim(($this->bill->description ?? '') . " (Periode: " . $periodeTagihan . ")");
            $totalDPP = ($this->bill->amount ?? 0) - ($this->bill->total_tax ?? 0);

            $parentRow = [
                \Carbon\Carbon::parse($this->bill->invoice_date)->format('d-M-Y'), $companyName, $this->bill->vendor_name,
                $judulParent, "SUMMARY", $totalDPP, $this->bill->total_tax, $this->bill->amount,
                $this->bill->vendor_invoice_number ?? '-', $this->bill->account_number ?? '-', $this->bill->bill_number,
                $this->bill->user->name ?? 'System', $catatanParent, '-'
            ];

            $googleSheetRows = array_merge([$parentRow], $childRows);

            // 3. TULIS ULANG FORMASI BARU KE SHEET
            foreach ($googleSheetRows as $rowData) {
                $sheetService->appendRow($tabName, $rowData);
            }

            // 4. SUKSES! Update status DB Lokal
            if (\Schema::hasColumn('bill_requests', 'sync_status')) {
                $this->bill->update(['sync_status' => 'SUCCESS', 'sync_retries' => 0, 'last_sync_error' => null]);
            }

        } catch (\Exception $e) {
            // JIKA GAGAL (Koneksi Putus): Catat dan ulang nanti otomatis
            Log::error("Gagal Sync Bill {$this->bill->bill_number} ke Sheet: " . $e->getMessage());
            if (\Schema::hasColumn('bill_requests', 'sync_status')) {
                $this->bill->increment('sync_retries');
                $this->bill->update(['sync_status' => 'FAILED', 'last_sync_error' => substr($e->getMessage(), 0, 500)]);
            }
            throw $e;
        }
    }
}
