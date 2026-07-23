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
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead class="table-dark sticky-top">
                        <tr>
                            <th class="ps-4">No</th>
                            <th>Nama Lengkap</th>
                            <th>Email</th>
                            <th>Password</th>
                            <th>PT ID</th>
                            <th>Dept ID</th>
                            <th>Role</th>
                            <th class="pe-4">Status & Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($previewData as $idx => $row)
                            <tr class="{{ count($row['errors']) > 0 ? 'table-danger' : '' }}">
                                <td class="ps-4 fw-bold text-muted">{{ $idx + 1 }}</td>
                                <td class="fw-bold text-dark">{{ $row['name'] ?: '-' }}</td>
                                <td>{{ $row['email'] ?: '-' }}</td>
                                <td class="text-muted fst-italic">{{ $row['password'] ?: '123456 (Default)' }}</td>
                                <td>{{ $row['company_id'] ?: '-' }}</td>
                                <td>{{ $row['department_id'] ?: '-' }}</td>
                                <td><span class="badge bg-secondary rounded-pill">{{ $row['role'] ?: 'Staff' }}</span></td>
                                <td class="pe-4">
                                    @if(count($row['errors']) == 0)
                                        <span class="badge bg-success-subtle text-success border-success-subtle border"><i class="bi bi-check-circle me-1"></i> Siap Simpan</span>
                                    @else
                                        <ul class="mb-0 text-danger fw-bold small ps-3" style="list-style-type: square;">
                                            @foreach($row['errors'] as $err)
                                                <li>{{ $err }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">Tidak ada data valid yang bisa dibaca.</td>
                            </tr>
                        @endforelse
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
