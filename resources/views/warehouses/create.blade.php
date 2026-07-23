@extends('layouts.app')

@section('content')
<div class="container pb-5 text-dark" style="max-width: 700px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-plus-circle text-primary me-2"></i> Tambah Gudang</h4>
        <a href="{{ route('warehouses.index') }}" class="btn btn-outline-secondary rounded-pill px-3"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
    </div>

    <form action="{{ route('warehouses.store') }}" method="POST">
        @csrf
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4 row g-3">
                <div class="col-12">
                    <div class="alert alert-info border-0 rounded-3 d-flex align-items-center mb-2">
                        <i class="bi bi-magic me-2 fs-5"></i>
                        <span class="small">Kode Gudang akan digenerate <strong>Otomatis oleh Sistem</strong> (contoh: GDG-001, GDG-002) saat disimpan.</span>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-bold">Nama Gudang <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Cth: Gudang Bahan Baku Utama" required value="{{ old('name') }}">
                    @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label class="form-label small fw-bold">Deskripsi Lokasi / Keterangan</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Alamat atau deskripsi singkat mengenai gudang ini...">{{ old('description') }}</textarea>
                </div>
            </div>
            <div class="card-footer bg-light border-top p-4 text-end rounded-bottom-4">
                <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm"><i class="bi bi-save me-1"></i> Simpan Data</button>
            </div>
        </div>
    </form>
</div>
@endsection
