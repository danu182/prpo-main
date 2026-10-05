<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflowStep extends Model
{
    // 🔥 PERBAIKAN: Masukkan target_department_id dan min_amount ke dalam array fillable
    protected $guarded = ['id'];


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

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
