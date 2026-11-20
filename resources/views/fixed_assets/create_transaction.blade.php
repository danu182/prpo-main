@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<style>
    .select2-container--bootstrap-5 .select2-results__option {
        padding: 0.65rem 0.85rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .select2-container--bootstrap-5 .select2-results__option--highlighted {
        background-color: #eff6ff !important;
        color: #1e293b !important;
    }
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
        background-color: #f8fafc;
        border: 1px solid #cbd5e1;
        color: #0f172a;
        font-size: 0.82rem;
        padding: 4px 8px;
        border-radius: 8px;
    }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid px-md-4">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <a href="{{ route('fixed-assets.transactions') }}" class="mb-2 border btn btn-sm btn-light rounded-pill fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Transaksi
            </a>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-file-earmark-plus text-primary me-2"></i>Buat Transaksi Aset Baru</h3>
            <p class="mb-0 text-muted small">Terbitkan dokumen BAST (Penyerahan), BAPA (Pengembalian), atau Perubahan Status untuk 1 maupun beberapa aset sekaligus.</p>
        </div>
    </div>

    @if(session('error'))
        <div class="border-0 border-4 shadow-sm alert alert-danger rounded-4 border-start border-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="border-0 border-4 shadow-sm alert alert-danger rounded-4 border-start border-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('fixed-assets.transactions.store') }}" method="POST">
        @csrf
        <div class="row g-4">
            {{-- KOLOM KIRI: HEADER DOKUMEN --}}
            <div class="col-lg-4">
                <div class="p-4 bg-white border shadow-sm rounded-4">
                    <h6 class="pb-2 mb-3 fw-bold text-uppercase text-secondary border-bottom">1. Informasi Dokumen Transaksi</h6>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Jenis Transaksi <span class="text-danger">*</span></label>
                        <select name="transaction_type" id="transaction_type" class="form-select fw-bold" required>
                            <option value="HANDOVER" {{ old('transaction_type') == 'HANDOVER' ? 'selected' : '' }}>Penyerahan Aset ke Staf (Terbit BAST)</option>
                            <option value="RETURN" {{ old('transaction_type') == 'RETURN' ? 'selected' : '' }}>Pengembalian Aset ke Gudang (Terbit BAPA)</option>
                            <option value="STATUS_CHANGE" {{ old('transaction_type') == 'STATUS_CHANGE' ? 'selected' : '' }}>Update Status / Maintenance / Disposal</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="mb-3 col-md-6">
                            <label class="form-label fw-bold small">Tanggal Transaksi <span class="text-danger">*</span></label>
                            <input type="date" name="transaction_date" class="form-control" value="{{ old('transaction_date', date('Y-m-d')) }}" required>
                        </div>
                        <div class="mb-3 col-md-6">
                            <label class="form-label fw-bold small">Status Aset Baru <span class="text-danger">*</span></label>
                            <select name="status_id" id="status_id" class="form-select" required>
                                @foreach($statuses as $st)
                                    <option value="{{ $st->id }}" data-slug="{{ $st->slug }}">{{ $st->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- FIELD KHUSUS HANDOVER --}}
                    <div class="mb-3" id="box-handover-user">
                        <label class="form-label fw-bold small text-primary">Staf Penerima Aset (Pihak Kedua) <span class="text-danger">*</span></label>
                        <select name="to_user_id" id="to_user_id" class="form-select select2-basic" style="width: 100%;">
                            <option value="">-- Pilih Karyawan Penerima --</option>
                            @foreach($users as $usr)
                                <option value="{{ $usr->id }}">{{ $usr->name }} ({{ optional($usr->department)->name ?? '-' }})</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- FIELD KHUSUS RETURN --}}
                    <div class="mb-3 d-none" id="box-return-warehouse">
                        <label class="form-label fw-bold small text-danger">Gudang Tujuan Pengembalian <span class="text-danger">*</span></label>
                        <select name="warehouse_id" id="warehouse_id" class="form-select select2-basic" style="width: 100%;">
                            <option value="">-- Pilih Gudang Penerima --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold small">Catatan / Keterangan Resmi</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Contoh: Serah terima perlengkapan kerja karyawan baru / Pengembalian karena mutasi...">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- KOLOM KANAN: PILIH DAFTAR ASET (PENCARIAN DETAIL & MULTI-ITEM) --}}
            <div class="col-lg-8">
                <div class="p-4 bg-white border shadow-sm rounded-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <h6 class="pb-2 mb-3 fw-bold text-uppercase text-secondary border-bottom">2. Pilih Unit Aset yang Diproses</h6>

                        {{-- Dropdown Aset Tersedia di Gudang (Untuk Handover & Status Change) --}}
                        <div class="mb-3" id="select-available-wrapper">
                            <label class="form-label fw-bold small">Cari & Pilih Aset dari Gudang (Bisa pilih lebih dari 1) <span class="text-danger">*</span></label>
                            <select name="asset_ids[]" id="available_asset_ids" class="form-select select2-asset-detail" multiple style="width: 100%;">
                                @foreach($availableAssets as $ast)
                                    @php
                                        $kodeItem = optional($ast->item)->code ?? '-';
                                        $namaItem = optional($ast->item)->name ?? '-';
                                        $sn       = $ast->serial_number ?: '-';
                                        $faNo     = $ast->accounting_asset_number ?: '-';
                                        $gudang   = optional($ast->warehouse)->name ?? 'Gudang';
                                    @endphp
                                    <option value="{{ $ast->id }}"
                                        data-asset-number="{{ $ast->asset_number }}"
                                        data-fa-number="{{ $faNo }}"
                                        data-item-code="{{ $kodeItem }}"
                                        data-item-name="{{ $namaItem }}"
                                        data-asset-name="{{ $ast->name }}"
                                        data-sn="{{ $sn }}"
                                        data-location="{{ $gudang }}"
                                        data-location-type="warehouse">
                                        [{{ $ast->asset_number }}] {{ $kodeItem }} - {{ $ast->name }} (S/N: {{ $sn }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Dropdown Aset Dipakai Staf (Untuk Return) --}}
                        <div class="mb-3 d-none" id="select-inuse-wrapper">
                            <label class="form-label fw-bold small text-danger">Cari & Pilih Aset yang Sedang Dipakai Staf (Bisa pilih lebih dari 1) <span class="text-danger">*</span></label>
                            <select id="inuse_asset_ids" class="form-select select2-asset-detail" multiple style="width: 100%;" disabled>
                                @foreach($inUseAssets as $ast)
                                    @php
                                        $kodeItem = optional($ast->item)->code ?? '-';
                                        $namaItem = optional($ast->item)->name ?? '-';
                                        $sn       = $ast->serial_number ?: '-';
                                        $faNo     = $ast->accounting_asset_number ?: '-';
                                        $pemegang = optional($ast->assignee)->name ?? '-';
                                    @endphp
                                    <option value="{{ $ast->id }}"
                                        data-asset-number="{{ $ast->asset_number }}"
                                        data-fa-number="{{ $faNo }}"
                                        data-item-code="{{ $kodeItem }}"
                                        data-item-name="{{ $namaItem }}"
                                        data-asset-name="{{ $ast->name }}"
                                        data-sn="{{ $sn }}"
                                        data-location="{{ $pemegang }}"
                                        data-location-type="user">
                                        [{{ $ast->asset_number }}] {{ $kodeItem }} - {{ $ast->name }} (S/N: {{ $sn }}) — Pemegang: {{ $pemegang }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="p-3 border-0 alert alert-info bg-info-subtle rounded-3 small">
                            <i class="bi bi-search me-1 text-info"></i>
                            <strong>Pencarian Multi-Kriteria:</strong> Anda bisa mencari berdasarkan <strong>Kode Aset</strong> (<code>AST/...</code>), <strong>Tag Akuntansi</strong> (<code>FA</code>), <strong>Kode Master Item</strong> (<code>SKU-...</code>), <strong>Nama Item</strong>, <strong>Nama Aset</strong>, maupun <strong>Serial Number (S/N)</strong>.
                        </div>
                    </div>

                    <div class="pt-3 mt-4 text-end border-top">
                        <a href="{{ route('fixed-assets.transactions') }}" class="px-4 btn btn-light fw-bold rounded-pill me-2">Batal</a>
                        <button type="submit" class="px-5 shadow-sm btn btn-primary fw-bold rounded-pill">
                            <i class="bi bi-check2-circle me-1"></i> Simpan & Terbitkan Nomor Dokumen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('.select2-basic').select2({ theme: 'bootstrap-5' });

        // Custom Matcher: Cari ke seluruh atribut (Kode Aset, FA, Kode Item, Nama Item, Nama Aset, S/N, Pemegang/Gudang)
        function customAssetMatcher(params, data) {
            if ($.trim(params.term) === '') {
                return data;
            }
            if (!data.element) {
                return null;
            }

            const el = $(data.element);
            const searchableText = [
                el.data('asset-number'),
                el.data('fa-number'),
                el.data('item-code'),
                el.data('item-name'),
                el.data('asset-name'),
                el.data('sn'),
                el.data('location'),
                data.text
            ].join(' ').toLowerCase();

            // Mendukung pencarian multi-kata (misal: "laptop 1014" atau "sku-lap paolo")
            const terms = params.term.toLowerCase().split(/\s+/);
            const isMatch = terms.every(term => searchableText.includes(term));

            return isMatch ? data : null;
        }

        // Custom Template: Tampilkan kartu informasi lengkap di dalam list dropdown
        function formatAssetResult(state) {
            if (!state.id || !state.element) {
                return state.text;
            }

            const el        = $(state.element);
            const assetNo   = el.data('asset-number') || '-';
            const faNo      = el.data('fa-number') || '-';
            const itemCode  = el.data('item-code') || '-';
            const itemName  = el.data('item-name') || '-';
            const assetName = el.data('asset-name') || '-';
            const sn        = el.data('sn') || '-';
            const location  = el.data('location') || '-';
            const locType   = el.data('location-type');

            const locBadge = locType === 'user'
                ? `<span class="border badge bg-warning-subtle text-warning-emphasis border-warning-subtle"><i class="bi bi-person-fill me-1"></i>${location}</span>`
                : `<span class="border badge bg-success-subtle text-success border-success-subtle"><i class="bi bi-shop me-1"></i>${location}</span>`;

            return $(`
                <div class="py-1">
                    <div class="flex-wrap gap-1 mb-1 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-primary font-monospace">${assetNo}</span>
                            <span class="border badge bg-secondary-subtle text-dark font-monospace">FA: ${faNo}</span>
                            <span class="border badge bg-info-subtle text-info-emphasis border-info-subtle font-monospace">${itemCode} — ${itemName}</span>
                        </div>
                        <div>${locBadge}</div>
                    </div>
                    <div class="fw-bold text-dark" style="font-size: 0.88rem;">${assetName}</div>
                    <div class="text-muted font-monospace" style="font-size: 0.78rem;">
                        <i class="bi bi-upc-scan me-1"></i>Serial Number (S/N): <strong class="text-dark">${sn}</strong>
                    </div>
                </div>
            `);
        }

        $('.select2-asset-detail').select2({
            theme: 'bootstrap-5',
            placeholder: 'Ketik Kode Aset / FA / Kode Item / Nama Item / Nama Aset / S/N...',
            allowClear: true,
            closeOnSelect: false,
            matcher: customAssetMatcher,
            templateResult: formatAssetResult
        });

        function toggleTransactionForm() {
            const type = $('#transaction_type').val();

            if (type === 'HANDOVER') {
                $('#box-handover-user').removeClass('d-none');
                $('#to_user_id').prop('required', true);
                $('#box-return-warehouse').addClass('d-none');
                $('#warehouse_id').prop('required', false);

                $('#select-available-wrapper').removeClass('d-none');
                $('#available_asset_ids').prop('disabled', false).attr('name', 'asset_ids[]').prop('required', true);
                $('#select-inuse-wrapper').addClass('d-none');
                $('#inuse_asset_ids').prop('disabled', true).removeAttr('name').prop('required', false);

                $('#status_id option').each(function() {
                    if ($(this).data('slug') === 'in_use') $('#status_id').val($(this).val());
                });
            } else if (type === 'RETURN') {
                $('#box-handover-user').addClass('d-none');
                $('#to_user_id').prop('required', false);
                $('#box-return-warehouse').removeClass('d-none');
                $('#warehouse_id').prop('required', true);

                $('#select-available-wrapper').addClass('d-none');
                $('#available_asset_ids').prop('disabled', true).removeAttr('name').prop('required', false);
                $('#select-inuse-wrapper').removeClass('d-none');
                $('#inuse_asset_ids').prop('disabled', false).attr('name', 'asset_ids[]').prop('required', true);

                $('#status_id option').each(function() {
                    if ($(this).data('slug') === 'available' || $(this).data('slug') === 'returned') {
                        $('#status_id').val($(this).val());
                        return false;
                    }
                });
            } else {
                $('#box-handover-user').addClass('d-none');
                $('#to_user_id').prop('required', false);
                $('#box-return-warehouse').addClass('d-none');
                $('#warehouse_id').prop('required', false);

                $('#select-available-wrapper').removeClass('d-none');
                $('#available_asset_ids').prop('disabled', false).attr('name', 'asset_ids[]').prop('required', true);
                $('#select-inuse-wrapper').addClass('d-none');
                $('#inuse_asset_ids').prop('disabled', true).removeAttr('name').prop('required', false);
            }
        }

        $('#transaction_type').on('change', toggleTransactionForm);
        toggleTransactionForm();
    });
</script>
@endpush