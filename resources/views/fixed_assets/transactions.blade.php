@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<style>
    /* 🔥 KUSTOMISASI TABEL SAAS MODERN 🔥 */
    .card-table-wrapper {
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        background: #fff;
    }
    .table-modern { margin-bottom: 0; }
    .table-modern thead th {
        background-color: #f8fafc;
        color: #475569;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1.2rem 1rem;
        border-bottom: 2px solid #e2e8f0;
        font-weight: 800;
        border-top: none;
    }
    .table-modern tbody td {
        padding: 1.2rem 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 0.85rem;
    }
    .table-modern tbody tr { transition: all 0.2s ease; background-color: #ffffff; }
    .table-modern tbody tr:hover { background-color: #f8fafc !important; }

    /* Info Badge Kategori */
    .badge-category { font-size: 0.65rem; letter-spacing: 0.5px; padding: 0.35rem 0.65rem; border-radius: 6px; }

    /* Custom Input Search */
    .search-input { border-radius: 20px 0 0 20px !important; padding-left: 1.2rem; border-color: #e2e8f0; }
    .search-btn { border-radius: 0 20px 20px 0 !important; padding-right: 1.5rem; }

    /* Select2 Kustomisasi dalam Modal */
    .select2-container .select2-selection--single { height: 40px !important; border: 1px solid #cbd5e1 !important; border-radius: 8px !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px !important; color: #334155 !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px !important; }
</style>
@endpush

@section('content')
{{-- 🔥 FULL WIDTH CONTAINER 🔥 --}}
<div class="pb-5 container-fluid px-md-4">

    {{-- ALERT PESAN SUKSES / ERROR --}}
    @if(session('success'))
        <div class="border-0 border-4 shadow-sm alert alert-success alert-dismissible fade show rounded-4 border-start border-success" role="alert">
            <i class="bi bi-check-circle-fill me-2 text-success"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="border-0 border-4 shadow-sm alert alert-danger alert-dismissible fade show rounded-4 border-start border-danger" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- 1. HEADER & PENCARIAN --}}
    <div class="mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-arrow-left-right text-danger me-2"></i>Transaksi & Pengembalian Aset</h3>
            <p class="mt-1 mb-0 text-muted small">Proses penyerahan aset ke staf dan pengembalian aset ke gudang.</p>
        </div>

        <div class="mt-3 mt-md-0">
            <form action="{{ route('fixed-assets.transactions') }}" method="GET" style="min-width: 350px;">
                <div class="shadow-sm input-group rounded-pill">
                    <input type="text" name="search" class="bg-white form-control search-input" placeholder="Cari aset / S/N / nama staf..." value="{{ request('search') }}">
                    <button class="px-4 btn btn-dark search-btn fw-bold" type="submit"><i class="bi bi-search me-1"></i> Cari</button>
                    @if(request('search'))
                        <a href="{{ route('fixed-assets.transactions') }}" class="btn btn-light border-top border-bottom text-danger fw-bold" title="Reset Pencarian"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- 2. TABEL DATA TRANSAKSI SAKTI --}}
    <div class="mb-4 border-4 card-table-wrapper border-top border-danger">
        <div class="p-0 table-responsive">
            <table class="table align-middle table-modern text-nowrap">
                <thead>
                    <tr>
                        <th class="text-center ps-3" width="5%">No</th>
                        <th width="15%">Identitas & Kode Aset</th>
                        <th width="12%">Kategori Aset</th>
                        <th width="20%">Nama Barang & S/N</th>
                        <th width="15%">Lokasi / Pemegang</th>
                        <th width="10%">Status Saat Ini</th>
                        <th width="23%" class="text-end pe-4">Aksi Transaksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $index => $asset)
                        @php
                            $subCategoryName = optional(optional($asset->item)->category)->name ?? '';
                            $subCategoryCode = optional(optional($asset->item)->category)->code ?? '';

                            if(empty($subCategoryName)) {
                                $namaBarangLower = strtolower($asset->name);
                                if(str_contains($namaBarangLower, 'laptop') || str_contains($namaBarangLower, 'macbook')) {
                                    $subCategoryName = 'Laptops'; $subCategoryCode = 'LAP';
                                } elseif(str_contains($namaBarangLower, 'pc') || str_contains($namaBarangLower, 'desktop')) {
                                    $subCategoryName = 'Elektronik & IT'; $subCategoryCode = 'ELK';
                                } elseif(str_contains($namaBarangLower, 'phone') || str_contains($namaBarangLower, 'hp')) {
                                    $subCategoryName = 'Handphone'; $subCategoryCode = 'HPN';
                                } else {
                                    $subCategoryName = 'Fixed Asset'; $subCategoryCode = 'AST';
                                }
                            }
                        @endphp
                        <tr>
                            <td class="text-center ps-3 text-muted fw-bold">{{ $assets->firstItem() + $index }}</td>

                            {{-- KOLOM 1: IDENTITAS & KODE --}}
                            <td>
                                <div class="mb-1 fw-bold text-primary fs-6">{{ $asset->asset_number ?? 'Belum Ada Kode' }}</div>
                                <div class="text-secondary font-monospace" style="font-size: 0.72rem;"><span class="fw-bold text-dark">FA:</span> {{ $asset->accounting_asset_number ?? '-' }}</div>
                            </td>

                            {{-- KOLOM 2: KATEGORI --}}
                            <td>
                                <span class="mb-1 border badge bg-primary-subtle text-primary border-primary-subtle badge-category d-inline-block">
                                    {{ $subCategoryName }}
                                </span>
                                <div class="text-muted font-monospace" style="font-size: 0.7rem;"><i class="bi bi-tag"></i> {{ $subCategoryCode }}</div>
                            </td>

                            {{-- KOLOM 3: NAMA & S/N --}}
                            <td>
                                <div class="fw-bold text-dark text-wrap" style="max-width: 250px;">
                                    {{ $asset->name ?? optional($asset->item)->name ?? 'Nama Tidak Diketahui' }}
                                </div>
                                <div class="mt-1 text-secondary font-monospace" style="font-size: 0.72rem;">
                                    <span class="fw-bold text-dark">S/N:</span> <span class="px-1 border rounded bg-light">{{ $asset->serial_number ?? '-' }}</span>
                                </div>
                            </td>

                            {{-- KOLOM 4: GUDANG ATAU PEMEGANG --}}
                            <td>
                                @if(empty($asset->assigned_to))
                                    <div class="fw-bold text-warning text-wrap" style="max-width: 200px;">
                                        <i class="bi bi-shop me-1"></i> {{ Str::limit(optional($asset->warehouse)->name ?? 'Gudang Belum Di-set', 20) }}
                                    </div>
                                    <div class="mt-1 text-muted" style="font-size: 0.75rem;">(Tersedia di Gudang)</div>
                                @else
                                    <div class="fw-bold text-primary text-wrap" style="max-width: 200px;">
                                        <i class="bi bi-person-badge me-1"></i> {{ Str::limit(optional($asset->assignee)->name ?? 'Unknown User', 20) }}
                                    </div>
                                    <div class="mt-1 text-dark" style="font-size: 0.75rem;">
                                        <i class="bi bi-building-gear text-secondary me-1"></i> {{ Str::limit(optional(optional($asset->assignee)->department)->name ?? optional($asset->department)->name ?? '-', 20) }}
                                    </div>
                                @endif
                            </td>

                            {{-- KOLOM 5: STATUS ASET --}}
                            {{-- KOLOM 5: STATUS ASET --}}
                            <td>
                                @php
                                    $statusName = optional($asset->status)->name ?? 'Normal';
                                    $statusSlug = optional($asset->status)->slug;

                                    // 🔥 Logika Murni Menggunakan SLUG 🔥
                                    $isDisposedOrVoid = in_array($statusSlug, ['disposed', 'void', 'batal', 'canceled', 'cancelled']);

                                    if($statusSlug === 'in_use') {
                                        $badgeClass = 'bg-success-subtle text-success border border-success-subtle';
                                    } elseif($isDisposedOrVoid) {
                                        $badgeClass = 'bg-danger text-white shadow-sm';
                                    } elseif($statusSlug === 'available' || $statusSlug === 'returned') {
                                        $badgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                                    } else {
                                        $badgeClass = 'bg-secondary-subtle text-secondary'; // Untuk Maintenance, dll
                                    }
                                @endphp
                                <span class="badge {{ $badgeClass }} px-2 py-1" style="font-size: 0.7rem;">
                                    {{ $statusName }}
                                </span>
                            </td>

                            {{-- KOLOM 6: AKSI TRANSAKSI --}}
                            <td class="text-end pe-4">
                                <div class="gap-2 d-flex justify-content-end align-items-center">
                                    {{-- 🔥 LOGIKA ANTI-DISPOSED DITERAPKAN DI SINI 🔥 --}}
                                    @if(!$isDisposedOrVoid)

                                        @if(!empty($asset->assigned_to))
                                            {{-- Jika sedang dipakai: Bisa Retur & Cetak BAST --}}
                                            <a href="{{ route('fixed-assets.bast', $asset->id) }}" target="_blank" class="shadow-sm btn btn-sm btn-dark fw-bold rounded-pill" title="Cetak BAST">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                            <button type="button" class="px-3 shadow-sm btn btn-sm btn-danger fw-bold rounded-pill" data-bs-toggle="modal" data-bs-target="#returnModal{{ $asset->id }}">
                                                <i class="bi bi-arrow-return-left me-1"></i> Retur Aset
                                            </button>
                                        @else
                                            {{-- Jika di gudang: Bisa Serahkan & Cetak BAPA --}}
                                            <a href="{{ route('fixed-assets.bapa', $asset->id) }}" target="_blank" class="shadow-sm btn btn-sm btn-outline-dark fw-bold rounded-pill" title="Cetak BAPA">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                            <button type="button" class="px-3 shadow-sm btn btn-sm btn-primary fw-bold rounded-pill" data-bs-toggle="modal" data-bs-target="#handoverModal{{ $asset->id }}">
                                                <i class="bi bi-person-plus me-1"></i> Serahkan
                                            </button>
                                        @endif

                                    @else
                                        {{-- Jika Disposed, sembunyikan tombol transaksi --}}
                                        <span class="badge bg-secondary bg-opacity-25 text-secondary border border-secondary-subtle"><i class="bi bi-slash-circle me-1"></i> Aset Nonaktif</span>
                                    @endif

                                    {{-- Tombol Riwayat (Selalu Muncul) --}}
                                    <a href="{{ route('fixed-assets.history', $asset->id) }}" class="shadow-sm btn btn-sm btn-warning text-dark fw-bold rounded-pill" title="Lihat Riwayat">
                                        <i class="bi bi-clock-history"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>

                        {{-- MODAL PROSES PENGEMBALIAN ASET (RETUR) --}}
                        @if(!empty($asset->assigned_to))
                        <div class="modal fade" id="returnModal{{ $asset->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="border-0 shadow-lg modal-content rounded-4">
                                    <div class="text-white modal-header bg-danger border-bottom-0 rounded-top-4">
                                        <h6 class="modal-title fw-bold"><i class="bi bi-arrow-return-left me-2"></i> Form Pengembalian Aset</h6>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form action="{{ route('fixed-assets.return', $asset->id) }}" method="POST">
                                        @csrf
                                        <div class="modal-body bg-light">
                                            <div class="p-3 mb-4 border-0 shadow-sm alert alert-danger bg-danger-subtle rounded-3">
                                                <div class="mb-1 fw-bold text-danger fs-6"><i class="bi bi-qr-code-scan me-1"></i> {{ $asset->asset_number }}</div>
                                                <div class="small text-dark fw-bold">{{ $asset->name ?? optional($asset->item)->name }}</div>
                                                <hr class="my-2 opacity-25 border-danger">
                                                <div class="small text-danger fw-bold"><i class="bi bi-person-workspace me-1"></i> Kembali dari: {{ optional($asset->assignee)->name }}</div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold text-dark small">Tujuan Gudang <span class="text-danger">*</span></label>
                                                <select name="warehouse_id" class="form-select border-danger-subtle" required>
                                                    <option value="">-- Pilih Gudang Penerima --</option>
                                                    @foreach($warehouses as $wh)
                                                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="row">
                                                <div class="mb-3 col-md-6">
                                                    <label class="form-label fw-bold text-dark small">Kondisi Aset <span class="text-danger">*</span></label>
                                                    <select name="status_id" class="form-select border-danger-subtle" required>
                                                        <option value="">-- Kondisi Saat Ini --</option>
                                                        @foreach($statuses as $st)
                                                            <option value="{{ $st->id }}" {{ str_contains(strtolower($st->name), 'normal') || str_contains(strtolower($st->name), 'available') ? 'selected' : '' }}>{{ $st->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="mb-3 col-md-6">
                                                    <label class="form-label fw-bold text-dark small">Tgl. Kembali <span class="text-danger">*</span></label>
                                                    <input type="date" name="return_date" class="form-control border-danger-subtle" value="{{ date('Y-m-d') }}" required>
                                                </div>
                                            </div>

                                            <div class="mb-1">
                                                <label class="form-label fw-bold text-dark small">Catatan Minus / Kerusakan</label>
                                                <textarea name="return_notes" class="form-control border-danger-subtle" rows="2" placeholder="Contoh: Lecet pemakaian, charger hilang..."></textarea>
                                            </div>
                                        </div>
                                        <div class="bg-white modal-footer border-top-0 rounded-bottom-4">
                                            <button type="button" class="px-4 btn btn-light fw-bold rounded-pill" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="px-4 shadow-sm btn btn-danger fw-bold rounded-pill"><i class="bi bi-save me-1"></i> Update Stok</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- MODAL PROSES PENYERAHAN ASET (HANDOVER) --}}
                        @if(empty($asset->assigned_to))
                        <div class="modal fade" id="handoverModal{{ $asset->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="border-0 shadow-lg modal-content rounded-4">
                                    <div class="text-white modal-header bg-primary border-bottom-0 rounded-top-4">
                                        <h6 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i> Form Penyerahan Aset</h6>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form action="{{ route('fixed-assets.handover', $asset->id) }}" method="POST">
                                        @csrf
                                        <div class="modal-body bg-light">
                                            <div class="p-3 mb-4 border-0 shadow-sm alert alert-primary bg-primary-subtle rounded-3">
                                                <div class="mb-1 fw-bold text-primary fs-6"><i class="bi bi-qr-code-scan me-1"></i> {{ $asset->asset_number }}</div>
                                                <div class="small text-dark fw-bold">{{ $asset->name ?? optional($asset->item)->name }}</div>
                                                <hr class="my-2 opacity-25 border-primary">
                                                <div class="small text-primary fw-bold"><i class="bi bi-shop me-1"></i> Dari: {{ optional($asset->warehouse)->name }}</div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold text-dark small">Pilih Staf Penerima <span class="text-danger">*</span></label>
                                                <select name="assigned_to" class="form-select select2-user border-primary-subtle" required style="width: 100%;">
                                                    <option value="">-- Ketik Nama Karyawan --</option>
                                                    @foreach($users as $user)
                                                        <option value="{{ $user->id }}">{{ $user->name }} (Dept: {{ optional($user->department)->name ?? '-' }})</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="row">
                                                <div class="mb-3 col-md-6">
                                                    <label class="form-label fw-bold text-dark small">Update Status <span class="text-danger">*</span></label>
                                                    <select name="status_id" class="form-select border-primary-subtle" required>
                                                        <option value="">-- Pilih Status --</option>
                                                        @foreach($statuses as $st)
                                                            <option value="{{ $st->id }}" {{ str_contains(strtolower($st->name), 'use') || str_contains(strtolower($st->name), 'pakai') ? 'selected' : '' }}>{{ $st->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="mb-3 col-md-6">
                                                    <label class="form-label fw-bold text-dark small">Tgl. Serah Terima <span class="text-danger">*</span></label>
                                                    <input type="date" name="handover_date" class="form-control border-primary-subtle" value="{{ date('Y-m-d') }}" required>
                                                </div>
                                            </div>

                                            <div class="mb-1">
                                                <label class="form-label fw-bold text-dark small">Catatan (Kelengkapan)</label>
                                                <textarea name="handover_notes" class="form-control border-primary-subtle" rows="2" placeholder="Contoh: Lengkap dengan tas & charger..."></textarea>
                                            </div>
                                        </div>
                                        <div class="bg-white modal-footer border-top-0 rounded-bottom-4">
                                            <button type="button" class="px-4 btn btn-light fw-bold rounded-pill" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="px-4 shadow-sm btn btn-primary fw-bold rounded-pill"><i class="bi bi-send-check me-1"></i> Serahkan Aset</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endif

                    @empty
                        <tr>
                            <td colspan="7" class="py-5 text-center text-muted">
                                <i class="mb-3 opacity-25 bi bi-inbox display-4 d-block"></i>
                                <h6 class="fw-bold text-dark">Data Transaksi Kosong</h6>
                                <p class="mb-0 small text-muted">Aset yang dicari tidak ditemukan atau tidak tersedia.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- 3. PAGINASI --}}
        @if($assets->hasPages())
        <div class="px-4 py-3 bg-white card-footer border-top d-flex flex-column flex-md-row justify-content-between align-items-center">
            <span class="mb-2 text-muted small mb-md-0 fw-medium">
                Menampilkan {{ $assets->firstItem() ?? 0 }} - {{ $assets->lastItem() ?? 0 }} dari {{ $assets->total() }} total aset
            </span>
            <nav>
                {{ $assets->links('pagination::bootstrap-5') }}
            </nav>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        // Inisialisasi Select2 pada Modal Saat Modal Dibuka (Menghindari masalah Z-Index)
        $('.modal').on('shown.bs.modal', function () {
            $(this).find('.select2-user').select2({
                theme: 'bootstrap-5',
                dropdownParent: $(this) // Memastikan list dropdown select2 berada di dalam modal
            });
        });
    });
</script>
@endpush
