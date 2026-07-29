@extends('layouts.app')

@push('css')
<style>
    /* Styling Step Badge */
    .step-badge {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background-color: #198754;
        color: white;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1rem;
        margin-right: 12px;
        box-shadow: 0 4px 6px rgba(25, 135, 84, 0.2);
    }

    /* Styling Upload Zone Modern */
    .upload-zone {
        border: 2px dashed #cbd5e1;
        border-radius: 16px;
        padding: 4rem 2rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background-color: #f8fafc;
        position: relative;
        overflow: hidden;
        display: block;
    }

    .upload-zone:hover { border-color: #198754; background-color: #d1e7dd; }
    .upload-zone.has-file { border-color: #198754; border-style: solid; background-color: #f0fdf4; }
    .upload-zone input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 1; }

    .upload-icon { font-size: 4rem; color: #198754; margin-bottom: 1rem; transition: transform 0.3s ease; display: block; }
    .upload-zone:hover .upload-icon { transform: translateY(-5px); }

    .upload-icon-doc { color: #6c757d; }
    .upload-zone:hover .upload-icon-doc { color: #0dcaf0; }
    .upload-zone.has-file .upload-icon-doc { color: #0dcaf0; }

    .upload-text { font-size: 1.25rem; font-weight: 700; color: #334155; margin-bottom: 0.25rem; }
    .upload-hint { font-size: 0.9rem; color: #64748b; font-weight: 500; }

    /* 🔥 PERBAIKAN: File Name Display & Tombol Hapus 🔥 */
    .file-name-display {
        margin-top: 1.5rem;
        padding: 0.5rem 1.25rem;
        background-color: #fff;
        border-radius: 50px;
        display: none; /* Berubah jadi inline-flex saat muncul via JS */
        align-items: center;
        font-weight: 700;
        color: #198754;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        border: 1px solid #a3cfbb;
        position: relative;
        z-index: 10; /* Berada di atas input file */
    }

    .btn-remove-file {
        background: transparent;
        border: none;
        color: #dc3545;
        margin-left: 15px;
        padding: 0;
        cursor: pointer;
        font-size: 1.2rem;
        display: flex;
        align-items: center;
        transition: transform 0.2s ease;
    }

    .btn-remove-file:hover {
        transform: scale(1.2);
    }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid text-dark">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-cloud-arrow-up-fill me-2 text-success"></i> Import Master Aset</h4>
            <div class="mt-1 text-muted small">Fasilitas unggah massal untuk registrasi awal ratusan aset sekaligus.</div>
        </div>
        <a href="{{ route('fixed-assets.index') }}" class="btn btn-outline-secondary rounded-pill fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
        </a>
    </div>

    @if($errors->any())
        <div class="border-0 border-4 shadow-sm alert alert-danger alert-dismissible fade show rounded-4 border-start border-danger">
            <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> <strong>Gagal Memproses:</strong>
            <ul class="mt-1 mb-0 small">@foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-success">
        <form action="{{ route('fixed-assets.process_import') }}" method="POST" enctype="multipart/form-data">
            @csrf

            {{-- STEP 1: DOWNLOAD TEMPLATE --}}
            <div class="p-4 bg-white card-body border-bottom rounded-top-4">
                <div class="d-flex align-items-center">
                    <span class="step-badge">1</span>
                    <div>
                        <h5 class="mb-1 fw-bold text-dark">Siapkan Template Data</h5>
                        <div class="text-muted small">Unduh template Excel resmi dan isi sesuai kolom yang disediakan.</div>
                    </div>
                </div>
                <div class="mt-3 ps-5 ms-2">
                    <a href="{{ route('fixed-assets.download_template') }}" class="shadow-sm btn btn-outline-success fw-bold rounded-pill">
                        <i class="bi bi-file-earmark-spreadsheet-fill me-2"></i> Download Template .XLSX
                    </a>
                </div>
            </div>

            {{-- STEP 2: UPLOAD EXCEL --}}
            <div class="p-4 p-md-5 card-body border-bottom" style="background-color: #fafbfc;">
                <div class="mb-4 d-flex align-items-center">
                    <span class="step-badge">2</span>
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">Upload File Excel Aset <span class="text-danger">*</span></h5>
                    </div>
                </div>

                <div class="ps-md-5 ms-md-2">
                    <label class="shadow-sm upload-zone w-100" id="zone-excel">
                        <input type="file" name="import_file" id="input-excel" accept=".xlsx, .xls, .csv" required>
                        <i class="bi bi-file-earmark-excel-fill upload-icon"></i>
                        <div class="upload-text">Klik atau Drag & Drop file Excel ke sini</div>
                        <div class="upload-hint">Format yang diizinkan: .xlsx, .xls, .csv (Maks. 10MB)</div>

                        {{-- 🔥 Tombol Hapus Ditambahkan Di Sini 🔥 --}}
                        <div class="file-name-display" id="name-excel">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <span class="fname"></span>
                            <button type="button" class="btn-remove-file" id="btn-remove-excel" title="Hapus File"><i class="bi bi-x-circle-fill"></i></button>
                        </div>
                    </label>
                </div>
            </div>

            {{-- STEP 3: DOKUMEN PENDUKUNG --}}
            <div class="p-4 bg-white p-md-5 card-body rounded-bottom-4">
                <div class="mb-4 d-flex align-items-center">
                    <span class="step-badge bg-secondary">3</span>
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">Dokumen Pendukung <span class="text-muted fw-normal fs-6">(Opsional)</span></h5>
                    </div>
                </div>

                <div class="ps-md-5 ms-md-2">
                    <label class="shadow-sm upload-zone w-100" id="zone-doc" style="padding: 3rem 2rem;">
                        <input type="file" name="support_doc" id="input-doc" accept=".pdf,.jpg,.jpeg,.png">
                        <i class="bi bi-folder-symlink-fill upload-icon upload-icon-doc" style="font-size: 3.5rem;"></i>
                        <div class="upload-text" style="font-size: 1.1rem;">Lampirkan BAST Hibah, PO, atau Nota Resmi</div>
                        <div class="upload-hint">Format: PDF, JPG, PNG (Maks 5MB). Berlaku untuk semua aset di file excel.</div>

                        {{-- 🔥 Tombol Hapus Ditambahkan Di Sini 🔥 --}}
                        <div class="file-name-display text-info border-info" id="name-doc" style="color: #0dcaf0!important;">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <span class="fname"></span>
                            <button type="button" class="btn-remove-file" id="btn-remove-doc" title="Hapus File"><i class="bi bi-x-circle-fill"></i></button>
                        </div>
                    </label>
                </div>
            </div>

            {{-- FOOTER / ACTION BUTTON --}}
            <div class="p-4 card-footer bg-light border-top d-flex justify-content-between align-items-center rounded-bottom-4">
                <span class="text-muted small"><i class="bi bi-shield-lock-fill me-1"></i> Data akan divalidasi sistem.</span>
                <button type="submit" class="px-5 shadow-sm btn btn-success fw-bold rounded-pill btn-lg">
                    <i class="bi bi-cloud-upload-fill me-2"></i> Mulai Proses Import
                </button>
            </div>

        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {

        function handleFileInput(inputId, zoneId, nameId, removeBtnId) {
            const input = document.getElementById(inputId);
            const zone = document.getElementById(zoneId);
            const nameDisplay = document.getElementById(nameId);
            const nameSpan = nameDisplay.querySelector('.fname');
            const removeBtn = document.getElementById(removeBtnId);

            // Logika Memilih File
            input.addEventListener('change', function(e) {
                if (this.files && this.files.length > 0) {
                    const fileName = this.files[0].name;
                    nameSpan.textContent = fileName;
                    nameDisplay.style.display = 'inline-flex'; // Menampilkan kotak nama
                    zone.classList.add('has-file');
                } else {
                    nameDisplay.style.display = 'none';
                    zone.classList.remove('has-file');
                }
            });

            // 🔥 Logika Menghapus File 🔥
            removeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation(); // Mencegah kotak dialog Windows Explorer ikut terbuka

                input.value = ''; // Kosongkan file di HTML
                const event = new Event('change');
                input.dispatchEvent(event); // Beri tahu sistem bahwa file sudah kosong
            });

            // Logika Warna Drag & Drop
            zone.addEventListener('dragover', function(e) {
                e.preventDefault();
                zone.style.backgroundColor = inputId === 'input-excel' ? '#d1e7dd' : '#cff4fc';
            });

            zone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                zone.style.backgroundColor = zone.classList.contains('has-file') ? (inputId === 'input-excel' ? '#f0fdf4' : '#f8f9fa') : (inputId === 'input-excel' ? '#f8fafc' : '#ffffff');
            });

            zone.addEventListener('drop', function(e) {
                e.preventDefault();
                zone.style.backgroundColor = zone.classList.contains('has-file') ? (inputId === 'input-excel' ? '#f0fdf4' : '#f8f9fa') : (inputId === 'input-excel' ? '#f8fafc' : '#ffffff');
                if(e.dataTransfer.files.length > 0) {
                    input.files = e.dataTransfer.files;
                    const event = new Event('change');
                    input.dispatchEvent(event);
                }
            });
        }

        // Eksekusi untuk 2 kotak input
        handleFileInput('input-excel', 'zone-excel', 'name-excel', 'btn-remove-excel');
        handleFileInput('input-doc', 'zone-doc', 'name-doc', 'btn-remove-doc');
    });
</script>
@endpush
