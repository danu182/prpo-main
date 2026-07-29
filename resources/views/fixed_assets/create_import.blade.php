@extends('layouts.app')

@section('content')
<div class="pb-5 container-fluid text-dark">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-file-earmark-excel me-2 text-success"></i> Import Master Aset Excel</h4>
            <div class="mt-1 text-muted small">Fasilitas unggah massal untuk migrasi data awal dari Excel.</div>
        </div>
        <a href="{{ route('fixed-assets.index') }}" class="btn btn-outline-secondary rounded-pill fw-bold"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-success">
                <form action="{{ route('fixed-assets.process_import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="p-4 card-body">
                        <div class="mb-4 shadow-sm alert alert-success bg-success-subtle text-success-emphasis border-success-subtle">
                            <h6 class="fw-bold"><i class="bi bi-info-circle me-1"></i> Petunjuk Import Massal:</h6>
                            <p class="mb-0 small">Gunakan file Excel dengan format header standar dari sistem. Pastikan seluruh kolom yang wajib (Master, Kategori, Gudang) terisi sesuai master data. Jika belum punya template-nya, silakan unduh di bawah ini:</p>
                            <div class="mt-3">
                                <a href="{{ route('fixed-assets.download_template') }}" class="shadow-sm btn btn-success fw-bold rounded-pill">
                                    <i class="bi bi-download me-2"></i> Download Template .XLSX
                                </a>
                            </div>
                        </div>

                        <div class="p-4 mb-4 text-center border rounded-4 bg-light">
                            <label class="mb-3 form-label fw-bold fs-5 text-dark">Upload File Excel Anda <span class="text-danger">*</span></label>
                            <input type="file" name="import_file" class="mx-auto shadow-sm form-control form-control-lg border-success" accept=".xlsx, .xls, .csv" required style="max-width: 500px;">
                            <div class="mt-2 form-text text-muted">Format: .xlsx / .xls / .csv (Maks. 10MB)</div>
                        </div>

                        <div class="p-4 mb-3 border rounded-4">
                            <label class="form-label fw-bold text-dark"><i class="bi bi-paperclip me-1"></i> File Dokumen Pendukung (Opsional)</label>
                            <input type="file" name="support_doc" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                            <div class="form-text small">Lampirkan BAST Hibah, PO, atau Nota (Maks 5MB). File ini akan melekat pada semua aset yang di-import pada batch ini.</div>
                        </div>
                    </div>
                    <div class="p-4 bg-white card-footer border-top text-end rounded-bottom-4">
                        <button type="submit" class="px-5 shadow-sm btn btn-success fw-bold rounded-pill btn-lg"><i class="bi bi-eye me-2"></i> Mulai Proses Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
