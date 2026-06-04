@extends('layouts.app')

@section('content')
<div class="container pb-5">
    <div class="mb-4">
        <a href="{{ route('document-types.index') }}" class="btn btn-light border rounded-pill fw-bold mb-3"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
        <h4 class="fw-bold text-dark"><i class="bi bi-plus-circle-fill me-2 text-primary"></i>Daftarkan Dokumen Baru</h4>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <form action="{{ route('document-types.update', $documentType->id) }}" method="POST">
                        @csrf
                        @method('PUT') <div class="mb-3">
                            <label class="form-label small fw-bold">Nama Tampilan Dokumen</label>
                            <input type="text" name="name" class="form-control" placeholder="Cth: Retur Barang Vendor" required value="{{ $documentType->name }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Namespace Model Class</label>
                            <input type="text" name="model_class" class="form-control" placeholder="Cth: App\Models\ReturnVendor" required value="{{ $documentType->model_class }}">
                            <small class="text-muted">Harus sesuai dengan nama file Model di kodingan.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Status</label>
                            <select name="is_active" class="form-select">
                                <option value="1">Aktif</option>
                                <option value="0">Non-Aktif</option>
                            </select>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary px-4 fw-bold rounded-pill shadow">Simpan Dokumen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="alert alert-warning border-warning rounded-4">
                <h6 class="fw-bold"><i class="bi bi-info-circle-fill me-2"></i>Catatan Penting:</h6>
                <p class="small mb-0">Menambahkan dokumen di sini akan membuat namanya muncul di dropdown <b>Matriks Persetujuan</b>. Namun, pastikan Programmer sudah membuat file Model, Controller, dan View fisiknya agar sistem tidak error saat diakses.</p>
            </div>
        </div>
    </div>
</div>
@endsection
