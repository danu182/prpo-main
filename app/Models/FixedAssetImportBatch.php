<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixedAssetImportBatch extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function details()
    {
        return $this->hasMany(FixedAssetImportDetail::class, 'batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getStatusInfoAttribute()
    {
        $statusMap = [
            'draft'            => ['name' => 'Draft (Karantina)', 'color' => 'secondary'],
            'waiting_approval' => ['name' => 'Menunggu Approval', 'color' => 'warning'],
            'approved'         => ['name' => 'Disetujui', 'color' => 'success'],
            'rejected'         => ['name' => 'Ditolak', 'color' => 'danger'],
        ];

        $status = strtolower($this->status);
        return (object) ($statusMap[$status] ?? ['name' => strtoupper($status), 'color' => 'dark']);
    }
}
