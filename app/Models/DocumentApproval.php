<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class DocumentApproval extends Model
{
    protected $guarded = [];

    // Relasi ke dokumen asli (PurchaseRequest atau PurchaseOrder)
    public function document()
    {
        return $this->morphTo();
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
