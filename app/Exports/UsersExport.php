<?php

namespace App\Exports;

use App\Models\User;
use App\Models\Company;
use App\Models\Department;
use App\Models\JobTitle;
use Spatie\Permission\Models\Role;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class UsersExport implements WithMultipleSheets
{
    protected bool $isTemplate;

    public function __construct(bool $isTemplate = false)
    {
        $this->isTemplate = $isTemplate;
    }

    public function sheets(): array
    {
        if (!$this->isTemplate) {
            return [
                new class implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize {
                    public function title(): string { return 'Data_Users'; }
                    public function headings(): array {
                        return ['ID', 'Name', 'Email', 'Company', 'Department', 'Job Title', 'Roles', 'Status'];
                    }
                    public function collection() {
                        return User::with(['company', 'department', 'roles'])->orderBy('name')->get()->map(fn($u) => [
                            $u->id,
                            $u->name,
                            $u->email,
                            optional($u->company)->name ?? '-',
                            optional($u->department)->name ?? '-',
                            $u->job_title ?? '-',
                            $u->getRoleNames()->implode(', '),
                            $u->is_active ? 'Aktif' : 'Nonaktif',
                        ]);
                    }
                }
            ];
        }

        return [
            // SHEET 1: FORM INPUT USER (CONTOH MENGGUNAKAN ID JABATAN)
            new class implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize {
                public function title(): string { return 'Input_Users'; }
                public function headings(): array {
                    return ['name', 'email', 'password', 'company_id', 'department_id', 'job_title_id', 'role'];
                }
                public function collection() {
                    $sampleJobId = JobTitle::where('is_active', true)->orderBy('id')->value('id') ?? 1;
                    return collect([
                        ['Joko', 'joko@gmail.com', '123456', 6, 4, $sampleJobId, 'Staff'],
                    ]);
                }
            },

            // SHEET 2: REFERENSI ID COMPANY, DEPARTEMEN, JABATAN & ROLE
            new class implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize {
                public function title(): string { return 'Referensi_ID'; }
                public function headings(): array {
                    return [
                        'COMPANY_ID', 'NAMA_PERUSAHAAN',
                        'DEPARTMENT_ID', 'NAMA_DEPARTEMEN',
                        'JOB_TITLE_ID', 'NAMA_JABATAN',
                        'NAMA_ROLE'
                    ];
                }
                public function collection() {
                    $companies = Company::orderBy('id')->get()->values();
                    $depts     = Department::orderBy('id')->get()->values();
                    $jobs      = JobTitle::where('is_active', true)->orderBy('id')->get()->values();
                    $roles     = Role::orderBy('name')->get()->values();

                    $maxRows = max($companies->count(), $depts->count(), $jobs->count(), $roles->count());
                    $rows = collect();

                    for ($i = 0; $i < $maxRows; $i++) {
                        $rows->push([
                            $companies[$i]->id ?? '',
                            $companies[$i]->name ?? '',
                            $depts[$i]->id ?? '',
                            $depts[$i]->name ?? '',
                            $jobs[$i]->id ?? '',
                            $jobs[$i]->name ?? '',
                            $roles[$i]->name ?? '',
                        ]);
                    }

                    return $rows;
                }
            }
        ];
    }
}
