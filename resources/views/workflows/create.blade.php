@extends('layouts.app')

@section('content')
<div class="container pb-5">
    <div class="mb-4">
        <a href="{{ route('workflows.index') }}" class="btn btn-light border rounded-pill fw-bold mb-3"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
        <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-plus-circle me-2 text-primary"></i>Buat Matriks Persetujuan Baru</h4>
        <p class="mb-0 text-muted">Tambahkan aturan persetujuan untuk modul dokumen baru.</p>
    </div>

    @if(session('error'))
        <div class="alert alert-danger shadow-sm border-0 rounded-4"><i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}</div>
    @endif

    <form action="{{ route('workflows.store') }}" method="POST" id="workflowForm">
        @csrf
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-info-square me-2"></i>Info Matriks</h6>
                    </div>
                    <div class="card-body p-4 bg-light">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Nama Aturan (Matriks)</label>
                            <input type="text" name="name" class="form-control fw-bold border-primary" placeholder="Cth: Matriks Persetujuan GR" required value="{{ old('name') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Untuk Jenis Dokumen</label>
                            <select name="document_type" class="form-select border-primary fw-bold" required>
                                <option value="">-- Pilih Dokumen --</option>
                                @foreach($supportedModels as $namespace => $label)
                                    <option value="{{ $namespace }}" {{ old('document_type') == $namespace ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted mt-1 d-block">Satu jenis dokumen hanya boleh memiliki 1 Matriks aktif.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card border-0 border-4 border-start border-primary shadow-sm rounded-4">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-list-ol me-2"></i>Urutan Tanda Tangan (Dari Bawah ke Atas)</h6>
                    </div>
                    <div class="card-body p-4 bg-light">
                        <div id="step-container">
                            {{-- Container Kosong, akan diisi via JS --}}
                        </div>

                        <button type="button" class="btn btn-outline-primary fw-bold border-dashed w-100 py-3 mt-2 mb-4" id="btn-add-step">
                            <i class="bi bi-plus-circle fs-5 d-block mb-1"></i> Tambah Lapis Persetujuan (Ke Atas)
                        </button>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary fw-bold px-5 py-2 rounded-pill shadow">
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

        let stepIndex = 0;

        $('#btn-add-step').click(function() {
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
                // Update indeks array agar selalu berurutan
                $(this).find('select').attr('name', 'steps[' + index + '][role_id]');
                $(this).find('input[type="number"]').attr('name', 'steps[' + index + '][min_amount]');
            });
        }
    });
</script>
@endpush
