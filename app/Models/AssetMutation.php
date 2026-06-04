<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetMutation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Aset mana yang dimutasi
     */
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * User Asal (Pengirim/Pemilik Lama)
     */
    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * User Tujuan (Penerima/Pemilik Baru)
     */
    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * Siapa yang melakukan approval mutasi ini
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
