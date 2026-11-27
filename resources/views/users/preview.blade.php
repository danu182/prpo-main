@extends('layouts.app')

@section('content')
<div class="container-fluid pb-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="bi bi-eye-fill me-2 text-primary"></i> Preview & Validasi Data</h3>
            <p class="text-muted mb-0">Sistem otomatis mendeteksi kesalahan. Hanya baris <span class="text-success fw-bold">Aman</span> yang akan disimpan.</p>
        </div>
    </div>

    @if($hasError)
        <div class="alert alert-warning border-0 shadow-sm rounded-4 mb-4 fw-bold">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> Ditemukan baris data yang bermasalah. Anda tetap bisa menekan "Simpan", namun data yang bermasalah (merah) akan diabaikan.
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                <table class="table mb-0 align-middle table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-4">No</th>
                            <th>Nama Lengkap</th>
                            <th>Email</th>
                            <th>Password</th>
                            <th>Perusahaan (PT)</th>
                            <th>Departemen</th>
                            <th>Jabatan</th>
                            <th>Role</th>
                            <th>Status & Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($previewData as $index => $row)
                            @php
                                $isValid = empty($row['errors']);
                            @endphp
                            <tr class="{{ $isValid ? '' : 'table-danger' }}">
                                <td class="ps-4 fw-bold text-secondary">{{ $index + 1 }}</td>
                                <td class="fw-bold text-dark">{{ $row['name'] ?: '-' }}</td>
                                <td>{{ $row['email'] ?: '-' }}</td>
                                <td class="text-muted fst-italic">{{ !empty($row['password']) ? $row['password'] : '123456' }}</td>

                                {{-- ID + NAMA PERUSAHAAN --}}
                                <td>
                                    @if(!empty($row['company_id']))
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1">ID: {{ $row['company_id'] }}</span>
                                        <span class="fw-semibold text-dark">{{ $row['company_name'] ?? '-' }}</span>
                                    @else
                                        <span class="text-muted fst-italic">Pusat (Tanpa PT)</span>
                                    @endif
                                </td>

                                {{-- ID + NAMA DEPARTEMEN --}}
                                <td>
                                    @if(!empty($row['department_id']))
                                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle me-1">ID: {{ $row['department_id'] }}</span>
                                        <span class="fw-semibold text-dark">{{ $row['dept_name'] ?? '-' }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>

                                {{-- JABATAN HASIL KONVERSI --}}
                                <td>
                                    @php
                                        $rawJobInput = $row['job_title_id'] ?? $row['job_title'] ?? '';
                                    @endphp
                                    @if(is_numeric($rawJobInput))
                                        <span class="badge bg-warning-subtle text-dark border border-warning-subtle me-1">ID: {{ $rawJobInput }}</span>
                                    @endif
                                    <span class="fw-semibold text-dark">{{ $row['job_title'] ?? 'Staff' }}</span>
                                </td>

                                {{-- ROLE --}}
                                <td>
                                    <span class="badge bg-secondary">{{ $row['role'] ?? 'Staff' }}</span>
                                </td>

                                {{-- STATUS VALIDASI --}}
                                <td>
                                    @if($isValid)
                                        <span class="badge bg-success-subtle text-success border border-success px-3 py-1 rounded-pill">
                                            <i class="bi bi-check-circle me-1"></i> Siap Simpan
                                        </span>
                                    @else
                                        <div class="text-danger small fw-bold">
                                            <ul class="mb-0 ps-3">
                                                @foreach($row['errors'] as $err)
                                                    <li>{{ $err }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-light p-4 d-flex justify-content-between align-items-center border-top">
            <div class="small fw-bold text-muted">
                Total Data: <span class="text-primary fs-6">{{ count($previewData) }} Baris</span>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('users.import_form') }}" class="btn btn-outline-danger fw-bold rounded-pill px-4 shadow-sm">
                    <i class="bi bi-arrow-left me-1"></i> Batal / Upload Ulang
                </a>
                @if(count($previewData) > 0)
                <form action="{{ route('users.process_import') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                        <i class="bi bi-save me-1"></i> Simpan Data yang Aman
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
