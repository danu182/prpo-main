@extends('layouts.app')

@push('css')
<style>
    /* 🔥 TAMPILAN CLEAN & LOS (FLOATING ROWS) 🔥 */
    .table-los {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 12px; /* Jarak antar baris diperlebar sedikit agar lebih elegan */
    }

    .table-los thead th {
        background-color: transparent;
        color: #64748b;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 0.5rem 1.25rem;
        font-weight: 800;
        border: none;
    }

    .table-los tbody tr {
        background-color: #ffffff;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04), 0 1px 3px rgba(0, 0, 0, 0.03);
        border-radius: 14px;
        transition: all 0.25s ease;
        position: relative;
        z-index: 1; /* Default z-index */
    }

    /* 🔥 FIX DROPDOWN TERTUTUP: Baris yang di-hover maju ke depan 🔥 */
    .table-los tbody tr:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.08);
        z-index: 50; /* Maju ke depan agar dropdown tidak tertabrak baris bawahnya */
    }

    .table-los tbody td {
        padding: 1.25rem 1.25rem;
        vertical-align: middle;
        border: none;
        color: #334155;
    }

    /* Melengkungkan sudut baris kiri dan kanan */
    .table-los tbody tr td:first-child { border-top-left-radius: 14px; border-bottom-left-radius: 14px; }
    .table-los tbody tr td:last-child { border-top-right-radius: 14px; border-bottom-right-radius: 14px; }

    /* Badge & Tombol Modern */
    .badge-soft {
        padding: 0.5em 0.85em;
        font-weight: 700;
        border-radius: 8px;
        font-size: 0.725rem;
        letter-spacing: 0.2px;
    }

    .btn-action-los { transition: all 0.2s; }
    .btn-action-los:hover { transform: scale(1.05); }

    /* Memastikan dropdown selalu di atas */
    .dropdown-menu { z-index: 1050 !important; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important; }
</style>
@endpush

