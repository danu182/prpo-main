<?php

namespace App\Exports;

use App\Models\User;
use App\Models\Company;
use App\Models\Department;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsersExport implements WithMultipleSheets
{
    protected $isTemplate;

    public function __construct($isTemplate = false)
    {
        $this->isTemplate = $isTemplate;
    }

    public function sheets(): array
    {
        return [
            new UsersDataSheet($this->isTemplate),
            new UsersGuideSheet() // Sheet ke-2 berisi panduan
        ];
    }
}

// ==========================================
// SHEET 1: DATA UTAMA / TEMPLATE
// ==========================================
class UsersDataSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    protected $isTemplate;

    public function __construct($isTemplate) { $this->isTemplate = $isTemplate; }

    public function collection()
    {
        return $this->isTemplate ? collect([]) : User::with(['company', 'department', 'roles'])->get();
    }

    public function headings(): array
    {
        return ['name', 'email', 'password', 'company_id', 'department_id', 'job_title', 'role'];
    }

    public function map($user): array
    {
        if ($this->isTemplate) return [];
        return [
            $user->name, $user->email, '',
            $user->company_id, $user->department_id, $user->job_title,
            $user->roles->pluck('name')->implode(', ')
        ];
    }

    public function title(): string { return 'Data_Karyawan'; }
}

// ==========================================
// SHEET 2: PANDUAN PENGISIAN
// ==========================================
class UsersGuideSheet implements FromCollection, WithHeadings, WithTitle
{
    public function collection()
    {
        $guideData = collect([]);
        $guideData->push(['--- DAFTAR ID PERUSAHAAN (PT) ---', '']);
        foreach (Company::all() as $cmp) { $guideData->push(["ID: {$cmp->id}", $cmp->name]); }

        $guideData->push(['', '']); // Baris kosong

        $guideData->push(['--- DAFTAR ID DEPARTEMEN ---', '']);
        foreach (Department::all() as $dept) { $guideData->push(["ID: {$dept->id}", $dept->name]); }

        return $guideData;
    }

    public function headings(): array { return ['KODE / ID (Masukkan angka ID ke Sheet 1)', 'NAMA (Sebagai Referensi)']; }
    public function title(): string { return 'PANDUAN_PENGISIAN'; }
}
