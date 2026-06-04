<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SystemSettingService
{
    /**
     * Mengambil nilai pengaturan dari database berdasarkan Key.
     * Menggunakan Cache (1 jam) agar database tidak jebol saat banyak proses upload.
     */
    public function getSetting(string $key, string $default = '')
    {
        return Cache::remember('setting_' . $key, 3600, function () use ($key, $default) {
            $setting = SystemSetting::where('setting_key', $key)->first();
            return $setting ? $setting->setting_value : $default;
        });
    }

    /**
     * Fungsi spesifik agar lebih enak dibaca saat dipanggil untuk Path Direktori
     */
    public function getAttachmentPath(string $module)
    {
        $keys = [
            'PR'  => 'path_pr_attachment',
            'PO'  => 'path_po_attachment',
            'GR'  => 'path_gr_attachment',
            'RTV' => 'path_rtv_attachment',
        ];

        $key = $keys[$module] ?? 'path_general_attachment';
        $defaultPath = strtolower($module) . '_attachments'; // Default fallback: gr_attachments

        return $this->getSetting($key, $defaultPath);
    }
}