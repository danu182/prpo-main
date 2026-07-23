@extends('layouts.app')

@section('content')
<div class="pb-5 container-fluid">

    {{-- HEADER --}}
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-1 fw-bold text-dark"><i class="bi bi-file-earmark-spreadsheet me-2 text-success"></i> Import & Export Pengguna</h3>
            <p class="mb-0 text-muted">Kelola data karyawan secara massal menggunakan file Excel.</p>
        </div>
        <a href="{{ route('users.index') }}" class="px-4 border shadow-sm btn btn-light rounded-pill fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    @if(session('error'))
        <div class="border-0 shadow-sm alert alert-danger rounded-3 fw-bold">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
        </div>
    @endif

    <div class="row g-4">
        {{-- KOTAK KIRI: IMPORT EXCEL --}}
        <div class="col-lg-7">
            <div class="border-0 shadow-sm card rounded-4 h-100">
                <div class="py-3 bg-white card-header border-bottom">
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-upload me-2 text-primary"></i> Upload Data Baru (Import)</h5>
                </div>
                <div class="p-4 card-body">
                    <div class="mb-4 border-0 alert alert-primary bg-primary-subtle rounded-3">
                        <h6 class="mb-2 fw-bold text-primary"><i class="bi bi-info-circle-fill me-2"></i>Petunjuk Import:</h6>
                        <ul class="mb-0 small text-dark">
                            <li>Gunakan format file dari <strong>Template Excel</strong> yang kami sediakan.</li>
                            <li>Kolom <strong>Email</strong> wajib unik (belum pernah terdaftar di sistem).</li>
                            <li>Format Company ID dan Dept ID gunakan angka (Lihat daftar ID di sheet bantuan).</li>
                            <li>Jika kolom Password kosong, sistem akan menggunakan <strong>123456</strong>.</li>
                        </ul>
                    </div>

                    <form action="{{ route('users.preview_import') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark">Pilih File Excel (.xlsx / .csv) <span class="text-danger">*</span></label>
                            <input type="file" name="file_excel" class="form-control form-control-lg bg-light" accept=".xlsx, .xls, .csv" required>
                        </div>
                        <button type="submit" class="px-5 shadow-sm btn btn-primary btn-lg rounded-pill fw-bold w-100">
                            <i class="bi bi-cloud-arrow-up-fill me-2"></i> Mulai Proses Import
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- KOTAK KANAN: DOWNLOAD & EXPORT --}}
        <div class="col-lg-5">
            <div class="border-0 shadow-sm card rounded-4 h-100">
                <div class="py-3 bg-white card-header border-bottom">
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-download me-2 text-success"></i> Download Data</h5>
                </div>
                <div class="gap-3 p-4 card-body d-flex flex-column">

                    {{-- Download Template --}}
                    <div class="p-3 text-center border rounded-3 bg-light">
                        <i class="mb-2 bi bi-file-earmark-spreadsheet text-success fs-1 d-block"></i>
                        <h6 class="fw-bold text-dark">Template Kosong</h6>
                        <p class="mb-3 small text-muted">Download format baku Excel untuk pengisian data karyawan baru.</p>
                        <a href="{{ route('users.template') }}" class="btn btn-outline-success rounded-pill fw-bold w-100">
                            <i class="bi bi-cloud-download me-1"></i> Download Template
                        </a>
                    </div>

                    {{-- Export Data Exist --}}
                    <div class="p-3 mt-auto text-center border rounded-3 bg-light">
                        <i class="mb-2 bi bi-database-down text-primary fs-1 d-block"></i>
                        <h6 class="fw-bold text-dark">Backup Data Karyawan</h6>
                        <p class="mb-3 small text-muted">Tarik seluruh data pengguna yang ada di sistem saat ini ke dalam Excel.</p>
                        <a href="{{ route('users.export') }}" class="shadow-sm btn btn-primary rounded-pill fw-bold w-100">
                            <i class="bi bi-file-earmark-excel-fill me-1"></i> Export Data Lengkap
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>
@endsection
