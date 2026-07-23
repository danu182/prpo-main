@extends('layouts.app')

@section('content')
<div class="container pb-5 text-dark" style="max-width: 900px;">

    {{-- Header --}}
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <h4 class="mb-0 fw-bold"><i class="bi bi-building-check text-primary me-2"></i> Detail Perusahaan</h4>
        <div>
            <a href="{{ route('companies.index') }}" class="px-3 btn btn-outline-secondary rounded-pill me-2">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <a href="{{ route('companies.edit', $company->id) }}" class="px-4 shadow-sm btn btn-warning rounded-pill fw-bold">
                <i class="bi bi-pencil-square me-1"></i> Edit Data
            </a>
        </div>
    </div>

    {{-- Kartu Detail Info --}}
    <div class="overflow-hidden border-0 shadow-sm card rounded-4">
        {{-- Banner Atas --}}
        <div class="p-4 text-white bg-primary d-flex align-items-center" style="background: linear-gradient(135deg, #0d6efd, #0dcaf0);">
            <div class="p-2 bg-white shadow-sm me-4 rounded-4">
                @if($company->logo_path)
                    <img src="{{ asset('storage/' . $company->logo_path) }}" alt="Logo PT" style="width: 80px; height: 80px; object-fit: contain;">
                @else
                    <div class="d-flex justify-content-center align-items-center text-muted" style="width: 80px; height: 80px;">
                        <i class="bi bi-buildings fs-1"></i>
                    </div>
                @endif
            </div>
            <div>
                <h3 class="mb-1 fw-bolder">{{ $company->name }}</h3>
                <div class="opacity-75 d-flex align-items-center">
                    <span class="badge bg-light text-primary me-2 font-monospace fs-6">{{ $company->code }}</span>
                    @if($company->is_head_office)
                        <span class="badge bg-warning text-dark"><i class="bi bi-star-fill me-1"></i> Head Office (Pusat)</span>
                    @else
                        <span class="bg-white badge text-dark"><i class="bi bi-geo-alt-fill me-1"></i> Kantor Cabang</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Body Info --}}
        <div class="p-4 card-body p-md-5">
            <h5 class="pb-2 mb-4 fw-bold border-bottom text-primary"><i class="bi bi-info-circle me-2"></i> Informasi Kontak & Legal</h5>

            <div class="row g-4">
                {{-- Kolom Kiri --}}
                <div class="col-md-6">
                    <div class="mb-3">
                        <div class="mb-1 small text-muted fw-bold text-uppercase">Email Resmi</div>
                        <div class="fs-6 fw-semibold text-dark">
                            <i class="bi bi-envelope-at me-2 text-primary"></i> {{ $company->email ?? 'Tidak ada data email' }}
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="mb-1 small text-muted fw-bold text-uppercase">Nomor Telepon</div>
                        <div class="fs-6 fw-semibold text-dark">
                            <i class="bi bi-telephone me-2 text-primary"></i> {{ $company->phone ?? 'Tidak ada data telepon' }}
                        </div>
                    </div>
                </div>

                {{-- Kolom Kanan --}}
                <div class="col-md-6">
                    <div class="mb-3">
                        <div class="mb-1 small text-muted fw-bold text-uppercase">Nomor NPWP</div>
                        <div class="fs-6 fw-semibold text-dark font-monospace">
                            <i class="bi bi-credit-card-2-front me-2 text-primary"></i> {{ $company->tax_id ?? 'Tidak ada data NPWP' }}
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="mb-1 small text-muted fw-bold text-uppercase">Alamat Lengkap</div>
                        <div class="fs-6 text-dark d-flex align-items-start">
                            <i class="mt-1 bi bi-geo-alt me-2 text-primary"></i>
                            <span>{{ $company->address ?? 'Alamat belum diisi.' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Info Tambahan (Tanggal Dibuat) --}}
            <div class="pt-3 mt-5 border-top text-end small text-muted">
                <i class="bi bi-clock-history me-1"></i> Data ditambahkan pada: {{ $company->created_at->translatedFormat('d F Y - H:i') }}
            </div>
        </div>
    </div>

</div>
@endsection
