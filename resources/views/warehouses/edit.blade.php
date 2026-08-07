@extends('layouts.app')

@section('content')
<div class="container-fluid pb-5 text-dark" style="max-width: 700px;">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <h4 class="mb-0 fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i> Edit Data Gudang</h4>
        <a href="{{ route('warehouses.index') }}" class="px-3 btn btn-outline-secondary rounded-pill"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
    </div>

    <form action="{{ route('warehouses.update', $warehouse->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="mb-4 border-0 shadow-sm card rounded-4">
            <div class="p-4 card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Kode Gudang (Otomatis)</label>
                    <input type="text" class="form-control bg-light text-muted font-monospace fw-bold" value="{{ $warehouse->code }}" readonly>
                </div>
                <div class="col-md-8">
                    <label class="form-label small fw-bold">Nama Gudang <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ $warehouse->name }}" required>
                </div>
                <div class="mt-3 col-12">
                    <label class="form-label small fw-bold">Deskripsi Lokasi / Keterangan</label>
                    <textarea name="description" class="form-control" rows="4">{{ $warehouse->description }}</textarea>
                </div>
            </div>
            <div class="p-4 card-footer bg-light border-top d-flex justify-content-between align-items-center rounded-bottom-4">
                <div>
                    Status:
                    @if($warehouse->is_active)
                        <span class="badge bg-success">Aktif</span>
                    @else
                        <span class="badge bg-danger">Nonaktif</span>
                    @endif
                </div>
                <button type="submit" class="px-5 shadow-sm btn btn-primary rounded-pill fw-bold"><i class="bi bi-save me-1"></i> Perbarui Data</button>
            </div>
        </div>
    </form>
</div>
@endsection
