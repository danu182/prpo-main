@extends('layouts.app')

@push('css')
<style>
    .upload-area {
        border: 2px dashed #a5d6a7;
        transition: all 0.3s ease;
        background-color: #f1f8e9;
    }
    .upload-area:hover {
        background-color: #e8f5e9;
        border-color: #4caf50;
    }
    /* Custom style untuk file input agar lebih cantik */
    .file-input-wrapper label { margin-bottom: 0.5rem; }
</style>
@endpush

@section('content')
<div class="container-fluid pb-5 text-dark">

    <div class="mb-4">
        <a href="{{ route('items.index') }}" class="mb-2 text-decoration-none text-muted small fw-bold d-inline-block">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Katalog Barang
        </a>
        <h3 class="mb-0 fw-bold text-dark" style="letter-spacing: -0.5px;">
            <i class="bi bi-file-earmark-spreadsheet-fill text-success me-2"></i> Pengajuan Import Master Item
        </h3>
        <div class="mt-1 text-secondary small">Data yang diunggah akan masuk ke <span class="badge bg-warning text-dark">Ruang Karantina (Draft)</span> untuk diperiksa sebelum di-ACC.</div>
    </div>

    @if($errors->any())
        <div class="border-0 border-4 shadow-sm alert alert-danger rounded-4 border-start border-danger alert-dismissible fade show">
            <div class="fw-bold d-flex align-items-center"><i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-danger"></i> Gagal memproses file!</div>
            <ul class="mt-2 mb-0 small text-danger">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        {{-- SISI KIRI: FORM UPLOAD FILE MULTIPLE --}}
        <div class="col-lg-7">
            <div class="border-0 shadow-sm card rounded-4 h-100">
                <div class="p-4 card-body p-lg-5">
                    <form action="{{ route('items.preview_import') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="py-4 mb-4 text-center upload-area rounded-4">
                            <i class="mb-3 opacity-75 bi bi-cloud-arrow-up-fill display-3 text-success d-block"></i>
                            <h5 class="mb-4 fw-bold text-dark">Pilih File Data & Bukti Pendukung</h5>

                            <div class="px-4 px-md-5 text-start file-input-wrapper">
                                {{-- 1. UPLOAD EXCEL --}}
                                <div class="mb-4">
                                    <label class="form-label fw-bold small text-dark"><i class="bi bi-1-circle-fill text-success me-1"></i> File Excel Master Data <span class="text-danger">*</span></label>
                                    <input type="file" name="import_file" class="bg-white shadow-sm form-control form-control-lg border-success" accept=".xlsx,.xls,.csv" required style="font-size: 0.9rem;">
                                </div>

                                {{-- 2. UPLOAD BUKTI PENDUKUNG (MULTI-FILE) --}}
                                <div>
                                    <label class="form-label fw-bold small text-dark"><i class="bi bi-2-circle-fill text-primary me-1"></i> Lampiran Bukti Pendukung <span class="text-danger">*</span></label>
                                    <div class="text-muted" style="font-size: 0.7rem; margin-top: -5px; margin-bottom: 8px;">
                                        Brosur / Surat Pengajuan / Memo (Bisa pilih lebih dari 1 file)
                                    </div>

                                    {{-- Tambahkan id="attachmentInput" --}}
                                    <input type="file" name="attachments[]" id="attachmentInput" class="bg-white shadow-sm form-control border-primary" accept=".pdf,.jpg,.jpeg,.png" multiple required>

                                    {{-- 🔥 WADAH KOSONG UNTUK MENAMPILKAN DAFTAR FILE NANTI 🔥 --}}
                                    <div id="fileListContainer" class="flex-wrap gap-2 mt-2 d-flex"></div>

                                    <div class="mt-2 form-text text-info fw-bold" style="font-size: 0.75rem;">
                                        <i class="bi bi-keyboard"></i> Tahan tombol <strong>CTRL</strong> pada keyboard saat memilih untuk mengunggah banyak file sekaligus.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 mt-4 d-flex justify-content-between align-items-center border-top">
                            <div class="text-muted small fw-bold">
                                <i class="bi bi-info-circle-fill text-primary me-1"></i> Maks. Total: 10MB
                            </div>
                            <button type="submit" class="px-4 py-2 shadow-sm btn btn-success rounded-pill fw-bold fs-6">
                                Upload ke Karantina <i class="bi bi-upload ms-1"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- SISI KANAN: INSTRUKSI --}}
        <div class="col-lg-5">
            <div class="overflow-hidden text-white border-0 shadow-sm card rounded-4 h-100 bg-dark">
                <div class="p-4 card-body p-lg-5 d-flex flex-column">
                    <h5 class="mb-4 fw-bold text-warning"><i class="bi bi-diagram-3-fill me-2"></i>Alur Proses Pengajuan</h5>

                    <div class="pb-2 border-2 position-relative ms-3 border-start border-warning">
                        <div class="mb-4 position-relative ms-3">
                            <span class="top-0 p-2 position-absolute start-0 translate-middle bg-warning rounded-circle" style="margin-left: -17px; margin-top: 5px;"></span>
                            <h6 class="mb-1 fw-bold text-warning">1. Upload & Draft</h6>
                            <p class="mb-0 opacity-75 small text-light">Unggah Excel & file pendukung. Data akan masuk status DRAFT.</p>
                        </div>
                        <div class="mb-4 position-relative ms-3">
                            <span class="top-0 p-2 border border-2 position-absolute start-0 translate-middle bg-secondary rounded-circle border-dark" style="margin-left: -17px; margin-top: 5px;"></span>
                            <h6 class="mb-1 fw-bold text-light">2. Pengecekan (Preview)</h6>
                            <p class="mb-0 opacity-75 small text-light">Anda bisa melihat dan memperbaiki data salah (typo) langsung di sistem.</p>
                        </div>
                        <div class="position-relative ms-3">
                            <span class="top-0 p-2 border border-2 position-absolute start-0 translate-middle bg-secondary rounded-circle border-dark" style="margin-left: -17px; margin-top: 5px;"></span>
                            <h6 class="mb-1 fw-bold text-light">3. Approval Atasan</h6>
                            <p class="mb-0 opacity-75 small text-light">Atasan memverifikasi. Jika di-ACC, barang resmi terdaftar di Katalog.</p>
                        </div>
                    </div>

                    {{-- TOMBOL DOWNLOAD TEMPLATE --}}
                    <div class="p-4 mt-auto text-center bg-white border border-opacity-50 rounded-4 bg-opacity-10 border-secondary">
                        <h6 class="mb-3 fw-bold">Format Excel Resmi</h6>
                        <a href="{{ route('items.download_template') }}" class="shadow-sm btn btn-warning w-100 fw-bold rounded-pill">
                            <i class="bi bi-cloud-download-fill me-2"></i> Download Template (.xlsx)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


@push('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const fileInput = document.getElementById('attachmentInput');
        const fileListContainer = document.getElementById('fileListContainer');

        fileInput.addEventListener('change', function() {
            // Bersihkan wadah sebelum diisi ulang
            fileListContainer.innerHTML = '';

            // Looping semua file yang dipilih user
            Array.from(this.files).forEach(file => {
                // Tentukan ikon berdasarkan tipe file
                let iconClass = 'bi-file-earmark-text text-secondary';
                if (file.type.includes('pdf')) {
                    iconClass = 'bi-file-earmark-pdf-fill text-danger';
                } else if (file.type.includes('image')) {
                    iconClass = 'bi-file-earmark-image-fill text-success';
                }

                // Buat elemen visual (Badge/Pill)
                const pill = document.createElement('span');
                pill.className = 'badge bg-light text-dark border border-secondary-subtle shadow-sm d-flex align-items-center py-2 px-3';
                pill.style.fontSize = '0.75rem';
                pill.innerHTML = `<i class="bi ${iconClass} fs-6 me-2"></i> ${file.name}`;

                // Masukkan ke wadah
                fileListContainer.appendChild(pill);
            });
        });
    });
</script>
@endpush
