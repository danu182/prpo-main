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

    .btn-delete-existing {
        position: absolute;
        top: -8px;
        right: -8px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        z-index: 20;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        transition: transform 0.2s;
    }
    .btn-delete-existing:hover { transform: scale(1.2); }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid text-dark">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-pencil-square me-2 text-info"></i> Update Data Aset: {{ $asset->asset_number }}</h4>
            <div class="mt-1 text-muted small">Perbarui nama varian, nama akuntansi, spesifikasi, rincian upgrade multi-baris, status, dan foto fisik.</div>
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
        <form action="{{ route('fixed-assets.update', $asset->id) }}" method="POST" enctype="multipart/form-data" id="formEditAsset">
            @csrf
            @method('PUT')
            <div class="p-4 card-body">
                <div class="p-3 mb-4 text-center border bg-light rounded-4">
                    <h5 class="mb-1 fw-bold text-dark">{{ $asset->name ?? optional($asset->item)->name }}</h5>
                    <div class="flex-wrap gap-1 d-flex justify-content-center align-items-center">
                        <span class="px-3 py-1 badge bg-primary">No. Aset: {{ $asset->asset_number }}</span>
                        @if($asset->item_alias_name)
                            <span class="px-3 py-1 border badge bg-info-subtle text-info-emphasis border-info-subtle">Varian: {{ $asset->item_alias_name }}</span>
                        @endif
                        @if($asset->goodsReceipt)
                            <span class="px-3 py-1 badge bg-info text-dark">Ref GR: {{ $asset->goodsReceipt->gr_number }}</span>
                        @else
                            <span class="px-3 py-1 badge bg-secondary">Input Manual / Hibah / Import</span>
                        @endif
                    </div>
                </div>

                <div class="row g-5">
                    {{-- KOLOM KIRI: PENAMAAN 3 LAPIS, SPESIFIKASI & FOTO --}}
                    <div class="col-md-6 border-end pe-md-5">
                        <h6 class="pb-2 mb-4 border-bottom text-primary fw-bold"><i class="bi bi-box-seam me-2"></i>Detail Fisik & Penamaan Aset</h6>

                        {{-- LAPIS 1 & 2: REFERENSI MASTER ITEM & NAMA ALTERNATIF / VARIAN --}}
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-dark">
                                Nama Alternatif / Varian Barang (Opsional)
                                <span class="border badge bg-info-subtle text-info-emphasis border-info-subtle ms-1" style="font-size: 0.68rem;">Auto-Simpan Kamus Varian</span>
                            </label>
                            <input type="text"
                                   name="item_alias_name"
                                   id="edit_item_alias_name"
                                   list="edit_alias_list"
                                   class="shadow-sm form-control border-info fw-semibold text-dark"
                                   value="{{ old('item_alias_name', $asset->item_alias_name) }}"
                                   placeholder="Cth: Mini PC, Komputer Rakitan, Komputer All in One...">
                            <datalist id="edit_alias_list">
                                @foreach(optional($asset->item)->aliases ?? [] as $al)
                                    <option value="{{ $al->alias_name }}"></option>
                                @endforeach
                            </datalist>
                            <div class="form-text" style="font-size: 0.72rem;">
                                <i class="bi bi-lightbulb-fill text-warning me-1"></i>Pilih varian dari saran kamus item ini atau ketik nama varian baru.
                            </div>
                        </div>

                        {{-- LAPIS 3: NAMA ASET AKUNTANSI --}}
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-primary">Nama Aset Akuntansi (Sesuai Buku Besar / Invoice) <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="shadow-sm form-control border-primary fw-bold text-primary" value="{{ old('name', $asset->name) }}" required>

                            <div class="p-2 mt-2 border rounded bg-light" style="font-size: 0.75rem;">
                                <div class="mb-1 text-secondary">
                                    <i class="bi bi-box me-1"></i> Nama Master Sistem: <strong class="text-dark">[{{ optional($asset->item)->code }}] {{ optional($asset->item)->name }}</strong>
                                </div>

                                @if($poItemName)
                                    <div class="text-info">
                                        <i class="bi bi-cart-check me-1"></i> Nama di Dokumen PO: <strong class="text-primary">{{ $poItemName }}</strong>
                                    </div>
                                @else
                                    <div class="text-muted fst-italic">
                                        <i class="bi bi-info-circle me-1"></i> Aset ini tidak terikat dengan dokumen PO (Input Manual / Hibah / Import).
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="row">
                            <div class="mb-3 col-md-6">
                                <label class="form-label fw-bold small text-muted">Serial Number (S/N) Fisik</label>
                                <input type="text" name="serial_number" class="shadow-sm form-control" value="{{ old('serial_number', $asset->serial_number) }}">
                            </div>

                            <div class="mb-3 col-md-6">
                                <label class="form-label fw-bold small text-muted">No. Aset (Label Akuntansi / FA)</label>
                                <input type="text" name="accounting_asset_number" class="shadow-sm form-control border-info" value="{{ old('accounting_asset_number', $asset->accounting_asset_number) }}">
                            </div>
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
                                <div class="mb-3" id="existing-photos-container">
                                    <div class="mb-2 small text-muted">Foto Saat Ini (Klik X untuk hapus):</div>
                                    <div class="flex-wrap gap-3 pt-2 d-flex pe-2">
                                        @foreach($asset->photos as $photo)
                                            @php
                                                $extension = strtolower(pathinfo($photo->file_path, PATHINFO_EXTENSION));
                                                $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif']);
                                            @endphp

                                            <div class="p-1 bg-white border rounded shadow-sm position-relative existing-photo-item" id="photo-{{ $photo->id }}" style="width: 78px; height: 78px;">
                                                @if($isImage)
                                                    <a href="{{ asset('storage/' . $photo->file_path) }}" target="_blank">
                                                        <img src="{{ asset('storage/' . $photo->file_path) }}" class="rounded" style="width: 68px; height: 68px; object-fit: cover;">
                                                    </a>
                                                @else
                                                    <a href="{{ asset('storage/' . $photo->file_path) }}" target="_blank" class="text-decoration-none">
                                                        <div class="rounded bg-light d-flex flex-column align-items-center justify-content-center" style="width: 68px; height: 68px;">
                                                            @if($extension == 'pdf')
                                                                <i class="bi bi-file-earmark-pdf-fill text-danger fs-3"></i>
                                                            @elseif(in_array($extension, ['doc', 'docx']))
                                                                <i class="bi bi-file-earmark-word-fill text-primary fs-3"></i>
                                                            @elseif(in_array($extension, ['xls', 'xlsx']))
                                                                <i class="bi bi-file-earmark-excel-fill text-success fs-3"></i>
                                                            @else
                                                                <i class="bi bi-file-earmark-zip-fill text-secondary fs-3"></i>
                                                            @endif
                                                        </div>
                                                    </a>
                                                @endif

                                                <button type="button" class="btn btn-danger btn-delete-existing remove-existing-photo" data-photo-id="{{ $photo->id }}" title="Hapus Lampiran Ini">
                                                    <i class="bi bi-x"></i>
                                                </button>
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

                    {{-- KOLOM KANAN: NILAI, PENYUSUTAN, UPGRADE MULTI-BARIS & STATUS --}}
                    <div class="col-md-6 ps-md-5">
                        <h6 class="pb-2 mb-4 border-bottom text-primary fw-bold"><i class="bi bi-cash-coin me-2"></i>Nilai & Penyusutan</h6>

                        <div class="p-3 mb-4 border bg-danger-subtle border-danger-subtle rounded-3">
                            <label class="form-label fw-bold small text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Tanggal Perolehan (Acquisition Date) <span class="text-danger">*</span></label>
                            <input type="date" name="acquisition_date" class="shadow-sm form-control border-danger fw-bold" value="{{ old('acquisition_date', $asset->acquisition_date) }}" required>
                            <div class="mt-2 form-text text-dark" style="font-size: 0.75rem; line-height: 1.3;">
                                <strong>PENTING:</strong> Tanggal ini adalah patokan argometer penyusutan dimulai.
                                <br>• <strong>Aset Import Excel:</strong> Biarkan sesuai tanggal asli pembelian di masa lalu.
                                <br>• <strong>Mengubah tanggal ini</strong> akan me-reset total dan merubah secara masif hitungan <strong>Nilai Buku Saat Ini</strong>!
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Mata Uang & Harga Beli Awal <span class="text-danger">*</span></label>
                            <div class="shadow-sm input-group">
                                <select name="currency_id" class="input-group-text bg-light border-success fw-bold text-dark" style="cursor: pointer;" required>
                                    @foreach($currencies as $currency)
                                        <option value="{{ $currency->id }}" {{ $asset->currency_id == $currency->id ? 'selected' : '' }}>{{ $currency->code }}</option>
                                    @endforeach
                                </select>

                                <input type="text" id="display_purchase_price" class="form-control border-success fw-bold text-dark"
                                       value="{{ number_format(old('purchase_price', $asset->purchase_price), 0, ',', '.') }}" placeholder="0">

                                <input type="hidden" name="purchase_price" id="real_purchase_price" value="{{ old('purchase_price', $asset->purchase_price) }}">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted">Kategori Penyusutan <span class="text-danger">*</span></label>
                            <select name="asset_category_id" class="shadow-sm form-select" required>
                                <option value="">-- Pilih Kategori --</option>
                                @foreach($assetCategories as $category)
                                    <option value="{{ $category->id }}" {{ old('asset_category_id', $asset->asset_category_id) == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }} ({{ $category->useful_life_years }} Thn)
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- 🔥 TABEL DINAMIS MULTI-BARIS KOMPONEN UPGRADE 🔥 --}}
                        <div class="p-3 mb-4 border rounded-3 bg-warning-subtle border-warning">
                            <input type="hidden" name="upgrades_submitted" value="1">
                            <div class="pb-2 mb-2 d-flex justify-content-between align-items-center border-bottom border-warning">
                                <span class="fw-bold text-dark" style="font-size: 0.85rem;">
                                    <i class="bi bi-cpu-fill text-warning-emphasis me-1"></i> Rincian Upgrade Komponen (Bisa Lebih dari 1 Baris)
                                </span>
                                <button type="button" class="px-3 btn btn-sm btn-warning fw-bold rounded-pill" id="btnAddUpgradeRow">
                                    <i class="bi bi-plus-circle me-1"></i> Tambah Komponen
                                </button>
                            </div>
                            <div class="mb-2 text-muted" style="font-size: 0.72rem;">
                                Catat komponen upgrade (RAM, SSD, LCD Tape, Enclosure, dll) beserta biayanya masing-masing.
                            </div>

                            <div class="bg-white border table-responsive rounded-3">
                                <table class="table mb-0 align-middle table-sm table-bordered" id="upgradesTable">
                                    <thead class="table-light small text-muted">
                                        <tr>
                                            <th style="width: 38%;">Komponen Upgrade</th>
                                            <th style="width: 24%;">Biaya</th>
                                            <th style="width: 28%;">Tgl / Pelaksana</th>
                                            <th style="width: 10%;" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="upgradesContainer">
                                        @foreach($asset->upgrades ?? [] as $uIdx => $upg)
                                            <tr class="upgrade-row">
                                                <td>
                                                    <input type="text" name="upgrades[{{ $uIdx }}][component_name]" class="form-control form-control-sm" value="{{ $upg->component_name }}" placeholder="Nama komponen...">
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm text-end upgrade-cost-display" value="{{ $upg->cost > 0 ? number_format($upg->cost, 0, ',', '.') : '' }}" placeholder="0">
                                                    <input type="hidden" name="upgrades[{{ $uIdx }}][cost]" class="upgrade-cost-real" value="{{ (float) $upg->cost }}">
                                                </td>
                                                <td>
                                                    <input type="date" name="upgrades[{{ $uIdx }}][upgrade_date]" class="mb-1 form-control form-control-sm" value="{{ $upg->upgrade_date ? $upg->upgrade_date->format('Y-m-d') : '' }}">
                                                    <input type="text" name="upgrades[{{ $uIdx }}][vendor]" class="form-control form-control-sm" value="{{ $upg->vendor_or_executor }}" placeholder="Vendor / IT">
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-upgrade" title="Hapus Baris">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="table-light small fw-bold">
                                        <tr>
                                            <td class="text-end">Total Biaya Upgrade:</td>
                                            <td colspan="3" class="text-primary" id="totalUpgradeDisplay">
                                                {{ number_format(($asset->upgrades ?? collect())->sum('cost'), 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
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
                            <select name="status_id" id="status_id" class="shadow-sm form-select" required>
                                <option value="">-- Pilih Status Aset --</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status->id }}" data-slug="{{ $status->slug }}" {{ $asset->status_id == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3" id="wrapper-assigned-to" style="display: {{ optional($asset->status)->slug === 'in_use' ? 'block' : 'none' }};">
                            <label class="form-label fw-bold small text-muted text-uppercase text-primary">Ditugaskan Kepada (User) <span class="text-danger">*</span></label>
                            <select name="assigned_to" id="assigned_to" class="shadow-sm form-select select2-user" style="width: 100%;" {{ optional($asset->status)->slug === 'in_use' ? 'required' : '' }}>
                                <option value="">-- Pilih Karyawan Pemakai --</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ $asset->assigned_to == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }} — {{ optional($user->company)->name ?? 'Tanpa PT' }} ({{ optional($user->department)->name ?? 'Tanpa Dept' }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text" style="font-size: 0.7rem;"><i class="bi bi-info-circle"></i> Wajib diisi karena status aset adalah In Use (Dipakai).</div>
                        </div>

                        <div class="p-3 mb-3 border bg-danger-subtle border-danger-subtle rounded-3" id="wrapper-reason" style="display: none;">
                            <label class="form-label fw-bold small text-danger text-uppercase">Alasan Resmi Perubahan Status <span class="text-danger">*</span></label>
                            <textarea name="status_change_reason" id="status_change_reason" class="shadow-sm form-control border-danger" rows="2" placeholder="Wajib diisi! Sebutkan alasan detail kenapa aset di-disposed atau diservis..."></textarea>
                            <div class="mt-2 form-text text-danger" style="font-size: 0.7rem;">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> Alasan ini akan dicetak abadi di dalam dokumen Audit / BAPP.
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-bold small text-muted">Catatan / Remarks</label>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        $('.select2-user').select2({ theme: 'bootstrap-5' });

        // Smart Toggle Status
        $('#status_id').on('change', function() {
            let slug = $(this).find('option:selected').data('slug');

            if (slug === 'in_use') {
                $('#wrapper-assigned-to').slideDown();
                $('#assigned_to').prop('required', true);
            } else {
                $('#wrapper-assigned-to').slideUp();
                $('#assigned_to').val('').trigger('change');
                $('#assigned_to').prop('required', false);
            }

            if (slug === 'disposed' || slug === 'maintenance' || slug === 'returned') {
                $('#wrapper-reason').slideDown();
                $('#status_change_reason').prop('required', true);
            } else {
                $('#wrapper-reason').slideUp();
                $('#status_change_reason').val('');
                $('#status_change_reason').prop('required', false);
            }
        });

        ClassicEditor.create(document.querySelector('#spesifikasi_editor_edit'), {
            toolbar: [ 'heading', '|', 'bold', 'italic', 'bulletedList', 'numberedList', 'blockQuote', '|', 'undo', 'redo' ]
        }).catch(error => { console.error(error); });

        // ==========================================
        // Logika Tambah & Hapus Baris Upgrade Multi-Baris
        // ==========================================
        let upgradeRowIdx = {{ ($asset->upgrades ?? collect())->count() + 100 }};

        function addUpgradeRow(comp = '', cost = '', date = '', vendor = '') {
            let idx = upgradeRowIdx++;
            let defaultDate = date || "{{ date('Y-m-d') }}";
            let formattedCost = cost ? parseInt(cost, 10).toLocaleString('id-ID') : '';

            let rowHtml = `
                <tr class="upgrade-row">
                    <td>
                        <input type="text" name="upgrades[${idx}][component_name]" class="form-control form-control-sm" value="${comp}" placeholder="Cth: RAM DDR3 16GB / SSD 512GB">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm text-end upgrade-cost-display" value="${formattedCost}" placeholder="0">
                        <input type="hidden" name="upgrades[${idx}][cost]" class="upgrade-cost-real" value="${cost || 0}">
                    </td>
                    <td>
                        <input type="date" name="upgrades[${idx}][upgrade_date]" class="mb-1 form-control form-control-sm" value="${defaultDate}">
                        <input type="text" name="upgrades[${idx}][vendor]" class="form-control form-control-sm" value="${vendor}" placeholder="Vendor / IT">
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-upgrade" title="Hapus Baris">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            $('#upgradesContainer').append(rowHtml);
            recalcTotalUpgrade();
        }

        $('#btnAddUpgradeRow').on('click', function () {
            addUpgradeRow();
        });

        $(document).on('click', '.btn-remove-upgrade', function () {
            $(this).closest('tr').remove();
            recalcTotalUpgrade();
        });

        $(document).on('input', '.upgrade-cost-display', function () {
            let raw = $(this).val().replace(/[^0-9]/g, '');
            let tr = $(this).closest('tr');
            tr.find('.upgrade-cost-real').val(raw || 0);
            $(this).val(raw ? parseInt(raw, 10).toLocaleString('id-ID') : '');
            recalcTotalUpgrade();
        });

        function recalcTotalUpgrade() {
            let total = 0;
            $('.upgrade-cost-real').each(function () {
                total += parseFloat($(this).val()) || 0;
            });
            $('#totalUpgradeDisplay').text(total.toLocaleString('id-ID'));
        }

        // LOGIKA HAPUS FOTO LAMA
        $('.remove-existing-photo').on('click', function(e) {
            e.preventDefault();
            let photoId = $(this).data('photo-id');
            let photoContainer = $('#photo-' + photoId);

            Swal.fire({
                title: 'Hapus foto ini?',
                text: "Foto akan ditandai untuk dihapus saat Anda klik Simpan Perubahan.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash"></i> Ya, Hapus!',
                cancelButtonText: 'Batal',
                customClass: { popup: 'rounded-4 shadow-lg border-0' }
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#formEditAsset').append('<input type="hidden" name="delete_photos[]" value="' + photoId + '">');
                    photoContainer.fadeOut(300, function() {
                        $(this).remove();
                        if($('.existing-photo-item').length === 0) {
                            $('#existing-photos-container').fadeOut();
                        }
                    });
                }
            });
        });

        // Tambah Baris Foto Baru
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
                previewDiv.html('<span class="text-muted small fst-italic"><i class="bi bi-image me-1"></i> Preview foto baru akan muncul di sini...</span>');
            }
        }

        // Format Ribuan Harga Beli
        let displayPrice = $('#display_purchase_price');
        let realPrice = $('#real_purchase_price');

        displayPrice.on('keyup', function() {
            let rawValue = $(this).val().replace(/[^0-9]/g, '');
            realPrice.val(rawValue);
            if(rawValue) {
                $(this).val(parseInt(rawValue, 10).toLocaleString('id-ID'));
            } else {
                $(this).val('');
            }
        });
    });
</script>
@endpush
