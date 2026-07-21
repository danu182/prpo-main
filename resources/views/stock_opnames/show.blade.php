@extends('layouts.app')

@push('css')
<style>
    .card-kpi {
        transition: all 0.25s ease-in-out;
        border-left: 5px solid transparent;
    }
    .card-kpi:hover {
        transform: translateY(-3px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.1) !important;
    }
    .card-kpi-system { border-left-color: #0d6efd; background-color: #f8faff; }
    .card-kpi-actual { border-left-color: #198754; background-color: #f6fff9; }
    .card-kpi-variance { border-left-color: #dc3545; background-color: #fff8f8; }

    .timeline-steps { display: flex; justify-content: space-around; flex-wrap: wrap; }
    .timeline-step { flex: 1; text-align: center; position: relative; min-width: 140px; }
    .timeline-step:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 20px;
        right: -50%;
        width: 100%;
        height: 2px;
        background-color: #dee2e6;
        z-index: 1;
    }
    .timeline-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        position: relative;
        z-index: 2;
        font-size: 1.1rem;
    }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid text-dark">

    {{-- ========================================================================= --}}
    {{-- 1. HEADER HALAMAN & TOMBOL AKSI UTAMA --}}
    {{-- ========================================================================= --}}
    <div class="gap-3 pb-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom">
        <div>
            <div class="gap-2 mb-1 d-flex align-items-center">
                <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-file-earmark-check text-primary me-2"></i> Detail Audit Stock Opname</h3>
                @php
                    $statusSlug = optional($opname->status)->slug ?? 'draft';
                    $statusName = optional($opname->status)->name ?? 'Draft / Menghitung';
                    $badgeClass = match($statusSlug) {
                        'draft' => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                        'pending_approval', 'pending' => 'bg-warning-subtle text-warning border-warning-subtle',
                        'approved' => 'bg-success-subtle text-success border-success-subtle',
                        'rejected' => 'bg-danger-subtle text-danger border-danger-subtle',
                        default => 'bg-light text-dark border'
                    };
                @endphp
                <span class="badge border px-3 py-2 rounded-pill fs-6 {{ $badgeClass }}">
                    <i class="bi bi-circle-fill me-1 small"></i> {{ strtoupper($statusName) }}
                </span>
            </div>
            <div class="text-muted small">
                Nomor Dokumen: <strong class="text-primary fs-6">{{ $opname->document_number }}</strong>
            </div>
        </div>

        <div class="flex-wrap gap-2 d-flex align-items-center">
            {{-- Tombol Kembali --}}
            <a href="{{ route('stock-opnames.index') }}" class="px-3 border btn btn-light fw-bold rounded-pill">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>

            {{-- Tombol Cetak Blind Count Sheet --}}
            <a href="{{ route('stock-opnames.print', $opname->id) }}" target="_blank" class="px-3 shadow-sm btn btn-outline-dark fw-bold rounded-pill">
                <i class="bi bi-printer me-1"></i> Cetak Lembar Hitung
            </a>

            {{-- Tombol Input/Edit Hasil Fisik (Hanya saat status DRAFT) --}}
            @if($statusSlug === 'draft')
                <a href="{{ route('stock-opnames.edit', $opname->id) }}" class="px-4 shadow-sm btn btn-warning fw-bold text-dark rounded-pill">
                    <i class="bi bi-pencil-square me-1"></i> Input Hasil Fisik
                </a>

                {{-- Tombol Ajukan Ke Worklist Approval --}}
                @if($opname->items->sum('actual_qty') > 0 || $opname->total_actual_value > 0)
                    <form action="{{ route('stock-opnames.submit-approval', $opname->id) }}" method="POST" class="d-inline" id="formSubmitApproval">
                        @csrf
                        <button type="button" onclick="confirmSubmitApproval()" class="px-4 shadow-sm btn btn-primary fw-bold rounded-pill">
                            <i class="bi bi-send-check me-1"></i> Ajukan Penyesuaian
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    {{-- NOTIFIKASI SYSTEM --}}
    @if(session('success'))
        <div class="p-3 mb-4 border-0 shadow-sm alert alert-success rounded-4 fw-bold d-flex align-items-center">
            <i class="bi bi-check-circle-fill fs-4 me-3"></i>
            <div>{{ session('success') }}</div>
        </div>
    @endif
    @if(session('error'))
        <div class="p-3 mb-4 border-0 shadow-sm alert alert-danger rounded-4 fw-bold d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
            <div>{{ session('error') }}</div>
        </div>
    @endif

    {{-- ========================================================================= --}}
    {{-- 2. INFORMASI ULASAN DOKUMEN & CARD VALUASI --}}
    {{-- ========================================================================= --}}
    <div class="mb-4 row g-4">
        {{-- METADATA SESI --}}
        <div class="col-xl-4 col-lg-5">
            <div class="border-0 shadow-sm card rounded-4 h-100">
                <div class="py-3 bg-white card-header border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-info-circle me-2 text-primary"></i>Informasi Sesi Audit</h6>
                </div>
                <div class="p-4 card-body">
                    <table class="table mb-0 table-sm table-borderless">
                        <tr>
                            <td class="text-muted small ps-0" width="40%">Entitas / PT</td>
                            <td class="fw-bold text-dark pe-0">: {{ optional($opname->company)->name ?? 'Head Office / Umum' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small ps-0">Lokasi Gudang</td>
                            <td class="fw-bold text-primary pe-0">: <i class="bi bi-shop me-1"></i> {{ optional($opname->warehouse)->name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small ps-0">Tgl Mulai Audit</td>
                            <td class="fw-semibold text-dark pe-0">: {{ \Carbon\Carbon::parse($opname->start_date)->format('d M Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small ps-0">Pembuat / Auditor</td>
                            <td class="fw-semibold text-dark pe-0">: {{ optional($opname->creator)->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small ps-0">Tgl Selesai / Lock</td>
                            <td class="fw-semibold text-dark pe-0">: {{ $opname->end_date ? \Carbon\Carbon::parse($opname->end_date)->format('d M Y') : '-' }}</td>
                        </tr>
                    </table>

                    @if($opname->notes)
                        <div class="pt-3 mt-3 border-top">
                            <label class="text-muted small fw-bold">Catatan / Instruksi Auditor:</label>
                            <div class="p-2 border bg-light rounded-3 small fst-italic text-secondary">{{ $opname->notes }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- RINGKASAN FINANSIAL & VALUASI PERSEDIAAN --}}
        <div class="col-xl-8 col-lg-7">
            <div class="row g-3 h-100 align-items-stretch">
                {{-- VALUASI SISTEM --}}
                <div class="col-md-4">
                    <div class="p-3 border-0 shadow-sm card rounded-4 h-100 card-kpi card-kpi-system d-flex flex-column justify-content-between">
                        <div>
                            <div class="mb-2 d-flex justify-content-between align-items-center">
                                <span class="text-muted small fw-bold text-uppercase">Valuasi Sistem</span>
                                <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-circle"><i class="bi bi-laptop"></i></div>
                            </div>
                            <h4 class="mb-1 fw-bolder text-primary">Rp {{ number_format($opname->total_system_value, 0, ',', '.') }}</h4>
                        </div>
                        <div class="pt-2 mt-3 border-top border-primary-subtle text-muted small">
                            Total Barang: <strong>{{ number_format($opname->items->sum('system_qty'), 0, ',', '.') }} Unit</strong>
                        </div>
                    </div>
                </div>

                {{-- VALUASI FISIK --}}
                <div class="col-md-4">
                    <div class="p-3 border-0 shadow-sm card rounded-4 h-100 card-kpi card-kpi-actual d-flex flex-column justify-content-between">
                        <div>
                            <div class="mb-2 d-flex justify-content-between align-items-center">
                                <span class="text-muted small fw-bold text-uppercase">Valuasi Fisik</span>
                                <div class="p-2 bg-success bg-opacity-10 text-success rounded-circle"><i class="bi bi-box-seam-fill"></i></div>
                            </div>
                            <h4 class="mb-1 fw-bolder text-success">Rp {{ number_format($opname->total_actual_value, 0, ',', '.') }}</h4>
                        </div>
                        <div class="pt-2 mt-3 border-top border-success-subtle text-muted small">
                            Hasil Hitung: <strong>{{ number_format($opname->items->sum('actual_qty'), 0, ',', '.') }} Unit</strong>
                        </div>
                    </div>
                </div>

                {{-- VALUASI VARIANCE / SELISIH --}}
                <div class="col-md-4">
                    @php
                        $netVarianceRupiah = $opname->total_actual_value - $opname->total_system_value;
                        $netVarianceQty = $opname->items->sum('variance_qty');
                        $isLoss = $netVarianceRupiah < 0;
                        $isGain = $netVarianceRupiah > 0;
                    @endphp
                    <div class="p-3 border-0 shadow-sm card rounded-4 h-100 card-kpi card-kpi-variance d-flex flex-column justify-content-between">
                        <div>
                            <div class="mb-2 d-flex justify-content-between align-items-center">
                                <span class="text-muted small fw-bold text-uppercase">Total Selisih (Absolut)</span>
                                <div class="p-2 bg-danger bg-opacity-10 text-danger rounded-circle"><i class="bi bi-calculator-fill"></i></div>
                            </div>
                            <h4 class="fw-bolder {{ $isLoss ? 'text-danger' : ($isGain ? 'text-primary' : 'text-success') }} mb-1">
                                Rp {{ number_format($opname->total_variance_value, 0, ',', '.') }}
                            </h4>
                        </div>
                        <div class="pt-2 mt-3 border-top border-danger-subtle small d-flex justify-content-between">
                            <span class="text-muted">Net Delta:</span>
                            <strong class="{{ $isLoss ? 'text-danger' : ($isGain ? 'text-primary' : 'text-success') }}">
                                {{ $netVarianceQty > 0 ? '+' : '' }}{{ number_format($netVarianceQty, 0, ',', '.') }} Unit
                            </strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- 3. TABEL DETAIL BARANG & HASIL HITUNG FISIK --}}
    {{-- ========================================================================= --}}
    <div class="mb-4 overflow-hidden border-0 shadow-sm card rounded-4">
        <div class="py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-table me-2 text-primary"></i>Rincian Hasil Audit Persediaan Gudang</h6>
            <span class="border badge bg-light text-dark">Total: {{ $opname->items->count() }} Jenis Barang</span>
        </div>
        <div class="p-0 card-body">
            <div class="table-responsive">
                <table class="table mb-0 align-middle table-hover">
                    <thead class="table-light text-muted small text-uppercase fw-bold border-bottom">
                        <tr>
                            <th class="py-3 ps-4" width="5%">No</th>
                            <th width="12%">Kode</th>
                            <th width="23%">Nama Barang</th>
                            <th class="text-end" width="10%">HPP / Unit (Rp)</th>
                            <th class="text-center" width="10%">Stok Sistem</th>
                            <th class="text-center bg-warning-subtle" width="10%">Hitung Fisik</th>
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
                                <td class="py-3 ps-4 text-muted small">{{ $idx + 1 }}</td>
                                <td><span class="border badge bg-secondary-subtle text-secondary font-monospace">{{ optional($item->item)->code ?? '-' }}</span></td>
                                <td>
                                    <div class="fw-bold text-dark">{{ optional($item->item)->name ?? 'Item Terhapus' }}</div>
                                    <small class="text-muted" style="font-size:0.75rem;">Satuan: {{ $item->base_uom }}</small>
                                </td>
                                <td class="text-end fw-semibold">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>

                                {{-- STOK SISTEM --}}
                                <td class="text-center fw-semibold text-secondary">
                                    {{ (float) $item->system_qty }} <small class="text-muted">{{ $item->base_uom }}</small>
                                </td>

                                {{-- FISIK AKTUAL --}}
                                <td class="text-center bg-warning-subtle fw-bolder text-dark fs-6">
                                    {{ (float) $item->actual_qty }} <small class="text-muted">{{ $item->base_uom }}</small>
                                </td>

                                {{-- SELISIH QTY --}}
                                <td class="text-center fw-bolder">
                                    @if($vQty == 0)
                                        <span class="border badge bg-success-subtle text-success border-success-subtle"><i class="bi bi-check-circle me-1"></i> Cocok</span>
                                    @elseif($vQty < 0)
                                        <span class="text-danger"><i class="bi bi-arrow-down-right"></i> {{ $vQty }} {{ $item->base_uom }}</span>
                                    @else
                                        <span class="text-primary"><i class="bi bi-arrow-up-right"></i> +{{ $vQty }} {{ $item->base_uom }}</span>
                                    @endif
                                </td>

                                {{-- SELISIH RUPIAH --}}
                                <td class="text-end fw-bold">
                                    @if($vVal == 0)
                                        <span class="text-muted">Rp 0</span>
                                    @elseif($vVal < 0)
                                        <span class="text-danger">- Rp {{ number_format(abs($vVal), 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-primary">+ Rp {{ number_format($vVal, 0, ',', '.') }}</span>
                                    @endif
                                </td>

                                {{-- KETERANGAN --}}
                                <td class="pe-4 small text-muted">
                                    {{ $item->notes ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-5 text-center text-muted">
                                    <i class="mb-2 opacity-50 bi bi-inbox fs-1 d-block text-secondary"></i>
                                    Tidak ada data persediaan untuk dihitung di gudang ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light fw-bold border-top">
                        <tr>
                            <td colspan="4" class="py-3 ps-4 text-uppercase">Total Keseluruhan</td>
                            <td class="text-center">{{ (float) $opname->items->sum('system_qty') }}</td>
                            <td class="text-center bg-warning-subtle">{{ (float) $opname->items->sum('actual_qty') }}</td>
                            <td class="text-center">{{ $netVarianceQty > 0 ? '+' : '' }}{{ (float) $netVarianceQty }}</td>
                            <td class="text-end text-danger">Rp {{ number_format($opname->total_variance_value, 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- 4. AREA BUKTI FISIK & WORKFLOW PERSETUJUAN --}}
    {{-- ========================================================================= --}}
    <div class="row g-4">
        {{-- BUKTI FISIK --}}
        <div class="col-md-5">
            <div class="border-0 shadow-sm card rounded-4 h-100">
                <div class="py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-paperclip me-2 text-primary"></i>Dokumen & Bukti Fisik Audit</h6>
                </div>
                <div class="p-4 card-body">
                    @if(isset($opname->attachments) && $opname->attachments->count() > 0)
                        <div class="gap-2 d-flex flex-column">
                            @foreach($opname->attachments as $file)
                                <div class="p-2 border rounded-3 bg-light d-flex justify-content-between align-items-center">
                                    <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="text-decoration-none fw-bold text-dark text-truncate small" style="max-width: 80%;">
                                        <i class="bi bi-file-earmark-pdf-fill text-danger me-2 fs-5"></i> {{ $file->file_name }}
                                    </a>
                                    <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="px-3 btn btn-sm btn-outline-primary rounded-pill">Buka</a>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-4 text-center text-muted small">
                            <i class="mb-1 opacity-50 bi bi-cloud-upload fs-2 d-block text-secondary"></i>
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
                                        <th>Level</th>
                                        <th>Role / Jabatan</th>
                                        <th>Penyetuju</th>
                                        <th>Status</th>
                                        <th>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($opname->approvals as $app)
                                        <tr>
                                            <td><span class="border badge bg-light text-dark rounded-circle">{{ $app->step_order ?? 1 }}</span></td>
                                            <td class="fw-bold text-dark">{{ optional($app->role)->name ?? 'Approver' }}</td>
                                            <td>{{ optional($app->approver)->name ?? '-' }}</td>
                                            <td>
                                                @if($app->status === 'APPROVED')
                                                    <span class="border badge bg-success-subtle text-success border-success-subtle"><i class="bi bi-check-lg"></i> Disetujui</span>
                                                @elseif($app->status === 'REJECTED')
                                                    <span class="border badge bg-danger-subtle text-danger border-danger-subtle"><i class="bi bi-x-lg"></i> Ditolak</span>
                                                @else
                                                    <span class="border badge bg-warning-subtle text-warning border-warning-subtle"><i class="bi bi-clock me-1"></i> Menunggu</span>
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
                            <i class="mb-1 opacity-50 bi bi-diagram-3 fs-2 d-block text-secondary"></i>
                            Persetujuan akan dibentuk otomatis berdasarkan Total Nilai Selisih setelah diajukan.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmSubmitApproval() {
        Swal.fire({
            title: 'Ajukan Hasil Opname?',
            html: "Sistem akan mengunci lembar hitung fisik ini dan mengirimkannya ke <b>Worklist Persetujuan Atasan</b> untuk eksekusi penyesuaian stok.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-send-check me-1"></i> Ya, Ajukan Sekarang!',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'rounded-pill px-4',
                cancelButton: 'rounded-pill px-4'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Mengajukan Dokumen...', allowOutsideClick: false, didOpen: () => { Swal.showLoading() } });
                document.getElementById('formSubmitApproval').submit();
            }
        });
    }
</script>
@endpush
