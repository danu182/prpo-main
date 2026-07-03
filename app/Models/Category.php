<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $guarded = ['id'];


    // Relasi untuk mengambil Kategori Induknya (Parent)
    public function parent() {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Relasi untuk mengambil Sub-Kategori / Tipe di bawahnya (Children)
    public function children() {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
