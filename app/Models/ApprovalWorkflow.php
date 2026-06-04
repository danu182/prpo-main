<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflow extends Model
{
    protected $fillable = ['document_type', 'name', 'is_active'];

    public function steps()
    {
        return $this->hasMany(ApprovalWorkflowStep::class)->orderBy('step_order', 'asc');
    }
}
