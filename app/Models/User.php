<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
}
