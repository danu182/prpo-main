<?php

namespace App\Jobs;

use App\Models\PurchaseOrder;
use App\Services\GoogleSheetService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncPoToGoogleSheet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $po;

    // Maksimal percobaan ulang jika Google API down/Internet mati
    public $tries = 5;

    public function __construct(PurchaseOrder $po)
    {
        $this->po = $po;
    }

    public function handle()
    {
        try {
            $this->po->load(['items.item.category', 'vendor', 'company', 'user']);

            $companyName = optional($this->po->company)->name ?? '-';
            $vendorName  = optional($this->po->vendor)->name ?? '-';
            $poDate      = \Carbon\Carbon::parse($this->po->po_date)->format('d-M-Y');

            $childRows = [];

            // 1. LOOPING ITEM PO
            foreach ($this->po->items as $item) {
                $infoTambahan = "";
                if ($item->discount_amount > 0) {
                    $infoTambahan .= (strtoupper($item->discount_type) === 'PERCENT') ? " (-Disc {$item->discount_value}%)" : " (-Diskon Rp" . number_format($item->discount_amount, 0, ',', '.') . ")";
                }
                if ($item->tax_amount > 0) {
                    $infoTambahan .= (strtoupper($item->tax_type) === 'PERCENT') ? " (+PPN {$item->tax_value}%)" : " (+Pajak Rp" . number_format($item->tax_amount, 0, ',', '.') . ")";
                }

                $dppSheet = $item->subtotal;
                $categoryName = optional(optional($item->item)->category)->name ?? 'Lainnya';
                $deskripsiLayanan = html_entity_decode(strip_tags($item->item_name ?? optional($item->item)->name ?? 'Item Tanpa Nama'));

                $childRows[] = [
                    $poDate, $companyName, $vendorName,
                    "  ↳ [Item] " . $deskripsiLayanan . $infoTambahan, $categoryName,
                    $dppSheet, $item->tax_amount ?? 0, $dppSheet + ($item->tax_amount ?? 0),
                    $this->po->invoice_number ?? '-', $this->po->account_number ?? '-', $this->po->po_number, $this->po->user->name ?? 'System', '-', html_entity_decode(strip_tags($item->description ?? '-'))
                ];
            }

            // 2. LOOPING BIAYA TAMBAHAN PO
            $charges = \DB::table('purchase_order_charges')->where('purchase_order_id', $this->po->id)->get();
            foreach ($charges as $charge) {
                $childRows[] = [
                    $poDate, $companyName, $vendorName, "  ↳ [+] " . html_entity_decode(strip_tags($charge->name)), 'Biaya Tambahan',
                    $charge->amount, 0, $charge->amount, $this->po->invoice_number ?? '-', $this->po->account_number ?? '-', $this->po->po_number, $this->po->user->name ?? 'System', '-', '-'
                ];
            }

            // 3. LOOPING POTONGAN/DISKON TAMBAHAN PO
            $discounts = \DB::table('purchase_order_discounts')->where('purchase_order_id', $this->po->id)->get();
            foreach ($discounts as $discount) {
                $childRows[] = [
                    $poDate, $companyName, $vendorName, "  ↳ [-] " . html_entity_decode(strip_tags($discount->name)), 'Potongan / Diskon',
                    -abs($discount->amount), 0, -abs($discount->amount), $this->po->invoice_number ?? '-', $this->po->account_number ?? '-', $this->po->po_number, $this->po->user->name ?? 'System', '-', '-'
                ];
            }

            // 4. DISKON GLOBAL
            $sumItemDisc = $this->po->items->sum('discount_amount');
            $actualGlobalDisc = (float)($this->po->discount_total ?? 0) - $sumItemDisc;
            if ($actualGlobalDisc > 0) {
                $labelDiskonGlobal = (isset($this->po->global_discount_type) && strtoupper($this->po->global_discount_type) === 'PERCENT') ? "Diskon Komersial {$this->po->global_discount_value}%" : "Diskon Komersial (Global)";
                $childRows[] = [
                    $poDate, $companyName, $vendorName, "  ↳ [-] " . $labelDiskonGlobal, 'Potongan / Diskon',
                    -$actualGlobalDisc, 0, -$actualGlobalDisc, $this->po->invoice_number ?? '-', $this->po->account_number ?? '-', $this->po->po_number, $this->po->user->name ?? 'System', '-', '-'
                ];
            }

            // 5. PAJAK GLOBAL
            $sumItemTax = $this->po->items->sum('tax_amount');
            $actualGlobalTax = (float)($this->po->tax_total ?? 0) - $sumItemTax;
            if ($actualGlobalTax > 0) {
                $labelPajakGlobal = (isset($this->po->global_tax_type) && strtoupper($this->po->global_tax_type) === 'PERCENT') ? "Pajak (VAT/PPN) {$this->po->global_tax_value}%" : "Pajak Header (VAT/PPN)";
                $childRows[] = [
                    $poDate, $companyName, $vendorName, "  ↳ [+] " . $labelPajakGlobal, 'Pajak',
                    $actualGlobalTax, 0, $actualGlobalTax, $this->po->invoice_number ?? '-', $this->po->account_number ?? '-', $this->po->po_number, $this->po->user->name ?? 'System', '-', '-'
                ];
            }

            // 6. PARENT ROW
            $globalDiscLabel = ($this->po->global_discount_value > 0) ? (($this->po->global_discount_type === 'PERCENT') ? " (-Disc Global {$this->po->global_discount_value}%)" : " (-Disc Global Rp" . number_format($this->po->global_discount_value, 0, ',', '.')) : "";
            $globalTaxLabel = ($this->po->global_tax_value > 0) ? (($this->po->global_tax_type === 'PERCENT') ? " (+PPN Global {$this->po->global_tax_value}%)" : " (+Pajak Global Rp" . number_format($this->po->global_tax_value, 0, ',', '.')) : "";

            $judulParent = "⭐ GRAND TOTAL PO" . $globalTaxLabel . $globalDiscLabel;
            $catatanParent = html_entity_decode(strip_tags(trim($this->po->notes ?? '-')));
            $totalDPP = ($this->po->grand_total ?? 0) - ($this->po->tax_total ?? 0);

            $parentRow = [
                $poDate, $companyName, $vendorName, $judulParent, "SUMMARY",
                $totalDPP, $this->po->tax_total ?? 0, $this->po->grand_total ?? 0,
                $this->po->invoice_number ?? '-', $this->po->account_number ?? '-', $this->po->po_number, $this->po->user->name ?? 'System', $catatanParent, '-'
            ];

            $googleSheetRows = array_merge([$parentRow], $childRows);

            // ====================================================================
            // 🔥 FASE KRUSIAL: EKSEKUSI API GOOGLE (IDEMPOTENT) 🔥
            // ====================================================================
            $sheetService = new GoogleSheetService();
            $tabName = env('GOOGLE_SHEET_OPEX_TAB_NAME', 'Sheet1');

            // Hapus dulu (Aman jika sebelumnya gagal, karena akan menghapus sisa)
            $sheetService->deleteRowsByBillNumber($tabName, $this->po->po_number);

            // Tulis ulang
            foreach ($googleSheetRows as $rowData) {
                $sheetService->appendRow($tabName, $rowData);
            }

            // TANDAI SUKSES DI DATABASE
            $this->po->update(['sync_status' => 'SUCCESS', 'sync_retries' => 0, 'last_sync_error' => null]);

        } catch (\Exception $e) {
            // JIKA INTERNET MATI / API GOOGLE ERROR:
            Log::error("Gagal Sync PO {$this->po->po_number} ke Sheet: " . $e->getMessage());

            $this->po->increment('sync_retries');
            $this->po->update([
                'sync_status' => 'FAILED',
                'last_sync_error' => substr($e->getMessage(), 0, 500)
            ]);

            // Lempar error agar Job ini dianggap "Failed" dan dicoba lagi oleh Laravel Queue
            throw $e;
        }
    }
}
