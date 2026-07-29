@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<style>
    .select2-container .select2-selection--single { height: 38px !important; border: 1px solid #e2e8f0 !important; border-radius: 8px !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; color: #475569 !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
    .ck-editor__editable_inline { min-height: 150px; border-bottom-left-radius: 8px !important; border-bottom-right-radius: 8px !important; }
    .ck-toolbar { border-top-left-radius: 8px !important; border-top-right-radius: 8px !important; background-color: #f8fafc !important; }
    .photo-preview-item { transition: transform 0.2s ease-in-out; }
    .photo-preview-item:hover { transform: scale(1.1); z-index: 10; }
    .btn-dashed { border-style: dashed !important; border-width: 2px !important; }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid text-dark">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-pencil-square me-2 text-info"></i> Update Data Aset: {{ $asset->asset_number }}</h4>
            <div class="mt-1 text-muted small">Perbarui spesifikasi, tanggal perolehan (untuk perhitungan penyusutan), status, dan foto fisik.</div>
        </div>
        <a href="{{ route('fixed-assets.index') }}" class="btn btn-outline-secondary rounded-pill fw-bold"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
    </div>

    @if($errors->any())
        <div class="border-0 border-4 shadow-sm alert alert-danger alert-dismissible fade show rounded-4 border-start border-danger">
            <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> <strong>Gagal Memproses:</strong>
            <ul class="mt-1 mb-0 small">@foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-info">
        <form action="{{ route('fixed-assets.update', $asset->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="p-4 card-body">
                <div class="p-3 mb-4 text-center border bg-light rounded-4">
                    <h5 class="mb-1 fw-bold text-dark">{{ $asset->name ?? optional($asset->item)->name }}</h5>
                    <span class="px-3 py-1 badge bg-primary">No. Aset: {{ $asset->asset_number }}</span>
                    @if($asset->goodsReceipt)
                        <span class="px-3 py-1 badge bg-info text-dark">Ref GR: {{ $asset->goodsReceipt->gr_number }}</span>
                    @else
                        <span class="px-3 py-1 badge bg-secondary">Input Manual / Hibah</span>
                    @endif
                </div>

                <div class="row g-5">
                    <div class="col-md-6 border-end pe-md-5">
                        <h6 class="pb-2 mb-4 border-bottom text-primary fw-bold"><i class="bi bi-box-seam me-2"></i>Detail Fisik & Spesifikasi</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Serial Number (S/N) Fisik</label>
                            <input type="text" name="serial_number" class="shadow-sm form-control" value="{{ old('serial_number', $asset->serial_number) }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">No. Aset (Label Akuntansi / FA)</label>
                            <input type="text" name="accounting_asset_number" class="shadow-sm form-control border-info" value="{{ old('accounting_asset_number', $asset->accounting_asset_number) }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Spesifikasi Detail / Unik Unit <span class="text-danger">*</span></label>
                            <textarea name="spesifikasi_detail" id="spesifikasi_editor_edit" class="shadow-sm form-control" rows="4">{{ old('spesifikasi_detail', $asset->spesifikasi_detail) }}</textarea>
                        </div>

                        {{-- GALERI FOTO & TAMBAH FOTO BARU --}}
                        <div class="p-3 mt-4 border rounded-3 bg-light border-primary-subtle">
                            <label class="pb-2 mb-3 d-block text-primary fw-bold border-bottom" style="font-size:0.85rem;">
                                <i class="bi bi-images me-1"></i> Galeri Foto Fisik & Upload Tambahan
                            </label>

                            @if($asset->photos->count() > 0)
                                <div class="mb-3">
                                    <div class="mb-2 small text-muted">Foto Saat Ini (Klik untuk memperbesar):</div>
                                    <div class="flex-wrap gap-2 d-flex">
                                        @foreach($asset->photos as $photo)
                                            <div class="p-1 bg-white border rounded shadow-sm position-relative">
                                                <a href="{{ asset('storage/' . $photo->file_path) }}" target="_blank">
                                                    <img src="{{ asset('storage/' . $photo->file_path) }}" class="rounded" style="width: 70px; height: 70px; object-fit: cover;">
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div id="photo-inputs-container" class="gap-2 mb-3 d-flex flex-column">
                                <div class="shadow-sm input-group input-group-sm photo-input-row">
                                    <input type="file" name="photos[]" class="form-control border-primary-subtle single-photo-input" accept="image/*">
                                    <button type="button" class="btn btn-danger remove-photo-row" title="Hapus baris ini"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>

                            <button type="button" class="btn btn-sm btn-light border-primary text-primary w-100 fw-bold add-photo-btn btn-dashed">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Foto Baru Lainnya
                            </button>

                            <div id="preview-photos" class="flex-wrap gap-2 p-2 mt-3 bg-white border rounded d-flex empty-preview align-items-center justify-content-center" style="min-height: 80px;">
                                <span class="text-muted small fst-italic"><i class="bi bi-image me-1"></i> Preview foto baru akan muncul di sini...</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 ps-md-5">
                        <h6 class="pb-2 mb-4 border-bottom text-primary fw-bold"><i class="bi bi-cash-coin me-2"></i>Nilai & Penyusutan</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Tanggal Perolehan <span class="text-danger">*</span></label>
                            <input type="date" name="acquisition_date" class="shadow-sm form-control border-info" value="{{ old('acquisition_date', $asset->acquisition_date) }}" required>
                            <div class="form-text" style="font-size: 0.7rem;"><i class="bi bi-info-circle"></i> Memengaruhi perhitungan umur & Nilai Buku Saat Ini.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Mata Uang & Harga Beli <span class="text-danger">*</span></label>
                            <div class="shadow-sm input-group">
                                <select name="currency_id" class="input-group-text bg-light border-success fw-bold text-dark" style="cursor: pointer;" required>
                                    @foreach($currencies as $currency)
                                        <option value="{{ $currency->id }}" {{ $asset->currency_id == $currency->id ? 'selected' : '' }}>{{ $currency->code }}</option>
                                    @endforeach
                                </select>
                                <input type="number" name="purchase_price" class="form-control border-success" value="{{ old('purchase_price', $asset->purchase_price) }}" placeholder="0" min="0">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted">Kategori Penyusutan <span class="text-danger">*</span></label>
                            <select name="asset_category_id" class="shadow-sm form-select" required>
                                <option value="">-- Pilih Kategori --</option>
                                @foreach($assetCategories as $category)
                                    <option value="{{ $category->id }}" {{ $asset->asset_category_id == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }} ({{ $category->useful_life_years }} Thn)
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <h6 class="pb-2 mt-4 mb-3 border-bottom text-primary fw-bold"><i class="bi bi-geo-alt me-2"></i>Lokasi & Status</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Aset Milik PT / Departemen <span class="text-danger">*</span></label>
                            <select name="company_id" class="shadow-sm form-select" required>
                                <option value="">-- Pilih Pemilik Aset --</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ $asset->company_id == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Status Aset <span class="text-danger">*</span></label>
                            <select name="status_id" class="shadow-sm form-select" onchange="toggleAssignee(this.options[this.selectedIndex].getAttribute('data-slug'))" required>
                                <option value="">-- Pilih Status Aset --</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status->id }}" data-slug="{{ $status->slug }}" {{ $asset->status_id == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Ditugaskan Kepada (User)</label>
                            <select name="assigned_to" id="assigneeSelect" class="shadow-sm form-select select2-user" style="width: 100%;" {{ optional($asset->status)->slug !== 'in_use' ? 'disabled' : '' }}>
                                <option value="">-- Cari Nama Karyawan --</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ $asset->assigned_to == $user->id ? 'selected' : '' }}>{{ $user->name }} • {{ optional($user->company)->name ?? 'Kantor Pusat' }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-bold small text-muted">Catatan / Alasan Perubahan Data</label>
                            <textarea name="notes" class="shadow-sm form-control border-warning" rows="2" placeholder="Tulis catatan atau alasan update data...">{{ old('notes', $asset->notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-white card-footer border-top text-end rounded-bottom-4">
                <a href="{{ route('fixed-assets.index') }}" class="px-4 btn btn-light fw-bold rounded-pill me-2">Batal</a>
                <button type="submit" class="px-5 text-white btn btn-info fw-bold rounded-pill"><i class="bi bi-save me-2"></i> Simpan Perubahan Aset</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
<script>
    $(document).ready(function() {
        $('.select2-user').select2({ theme: 'bootstrap-5' });

        ClassicEditor.create(document.querySelector('#spesifikasi_editor_edit'), {
            toolbar: [ 'heading', '|', 'bold', 'italic', 'bulletedList', 'numberedList', 'blockQuote', '|', 'undo', 'redo' ]
        }).catch(error => { console.error(error); });

        // Tambah Baris Foto
        $('.add-photo-btn').on('click', function() {
            let container = $('#photo-inputs-container');
            container.append(`
            <div class="mb-2 shadow-sm input-group input-group-sm photo-input-row">
                <input type="file" name="photos[]" class="form-control border-primary-subtle single-photo-input" accept="image/*">
                <button type="button" class="btn btn-danger remove-photo-row" title="Hapus baris ini"><i class="bi bi-trash"></i></button>
            </div>`);
        });

        $(document).on('click', '.remove-photo-row', function() {
            $(this).closest('.photo-input-row').remove();
            triggerPhotoPreview();
        });

        $(document).on('change', '.single-photo-input', function() {
            triggerPhotoPreview();
        });

        function triggerPhotoPreview() {
            let fileInputs = $('.single-photo-input');
            let previewDiv = $('#preview-photos');

            previewDiv.empty();
            let hasFiles = false;

            fileInputs.each(function() {
                let files = this.files;
                if (files && files[0] && files[0].type.match('image.*')) {
                    hasFiles = true;
                    let reader = new FileReader();
                    reader.onload = function(e) {
                        previewDiv.append(`
                        <div class="p-1 bg-white border shadow-sm rounded-3 position-relative photo-preview-item">
                            <img src="${e.target.result}" class="rounded" style="width: 70px; height: 70px; object-fit: cover;">
                        </div>`);
                    }
                    reader.readAsDataURL(files[0]);
                }
            });

            if (!hasFiles) {
                previewDiv.html('<span class="text-muted small fst-italic"><i class="bi bi-image me-1"></i> Preview foto baru akan muncul di sini...');
            }
        }
    });

    function toggleAssignee(slug) {
        let assignee = $('#assigneeSelect');
        if (slug === 'in_use') {
            assignee.prop('disabled', false);
            assignee.prop('required', true);
        } else {
            assignee.prop('disabled', true);
            assignee.prop('required', false);
            assignee.val('').trigger('change');
        }
    }
</script>
@endpush
