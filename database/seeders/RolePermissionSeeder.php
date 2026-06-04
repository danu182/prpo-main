<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Buat Daftar Izin (Permissions) sesuai Navbar + TAMBAHAN BARU
        $permissions = [
            'view_pr', 'view_po', 'view_bills',         // Purchasing
            'view_gr', 'view_inventory', 'manage_gi',   // Gudang & Receiving
            'view_assets', 'manage_items',              // IT / Master Data
            'view_invoices', 'view_payments',           // Finance
            'manage_roles',                             // Admin
            'view_reports'                              // Laporan (BARU)
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 3. Buat Role dan Assign Izinnya

        // A. Role GUDANG
        $roleGudang = Role::firstOrCreate(['name' => 'Staf Gudang']);
        $roleGudang->syncPermissions(['view_gr', 'view_inventory', 'manage_gi', 'manage_items']);

        // B. Role PURCHASING
        $rolePurchasing = Role::firstOrCreate(['name' => 'Purchasing']);
        $rolePurchasing->syncPermissions(['view_pr', 'view_po', 'view_bills', 'manage_items']);

        // C. Role FINANCE (Diberi tambahan akses view_reports)
        $roleFinance = Role::firstOrCreate(['name' => 'Finance']);
        $roleFinance->syncPermissions(['view_invoices', 'view_payments', 'view_bills', 'view_reports']);

        // D. Role IT / GA (General Affairs)
        $roleIT = Role::firstOrCreate(['name' => 'IT / GA Asset']);
        $roleIT->syncPermissions(['view_assets', 'manage_items']);

        // ==========================================
        // E. Role MANAGER (UNTUK APPROVAL)
        // ==========================================
        $roleManager = Role::firstOrCreate(['name' => 'manager']);
        $roleManager->syncPermissions(['view_pr', 'view_po', 'view_reports']);

        // ==========================================
        // F. Role DIREKTUR (UNTUK APPROVAL)
        // ==========================================
        $roleDirektur = Role::firstOrCreate(['name' => 'direktur']);
        $roleDirektur->syncPermissions(['view_po', 'view_payments', 'view_reports']);

        // ==========================================
        // G. Role STAFF (KARYAWAN BIASA)
        // ==========================================
        $roleStaff = Role::firstOrCreate(['name' => 'Staff']);
        $roleStaff->syncPermissions(['view_pr']); // HANYA BISA AKSES MENU PR UNTUK MINTA BARANG

        // H. Role SUPER ADMIN (Bisa semuanya!)
        $roleAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $roleAdmin->syncPermissions(Permission::all());

        // 4. Berikan Role Super Admin ke User Pertama (Biasanya akun Anda)
        $user = User::first();
        if ($user) {
            $user->assignRole('Super Admin');
        }
    }
}
