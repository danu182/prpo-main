@extends('layouts.app')

@section('content')
<div class="container-fluid pb-5 text-dark">

    <div class="flex-wrap gap-3 mb-4 d-flex justify-content-between align-items-center">
        <div>
            <a href="{{ route('items.index') }}" class="mb-2 text-decoration-none text-muted small fw-bold d-inline-block">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Katalog Barang
            </a>
            <h3 class="mb-0 fw-bold text-dark">
                <i class="bi bi-clock-history text-primary me-2"></i> Riwayat Import Barang
            </h3>
            <div class="mt-1 text-secondary small">Daftar pengajuan import master data barang.</div>
        </div>
        <div>
            <a href="{{ route('items.import') }}" class="px-4 shadow-sm btn btn-success rounded-pill fw-bold">
                <i class="bi bi-plus-lg me-1"></i> Buat Pengajuan Baru
            </a>
        </div>
    </div>

    <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-primary">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="py-3 ps-4">No. Dokumen</th>
                        <th class="py-3 text-center">Tanggal</th>
                        <th class="py-3">Dibuat Oleh</th>
                        <th class="py-3 text-center">Jml Item</th>
                        <th class="py-3 text-center">Status</th>
                        <th class="py-3 pe-4 text-end">Opsi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($batches as $batch)
                    <tr>
                        <td class="py-3 ps-4 fw-bold text-dark">{{ $batch->batch_number }}</td>
                        <td class="py-3 text-center small">{{ $batch->created_at->format('d M Y H:i') }}</td>
                        <td class="py-3 small">{{ optional($batch->creator)->name ?? 'Sistem' }}</td>
                        <td class="py-3 text-center fw-bold">{{ $batch->details_count }}</td>
                        <td class="py-3 text-center">
                            @if($batch->statusInfo)
                                {{-- Jika teksnya warning/kuning, tulisan harus hitam agar terbaca --}}
                                @php $textColor = $batch->statusInfo->color == 'warning' ? 'text-dark' : 'text-white'; @endphp

                                <span class="badge bg-{{ $batch->statusInfo->color }} {{ $textColor }} px-3 rounded-pill shadow-sm">
                                    {{ strtoupper($batch->statusInfo->name) }}
                                </span>
                            @else
                                {{-- Cadangan jika data status belum dimasukkan ke database --}}
                                <span class="px-3 badge bg-secondary rounded-pill">{{ strtoupper($batch->status) }}</span>
                            @endif
                        </td>
                        <td class="py-3 pe-4 text-end">
                            <a href="{{ route('items.import_staging', $batch->id) }}" class="px-3 shadow-sm btn btn-sm btn-outline-primary rounded-pill fw-bold">
                                Buka Dokumen <i class="bi bi-box-arrow-in-up-right ms-1"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-5 text-center text-muted">
                            <i class="mb-3 opacity-50 bi bi-inboxes display-4 d-block"></i>
                            Belum ada riwayat pengajuan import.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($batches->hasPages())
        <div class="pt-3 pb-2 bg-white border-0 card-footer rounded-bottom-4">
            {{ $batches->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
