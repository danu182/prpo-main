@extends('layouts.app')

@section('content')
<div class="container pb-5">
    <div class="mb-4">
        <a href="{{ route('workflows.index') }}" class="mb-3 border btn btn-light rounded-pill fw-bold"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
        <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-sliders me-2 text-warning"></i>Ubah Formasi Persetujuan</h4>
        <p class="mb-0 text-muted">Dokumen: <span class="fw-bold text-primary">{{ $workflow->name }}</span></p>
    </div>

    @if(session('error'))
        <div class="border-0 shadow-sm alert alert-danger rounded-4"><i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="border-0 border-4 shadow-sm card border-start border-primary rounded-4">
                <div class="py-3 bg-white card-header border-bottom">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-list-ol me-2"></i>Urutan Tanda Tangan (Dari Bawah ke Atas)</h6>
                </div>
                <div class="p-4 card-body bg-light">

                    <form action="{{ route('workflows.update', $workflow->id) }}" method="POST" id="workflowForm">
                        @csrf
                        @method('PUT')

                        {{-- 🔥 KOTAK INFO MATRIKS (SUDAH DIPERBAIKI LENGKAP) 🔥 --}}
                        <div class="p-4 mb-4 bg-white border border-4 shadow-sm border-primary rounded-3 border-start">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="mb-1 form-label fw-bold small text-primary">Nama Aturan (Matriks)</label>
                                    <input type="text" name="name" class="form-control fw-bold" value="{{ $workflow->name }}" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="mb-1 form-label fw-bold small text-muted">Untuk Jenis Dokumen</label>
                                    <select name="document_type" class="form-select fw-bold bg-light" required>
                                        <option value="">-- Pilih Dokumen --</option>
                                        @foreach($supportedModels as $namespace => $label)
                                            <option value="{{ $namespace }}" {{ $workflow->document_type == $namespace ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="mb-1 form-label fw-bold small text-muted">Berlaku Untuk</label>
                                    <select name="department_id" class="form-select fw-bold border-info">
                                        <option value="">[Berlaku UMUM / Default Semua Departemen]</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}" {{ $workflow->department_id == $dept->id ? 'selected' : '' }}>Spesifik: {{ $dept->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div id="step-container">
                            @forelse($workflow->steps as $index => $step)
                                <div class="p-3 mb-3 bg-white border shadow-sm step-row d-flex align-items-center rounded-3">
                                    <div class="me-3">
                                        <span class="px-2 py-2 badge bg-dark rounded-circle step-number">{{ $index + 1 }}</span>
                                    </div>
                                    <div class="flex-grow-1 me-3">
                                        <label class="mb-1 small text-muted fw-bold">Pilih Jabatan (Role)</label>
                                        <select name="steps[{{ $index }}][role_id]" class="form-select border-primary fw-bold" required>
                                            <option value="">-- Pilih Jabatan --</option>
                                            @foreach($roles as $role)
                                                <option value="{{ $role->id }}" {{ $step->role_id == $role->id ? 'selected' : '' }}>{{ strtoupper($role->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    {{-- 🔥 TARGET DEPARTEMEN HYBRID 🔥 --}}
                                    <div class="flex-grow-1 me-3">
                                        <label class="mb-1 small text-muted fw-bold">Departemen Penyetuju</label>
                                        <select name="steps[{{ $index }}][target_department_id]" class="form-select border-info text-dark fw-bold">
                                            <option value="" {{ is_null($step->target_department_id) ? 'selected' : '' }}>[Atasan Langsung / Satu Departemen]</option>
                                            <option value="all" {{ $step->target_department_id === 0 ? 'selected' : '' }}>[Lintas Batas / Semua Departemen]</option>
                                            @foreach($departments as $dept)
                                                <option value="{{ $dept->id }}" {{ $step->target_department_id == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="flex-grow-1 me-3">
                                        <label class="mb-1 small text-muted fw-bold">Minimal Nominal (Rp)</label>
                                        <input type="number" name="steps[{{ $index }}][min_amount]" class="form-control border-warning fw-bold text-dark" value="{{ (int)$step->min_amount }}" min="0" required>
                                    </div>
                                    <div class="mt-4">
                                        <button type="button" class="btn btn-outline-danger btn-remove-step rounded-circle" title="Hapus Lapis Ini"><i class="bi bi-trash"></i></button>
                                    </div>
                                </div>
                            @empty
                                <div class="shadow-sm alert alert-warning border-warning" id="empty-alert">
                                    Belum ada aturan persetujuan. Dokumen akan langsung otomatis Disetujui (Approved) jika tidak ada lapis persetujuan.
                                </div>
                            @endforelse
                        </div>

                        <button type="button" class="py-2 mt-2 mb-4 border-dashed btn btn-outline-primary fw-bold w-100" id="btn-add-step">
                            <i class="bi bi-plus-circle me-2"></i> Tambah Lapis Persetujuan (Ke Atas)
                        </button>

                        <div class="text-end">
                            <button type="submit" class="px-5 py-2 shadow btn btn-success fw-bold rounded-pill">
                                <i class="bi bi-save me-2"></i> Simpan Formasi Persetujuan
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="shadow-sm alert alert-info border-info rounded-4">
                <h6 class="fw-bold"><i class="bi bi-info-circle-fill me-2"></i>Cara Kerja Hybrid:</h6>
                <ul class="mb-0 small ps-3">
                    <li class="mb-2">Jika Anda memilih <b>[Atasan Langsung]</b>, sistem memaksa mencari penyetuju dari Departemen yang <b>SAMA PERSIS</b> dengan pembuat tagihan.</li>
                    <li class="mb-2">Jika Anda memilih <b>Nama Departemen (Cth: HRD)</b>, sistem akan melempar persetujuan tersebut khusus ke orang dengan jabatan tersebut di divisi HRD.</li>
                    <li class="mb-0"><b>Minimal Nominal:</b> Berguna jika Direktur hanya mau menyetujui tagihan di atas angka tertentu.</li>
                </ul>
            </div>
        </div>
    </div>
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

        let stepIndex = {{ $workflow->steps->count() }};

        $('#btn-add-step').click(function() {
            $('#empty-alert').remove();

            let newRow = `
                <div class="p-3 mb-3 bg-white border shadow-sm step-row d-flex align-items-center rounded-3" style="display:none;">
                    <div class="me-3">
                        <span class="px-2 py-2 badge bg-dark rounded-circle step-number">X</span>
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
                    <div class="flex-grow-1 me-3">
                        <label class="mb-1 small text-muted fw-bold">Minimal Nominal (Rp)</label>
                        <input type="number" name="steps[${stepIndex}][min_amount]" class="form-control border-warning fw-bold text-dark" value="0" min="0" required>
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
                $(this).find('select').eq(0).attr('name', 'steps[' + index + '][role_id]');
                $(this).find('select').eq(1).attr('name', 'steps[' + index + '][target_department_id]');
                $(this).find('input[type="number"]').attr('name', 'steps[' + index + '][min_amount]');
            });
        }
    });
</script>
@endpush
