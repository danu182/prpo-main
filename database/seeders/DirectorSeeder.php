<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DirectorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Pastikan Permission Ada
        // Kita gunakan firstOrCreate agar tidak error jika sudah ada
        $permView    = Permission::firstOrCreate(['name' => 'view pr']);
        $permApprove = Permission::firstOrCreate(['name' => 'approve pr director']);
        $permReject  = Permission::firstOrCreate(['name' => 'reject pr']);

        // 2. Buat Role 'Director'
        $roleDirector = Role::firstOrCreate(['name' => 'Director']);

        // 3. Assign Permission ke Role
        // Director bisa Lihat, Approve (Final), dan Tolak
        $roleDirector->syncPermissions([$permView, $permApprove, $permReject]);

        // 4. Ambil Company Head Office (ID 1 atau sesuai kode HO)
        $ho = Company::where('code', 'HO')->first();

        // Jika company HO belum ada, buat dummy (jaga-jaga error)
        if(!$ho) {
            $ho = Company::create(['code' => 'HO', 'name' => 'Head Office', 'is_head_office' => true]);
        }

        // 5. Buat User Director
        $userDirector = User::firstOrCreate(
            ['email' => 'director@app.com'], // Cek berdasarkan email
            [
                'name' => 'Pak Bos Director',
                'password' => bcrypt('123'), // Password default
                'company_id' => $ho->id,
                'avatar' => null,
                'signature' => null
            ]
        );

        // 6. Assign Role Director ke User tersebut
        $userDirector->assignRole($roleDirector);

        $this->command->info('User Director berhasil dibuat: director@app.com / 123');
    }
}
