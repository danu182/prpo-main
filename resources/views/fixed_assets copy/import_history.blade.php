@extends('layouts.app')

@section('content')
<div class="pb-5 container-fluid text-dark" style="max-width: 1500px;">

    {{-- HEADER --}}
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <h3 class="mb-0 fw-bold">
            <i class="bi bi-clock-history text-primary me-2"></i> Riwayat Import Aset
        </h3>
        <a href="{{ route('fixed-assets.index') }}" class="shadow-sm btn btn-light border-secondary rounded-pill fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Aset
        </a>
    </div>

    {{-- TABEL RIWAYAT --}}
    <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-primary">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover table-striped">
                <thead class="bg-light small text-muted text-uppercase">
                    <tr>
                        <th class="py-3 ps-4">Tanggal Upload</th>
                        <th class="py-3">Nomor Batch & Status</th>
                        <th class="py-3">Diupload Oleh</th>
                        <th class="py-3 text-center">Dokumen Bukti</th>
                        <th class="py-3 pe-4 text-end">Aksi Detail & Cetak</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $batch)
                    <tr>
                        {{-- 1. TANGGAL UPLOAD --}}
                        <td class="py-3 ps-4">
                            <div class="fw-bold text-dark">{{ $batch->created_at->format('d M Y') }}</div>
                            <div class="small text-muted">{{ $batch->created_at->format('H:i:s') }} WIB</div>
                        </td>

                        {{-- 2. BATCH ID & STATUS --}}
                        <td class="py-3">
                            <a href="{{ route('fixed-assets.import_staging', $batch->id) }}" class="fw-bold text-primary text-decoration-none fs-6">
                                <i class="bi bi-file-earmark-spreadsheet me-1"></i> {{ $batch->batch_number }}
                            </a>
                            <div class="mt-1">
                                @php
                                    $statusColor = 'secondary';
                                    $statusName = strtoupper($batch->status);
                                    if($batch->status == 'waiting_approval') { $statusColor = 'warning text-dark'; $statusName = 'MENUNGGU APPROVAL'; }
                                    if($batch->status == 'approved') { $statusColor = 'success'; $statusName = 'DISETUJUI'; }
                                    if($batch->status == 'rejected') { $statusColor = 'danger'; $statusName = 'DITOLAK'; }
                                @endphp
                                <span class="badge bg-{{ $statusColor }} shadow-sm" style="font-size: 0.7rem;">
                                    {{ $statusName }}
                                </span>
                            </div>
                        </td>

                        {{-- 3. DIUPLOAD OLEH --}}
                        <td class="py-3">
                            <div class="fw-bold text-dark">
                                <i class="bi bi-person-circle text-muted me-1"></i>
                                {{ optional($batch->user)->name ?? 'System' }}
                            </div>
                        </td>

                        {{-- 4. DOKUMEN BUKTI --}}
                        <td class="py-3 text-center">
                            @if($batch->support_doc)
                                <a href="{{ asset('storage/' . $batch->support_doc) }}" target="_blank" class="p-2 shadow-sm badge bg-info text-dark text-decoration-none">
                                    <i class="bi bi-paperclip"></i> Lihat File
                                </a>
                            @else
                                <span class="small text-muted fst-italic">-</span>
                            @endif
                        </td>

                        {{-- 5. AKSI CETAK --}}
                        <td class="py-3 pe-4 text-end text-nowrap">
                            {{-- Tombol Detail diarahkan ke Ruang Karantina (karena sudah bisa menangani status Draft s/d Approved) --}}
                            <a href="{{ route('fixed-assets.import_staging', $batch->id) }}" class="px-3 text-white shadow-sm btn btn-sm btn-primary rounded-pill fw-bold me-1">
                                <i class="bi bi-shield-check me-1"></i> Ruang Karantina
                            </a>

                            {{-- Tombol Cetak BAST hanya muncul jika sudah di-Approve --}}
                            @if($batch->status == 'approved')
                            <div class="dropdown d-inline-block">
                                <button class="px-3 shadow-sm btn btn-sm btn-success rounded-pill fw-bold dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-printer-fill me-1"></i> Cetak
                                </button>
                                <ul class="border-0 shadow-sm dropdown-menu dropdown-menu-end rounded-3">
                                    <li>
                                        <a class="py-2 dropdown-item fw-medium text-success" href="{{ route('fixed-assets.print_bast_batch', $batch->batch_number) }}" target="_blank">
                                            <i class="bi bi-file-earmark-check me-2"></i> Cetak BAST Massal
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-5 text-center text-muted">
                            <i class="mb-2 opacity-50 bi bi-inbox fs-1 d-block"></i>
                            <span class="fw-bold">Belum ada riwayat import aset.</span>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINASI --}}
        @if($batches->hasPages())
        <div class="pt-3 pb-2 bg-white border-0 card-footer rounded-bottom-4">
            {{ $batches->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
