<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // ==========================================
        // 1. PASTIKAN ROLE SUDAH ADA DI DATABASE
        // ==========================================
        Role::firstOrCreate(['name' => 'Super Admin']);
        Role::firstOrCreate(['name' => 'Finance']);
        Role::firstOrCreate(['name' => 'Gudang']);
        Role::firstOrCreate(['name' => 'Purchasing']);
        Role::firstOrCreate(['name' => 'manager']);
        Role::firstOrCreate(['name' => 'direktur']);
        Role::firstOrCreate(['name' => 'Staff']);

        // ==========================================
        // 2. AMBIL DATA COMPANY UNTUK DISTRIBUSI
        // ==========================================
        // Ambil Head Office (Pusat)
        $headOffice = Company::where('is_head_office', true)->first();
        $hoId = $headOffice ? $headOffice->id : 1;

        // Ambil semua ID Cabang (yang is_head_office = false)
        $branchIds = Company::where('is_head_office', false)->pluck('id')->toArray();

        // Siapkan ID Cabang (Fallback ke HO jika cabang belum ada)
        $branch1 = !empty($branchIds) ? $branchIds[0] : $hoId; // Ambil cabang urutan ke-1
        $branch2 = !empty($branchIds) ? (isset($branchIds[1]) ? $branchIds[1] : $branchIds[0]) : $hoId; // Ambil cabang urutan ke-2

        // ==========================================
        // TEAM KANTOR PUSAT (HEAD OFFICE)
        // ==========================================
        $admin = User::updateOrCreate(
            ['email' => 'admin@app.com'],
            ['name' => 'Super Administrator', 'password' => Hash::make('123'), 'company_id' => $hoId]
        );
        $admin->assignRole('Super Admin');

        $direktur = User::updateOrCreate(
            ['email' => 'direktur@app.com'],
            ['name' => 'Ibu Direktur', 'password' => Hash::make('123'), 'company_id' => $hoId]
        );
        $direktur->assignRole('direktur');

        $finance = User::updateOrCreate(
            ['email' => 'finance@app.com'],
            ['name' => 'Sari (Keuangan)', 'password' => Hash::make('123'), 'company_id' => $hoId]
        );
        $finance->assignRole('Finance');

        $purchasing = User::updateOrCreate(
            ['email' => 'purchasing@app.com'],
            ['name' => 'Dina (Purchasing)', 'password' => Hash::make('123'), 'company_id' => $hoId]
        );
        $purchasing->assignRole('Purchasing');

        // ==========================================
        // TEAM CABANG 1 (Contoh: PT Anak Usaha Satu)
        // ==========================================
        $manager = User::updateOrCreate(
            ['email' => 'manager@app.com'],
            ['name' => 'Pak Manager', 'password' => Hash::make('123'), 'company_id' => $branch1]
        );
        $manager->assignRole('manager');

        $gudang = User::updateOrCreate(
            ['email' => 'gudang@app.com'],
            ['name' => 'Budi (Gudang)', 'password' => Hash::make('123'), 'company_id' => $branch1]
        );
        $gudang->assignRole('Gudang');

        // ==========================================
        // TEAM CABANG 2 (Contoh: Perum Mardhiyah)
        // ==========================================
        $staff = User::updateOrCreate(
            ['email' => 'karyawan@app.com'],
            ['name' => 'Andi (Staff Biasa)', 'password' => Hash::make('123'), 'company_id' => $branch2]
        );
        $staff->assignRole('Staff');
    }
}
