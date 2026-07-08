<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $settings = [
            // ==========================================
            // MODUL PROCUREMENT & INVENTORY
            // ==========================================
            [
                'setting_key'   => 'path_pr_attachment',
                'setting_value' => 'attachments/purchase_requests',
                'description'   => 'Folder penyimpanan file lampiran PR'
            ],
            [
                'setting_key'   => 'path_po_attachment',
                'setting_value' => 'attachments/purchase_orders',
                'description'   => 'Folder penyimpanan file lampiran PO'
            ],
            [
                'setting_key'   => 'path_gr_attachment',
                'setting_value' => 'attachments/goods_receipts',
                'description'   => 'Folder penyimpanan file lampiran GR'
            ],
            [
                'setting_key'   => 'path_invoice_attachment',
                'setting_value' => 'attachments/invoices',
                'description'   => 'Folder penyimpanan file lampiran Dokumen Invoice (Faktur Pajak, Garansi, dll).'
            ],
            [
                'setting_key'   => 'path_payment_attachment',
                'setting_value' => 'attachments/payments',
                'description'   => 'Folder penyimpanan file lampiran Payment'
            ],
            [
                'setting_key'   => 'path_rtv_attachment',
                'setting_value' => 'attachments/rtv',
                'description'   => 'Folder penyimpanan file lampiran RTV'
            ],

            // ==========================================
            // MODUL FINANCE / OPEX
            // ==========================================
            [
                'setting_key'   => 'path_bills_opex',
                'setting_value' => 'attachments/opex',
                'description'   => 'Folder penyimpanan file lampiran Bills/Tagihan Opex'
            ],
            [
                'setting_key'   => 'path_payment_opex',
                'setting_value' => 'attachments/payment_opex',
                'description'   => 'Folder penyimpanan file lampiran Bukti Bayar Opex'
            ],

            // ==========================================
            // MODUL FIXED ASSETS (ASET TETAP)
            // ==========================================
            [
                'setting_key'   => 'path_asset_import',
                'setting_value' => 'attachments/asset_imports',
                'description'   => 'Folder penyimpanan lampiran Import / Karantina Aset'
            ],
            [
                'setting_key'   => 'path_asset_manual',
                'setting_value' => 'attachments/asset_manual',
                'description'   => 'Folder penyimpanan lampiran Registrasi Aset Manual (Hibah)'
            ],

             // ==========================================
            // MODUL users
            // ==========================================
            [
                'setting_key'   => 'path_user_profile',
                'setting_value' => 'users',
                'description'   => 'Folder penyimpanan lampiran foto profile dan tanda tanagan users'
            ],
        ];

        foreach ($settings as $data) {
            SystemSetting::updateOrCreate(
                ['setting_key' => $data['setting_key']], // Cari berdasarkan key ini
                $data                                    // Update atau Create dengan data ini
            );
        }
    }
}
