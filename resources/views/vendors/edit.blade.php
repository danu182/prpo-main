@extends('layouts.app')

@push('css')
<style>.locked-input { background-color: #e9ecef !important; cursor: not-allowed; }</style>
@endpush

@section('content')
<div class="container-fluid pb-5 text-dark">
    <div class="mb-4">
        <a href="{{ route('vendors.index') }}" class="mb-2 text-decoration-none text-muted small fw-bold d-inline-block">
            <i class="bi bi-arrow-left me-1"></i> Batal & Kembali
        </a>
        <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-pencil-square text-warning me-2"></i> Edit Mitra Vendor</h4>
    </div>

    @if($errors->any())
        <div class="border-0 border-4 shadow-sm alert alert-danger rounded-3 border-start border-danger">
            <div class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Terdapat Kesalahan:</div>
            <ul class="mt-2 mb-0 small">
                @foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    @endif

    {{-- 🔥 GEMBOK TRANSAKSI 🔥 --}}
    @if($vendor->hasTransactions())
        <div class="mb-4 border-4 shadow-sm alert alert-warning border-warning rounded-4 border-start">
            <h6 class="mb-1 fw-bold"><i class="bi bi-lock-fill me-2"></i>Identitas Vendor Terkunci</h6>
            <small>Vendor ini sudah terikat dengan dokumen transaksi (Purchase Order). <b>Nama Perusahaan</b> tidak dapat diubah lagi untuk menjaga validitas dokumen historis.</small>
        </div>
    @endif

    <form action="{{ route('vendors.update', $vendor->id) }}" method="POST">
        @csrf @method('PUT')

        {{-- 1. INFO PERUSAHAAN --}}
        <div class="mb-4 border-0 border-4 shadow-sm card rounded-4 border-start border-primary">
            <div class="py-3 bg-white card-header border-bottom"><h6 class="mb-0 fw-bold text-dark"><i class="bi bi-building me-2 text-primary"></i>Informasi Perusahaan</h6></div>
            <div class="p-4 card-body bg-light">
                <div class="row g-4">
                    <div class="col-md-2">
                        <label class="form-label fw-bold small text-muted">Kode Vendor</label>
                        <input type="text" class="shadow-sm form-control fw-bold text-primary locked-input" value="{{ $vendor->code }}" disabled>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold small text-muted">Nama Perusahaan / Vendor <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="shadow-sm form-control border-primary {{ $vendor->hasTransactions() ? 'locked-input' : '' }}" required value="{{ old('name', $vendor->name) }}" {{ $vendor->hasTransactions() ? 'readonly' : '' }}>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold small text-muted">NPWP / Tax ID</label>
                        <input type="text" name="tax_id" class="shadow-sm form-control" value="{{ old('tax_id', $vendor->tax_id) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Email Perusahaan</label>
                        <input type="email" name="email" class="shadow-sm form-control" value="{{ old('email', $vendor->email) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">No. Telepon Kantor</label>
                        <input type="text" name="phone" class="shadow-sm form-control" value="{{ old('phone', $vendor->phone) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold small text-muted">Alamat Lengkap</label>
                        <textarea name="address" class="shadow-sm form-control" rows="2">{{ old('address', $vendor->address) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-4 row g-4">
            {{-- 2. INFO PIC --}}
            <div class="col-lg-6">
                <div class="border-0 border-4 shadow-sm card rounded-4 border-start border-warning h-100">
                    <div class="py-3 bg-white card-header border-bottom"><h6 class="mb-0 fw-bold text-dark"><i class="bi bi-person-badge me-2 text-warning"></i>Contact Person (PIC)</h6></div>
                    <div class="p-4 card-body bg-light">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Nama PIC (Sales/Marketing)</label>
                            <input type="text" name="pic_name" class="shadow-sm form-control" value="{{ old('pic_name', $vendor->pic_name) }}">
                        </div>
                        <div>
                            <label class="form-label fw-bold small text-muted">No. HP / WhatsApp PIC</label>
                            <input type="text" name="pic_phone" class="shadow-sm form-control" value="{{ old('pic_phone', $vendor->pic_phone) }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3. INFO KEUANGAN --}}
            <div class="col-lg-6">
                <div class="border-0 border-4 shadow-sm card rounded-4 border-start border-success h-100">
                    <div class="py-3 bg-white card-header border-bottom"><h6 class="mb-0 fw-bold text-dark"><i class="bi bi-bank me-2 text-success"></i>Informasi Keuangan & Pembayaran</h6></div>
                    <div class="p-4 card-body bg-light">
                        <div class="mb-3 row g-3">
                            <div class="col-sm-4">
                                    <label class="form-label fw-bold small text-muted">Nama Bank</label>
                                    <select name="bank_name" class="shadow-sm form-select">
                                        <option value="">-- Pilih Bank --</option>
                                        @foreach($banks as $bank)
                                            <option value="{{ $bank->name }}" {{ old('bank_name', $vendor->bank_name) == $bank->name ? 'selected' : '' }}>
                                                {{ $bank->code }} - {{ $bank->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            <div class="col-sm-8">
                                <label class="form-label fw-bold small text-muted">Nomor Rekening</label>
                                <input type="text" name="bank_account_number" class="shadow-sm form-control" value="{{ old('bank_account_number', $vendor->bank_account_number) }}">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Atas Nama Rekening</label>
                            <input type="text" name="bank_account_name" class="shadow-sm form-control" value="{{ old('bank_account_name', $vendor->bank_account_name) }}">
                        </div>
                        <div>
                            <label class="form-label fw-bold small text-muted">Term of Payment (TOP) <span class="text-danger">*</span></label>
                            <div class="shadow-sm input-group">
                                <input type="number" name="payment_terms_days" class="text-center form-control fw-bold border-success" required min="0" value="{{ old('payment_terms_days', $vendor->payment_terms_days) }}">
                                <span class="input-group-text bg-light text-muted">Hari</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-5 text-end">
            <button type="submit" class="px-5 py-2 shadow-lg btn btn-warning text-dark rounded-pill fw-bold fs-5"><i class="bi bi-save me-2"></i> Simpan Perubahan Data</button>
        </div>
    </form>
</div>
@endsection
