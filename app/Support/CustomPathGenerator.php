<?php

namespace App\Support;

use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Models\PurchaseRequestItemVendor;
use Illuminate\Support\Facades\Log;

class CustomPathGenerator implements PathGenerator
{
    /*
     * Tentukan path penyimpanan:
     * Format: pr_uploads/{NOMOR_PR_SANITIZED}/{MEDIA_ID}/
     */
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media) . '/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media) . '/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media) . '/responsive-images/';
    }

    /*
     * LOGIKA UTAMA ADA DISINI
     */
    protected function getBasePath(Media $media): string
    {

        Log::info('Model Type: ' . $media->model_type);
        Log::info('Target Class: ' . PurchaseRequestItemVendor::class);

       if ($media->model_type === PurchaseRequestItemVendor::class) {

            // Coba ambil data lengkap
            $vendorQuote = PurchaseRequestItemVendor::with('item.purchaseRequest')->find($media->model_id);

            // --- DEBUGGING: TULIS KE LOG SIAPA YANG HILANG ---
            if (! $vendorQuote) {
                Log::error("GAGAL: Data VendorQuote ID {$media->model_id} tidak ditemukan di database.");
            } elseif (! $vendorQuote->item) {
                Log::error("GAGAL: VendorQuote ID {$media->model_id} ada, tapi ITEM-nya null. Cek kolom 'purchase_request_item_id'.");
            } elseif (! $vendorQuote->item->purchaseRequest) {
                Log::error("GAGAL: Item ID {$vendorQuote->item->id} ada, tapi PR-nya null. Cek kolom 'purchase_request_id'.");
            } else {
                Log::info("SUKSES: Semua data lengkap! PR Number: " . $vendorQuote->item->purchaseRequest->pr_number);
            }
            // ------------------------------------------------

            // Jika semua data ada, baru gunakan Folder PR
            if ($vendorQuote && $vendorQuote->item && $vendorQuote->item->purchaseRequest) {
                $prNumber = $vendorQuote->item->purchaseRequest->pr_number;
                $safePrNumber = str_replace('/', '-', $prNumber);

                return "pr_uploads/{$safePrNumber}/{$media->id}";
            }
        }

        // Jika gagal atau bukan VendorQuote, pakai ID biasa
        return $media->id;
    }
}
