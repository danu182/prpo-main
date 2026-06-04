<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;
use App\Models\Company;
use App\Models\Vendor;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // 1. Reset Cached Roles/Permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ====================================================
        // 2. SETUP PERUSAHAAN (COMPANY)
        // ====================================================
        // Kita buat dulu agar ID-nya tersedia untuk User
        $ho = Company::firstOrCreate(
            ['code' => 'HO'],
            ['name' => 'Head Office', 'is_head_office' => true, 'address'=>'Jl. pasar minggu jakarta']

        );

        $sub1 = Company::firstOrCreate(
            ['code' => 'SUB1'],
            ['name' => 'PT Anak Usaha Satu'],
            ['address' => 'Jl. pasar Baru jakarta'],
        );

        // ====================================================
        // 3. PANGGIL SEEDER LAIN (DATA MASTER)
        // ====================================================
        // DirectorSeeder & UserSeeder dihapus dari list karena isinya sudah digabung di sini
        $this->call([

        // 🔥 TAMBAHKAN UOM SEEDER DI SINI 🔥
            UomSeeder::class,
            // AdminUserSeeder::class,
            WarehouseSeeder::class,
            ItemConditionSeeder::class,
            ItemTypeSeeder::class,
            VendorSeeder::class,
            CompanySeeder::class,
            CategorySeeder::class,
            ItemsSeeder::class,
            CurrencySeeder::class,
            FinanceSeeder::class,
            PaymentMethodSeeder::class,
            TaxSeeder::class,
            ChargeTypeSeeder::class,
            PaymentTermSeeder::class,
            StatusSeeder::class,
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            DiscountTypeSeeder::class,
            ReturnReasonSeeder::class,
            ApprovalWorkflowSeeder::class,
            SystemSettingSeeder::class,
            DocumentTypeSeeder::class,
            BankDataSeeder::class,
            

        ]);



        // ====================================================
        // 7. DATA TAMBAHAN (Vendor Manual)
        // ====================================================
        Vendor::firstOrCreate(['name' => 'CV Maju Jaya'], ['phone' => '08123456789']);
        Vendor::firstOrCreate(['name' => 'PT Elektronik Sentosa'], ['phone' => '021-555555']);

        $this->command->info('Database Seeding Completed Successfully.');
        $this->command->info('Users Created: staff@app.com, manager@app.com, director@app.com (Pass: 123)');
    }
}
