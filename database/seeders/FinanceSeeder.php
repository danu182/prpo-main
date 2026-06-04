<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class FinanceSeeder extends Seeder
{
    public function run()
    {
        // 1. Reset Cache Permission (Penting agar tidak error)
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Daftar Permission Khusus Finance
        // Kita buat permission granular agar fleksibel
        $permissions = [
            'menu_finance',       // Akses melihat menu di sidebar
            'view_payments',      // Melihat daftar hutang
            'process_payments',   // Melakukan pembayaran (input termin)
            'view_bills',         // Melihat detail tagihan (read only)
            'download_reports',   // Download rekap (opsional)
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 3. Buat Role "Finance" & Sync Permission
        $financeRole = Role::firstOrCreate(['name' => 'Finance']);

        // Berikan semua permission di atas ke role Finance
        $financeRole->givePermissionTo($permissions);

        // 4. Buat User Spesifik (Staff Finance)
        $financeUser = User::firstOrCreate(
            ['email' => 'finance@app.com'], // Cek email agar tidak duplikat
            [
                'name' => 'Wati Finance',
                'password' => Hash::make('123'), // Password default
                'email_verified_at' => now(),
            ]
        );

        // 5. Assign Role ke User
        $financeUser->assignRole($financeRole);

        $this->command->info('User Finance berhasil dibuat!');
        $this->command->info('Email: finance@kantor.com');
        $this->command->info('Pass: password123');
    }
}
