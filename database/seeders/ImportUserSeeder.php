<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class ImportUserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pastikan Role Staff ada
        Role::firstOrCreate(['name' => 'Staff']);

        // 2. Lokasi file CSV Anda
        $csvFile = database_path('seeders/users.csv');

        if (!file_exists($csvFile)) {
            $this->command->error("🚨 GAGAL: File tidak ditemukan di database/seeders/users.csv");
            return;
        }

        $file = fopen($csvFile, 'r');
        $isHeader = true;
        $count = 0;

        $this->command->info("🔄 Memulai sedot data user dengan Smart CSV Detector...");

        while (($line = fgets($file)) !== false) {
            $line = trim($line);
            if (empty($line)) continue;

            if ($isHeader) {
                $isHeader = false;
                continue;
            }

            // SMART DETECTOR: Deteksi otomatis pakai Koma atau Titik Koma
            if (strpos($line, ';') !== false) {
                $data = str_getcsv($line, ';');
            } else {
                $data = str_getcsv($line, ',');
            }

            $nama     = isset($data[0]) ? trim($data[0]) : null;
            $deptName = isset($data[1]) ? trim($data[1]) : null;
            $compName = isset($data[2]) ? trim($data[2]) : null;

            if (empty($nama)) continue;

            // A. Cari atau Buat Departemen Otomatis (ANTI-BENTROK)
            $deptId = null;
            if (!empty($deptName) && $deptName !== '-') {
                // Cari apakah departemen ini sudah ada berdasarkan NAMA-nya
                $dept = Department::where('name', $deptName)->first();

                // Jika belum ada, buat baru dengan kode yang dijamin unik
                if (!$dept) {
                    $baseCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $deptName), 0, 3));
                    $code = $baseCode;
                    $counter = 1;

                    // Looping cek ke database, jika kode sudah dipakai, tambahkan angka (SAL1, SAL2)
                    while (Department::where('code', $code)->exists()) {
                        $code = $baseCode . $counter;
                        $counter++;
                    }

                    $dept = Department::create([
                        'name' => $deptName,
                        'code' => $code,
                        'is_active' => true
                    ]);
                }
                $deptId = $dept->id;
            }

            // B. Cari Perusahaan (PT)
            $companyId = 1;
            if (!empty($compName) && $compName !== '-') {
                $company = Company::where('name', 'like', "%{$compName}%")
                                  ->orWhere('code', 'like', "%{$compName}%")
                                  ->first();
                if ($company) {
                    $companyId = $company->id;
                }
            }

            // C. Buat Email Unik
            $email = strtolower(Str::slug($nama, '.')) . rand(10, 99) . '@app.com';

            // D. Masukkan ke Database Users
            $user = User::firstOrCreate(
                ['name' => $nama],
                [
                    'email'         => $email,
                    'password'      => Hash::make('123'),
                    'company_id'    => $companyId,
                    'department_id' => $deptId,
                    'job_title'     => $deptName ? 'Staff ' . $deptName : 'Staff',
                    'is_active'     => 1,
                ]
            );

            // Beri akses Staff
            if (!$user->hasRole('Staff')) {
                $user->assignRole('Staff');
            }

            $count++;
        }

        fclose($file);
        $this->command->info("✅ MANTAP! {$count} User berhasil disedot dan dipisah dengan rapi tanpa bentrok!");
    }
}
