@extends('layouts.app')

@push('css')
<style>
    /* Select2 Kustomisasi */
    .select2-container .select2-selection--single { height: 38px !important; border: 1px solid #e2e8f0 !important; border-radius: 8px !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; color: #475569 !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }

    /* Avatar Modern */
    .avatar-circle-modern {
        width: 46px; height: 46px;
        background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
        color: #0284c7; font-weight: 800;
        display: flex; align-items: center; justify-content: center;
        border-radius: 12px; font-size: 1.1rem;
        box-shadow: 0 2px 5px rgba(14, 165, 233, 0.15);
    }

    /* Tabel SaaS Modern */
    .card-table-wrapper { border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff; }
    .table-modern { margin-bottom: 0; }
    .table-modern thead th { background-color: #f8fafc; color: #64748b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; padding: 1.2rem 1.5rem; border-bottom: 1px solid #e2e8f0; font-weight: 700; border-top: none; }
    .table-modern tbody td { padding: 1.25rem 1.5rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; color: #334155; }

    /* Baris Utama & Hover */
    .table-modern tbody tr.main-row { transition: all 0.2s ease; background-color: #ffffff; }
    .table-modern tbody tr.main-row:hover { background-color: #f8fafc !important; cursor: pointer; }

    /* Baris Detail (Collapse) */
    .collapse-row td { padding: 0 !important; border: none; background-color: #f8fafc; }
    .inner-collapse-modern {
        margin: 0 1.5rem 1.5rem 1.5rem; padding: 1.5rem;
        background-color: #ffffff; border-radius: 12px;
        border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }

    /* Tag & Badges Halus */
    .badge-soft { padding: 0.4em 0.75em; font-weight: 600; border-radius: 8px; font-size: 0.7rem; letter-spacing: 0.3px; }

    /* Tombol Aksi */
    .btn-action-icon { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; transition: all 0.2s; color: #64748b; border: 1px solid transparent; background: transparent; }
    .btn-action-icon:hover { background-color: #f1f5f9; color: #0f172a; border-color: #e2e8f0; }
    .dropdown-action-menu { border: 1px solid #e2e8f0; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border-radius: 12px; padding: 0.5rem; }
    .dropdown-action-menu .dropdown-item { border-radius: 6px; padding: 0.5rem 1rem; font-size: 0.85rem; font-weight: 500; transition: 0.2s; }
    .dropdown-action-menu .dropdown-item:hover { background-color: #f8fafc; }

    .timeline-container .border-bottom:last-child { border-bottom: 0 !important; padding-bottom: 0 !important; margin-bottom: 0 !important; }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    {{-- HEADER & PENCARIAN --}}
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="bi bi-pc-display me-2 text-info"></i> Register Aset Tetap
            </h4>
            <div class="mt-1 text-muted small">Kelola data aset tetap, kepemilikan PT, dan peminjam.</div>
        </div>

        <div class="flex-wrap gap-2 d-flex">
            <form action="{{ route('fixed-assets.index') }}" method="GET" class="gap-2 d-flex" style="min-width: 500px;">
                <select name="warehouse_id" class="shadow-sm form-select rounded-pill border-info" onchange="this.form.submit()" style="width: 200px;">
                    <option value="">-- Semua Gudang --</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                    @endforeach
                </select>

                <div class="overflow-hidden border shadow-sm input-group rounded-pill">
                    <input type="text" name="search" class="px-4 bg-white border-0 form-control" placeholder="Cari No Aset, S/N..." value="{{ request('search') }}">
                    <button class="px-4 text-white border-0 btn btn-info fw-bold" type="submit"><i class="bi bi-search"></i></button>
                    @if(request('search') || request('warehouse_id'))
                        <a href="{{ route('fixed-assets.index') }}" class="px-3 btn btn-light border-start text-danger" title="Reset"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>

            <div class="dropdown">
                <button class="shadow-sm btn btn-primary rounded-pill fw-bold dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-plus-circle me-1"></i> Aksi Aset
                </button>
                <ul class="border-0 shadow dropdown-menu dropdown-menu-end rounded-4">
                    <li><a class="py-2 dropdown-item fw-medium" href="#" data-bs-toggle="modal" data-bs-target="#modalAddAsset"><i class="bi bi-pencil-square me-2 text-primary"></i> Input Manual (Hibah)</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="py-2 dropdown-item fw-medium" href="{{ route('fixed-assets.hibah_history') }}"><i class="bi bi-gift me-2 text-warning"></i> Riwayat Penerimaan Hibah</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="py-2 dropdown-item fw-medium" href="#" data-bs-toggle="modal" data-bs-target="#modalImportAset"><i class="bi bi-file-earmark-excel me-2 text-success"></i> Import Excel Aset</a></li>
                    <li><a class="py-2 dropdown-item fw-medium" href="{{ route('fixed-assets.import_history') }}"><i class="bi bi-clock-history me-2 text-secondary"></i> Riwayat Import & BAST</a></li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ALERT PESAN --}}
    @if($errors->any())
        <div class="border-0 border-4 shadow-sm alert alert-danger alert-dismissible fade show rounded-4 border-start border-danger">
            <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> <strong>Gagal Memproses:</strong>
            <ul class="mt-1 mb-0 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="border-0 border-4 shadow-sm alert alert-danger alert-dismissible fade show rounded-4 border-start border-danger"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> {{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('success'))
        <div class="border-0 border-4 shadow-sm alert alert-success alert-dismissible fade show rounded-4 border-start border-success"><i class="bi bi-check-circle-fill me-2 text-success"></i> {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    {{-- MODERN EXPANDABLE DATA TABLE --}}
    <div class="mb-4 card-table-wrapper">
        <div class="p-0 table-responsive">
            <table class="table table-modern">
                <thead>
                    <tr>
                        <th width="35%">Identitas Aset</th>
                        <th width="15%" class="text-center">Status</th>
                        <th width="30%">Lokasi / Penanggung Jawab</th>
                        <th width="20%" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $ast)
                        {{-- BARIS INDUK (Data Utama Aset) --}}
                        <tr class="main-row">
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0 avatar-circle-modern me-3">
                                        {{ strtoupper(substr($ast->name ?? optional($ast->item)->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark fs-6 mb-1 {{ optional($ast->status)->slug === 'disposed' ? 'text-decoration-line-through text-danger' : '' }}">
                                            {{ $ast->name ?? optional($ast->item)->name }}
                                        </div>
                                        <div class="gap-2 d-flex flex-wrap">
                                            <span class="badge-soft bg-primary-subtle text-primary border border-primary-subtle">
                                                <i class="bi bi-tag-fill me-1"></i>{{ $ast->asset_number }}
                                            </span>
                                            @if($ast->serial_number)
                                                <span class="badge-soft bg-light text-secondary border border-light">
                                                    <i class="bi bi-upc-scan me-1"></i>S/N: {{ $ast->serial_number }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="text-center">
                                @if($ast->status)
                                    <span class="badge-soft bg-{{ $ast->status->color }}-subtle text-{{ $ast->status->color }} border border-{{ $ast->status->color }}-subtle">
                                        <span class="d-inline-block bg-{{ $ast->status->color }} rounded-circle me-1" style="width: 6px; height: 6px;"></span>
                                        {{ $ast->status->name }}
                                    </span>
                                @else
                                    <span class="badge-soft bg-secondary-subtle text-secondary border border-secondary-subtle">Unknown</span>
                                @endif
                            </td>

                            <td>
                                @if(optional($ast->status)->slug === 'disposed')
                                    <div class="fw-bold text-danger"><i class="bi bi-trash-fill me-1"></i> Dihancurkan / Dijual</div>
                                    <div class="small text-muted mt-1"><i class="bi bi-info-circle me-1"></i> Tidak ada lokasi</div>
                                @elseif($ast->assigned_to)
                                    <div class="fw-bold text-dark">
                                        <i class="bi bi-person-badge me-2 text-primary"></i>{{ optional($ast->assignee)->name }}
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <i class="bi bi-building me-2"></i>{{ optional($ast->assignee->company)->name ?? 'Kantor Pusat' }}
                                    </div>
                                @else
                                    <div class="fw-bold text-success">
                                        <i class="bi bi-box-seam me-2"></i>{{ optional($ast->warehouse)->name ?? 'Belum Diset' }}
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <i class="bi bi-geo-alt me-2"></i>Lokasi Gudang
                                    </div>
                                @endif
                            </td>

                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    {{-- Tombol Detail Expand --}}
                                    <button class="btn-action-icon" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $ast->id }}" title="Lihat Detail">
                                        <i class="bi bi-chevron-down"></i>
                                    </button>

                                    {{-- Dropdown Opsi Minimalis --}}
                                    <div class="dropdown">
                                        <button class="btn-action-icon" type="button" data-bs-toggle="dropdown" title="Opsi Aset">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end dropdown-action-menu">
                                            <li><a class="dropdown-item text-primary" href="#" data-bs-toggle="modal" data-bs-target="#editModal" data-id="{{ $ast->id }}" data-item-name="{{ $ast->name ?? optional($ast->item)->name }}" data-asset-number="{{ $ast->asset_number }}" data-serial="{{ $ast->serial_number }}" data-accounting="{{ $ast->accounting_asset_number }}" data-spesifikasi="{{ $ast->spesifikasi_detail }}" data-company-id="{{ $ast->company_id }}" data-status-id="{{ $ast->status_id }}" data-assigned-to="{{ $ast->assigned_to }}" data-notes="{{ $ast->notes }}" data-price="{{ (float)$ast->purchase_price }}" data-currency-id="{{ $ast->currency_id }}"><i class="bi bi-pencil-square me-2"></i> Edit Data Aset</a></li>
                                            <li><a class="dropdown-item text-dark" href="#" data-bs-toggle="modal" data-bs-target="#modalHistory{{ $ast->id }}"><i class="bi bi-clock-history me-2"></i> Log Riwayat Aset</a></li>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <li><a class="dropdown-item text-dark" href="{{ route('fixed-assets.print_qr', $ast->id) }}" target="_blank"><i class="bi bi-qr-code me-2"></i> Cetak Label QR</a></li>

                                            @if(optional($ast->status)->slug === 'in_use' && $ast->assigned_to)
                                                <li><a class="dropdown-item text-info" href="{{ route('fixed-assets.bast', $ast->id) }}" target="_blank"><i class="bi bi-file-earmark-check me-2"></i> Cetak BAST</a></li>
                                            @endif

                                            @if(optional($ast->status)->slug === 'available' && $ast->histories->whereNotNull('assigned_to')->count() > 0)
                                                <li><a class="dropdown-item text-warning" href="{{ route('fixed-assets.bapa', $ast->id) }}" target="_blank"><i class="bi bi-file-earmark-arrow-down me-2"></i> Cetak BAPA</a></li>
                                            @endif
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        {{-- BARIS ANAK (Rincian Aset - Collapse) --}}
                        <tr class="collapse-row">
                            <td colspan="4">
                                <div class="collapse" id="collapse-{{ $ast->id }}">
                                    <div class="inner-collapse-modern">
                                        <div class="row g-4">
                                            <div class="col-md-6 border-end pe-md-4">
                                                <h6 class="mb-3 fw-bold text-dark fs-6"><i class="bi bi-card-text me-2 text-primary"></i>Spesifikasi & Nilai</h6>
                                                <table class="table table-sm table-borderless small mb-0">
                                                    <tr><td width="35%" class="text-muted pb-2">Pemilik PT</td><td class="fw-bold text-dark pb-2">: {{ optional($ast->company)->name ?? '-' }}</td></tr>
                                                    <tr><td class="text-muted pb-2">Label Akuntansi</td><td class="fw-bold text-dark pb-2">: {{ $ast->accounting_asset_number ?? '-' }}</td></tr>
                                                    <tr>
                                                        <td class="text-muted pb-2">Harga Perolehan</td>
                                                        <td class="fw-bold text-success pb-2">: {{ optional($ast->currency)->symbol ?? 'Rp' }} {{ number_format($ast->purchase_price ?? 0, 0, ',', '.') }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="align-top text-muted">Spesifikasi</td>
                                                        <td class="align-top text-dark d-flex">
                                                            <span class="me-2">:</span>
                                                            <div class="content-html" style="font-size: 0.85rem; line-height: 1.5;">
                                                                {!! $ast->spesifikasi_detail ?? optional($ast->item)->specification ?? '-' !!}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                            <div class="col-md-6 ps-md-4">
                                                <h6 class="mb-3 fw-bold text-dark fs-6"><i class="bi bi-folder2-open me-2 text-warning"></i>Asal Usul Dokumen</h6>
                                                <div class="d-flex flex-column gap-3">
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-light rounded p-2 me-3 text-primary"><i class="bi bi-calendar-event fs-5"></i></div>
                                                        <div>
                                                            <div class="small text-muted">Tgl Perolehan</div>
                                                            <div class="fw-bold text-dark">{{ $ast->acquisition_date ? \Carbon\Carbon::parse($ast->acquisition_date)->format('d M Y') : '-' }}</div>
                                                        </div>
                                                    </div>

                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-light rounded p-2 me-3 text-info"><i class="bi bi-link-45deg fs-5"></i></div>
                                                        <div>
                                                            <div class="small text-muted">Referensi Dokumen</div>
                                                            <div class="fw-bold text-dark">
                                                                @if($ast->goods_receipt_id)
                                                                    GR: {{ optional($ast->goodsReceipt)->gr_number }}
                                                                @elseif($ast->batch_id)
                                                                    {{ $ast->batch_id }}
                                                                @else
                                                                    Input Manual / Hibah
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>

                                                    @if($ast->supporting_document)
                                                        <a href="{{ asset('storage/' . $ast->supporting_document) }}" target="_blank" class="btn btn-sm btn-light border fw-bold text-primary text-start mt-2">
                                                            <i class="bi bi-file-earmark-pdf-fill me-2 text-danger"></i> Lihat Dokumen BAST/Nota Asli
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        {{-- MODAL RIWAYAT ASET (Tetap sama, tidak perlu diubah layout strukturnya) --}}
                        <div class="modal fade" id="modalHistory{{ $ast->id }}" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                                <div class="overflow-hidden border-0 shadow-lg modal-content rounded-4">
                                    <div class="py-3 text-white border-0 modal-header bg-dark">
                                        <h5 class="modal-title fw-bold"><i class="bi bi-clock-history me-2"></i>Jejak Rekam Aset</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="p-0 modal-body bg-light text-start">
                                        <div class="p-4 text-center bg-white shadow-sm border-bottom z-1 position-relative">
                                            <h5 class="mb-1 fw-bold text-dark">{{ $ast->name ?? optional($ast->item)->name }}</h5>
                                            <div class="gap-2 mt-2 d-flex justify-content-center">
                                                <span class="badge bg-primary"><i class="bi bi-tag-fill me-1"></i>{{ $ast->asset_number }}</span>
                                                <span class="badge bg-info text-dark"><i class="bi bi-geo-alt-fill me-1"></i>{{ optional($ast->warehouse)->name ?? 'Gudang Pusat' }}</span>
                                            </div>
                                        </div>
                                        <div class="p-4">
                                            <div class="p-4 bg-white shadow-sm timeline-container rounded-4">
                                                @forelse($ast->histories as $log)
                                                    <div class="mb-4 d-flex position-relative">
                                                        <div class="flex-shrink-0 mt-1 me-3">
                                                            @if(str_contains(strtolower($log->status), 'use'))
                                                                <i class="bi bi-person-check-fill text-primary fs-4"></i>
                                                            @elseif(in_array(strtolower($log->status), ['available', 'maintenance', 'returned']))
                                                                <i class="bi bi-arrow-down-circle-fill text-success fs-4"></i>
                                                            @else
                                                                <i class="bi bi-info-circle-fill text-secondary fs-4"></i>
                                                            @endif
                                                        </div>
                                                        <div class="pb-3 border-bottom w-100">
                                                            <div class="mb-1 d-flex justify-content-between align-items-center">
                                                                <h6 class="mb-0 fw-bold text-dark">Status: <span class="text-primary">{{ $log->status }}</span></h6>
                                                                <small class="px-2 py-1 rounded text-muted fw-bold bg-light">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y, H:i') }}</small>
                                                            </div>
                                                            @if($log->assigned_to)
                                                                <div class="mb-2 small fw-bold text-primary"><i class="bi bi-arrow-right-short"></i> Diserahkan ke: {{ optional($log->assignee)->name }}</div>
                                                            @elseif(in_array(strtolower($log->status), ['available', 'maintenance', 'returned']))
                                                                <div class="mb-2 small fw-bold text-success"><i class="bi bi-arrow-left-short"></i> Gudang: {{ optional($ast->warehouse)->name ?? 'Pusat' }}</div>
                                                            @endif
                                                            <div class="p-3 mt-2 rounded border-start border-3 border-info text-dark bg-info-subtle small" style="line-height: 1.4;">
                                                                <em>{!! nl2br(e($log->notes ?? 'Tidak ada catatan.')) !!}</em>
                                                            </div>
                                                            <div class="mt-2 text-end text-muted" style="font-size: 0.65rem;">
                                                                Oleh: <span class="fw-bold">{{ optional($log->creator)->name ?? 'Sistem' }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @empty
                                                    <div class="py-5 text-center text-muted">
                                                        <i class="mb-2 opacity-50 bi bi-journal-x display-6 d-block"></i>
                                                        <div class="fw-bold">Belum Ada Riwayat</div>
                                                    </div>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3 bg-white modal-footer border-top">
                                        <button type="button" class="px-4 border btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Tutup Riwayat</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="4" class="py-5 text-center text-muted border-0">
                                <div class="bg-light rounded-4 py-5 mx-auto" style="max-width: 500px;">
                                    <i class="mb-3 bi bi-pc-display text-secondary display-4 d-block opacity-25"></i>
                                    <h6 class="mb-1 fw-bold text-dark">Belum Ada Data Aset</h6>
                                    <p class="mb-0 small text-muted">Data aset tetap yang diregistrasi akan muncul di sini.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(isset($assets) && $assets->hasPages())
            <div class="pt-3 pb-3 px-4 bg-white border-top">
                {{ $assets->links() }}
            </div>
        @endif
    </div>
</div>

{{-- MODAL EDIT ASET --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="overflow-hidden border-0 shadow-lg modal-content rounded-4">
            <form action="" method="POST" id="formEditAsset">
                @csrf
                @method('PUT')
                <div class="py-3 text-white border-0 modal-header bg-info">
                    <h5 class="modal-title fw-bold"><i class="bi bi-sliders me-2"></i>Update Status Aset</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="p-4 modal-body bg-light text-start">
                    <div class="mb-4 text-center">
                        <h5 class="mb-0 fw-bold text-dark" id="editModalItemName">Nama Barang</h5>
                        <span class="mt-1 badge bg-secondary" id="editModalAssetNumber">No Aset</span>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6 border-end pe-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Serial Number (S/N) Fisik</label>
                                <input type="text" name="serial_number" class="shadow-sm form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">No. Aset (Label Akuntansi)</label>
                                <input type="text" name="accounting_asset_number" class="shadow-sm form-control border-info">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Spesifikasi Detail / Unik Unit</label>
                                <textarea name="spesifikasi_detail" class="shadow-sm form-control" rows="3"></textarea>
                            </div>
                            {{-- 🔥 TAMPILAN EDIT MATA UANG & HARGA 🔥 --}}
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Mata Uang & Nilai Wajar</label>
                                <div class="shadow-sm input-group">
                                    <select name="currency_id" class="input-group-text bg-light border-success fw-bold text-dark" style="cursor: pointer;" required>
                                        @foreach($currencies as $currency)
                                            <option value="{{ $currency->id }}">{{ $currency->code }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" name="purchase_price" class="form-control border-success" placeholder="0">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 ps-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Aset Milik PT / Departemen <span class="text-danger">*</span></label>
                                <select name="company_id" class="shadow-sm form-select select2-user" style="width: 100%;" required>
                                    <option value="">-- Pilih Pemilik Aset --</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Status Aset <span class="text-danger">*</span></label>
                                <select name="status_id" class="shadow-sm form-select" onchange="toggleAssignee('Edit', this.options[this.selectedIndex].getAttribute('data-slug'))" required>
                                    <option value="">-- Pilih Status Aset --</option>
                                    @foreach($statuses as $status)
                                        <option value="{{ $status->id }}" data-slug="{{ $status->slug }}">{{ $status->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Ditugaskan Kepada</label>
                                <select name="assigned_to" id="assigneeSelectEdit" class="shadow-sm form-select select2-user" style="width: 100%;" disabled>
                                    <option value="">-- Cari Nama Karyawan --</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }} • {{ optional($user->company)->name ?? 'Kantor Pusat' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label fw-bold small text-muted">Catatan (Kondisi/Perubahan)</label>
                                <textarea name="notes" class="shadow-sm form-control" rows="2" placeholder="Catatan log perubahan..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-3 bg-white modal-footer border-top">
                    <button type="button" class="px-4 border btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="px-4 text-white shadow-sm btn btn-info rounded-pill fw-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL TAMBAH ASET MANUAL --}}
<div class="modal fade" id="modalAddAsset" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="overflow-hidden border-0 shadow-lg modal-content rounded-4">
            <form action="{{ route('fixed-assets.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="py-3 text-white border-0 modal-header bg-dark">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Registrasi Aset Manual (Massal)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="p-4 modal-body bg-light text-start">

                    <div class="row g-4">
                        <div class="col-md-6 border-end pe-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted text-uppercase">Gudang Penerima <span class="text-danger">*</span></label>
                                <select name="warehouse_id" class="shadow-sm form-select" required>
                                    <option value="">-- Pilih Gudang Lokasi Aset --</option>
                                    @foreach($warehouses as $wh)
                                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted text-uppercase">Pilih Barang (Master) <span class="text-danger">*</span></label>
                                <select name="item_id" class="form-select select2-item-ajax" style="width: 100%;" required>
                                    <option value="">-- Ketik Nama / Kode Barang --</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Penamaan Spesifik Aset</label>
                                <input type="text" name="asset_name" class="shadow-sm form-control border-primary" placeholder="Cth: Laptop Core i7 Direksi...">
                                <div class="form-text" style="font-size: 0.7rem;"><i class="bi bi-info-circle"></i> Kosongkan jika ingin menggunakan nama bawaan Master Barang.</div>
                            </div>

                            <div class="row">
                                <div class="mb-3 col-6">
                                    <label class="form-label fw-bold small text-muted">Tgl Diterima <span class="text-danger">*</span></label>
                                    <input type="date" name="acquisition_date" class="shadow-sm form-control border-info" value="{{ date('Y-m-d') }}" required>
                                </div>
                                <div class="mb-3 col-6">
                                    <label class="form-label fw-bold small text-muted">Jumlah Unit <span class="text-danger">*</span></label>
                                    <input type="number" name="quantity" class="shadow-sm form-control border-warning" value="1" min="1" required>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 ps-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted text-uppercase">Aset Milik PT <span class="text-danger">*</span></label>
                                <select name="company_id" class="shadow-sm form-select" required>
                                    <option value="">-- Pilih Pemilik Aset --</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted text-uppercase">Status <span class="text-danger">*</span></label>
                                <select name="status_id" id="statusSelectNew" class="shadow-sm form-select" onchange="toggleAssignee('New', this.options[this.selectedIndex].getAttribute('data-slug'))" required>
                                    @foreach($statuses as $status)
                                        @if(in_array($status->slug, ['available', 'in_use']))
                                            <option value="{{ $status->id }}" data-slug="{{ $status->slug }}">{{ $status->name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>

                            <div class="row">
                                <div class="mb-3 col-4 pe-1">
                                    <label class="form-label fw-bold small text-muted">Mata Uang <span class="text-danger">*</span></label>
                                    <select name="currency_id" class="shadow-sm form-select border-success" required>
                                        @if(isset($currencies))
                                            @foreach($currencies as $currency)
                                                <option value="{{ $currency->id }}" {{ $currency->code == 'IDR' ? 'selected' : '' }}>{{ $currency->code }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                                <div class="mb-3 col-8 ps-1">
                                    <label class="form-label fw-bold small text-muted">Nilai Wajar / Harga</label>
                                    <input type="number" name="purchase_price" class="shadow-sm form-control border-success" placeholder="Estimasi harga hibah..." min="0">
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="row">
                                <div class="mb-3 col-md-6 pe-md-3">
                                    <label class="form-label fw-bold small text-muted">Serial Number (Bila Qty=1)</label>
                                    <input type="text" name="serial_number" class="shadow-sm form-control" placeholder="Kosongkan jika input massal">
                                </div>
                                <div class="mb-3 col-md-6 ps-md-3">
                                    <label class="form-label fw-bold small text-muted">Label Akuntansi (Bila Qty=1)</label>
                                    <input type="text" name="accounting_asset_number" class="shadow-sm form-control" placeholder="FA-XXX...">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Spesifikasi Detail / Merek <span class="text-danger">*</span></label>
                                <textarea name="spesifikasi_detail" class="shadow-sm form-control" rows="2" placeholder="Sertakan detail spesifikasi..." required></textarea>
                            </div>

                            <div class="row">
                                <div class="mb-3 col-md-6 pe-md-3">
                                    <label class="form-label fw-bold small text-muted">Catatan Asal Usul / Hibah</label>
                                    <textarea name="notes" class="shadow-sm form-control" rows="2" placeholder="Cth: Hibah dari CSR Bank X..."></textarea>
                                </div>
                                <div class="mb-3 col-md-6 ps-md-3">
                                    <label class="form-label fw-bold small text-muted">Dokumen Pendukung (Opsional)</label>
                                    <input type="file" name="supporting_document" class="shadow-sm form-control border-secondary" accept=".pdf,.jpg,.jpeg,.png">
                                    <div class="form-text" style="font-size: 0.7rem;"><i class="bi bi-paperclip"></i> Maks 5MB (PDF/JPG/PNG). Lampirkan BAST Hibah / Nota.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-3 bg-white modal-footer border-top">
                    <button type="button" class="px-4 border btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="px-4 text-white shadow-sm btn btn-dark rounded-pill fw-bold"><i class="bi bi-save me-1"></i> Proses Registrasi Massal</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL IMPORT EXCEL --}}
<div class="modal fade" id="modalImportAset" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="overflow-hidden border-0 shadow-lg modal-content rounded-4">
            <form action="{{ route('fixed-assets.preview_import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="px-4 pt-3 pb-3 text-white border-0 modal-header bg-success">
                    <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-excel me-2"></i> Import Master Aset</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="p-4 modal-body bg-light text-start">
                    <div class="mb-4 shadow-sm alert alert-success bg-success-subtle text-success-emphasis border-success-subtle small">
                        <strong><i class="bi bi-info-circle me-1"></i> Petunjuk Import:</strong><br>
                        Gunakan file Excel dengan format header standar. Jika belum punya, unduh template di bawah ini:
                        <div class="mt-2">
                            <a href="{{ route('fixed-assets.download_template') }}" class="shadow-sm btn btn-sm btn-success fw-bold rounded-pill">
                                <i class="bi bi-download me-1"></i> Download Template .XLSX
                            </a>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Pilih File Excel (.xlsx / .csv) <span class="text-danger">*</span></label>
                        <input type="file" name="import_file" class="shadow-sm form-control border-success" accept=".xlsx, .xls, .csv" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">File Pendukung (Opsional)</label>
                        <input type="file" name="support_doc" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="form-text">Lampirkan BAST Hibah, PO, atau Nota (Maks 5MB). File ini akan melekat pada semua aset yang di-import.</div>
                    </div>
                </div>
                <div class="p-3 bg-white modal-footer border-top">
                    <button type="button" class="px-4 border btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="px-4 shadow-sm btn btn-success fw-bold rounded-pill"><i class="bi bi-eye me-1"></i> Preview Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {

        $('.main-row').on('click', function(e) {
            if(e.target.tagName === 'BUTTON' || e.target.tagName === 'A' || $(e.target).closest('a').length || $(e.target).closest('button').length) {
                return;
            }
            let btn = $(this).find('[data-bs-toggle="collapse"]');
            if(btn.length) {
                btn.click();
            }
        });

        $('.select2-item-ajax').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#modalAddAsset'),
            placeholder: '-- Ketik minimal 2 huruf --',
            minimumInputLength: 2,
            ajax: {
                url: "{{ route('fixed-assets.search-items') }}",
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { search: params.term };
                },
                processResults: function (data) {
                    return { results: data };
                },
                cache: true
            }
        });

        $('#editModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);

            var id = button.data('id');
            var itemName = button.data('item-name');
            var assetNumber = button.data('asset-number');
            var serial_number = button.data('serial');
            var accounting_number = button.data('accounting');
            var spesifikasi = button.data('spesifikasi');
            var company_id = button.data('company-id');
            var status_id = button.data('status-id');
            var assigned_to = button.data('assigned-to');
            var notes = button.data('notes');
            var price = button.data('price'); // 🔥 Tangkap Data Harga
            var notes = button.data('notes');
            var currency_id = button.data('currency-id'); // 🔥 Tambah ini

            var modal = $(this);

            modal.find('form').attr('action', "{{ url('fixed-assets') }}/" + id);
            modal.find('#editModalItemName').text(itemName);
            modal.find('#editModalAssetNumber').text(assetNumber);

            modal.find('input[name="serial_number"]').val(serial_number);
            modal.find('input[name="accounting_asset_number"]').val(accounting_number);
            modal.find('textarea[name="spesifikasi_detail"]').val(spesifikasi);
            modal.find('textarea[name="notes"]').val(notes);
            modal.find('input[name="purchase_price"]').val(price); // 🔥 Masukkan Harga ke Input
            modal.find('select[name="currency_id"]').val(currency_id); // 🔥 Tambah ini

            modal.find('select[name="company_id"]').val(company_id).trigger('change');
            modal.find('select[name="status_id"]').val(status_id).trigger('change');
            modal.find('select[name="assigned_to"]').val(assigned_to).trigger('change');
        });
    });

    function toggleAssignee(tipeModal, slug) {
        let assignee = $('#assigneeSelect' + tipeModal);

        if (slug === 'in_use') {
            assignee.prop('disabled', false);
            assignee.prop('required', true);
        } else {
            assignee.prop('disabled', true);
            assignee.prop('required', false);
        }
    }
</script>
@endpush
