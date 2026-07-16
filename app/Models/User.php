<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\Department as ModelsDepartment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles; // PENTING: Import Spatie


class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles; // PENTING: Pakai Trait HasRoles

    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id', // Pastikan company_id ada di sini
        'job_title',
        'is_active',
        'department_id',
        'avatar',      // <--- Tambahkan
        'signature',   // <--- Tambahkan
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relasi User ke Company
    public function company()
    {
        return $this->belongsTo(Company::class);
    }


    /**
     * Relasi: Satu User memiliki/berada di satu Departemen (Divisi)
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }


    // 1. Relasi ke Gudang
    public function warehouses()
    {
        return $this->belongsToMany(\App\Models\Warehouse::class, 'user_warehouse');
    }

    // 2. Fungsi Filter Akses Gudang (SUPER HELPER)
    public function getAccessibleWarehouseIds()
    {
        // Daftar Role "Dewa" yang boleh akses SEMUA gudang (Bisa Anda sesuaikan)
        $superRoles = ['Super Admin', 'Finance', 'Manager', 'Direktur', 'Supervisor'];

        if ($this->hasAnyRole($superRoles)) {
            // Jika dia Bos/Finance, berikan SEMUA ID Gudang yang ada di database
            return \App\Models\Warehouse::pluck('id')->toArray();
        }

        // Jika dia Staff biasa / Orang Gudang, berikan HANYA ID Gudang yang di-assign kepadanya
        return $this->warehouses()->pluck('warehouses.id')->toArray();
    }


}
