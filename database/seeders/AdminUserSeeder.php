<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Bersihkan cache Spatie agar role terbaca fresh
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

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

        // 🔥 ROLE BARU: PEMBUAT PR & SUPERVISOR 🔥
        Role::firstOrCreate(['name' => 'Staff PR']);
        Role::firstOrCreate(['name' => 'Supervisor']);

        // ==========================================
        // 2. AMBIL DATA COMPANY & DEPARTMENT
        // ==========================================
        $headOffice = Company::where('is_head_office', true)->first();
        $hoId = $headOffice ? $headOffice->id : 1;

        $branchIds = Company::where('is_head_office', false)->pluck('id')->toArray();
        $branch1 = $branchIds[0] ?? $hoId;
        $branch2 = $branchIds[1] ?? $hoId;

        $deptGA = Department::where('code', 'GA')->first();
        $deptIT = Department::where('code', 'IT')->first();

        // ==========================================
        // 3. TEAM KANTOR PUSAT (HEAD OFFICE)
        // ==========================================
        $admin = User::updateOrCreate(
            ['email' => 'admin@app.com'],
            [
                'name'          => 'Super Administrator',
                'password'      => Hash::make('123'),
                'company_id'    => $hoId,
                'department_id' => $deptIT ? $deptIT->id : null,
                'is_active'     => 1,
            ]
        );
        $admin->assignRole('Super Admin');

        $direktur = User::updateOrCreate(
            ['email' => 'direktur@app.com'],
            [
                'name'          => 'Ibu Direktur',
                'password'      => Hash::make('123'),
                'company_id'    => $hoId,
                'department_id' => null,
                'is_active'     => 1,
            ]
        );
        $direktur->assignRole('direktur');

        $finance = User::updateOrCreate(
            ['email' => 'finance@app.com'],
            [
                'name'          => 'Sari (Keuangan)',
                'password'      => Hash::make('123'),
                'company_id'    => $hoId,
                'department_id' => $deptGA ? $deptGA->id : null,
                'is_active'     => 1,
            ]
        );
        $finance->assignRole('Finance');

        $purchasing = User::updateOrCreate(
            ['email' => 'purchasing@app.com'],
            [
                'name'          => 'Dina (Purchasing)',
                'password'      => Hash::make('123'),
                'company_id'    => $hoId,
                'department_id' => $deptGA ? $deptGA->id : null,
                'is_active'     => 1,
            ]
        );
        $purchasing->assignRole('Purchasing');

        // 🔥 USER KHUSUS PEMBUAT PR 🔥
        $stafPR = User::updateOrCreate(
            ['email' => 'staf_pr@app.com'],
            [
                'name'          => 'Joko (Khusus Buat PR)',
                'password'      => Hash::make('123'),
                'company_id'    => $hoId,
                'department_id' => null,
                'job_title'     => 'Staff Requester',
                'is_active'     => 1,
            ]
        );
        $stafPR->assignRole('Staff PR');

        // 🔥 USER KHUSUS SUPERVISOR (ATASAN PR LAPIS 1) 🔥
        $supervisor = User::updateOrCreate(
            ['email' => 'supervisor@app.com'],
            [
                'name'          => 'Pak Supervisor (Atasan PR)',
                'password'      => Hash::make('123'),
                'company_id'    => $hoId,
                'department_id' => null,
                'job_title'     => 'Supervisor Operasional',
                'is_active'     => 1,
            ]
        );
        $supervisor->assignRole('Supervisor');

        // ==========================================
        // 4. TEAM CABANG 1 (Hitawasana)
        // ==========================================
        $manager = User::updateOrCreate(
            ['email' => 'manager@app.com'],
            [
                'name'          => 'Pak Manager',
                'password'      => Hash::make('123'),
                'company_id'    => $branch1,
                'department_id' => null,
                'is_active'     => 1,
            ]
        );
        $manager->assignRole('manager');

        $gudang = User::updateOrCreate(
            ['email' => 'gudang@app.com'],
            [
                'name'          => 'Budi (Gudang)',
                'password'      => Hash::make('123'),
                'company_id'    => $branch1,
                'department_id' => $deptGA ? $deptGA->id : null,
                'is_active'     => 1,
            ]
        );
        $gudang->assignRole('Gudang');

        // ==========================================
        // 5. TEAM CABANG 2 (DestinAsian)
        // ==========================================
        $staff = User::updateOrCreate(
            ['email' => 'karyawan@app.com'],
            [
                'name'          => 'Andi (Staff Biasa)',
                'password'      => Hash::make('123'),
                'company_id'    => $branch2,
                'department_id' => null,
                'is_active'     => 1,
            ]
        );
        $staff->assignRole('Staff');
    }
}
