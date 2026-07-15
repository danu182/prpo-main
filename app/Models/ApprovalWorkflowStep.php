<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflowStep extends Model
{
    // 🔥 PERBAIKAN: Masukkan target_department_id dan min_amount ke dalam array fillable
    protected $fillable = [
        'approval_workflow_id',
        'step_order',
        'role_id',
        'target_department_id',
        'min_amount'
    ];

    public function role()
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class);
    }

    // Relasi tambahan agar bisa memanggil nama departemen target dengan mudah
    public function targetDepartment()
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }
}