@section('content')
{{-- CONTAINER FULL WIDTH --}}
<div class="pb-5 container-fluid text-dark px-md-4">

    {{-- HEADER --}}
    <div class="mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h3 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3 me-3 d-inline-flex">
                    <i class="bi bi-clock-history fs-4"></i>
                </div>
                Riwayat Import Aset
            </h3>
            <div class="mt-2 text-muted small">Daftar riwayat proses unggah, karantina, dan validasi aset secara massal.</div>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="{{ route('fixed-assets.index') }}" class="bg-white border shadow-sm btn btn-white rounded-pill fw-bold text-secondary btn-action-los">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Master Aset
            </a>
        </div>
    </div>

    {{-- TABEL CLEAN (FLOATING ROWS) --}}
    <div class="pb-5 w-100 position-relative"> {{-- Tambahan pb-5 agar dropdown baris terakhir tidak kepotong layar --}}
        <table class="align-middle table-los">
            <thead>
                <tr>
                    <th class="ps-4" width="20%">Waktu Upload</th>
                    <th width="25%">Nomor Batch & Status</th>
                    <th width="20%">Diupload Oleh</th>
                    <th class="text-center" width="12%">Dokumen Bukti</th>
                    <th class="pe-4 text-end" width="23%">Aksi Detail & Cetak</th>
                </tr>
            </thead>
            <tbody>
                @forelse($batches as $batch)
                <tr>
                    {{-- 1. TANGGAL UPLOAD --}}
                    <td class="ps-4">
                        <div class="d-flex align-items-center">
                            <div class="p-2 border bg-light rounded-3 me-3 text-secondary border-secondary-subtle">
                                <i class="bi bi-calendar2-check fs-5"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark">{{ $batch->created_at->format('d M Y') }}</div>
                                <div class="small text-muted font-monospace" style="font-size: 0.78rem;">{{ $batch->created_at->format('H:i:s') }} WIB</div>
                            </div>
                        </div>
                    </td>

                    {{-- 2. BATCH ID & STATUS --}}
                    <td>
                        <a href="{{ route('fixed-assets.import_staging', $batch->id) }}" class="mb-2 fw-bold text-primary text-decoration-none fs-6 d-inline-block btn-action-los">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i> {{ $batch->batch_number }}
                        </a>
                        <div>
                            @php
                                $statusBg = 'bg-secondary-subtle';
                                $statusText = 'text-secondary';
                                $statusBorder = 'border-secondary-subtle';
                                $statusName = strtoupper($batch->status);

                                if(strtolower($batch->status) == 'waiting_approval') {
                                    $statusBg = 'bg-warning-subtle'; $statusText = 'text-warning-emphasis'; $statusBorder = 'border-warning-subtle'; $statusName = 'MENUNGGU APPROVAL';
                                }
                                elseif(strtolower($batch->status) == 'approved') {
                                    $statusBg = 'bg-success-subtle'; $statusText = 'text-success'; $statusBorder = 'border-success-subtle'; $statusName = 'DISETUJUI';
                                }
                                elseif(strtolower($batch->status) == 'rejected') {
                                    $statusBg = 'bg-danger-subtle'; $statusText = 'text-danger'; $statusBorder = 'border-danger-subtle'; $statusName = 'DITOLAK';
                                }
                            @endphp
                            <span class="badge badge-soft {{ $statusBg }} {{ $statusText }} border {{ $statusBorder }}">
                                @if(strtolower($batch->status) == 'waiting_approval') <i class="bi bi-hourglass-split me-1"></i>
                                @elseif(strtolower($batch->status) == 'approved') <i class="bi bi-check-circle-fill me-1"></i>
                                @elseif(strtolower($batch->status) == 'rejected') <i class="bi bi-x-circle-fill me-1"></i>
                                @endif
                                {{ $statusName }}
                            </span>
                        </div>
                    </td>

                    {{-- 3. DIUPLOAD OLEH --}}
                    <td>
                        <div class="fw-bold text-dark d-flex align-items-center">
                            <div class="text-white shadow-sm bg-primary rounded-circle d-flex align-items-center justify-content-center me-3 fw-bold" style="width: 38px; height: 38px; font-size: 0.9rem;">
                                {{ strtoupper(substr(optional($batch->user)->name ?? 'S', 0, 2)) }}
                            </div>
                            <div class="d-flex flex-column">
                                <span>{{ optional($batch->user)->name ?? 'Sistem Robot' }}</span>
                                <span class="text-muted fw-normal" style="font-size: 0.75rem;">Administrator</span>
                            </div>
                        </div>
                    </td>

                    {{-- 4. DOKUMEN BUKTI --}}
                    <td class="text-center">
                        @if($batch->support_doc)
                            <a href="{{ asset('storage/' . $batch->support_doc) }}" target="_blank" class="px-3 py-2 border shadow-sm badge bg-light text-primary text-decoration-none btn-action-los" title="Lihat Dokumen BAST/Pendukung">
                                <i class="bi bi-paperclip fs-6 me-1"></i> Lihat File
                            </a>
                        @else
                            <span class="px-3 py-2 border badge bg-light text-muted fw-normal"><i class="bi bi-dash"></i> Tidak Ada</span>
                        @endif
                    </td>

                    {{-- 5. AKSI CETAK --}}
                    <td class="pe-4 text-end text-nowrap">
                        <div class="gap-2 d-flex justify-content-end align-items-center">
                            <a href="{{ route('fixed-assets.import_staging', $batch->id) }}" class="px-3 shadow-sm btn btn-sm btn-primary rounded-pill fw-bold btn-action-los">
                                <i class="bi bi-shield-check me-1"></i> Karantina
                            </a>

                            @if(strtolower($batch->status) == 'approved')
                            <div class="dropdown d-inline-block">
                                {{-- 🔥 Tambahan data-bs-boundary="window" agar dropdown melayang tembus batasan elemen 🔥 --}}
                                <button class="shadow-sm btn btn-sm btn-success rounded-pill fw-bold dropdown-toggle btn-action-los" type="button" data-bs-toggle="dropdown" data-bs-boundary="window" aria-expanded="false">
                                    <i class="bi bi-printer-fill me-1"></i> Cetak Dokumen
                                </button>
                                <ul class="py-2 border-0 shadow-lg dropdown-menu dropdown-menu-end" style="min-width: 220px;">
                                    <li>
                                        <a class="py-2 dropdown-item fw-bold text-success" href="{{ route('fixed-assets.print_bast_batch', $batch->batch_number) }}" target="_blank">
                                            <i class="bi bi-file-earmark-check me-2"></i> BAST Serah Terima
                                        </a>
                                    </li>
                                    <li><hr class="my-1 dropdown-divider"></li>
                                    <li>
                                        <a class="py-2 dropdown-item fw-bold text-dark" href="{{ route('fixed-assets.print_mass_qr', $batch->batch_number) }}" target="_blank">
                                            <i class="bi bi-qr-code-scan me-2"></i> Cetak Label QR Massal
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="py-5 text-center bg-white border-0 shadow-sm text-muted rounded-4">
                        <div class="py-5">
                            <i class="mb-3 opacity-25 bi bi-inbox display-1 d-block text-secondary"></i>
                            <h5 class="fw-bold text-dark">Belum Ada Riwayat Import</h5>
                            <p class="mb-0 small text-muted">Semua proses import massal (karantina & approval) akan tercatat di sini.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- PAGINASI --}}
    @if($batches->hasPages())
    <div class="mt-2 d-flex justify-content-center">
        {{ $batches->links() }}
    </div>
    @endif
</div>
@endsection
