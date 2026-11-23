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
    select.status-locked {
        background-color: #f1f5f9 !important;
        pointer-events: none;
        font-weight: 700;
        color: #0f172a;
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
            <p class="mb-0 text-muted small">Terbitkan dokumen BAST (Penyerahan), BAPA (Pengembalian), BASP (Serah Terima Perbaikan), atau Perubahan Status / Disposal.</p>
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
                            <option value="RETURN" {{ old('transaction_type') == 'RETURN' ? 'selected' : '' }}>Pengembalian Aset ke Gudang / Tarik Rusak (Terbit BAPA)</option>
                            <option value="REPAIR_SEND" {{ old('transaction_type') == 'REPAIR_SEND' ? 'selected' : '' }}>Kirim Perbaikan ke Service Center (Terbit BASP)</option>
                            <option value="REPAIR_FINISH" {{ old('transaction_type') == 'REPAIR_FINISH' ? 'selected' : '' }}>Selesai Perbaikan / Masuk Gudang (Terbit BAPA-SRV)</option>
                            <option value="STATUS_CHANGE" {{ old('transaction_type') == 'STATUS_CHANGE' ? 'selected' : '' }}>Update Status / Disposal</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="mb-3 col-md-6">
                            <label class="form-label fw-bold small">Tanggal Transaksi <span class="text-danger">*</span></label>
                            <input type="date" name="transaction_date" class="form-control" value="{{ old('transaction_date', date('Y-m-d')) }}" required>
                        </div>
                        <div class="mb-3 col-md-6">
                            <label class="form-label fw-bold small d-flex justify-content-between align-items-center">
                                <span>Status Aset Baru <span class="text-danger">*</span></span>
                                <span id="badge-status-lock" class="badge bg-secondary-subtle text-secondary d-none" style="font-size: 0.65rem;">
                                    <i class="bi bi-lock-fill"></i> Terkunci
                                </span>
                            </label>
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

                    {{-- FIELD KHUSUS RETURN & REPAIR_FINISH --}}
                    <div class="mb-3 d-none" id="box-return-warehouse">
                        <label class="form-label fw-bold small text-danger" id="lbl-warehouse-target">Gudang Tujuan Pengembalian <span class="text-danger">*</span></label>
                        <select name="warehouse_id" id="warehouse_id" class="form-select select2-basic" style="width: 100%;">
                            <option value="">-- Pilih Gudang Penerima --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- FIELD KHUSUS REPAIR_SEND (SERVICE CENTER) --}}
                    <div class="p-3 mb-3 border rounded-3 bg-warning-subtle border-warning d-none" id="box-repair-send">
                        <div class="mb-2">
                            <label class="form-label fw-bold small text-dark">Nama Service Center / Vendor <span class="text-danger">*</span></label>
                            <input type="text" name="service_center_name" id="service_center_name" class="bg-white form-control" value="{{ old('service_center_name') }}" placeholder="Contoh: Apple Authorized Service / Dell Service Center">
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-bold small text-dark">No. RMA / Tanda Terima Servis</label>
                            <input type="text" name="rma_number" id="rma_number" class="bg-white form-control" value="{{ old('rma_number') }}" placeholder="Opsional (bisa diisi jika sudah ada tiket)...">
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold small" id="lbl-notes">Catatan / Keterangan Resmi</label>
                        <textarea name="notes" id="notes_field" class="form-control" rows="3" placeholder="Contoh: Serah terima perlengkapan kerja / Alasan penghapusan atau kerusakan aset...">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- KOLOM KANAN: PILIH DAFTAR ASET --}}
            <div class="col-lg-8">
                <div class="p-4 bg-white border shadow-sm rounded-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <h6 class="pb-2 mb-3 fw-bold text-uppercase text-secondary border-bottom">2. Pilih Unit Aset yang Diproses</h6>

                        {{-- Dropdown 1: Aset Siap Pakai di Gudang (Untuk Handover) --}}
                        <div class="mb-3" id="select-available-wrapper">
                            <label class="form-label fw-bold small">Cari & Pilih Aset Siap Pakai dari Gudang (Bisa pilih lebih dari 1) <span class="text-danger">*</span></label>
                            <select name="asset_ids[]" id="available_asset_ids" class="form-select select2-asset-detail" multiple style="width: 100%;">
                                @foreach($availableAssets as $ast)
                                    @php
                                        $kodeItem   = optional($ast->item)->code ?? '-';
                                        $namaItem   = optional($ast->item)->name ?? '-';
                                        $sn         = $ast->serial_number ?: '-';
                                        $faNo       = $ast->accounting_asset_number ?: '-';
                                        $gudang     = optional($ast->warehouse)->name ?? 'Gudang';
                                        $statusName = optional($ast->status)->name ?? 'Available';
                                        $statusSlug = optional($ast->status)->slug ?? 'available';
                                    @endphp
                                    <option value="{{ $ast->id }}"
                                        data-asset-number="{{ $ast->asset_number }}"
                                        data-fa-number="{{ $faNo }}"
                                        data-item-code="{{ $kodeItem }}"
                                        data-item-name="{{ $namaItem }}"
                                        data-asset-name="{{ $ast->name }}"
                                        data-sn="{{ $sn }}"
                                        data-status-name="{{ $statusName }}"
                                        data-status-slug="{{ $statusSlug }}"
                                        data-location="{{ $gudang }}"
                                        data-location-type="warehouse">
                                        [{{ $ast->asset_number }}] {{ $kodeItem }} - {{ $ast->name }} [{{ $statusName }}]
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Dropdown 2: Aset Dipakai Staf (Untuk Return) --}}
                        <div class="mb-3 d-none" id="select-inuse-wrapper">
                            <label class="form-label fw-bold small text-danger">Cari & Pilih Aset yang Sedang Dipakai Staf (Bisa pilih lebih dari 1) <span class="text-danger">*</span></label>
                            <select id="inuse_asset_ids" class="form-select select2-asset-detail" multiple style="width: 100%;" disabled>
                                @foreach($inUseAssets as $ast)
                                    @php
                                        $kodeItem   = optional($ast->item)->code ?? '-';
                                        $namaItem   = optional($ast->item)->name ?? '-';
                                        $sn         = $ast->serial_number ?: '-';
                                        $faNo       = $ast->accounting_asset_number ?: '-';
                                        $pemegang   = optional($ast->assignee)->name ?? '-';
                                        $statusName = optional($ast->status)->name ?? 'In Use';
                                        $statusSlug = optional($ast->status)->slug ?? 'in_use';
                                    @endphp
                                    <option value="{{ $ast->id }}"
                                        data-asset-number="{{ $ast->asset_number }}"
                                        data-fa-number="{{ $faNo }}"
                                        data-item-code="{{ $kodeItem }}"
                                        data-item-name="{{ $namaItem }}"
                                        data-asset-name="{{ $ast->name }}"
                                        data-sn="{{ $sn }}"
                                        data-status-name="{{ $statusName }}"
                                        data-status-slug="{{ $statusSlug }}"
                                        data-location="{{ $pemegang }}"
                                        data-location-type="user">
                                        [{{ $ast->asset_number }}] {{ $kodeItem }} - {{ $ast->name }} [{{ $statusName }}] — {{ $pemegang }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Dropdown 3: Semua Aset Aktif (Untuk Kirim Service Center & Update Status / Disposal) --}}
                        <div class="mb-3 d-none" id="select-all-active-wrapper">
                            <label class="form-label fw-bold small text-dark" id="lbl-all-active">Cari & Pilih Unit Aset (Menampilkan Status Kondisi & Lokasi) <span class="text-danger">*</span></label>
                            <select id="all_active_asset_ids" class="form-select select2-asset-detail" multiple style="width: 100%;" disabled>
                                @foreach($allActiveAssets as $ast)
                                    @php
                                        $kodeItem   = optional($ast->item)->code ?? '-';
                                        $namaItem   = optional($ast->item)->name ?? '-';
                                        $sn         = $ast->serial_number ?: '-';
                                        $faNo       = $ast->accounting_asset_number ?: '-';
                                        $statusName = optional($ast->status)->name ?? 'Normal';
                                        $statusSlug = optional($ast->status)->slug ?? 'available';
                                        $lokasi     = !empty($ast->assigned_to)
                                            ? (optional($ast->assignee)->name ?? '-')
                                            : (optional($ast->warehouse)->name ?? 'Gudang / Servis');
                                        $locType    = !empty($ast->assigned_to) ? 'user' : 'warehouse';
                                    @endphp
                                    <option value="{{ $ast->id }}"
                                        data-asset-number="{{ $ast->asset_number }}"
                                        data-fa-number="{{ $faNo }}"
                                        data-item-code="{{ $kodeItem }}"
                                        data-item-name="{{ $namaItem }}"
                                        data-asset-name="{{ $ast->name }}"
                                        data-sn="{{ $sn }}"
                                        data-status-name="{{ $statusName }}"
                                        data-status-slug="{{ $statusSlug }}"
                                        data-location="{{ $lokasi }}"
                                        data-location-type="{{ $locType }}">
                                        [{{ $ast->asset_number }}] {{ $kodeItem }} - {{ $ast->name }} [{{ $statusName }}]
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Dropdown 4: Khusus Aset di Service Center / Maintenance (Untuk Selesai Perbaikan / BAPA-SRV) --}}
                        <div class="mb-3 d-none" id="select-repair-wrapper">
                            <label class="form-label fw-bold small text-success">Cari & Pilih Aset yang Sedang di Service Center / Perbaikan <span class="text-danger">*</span></label>
                            <select id="repair_asset_ids" class="form-select select2-asset-detail" multiple style="width: 100%;" disabled>
                                @foreach($repairAssets as $ast)
                                    @php
                                        $kodeItem   = optional($ast->item)->code ?? '-';
                                        $namaItem   = optional($ast->item)->name ?? '-';
                                        $sn         = $ast->serial_number ?: '-';
                                        $faNo       = $ast->accounting_asset_number ?: '-';
                                        $statusName = optional($ast->status)->name ?? 'Maintenance';
                                        $statusSlug = optional($ast->status)->slug ?? 'maintenance';

                                        $scLabel = 'Service Center / Maintenance';
                                        if (str_contains((string) $ast->notes, 'Service Center:')) {
                                            $parts = explode('|', $ast->notes);
                                            $scLabel = trim($parts[0]);
                                        }
                                    @endphp
                                    <option value="{{ $ast->id }}"
                                        data-asset-number="{{ $ast->asset_number }}"
                                        data-fa-number="{{ $faNo }}"
                                        data-item-code="{{ $kodeItem }}"
                                        data-item-name="{{ $namaItem }}"
                                        data-asset-name="{{ $ast->name }}"
                                        data-sn="{{ $sn }}"
                                        data-status-name="{{ $statusName }}"
                                        data-status-slug="{{ $statusSlug }}"
                                        data-location="{{ $scLabel }}"
                                        data-location-type="user">
                                        [{{ $ast->asset_number }}] {{ $kodeItem }} - {{ $ast->name }} [{{ $statusName }}] — {{ $scLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="p-3 border-0 alert alert-info bg-info-subtle rounded-3 small">
                            <i class="bi bi-info-circle-fill me-1 text-info"></i>
                            <strong>Panduan Kondisi & Perbaikan Aset:</strong>
                            <ol class="mt-1 mb-0 ps-3">
                                <li>Tarik unit rusak dari staf ke IT/Gudang via <strong>Pengembalian Aset (BAPA)</strong> dengan status <em>Maintenance / Returned</em>.</li>
                                <li>Kirim unit ke vendor servis via <strong>Kirim Perbaikan ke Service Center (BASP)</strong> untuk mencetak bukti serah terima perbaikan.</li>
                                <li>Saat unit kembali dari servis, proses <strong>Selesai Perbaikan (BAPA-SRV)</strong> (pilih <em>Available</em> jika normal, atau <em>Returned / Maintenance</em> jika tidak bisa diperbaiki lalu lanjutkan ke <strong>Disposal</strong>).</li>
                            </ol>
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

        function customAssetMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (!data.element) return null;

            const el = $(data.element);
            const searchableText = [
                el.data('asset-number'),
                el.data('fa-number'),
                el.data('item-code'),
                el.data('item-name'),
                el.data('asset-name'),
                el.data('sn'),
                el.data('status-name'),
                el.data('location'),
                data.text
            ].join(' ').toLowerCase();

            const terms = params.term.toLowerCase().split(/\s+/);
            return terms.every(term => searchableText.includes(term)) ? data : null;
        }

        function formatAssetResult(state) {
            if (!state.id || !state.element) return state.text;

            const el         = $(state.element);
            const assetNo    = el.data('asset-number') || '-';
            const faNo       = el.data('fa-number') || '-';
            const itemCode   = el.data('item-code') || '-';
            const itemName   = el.data('item-name') || '-';
            const assetName  = el.data('asset-name') || '-';
            const sn         = el.data('sn') || '-';
            const statusName = el.data('status-name') || 'Normal';
            const statusSlug = (el.data('status-slug') || '').toLowerCase();
            const location   = el.data('location') || '-';
            const locType    = el.data('location-type');

            let statusBadgeClass = 'bg-secondary-subtle text-secondary border-secondary-subtle';
            if (statusSlug.includes('available') || statusSlug.includes('ready')) {
                statusBadgeClass = 'bg-success-subtle text-success border-success-subtle';
            } else if (statusSlug.includes('use')) {
                statusBadgeClass = 'bg-primary-subtle text-primary border-primary-subtle';
            } else if (statusSlug.includes('rusak') || statusSlug.includes('broken') || statusSlug.includes('hilang')) {
                statusBadgeClass = 'bg-danger text-white border-danger';
            } else if (statusSlug.includes('maintenance') || statusSlug.includes('repair') || statusSlug.includes('servis')) {
                statusBadgeClass = 'bg-warning text-dark border-warning';
            }

            const locBadge = locType === 'user'
                ? `<span class="border badge bg-light text-dark border-secondary-subtle"><i class="bi bi-person-fill text-primary me-1"></i>${location}</span>`
                : `<span class="border badge bg-light text-dark border-secondary-subtle"><i class="bi bi-shop text-success me-1"></i>${location}</span>`;

            return $(`
                <div class="py-1">
                    <div class="flex-wrap gap-1 mb-1 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-primary font-monospace">${assetNo}</span>
                            <span class="border badge bg-secondary-subtle text-dark font-monospace">FA: ${faNo}</span>
                            <span class="border badge bg-info-subtle text-info-emphasis border-info-subtle font-monospace">${itemCode} — ${itemName}</span>
                        </div>
                        <div class="gap-1 d-flex align-items-center">
                            <span class="border badge ${statusBadgeClass}">${statusName}</span>
                            ${locBadge}
                        </div>
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
            placeholder: 'Ketik Kode Aset / FA / Kode Item / Nama Item / Status / S/N...',
            allowClear: true,
            closeOnSelect: false,
            matcher: customAssetMatcher,
            templateResult: formatAssetResult
        });

        function activateAssetDropdown(targetId) {
            const allIds = ['available_asset_ids', 'inuse_asset_ids', 'all_active_asset_ids', 'repair_asset_ids'];
            const wrappers = {
                'available_asset_ids': '#select-available-wrapper',
                'inuse_asset_ids': '#select-inuse-wrapper',
                'all_active_asset_ids': '#select-all-active-wrapper',
                'repair_asset_ids': '#select-repair-wrapper'
            };

            allIds.forEach(id => {
                if (id === targetId) {
                    $(wrappers[id]).removeClass('d-none');
                    $('#' + id).prop('disabled', false).attr('name', 'asset_ids[]').prop('required', true);
                } else {
                    $(wrappers[id]).addClass('d-none');
                    $('#' + id).prop('disabled', true).removeAttr('name').prop('required', false);
                }
            });
        }

        // Aturan daftar status yang diizinkan dan status default untuk setiap jenis transaksi
        const statusRules = {
            'HANDOVER': {
                allowed: ['in_use'],
                defaultSlug: 'in_use',
                lock: true
            },
            'RETURN': {
                allowed: ['available', 'returned', 'maintenance', 'repair', 'rusak'],
                defaultSlug: 'available',
                lock: false
            },
            'REPAIR_SEND': {
                allowed: ['maintenance', 'repair', 'in_repair', 'service', 'rusak'],
                defaultSlug: 'maintenance',
                lock: true
            },
            'REPAIR_FINISH': {
                allowed: ['available', 'returned', 'maintenance', 'repair', 'rusak'],
                defaultSlug: 'available',
                lock: false
            },
            'STATUS_CHANGE': {
                allowed: ['disposed', 'maintenance', 'repair', 'rusak', 'available', 'returned'],
                defaultSlug: 'disposed',
                lock: false
            }
        };

        function filterAndLockStatuses(type) {
            const rule = statusRules[type] || statusRules['STATUS_CHANGE'];
            const $statusSelect = $('#status_id');
            let firstAllowedVal = null;
            let defaultVal = null;

            $statusSelect.find('option').each(function() {
                const slug = ($(this).data('slug') || '').toLowerCase();
                const isAllowed = rule.allowed.some(a => slug === a || slug.includes(a));

                if (isAllowed) {
                    $(this).prop('disabled', false).removeClass('d-none');
                    if (!firstAllowedVal) firstAllowedVal = $(this).val();
                    if (slug === rule.defaultSlug || slug.includes(rule.defaultSlug)) {
                        defaultVal = $(this).val();
                    }
                } else {
                    $(this).prop('disabled', true).addClass('d-none');
                }
            });

            $statusSelect.val(defaultVal || firstAllowedVal);

            if (rule.lock) {
                $statusSelect.addClass('status-locked');
                $('#badge-status-lock').removeClass('d-none');
            } else {
                $statusSelect.removeClass('status-locked');
                $('#badge-status-lock').addClass('d-none');
            }
        }

        function toggleTransactionForm() {
            const type = $('#transaction_type').val();

            $('#box-handover-user').addClass('d-none');
            $('#to_user_id').prop('required', false);

            $('#box-return-warehouse').addClass('d-none');
            $('#warehouse_id').prop('required', false);

            $('#box-repair-send').addClass('d-none');
            $('#service_center_name').prop('required', false);

            $('#lbl-notes').text('Catatan / Keterangan Resmi');

            // Terapkan filter & penguncian status sesuai jenis transaksi
            filterAndLockStatuses(type);

            if (type === 'HANDOVER') {
                $('#box-handover-user').removeClass('d-none');
                $('#to_user_id').prop('required', true);
                activateAssetDropdown('available_asset_ids');
            } else if (type === 'RETURN') {
                $('#box-return-warehouse').removeClass('d-none');
                $('#lbl-warehouse-target').html('Gudang Tujuan Pengembalian <span class="text-danger">*</span>');
                $('#warehouse_id').prop('required', true);
                activateAssetDropdown('inuse_asset_ids');
            } else if (type === 'REPAIR_SEND') {
                $('#box-repair-send').removeClass('d-none');
                $('#service_center_name').prop('required', true);
                $('#lbl-notes').text('Keluhan Kerusakan / Deskripsi Perbaikan');
                $('#lbl-all-active').html('Cari & Pilih Aset yang Akan Dikirim ke Service Center <span class="text-danger">*</span>');
                activateAssetDropdown('all_active_asset_ids');
            } else if (type === 'REPAIR_FINISH') {
                $('#box-return-warehouse').removeClass('d-none');
                $('#lbl-warehouse-target').html('Gudang Penerima Hasil Perbaikan <span class="text-danger">*</span>');
                $('#warehouse_id').prop('required', true);
                activateAssetDropdown('repair_asset_ids');
            } else {
                $('#lbl-all-active').html('Cari & Pilih Aset yang Akan Diubah Statusnya / Disposal (Perhatikan Badge Status) <span class="text-danger">*</span>');
                activateAssetDropdown('all_active_asset_ids');
            }
        }

        $('#transaction_type').on('change', toggleTransactionForm);
        toggleTransactionForm();
    });
</script>
@endpush
