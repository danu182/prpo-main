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
            ['name' => 'Art. Dir. Prestige', 'code' => 'ARP'],
            ['name' => 'Art. Dir. Destin', 'code' => 'ARD'],
            ['name' => 'Accounting & Finance', 'code' => 'FA'],
            ['name' => 'Bintaro', 'code' => 'BIN'],
            ['name' => 'Daman', 'code' => 'DA'],
            ['name' => 'Destinasian', 'code' => 'DE'],
            ['name' => 'Distribusi', 'code' => 'DI'],
            ['name' => 'Digital Imaging', 'code' => 'DIM'], // Diubah dari DI menjadi DIM agar tidak bentrok
            ['name' => 'Editorial Fashion', 'code' => 'EDF'],
            ['name' => 'Editor videographer', 'code' => 'EDV'],
            ['name' => 'Editorial', 'code' => 'ED'],
            ['name' => 'F&A Sr Manager', 'code' => 'FAM'],
            ['name' => 'Fashion Stylist', 'code' => 'FS'],
            ['name' => 'HR&GA', 'code' => 'HR'],
            ['name' => 'IT', 'code' => 'IT'],
            ['name' => 'Marketing', 'code' => 'MA'],
            ['name' => 'Online Editor DAI', 'code' => 'ONEDAI'],
            ['name' => 'Online Writer DAI', 'code' => 'ONWDAI'],
            ['name' => 'Online Writer', 'code' => 'ONW'],
            ['name' => 'Personal Assistant', 'code' => 'PA'], // Memperbaiki typo Aistant -> Assistant
            ['name' => 'Photographer01', 'code' => 'PH'],     // Memperbaiki typo Photograper -> Photographer
            ['name' => 'Production', 'code' => 'PR'],         // Merapikan kapitalisasi huruf depan
            ['name' => 'Senior Editor', 'code' => 'SE'],
            ['name' => 'Social Media', 'code' => 'SOSMED'],
            ['name' => 'Tax', 'code' => 'TAX'],
            ['name' => 'Videographer', 'code' => 'VI'],
            ['name' => 'Writer', 'code' => 'WR'],
            ['name' => 'SALES', 'code' => 'SLS'],
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
        $destinAsian = Company::where('code', 'DM')->orWhere('name', 'like', '%DAMA%')->first();
        $daCompanyId = $destinAsian ? $destinAsian->id : 1; // Fallback ke ID 1 jika tidak ditemukan

        // =========================================================================
        // 3. GENERATE PENGGUNA (USERS) YANG TERIKAT KE DEPARTEMEN & COMPANY
        // =========================================================================
         $employees = [
            // Format: ['nama_karyawan', 'kode_departemen']
            ['name' => 'Aditya wisnu', 'dept' => 'ARP'],
            ['name' => 'Agus Purnomo', 'dept' => 'ARD'],
            ['name' => 'Angel Tan', 'dept' => 'VI'],
            ['name' => 'Anindya Devi', 'dept' => 'FA'],
            ['name' => 'Anwar', 'dept' => 'BIN'],
            ['name' => 'Arie Kusumastuti', 'dept' => 'DA'],
            ['name' => 'Arie Sales', 'dept' => 'ARP'],
            ['name' => 'Arlen Septiana Adam', 'dept' => 'DE'],
            ['name' => 'Atiet Soeharto', 'dept' => 'DIM'], // Disesuaikan ke DIM
            ['name' => 'Aurora', 'dept' => 'ARD'],
            ['name' => 'Brad Homes', 'dept' => 'EDF'],
            ['name' => 'Budi utomo', 'dept' => 'EDV'],
            ['name' => 'Chriss Hill', 'dept' => 'ED'],
            ['name' => 'Christina Andhika', 'dept' => 'FAM'],
            ['name' => 'Christina Andhika', 'dept' => 'FA'],
            ['name' => 'Contasia Christie A.', 'dept' => 'FS'],
            ['name' => 'Danu Marwanto', 'dept' => 'HR'],
            ['name' => 'Darwin Chang', 'dept' => 'IT'],
            ['name' => 'Dervina', 'dept' => 'MA'],
            ['name' => 'Deswita reghita', 'dept' => 'ONEDAI'],
            ['name' => 'Diar Putra Matahari', 'dept' => 'ONW'],
            ['name' => 'Dominique Anklam', 'dept' => 'PA'],
            ['name' => 'Dwi Hartanto', 'dept' => 'PH'],
            ['name' => 'Elfrida Chintya Puri', 'dept' => 'PR'],
            ['name' => 'Elsabella Sohilait', 'dept' => 'SE'],
            ['name' => 'Elvida Nataya', 'dept' => 'SOSMED'],
            ['name' => 'Eqqi Syahputra', 'dept' => 'TAX'],
            ['name' => 'Fajri Rizqi', 'dept' => 'FAM'],
            ['name' => 'Fataya Niken', 'dept' => 'FS'],
            ['name' => 'Febry Ramadhan', 'dept' => 'HR'],
            ['name' => 'Fidelis Ilham Cesardianto', 'dept' => 'IT'],
            ['name' => 'Gabriel Putra', 'dept' => 'MA'],
            ['name' => 'Geoffrey Mohammad', 'dept' => 'ONEDAI'],
            ['name' => 'Guspriaman', 'dept' => 'ONWDAI'],
            ['name' => 'Harvey Norman (Paolo)', 'dept' => 'ONW'],
            ['name' => 'Imam Subahtiar', 'dept' => 'PA'],
            ['name' => 'Irfana Thahirah Putri', 'dept' => 'PH'],
            ['name' => 'Isabella Hana', 'dept' => 'PR'],
            ['name' => 'James Jia-Chang Louie', 'dept' => 'TAX'],
            ['name' => 'Jessica Esther', 'dept' => 'VI'],
            ['name' => 'Joe Sabarto', 'dept' => 'WR'],
            ['name' => 'Joezer', 'dept' => 'SLS'],
            ['name' => 'Kusdiana', 'dept' => 'ARP'],
            ['name' => 'Liana Phiong', 'dept' => 'ARD'],
            ['name' => 'Muhammad Rafli Hasani', 'dept' => 'FA'],
            ['name' => 'Muhammad Ridwan', 'dept' => 'BIN'],
            ['name' => 'Muklas Adi Saputra', 'dept' => 'DA'],
            ['name' => 'Myranda Fae', 'dept' => 'DE'],
            ['name' => 'Nabila Nadazera', 'dept' => 'DIM'], // Disesuaikan ke DIM
            ['name' => 'Nuridin', 'dept' => 'DIM'],        // Disesuaikan ke DIM
            ['name' => 'Paolo Avis', 'dept' => 'DIM'],      // Disesuaikan ke DIM
            ['name' => 'Purnama Putri', 'dept' => 'EDV'],
            ['name' => 'Putri Lathifah', 'dept' => 'EDF'],
            ['name' => 'Putri Lathifah', 'dept' => 'ED'],
            ['name' => 'Raden Haryo Suryadi', 'dept' => 'FAM'],
            ['name' => 'Radit', 'dept' => 'FS'],
            ['name' => 'Resliana', 'dept' => 'HR'],
            ['name' => 'Riga Adhitya Ramadhan', 'dept' => 'IT'],
            ['name' => 'Rinawati', 'dept' => 'MA'],
            ['name' => 'Rosalie Amy Ramaker', 'dept' => 'ONEDAI'],
            ['name' => 'Salvia Irani', 'dept' => 'ONWDAI'],
            ['name' => 'Sayrifudin', 'dept' => 'ONW'],
            ['name' => 'Shahnaz Tsaniyah', 'dept' => 'PR'],
            ['name' => 'Sudiksha Ajit Lachman', 'dept' => 'SE'],
            ['name' => 'Sunaryo', 'dept' => 'SOSMED'],
            ['name' => 'Vina', 'dept' => 'EDV'],
            ['name' => 'Wahyudi', 'dept' => 'VI'],
            ['name' => 'Yopie', 'dept' => 'WR'],
        ];

        foreach ($employees as $emp) {
            // 1. Membuat email unik dengan kombinasi nama + kode departemen agar tidak saling menimpa
            $cleanName = strtolower(Str::slug($emp['name'], ''));
            $email = $cleanName . ($emp['dept'] ? '.' . strtolower($emp['dept']) : '') . '@app.com';

            // 2. Dapatkan ID Departemen berdasarkan kodenya jika ada di dalam array $departments
            // Menggunakan isset() untuk mencegah error jika ada kode dept yang typo/tidak terdaftar
            $deptId = (isset($emp['dept']) && isset($departments[$emp['dept']]))
                ? $departments[$emp['dept']]->id
                : null;

            // 3. Buat atau Update data user ke database
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name'          => $emp['name'],
                    'password'      => Hash::make('123'), // Password default untuk login massal
                    'company_id'    => $daCompanyId,      // Resmi terikat ke PT DestinAsian
                    'department_id' => $deptId,          // Resmi terikat ke Departemen barunya
                    'job_title'     => $emp['dept'] ? 'Staff ' . $emp['dept'] : 'Staff',
                    'is_active'     => true,
                ]
            );

            // 4. Berikan Role 'Staff' bawaan agar sistem otorisasi Spatie aktif
            if (!$user->hasRole('Staff')) {
                $user->assignRole('Staff');
            }
        }

    }
}
