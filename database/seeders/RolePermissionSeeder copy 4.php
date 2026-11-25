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
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ==========================================
        // 1. DAFTAR PERMISSIONS (POLA: MENU -> VIEW -> MANAGE)
        // ==========================================
        $permissions = [
            // 📁 GRUP MASTER DATA
            'menu_master_data',
                'view_items', 'manage_items', 'approve_master', // 🔥 Tambah approve_master
                'view_companies', 'manage_companies',
                'view_vendors', 'manage_vendors',
                'view_assets_master',

            // 📁 GRUP PURCHASING
            'menu_purchasing',
                'view_pr', 'create_pr', 'manage_pr', 'approve_pr', // 🔥 Tambah manage_pr
                'view_po', 'create_po', 'manage_po', 'approve_po', // 🔥 Tambah manage_po
                'view_purchasing_reports',

            // 📁 GRUP RECEIVING
            'menu_receiving',
                'view_gr', 'create_gr',

            // 📁 GRUP WAREHOUSE
            'menu_warehouse',
                'view_inventory', 'manage_inventory',

            // 📁 GRUP FIXED ASSET
            'menu_assets',
                'view_assets', 'manage_assets',

            // 📁 GRUP FINANCE
            'menu_finance',
                'view_invoices', 'manage_invoices',
                'view_bills', 'manage_bills', 'approve_bills', // 🔥 Tambah approve_bills (OPEX)
                'view_payments', 'manage_payments',
                'view_inventory_valuation',

            // 📁 GRUP LAPORAN
            'menu_reports',
                'view_reports',

            // 📁 GRUP SETTING
            'menu_settings',
                'manage_roles'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // ==========================================
        // 2. PEMBAGIAN HAK AKSES PER JABATAN (Berdasarkan Tabel Baru)
        // ==========================================

        // A. USER / STAFF (Hanya membuat/manage tanpa hak setuju)
        $roleUser = Role::firstOrCreate(['name' => 'User']);
        $roleUser->syncPermissions([
            'menu_purchasing', 'view_pr', 'create_pr', 'manage_pr',
            'view_po', 'create_po', 'manage_po',
            'menu_finance', 'view_bills', 'manage_bills' // OPEX
        ]);

        // B. SUPERVISOR (Manage + Hak Setuju / Approval)
        $roleSupervisor = Role::firstOrCreate(['name' => 'Supervisor']);
        $roleSupervisor->syncPermissions([
            'menu_purchasing', 'view_pr', 'create_pr', 'manage_pr', 'approve_pr',
            'view_po', 'create_po', 'manage_po', 'approve_po',
            'menu_finance', 'view_bills', 'manage_bills', 'approve_bills', // OPEX
            'menu_master_data', 'view_items', 'manage_items', 'approve_master' // Master Barang
        ]);

        // C. MANAGER (Sama dengan Supervisor + Lihat Laporan)
        $roleManager = Role::firstOrCreate(['name' => 'Manager']);
        $roleManager->syncPermissions([
            'menu_purchasing', 'view_pr', 'create_pr', 'manage_pr', 'approve_pr',
            'view_po', 'create_po', 'manage_po', 'approve_po',
            'menu_finance', 'view_bills', 'manage_bills', 'approve_bills', // OPEX
            'menu_master_data', 'view_items', 'manage_items', 'approve_master', // Master Barang
            'menu_reports', 'view_reports' // Ekstra untuk level Manager
        ]);

        // ==========================================
        // 3. ROLE TAMBAHAN TETAP DIPERTAHANKAN
        // ==========================================

        $roleFinance = Role::firstOrCreate(['name' => 'Finance']);
        $roleFinance->syncPermissions([
            'menu_master_data', 'view_companies', 'view_vendors',
            'menu_purchasing', 'view_po',
            'menu_receiving', 'view_gr',
            'menu_finance', 'view_invoices', 'manage_invoices', 'view_bills', 'manage_bills', 'view_payments', 'manage_payments', 'view_inventory_valuation',
            'menu_reports', 'view_reports'
        ]);

        $rolePurchasing = Role::firstOrCreate(['name' => 'Purchasing']);
        $rolePurchasing->syncPermissions([
            'menu_master_data', 'view_items', 'manage_items', 'view_vendors', 'manage_vendors',
            'menu_purchasing', 'view_pr', 'view_po', 'create_po', 'view_purchasing_reports',
            'menu_finance', 'view_bills'
        ]);

        $roleGudang = Role::firstOrCreate(['name' => 'Gudang']);
        $roleGudang->syncPermissions([
            'menu_purchasing', 'view_po',
            'menu_receiving', 'view_gr', 'create_gr',
            'menu_warehouse', 'view_inventory', 'manage_inventory'
        ]);

        $roleAsset = Role::firstOrCreate(['name' => 'Opex & Asset']);
        $roleAsset->syncPermissions([
            'menu_master_data', 'view_assets_master',
            'menu_assets', 'view_assets', 'manage_assets',
            'menu_finance', 'view_bills', 'manage_bills',
            'menu_warehouse', 'view_inventory'
        ]);

        $roleDirektur = Role::firstOrCreate(['name' => 'Direktur']);
        $roleDirektur->syncPermissions([
            'menu_purchasing', 'view_pr', 'view_po', 'approve_po',
            'menu_finance', 'view_payments',
            'menu_reports', 'view_reports'
        ]);

        $roleAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $roleAdmin->syncPermissions(Permission::all());

        $user = User::first();
        if ($user) $user->assignRole('Super Admin');
    }
}
