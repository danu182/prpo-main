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
        // 1. Bersihkan cache Spatie agar permission baru langsung terbaca
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ==========================================
        // 2. DAFTARKAN SEMUA IZIN (PERMISSIONS) GRANULAR
        // ==========================================
        $permissions = [
            // 🔥 TAMBAHAN: Hak Akses Master Data Perusahaan & Vendor 🔥
            'view_companies',
            'view_vendors',

            // Hak Akses PR (Purchase Requisition)
            'view_pr',
            'create_pr',
            'edit_own_pr',  // Khusus Staff edit PR miliknya sendiri
            'approve_pr',   // Khusus Atasan

            // Hak Akses PO (Purchase Order)
            'view_po',
            'create_po',
            'approve_po',   // Khusus Atasan/Direktur

            // Hak Akses Gudang (Inventory & Receiving)
            'view_gr',
            'create_gr',    // Terima Barang
            'view_inventory',
            'manage_gi',    // Keluarkan Barang (Goods Issue)

            // Hak Akses Master Data & Aset (Opex / Capex)
            'manage_items',
            'view_assets',
            'manage_assets',
            'manage_opex',

            // Hak Akses Keuangan (Finance)
            'view_bills',
            'view_invoices',
            'manage_invoices',
            'view_payments',
            'manage_payments',

            // Hak Akses General & Admin
            'view_reports',
            'manage_roles'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // ==========================================
        // 3. BUAT ROLE & SINKRONISASI HAK AKSES
        // ==========================================

        // A. ROLE STAFF
        $roleStaff = Role::firstOrCreate(['name' => 'Staff']);
        $roleStaff->syncPermissions([
            'view_pr', 'create_pr', 'edit_own_pr'
        ]);

        // B. ROLE SUPERVISOR
        $roleSupervisor = Role::firstOrCreate(['name' => 'Supervisor']);
        $roleSupervisor->syncPermissions([
            'view_pr', 'approve_pr', 'view_reports'
        ]);

        // C. ROLE MANAGER
        $roleManager = Role::firstOrCreate(['name' => 'Manager']);
        $roleManager->syncPermissions([
            // Tambahkan view_companies & view_vendors
            'view_companies', 'view_vendors',
            'view_pr', 'approve_pr', 'view_po', 'approve_po', 'view_reports'
        ]);

        // D. ROLE DIREKTUR
        $roleDirektur = Role::firstOrCreate(['name' => 'Direktur']);
        $roleDirektur->syncPermissions([
            // Tambahkan view_companies & view_vendors
            'view_companies', 'view_vendors',
            'view_pr', 'view_po', 'approve_po', 'view_payments', 'view_reports'
        ]);

        // E. ROLE PURCHASING
        $rolePurchasing = Role::firstOrCreate(['name' => 'Purchasing']);
        $rolePurchasing->syncPermissions([
            // Tambahkan view_companies & view_vendors
            'view_companies', 'view_vendors',
            'view_pr', 'view_po', 'create_po', 'manage_items', 'view_bills'
        ]);

        // F. ROLE GUDANG
        $roleGudang = Role::firstOrCreate(['name' => 'Gudang']);
        $roleGudang->syncPermissions([
            'view_po', 'view_gr', 'create_gr', 'view_inventory', 'manage_gi', 'manage_items'
        ]);

        // G. ROLE OPEX & FIXED ASSET
        $roleAsset = Role::firstOrCreate(['name' => 'Opex & Asset']);
        $roleAsset->syncPermissions([
            'view_assets', 'manage_assets', 'manage_opex', 'manage_items', 'view_inventory'
        ]);

        // H. ROLE FINANCE
        $roleFinance = Role::firstOrCreate(['name' => 'Finance']);
        $roleFinance->syncPermissions([
            // Tambahkan view_companies & view_vendors
            'view_companies', 'view_vendors',
            'view_po', 'view_gr', 'view_bills', 'view_invoices', 'manage_invoices', 'view_payments', 'manage_payments', 'view_reports'
        ]);

        // I. ROLE SUPER ADMIN (Bisa Semua Akses!)
        $roleAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $roleAdmin->syncPermissions(Permission::all());

        // ==========================================
        // 4. BERIKAN SUPER ADMIN KE USER ID 1
        // ==========================================
        $user = User::first();
        if ($user) {
            $user->assignRole('Super Admin');
        }
    }
}
