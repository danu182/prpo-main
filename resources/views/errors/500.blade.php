@extends('layouts.app')

@section('content')
<div class="container d-flex align-items-center justify-content-center" style="min-height: 75vh;">
    <div class="px-4 text-center">
        {{-- Ikon / Ilustrasi --}}
        <div class="mb-4">
            <div class="p-4 shadow-sm d-inline-block rounded-circle bg-danger-subtle text-danger">
                <i class="bi bi-exclamation-triangle-fill" style="font-size: 5rem; line-height: 1;"></i>
            </div>
        </div>

        {{-- Teks Error --}}
        <h1 class="mb-0 display-1 fw-bolder text-dark" style="letter-spacing: -2px;">500</h1>
        <h3 class="mb-3 tracking-wide text-uppercase fw-bold text-muted fs-5">Terjadi Kesalahan Sistem</h3>

        <p class="mb-5 text-secondary fs-6" style="max-width: 550px; margin: 0 auto;">
            Mohon maaf, server kami sedang mengalami gangguan internal atau masalah tak terduga. Tim teknis telah mencatat *error* ini dan akan segera melakukan perbaikan. Silakan coba muat ulang beberapa saat lagi.
        </p>

        {{-- Tombol Navigasi --}}
        <div class="gap-3 d-flex justify-content-center">
            <button onclick="window.location.reload()" class="px-4 py-2 shadow-sm btn btn-outline-danger rounded-pill fw-bold">
                <i class="bi bi-arrow-clockwise me-2"></i> Muat Ulang Halaman
            </button>
            <a href="{{ url('/') }}" class="px-4 py-2 shadow-sm btn btn-primary rounded-pill fw-bold">
                <i class="bi bi-house-door me-2"></i> Ke Beranda Utama
            </a>
        </div>
    </div>
</div>
@endsection
