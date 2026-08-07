@extends('layouts.app')

@section('content')
<div class="container-fluid pb-5 text-dark" style="max-width: 800px;">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <h4 class="mb-0 fw-bold"><i class="bi bi-info-circle text-primary me-2"></i> Detail Gudang</h4>
        <div style="position: relative; z-index: 999;">
            <a href="{{ route('warehouses.index') }}" class="px-3 btn btn-outline-secondary rounded-pill me-2">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <a href="{{ route('warehouses.edit', $warehouse->id) }}" class="px-4 shadow-sm btn btn-warning rounded-pill fw-bold">
                <i class="bi bi-pencil-square me-1"></i> Edit Data
            </a>
        </div>
    </div>

    <div class="overflow-hidden border-0 shadow-sm card rounded-4">
        <div class="p-4 text-white bg-primary" style="background: linear-gradient(135deg, #0d6efd, #0dcaf0);">
            <div class="d-flex align-items-center">
                <div class="p-3 bg-white shadow-sm me-4 rounded-4 text-primary">
                    <i class="bi bi-box-seam display-4"></i>
                </div>
                <div>
                    <h3 class="mb-1 fw-bolder">{{ $warehouse->name }}</h3>
                    <div class="mt-2 opacity-75 d-flex align-items-center">
                        <span class="px-3 badge bg-light text-primary me-2 font-monospace fs-6"><i class="bi bi-upc-scan me-1"></i> {{ $warehouse->code }}</span>
                        @if($warehouse->is_active)
                            <span class="border badge bg-success border-light"><i class="bi bi-check-circle me-1"></i> Beroperasi (Aktif)</span>
                        @else
                            <span class="border badge bg-danger border-light"><i class="bi bi-x-circle me-1"></i> Ditutup (Nonaktif)</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="p-4 card-body p-md-5">
            <h5 class="pb-2 mb-4 fw-bold border-bottom text-primary"><i class="bi bi-card-text me-2"></i> Keterangan & Lokasi</h5>
            <div class="p-4 border fs-6 text-dark d-flex align-items-start bg-light rounded-3">
                <i class="mt-1 bi bi-geo-alt-fill me-3 text-secondary fs-4"></i>
                <span class="lh-lg">{{ $warehouse->description ?? 'Tidak ada rincian lokasi atau deskripsi yang diinputkan untuk gudang ini.' }}</span>
            </div>

            <div class="pt-3 mt-5 border-top text-end small text-muted">
                <i class="bi bi-clock-history me-1"></i> Data ditambahkan pada: {{ $warehouse->created_at->translatedFormat('d F Y - H:i') }}
            </div>
        </div>
    </div>
</div>
@endsection
