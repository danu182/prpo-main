<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DepartmentAndUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 0. Bersihkan cache Spatie Permission agar aman saat penetapan Role
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Pastikan Role 'Staff' sudah ada di database (Spatie Permission)
        Role::firstOrCreate(['name' => 'Staff']);

        // =========================================================================
        // 1. GENERATE DATA MASTER DEPARTEMEN DARI EXCEL
        // =========================================================================
        $deptData = [
            ['name' => 'Art & Design (AD)',         'code' => 'ART'],
            ['name' => 'Editorial',                 'code' => 'EDT'],
            ['name' => 'Information Technology (IT)', 'code' => 'IT'],
            ['name' => 'General Affairs (GA)',       'code' => 'GA'],
        ];

        $departments = [];
        foreach ($deptData as $d) {
            $departments[$d['code']] = Department::firstOrCreate(
                ['code' => $d['code']],
                ['name' => $d['name'], 'is_active' => true]
            );
        }

        // =========================================================================
        // 2. AMBIL DATA PERUSAHAAN / COMPANY RELEVAN (DestinAsian)
        // =========================================================================
        // Sistem mencari PT DestinAsian yang kodenya 'DES' dari CompanySeeder Anda
        $destinAsian = Company::where('code', 'DES')->orWhere('name', 'like', '%DestinAsian%')->first();
        $daCompanyId = $destinAsian ? $destinAsian->id : 1; // Fallback ke ID 1 jika tidak ditemukan

        // =========================================================================
        // 3. GENERATE PENGGUNA (USERS) YANG TERIKAT KE DEPARTEMEN & COMPANY
        // =========================================================================
        $employees = [
            // Format: ['nama_karyawan', 'kode_departemen']
            ['name' => 'Teguh',   'dept' => 'ART'],
            ['name' => 'Fajrin',  'dept' => 'ART'],
            ['name' => 'Irfan',   'dept' => null], // Sesuai Excel: Hanya tertulis DA (DestinAsian) umum
            ['name' => 'Yoga',    'dept' => 'EDT'],
            ['name' => 'Paul',    'dept' => null], // Hanya tertulis DA
            ['name' => 'Alda',    'dept' => 'ART'],
            ['name' => 'Nico',    'dept' => 'ART'],
            ['name' => 'Kemas',   'dept' => null], // Hanya tertulis DA
            ['name' => 'Lutfi',   'dept' => 'IT'],
            ['name' => 'Baskoro', 'dept' => 'IT'],
            ['name' => 'Asep',    'dept' => 'IT'],
            ['name' => 'Yosi',    'dept' => null], // Hanya tertulis DA
            ['name' => 'Sisca',   'dept' => null], // Hanya tertulis DA
            ['name' => 'Edo',     'dept' => 'IT'],
            ['name' => 'Yuda',    'dept' => 'IT'],
            ['name' => 'Zikri',   'dept' => 'IT'],
            ['name' => 'Ardi',    'dept' => 'IT'],
            ['name' => 'Sugi',    'dept' => 'IT'],
            ['name' => 'Candra',  'dept' => 'IT'],
            ['name' => 'Susi',    'dept' => 'IT'],
        ];

        foreach ($employees as $emp) {
            // Membuat email otomatis dari nama tanpa spasi (Cth: teguh@app.com, artandidesign@app.com)
            $cleanName = strtolower(Str::slug($emp['name'], ''));
            $email = $cleanName . '@app.com';

            // Dapatkan ID Departemen berdasarkan kodenya jika ada
            $deptId = $emp['dept'] ? $departments[$emp['dept']]->id : null;

            // Buat atau Update data user ke database
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name'          => $emp['name'],
                    'password'      => Hash::make('password123'), // Password default untuk login massal
                    'company_id'    => $daCompanyId,              // Resmi terikat ke PT DestinAsian
                    'department_id' => $deptId,                  // Resmi terikat ke Departemen barunya!
                    'job_title'     => $emp['dept'] ? 'Staff ' . $emp['dept'] : 'Staff',
                    'is_active'     => true,
                ]
            );

            // Berikan Role 'Staff' bawaan agar sistem otorisasi Spatie aktif
            if (!$user->hasRole('Staff')) {
                $user->assignRole('Staff');
            }
        }
    }
}
