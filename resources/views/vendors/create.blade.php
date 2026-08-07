@extends('layouts.app')

@section('content')
<div class="container-fluid pb-5 text-dark">
    <div class="mb-4">
        <a href="{{ route('vendors.index') }}" class="mb-2 text-decoration-none text-muted small fw-bold d-inline-block">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Buku Vendor
        </a>
        <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-plus-circle-fill text-dark me-2"></i> Tambah Mitra Vendor</h4>
    </div>

    @if($errors->any())
        <div class="border-0 border-4 shadow-sm alert alert-danger rounded-3 border-start border-danger">
            <div class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Terdapat Kesalahan:</div>
            <ul class="mt-2 mb-0 small">
                @foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('vendors.store') }}" method="POST">
        @csrf

        {{-- 1. INFO PERUSAHAAN --}}
        <div class="mb-4 border-0 border-4 shadow-sm card rounded-4 border-start border-primary">
            <div class="py-3 bg-white card-header border-bottom"><h6 class="mb-0 fw-bold text-dark"><i class="bi bi-building me-2 text-primary"></i>Informasi Perusahaan</h6></div>
            <div class="p-4 card-body bg-light">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Nama Perusahaan / Vendor <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="shadow-sm form-control border-primary" placeholder="Cth: PT Indofood Sukses Makmur" required value="{{ old('name') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">NPWP / Tax ID</label>
                        <input type="text" name="tax_id" class="shadow-sm form-control" placeholder="Cth: 01.234.567.8-901.000" value="{{ old('tax_id') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Email Perusahaan</label>
                        <input type="email" name="email" class="shadow-sm form-control" placeholder="Cth: info@vendor.com" value="{{ old('email') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">No. Telepon Kantor</label>
                        <input type="text" name="phone" class="shadow-sm form-control" placeholder="Cth: (021) 1234567" value="{{ old('phone') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold small text-muted">Alamat Lengkap</label>
                        <textarea name="address" class="shadow-sm form-control" rows="2" placeholder="Alamat lengkap perusahaan...">{{ old('address') }}</textarea>
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
                            <input type="text" name="pic_name" class="shadow-sm form-control" placeholder="Cth: Bpk. Budi Santoso" value="{{ old('pic_name') }}">
                        </div>
                        <div>
                            <label class="form-label fw-bold small text-muted">No. HP / WhatsApp PIC</label>
                            <input type="text" name="pic_phone" class="shadow-sm form-control" placeholder="Cth: 081234567890" value="{{ old('pic_phone') }}">
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
                                        <option value="{{ $bank->name }}" {{ old('bank_name') == $bank->name ? 'selected' : '' }}>
                                            {{ $bank->code }} - {{ $bank->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-8">
                                <label class="form-label fw-bold small text-muted">Nomor Rekening</label>
                                <input type="text" name="bank_account_number" class="shadow-sm form-control" placeholder="Cth: 1234567890" value="{{ old('bank_account_number') }}">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Atas Nama Rekening</label>
                            <input type="text" name="bank_account_name" class="shadow-sm form-control" placeholder="Cth: PT Indofood Sukses Makmur" value="{{ old('bank_account_name') }}">
                        </div>
                        <div>
                            <label class="form-label fw-bold small text-muted">Term of Payment (TOP) <span class="text-danger">*</span></label>
                            <div class="shadow-sm input-group">
                                <input type="number" name="payment_terms_days" class="text-center form-control fw-bold border-success" required min="0" value="{{ old('payment_terms_days', 0) }}">
                                <span class="input-group-text bg-light text-muted">Hari</span>
                            </div>
                            <small class="text-muted" style="font-size: 0.7rem;">Isi "0" untuk pembayaran Cash/COD.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-5 text-end">
            <button type="submit" class="px-5 py-2 shadow-lg btn btn-dark rounded-pill fw-bold fs-5"><i class="bi bi-save me-2"></i> Simpan Data Vendor</button>
        </div>
    </form>
</div>
@endsection
