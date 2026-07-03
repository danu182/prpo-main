<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // 1. Reset Cached Roles/Permissions (Wajib jalan duluan)
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ====================================================
        // 2. PANGGIL SEEDER DENGAN URUTAN YANG BENAR (LOGIS)
        // ====================================================
        $this->call([
            // A. Pondasi Sistem & Otoritas
            RolePermissionSeeder::class,     // Bikin Role (Admin, Staff, dll) duluan
            SystemSettingSeeder::class,      // Pengaturan Sistem

            // B. Pondasi Perusahaan & Karyawan
            CompanySeeder::class,            // Bikin PT DestinAsian, HO, dll duluan
            DepartmentAndUserSeeder::class,  // Bikin Departemen & User Excel (Butuh PT)
            AdminUserSeeder::class,          // Bikin User Admin & Manager (Butuh PT)

            // C. Pondasi Master Data (Aset, Gudang, Barang)
            UomSeeder::class,
            CategorySeeder::class,
            WarehouseSeeder::class,
            ItemConditionSeeder::class,
            ItemTypeSeeder::class,
            VendorSeeder::class,
            ItemsSeeder::class,

            // D. Pondasi Keuangan (Finance & Purchasing)
            CurrencySeeder::class,
            FinanceSeeder::class,
            PaymentMethodSeeder::class,
            TaxSeeder::class,
            ChargeTypeSeeder::class,
            PaymentTermSeeder::class,
            StatusSeeder::class,
            DiscountTypeSeeder::class,
            ReturnReasonSeeder::class,
            ApprovalWorkflowSeeder::class,
            DocumentTypeSeeder::class,
            BankDataSeeder::class,
            ImportUserSeeder::class,

        ]);

        $this->command->info('=============================================');
        $this->command->info('🚀 BINGO! DATABASE SEEDING SUKSES SEMPURNA 🚀');
        $this->command->info('=============================================');
    }
}
