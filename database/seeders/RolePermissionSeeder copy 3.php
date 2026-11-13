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
                'view_items', 'manage_items',
                'view_companies', 'manage_companies',
                'view_vendors', 'manage_vendors',
                'view_assets_master', // Untuk Kategori Aset

            // 📁 GRUP PURCHASING
            'menu_purchasing',
                'view_pr', 'create_pr', 'approve_pr',
                'view_po', 'create_po', 'approve_po',
                'view_purchasing_reports', // Untuk analisa harga

            // 📁 GRUP RECEIVING
            'menu_receiving',
                'view_gr', 'create_gr',

            // 📁 GRUP WAREHOUSE
            'menu_warehouse',
                'view_inventory', 'manage_inventory', // Manage mencakup GI, mutasi, opname

            // 📁 GRUP FIXED ASSET
            'menu_assets',
                'view_assets', 'manage_assets',

            // 📁 GRUP FINANCE
            'menu_finance',
                'view_invoices', 'manage_invoices', // A/P Tagihan Vendor
                'view_bills', 'manage_bills',       // OPEX
                'view_payments', 'manage_payments', // Proses Bayar
                'view_inventory_valuation',         // Valuasi Rp

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

        // A. STAFF (Hanya bisa buka menu Purchasing & Finance tertentu)
        $roleStaff = Role::firstOrCreate(['name' => 'Staff']);
        $roleStaff->syncPermissions([
            'menu_purchasing', 'view_pr', 'create_pr',
            'menu_finance', 'view_bills', 'manage_bills'
        ]);

        // B. FINANCE (Dewa di area Keuangan)
        $roleFinance = Role::firstOrCreate(['name' => 'Finance']);
        $roleFinance->syncPermissions([
            'menu_master_data', 'view_companies', 'view_vendors',
            'menu_purchasing', 'view_po',
            'menu_receiving', 'view_gr',
            'menu_finance', 'view_invoices', 'manage_invoices', 'view_bills', 'manage_bills', 'view_payments', 'manage_payments', 'view_inventory_valuation',
            'menu_reports', 'view_reports'
        ]);

        // C. PURCHASING (Dewa di area Belanja & Vendor)
        $rolePurchasing = Role::firstOrCreate(['name' => 'Purchasing']);
        $rolePurchasing->syncPermissions([
            'menu_master_data', 'view_items', 'manage_items', 'view_vendors', 'manage_vendors',
            'menu_purchasing', 'view_pr', 'view_po', 'create_po', 'view_purchasing_reports',
            'menu_finance', 'view_bills'
        ]);

        // D. GUDANG (Dewa di area Fisik Barang)
        $roleGudang = Role::firstOrCreate(['name' => 'Gudang']);
        $roleGudang->syncPermissions([
            'menu_purchasing', 'view_po',
            'menu_receiving', 'view_gr', 'create_gr',
            'menu_warehouse', 'view_inventory', 'manage_inventory'
        ]);

        // E. OPEX & ASSET
        $roleAsset = Role::firstOrCreate(['name' => 'Opex & Asset']);
        $roleAsset->syncPermissions([
            'menu_master_data', 'view_assets_master',
            'menu_assets', 'view_assets', 'manage_assets',
            'menu_finance', 'view_bills', 'manage_bills',
            'menu_warehouse', 'view_inventory'
        ]);

        // F. MANAGER (Level Approval & Laporan)
        $roleManager = Role::firstOrCreate(['name' => 'Manager']);
        $roleManager->syncPermissions([
            'menu_purchasing', 'view_pr', 'approve_pr', 'view_po', 'approve_po',
            'menu_finance', 'view_bills',
            'menu_reports', 'view_reports'
        ]);

        // G. DIREKTUR (Level Approval Puncak & Laporan)
        $roleDirektur = Role::firstOrCreate(['name' => 'Direktur']);
        $roleDirektur->syncPermissions([
            'menu_purchasing', 'view_pr', 'view_po', 'approve_po',
            'menu_finance', 'view_payments',
            'menu_reports', 'view_reports'
        ]);

        // H. SUPER ADMIN (Semua Akses)
        $roleAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $roleAdmin->syncPermissions(Permission::all());

        $user = User::first();
        if ($user) $user->assignRole('Super Admin');
    }
}
