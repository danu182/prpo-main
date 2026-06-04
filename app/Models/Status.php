<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Status extends Model
{


    protected $table='statuses';

    protected $fillable = ['type', 'name', 'slug', 'color', 'sequence'];

    // Scope untuk mempermudah pemanggilan
    public function scopePo($query)
    {
        return $query->where('type', 'PO')->orderBy('sequence');
    }

    public function scopePr($query)
    {
        return $query->where('type', 'PR')->orderBy('sequence');
    }
}
