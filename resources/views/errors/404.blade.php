@extends('layouts.app')

@section('content')
<div class="container d-flex align-items-center justify-content-center" style="min-height: 75vh;">
    <div class="px-4 text-center">
        {{-- Ikon / Ilustrasi --}}
        <div class="mb-4">
            <div class="p-4 d-inline-block rounded-circle bg-danger-subtle text-danger">
                <i class="bi bi-search" style="font-size: 5rem; line-height: 1;"></i>
            </div>
        </div>

        {{-- Teks Error --}}
        <h1 class="mb-0 display-1 fw-bolder text-dark" style="letter-spacing: -2px;">404</h1>
        <h3 class="mb-3 tracking-wide text-uppercase fw-bold text-muted fs-5">Halaman Tidak Ditemukan</h3>

        <p class="mb-5 text-secondary fs-6" style="max-width: 500px; margin: 0 auto;">
            Maaf, halaman atau data inventaris yang Anda cari tidak dapat ditemukan. Mungkin data telah dihapus, alamat URL salah ketik, atau Anda tidak memiliki akses ke rute ini.
        </p>

        {{-- Tombol Navigasi --}}
        <div class="gap-3 d-flex justify-content-center">
            <button onclick="history.back()" class="px-4 py-2 shadow-sm btn btn-outline-secondary rounded-pill fw-bold">
                <i class="bi bi-arrow-left me-2"></i> Kembali
            </button>
            <a href="{{ url('/') }}" class="px-4 py-2 shadow-sm btn btn-primary rounded-pill fw-bold">
                <i class="bi bi-house-door me-2"></i> Ke Beranda Utama
            </a>
        </div>
    </div>
</div>
@endsection
