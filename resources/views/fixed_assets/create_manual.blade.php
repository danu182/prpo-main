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
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-pencil-square me-2 text-primary"></i> Input Aset Manual (Hibah)</h4>
            <div class="mt-1 text-muted small">Registrasi aset yang tidak melalui proses Purchase Order (PO).</div>
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

    <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-primary">
        <form action="{{ route('fixed-assets.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="p-4 card-body">
                <div class="row g-5">
                    <div class="col-md-6 border-end pe-md-5">
                        <h6 class="pb-2 mb-4 border-bottom text-primary fw-bold"><i class="bi bi-box-seam me-2"></i>Informasi Fisik Aset</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Gudang Penerima <span class="text-danger">*</span></label>
                            <select name="warehouse_id" class="shadow-sm form-select" required>
                                <option value="">-- Pilih Gudang Lokasi Aset --</option>
                                @foreach($warehouses as $wh) <option value="{{ $wh->id }}">{{ $wh->name }}</option> @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Pilih Barang (Master) <span class="text-danger">*</span></label>
                            <select name="item_id" class="form-select select2-item-ajax" style="width: 100%;" required>
                                <option value="">-- Ketik Nama / Kode Barang --</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Kategori Penyusutan <span class="text-danger">*</span></label>
                            <select name="asset_category_id" class="shadow-sm form-select border-primary" required>
                                <option value="">-- Pilih Kategori Aset --</option>
                                @foreach($assetCategories as $category) <option value="{{ $category->id }}">{{ $category->name }} (Umur: {{ $category->useful_life_years }} Thn)</option> @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Penamaan Spesifik Aset</label>
                            <input type="text" name="asset_name" class="shadow-sm form-control border-primary" placeholder="Cth: Laptop Core i7 Direksi...">
                            <div class="form-text" style="font-size: 0.7rem;"><i class="bi bi-info-circle"></i> Kosongkan jika ingin menggunakan nama bawaan Master Barang.</div>
                        </div>

                        <div class="row">
                            <div class="mb-3 col-6">
                                <label class="form-label fw-bold small text-muted">Tgl Diterima / Beli <span class="text-danger">*</span></label>
                                <input type="date" name="acquisition_date" class="shadow-sm form-control border-info" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="mb-3 col-6">
                                <label class="form-label fw-bold small text-muted">Jumlah Unit <span class="text-danger">*</span></label>
                                <input type="number" name="quantity" class="shadow-sm form-control border-warning" value="1" min="1" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Spesifikasi Detail / Merek <span class="text-danger">*</span></label>
                            <textarea name="spesifikasi_detail" id="spesifikasi_editor_add" class="shadow-sm form-control" rows="4"></textarea>
                        </div>

                        {{-- 🔥 AREA UPLOAD FOTO FISIK ASET 🔥 --}}
                        <div class="p-3 mt-4 border rounded-3 bg-light border-primary-subtle">
                            <label class="pb-2 mb-3 d-block text-primary fw-bold border-bottom" style="font-size:0.85rem;">
                                <i class="bi bi-images me-1"></i> Upload Foto Fisik Aset
                            </label>

                            <div id="photo-inputs-container" class="gap-2 mb-3 d-flex flex-column">
                                <div class="shadow-sm input-group input-group-sm photo-input-row">
                                    <input type="file" name="photos[]" class="form-control border-primary-subtle single-photo-input" accept="image/*">
                                    <button type="button" class="btn btn-danger remove-photo-row" title="Hapus baris ini"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>

                            <button type="button" class="btn btn-sm btn-light border-primary text-primary w-100 fw-bold add-photo-btn btn-dashed">
                                <i class="bi bi-plus-circle me-1"></i> Tambah File Foto Lainnya
                            </button>

                            <div id="preview-photos" class="flex-wrap gap-2 p-2 mt-3 bg-white border rounded d-flex empty-preview align-items-center justify-content-center" style="min-height: 80px;">
                                <span class="text-muted small fst-italic"><i class="bi bi-image me-1"></i> Preview foto fisik akan muncul di sini...</span>
                            </div>
                        </div>

                    </div>

                    <div class="col-md-6 ps-md-5">
                        <h6 class="pb-2 mb-4 border-bottom text-primary fw-bold"><i class="bi bi-buildings me-2"></i>Status & Administrasi</h6>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Aset Milik PT <span class="text-danger">*</span></label>
                            <select name="company_id" class="shadow-sm form-select" required>
                                <option value="">-- Pilih Pemilik Aset --</option>
                                @foreach($companies as $company) <option value="{{ $company->id }}">{{ $company->name }}</option> @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase">Status <span class="text-danger">*</span></label>
                            {{-- 🔥 ID status_id HARUS ADA DI SINI 🔥 --}}
                            <select name="status_id" id="status_id" class="shadow-sm form-select" required>
                                <option value="">-- Pilih Status Aset --</option>
                                @foreach($statuses as $status)
                                    @if(in_array($status->slug, ['available', 'in_use']))
                                        <option value="{{ $status->id }}">{{ $status->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        {{-- 🔥 BLOK KOLOM USER / PEMEGANG ASET 🔥 --}}
                        <div class="mb-3" id="wrapper-assigned-to" style="display: none;">
                            <label class="form-label fw-bold small text-muted text-uppercase text-primary">Ditugaskan Kepada (User) <span class="text-danger">*</span></label>
                            <select name="assigned_to" id="assigned_to" class="shadow-sm form-select select2-user" style="width: 100%;">
                                <option value="">-- Pilih Karyawan Pemakai --</option>
                                @if(isset($users))
                                    @foreach($users as $user)
                                        {{-- 🔥 Tambahkan Departemen di dalam kurung 🔥 --}}
                                        <option value="{{ $user->id }}">
                                            {{ $user->name }} — {{ optional($user->company)->name ?? 'Tanpa PT' }} ({{ optional($user->department)->name ?? 'Tanpa Dept' }})
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            <div class="form-text" style="font-size: 0.7rem;"><i class="bi bi-info-circle"></i> Wajib diisi karena status aset adalah In Use (Dipakai).</div>
                        </div>
                        {{-- 🔥 END BLOK KOLOM USER 🔥 --}}


                        <div class="row">
                            <div class="mb-3 col-4 pe-1">
                                <label class="form-label fw-bold small text-muted">Mata Uang <span class="text-danger">*</span></label>
                                <select name="currency_id" class="shadow-sm form-select border-success" required>
                                    @foreach($currencies as $currency) <option value="{{ $currency->id }}" {{ $currency->code == 'IDR' ? 'selected' : '' }}>{{ $currency->code }}</option> @endforeach
                                </select>
                            </div>
                            <div class="mb-3 col-8 ps-1">
                                <label class="form-label fw-bold small text-muted">Nilai Wajar / Harga</label>
                                <input type="number" name="purchase_price" class="shadow-sm form-control border-success" placeholder="Estimasi harga hibah..." min="0">
                            </div>
                        </div>

                        <div class="p-3 mt-3 border row bg-light rounded-3">
                            <div class="mb-2 col-12"><span class="mb-2 badge bg-secondary">Hanya Berlaku Jika Qty = 1</span></div>
                            <div class="mb-3 col-md-6 pe-md-3">
                                <label class="form-label fw-bold small text-muted">Serial Number (S/N)</label>
                                <input type="text" name="serial_number" class="form-control" placeholder="Kosongkan jika massal">
                            </div>
                            <div class="mb-3 col-md-6 ps-md-3">
                                <label class="form-label fw-bold small text-muted">Label Akuntansi</label>
                                <input type="text" name="accounting_asset_number" class="form-control" placeholder="FA-XXX...">
                            </div>
                        </div>

                        <div class="mt-4 row">
                            <div class="mb-3 col-md-6 pe-md-3">
                                <label class="form-label fw-bold small text-muted">Catatan Asal Usul / Hibah</label>
                                <textarea name="notes" class="shadow-sm form-control" rows="2" placeholder="Cth: Hibah dari CSR..."></textarea>
                            </div>
                            <div class="mb-3 col-md-6 ps-md-3">
                                <label class="form-label fw-bold small text-muted">Dokumen Pendukung</label>
                                <input type="file" name="supporting_document" class="shadow-sm form-control border-secondary" accept=".pdf,.jpg,.jpeg,.png">
                                <div class="form-text" style="font-size: 0.7rem;"><i class="bi bi-paperclip"></i> Maks 5MB (PDF/JPG/PNG). Lampirkan BAST Hibah / Nota.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-white card-footer border-top text-end rounded-bottom-4">
                <a href="{{ route('fixed-assets.index') }}" class="px-4 btn btn-light fw-bold rounded-pill me-2">Batal</a>
                <button type="submit" class="px-5 btn btn-primary fw-bold rounded-pill"><i class="bi bi-save me-2"></i> Registrasi Aset Sekarang</button>
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
        // Init Select2 untuk Barang
        $('.select2-item-ajax').select2({
            theme: 'bootstrap-5',
            placeholder: '-- Ketik minimal 2 huruf --',
            minimumInputLength: 2,
            ajax: {
                url: "{{ route('fixed-assets.search-items') }}",
                dataType: 'json',
                delay: 250,
                data: function (params) { return { search: params.term }; },
                processResults: function (data) { return { results: data }; },
                cache: true
            }
        });

        // 🔥 LOGIKA BARU: Init Select2 untuk Karyawan 🔥
        $('.select2-user').select2({
            theme: 'bootstrap-5',
            placeholder: '-- Ketik / Pilih Nama Karyawan --'
        });

        // 🔥 LOGIKA BARU: Smart Toggle Tampilkan/Sembunyikan Kolom Karyawan 🔥
        $('#status_id').on('change', function() {
            let statusText = $(this).find('option:selected').text().toLowerCase();

            // Jika nama status mengandung kata "use" atau "pakai"
            if (statusText.includes('use') || statusText.includes('pakai')) {
                $('#wrapper-assigned-to').slideDown(); // Munculkan Animasi
                $('#assigned_to').prop('required', true); // Jadikan Wajib
            } else {
                $('#wrapper-assigned-to').slideUp(); // Sembunyikan Animasi
                $('#assigned_to').val('').trigger('change'); // Kosongkan Pilihan
                $('#assigned_to').prop('required', false); // Batal Wajib
            }
        });

        // Panggil saat pertama kali load (jaga-jaga kalau error validasi dan kembali ke halaman)
        $('#status_id').trigger('change');


        // Init CKEditor
        ClassicEditor.create(document.querySelector('#spesifikasi_editor_add'), {
            toolbar: [ 'heading', '|', 'bold', 'italic', 'bulletedList', 'numberedList', 'blockQuote', '|', 'undo', 'redo' ]
        }).then(editor => {
            editor.model.document.on('change:data', () => { document.querySelector('#spesifikasi_editor_add').value = editor.getData(); });
        });

        // ==========================================
        // Logika Tambah & Hapus Baris Foto
        // ==========================================
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
                previewDiv.html('<span class="text-muted small fst-italic"><i class="bi bi-image me-1"></i> Preview foto fisik akan muncul di sini...</span>');
            }
        }
    });
</script>
@endpush
