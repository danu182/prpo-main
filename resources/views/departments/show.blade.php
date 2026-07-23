@extends('layouts.app')

@section('content')
<div class="container-fluid pb-5">

    {{-- HEADER KEMBALI --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-diagram-3-fill me-2 text-primary"></i> Detail Departemen</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('departments.index') }}" class="text-decoration-none">Master Departemen</a></li>
                    <li class="breadcrumb-item active fw-bold" aria-current="page">{{ $department->code }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('departments.index') }}" class="btn btn-light border rounded-pill px-4 shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <a href="{{ route('departments.edit', $department->id) }}" class="btn btn-warning fw-bold text-dark rounded-pill px-4 shadow-sm">
                <i class="bi bi-pencil-square me-1"></i> Edit
            </a>
        </div>
    </div>

    <div class="row g-4">
        {{-- KARTU INFORMASI DEPARTEMEN --}}
        <div class="col-xl-4 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-info-circle me-2 text-primary"></i>Informasi Divisi</h6>
                </div>
                <div class="card-body p-4">
                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <td class="text-muted small ps-0" width="40%">Kode Departemen</td>
                            <td class="pe-0">
                                <span class="badge border bg-light text-dark font-monospace px-2 py-1 shadow-sm">{{ $department->code }}</span>
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
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3">Aktif</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3">Non-Aktif</span>
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
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-people me-2 text-primary"></i>Daftar Karyawan</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase fw-bold border-bottom">
                                <tr>
                                    <th class="ps-4 py-3" width="5%">No</th>
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
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                                {{ $user->roles->pluck('name')->first() ?? 'Staff' }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted">
                                            <i class="bi bi-person-x fs-1 d-block mb-2 opacity-25"></i>
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
