<?php

namespace App\Support;

use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Models\BillRequest;
use App\Models\BillPayment; // <--- Tambahkan ini
use App\Models\PurchaseRequestItemVendor;

class MainPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        // =========================================================
        // KASUS 1: TAGIHAN (BILL REQUEST)
        // =========================================================
        if ($media->model_type === BillRequest::class || $media->model_type === 'App\Models\BillRequest') {

            // Safety Load
            $model = $media->model;
            if (!$model) {
                $model = BillRequest::find($media->model_id);
            }

            if ($model) {
                $billNumber = $model->bill_number ?? 'DRAFT';
                $safeBillNumber = str_replace(['/', '\\', ' '], '-', $billNumber);
                return "tagihan/{$safeBillNumber}/{$media->id}/";
            }
        }

        // =========================================================
        // KASUS 2: PEMBAYARAN (BILL PAYMENT) - PERBAIKAN DI SINI
        // =========================================================
        if ($media->model_type === BillPayment::class || $media->model_type === 'App\Models\BillPayment') {

            // 1. Safety Load (PENTING: Agar tidak error "on null")
            $model = $media->model;
            if (!$model) {
                $model = BillPayment::find($media->model_id);
            }

            if ($model) {
                // 2. Ambil Nomor Pembayaran
                $payNumber = $model->payment_number ?? 'PAY-UNKNOWN';

                // 3. Bersihkan simbol miring
                $safeName = str_replace(['/', '\\', ' '], '-', $payNumber);

                // Hasil: storage/app/public/pembayaran/TRF-BCA-001/15/
                return "pembayaran/{$safeName}/{$media->id}/";
            }
        }

        // =========================================================
        // KASUS 3: PR VENDOR
        // =========================================================
        if ($media->model_type === PurchaseRequestItemVendor::class || $media->model_type === 'App\Models\PurchaseRequestItemVendor') {

            $vendorQuote = PurchaseRequestItemVendor::with('item.purchaseRequest')->find($media->model_id);

            if ($vendorQuote && $vendorQuote->item && $vendorQuote->item->purchaseRequest) {
                $prNumber = $vendorQuote->item->purchaseRequest->pr_number;
                $safePrNumber = str_replace(['/', '\\', ' '], '-', $prNumber);
                return "pr_uploads/{$safePrNumber}/{$media->id}/";
            }
        }

        // =========================================================
        // DEFAULT (Jika tidak masuk kategori di atas)
        // =========================================================
        return $media->id . '/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media) . 'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media) . 'responsive-images/';
    }
}
