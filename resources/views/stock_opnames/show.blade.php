@extends('layouts.app')

@push('css')
<style>
    /* Transisi Halus untuk Efek Hover Kartu KPI */
    .card-kpi {
        transition: all 0.3s ease;
        border-left: 5px solid transparent;
    }
    .card-kpi:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.08) !important;
    }
    .card-kpi-system { border-left-color: #0d6efd; background-color: #f8faff; }
    .card-kpi-actual { border-left-color: #198754; background-color: #f6fff9; }
    .card-kpi-variance { border-left-color: #dc3545; background-color: #fff8f8; }

    /* Tombol Aksi Bulat & Halus */
    .btn-action-rounded {
        border-radius: 50rem;
        font-weight: 600;
        padding: 0.5rem 1.2rem;
        box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        transition: all 0.2s;
    }
    .btn-action-rounded:hover {
        transform: scale(1.02);
    }
    .btn-icon-only {
        padding: 0.5rem 0.8rem;
    }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid text-dark">

    {{-- ========================================================================= --}}
    {{-- 1. LOGIKA STATUS & PERSETUJUAN (BERDASARKAN TABEL STATUSES DB) --}}
    {{-- ========================================================================= --}}
    @php
        $hasApprovals = isset($opname->approvals) && $opname->approvals->count() > 0;

        // Ambil data asli dari tabel statuses
        $statusSlug = optional($opname->status)->slug ?? 'draft';
        $statusName = optional($opname->status)->name ?? 'Draft / Menghitung';
        $statusColor = optional($opname->status)->color ?? 'secondary';

        $pendingApproval = null;
        $canApprove = false;

        if ($hasApprovals) {
            // Cari antrean yang BUKAN APPROVED dan BUKAN REJECTED
            $pendingApproval = $opname->approvals->reject(function($app) {
                $st = strtoupper($app->status ?? '');
                return $st === 'APPROVED' || $st === 'REJECTED';
            })->sortBy('step_order')->first();

            $isRejected = $opname->approvals->contains(function($app) {
                return strtoupper($app->status ?? '') === 'REJECTED';
            });

            // 🔥 MENCEGAH BUG CONTROLLER: Paksa Label UI Mengikuti Realitas Antrean Bawah 🔥
            if ($isRejected) {
                $statusSlug = 'rejected';
                $statusName = 'Ditolak';
                $statusColor = 'danger';
            } elseif ($pendingApproval) {
                $statusSlug = 'pending_approval';
                $statusName = 'Menunggu Persetujuan';
                $statusColor = 'warning';
            } else {
                $statusSlug = 'approved';
                $statusName = 'Disetujui / Selesai';
                $statusColor = 'success';
            }

            // Pengecekan Hak Akses Tombol Setujui/Tolak (Super Admin Bypass)
            if ($pendingApproval && auth()->check()) {
                $user = auth()->user();
                $userRoles = $user->roles->pluck('id')->toArray();
                $isSuperAdmin = $user->hasAnyRole(['Super Administrator', 'Super Admin']) || $user->id === 1;

                if (in_array($pendingApproval->role_id, $userRoles) || $isSuperAdmin) {
                    $canApprove = true;
                }
            }
        }

        // Gunakan warna asli dari database (kolom color)
        $badgeClass = "bg-{$statusColor}-subtle text-{$statusColor} border-{$statusColor}-subtle";
    @endphp

    {{-- ========================================================================= --}}
    {{-- 2. HEADER HALAMAN & TOMBOL AKSI UTAMA (DIRAPIKAN) --}}
    {{-- ========================================================================= --}}
    <div class="pb-3 mb-4 row align-items-center border-bottom gy-3">
        <div class="col-xl-5 col-lg-5">
            <h4 class="mb-1 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-file-earmark-check text-primary me-2 fs-3"></i> Detail Audit Stok
            </h4>
            <div class="flex-wrap gap-3 mt-2 d-flex align-items-center">
                <span class="badge border px-3 py-2 rounded-pill {{ $badgeClass }}">
                    <i class="bi bi-circle-fill me-1" style="font-size: 0.6rem;"></i> {{ strtoupper($statusName) }}
                </span>
                <span class="text-muted small fw-medium">
                    No. Dok: <strong class="text-primary fs-6">{{ $opname->document_number }}</strong>
                </span>
            </div>
        </div>

        <div class="col-xl-7 col-lg-7 text-lg-end">
            <div class="flex-wrap gap-2 d-flex justify-content-lg-end align-items-center">
                {{-- Tombol Sekunder --}}
                <a href="{{ route('stock-opnames.index') }}" class="border btn btn-light btn-action-rounded">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
                <a href="{{ route('stock-opnames.print', $opname->id) }}" target="_blank" class="btn btn-outline-dark btn-action-rounded">
                    <i class="bi bi-printer me-1"></i> Cetak
                </a>
                <a href="{{ route('stock-opnames.cetakHasil', $opname->id) }}" target="_blank" class="btn btn-outline-dark btn-action-rounded">
                    <i class="bi bi-printer me-1"></i> Cetak Laporan
                </a>

                {{-- Group Tombol Eksekutor (Hanya Tampil Jika DRAFT) --}}
                @if(!$hasApprovals && ($statusSlug === 'draft' || empty($statusSlug)))
                    <div class="gap-2 border-start ms-1 ps-2 d-flex">
                        <a href="{{ route('stock-opnames.edit', $opname->id) }}" class="btn btn-warning text-dark btn-action-rounded">
                            <i class="bi bi-pencil-square me-1"></i> Input Fisik
                        </a>

                        {{-- Tombol Ajukan (Panggil fungsi confirmSubmit) --}}
                        <form action="{{ route('stock-opnames.submit-approval', $opname->id) }}" method="POST" id="formSubmitApproval" class="m-0">
                            @csrf
                            <button type="button" onclick="confirmSubmit()" class="btn btn-primary btn-action-rounded">
                                <i class="bi bi-send-check me-1"></i> Ajukan
                            </button>
                        </form>

                        {{-- Tombol Batalkan / Hapus (Panggil fungsi confirmCancel) --}}
                        <form action="{{ route('stock-opnames.destroy', $opname->id) }}" method="POST" id="formCancelOpname" class="m-0">
                            @csrf
                            @method('DELETE')
                            <button type="button" onclick="confirmCancel()" class="btn btn-outline-danger btn-action-rounded btn-icon-only" title="Batalkan Sesi">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                @endif

                {{-- Group Tombol Approver (Hanya Tampil Jika Ada Antrean & User Berwenang) --}}
                @if($canApprove)
                    <div class="gap-2 border-start ms-1 ps-2 d-flex">
                        <div class="gap-2 d-flex">
                        {{-- FORM SETUJUI (Menggunakan Animasi SweetAlert) --}}
                        {{-- FORM SETUJUI --}}
                        <form action="{{ route('stock-opnames.approve', $opname->id) }}" method="POST" id="form-approve" class="m-0">
                            @csrf
                            {{-- Tambahkan parameter 'approve' ke dalam fungsi --}}
                            <button type="button" class="shadow-sm btn btn-success btn-action-rounded" onclick="triggerSweetAlert('approve')">
                                <i class="bi bi-check-circle me-1"></i> Setujui
                            </button>
                        </form>

                        {{-- FORM TOLAK --}}
                        <form action="{{ route('stock-opnames.reject', $opname->id) }}" method="POST" id="form-reject" class="m-0">
                            @csrf
                            {{-- Tambahkan parameter 'reject' ke dalam fungsi --}}
                            <button type="button" class="shadow-sm btn btn-danger btn-action-rounded" onclick="triggerSweetAlert('reject')">
                                <i class="bi bi-x-circle me-1"></i> Tolak
                            </button>
                        </form>
                    </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- NOTIFIKASI SYSTEM --}}
    @if(session('success'))
        <div class="p-3 mb-4 border-0 shadow-sm alert alert-success rounded-4 fw-bold d-flex align-items-center">
            <i class="bi bi-check-circle-fill fs-4 me-3"></i> <div>{{ session('success') }}</div>
        </div>
    @endif
    @if(session('error'))
        <div class="p-3 mb-4 border-0 shadow-sm alert alert-danger rounded-4 fw-bold d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i> <div>{{ session('error') }}</div>
        </div>
    @endif

    {{-- ========================================================================= --}}
    {{-- 3. INFORMASI SESI & KPI KARTU (VALUASI & AKURASI) --}}
    {{-- ========================================================================= --}}
    <div class="mb-4 row g-4">
        {{-- METADATA SESI --}}
        <div class="col-xl-3 col-lg-4">
            <div class="border-0 shadow-sm card rounded-4 h-100">
                <div class="py-3 bg-white card-header border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-info-circle me-2 text-primary"></i>Informasi Sesi</h6>
                </div>
                <div class="p-4 card-body">
                    <table class="table mb-0 table-sm table-borderless">
                        <tr>
                            <td class="text-muted small ps-0" width="45%">Entitas / PT</td>
                            <td class="fw-bold text-dark pe-0">: {{ optional($opname->company)->name ?? 'Head Office' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small ps-0">Lokasi Gudang</td>
                            <td class="fw-bold text-primary pe-0">: <i class="bi bi-shop me-1"></i> {{ optional($opname->warehouse)->name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small ps-0">Tgl Audit</td>
                            <td class="fw-semibold text-dark pe-0">: {{ \Carbon\Carbon::parse($opname->start_date)->format('d M Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small ps-0">Auditor</td>
                            <td class="fw-semibold text-dark pe-0">: {{ optional($opname->creator)->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small ps-0">Tgl Lock</td>
                            <td class="fw-semibold text-dark pe-0">: {{ $opname->end_date ? \Carbon\Carbon::parse($opname->end_date)->format('d M Y') : '-' }}</td>
                        </tr>
                    </table>

                    @if($opname->notes)
                        <div class="pt-3 mt-3 border-top">
                            <label class="mb-2 text-muted small fw-bold d-block">Instruksi Auditor:</label>
                            <div class="p-2 border bg-light rounded-3 small fst-italic text-secondary">{{ $opname->notes }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- RINGKASAN FINANSIAL & VALUASI PERSEDIAAN (4 KOTAK KPI) --}}
        <div class="col-xl-9 col-lg-8">
            <div class="row g-3 h-100 align-items-stretch">
                {{-- KPI 1: VALUASI SISTEM --}}
                <div class="col-sm-6 col-xl-3">
                    <div class="p-3 border-0 shadow-sm card rounded-4 h-100 card-kpi card-kpi-system d-flex flex-column justify-content-between">
                        <div>
                            <div class="mb-2 d-flex justify-content-between align-items-center">
                                <span class="text-muted small fw-bold text-uppercase">Valuasi Sistem</span>
                                <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-circle"><i class="bi bi-laptop"></i></div>
                            </div>
                            <h4 class="mb-1 fw-bolder text-primary text-truncate" title="Rp {{ number_format($opname->total_system_value, 0, ',', '.') }}">
                                Rp {{ number_format($opname->total_system_value, 0, ',', '.') }}
                            </h4>
                        </div>
                        <div class="pt-2 mt-3 border-top border-primary-subtle text-muted small d-flex justify-content-between align-items-center">
                            <span>Total Barang:</span>
                            <strong class="text-primary">{{ number_format($opname->items->sum('system_qty'), 0, ',', '.') }} Unit</strong>
                        </div>
                    </div>
                </div>

                {{-- KPI 2: VALUASI FISIK --}}
                <div class="col-sm-6 col-xl-3">
                    <div class="p-3 border-0 shadow-sm card rounded-4 h-100 card-kpi card-kpi-actual d-flex flex-column justify-content-between">
                        <div>
                            <div class="mb-2 d-flex justify-content-between align-items-center">
                                <span class="text-muted small fw-bold text-uppercase">Valuasi Fisik</span>
                                <div class="p-2 bg-success bg-opacity-10 text-success rounded-circle"><i class="bi bi-box-seam-fill"></i></div>
                            </div>
                            <h4 class="mb-1 fw-bolder text-success text-truncate" title="Rp {{ number_format($opname->total_actual_value, 0, ',', '.') }}">
                                Rp {{ number_format($opname->total_actual_value, 0, ',', '.') }}
                            </h4>
                        </div>
                        <div class="pt-2 mt-3 border-top border-success-subtle text-muted small d-flex justify-content-between align-items-center">
                            <span>Hasil Hitung:</span>
                            <strong class="text-success">{{ number_format($opname->items->sum('actual_qty'), 0, ',', '.') }} Unit</strong>
                        </div>
                    </div>
                </div>

                {{-- KPI 3: VALUASI VARIANCE --}}
                <div class="col-sm-6 col-xl-3">
                    @php
                        $netVarianceRupiah = $opname->total_actual_value - $opname->total_system_value;
                        $netVarianceQty = $opname->items->sum('variance_qty');
                        $isLoss = $netVarianceRupiah < 0;
                        $isGain = $netVarianceRupiah > 0;
                    @endphp
                    <div class="p-3 border-0 shadow-sm card rounded-4 h-100 card-kpi card-kpi-variance d-flex flex-column justify-content-between">
                        <div>
                            <div class="mb-2 d-flex justify-content-between align-items-center">
                                <span class="text-muted small fw-bold text-uppercase">Total Selisih</span>
                                <div class="p-2 bg-danger bg-opacity-10 text-danger rounded-circle"><i class="bi bi-calculator-fill"></i></div>
                            </div>
                            <h4 class="fw-bolder {{ $isLoss ? 'text-danger' : ($isGain ? 'text-primary' : 'text-success') }} mb-1 text-truncate" title="Rp {{ number_format($opname->total_variance_value, 0, ',', '.') }}">
                                Rp {{ number_format($opname->total_variance_value, 0, ',', '.') }}
                            </h4>
                        </div>
                        <div class="pt-2 mt-3 border-top border-danger-subtle small d-flex justify-content-between align-items-center">
                            <span class="text-muted">Net Delta:</span>
                            <strong class="{{ $isLoss ? 'text-danger' : ($isGain ? 'text-primary' : 'text-success') }}">
                                {{ $netVarianceQty > 0 ? '+' : '' }}{{ number_format($netVarianceQty, 0, ',', '.') }} Unit
                            </strong>
                        </div>
                    </div>
                </div>

                {{-- KPI 4: TINGKAT AKURASI --}}
                <div class="col-sm-6 col-xl-3">
                    @php
                        $sysQty = (float) $opname->items->sum('system_qty');
                        $absVarQty = (float) $opname->items->sum(function($i) { return abs((float)$i->variance_qty); });
                        $accuracy = $sysQty > 0 ? max(0, 100 - (($absVarQty / $sysQty) * 100)) : ($absVarQty > 0 ? 0 : 100);
                        $accColor = $accuracy >= 98 ? 'success' : ($accuracy >= 85 ? 'warning' : 'danger');
                    @endphp
                    <div class="p-3 border-0 shadow-sm card rounded-4 h-100 card-kpi d-flex flex-column justify-content-between" style="border-left-color: var(--bs-{{ $accColor }}); background-color: var(--bs-{{ $accColor }}-bg-subtle);">
                        <div>
                            <div class="mb-2 d-flex justify-content-between align-items-center">
                                <span class="text-muted small fw-bold text-uppercase">Tingkat Akurasi</span>
                                <div class="p-2 bg-{{ $accColor }} bg-opacity-10 text-{{ $accColor }} rounded-circle"><i class="bi bi-bullseye"></i></div>
                            </div>
                            <h4 class="mb-1 fw-bolder text-{{ $accColor }}">
                                {{ number_format($accuracy, 1, ',', '') }}%
                            </h4>
                        </div>
                        <div class="pt-2 mt-3 border-top border-{{ $accColor }}-subtle small d-flex justify-content-between align-items-center">
                            <span class="text-muted">Kondisi:</span>
                            <strong class="text-{{ $accColor }}">
                                {{ $accuracy >= 98 ? 'Sangat Baik' : ($accuracy >= 85 ? 'Cukup' : 'Kritis/Buruk') }}
                            </strong>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- 4. TABEL DETAIL BARANG & HASIL HITUNG FISIK --}}
    {{-- ========================================================================= --}}
    <div class="mb-4 overflow-hidden border-0 shadow-sm card rounded-4">
        <div class="py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-table me-2 text-primary"></i>Rincian Hasil Audit Persediaan Gudang</h6>
            <span class="border shadow-sm badge bg-light text-dark">Total: {{ $opname->items->count() }} Jenis Barang</span>
        </div>
        <div class="p-0 card-body">
            <div class="table-responsive">
                <table class="table mb-0 align-middle table-hover">
                    <thead class="table-light text-muted small text-uppercase fw-bold border-bottom">
                        <tr>
                            <th class="py-3 ps-4" width="5%">No</th>
                            <th width="12%">Kode</th>
                            <th width="23%">Nama Barang</th>
                            <th class="text-end" width="10%">HPP/Unit (Rp)</th>
                            <th class="text-center" width="10%">Stok Sistem</th>
                            <th class="text-center bg-warning-subtle text-dark" width="10%">Hitung Fisik</th>
                            <th class="text-center" width="10%">Selisih Qty</th>
                            <th class="text-end" width="10%">Selisih Rp</th>
                            <th class="pe-4" width="10%">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($opname->items as $idx => $item)
                            @php
                                $vQty = (float) $item->variance_qty;
                                $vVal = (float) $item->variance_value;
                                $rowClass = $vQty < 0 ? 'table-danger-subtle' : ($vQty > 0 ? 'table-info-subtle' : '');
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td class="py-3 ps-4 text-muted fw-bold">{{ $idx + 1 }}</td>
                                <td><span class="border badge bg-secondary-subtle text-secondary font-monospace">{{ optional($item->item)->code ?? '-' }}</span></td>
                                <td>
                                    <div class="fw-bold text-dark">{{ optional($item->item)->name ?? 'Item Terhapus' }}</div>
                                    <div class="text-muted" style="font-size:0.75rem;">Satuan: {{ $item->base_uom }}</div>
                                </td>
                                <td class="text-end fw-semibold">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                <td class="text-center fw-semibold text-secondary">
                                    {{ (float) $item->system_qty }} <span class="small fw-normal">{{ $item->base_uom }}</span>
                                </td>
                                <td class="text-center bg-warning-subtle fw-bolder text-dark fs-6">
                                    {{ (float) $item->actual_qty }} <span class="small fw-normal">{{ $item->base_uom }}</span>
                                </td>
                                <td class="text-center fw-bolder">
                                    @if($vQty == 0)
                                        <span class="px-2 py-1 border badge bg-success-subtle text-success border-success-subtle"><i class="bi bi-check-circle me-1"></i> Cocok</span>
                                    @elseif($vQty < 0)
                                        <span class="text-danger"><i class="bi bi-arrow-down-right"></i> {{ $vQty }} <span class="small">{{ $item->base_uom }}</span></span>
                                    @else
                                        <span class="text-primary"><i class="bi bi-arrow-up-right"></i> +{{ $vQty }} <span class="small">{{ $item->base_uom }}</span></span>
                                    @endif
                                </td>
                                <td class="text-end fw-bold">
                                    @if($vVal == 0)
                                        <span class="text-muted">Rp 0</span>
                                    @elseif($vVal < 0)
                                        <span class="text-danger">- Rp {{ number_format(abs($vVal), 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-primary">+ Rp {{ number_format($vVal, 0, ',', '.') }}</span>
                                    @endif
                                </td>
                                <td class="pe-4 small text-muted fst-italic">
                                    {{ $item->notes ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-5 text-center text-muted">
                                    <i class="mb-2 opacity-25 bi bi-inbox fs-1 d-block"></i>
                                    Tidak ada persediaan untuk dihitung di gudang ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light fw-bold border-top">
                        <tr>
                            <td colspan="4" class="py-3 ps-4 text-end text-uppercase text-muted">Total Keseluruhan :</td>
                            <td class="text-center text-dark">{{ (float) $opname->items->sum('system_qty') }}</td>
                            <td class="text-center bg-warning-subtle text-dark">{{ (float) $opname->items->sum('actual_qty') }}</td>
                            <td class="text-center text-dark">{{ $netVarianceQty > 0 ? '+' : '' }}{{ (float) $netVarianceQty }}</td>
                            <td class="text-end text-danger fs-6">Rp {{ number_format($opname->total_variance_value, 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- 5. AREA BUKTI FISIK & WORKFLOW PERSETUJUAN --}}
    {{-- ========================================================================= --}}
    <div class="row g-4">
        {{-- BUKTI FISIK --}}
        <div class="col-md-5">
            <div class="border-0 shadow-sm card rounded-4 h-100">
                <div class="py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-paperclip me-2 text-primary"></i>Dokumen Bukti Fisik</h6>
                </div>
                <div class="p-4 card-body">
                    @if(isset($opname->attachments) && $opname->attachments->count() > 0)
                        <div class="gap-2 d-flex flex-column">
                            @foreach($opname->attachments as $file)
                                <div class="p-2 px-3 border shadow-sm rounded-pill bg-light d-flex justify-content-between align-items-center">
                                    <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="text-decoration-none fw-bold text-dark text-truncate small" style="max-width: 75%;">
                                        <i class="bi bi-file-earmark-pdf-fill text-danger me-2 fs-5"></i> {{ $file->file_name }}
                                    </a>
                                    <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="px-3 btn btn-sm btn-primary rounded-pill fw-semibold">Buka</a>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-4 text-center text-muted small">
                            <i class="mb-1 opacity-25 bi bi-cloud-upload fs-1 d-block"></i>
                            Belum ada bukti fisik yang diunggah.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- WORKFLOW APPROVAL MATRIX --}}
        <div class="col-md-7">
            <div class="border-0 shadow-sm card rounded-4 h-100">
                <div class="py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-shield-check me-2 text-primary"></i>Rute Persetujuan Penyesuaian Stok</h6>
                </div>
                <div class="p-4 card-body">
                    @if(isset($opname->approvals) && $opname->approvals->count() > 0)
                        <div class="table-responsive">
                            <table class="table align-middle table-sm table-borderless">
                                <thead class="border-bottom small text-muted">
                                    <tr>
                                        <th class="ps-2">Lvl</th>
                                        <th>Role / Jabatan</th>
                                        <th>Penyetuju</th>
                                        <th>Status</th>
                                        <th>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($opname->approvals as $app)
                                        <tr>
                                            <td class="ps-2"><span class="px-2 py-1 border shadow-sm badge bg-light text-dark rounded-circle">{{ $app->step_order ?? 1 }}</span></td>
                                            <td class="fw-bold text-dark">{{ optional($app->role)->name ?? 'Approver' }}</td>
                                            <td>{{ optional($app->approver)->name ?? '-' }}</td>
                                            <td>
                                                {{-- 🔥 PERBAIKAN CASE SENSITIVITY STATUS 🔥 --}}
                                                @php $appStatus = strtoupper($app->status ?? ''); @endphp
                                                @if($appStatus === 'APPROVED')
                                                    <span class="px-2 py-1 border badge bg-success-subtle text-success border-success-subtle"><i class="bi bi-check-lg me-1"></i> Disetujui</span>
                                                @elseif($appStatus === 'REJECTED')
                                                    <span class="px-2 py-1 border badge bg-danger-subtle text-danger border-danger-subtle"><i class="bi bi-x-lg me-1"></i> Ditolak</span>
                                                @else
                                                    <span class="px-2 py-1 border badge bg-warning-subtle text-warning border-warning-subtle"><i class="bi bi-clock me-1"></i> Menunggu</span>
                                                @endif
                                            </td>
                                            <td class="small text-muted">{{ $app->approved_at ? \Carbon\Carbon::parse($app->approved_at)->format('d/m/Y H:i') : '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="py-4 text-center text-muted small">
                            <i class="mb-1 opacity-25 bi bi-diagram-3 fs-1 d-block"></i>
                            Persetujuan akan dibentuk otomatis berdasarkan <strong>Total Nilai Selisih</strong> setelah dokumen diajukan.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>


@push('scripts')
<!-- Panggil Library SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Fungsi Tunggal Anti-Gagal untuk Semua Tombol Aksi
    function triggerSweetAlert(action) {
        let titleText = '';
        let descText = '';
        let iconType = '';
        let btnColor = '';
        let btnText = '';
        let formId = '';

        // Tentukan pesan berdasarkan tombol yang diklik
        if (action === 'approve') {
            titleText = 'Setujui Dokumen?';
            descText = 'Apakah Anda yakin ingin menyetujui dokumen Audit Stok ini?';
            iconType = 'question';
            btnColor = '#198754'; // Hijau
            btnText = '<i class="bi bi-check-circle me-1"></i> Ya, Setujui';
            formId = 'form-approve';
        } else if (action === 'reject') {
            titleText = 'Tolak Dokumen?';
            descText = 'Apakah Anda yakin ingin menolak dokumen ini? Proses akan dihentikan.';
            iconType = 'warning';
            btnColor = '#dc3545'; // Merah
            btnText = '<i class="bi bi-x-circle me-1"></i> Ya, Tolak';
            formId = 'form-reject';
        }

        // Tampilkan SweetAlert
        Swal.fire({
            title: titleText,
            text: descText,
            icon: iconType,
            showCancelButton: true,
            confirmButtonColor: btnColor,
            cancelButtonColor: '#6c757d',
            confirmButtonText: btnText,
            cancelButtonText: 'Batal',
            reverseButtons: true,
            customClass: {
                confirmButton: 'rounded-pill px-4 shadow-sm m-1',
                cancelButton: 'rounded-pill px-4 m-1'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Submit form sesuai ID yang dipilih
                document.getElementById(formId).submit();
            }
        });
    }

    // Fungsi untuk tombol Ajukan dan Batalkan (Jika ada)
    function confirmSubmit() {
        triggerAction('formSubmitApproval', 'Ajukan Dokumen?', 'Dokumen akan dikunci dan diajukan.', 'info', '#0d6efd', 'Ya, Ajukan');
    }

    function confirmCancel() {
        triggerAction('formCancelOpname', 'Batalkan Sesi?', 'Data audit akan dihapus permanen!', 'warning', '#dc3545', 'Ya, Hapus');
    }

    function triggerAction(formId, title, text, icon, color, btnText) {
        Swal.fire({
            title: title, text: text, icon: icon, showCancelButton: true,
            confirmButtonColor: color, cancelButtonColor: '#6c757d',
            confirmButtonText: btnText, cancelButtonText: 'Batal', reverseButtons: true,
            customClass: { confirmButton: 'rounded-pill px-4 shadow-sm m-1', cancelButton: 'rounded-pill px-4 m-1' }
        }).then((result) => {
            if (result.isConfirmed) document.getElementById(formId).submit();
        });
    }
</script>
@endpush

@endsection
