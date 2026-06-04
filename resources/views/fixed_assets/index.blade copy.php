@extends('layouts.app')

@push('css')
{{-- PANGGIL CSS SELECT2 DI SINI AGAR PASTI TER-LOAD --}}
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Sedikit sentuhan agar Select2 senada dengan Bootstrap 5 */
    .select2-container .select2-selection--single { height: 38px !important; border: 1px solid #dee2e6 !important; border-radius: 0.375rem !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px !important; color: #6c757d !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }

    /* Styling Timeline Modal History */
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
                {{-- 🔥 DROPDOWN FILTER GUDANG 🔥 --}}
                <select name="warehouse_id" class="shadow-sm form-select rounded-pill border-info" onchange="this.form.submit()" style="width: 200px;">
                    <option value="">-- Semua Gudang --</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>
                            {{ $wh->name }}
                        </option>
                    @endforeach
                </select>

                <div class="overflow-hidden border shadow-sm input-group rounded-pill">
                    <input type="text" name="search" class="px-4 bg-white border-0 form-control" placeholder="Cari No Aset, S/N..." value="{{ request('search') }}">
                    <button class="px-4 text-white border-0 btn btn-info fw-bold" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                    @if(request('search') || request('warehouse_id'))
                        <a href="{{ route('fixed-assets.index') }}" class="px-3 btn btn-light border-start text-danger" title="Reset">
                            <i class="bi bi-x-lg"></i>
                        </a>
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
                    <li><a class="py-2 dropdown-item fw-medium" href="#" data-bs-toggle="modal" data-bs-target="#modalImportAset"><i class="bi bi-file-earmark-excel me-2 text-success"></i> Import Excel Aset</a></li>
                    <li><a class="py-2 dropdown-item fw-medium" href="{{ route('fixed-assets.import_history') }}"><i class="bi bi-clock-history me-2 text-secondary"></i> Riwayat Import & BAST</a></li>
                </ul>
            </div>
        </div>
    </div>

    {{-- TAMPILKAN ERROR --}}
    @if(session('error'))
        <div class="border-0 border-4 shadow-sm alert alert-danger alert-dismissible fade show rounded-4 border-start border-danger" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('success'))
        <div class="border-0 border-4 shadow-sm alert alert-success alert-dismissible fade show rounded-4 border-start border-success" role="alert">
            <i class="bi bi-check-circle-fill me-2 text-success"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- TABEL ASET --}}
    <div class="border-0 border-4 shadow-sm card border-top border-info rounded-3">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light fw-bold text-muted border-bottom">
                    <tr>
                        <th class="py-3 ps-4" width="20%">No. Aset Sistem & Akuntansi</th>
                        <th class="py-3" width="22%">Nama Barang / Aset & Spesifikasi</th>
                        <th class="py-3" width="15%">Serial Number</th>
                        <th class="py-3 text-center" width="13%">Status</th>
                        <th class="py-3" width="18%">Dipegang Oleh</th>
                        <th class="py-3 pe-4 text-end" width="12%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $ast)
                    <tr>
                        <td class="py-3 ps-4">
                            <div class="fw-bold text-dark">{{ $ast->asset_number }}</div>
                            @if($ast->accounting_asset_number)
                                <div class="mt-1 small fw-bold text-secondary" title="Nomor Label Akuntansi">
                                    <i class="bi bi-tag-fill me-1 text-warning"></i>{{ $ast->accounting_asset_number }}
                                </div>
                            @endif

                            {{-- 🔥 LOKASI GUDANG (REAL-TIME) 🔥 --}}
                            <div class="mt-1 small text-muted" style="font-size: 0.7rem;">
                                @if($ast->warehouse_id)
                                    <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                                    Gudang: <span class="fw-bold text-dark">{{ optional($ast->warehouse)->name }}</span>
                                @else
                                    <i class="bi bi-question-circle text-danger me-1"></i> <span class="text-danger">Belum Diset</span>
                                @endif
                            </div>

                            {{-- 🔥 DOKUMEN ASAL USUL ASET 🔥 --}}
                            <div class="mt-1 small text-muted" style="font-size: 0.7rem;">
                                @if($ast->goods_receipt_id)
                                    <i class="bi bi-box-seam me-1"></i> GR: <span class="fw-bold">{{ optional($ast->goodsReceipt)->gr_number }}</span>
                                @elseif($ast->batch_id)
                                    <i class="bi bi-file-earmark-excel me-1 text-success"></i>
                                    <a href="{{ route('fixed-assets.show_import_batch', $ast->batch_id) }}" class="text-success text-decoration-none fw-bold" title="Lihat Detail Import">
                                        {{ $ast->batch_id }}
                                    </a>
                                @else
                                    <i class="bi bi-plus-circle me-1"></i> Manual / Hibah
                                @endif
                            </div>
                        </td>

                        <td class="py-3 fw-bold text-dark">
                            @if(optional($ast->status)->slug === 'disposed')
                                <div class="fw-bold text-danger text-decoration-line-through">{{ $ast->name ?? optional($ast->item)->name }}</div>
                            @else
                                <div class="fw-bold text-primary">{{ $ast->name ?? optional($ast->item)->name }}</div>
                            @endif

                            <div class="mt-1 small fw-bold text-primary" style="font-size: 0.7rem;">
                                <i class="bi bi-building me-1"></i> Milik: {{ optional($ast->company)->name ?? 'Belum diset' }}
                            </div>

                            @php
                                $tglPerolehan = $ast->acquisition_date ? \Carbon\Carbon::parse($ast->acquisition_date) : $ast->created_at;
                                $diff = $tglPerolehan->diff(now());

                                $umurTeks = '';
                                if ($diff->y > 0) { $umurTeks .= $diff->y . ' Thn '; }
                                if ($diff->m > 0) { $umurTeks .= $diff->m . ' Bln'; }

                                if ($umurTeks == '') {
                                    $umurTeks = $diff->d > 0 ? $diff->d . ' Hari' : 'Baru Masuk';
                                }
                            @endphp
                            <div class="mt-1 small fw-bold text-success" style="font-size: 0.7rem;">
                                <i class="bi bi-calendar-check me-1"></i> Diterima:
                                {{ $tglPerolehan->format('d M Y') }}
                                <span class="text-muted ms-1">(Umur: {{ trim($umurTeks) }})</span>
                            </div>

                            <div class="mt-1 small fw-normal text-muted" style="font-size: 0.75rem;">
                                <i class="bi bi-cpu me-1"></i>
                                {{ $ast->spesifikasi_detail ?? optional($ast->item)->specification ?? 'Belum ada spesifikasi unit.' }}
                            </div>
                        </td>

                        <td class="py-3">
                            @if($ast->serial_number)
                                <span class="text-dark fw-semibold"><i class="bi bi-upc-scan me-1 text-muted"></i>{{ $ast->serial_number }}</span>
                            @else
                                <span class="text-danger small fst-italic">Belum diinput</span>
                            @endif
                        </td>

                        <td class="py-3 text-center">
                            @if($ast->status)
                                <span class="badge bg-{{ $ast->status->color }}-subtle text-{{ $ast->status->color }} border border-{{ $ast->status->color }}-subtle rounded-pill px-3 shadow-sm">
                                    {{ $ast->status->name }}
                                </span>
                            @else
                                <span class="px-3 border shadow-sm badge bg-secondary-subtle text-secondary border-secondary-subtle rounded-pill">
                                    Unknown
                                </span>
                            @endif
                        </td>

                        <td class="py-3">
                            @if(optional($ast->status)->slug === 'disposed')
                                <span class="border badge bg-danger-subtle text-danger border-danger-subtle rounded-pill">Dihancurkan / Dijual</span>
                            @elseif($ast->assigned_to)
                                <div class="mb-1 fw-bold text-dark">
                                    <i class="bi bi-person-badge me-1 text-info"></i> {{ optional($ast->assignee)->name }}
                                </div>
                                <div class="small text-muted" style="font-size: 0.75rem;">
                                    <i class="bi bi-geo-alt me-1"></i> {{ optional($ast->assignee->company)->name ?? 'Kantor Pusat' }}
                                </div>
                            @else
                                {{-- 🔥 LOKASI GUDANG DI KOLOM DIPEGANG OLEH (REAL-TIME) 🔥 --}}
                                <span class="px-3 py-2 border badge bg-success-subtle text-success border-success-subtle rounded-pill">
                                    @if($ast->warehouse_id)
                                        <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                                        Gudang: <span class="fw-bold text-dark">{{ optional($ast->warehouse)->name }}</span>
                                    @else
                                        <i class="bi bi-question-circle me-1"></i> Belum Diset
                                    @endif
                                </span>
                            @endif
                        </td>

                        <td class="py-3 pe-4 text-end text-nowrap">
                            <button type="button" class="px-2 shadow-sm btn btn-sm btn-outline-secondary rounded-pill fw-bold me-1" data-bs-toggle="modal" data-bs-target="#modalHistory{{ $ast->id }}" title="Lihat Riwayat">
                                <i class="bi bi-clock-history"></i>
                            </button>

                            <button type="button" class="px-2 shadow-sm btn btn-sm btn-info rounded-pill me-1"
                                data-bs-toggle="modal"
                                data-bs-target="#editModal"
                                data-id="{{ $ast->id }}"
                                data-item-name="{{ $ast->name ?? optional($ast->item)->name }}"
                                data-asset-number="{{ $ast->asset_number }}"
                                data-serial="{{ $ast->serial_number }}"
                                data-accounting="{{ $ast->accounting_asset_number }}"
                                data-spesifikasi="{{ $ast->spesifikasi_detail }}"
                                data-company-id="{{ $ast->company_id }}"
                                data-status-id="{{ $ast->status_id }}"
                                data-assigned-to="{{ $ast->assigned_to }}"
                                data-notes="{{ $ast->notes }}">
                                <i class="bi bi-pencil-square"></i>
                            </button>

                            <div class="dropdown d-inline-block">
                                <button class="px-2 text-white shadow-sm btn btn-sm btn-dark rounded-pill fw-bold dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-printer"></i>
                                </button>
                                <ul class="border-0 shadow-sm dropdown-menu dropdown-menu-end rounded-3">
                                    <li><a class="py-2 dropdown-item fw-medium text-dark" href="{{ route('fixed-assets.print_qr', $ast->id) }}" target="_blank"><i class="bi bi-qr-code me-2"></i> Cetak Label QR</a></li>
                                    <li><hr class="dropdown-divider"></li>

                                    @if(optional($ast->status)->slug === 'in_use' && $ast->assigned_to)
                                        <li><a class="py-2 dropdown-item fw-medium text-primary" href="{{ route('fixed-assets.bast', $ast->id) }}" target="_blank"><i class="bi bi-file-earmark-check me-2"></i> Cetak BAST</a></li>
                                    @else
                                        <li><a class="py-2 dropdown-item fw-medium text-muted disabled" href="#"><i class="bi bi-file-earmark-check me-2"></i> BAST (Hanya In Use)</a></li>
                                    @endif

                                    @if(optional($ast->status)->slug === 'available' && $ast->histories->whereNotNull('assigned_to')->count() > 0)
                                        <li><a class="py-2 dropdown-item fw-medium text-warning" href="{{ route('fixed-assets.bapa', $ast->id) }}" target="_blank"><i class="bi bi-file-earmark-arrow-down me-2"></i> Cetak BAPA</a></li>
                                    @else
                                        <li><a class="py-2 dropdown-item fw-medium text-muted disabled" href="#"><i class="bi bi-file-earmark-arrow-down me-2"></i> BAPA (Belum Peminjaman)</a></li>
                                    @endif

                                    @if(optional($ast->status)->slug === 'disposed')
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="py-2 dropdown-item fw-medium text-danger" href="{{ route('fixed-assets.bapp', $ast->id) }}" target="_blank"><i class="bi bi-file-earmark-x me-2"></i> Cetak BAPP</a></li>
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>

                    {{-- MODAL RIWAYAT ASET --}}
                    <div class="modal fade" id="modalHistory{{ $ast->id }}" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="overflow-hidden border-0 shadow-lg modal-content rounded-4">
                                <div class="py-3 text-white border-0 modal-header bg-dark">
                                    <h5 class="modal-title fw-bold"><i class="bi bi-clock-history me-2"></i>Jejak Rekam Aset</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="p-4 modal-body bg-light text-start">
                                    <div class="pb-3 mb-4 text-center border-bottom">
                                        <h5 class="mb-0 fw-bold text-dark">{{ $ast->name ?? optional($ast->item)->name }}</h5>
                                        <span class="mt-1 badge bg-secondary">{{ $ast->asset_number }}</span>
                                    </div>
                                    <div class="p-4 bg-white shadow-sm timeline-container rounded-4">
                                        @forelse($ast->histories as $log)
                                            <div class="mb-4 d-flex position-relative">
                                                <div class="mt-1 me-3">
                                                    @if(str_contains(strtolower($log->status), 'use'))
                                                        <i class="bi bi-person-check-fill text-primary fs-4"></i>
                                                    @elseif(str_contains(strtolower($log->status), 'available'))
                                                        <i class="bi bi-arrow-down-circle-fill text-success fs-4"></i>
                                                    @elseif(str_contains(strtolower($log->status), 'maintenance'))
                                                        <i class="bi bi-tools text-warning fs-4"></i>
                                                    @else
                                                        <i class="bi bi-x-circle-fill text-dark fs-4"></i>
                                                    @endif
                                                </div>
                                                <div class="pb-3 border-bottom w-100">
                                                    <div class="d-flex justify-content-between">
                                                        <h6 class="mb-1 fw-bold text-dark">Status: {{ $log->status }}</h6>
                                                        <small class="text-muted fw-bold">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y, H:i') }}</small>
                                                    </div>
                                                    @if($log->assigned_to)
                                                        <div class="mb-1 small fw-bold text-primary">
                                                            <i class="bi bi-arrow-right-short"></i> Diserahkan ke: {{ optional($log->assignee)->name }}
                                                        </div>
                                                    @elseif(str_contains(strtolower($log->status), 'available'))
                                                        <div class="mb-1 small fw-bold text-success">
                                                            <i class="bi bi-arrow-left-short"></i> Gudang / IT
                                                        </div>
                                                    @endif
                                                    <div class="p-2 mt-2 border rounded small text-dark bg-light">
                                                        <em>{!! nl2br(e($log->notes ?? '-')) !!}</em>
                                                    </div>
                                                    <div class="mt-2 small text-muted" style="font-size: 0.65rem;">
                                                        Diproses oleh: <span class="fw-bold">{{ optional($log->creator)->name }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="py-4 text-center text-muted">
                                                <i class="mb-2 opacity-50 bi bi-journal-x fs-1 d-block"></i>
                                                Belum ada riwayat pergerakan untuk aset ini.
                                            </div>
                                        @endforelse
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
                        <td colspan="6" class="py-5 text-center text-muted">
                            <i class="mb-3 opacity-50 bi bi-pc-display text-secondary display-6 d-block"></i>
                            <p class="mb-0 small fw-bold">Belum ada data Aset Tetap terdaftar.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(isset($assets) && $assets->hasPages())
        <div class="pt-3 pb-2 bg-white border-0 card-footer rounded-bottom-4">
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
            <form action="{{ route('fixed-assets.store') }}" method="POST">
                @csrf
                <div class="py-3 text-white border-0 modal-header bg-dark">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Registrasi Aset Manual</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="p-4 modal-body bg-light text-start">
                    <div class="mb-4 shadow-sm alert alert-info border-info-subtle small">
                        <i class="bi bi-info-circle-fill me-1"></i> Gunakan form ini untuk mendaftarkan Aset dari <strong>Hibah atau Saldo Awal</strong> (Tidak melewati PO/GR).
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6 border-end pe-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted text-uppercase">PILIH BARANG (ketik min 2 huruf) <span class="text-danger">*</span></label>
                                <select name="item_id" class="form-select select2-item" style="width: 100%;" required>
                                    <option value="">-- Ketik Nama / Kode Barang --</option>
                                    @foreach($items as $item)
                                        <option value="{{ $item->id }}">{{ $item->code }} - {{ $item->name }}</option>
                                    @endforeach
                                </select>
                                <div class="mt-2 form-text" style="font-size: 0.75rem;">
                                    <i class="bi bi-lightbulb text-warning"></i> Barang belum ada di pilihan?
                                    <a href="{{ route('items.index') }}" target="_blank" class="text-primary fw-bold text-decoration-none">
                                        Buat Master Barang Baru <i class="bi bi-box-arrow-up-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Spesifikasi Detail / Merek <span class="text-danger">*</span></label>
                                <textarea name="spesifikasi_detail" class="shadow-sm form-control" rows="2" placeholder="Cth: MAC Address, Warna, Spesifikasi Khusus..." required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">No. Seri Fisik</label>
                                <input type="text" name="serial_number" class="shadow-sm form-control" placeholder="Opsional...">
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
                                <label class="form-label fw-bold small text-muted">Status Penempatan <span class="text-danger">*</span></label>
                                <select name="status_id" id="statusSelectNew" class="shadow-sm form-select" onchange="toggleAssignee('New', this.options[this.selectedIndex].getAttribute('data-slug'))" required>
                                    @foreach($statuses as $status)
                                        @if(in_array($status->slug, ['available', 'in_use']))
                                            <option value="{{ $status->id }}" data-slug="{{ $status->slug }}">
                                                {{ $status->name }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Diserahkan Kepada</label>
                                <select name="assigned_to" id="assigneeSelectNew" class="shadow-sm form-select select2-user" style="width: 100%;" disabled>
                                    <option value="">-- Cari Nama Karyawan --</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }} • {{ optional($user->company)->name ?? 'Pusat' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">No. Label Akuntansi</label>
                                <input type="text" name="accounting_asset_number" class="shadow-sm form-control border-info" placeholder="Opsional...">
                            </div>
                            <div class="mb-2">
                                <label class="form-label fw-bold small text-muted">Catatan Asal Usul</label>
                                <textarea name="notes" class="shadow-sm form-control" rows="2" placeholder="Cth: Hadiah dari Vendor..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-3 bg-white modal-footer border-top">
                    <button type="button" class="px-4 border btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="px-4 text-white shadow-sm btn btn-dark rounded-pill fw-bold"><i class="bi bi-save me-1"></i> Register Aset</button>
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
                    <button type="submit" class="px-4 shadow-sm btn btn-success fw-bold rounded-pill"><i class="bi bi-cloud-upload me-1"></i> Mulai Import</button>
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
        // Eksekusi saat Modal Edit Terbuka
        $('#editModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);

            // 1. Tarik semua data dari tombol
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

            var modal = $(this);

            // 2. Set Action URL Form Update
            modal.find('form').attr('action', "{{ url('fixed-assets') }}/" + id);

            // 3. Set Header Text Modal
            modal.find('#editModalItemName').text(itemName);
            modal.find('#editModalAssetNumber').text(assetNumber);

            // 4. Masukkan data ke input teks
            modal.find('input[name="serial_number"]').val(serial_number);
            modal.find('input[name="accounting_asset_number"]').val(accounting_number);
            modal.find('textarea[name="spesifikasi_detail"]').val(spesifikasi);
            modal.find('textarea[name="notes"]').val(notes);

            // 5. Masukkan data ke Dropdown & jalankan trigger change untuk Select2
            modal.find('select[name="company_id"]').val(company_id).trigger('change');
            modal.find('select[name="status_id"]').val(status_id).trigger('change');
            modal.find('select[name="assigned_to"]').val(assigned_to).trigger('change');
        });
    });

    // 🔥 Deteksi menggunakan slug untuk disable/enable nama peminjam
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
