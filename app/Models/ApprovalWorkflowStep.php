<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class ApprovalWorkflowStep extends Model
{
    protected $fillable = ['approval_workflow_id', 'step_order', 'role_id'];

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflow::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
