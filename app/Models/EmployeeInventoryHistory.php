<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeInventoryHistory extends Model
{
    protected $fillable = [
        'employee_name',
        'item_id',
        'type',
        'qty',
        'reference_number',
        'notes'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
