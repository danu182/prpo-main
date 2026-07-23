@extends('layouts.app')

@section('content')
<div class="container pb-5 text-dark" style="max-width: 800px;">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <h4 class="mb-0 fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i> Edit Data Perusahaan</h4>
        <a href="{{ route('companies.index') }}" class="px-3 btn btn-outline-secondary rounded-pill"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
    </div>

    {{-- WAJIB ada enctype="multipart/form-data" untuk upload file --}}
    <form action="{{ route('companies.update', $company->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="mb-4 border-0 shadow-sm card rounded-4">
            <div class="p-4 card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Kode Perusahaan <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="form-control text-uppercase" value="{{ $company->code }}" required>
                </div>
                <div class="col-md-8">
                    <label class="form-label small fw-bold">Nama Perusahaan / Entitas <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ $company->name }}" required>
                </div>

                <div class="mt-4 col-md-6">
                    <label class="form-label small fw-bold">Email Resmi</label>
                    <input type="email" name="email" class="form-control" value="{{ $company->email }}">
                </div>
                <div class="mt-4 col-md-6">
                    <label class="form-label small fw-bold">Nomor Telepon</label>
                    <input type="text" name="phone" class="form-control" value="{{ $company->phone }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-bold">NPWP Perusahaan</label>
                    <input type="text" name="tax_id" class="form-control" value="{{ $company->tax_id }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold mb-3">Logo Perusahaan</label>
                    <div class="d-flex align-items-start gap-3">
                        {{-- Frame Logo (Besar) --}}
                        <div class="flex-shrink-0">
                            @if($company->logo_path)
                                <img src="{{ asset('storage/' . $company->logo_path) }}" alt="Logo PT" class="rounded-4 border shadow-sm" style="width: 120px; height: 120px; object-fit: contain; background-color: #ffffff; padding: 10px;">
                            @else
                                <div class="rounded-4 border shadow-sm d-flex align-items-center justify-content-center bg-light text-secondary" style="width: 120px; height: 120px;">
                                    <i class="bi bi-buildings display-4 opacity-25"></i>
                                </div>
                            @endif
                        </div>

                        {{-- Input File --}}
                        <div class="flex-grow-1 mt-1">
                            <label class="form-label small fw-semibold text-primary">Ganti Logo (Opsional)</label>
                            <input type="file" name="logo" class="form-control" accept="image/png, image/jpeg, image/jpg">
                            <div class="mt-2 text-muted" style="font-size: 0.75rem; line-height: 1.4;">
                                <strong>Ketentuan:</strong><br>
                                - Format file: JPG, JPEG, atau PNG.<br>
                                - Ukuran maksimal: 2 MB.<br>
                                <span class="text-warning-emphasis">* Biarkan kosong jika tidak ingin mengganti logo saat ini.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-bold">Alamat Lengkap</label>
                    <textarea name="address" class="form-control" rows="3">{{ $company->address }}</textarea>
                </div>
            </div>

            <div class="p-4 card-footer bg-light border-top d-flex justify-content-between align-items-center rounded-bottom-4">
                <div class="form-check form-switch fs-5">
                    <input class="form-check-input" type="checkbox" name="is_head_office" id="isHeadOffice" value="1" {{ $company->is_head_office ? 'checked' : '' }}>
                    <label class="mt-1 form-check-label fw-bold text-primary ms-2 fs-6" for="isHeadOffice">Jadikan Kantor Pusat (Head Office)</label>
                </div>
                <button type="submit" class="px-5 shadow-sm btn btn-primary rounded-pill fw-bold">Perbarui Data</button>
            </div>
        </div>
    </form>
</div>
@endsection
