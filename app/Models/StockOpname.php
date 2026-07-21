<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockOpname extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'document_number', 'company_id', 'warehouse_id', 'status_id',
        'start_date', 'end_date', 'notes', 'total_system_value',
        'total_actual_value', 'total_variance_value', 'created_by',
        'approved_by', 'approved_at'
    ];

    public function company() { return $this->belongsTo(Company::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function status() { return $this->belongsTo(Status::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function items() { return $this->hasMany(StockOpnameItem::class); }
    public function attachments() { return $this->hasMany(StockOpnameAttachment::class); }

    // Relasi untuk Workflow Matriks (Sama seperti PO/PR)
    public function approvals() { return $this->morphMany(DocumentApproval::class, 'document'); }
}
