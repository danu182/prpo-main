@extends('layouts.app')

@section('content')
<div class="container-fluid pb-5 text-dark" style="max-width: 800px;">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <h4 class="mb-0 fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i> Tambah Perusahaan</h4>
        <a href="{{ route('companies.index') }}" class="px-3 btn btn-outline-secondary rounded-pill"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
    </div>

    {{-- Penting: tambahkan enctype untuk upload file --}}
    <form action="{{ route('companies.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="mb-4 border-0 shadow-sm card rounded-4">
            <div class="p-4 card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Kode Perusahaan <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="form-control text-uppercase" placeholder="Contoh: HO-01 / JKT-01" required>
                </div>
                <div class="col-md-8">
                    <label class="form-label small fw-bold">Nama Perusahaan / Entitas <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Contoh: PT Surya Makmur (Cabang Jakarta)" required>
                </div>

                <div class="mt-4 col-md-6">
                    <label class="form-label small fw-bold">Email Resmi</label>
                    <input type="email" name="email" class="form-control" placeholder="info@perusahaan.com">
                </div>
                <div class="mt-4 col-md-6">
                    <label class="form-label small fw-bold">Nomor Telepon</label>
                    <input type="text" name="phone" class="form-control" placeholder="021-1234567">
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-bold">NPWP Perusahaan</label>
                    <input type="text" name="tax_id" class="form-control" placeholder="00.000.000.0-000.000">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Upload Logo PT (Opsional)</label>
                    <input type="file" name="logo" class="form-control" accept="image/png, image/jpeg, image/jpg">
                    <div class="mt-1 small text-muted" style="font-size: 0.65rem;">Format: JPG/PNG, Maksimal: 2MB</div>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-bold">Alamat Lengkap</label>
                    <textarea name="address" class="form-control" rows="3" placeholder="Alamat lengkap jalan, gedung, kota..."></textarea>
                </div>
            </div>
            <div class="p-4 card-footer bg-light border-top d-flex justify-content-between align-items-center rounded-bottom-4">
                <div class="form-check form-switch fs-5">
                    <input class="form-check-input" type="checkbox" name="is_head_office" id="isHeadOffice" value="1">
                    <label class="mt-1 form-check-label fw-bold text-primary ms-2 fs-6" for="isHeadOffice">Jadikan Kantor Pusat (Head Office)</label>
                </div>
                <button type="submit" class="px-5 shadow-sm btn btn-primary rounded-pill fw-bold">Simpan Data</button>
            </div>
        </div>
    </form>
</div>
@endsection
