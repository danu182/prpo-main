@extends('layouts.app')

@section('content')
<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 fw-bold text-dark d-flex align-items-center">
            <i class="bi bi-clock-history text-primary me-2"></i> Riwayat Import Aset
        </h4>
        <a href="{{ route('fixed-assets.index') }}" class="btn btn-light border fw-bold shadow-sm rounded-pill px-4">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Aset
        </a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger shadow-sm border-start border-danger border-4">{{ session('error') }}</div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 border-top border-primary border-4">
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light fw-bold text-muted small border-bottom">
                    <tr>
                        <th class="py-3 ps-4">Tanggal Upload</th>
                        <th class="py-3">Batch ID</th>
                        <th class="py-3">Diupload Oleh</th>
                        <th class="py-3 text-center">Total Aset Masuk</th>
                        <th class="py-3 text-center">Dokumen Bukti</th>
                        <th class="py-3 pe-4 text-end">Aksi Cetak</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $batch)
                        <tr>
                            <td class="py-3 ps-4 small text-muted">
                                <div class="fw-bold text-dark">{{ $batch->created_at->format('d M Y') }}</div>
                                {{ $batch->created_at->format('H:i:s') }} WIB
                            </td>
                            {{-- Batch ID jadi Link Bisa Diklik --}}
                            <td class="py-3 small">
                                <a href="{{ route('fixed-assets.show_import_batch', $batch->batch_id) }}" class="fw-bold text-decoration-none text-primary">
                                    {{ $batch->batch_id }}
                                </a>
                            </td>
                            <td class="py-3 small fw-bold">
                                <i class="bi bi-person-circle text-muted me-1"></i> {{ $batch->uploader->name ?? 'System' }}
                            </td>
                            <td class="py-3 text-center fw-bold fs-6 text-success">{{ $batch->total_items }}</td>
                            <td class="py-3 text-center">
                                @if($batch->support_doc)
                                    <a href="{{ asset('storage/' . $batch->support_doc) }}" target="_blank" class="btn btn-sm btn-outline-info rounded-pill px-3 fw-bold">
                                        <i class="bi bi-file-earmark-pdf-fill"></i> Lihat
                                    </a>
                                @else
                                    <span class="text-muted fst-italic small">-</span>
                                @endif
                            </td>
                            <td class="py-3 pe-4 text-end">
                                <a href="{{ route('fixed-assets.show_import_batch', $batch->batch_id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold shadow-sm me-1">
                                    <i class="bi bi-eye-fill"></i> Detail
                                </a>
                                <a href="{{ route('fixed-assets.print_bast_batch', $batch->batch_id) }}" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold shadow-sm">
                                    <i class="bi bi-printer-fill me-1"></i> BAST
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-5 text-center text-muted">
                                <i class="bi bi-inbox display-4 d-block mb-3 opacity-50"></i>
                                Belum ada riwayat import aset.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white p-3 border-top rounded-bottom-4">
            {{ $batches->links() }}
        </div>
    </div>
</div>
@endsection
