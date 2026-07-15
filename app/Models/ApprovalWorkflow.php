<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflow extends Model
{
    // Tambahkan department_id ke dalam array fillable
    protected $fillable = ['document_type', 'department_id', 'name', 'is_active'];

    public function steps()
    {
        return $this->hasMany(ApprovalWorkflowStep::class)->orderBy('step_order', 'asc');
    }

    // Relasi untuk mengenali ini matriks milik departemen mana
    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
