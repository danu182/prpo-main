@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<style>
    /* Select2 Kustomisasi Modern */
    .select2-container .select2-selection--single { height: 38px !important; border: 1px solid #e2e8f0 !important; border-radius: 8px !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; color: #475569 !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
    .select2-results__options { max-height: 250px !important; overflow-y: auto !important; }

    /* Avatar Modern */
    .avatar-circle-modern { width: 46px; height: 46px; background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%); color: #0284c7; font-weight: 800; display: flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 1.1rem; box-shadow: 0 2px 5px rgba(14, 165, 233, 0.15); }

    /* Tabel SaaS Modern */
    .card-table-wrapper { border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff; }
    .table-modern { margin-bottom: 0; }
    .table-modern thead th { background-color: #f8fafc; color: #64748b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; padding: 1.2rem 1.5rem; border-bottom: 1px solid #e2e8f0; font-weight: 700; border-top: none; }
    .table-modern tbody td { padding: 1.25rem 1.5rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; color: #334155; }
    .table-modern tbody tr.main-row { transition: all 0.2s ease; background-color: #ffffff; }
    .table-modern tbody tr.main-row:hover { background-color: #f8fafc !important; cursor: pointer; }

    /* Baris Detail (Collapse) */
    .collapse-row td { padding: 0 !important; border: none; background-color: #f8fafc; }
    .inner-collapse-modern { margin: 0 1.5rem 1.5rem 1.5rem; padding: 1.5rem; background-color: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }

    /* Tag & Badges Halus */
    .badge-soft { padding: 0.4em 0.75em; font-weight: 600; border-radius: 8px; font-size: 0.7rem; letter-spacing: 0.3px; }
    .badge-item-code { font-size: 0.65rem; background-color: #f1f5f9; color: #475569; padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid #cbd5e1; }

    /* Tombol Aksi */
    .btn-action-icon { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; transition: all 0.2s; color: #64748b; border: 1px solid transparent; background: transparent; }
    .btn-action-icon:hover { background-color: #f1f5f9; color: #0f172a; border-color: #e2e8f0; }
    .dropdown-action-menu { border: 1px solid #e2e8f0; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border-radius: 12px; padding: 0.5rem; }
    .dropdown-action-menu .dropdown-item { border-radius: 6px; padding: 0.5rem 1rem; font-size: 0.85rem; font-weight: 500; transition: 0.2s; }
    .dropdown-action-menu .dropdown-item:hover { background-color: #f8fafc; }
    .timeline-container .border-bottom:last-child { border-bottom: 0 !important; padding-bottom: 0 !important; margin-bottom: 0 !important; }

    /* KUSTOMISASI CKEDITOR */
    .ck-editor__editable_inline { min-height: 150px; border-bottom-left-radius: 8px !important; border-bottom-right-radius: 8px !important; }
    .ck-toolbar { border-top-left-radius: 8px !important; border-top-right-radius: 8px !important; background-color: #f8fafc !important; }
    .ck.ck-balloon-panel { z-index: 1056 !important; }

    /* 🔥 FOTO GALLERY HOVER 🔥 */
    .photo-gallery-item { transition: transform 0.2s ease-in-out; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; background: #fff; padding: 3px; display: inline-block; }
    .photo-gallery-item:hover { transform: scale(1.1); z-index: 10; border-color: #0dcaf0; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }

    /* 🔥 KUSTOMISASI MODAL EDUKASI 🔥 */
    .math-formula { font-family: 'Courier New', Courier, monospace; background: #f1f5f9; padding: 10px 15px; border-left: 4px solid #0d6efd; border-radius: 4px; font-weight: 600; color: #334155; margin-bottom: 1rem; }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid text-dark">
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-pc-display me-2 text-info"></i> Register Aset Tetap</h4>
            <div class="mt-1 text-muted small">Kelola data aset tetap, kepemilikan PT, penyusutan, dan penugasan.
                <a href="#" class="mt-1 ms-2 text-primary fw-bold text-decoration-none d-inline-block mt-md-0" data-bs-toggle="modal" data-bs-target="#modalEdukasiPenyusutan">
                    <i class="bi bi-lightbulb-fill text-warning"></i> Info Perhitungan Nilai Buku
                </a>
            </div>
        </div>

        <div class="flex-wrap gap-2 d-flex">
            <form action="{{ route('fixed-assets.index') }}" method="GET" class="gap-2 d-flex" style="min-width: 500px;">
                <select name="warehouse_id" class="shadow-sm form-select rounded-pill border-info" onchange="this.form.submit()" style="width: 200px;">
                    <option value="">-- Semua Lokasi / Gudang --</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                    @endforeach
                </select>

                <div class="overflow-hidden border shadow-sm input-group rounded-pill">
                    <input type="text" name="search" class="px-4 bg-white border-0 form-control" placeholder="Cari No Aset, Master, S/N..." value="{{ request('search') }}">
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
                    <li><a class="py-2 dropdown-item fw-medium" href="{{ route('fixed-assets.create_manual') }}"><i class="bi bi-pencil-square me-2 text-primary"></i> Input Manual (Hibah)</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="py-2 dropdown-item fw-medium" href="{{ route('fixed-assets.hibah_history') }}"><i class="bi bi-gift me-2 text-warning"></i> Riwayat Penerimaan Hibah</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="py-2 dropdown-item fw-medium" href="{{ route('fixed-assets.create_import') }}"><i class="bi bi-file-earmark-excel me-2 text-success"></i> Import Excel Aset</a></li>
                    <li><a class="py-2 dropdown-item fw-medium" href="{{ route('fixed-assets.import_history') }}"><i class="bi bi-clock-history me-2 text-secondary"></i> Riwayat Import & BAST</a></li>
                </ul>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="border-0 border-4 shadow-sm alert alert-danger alert-dismissible fade show rounded-4 border-start border-danger">
            <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> <strong>Gagal Memproses:</strong>
            <ul class="mt-1 mb-0 small">
                @foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error')) <div class="border-0 border-4 shadow-sm alert alert-danger alert-dismissible fade show rounded-4 border-start border-danger"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> {{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div> @endif
    @if(session('success')) <div class="border-0 border-4 shadow-sm alert alert-success alert-dismissible fade show rounded-4 border-start border-success"><i class="bi bi-check-circle-fill me-2 text-success"></i> {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div> @endif

    {{-- MODERN EXPANDABLE DATA TABLE --}}
    <div class="mb-4 card-table-wrapper">
        <div class="p-0 table-responsive">
            <table class="table table-modern">
                <thead>
                    <tr>
                        <th width="45%">Identitas, Perolehan & Nilai Buku</th>
                        <th width="15%" class="text-center">Status</th>
                        <th width="25%">Lokasi / Penanggung Jawab</th>
                        <th width="15%" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $ast)
                        <tr class="main-row">
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0 avatar-circle-modern me-3">{{ strtoupper(substr($ast->name ?? optional($ast->item)->name, 0, 2)) }}</div>
                                    <div>
                                        <div class="fw-bold text-dark fs-6 mb-1 {{ optional($ast->status)->slug === 'disposed' ? 'text-decoration-line-through text-danger' : '' }}">{{ $ast->name ?? optional($ast->item)->name }}</div>
                                        <div class="mb-1 text-muted small"><span class="badge-item-code me-1"><i class="bi bi-box me-1"></i>{{ optional($ast->item)->code ?? 'No Code' }}</span> {{ optional($ast->item)->name ?? 'Unknown Master' }}</div>

                                        {{-- 🔥 TANGGAL PEROLEHAN & NILAI MUNCUL DI BARIS DEPAN 🔥 --}}
                                        <div class="flex-wrap gap-2 mt-2 d-flex">
                                            <span class="border badge-soft bg-primary-subtle text-primary border-primary-subtle" title="Nomor Registrasi Aset">
                                                <i class="bi bi-tag-fill me-1"></i>{{ $ast->asset_number }}
                                            </span>
                                            <span class="border badge-soft bg-warning-subtle text-dark border-warning-subtle" title="Tanggal Perolehan (Mulai Hitung Susut)">
                                                <i class="bi bi-calendar-check-fill me-1 text-warning" style="filter: brightness(0.8);"></i>Tgl: {{ $ast->acquisition_date ? \Carbon\Carbon::parse($ast->acquisition_date)->format('d M Y') : '-' }}
                                            </span>
                                            <span class="border badge-soft bg-danger-subtle text-danger border-danger-subtle" title="Nilai Buku Saat Ini">
                                                <i class="bi bi-cash-coin me-1"></i>Rp {{ number_format($ast->net_book_value ?? $ast->purchase_price ?? 0, 0, ',', '.') }}
                                            </span>
                                            @if($ast->serial_number)
                                                <span class="border badge-soft bg-light text-secondary border-light" title="Serial Number (S/N)"><i class="bi bi-upc-scan me-1"></i>S/N: {{ $ast->serial_number }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                @if($ast->status)
                                    <span class="badge-soft bg-{{ $ast->status->color }}-subtle text-{{ $ast->status->color }} border border-{{ $ast->status->color }}-subtle"><span class="d-inline-block bg-{{ $ast->status->color }} rounded-circle me-1" style="width: 6px; height: 6px;"></span>{{ $ast->status->name }}</span>
                                @else
                                    <span class="border badge-soft bg-secondary-subtle text-secondary border-secondary-subtle">Unknown</span>
                                @endif
                            </td>
                            <td>
                                @if(optional($ast->status)->slug === 'disposed')
                                    <div class="fw-bold text-danger"><i class="bi bi-trash-fill me-1"></i> Dihancurkan / Dijual</div>
                                @elseif($ast->assigned_to)
                                    <div class="fw-bold text-dark"><i class="bi bi-person-badge me-2 text-primary"></i>{{ optional($ast->assignee)->name }}</div>
                                    <div class="mt-1 small text-muted"><i class="bi bi-building me-2"></i>{{ optional($ast->assignee->company)->name ?? 'Kantor Pusat' }}</div>
                                @else
                                    <div class="fw-bold text-success"><i class="bi bi-box-seam me-2"></i>{{ optional($ast->warehouse)->name ?? 'Belum Diset' }}</div>
                                    <div class="mt-1 small text-muted"><i class="bi bi-geo-alt me-2"></i>Lokasi Gudang</div>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="gap-1 d-flex justify-content-end">
                                    <button class="btn-action-icon" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $ast->id }}"><i class="bi bi-chevron-down"></i></button>
                                    <div class="dropdown">
                                        <button class="btn-action-icon" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end dropdown-action-menu">
                                            <li><a class="dropdown-item text-primary" href="{{ route('fixed-assets.edit', $ast->id) }}"><i class="bi bi-pencil-square me-2"></i> Edit Data Aset</a></li>
                                            <li><a class="dropdown-item text-dark" href="#" data-bs-toggle="modal" data-bs-target="#modalHistory{{ $ast->id }}"><i class="bi bi-clock-history me-2"></i> Log Riwayat Aset</a></li>
                                            <li><hr class="my-1 dropdown-divider"></li>
                                            <li><a class="dropdown-item text-dark" href="{{ route('fixed-assets.print_qr', $ast->id) }}" target="_blank"><i class="bi bi-qr-code me-2"></i> Cetak Label QR</a></li>
                                            @if(optional($ast->status)->slug === 'in_use' && $ast->assigned_to) <li><a class="dropdown-item text-info" href="{{ route('fixed-assets.bast', $ast->id) }}" target="_blank"><i class="bi bi-file-earmark-check me-2"></i> Cetak BAST</a></li> @endif
                                            @if(optional($ast->status)->slug === 'disposed') <li><a class="dropdown-item text-danger fw-bold" href="{{ route('fixed-assets.bapp', $ast->id) }}" target="_blank"><i class="bi bi-file-earmark-x-fill me-2"></i> Cetak BAPP</a></li> @endif
                                            {{-- 🔥 TAMBAHKAN KODE TOMBOL HAPUS INI 🔥 --}}
                                            @if(empty($ast->assigned_to))
                                            <li><hr class="my-1 dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('fixed-assets.destroy', $ast->id) }}" method="POST" class="form-delete-asset">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="dropdown-item text-danger fw-bold btn-delete-asset">
                                                        <i class="bi bi-trash3-fill me-2"></i> Batalkan / Hapus
                                                    </button>
                                                </form>
                                            </li>
                                            @endif
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <tr class="collapse-row">
                            <td colspan="4">
                                <div class="collapse" id="collapse-{{ $ast->id }}">
                                    <div class="inner-collapse-modern">
                                        <div class="row g-4">
                                            <div class="col-md-6 border-end pe-md-4">
                                                <h6 class="mb-3 fw-bold text-dark fs-6"><i class="bi bi-card-text me-2 text-primary"></i>Spesifikasi & Nilai Aset</h6>
                                                <table class="table mb-0 table-sm table-borderless small">
                                                    <tr><td width="35%" class="pb-2 text-muted">Pemilik PT</td><td class="pb-2 fw-bold text-dark">: {{ optional($ast->company)->name ?? '-' }}</td></tr>
                                                    <tr><td class="pb-2 text-muted">Label Akuntansi</td><td class="pb-2 fw-bold text-dark">: {{ $ast->accounting_asset_number ?? '-' }}</td></tr>
                                                    <tr><td class="pb-2 text-muted">Kategori</td><td class="pb-2 fw-bold text-dark">: {{ $ast->assetCategory ? $ast->assetCategory->name . ' (' . $ast->assetCategory->useful_life_years . ' Tahun)' : 'Kelompok 1 (Default)' }}</td></tr>
                                                    <tr><td class="pb-2 text-muted">Harga Perolehan</td><td class="pb-2 fw-bold text-dark">: {{ optional($ast->currency)->symbol ?? 'Rp' }} {{ number_format($ast->purchase_price ?? 0, 0, ',', '.') }}</td></tr>
                                                    <tr><td class="pb-2 text-muted">Nilai Buku Saat Ini</td><td class="pb-2 fw-bold text-danger">: {{ optional($ast->currency)->symbol ?? 'Rp' }} {{ number_format($ast->net_book_value ?? $ast->purchase_price ?? 0, 0, ',', '.') }}</td></tr>
                                                    <tr><td class="align-top text-muted">Spesifikasi</td><td class="align-top text-dark d-flex"><span class="me-2">:</span><div class="content-html" style="font-size: 0.85rem; line-height: 1.5;">{!! $ast->spesifikasi_detail ?? optional($ast->item)->specification ?? '-' !!}</div></td></tr>
                                                </table>
                                            </div>
                                            <div class="col-md-6 ps-md-4">
                                                <h6 class="mb-3 fw-bold text-dark fs-6"><i class="bi bi-folder2-open me-2 text-warning"></i>Asal Usul Dokumen</h6>
                                                <div class="gap-3 d-flex flex-column">
                                                    <div class="d-flex align-items-center">
                                                        <div class="p-2 rounded bg-light me-3 text-primary"><i class="bi bi-calendar-event fs-5"></i></div>
                                                        <div>
                                                            <div class="small text-muted">Tgl Perolehan (Mulai Susut)</div>
                                                            <div class="fw-bold text-dark">{{ $ast->acquisition_date ? \Carbon\Carbon::parse($ast->acquisition_date)->format('d M Y') : '-' }}</div>
                                                           {{-- 🔥 INDIKATOR KHUSUS ASAL USUL TANGGAL 🔥 --}}
                                                            @if($ast->batch_id && str_contains($ast->batch_id, 'IMP'))
                                                                <div class="mt-1" style="font-size: 0.68rem; line-height: 1.2; color: #0369a1; font-weight: 600;">
                                                                    <i class="bi bi-info-circle-fill"></i> Tgl asli dibeli sebelum di-import ke sistem.
                                                                </div>
                                                            @elseif($ast->batch_id && str_contains($ast->batch_id, 'HIBAH'))
                                                                <div class="mt-1" style="font-size: 0.68rem; line-height: 1.2; color: #15803d; font-weight: 600;">
                                                                    <i class="bi bi-info-circle-fill"></i> Tgl perolehan fisik via registrasi manual/hibah.
                                                                </div>
                                                            @elseif($ast->goods_receipt_id)
                                                                <div class="mt-1" style="font-size: 0.68rem; line-height: 1.2; color: #64748b; font-weight: 600;">
                                                                    <i class="bi bi-info-circle-fill"></i> Tgl penerimaan fisik dari Purchase Order (GR).
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="d-flex align-items-center">
                                                        <div class="p-2 rounded bg-light me-3 text-info"><i class="bi bi-link-45deg fs-5"></i></div>
                                                        <div>
                                                            <div class="small text-muted">Referensi Dokumen / Input</div>
                                                            <div class="fw-bold text-dark">
                                                                @if($ast->goods_receipt_id)
                                                                    GR: {{ optional($ast->goodsReceipt)->gr_number }}
                                                                @elseif($ast->batch_id)
                                                                    Batch: {{ $ast->batch_id }}
                                                                @else
                                                                    Input Manual / Hibah
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                    @if($ast->supporting_document)
                                                        <a href="{{ asset('storage/' . $ast->supporting_document) }}" target="_blank" class="mt-2 border btn btn-sm btn-light fw-bold text-primary text-start" style="width: fit-content;"><i class="bi bi-file-earmark-pdf-fill me-2 text-danger"></i> Lihat Dokumen BAST/Nota Asli</a>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- 🔥 GALERI FOTO FISIK ASET 🔥 --}}
                                            <div class="pt-4 mt-3 col-12 border-top">
                                                <h6 class="mb-3 fw-bold text-dark fs-6"><i class="bi bi-images me-2 text-info"></i>Galeri Foto Fisik Aset</h6>
                                                @if(isset($ast->photos) && $ast->photos->count() > 0)
                                                    <div class="flex-wrap gap-3 d-flex">
                                                        @foreach($ast->photos as $photo)
                                                            <a href="{{ asset('storage/' . $photo->file_path) }}" target="_blank" class="photo-gallery-item">
                                                                <img src="{{ asset('storage/' . $photo->file_path) }}" class="rounded-2" style="width: 100px; height: 100px; object-fit: cover;">
                                                            </a>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="p-3 border bg-light rounded-3 border-secondary-subtle">
                                                        <span class="text-muted small fst-italic"><i class="bi bi-info-circle me-1"></i>Belum ada foto fisik yang dilampirkan untuk aset ini.</span>
                                                    </div>
                                                @endif
                                            </div>
                                            {{-- 🔥 END GALERI FOTO 🔥 --}}

                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        {{-- MODAL HISTORY (Jejak Rekam) --}}
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
                                                @if($ast->assigned_to) <span class="text-white badge bg-success"><i class="bi bi-person-check-fill me-1"></i>Dipegang: {{ optional($ast->assignee)->name }}</span> @else <span class="badge bg-info text-dark"><i class="bi bi-geo-alt-fill me-1"></i>{{ optional($ast->warehouse)->name ?? 'Lokasi Belum Diset' }}</span> @endif
                                            </div>
                                        </div>
                                        <div class="p-4">
                                            <div class="p-4 bg-white shadow-sm timeline-container rounded-4">
                                                @forelse($ast->histories as $log)
                                                    <div class="mb-4 d-flex position-relative">
                                                        <div class="flex-shrink-0 mt-1 me-3">
                                                            @if(str_contains(strtolower($log->status), 'use')) <i class="bi bi-person-check-fill text-primary fs-4"></i> @elseif(in_array(strtolower($log->status), ['available', 'maintenance', 'returned'])) <i class="bi bi-arrow-down-circle-fill text-success fs-4"></i> @else <i class="bi bi-info-circle-fill text-secondary fs-4"></i> @endif
                                                        </div>
                                                        <div class="pb-3 border-bottom w-100">
                                                            <div class="mb-1 d-flex justify-content-between align-items-center">
                                                                <h6 class="mb-0 fw-bold text-dark">Status: <span class="text-primary">{{ $log->status }}</span></h6>
                                                                <small class="px-2 py-1 rounded text-muted fw-bold bg-light">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y, H:i') }}</small>
                                                            </div>
                                                            @if($log->assigned_to) <div class="mb-2 small fw-bold text-primary"><i class="bi bi-arrow-right-short"></i> Diserahkan ke: {{ optional($log->assignee)->name }}</div> @elseif(in_array(strtolower($log->status), ['available', 'maintenance', 'returned'])) <div class="mb-2 small fw-bold text-success"><i class="bi bi-arrow-left-short"></i> Gudang: {{ optional($ast->warehouse)->name ?? 'Gudang Terdaftar' }}</div> @endif
                                                            <div class="p-3 mt-2 rounded border-start border-3 border-info text-dark bg-info-subtle small" style="line-height: 1.4;"><em>{!! nl2br(e($log->notes ?? 'Tidak ada catatan.')) !!}</em></div>
                                                            <div class="mt-2 text-end text-muted" style="font-size: 0.65rem;">Oleh: <span class="fw-bold">{{ optional($log->creator)->name ?? 'Sistem' }}</span></div>
                                                        </div>
                                                    </div>
                                                @empty
                                                    <div class="py-5 text-center text-muted"><i class="mb-2 opacity-50 bi bi-journal-x display-6 d-block"></i><div class="fw-bold">Belum Ada Riwayat</div></div>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-3 bg-white modal-footer border-top"><button type="button" class="px-4 border btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Tutup Riwayat</button></div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr><td colspan="4" class="py-5 text-center border-0 text-muted"><div class="py-5 mx-auto bg-light rounded-4" style="max-width: 500px;"><i class="mb-3 opacity-25 bi bi-pc-display text-secondary display-4 d-block"></i><h6 class="mb-1 fw-bold text-dark">Belum Ada Data Aset</h6><p class="mb-0 small text-muted">Data aset tetap yang diregistrasi akan muncul di sini.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(isset($assets) && $assets->hasPages()) <div class="px-4 pt-3 pb-3 bg-white border-top">{{ $assets->links() }}</div> @endif
    </div>
</div>

{{-- 🔥 MODAL EDUKASI / PANDUAN PENYUSUTAN ASET 🔥 --}}
<div class="modal fade" id="modalEdukasiPenyusutan" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="border-0 shadow-lg modal-content rounded-4">
            <div class="py-3 text-white border-0 modal-header bg-primary">
                <h5 class="modal-title fw-bold"><i class="bi bi-book-half me-2"></i>Panduan Perhitungan Penyusutan Aset</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="p-4 modal-body bg-light text-start" style="font-size: 0.95rem;">

                {{-- Dasar Hukum & Tanggal Perolehan --}}
                <div class="p-3 mb-4 bg-white border shadow-sm rounded-3 border-primary-subtle">
                    <h6 class="mb-2 fw-bold text-primary"><i class="bi bi-bank me-2"></i>Dasar Hukum & Waktu Mulai Penyusutan</h6>
                    <p class="mb-2 text-dark">Penyusutan aset <strong>TIDAK</strong> dihitung berdasarkan tanggal Purchase Order (PO), melainkan dari <strong>Tanggal Perolehan (Acquisition Date)</strong>, yaitu saat barang fisik diterima dan siap digunakan.</p>
                    <ul class="mb-3 text-muted small fw-medium">
                        <li><strong>Pembelian via PO:</strong> Dihitung mulai dari tanggal <em>Goods Receipt (GR)</em> oleh Gudang.</li>
                        <li><strong>Import / Hibah Manual:</strong> Dihitung mulai dari tanggal yang diinputkan oleh staf pada form.</li>
                    </ul>
                    <p class="pt-2 mb-0 text-muted small border-top">Sistem ini mengacu pada Standar Akuntansi & Pajak Indonesia (UU PPh Pasal 11 & PMK No 72 Tahun 2023).</p>
                </div>

                {{-- Rumus Matematika --}}
                <h6 class="mb-3 fw-bold text-dark"><i class="bi bi-calculator me-2 text-success"></i>Metode Penyusutan: Garis Lurus (Straight-Line)</h6>
                <div class="math-formula">
                    <span class="mb-1 text-muted d-block small">1. Menghitung Penyusutan Bulanan:</span>
                    Penyusutan Bulanan = <span class="text-primary">Harga Perolehan</span> / <span class="text-primary">Masa Manfaat (Dalam Bulan)</span>
                </div>
                <div class="math-formula">
                    <span class="mb-1 text-muted d-block small">2. Menghitung Total Susut hingga Hari Ini:</span>
                    Akumulasi Penyusutan = <span class="text-primary">Penyusutan Bulanan</span> × <span class="text-primary">Durasi Pemakaian (Total Bulan)</span>
                </div>
                <div class="border-4 math-formula border-success bg-success-subtle border-start">
                    <span class="mb-1 text-success d-block small">3. Nilai Akhir yang Muncul di Sistem:</span>
                    Nilai Buku Saat Ini = <span class="text-dark">Harga Perolehan</span> - <span class="text-dark">Akumulasi Penyusutan</span>
                </div>

                {{-- Simulasi Kasus --}}
                <h6 class="mt-4 mb-3 fw-bold text-dark"><i class="bi bi-laptop me-2 text-warning"></i>Contoh Simulasi Kasus</h6>
                <div class="p-3 bg-white border shadow-sm rounded-3 text-muted">
                    <div class="pb-2 mb-3 row border-bottom">
                        <div class="col-6"><strong>Harga Perolehan:</strong> Rp 10.000.000</div>
                        <div class="col-6"><strong>Kategori:</strong> Kelompok 1 (Masa 4 Tahun / 48 Bulan)</div>
                        <div class="mt-2 col-6"><strong>Tgl Perolehan (GR/Input):</strong> 01 Jan 2024</div>
                        <div class="mt-2 col-6 text-danger"><strong>Waktu Pengecekan:</strong> Hari ini (Jeda $\approx$ 30,93 Bulan)</div>
                    </div>

                    <div class="mb-2">
                        <span class="badge bg-secondary me-2">Langkah 1</span>
                        Penyusutan per bulan = Rp 10.000.000 / 48 bulan = <strong>Rp 208.333,33</strong>
                    </div>
                    <div class="mb-2">
                        <span class="badge bg-secondary me-2">Langkah 2</span>
                        Akumulasi Susut = Rp 208.333,33 × 30,93 bulan = <strong>Rp 6.442.553</strong>
                    </div>
                    <div class="p-2 mb-2 border rounded bg-light">
                        <span class="badge bg-success me-2">Hasil Akhir</span>
                        Nilai Buku Saat Ini = Rp 10.000.000 - Rp 6.442.553 = <strong class="text-dark fs-6">Rp 3.557.447</strong>
                    </div>
                    <div class="mt-3 small text-info"><i class="bi bi-info-circle-fill me-1"></i> Catatan: Jika usia aset sudah melampaui masa manfaatnya (misal sudah lewat 4 tahun), maka sistem akan otomatis mengunci Nilai Buku di angka <strong>Rp 0</strong>.</div>
                </div>

            </div>
            <div class="p-3 bg-white modal-footer border-top">
                <button type="button" class="px-5 shadow-sm btn btn-primary rounded-pill fw-bold" data-bs-dismiss="modal">Saya Mengerti</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
{{-- Pastikan jQuery dan SweetAlert2 dipanggil --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {

        // 🔥 MENGGUNAKAN EVENT DELEGATION: $(document).on('click', ...) 🔥
        // Ini memastikan tombol tetap bisa diklik walaupun berada di halaman 2, 3 (Paginasi)
        $(document).on('click', '.btn-delete-asset', function(e) {
            e.preventDefault(); // Mencegah dropdown tertutup otomatis
            e.stopPropagation(); // Mencegah event tumpang tindih

            let form = $(this).closest('form');

            Swal.fire({
                title: 'Batalkan & Hapus Aset?',
                text: "Aset yang salah input ini akan dihapus secara permanen beserta log riwayat dan fotonya. Tindakan ini tidak bisa dibatalkan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545', // Merah Bootstrap
                cancelButtonColor: '#6c757d',  // Abu-abu Bootstrap
                confirmButtonText: '<i class="bi bi-trash3-fill me-1"></i> Ya, Hapus Aset!',
                cancelButtonText: 'Batal',
                customClass: { popup: 'rounded-4 shadow-lg border-0' }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Munculkan loading agar user tidak klik 2x
                    Swal.fire({
                        title: 'Menghapus Data...',
                        html: 'Mohon tunggu sebentar.',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Eksekusi form hapus ke server
                    form.submit();
                }
            });
        });

    });
</script>
@endpush
