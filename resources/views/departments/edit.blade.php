@extends('layouts.app')

@section('content')
<div class="pb-5 container-fluid">

    {{-- HEADER KEMBALI --}}
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1 fw-bold text-dark"><i class="bi bi-diagram-3-fill me-2 text-primary"></i> Detail Departemen</h3>
            <nav aria-label="breadcrumb">
                <ol class="mb-0 breadcrumb small">
                    <li class="breadcrumb-item"><a href="{{ route('departments.index') }}" class="text-decoration-none">Master Departemen</a></li>
                    <li class="breadcrumb-item active fw-bold" aria-current="page">{{ $department->code }}</li>
                </ol>
            </nav>
        </div>
        <div class="gap-2 d-flex">
            <a href="{{ route('departments.index') }}" class="px-4 border shadow-sm btn btn-light rounded-pill">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <a href="{{ route('departments.edit', $department->id) }}" class="px-4 shadow-sm btn btn-warning fw-bold text-dark rounded-pill">
                <i class="bi bi-pencil-square me-1"></i> Edit
            </a>
        </div>
    </div>

    <div class="row g-4">
        {{-- KARTU INFORMASI DEPARTEMEN --}}
        <div class="col-xl-4 col-lg-5">
            <div class="border-0 shadow-sm card rounded-4 h-100">
                <div class="py-3 bg-white card-header border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-info-circle me-2 text-primary"></i>Informasi Divisi</h6>
                </div>
                <div class="p-4 card-body">
                    <table class="table mb-0 table-borderless table-sm">
                        <tr>
                            <td class="text-muted small ps-0" width="40%">Kode Departemen</td>
                            <td class="pe-0">
                                <span class="px-2 py-1 border shadow-sm badge bg-light text-dark font-monospace">{{ $department->code }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small ps-0">Nama Departemen</td>
                            <td class="fw-bold text-dark pe-0">{{ $department->name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small ps-0">Status Aktif</td>
                            <td class="pe-0">
                                @if($department->is_active)
                                    <span class="px-3 border badge bg-success-subtle text-success border-success-subtle rounded-pill">Aktif</span>
                                @else
                                    <span class="px-3 border badge bg-danger-subtle text-danger border-danger-subtle rounded-pill">Non-Aktif</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small ps-0">Total Karyawan</td>
                            <td class="fw-bold text-primary pe-0">{{ $department->users->count() }} Orang</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- DAFTAR KARYAWAN DI DEPARTEMEN INI --}}
        <div class="col-xl-8 col-lg-7">
            <div class="border-0 shadow-sm card rounded-4 h-100">
                <div class="py-3 bg-white card-header border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-people me-2 text-primary"></i>Daftar Karyawan</h6>
                </div>
                <div class="p-0 card-body">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle table-hover">
                            <thead class="table-light text-muted small text-uppercase fw-bold border-bottom">
                                <tr>
                                    <th class="py-3 ps-4" width="5%">No</th>
                                    <th>Nama Karyawan</th>
                                    <th>Email</th>
                                    <th>Role / Akses</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($department->users as $idx => $user)
                                    <tr>
                                        <td class="ps-4 text-muted fw-bold">{{ $idx + 1 }}</td>
                                        <td class="fw-bold text-dark">{{ $user->name }}</td>
                                        <td class="text-muted">{{ $user->email }}</td>
                                        <td>
                                            <span class="px-2 py-1 border badge bg-primary-subtle text-primary border-primary-subtle">
                                                {{ $user->roles->pluck('name')->first() ?? 'Staff' }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-5 text-center text-muted">
                                            <i class="mb-2 opacity-25 bi bi-person-x fs-1 d-block"></i>
                                            Belum ada karyawan di departemen ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
