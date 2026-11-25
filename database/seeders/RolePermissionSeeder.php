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
                'view_items', 'manage_items', 'approve_master',
                'view_companies', 'manage_companies',
                'view_vendors', 'manage_vendors',
                'view_assets_master',

            // 📁 GRUP PURCHASING
            'menu_purchasing',
                'view_pr', 'create_pr', 'manage_pr', 'approve_pr',
                'view_po', 'create_po', 'manage_po', 'approve_po',
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
                'view_bills', 'manage_bills', 'approve_bills',
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
        // 2. PEMBAGIAN HAK AKSES PER JABATAN
        // ==========================================

        // A. STAFF / USER (Membuat pengajuan PR/Opex tanpa hak approval)
        $staffPermissions = [
            'menu_purchasing', 'view_pr', 'create_pr', 'manage_pr',
            'view_po', 'create_po', 'manage_po',
            'menu_finance', 'view_bills', 'manage_bills',
        ];
        Role::firstOrCreate(['name' => 'Staff'])->syncPermissions($staffPermissions);
        Role::firstOrCreate(['name' => 'User'])->syncPermissions($staffPermissions);

        // B. SUPERVISOR (Manage + Hak Setuju / Approval)
        Role::firstOrCreate(['name' => 'Supervisor'])->syncPermissions([
            'menu_purchasing', 'view_pr', 'create_pr', 'manage_pr', 'approve_pr',
            'view_po', 'create_po', 'manage_po', 'approve_po',
            'menu_finance', 'view_bills', 'manage_bills', 'approve_bills',
            'menu_master_data', 'view_items', 'manage_items', 'approve_master',
        ]);

        // C. MANAGER / ASSISTANT MANAGER / SENIOR MANAGER / GENERAL MANAGER
        Role::firstOrCreate(['name' => 'Manager'])->syncPermissions([
            'menu_purchasing', 'view_pr', 'create_pr', 'manage_pr', 'approve_pr',
            'view_po', 'create_po', 'manage_po', 'approve_po',
            'menu_finance', 'view_bills', 'manage_bills', 'approve_bills',
            'menu_master_data', 'view_items', 'manage_items', 'approve_master',
            'menu_reports', 'view_reports',
        ]);

        // D. FINANCE (Departemen Accounting)
        Role::firstOrCreate(['name' => 'Finance'])->syncPermissions([
            'menu_master_data', 'view_companies', 'view_vendors',
            'menu_purchasing', 'view_po',
            'menu_receiving', 'view_gr',
            'menu_finance', 'view_invoices', 'manage_invoices', 'view_bills', 'manage_bills', 'approve_bills', 'view_payments', 'manage_payments', 'view_inventory_valuation',
            'menu_reports', 'view_reports',
        ]);

        // E. PURCHASING
        Role::firstOrCreate(['name' => 'Purchasing'])->syncPermissions([
            'menu_master_data', 'view_items', 'manage_items', 'view_vendors', 'manage_vendors',
            'menu_purchasing', 'view_pr', 'view_po', 'create_po', 'manage_po', 'view_purchasing_reports',
            'menu_finance', 'view_bills',
        ]);

        // F. GUDANG
        Role::firstOrCreate(['name' => 'Gudang'])->syncPermissions([
            'menu_purchasing', 'view_po',
            'menu_receiving', 'view_gr', 'create_gr',
            'menu_warehouse', 'view_inventory', 'manage_inventory',
        ]);

        // G. OPEX & ASSET (Departemen HR & GA / IT & WEB)
        Role::firstOrCreate(['name' => 'Opex & Asset'])->syncPermissions([
            'menu_master_data', 'view_assets_master', 'view_items', 'manage_items',
            'menu_purchasing', 'view_pr', 'create_pr', 'manage_pr',
            'menu_assets', 'view_assets', 'manage_assets',
            'menu_finance', 'view_bills', 'manage_bills',
            'menu_warehouse', 'view_inventory', 'manage_inventory',
        ]);

        // H. DIREKTUR
        Role::firstOrCreate(['name' => 'Direktur'])->syncPermissions([
            'menu_purchasing', 'view_pr', 'approve_pr', 'view_po', 'approve_po',
            'menu_finance', 'view_bills', 'approve_bills', 'view_payments',
            'menu_assets', 'view_assets',
            'menu_reports', 'view_reports',
        ]);

        // I. SUPER ADMIN
        $roleAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $roleAdmin->syncPermissions(Permission::all());

        $user = User::where('email', 'admin@app.com')->first() ?? User::first();
        if ($user) {
            $user->assignRole('Super Admin');
        }
    }
}
