@extends('layouts.app')

@section('content')
<div class="container pb-5">
    <div class="mb-4">
        <a href="{{ route('workflows.index') }}" class="mb-3 border btn btn-light rounded-pill fw-bold"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
        <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-plus-circle me-2 text-primary"></i>Buat Matriks Persetujuan Baru</h4>
        <p class="mb-0 text-muted">Tambahkan aturan persetujuan untuk modul dokumen baru.</p>
    </div>

    {{-- ALARM ERROR SYSTEM --}}
    @if(session('error'))
        <div class="border-0 shadow-sm alert alert-danger rounded-4"><i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}</div>
    @endif

    {{-- 🔥 ALARM ERROR VALIDASI (TAMBAHAN BARU) 🔥 --}}
    @if ($errors->any())
        <div class="mb-4 border-0 shadow-sm alert alert-danger alert-dismissible fade show rounded-4">
            <div class="mb-1 fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Terdapat Kesalahan Input:</div>
            <ul class="mb-0 small">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('workflows.store') }}" method="POST" id="workflowForm">
        @csrf
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="border-0 shadow-sm card rounded-4">
                    <div class="py-3 bg-white card-header border-bottom">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-info-square me-2"></i>Info Matriks</h6>
                    </div>
                    <div class="p-4 card-body bg-light">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Nama Aturan (Matriks)</label>
                            <input type="text" name="name" class="form-control fw-bold border-primary" placeholder="Cth: Matriks Opex Umum" required value="{{ old('name') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Untuk Jenis Dokumen</label>
                            <select name="document_type" class="form-select border-primary fw-bold" required>
                                <option value="">-- Pilih Dokumen --</option>
                                @foreach($supportedModels as $namespace => $label)
                                    <option value="{{ $namespace }}" {{ old('document_type') == $namespace ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Berlaku Untuk</label>
                            <select name="department_id" class="form-select border-info text-dark fw-bold">
                                <option value="">[Berlaku UMUM / Default Semua Departemen]</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>Spesifik: {{ $dept->name }}</option>
                                @endforeach
                            </select>
                            <small class="mt-1 text-muted d-block">Sistem akan mencari aturan Spesifik Departemen terlebih dahulu. Jika tidak ada, sistem akan memakai aturan UMUM.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="border-0 border-4 shadow-sm card border-start border-primary rounded-4">
                    <div class="py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-list-ol me-2"></i>Urutan Tanda Tangan (Dari Bawah ke Atas)</h6>
                    </div>
                    <div class="p-4 card-body bg-light">
                        <div id="step-container">
                            {{-- Container Kosong, akan diisi via JS --}}
                        </div>

                        {{-- KLIK TOMBOL INI UNTUK MENAMBAH TINGKATAN APPROVAL --}}
                        <button type="button" class="py-3 mt-2 mb-4 border-dashed btn btn-outline-primary fw-bold w-100" id="btn-add-step">
                            <i class="mb-1 bi bi-plus-circle fs-5 d-block"></i> Klik di Sini Untuk Tambah Tingkatan Persetujuan
                        </button>

                        <div class="text-end">
                            <button type="submit" class="px-5 py-2 shadow btn btn-primary fw-bold rounded-pill">
                                <i class="bi bi-save me-2"></i> Buat Matriks
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    $(document).ready(function() {
        let roleOptions = `
            <option value="">-- Pilih Jabatan --</option>
            @foreach($roles as $role)
                <option value="{{ $role->id }}">{{ strtoupper($role->name) }}</option>
            @endforeach
        `;

        let deptOptions = `
            <option value="">[Atasan Langsung / Satu Departemen]</option>
            <option value="all">[Lintas Batas / Semua Departemen]</option>
            @foreach($departments as $dept)
                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
            @endforeach
        `;

        let stepIndex = 0;

        $('#btn-add-step').click(function() {
            let newRow = `
                <div class="p-3 mb-3 bg-white border shadow-sm step-row d-flex align-items-center rounded-3" style="display:none;">
                    <div class="me-3">
                        <span class="px-3 py-2 badge bg-dark rounded-circle step-number fs-6">X</span>
                    </div>
                    <div class="flex-grow-1 me-3">
                        <label class="mb-1 small text-muted fw-bold">Pilih Jabatan (Role)</label>
                        <select name="steps[${stepIndex}][role_id]" class="form-select border-primary fw-bold" required>
                            ${roleOptions}
                        </select>
                    </div>
                    <div class="flex-grow-1 me-3">
                        <label class="mb-1 small text-muted fw-bold">Departemen Penyetuju</label>
                        <select name="steps[${stepIndex}][target_department_id]" class="form-select border-info text-dark fw-bold">
                            ${deptOptions}
                        </select>
                    </div>
                    <div class="mt-4">
                        <button type="button" class="btn btn-outline-danger btn-remove-step rounded-circle" title="Hapus Lapis Ini"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            `;

            $('#step-container').append(newRow);
            $('.step-row').last().fadeIn(300);
            stepIndex++;
            updateStepNumbers();
        });

        $(document).on('click', '.btn-remove-step', function() {
            $(this).closest('.step-row').fadeOut(300, function() {
                $(this).remove();
                updateStepNumbers();
            });
        });

        function updateStepNumbers() {
            $('.step-row').each(function(index) {
                $(this).find('.step-number').text(index + 1);
                // Update indeks array agar selalu berurutan
                $(this).find('select').eq(0).attr('name', 'steps[' + index + '][role_id]');
                $(this).find('select').eq(1).attr('name', 'steps[' + index + '][target_department_id]');
            });
        }
    });
</script>
@endpush
