<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    // Sesuai instruksi Komandan, kita buka gerbang Mass Assignment-nya
    protected $guarded = ['id'];

    /**
     * Relasi: Satu Departemen memiliki banyak Karyawan (Users)
     */
    public function users()
    {
        return $this->hasMany(User::class, 'department_id');
    }

    /**
     * Relasi: Satu Departemen memiliki banyak Aset (Fixed Assets)
     */
    public function fixedAssets()
    {
        return $this->hasMany(FixedAsset::class, 'department_id');
    }

    /**
     * Relasi: Satu Departemen memiliki banyak Riwayat Import Aset (Karantina)
     */
    public function importDetails()
    {
        return $this->hasMany(FixedAssetImportDetail::class, 'department_id');
    }
}
