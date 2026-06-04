@extends('layouts.app')

@push('css')
<style>
    /* Desain Banner Profile Karyawan */
    .profile-banner {
        background: linear-gradient(135deg, #0dcaf0 0%, #087990 100%);
        position: relative;
        overflow: hidden;
    }
    .profile-banner::after {
        content: ''; position: absolute; top: -50%; right: -10%; width: 300px; height: 300px;
        background: rgba(255,255,255,0.1); border-radius: 50%;
    }
    .avatar-circle-lg {
        width: 65px; height: 65px; background: #ffffff; color: #087990;
        font-size: 1.5rem; font-weight: 800; display: flex; align-items: center; justify-content: center;
        border-radius: 50%; box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }

    /* Efek Tanggal ala Kalender */
    .date-badge {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px;
        width: 60px; height: 60px; box-shadow: inset 0 -3px 0 rgba(0,0,0,0.05);
    }
    .date-badge .day { font-size: 1.2rem; font-weight: 900; color: #212529; line-height: 1; }
    .date-badge .month { font-size: 0.65rem; font-weight: 700; color: #6c757d; text-transform: uppercase; margin-top: 2px; }

    /* Desain Chip Serial Number */
    .sn-chip {
        font-family: 'Courier New', Courier, monospace;
        font-size: 0.7rem; font-weight: 600; padding: 4px 8px;
        background-color: #f1f3f5; border: 1px solid #dee2e6;
        color: #495057; border-radius: 6px; display: inline-block;
        margin-bottom: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);
    }

    /* Hover Table */
    .table-hover tbody tr:hover { background-color: rgba(13, 202, 240, 0.03); transition: all 0.2s ease; }

    /* Scrollbar Minimalis */
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f8f9fa; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #dee2e6; border-radius: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #adb5bd; }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    {{-- HEADER: PROFILE BANNER --}}
    <div class="p-4 mb-4 text-white border-0 shadow-sm card rounded-4 profile-banner">
        <div class="position-relative z-1 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div class="d-flex align-items-center">
                <div class="avatar-circle-lg me-3 flex-shrink-0">
                    {{ strtoupper(substr($employee_name, 0, 2)) }}
                </div>
                <div>
                    <h3 class="mb-0 fw-bold">{{ $employee_name }}</h3>
                    <div class="mt-1 text-white-50 small fw-medium">
                        <i class="bi bi-person-lines-fill me-1"></i> Log Riwayat Serah Terima Aset & Inventaris
                    </div>
                </div>
            </div>
            <div class="mt-3 mt-md-0 d-flex align-items-center gap-2">
                <span class="px-3 py-2 border border-white shadow-sm badge bg-white text-info rounded-pill fw-bold border-opacity-25">
                    <i class="bi bi-radar me-1"></i> Tracking Active
                </span>
                <a href="{{ route('employee-inventories.index') }}" class="px-4 shadow-sm btn btn-dark rounded-pill fw-bold transition-all">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    {{-- TABEL HISTORY (MODERN LEDGER) --}}
    <div class="overflow-hidden border-0 border-4 shadow-sm card rounded-4 border-top border-info">
        <div class="px-4 py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history text-info me-2"></i> Timeline Transaksi</h6>
            <span class="badge bg-light text-muted border">{{ count($allHistories) }} Record(s)</span>
        </div>
        <div class="p-0 card-body">
            <div class="table-responsive">
                <table class="table mb-0 align-middle table-hover">
                    <thead class="bg-light text-muted small text-uppercase fw-bold border-bottom" style="letter-spacing: 0.5px;">
                        <tr>
                            <th class="py-3 ps-4 text-center" width="10%">Waktu</th>
                            <th class="py-3" width="15%">Transaksi</th>
                            <th class="py-3" width="30%">Rincian Barang</th>
                            <th class="py-3" width="20%">Kuantitas / S.N</th>
                            <th class="py-3 pe-4" width="25%">Catatan Referensi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($allHistories as $log)
                        <tr>
                            {{-- KOLOM 1: TANGGAL ALA KALENDER --}}
                            <td class="py-3 ps-4">
                                <div class="d-flex justify-content-center">
                                    <div class="date-badge" title="{{ \Carbon\Carbon::parse($log->date)->format('H:i') }} WIB">
                                        <span class="day">{{ \Carbon\Carbon::parse($log->date)->format('d') }}</span>
                                        <span class="month">{{ \Carbon\Carbon::parse($log->date)->format('M Y') }}</span>
                                    </div>
                                </div>
                                <div class="mt-1 text-center text-muted fw-bold" style="font-size: 0.65rem;">
                                    <i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($log->date)->format('H:i') }}
                                </div>
                            </td>

                            {{-- KOLOM 2: TRANSAKSI & KATEGORI --}}
                            <td class="py-3">
                                @if($log->action == 'TERIMA')
                                    <div class="mb-1">
                                        <span class="px-3 py-1 border badge bg-success-subtle text-success border-success-subtle rounded-pill fw-bold shadow-sm">
                                            <i class="bi bi-arrow-down-left me-1"></i> DITERIMA
                                        </span>
                                    </div>
                                @else
                                    <div class="mb-1">
                                        <span class="px-3 py-1 border badge bg-danger-subtle text-danger border-danger-subtle rounded-pill fw-bold shadow-sm">
                                            <i class="bi bi-arrow-up-right me-1"></i> DIKEMBALIKAN
                                        </span>
                                    </div>
                                @endif

                                @if($log->type == 'MAJOR')
                                    <span class="badge bg-light text-primary border border-light fw-semibold" style="font-size: 0.65rem;">
                                        <i class="bi bi-pc-display me-1"></i> Aset Tetap
                                    </span>
                                @else
                                    <span class="badge bg-light text-secondary border border-light fw-semibold" style="font-size: 0.65rem;">
                                        <i class="bi bi-box-seam me-1"></i> Inv. Minor
                                    </span>
                                @endif
                            </td>

                            {{-- KOLOM 3: NAMA BARANG --}}
                            <td class="py-3">
                                <div class="fw-bold text-dark fs-6">{{ $log->item_name }}</div>
                                <div class="mt-1 text-muted small fw-medium">
                                    <i class="bi bi-upc-scan me-1"></i> Kode: {{ $log->item_code }}
                                </div>
                            </td>

                            {{-- KOLOM 4: QTY / SN & TOMBOL PRINT --}}
                            <td class="py-3 align-middle">
                                @if($log->type == 'MINOR')
                                    <div class="mb-2 fw-bold fs-6 {{ str_contains($log->qty_or_sn, '+') ? 'text-success' : 'text-danger' }}">
                                        {{ $log->qty_or_sn }}
                                    </div>

                                    @if(!empty($log->sn_list))
                                        <div class="custom-scrollbar pe-1" style="max-height: 80px; overflow-y: auto;">
                                            @foreach($log->sn_list as $sn)
                                                <div class="sn-chip"><i class="bi bi-tag-fill text-warning me-1"></i>{{ $sn }}</div>
                                            @endforeach
                                        </div>
                                    @endif
                                @else
                                    <div class="sn-chip mb-2 fs-6 py-1 px-2 border-info bg-info-subtle text-info-emphasis">
                                        <i class="bi bi-shield-check me-1"></i> {{ $log->qty_or_sn }}
                                    </div>
                                @endif

                                {{-- TOMBOL PRINT QR (Rapi di bawah item) --}}
                                @if(isset($log->print_id) && $log->print_id)
                                    <div class="mt-2">
                                        @php
                                            $printRoute = ($log->type == 'MAJOR')
                                                ? route('fixed-assets.print_qr', $log->print_id)
                                                : route('employee-inventories.print_qr', $log->print_id);
                                        @endphp
                                        <a href="{{ $printRoute }}" target="_blank" class="btn btn-sm btn-outline-dark rounded-pill fw-bold" style="font-size: 0.65rem;" title="Cetak Label QR">
                                            <i class="bi bi-printer-fill me-1"></i> Print Label
                                        </a>
                                    </div>
                                @endif
                            </td>

                            {{-- KOLOM 5: CATATAN --}}
                            <td class="py-3 pe-4">
                                @if($log->type == 'MINOR')
                                    <div class="mb-1 badge bg-light text-dark border border-secondary-subtle">
                                        <i class="bi bi-file-earmark-text me-1"></i> Ref: {{ $log->reference_number }}
                                    </div>
                                @endif
                                <div class="p-2 mt-1 rounded bg-light border text-muted" style="font-size: 0.75rem; line-height: 1.5;">
                                    {!! nl2br(e($log->notes ?? 'Tidak ada catatan tambahan.')) !!}
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="py-5 text-center bg-light-subtle">
                                <div class="p-4 mx-auto max-w-sm">
                                    <i class="mb-3 opacity-25 bi bi-journal-x text-muted display-4 d-block"></i>
                                    <h6 class="fw-bold text-dark">Data Riwayat Kosong</h6>
                                    <p class="mb-0 text-muted small">Belum ada riwayat transaksi serah terima aset maupun inventaris untuk personil ini.</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
