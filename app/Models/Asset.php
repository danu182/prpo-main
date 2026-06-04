<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $guarded = ['id'];

    // Item masternya apa
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // User yang saat ini memegang aset (Current Holder)
    public function currentUser()
    {
        return $this->belongsTo(User::class, 'current_user_id');
    }

    // LIST HISTORY MUTASI
    // Relasi ini dipakai untuk menampilkan tabel history di halaman detail aset
    public function mutations()
    {
        return $this->hasMany(AssetMutation::class)->latest('mutation_date');
    }
}
