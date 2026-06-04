<?php

namespace App\Support;

use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Models\BillRequest;

class BillPathGenerator implements PathGenerator
{
    /*
     * Menentukan path penyimpanan file utama
     */
    public function getPath(Media $media): string
    {
        // Cek apakah file ini milik model BillRequest?
        if ($media->model_type === BillRequest::class) {

            // Ambil Nomor Tagihan dari database
            $billNumber = $media->model->bill_number;

            // Bersihkan format nomor (Ganti tanda '/' jadi '-' agar tidak jadi sub-folder dalam)
            // Contoh: "BILL/2026/02/001" menjadi "BILL-2026-02-001"
            $folderName = str_replace(['/', '\\'], '-', $billNumber);

            // Hasil: storage/app/public/tagihan/BILL-2026-02-001/{id_file}/
            // {id} tetap diperlukan oleh Spatie agar jika ada nama file sama tidak tertimpa
            return "tagihan/{$folderName}/{$media->id}/";
        }

        // Jika bukan BillRequest (misal Avatar User), pakai default Spatie (berdasarkan ID)
        return $media->id . '/';
    }

    /*
     * Menentukan path untuk hasil konversi (Thumbnail, dll)
     */
    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media) . 'conversions/';
    }

    /*
     * Menentukan path untuk responsive images (jika pakai)
     */
    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media) . 'responsive-images/';
    }
}
