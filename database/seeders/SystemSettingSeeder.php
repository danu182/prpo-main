<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $settings = [
            [
                'setting_key' => 'path_pr_attachment',
                'setting_value' => 'attachments/purchase_requests',
                'description' => 'Folder penyimpanan file lampiran PR'
            ],
            [
                'setting_key' => 'path_po_attachment',
                'setting_value' => 'attachments/purchase_orders',
                'description' => 'Folder penyimpanan file lampiran PO'
            ],
            [
                'setting_key' => 'path_gr_attachment',
                'setting_value' => 'attachments/goods_receipts',
                'description' => 'Folder penyimpanan file lampiran GR'
            ],
            [
                'setting_key' => 'path_invoice_attachment',
                'setting_value' => 'attachments/invoices',
                'description' => 'Folder penyimpanan file lampiran Dokumen Invoice (Faktur Pajak, Garansi, dll).'
            ],
            [
                'setting_key' => 'path_payment_attachment',
                'setting_value' => 'attachments/payments',
                'description' => 'Folder penyimpanan file lampiran Payment'
            ],
            [
                'setting_key' => 'path_rtv_attachment',
                'setting_value' => 'attachments/rtv',
                'description' => 'Folder penyimpanan file lampiran RTV'
            ],
            
        ];


        foreach ($settings as $data) {
            SystemSetting::updateOrCreate(
                ['setting_key' => $data['setting_key']],
                $data
            );
        }


    }
}
