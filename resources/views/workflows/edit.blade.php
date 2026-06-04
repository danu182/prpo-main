@extends('layouts.app')

@section('content')
<div class="container pb-5">
    <div class="mb-4">
        <a href="{{ route('workflows.index') }}" class="btn btn-light border rounded-pill fw-bold mb-3"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
        <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-sliders me-2 text-warning"></i>Ubah Formasi Persetujuan</h4>
        <p class="mb-0 text-muted">Dokumen: <span class="fw-bold text-primary">{{ $workflow->name }}</span></p>
    </div>

    @if(session('error'))
        <div class="alert alert-danger shadow-sm border-0 rounded-4"><i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 border-4 border-start border-primary shadow-sm rounded-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-list-ol me-2"></i>Urutan Tanda Tangan (Dari Bawah ke Atas)</h6>
                </div>
                <div class="card-body p-4 bg-light">

                    <form action="{{ route('workflows.update', $workflow->id) }}" method="POST" id="workflowForm">
                        @csrf
                        @method('PUT')

                        {{-- 🔥 KOTAK INPUT NAMA MATRIKS 🔥 --}}
                        <div class="mb-4 p-3 bg-white border border-primary shadow-sm rounded-3 border-start border-4">
                            <label class="form-label fw-bold small text-primary mb-1">Nama Aturan (Matriks)</label>
                            <input type="text" name="name" class="form-control fw-bold" value="{{ $workflow->name }}" required>
                            <small class="text-muted">Ganti nama ini agar sesuai dengan jumlah lapis persetujuan yang baru.</small>
                        </div>

                        <div id="step-container">
                            @forelse($workflow->steps as $index => $step)
                                <div class="step-row d-flex align-items-center mb-3 p-3 bg-white border shadow-sm rounded-3">
                                    <div class="me-3">
                                        <span class="badge bg-dark rounded-circle px-2 py-2 step-number">{{ $index + 1 }}</span>
                                    </div>
                                    <div class="flex-grow-1 me-3">
                                        <label class="small text-muted fw-bold mb-1">Pilih Jabatan (Role)</label>
                                        <select name="steps[{{ $index }}][role_id]" class="form-select border-primary fw-bold" required>
                                            <option value="">-- Pilih Jabatan --</option>
                                            @foreach($roles as $role)
                                                <option value="{{ $role->id }}" {{ $step->role_id == $role->id ? 'selected' : '' }}>{{ strtoupper($role->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="flex-grow-1 me-3">
                                        <label class="small text-muted fw-bold mb-1">Minimal Nominal (Rp)</label>
                                        <input type="number" name="steps[{{ $index }}][min_amount]" class="form-control border-warning fw-bold text-dark" value="{{ (int)$step->min_amount }}" min="0" required>
                                        <small class="x-small text-muted">Isi 0 jika selalu wajib ACC.</small>
                                    </div>
                                    <div class="mt-4">
                                        <button type="button" class="btn btn-outline-danger btn-remove-step rounded-circle" title="Hapus Lapis Ini"><i class="bi bi-trash"></i></button>
                                    </div>
                                </div>
                            @empty
                                <div class="alert alert-warning border-warning shadow-sm" id="empty-alert">
                                    Belum ada aturan persetujuan. Dokumen akan langsung otomatis Disetujui (Approved) jika tidak ada lapis persetujuan.
                                </div>
                            @endforelse
                        </div>

                        <button type="button" class="btn btn-outline-primary fw-bold border-dashed w-100 py-2 mt-2 mb-4" id="btn-add-step">
                            <i class="bi bi-plus-circle me-2"></i> Tambah Lapis Persetujuan (Ke Atas)
                        </button>

                        <div class="text-end">
                            <button type="submit" class="btn btn-success fw-bold px-5 py-2 rounded-pill shadow">
                                <i class="bi bi-save me-2"></i> Simpan Formasi Persetujuan
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="alert alert-info border-info shadow-sm rounded-4">
                <h6 class="fw-bold"><i class="bi bi-info-circle-fill me-2"></i>Cara Kerja:</h6>
                <ul class="small mb-0 ps-3">
                    <li class="mb-2">Angka <b>1</b> adalah orang pertama yang memproses dokumen.</li>
                    <li class="mb-2">Angka terbesar adalah penentu <b>Keputusan Final</b>.</li>
                    <li class="mb-2"><b>Minimal Nominal:</b> Atasan ini hanya akan dimintai persetujuan jika nilai total dokumen mencapai/melebihi angka tersebut.</li>
                    <li class="mb-0">Jika Anda menghapus semua baris, dokumen akan langsung berstatus <i>Auto-Approved</i>.</li>
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

        let stepIndex = {{ $workflow->steps->count() }};

        $('#btn-add-step').click(function() {
            $('#empty-alert').remove();

            let newRow = `
                <div class="step-row d-flex align-items-center mb-3 p-3 bg-white border shadow-sm rounded-3" style="display:none;">
                    <div class="me-3">
                        <span class="badge bg-dark rounded-circle px-2 py-2 step-number">X</span>
                    </div>
                    <div class="flex-grow-1 me-3">
                        <label class="small text-muted fw-bold mb-1">Pilih Jabatan (Role)</label>
                        <select name="steps[${stepIndex}][role_id]" class="form-select border-primary fw-bold" required>
                            ${roleOptions}
                        </select>
                    </div>
                    <div class="flex-grow-1 me-3">
                        <label class="small text-muted fw-bold mb-1">Minimal Nominal (Rp)</label>
                        <input type="number" name="steps[${stepIndex}][min_amount]" class="form-control border-warning fw-bold text-dark" value="0" min="0" required>
                        <small class="x-small text-muted">Isi 0 jika selalu wajib ACC.</small>
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
                // Update atribut name pada array
                $(this).find('select').attr('name', 'steps[' + index + '][role_id]');
                $(this).find('input[type="number"]').attr('name', 'steps[' + index + '][min_amount]');
            });
        }
    });
</script>
@endpush
