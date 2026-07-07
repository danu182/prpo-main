<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/PurchaseRequest.php
class PurchaseRequest extends Model
{
    protected $guarded = ['id'];

    public function items()
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // public function histories()
    // {
    //     return $this->hasMany(PurchaseRequestHistory::class)->latest();
    // }


    // App\Models\PurchaseRequest.php
    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }


    // 2. Helper untuk Cek Status (Opsional, biar kodingan rapi)
    public function isPending()
    {
        // Cek apakah relasi status ada DAN slug-nya 'pending_approval'
        return $this->status && $this->status->slug === 'pending_approval';
    }

    public function isApproved()
    {
        return $this->status && $this->status->slug === 'approved';
    }


    // Relasi ke Purchase Order (1 PR bisa punya banyak PO)
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'purchase_request_id');
    }


     /**
     * MENDAPATKAN USER MANAGER YANG MELAKUKAN APPROVAL
     */
    public function getManagerApprover()
    {
        // Cari di history siapa yang action-nya 'Disetujui Manager'
        $history = $this->histories->where('action', 'Disetujui Manager')->last();
        return $history ? $history->user : null;
    }

    /**
     * MENDAPATKAN USER DIREKTUR YANG MELAKUKAN APPROVAL FINAL
     */
    public function getDirectorApprover()
    {
        // Cari di history siapa yang action-nya 'Disetujui Direktur (Final)'
        $history = $this->histories->where('action', 'Disetujui Direktur (Final)')->last();
        return $history ? $history->user : null;
    }


    // 🔥 INI PENYEBAB UTAMA DATA KOSONG!
    // Kita harus kasih tahu Laravel bahwa kolomnya bernama 'pr_item_id'
    public function vendorQuotes()
    {
        // Ganti VendorQuote::class dengan nama Model vendor Komandan
        // (misal: PurchaseRequestItemVendor::class jika namanya itu)
        return $this->hasMany(VendorQuote::class, 'pr_item_id', 'id');
    }

    // Tambahkan kodingan ini di dalam class PurchaseRequest

    public function histories()
    {
        // Menyambungkan PR dengan tabel Riwayat (History)
        return $this->hasMany(\App\Models\PurchaseRequestHistory::class, 'purchase_request_id', 'id');
    }


    /**
     * Relasi ke matriks persetujuan (DocumentApproval)
     */
    public function approvals()
    {
        return $this->morphMany(\App\Models\DocumentApproval::class, 'document');
    }


    /**
     * Relasi ke pembuat PR (User / Requester)
     */
    public function requester()
    {
        // Menghubungkan user_id ke tabel users
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }


    public function department()
    {
        return $this->belongsTo(\App\Models\Department::class, 'department_id');
    }





}


