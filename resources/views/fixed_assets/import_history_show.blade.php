@extends('layouts.app')

@push('css')
<style>
    .info-card {
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
    }
    .info-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
    }
    .border-left-primary { border-left-color: #0d6efd !important; }
    .border-left-success { border-left-color: #198754 !important; }
    .border-left-info { border-left-color: #0dcaf0 !important; }
    .border-left-warning { border-left-color: #ffc107 !important; }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    {{-- Header Info Batch --}}
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-box-seam text-primary me-2"></i> Detail Batch Import
            </h4>
            <div class="mt-1 text-muted small">
                Menampilkan rincian aset yang sukses masuk pada Batch: <span class="fw-bold text-primary">{{ $batch->batch_id }}</span>
            </div>
        </div>
        <div class="gap-2 d-flex flex-wrap">
            <a href="{{ route('fixed-assets.import_history') }}" class="px-4 border shadow-sm btn btn-light fw-bold rounded-pill">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>

            <a href="{{ route('fixed-assets.print_mass_qr', $batch->batch_id) }}" target="_blank" class="px-4 shadow-sm btn btn-dark fw-bold rounded-pill">
                <i class="bi bi-qr-code me-1"></i> Cetak QR Massal
            </a>

            <a href="{{ route('fixed-assets.print_bast_batch', $batch->batch_id) }}" target="_blank" class="px-4 shadow-sm btn btn-danger fw-bold rounded-pill">
                <i class="bi bi-printer-fill me-1"></i> Cetak BAST Massal
            </a>
        </div>
    </div>

    {{-- Kotak Info Ringkasan (Dipercantik dengan Hover Effect) --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 info-card border-left-info h-100 bg-white">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold text-uppercase mb-1"><i class="bi bi-clock-history me-1"></i> Waktu Eksekusi</div>
                    <div class="fw-bold text-dark fs-6">{{ $batch->created_at->format('d M Y, H:i') }} WIB</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 info-card border-left-primary h-100 bg-white">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold text-uppercase mb-1"><i class="bi bi-person-circle me-1"></i> Dieksekusi Oleh</div>
                    <div class="fw-bold text-dark fs-6">{{ $batch->uploader->name ?? 'System' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 info-card border-left-success h-100 bg-white">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold text-uppercase mb-1"><i class="bi bi-boxes me-1"></i> Total Aset Masuk</div>
                    <div class="fw-bold text-success fs-5">{{ $batch->total_items }} Unit</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 info-card border-left-warning h-100 bg-white">
                <div class="card-body p-3">
                    <div class="text-muted small fw-bold text-uppercase mb-1"><i class="bi bi-paperclip me-1"></i> Dokumen Pendukung</div>
                    @if($batch->support_doc)
                        <a href="{{ asset('storage/' . $batch->support_doc) }}" target="_blank" class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle text-decoration-none rounded-pill px-3 py-2 mt-1">
                            <i class="bi bi-file-earmark-pdf-fill me-1"></i> Lihat Dokumen
                        </a>
                    @else
                        <div class="text-muted fst-italic mt-1 fs-6">Tidak ada lampiran</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel Daftar Aset --}}
    <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-primary">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover" style="min-width: 1200px;">
                <thead class="bg-light fw-bold text-muted small border-bottom text-uppercase">
                    <tr>
                        <th class="py-3 ps-4" width="5%">No</th>
                        <th class="py-3" width="22%">Identitas Aset</th>
                        <th class="py-3" width="23%">Rincian Barang & Lokasi</th>
                        <th class="py-3" width="15%">Info Perolehan</th>
                        <th class="py-3" width="15%">Status Saat Ini</th>
                        <th class="py-3 pe-4" width="20%">Dipegang Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $index => $asset)
                        <tr>
                            <td class="py-3 ps-4 text-muted small fw-bold">{{ $assets->firstItem() + $index }}</td>

                            {{-- 🔥 Identitas Aset (Diperlengkap dengan Label Akuntansi) 🔥 --}}
                            <td class="py-3">
                                <div class="fw-bold text-dark fs-6 mb-1">
                                    <i class="bi bi-tag-fill text-primary me-1"></i> {{ $asset->asset_number }}
                                </div>
                                <div class="text-muted font-monospace mb-1" style="font-size: 0.75rem;">
                                    <i class="bi bi-upc-scan me-1"></i>S/N: <span class="fw-bold text-dark">{{ $asset->serial_number ?? 'KOSONG' }}</span>
                                </div>
                                <div class="text-muted font-monospace" style="font-size: 0.75rem;">
                                    <i class="bi bi-tags me-1"></i>AKT: <span class="fw-bold text-info">{{ $asset->accounting_asset_number ?? 'BELUM DISET' }}</span>
                                </div>
                            </td>

                            {{-- 🔥 Rincian Barang, PT, dan Gudang 🔥 --}}
                            <td class="py-3">
                                <div class="fw-bold text-primary mb-1">
                                    {{ $asset->name ?? optional($asset->item)->name ?? 'N/A' }}
                                </div>
                                <div class="d-flex flex-wrap gap-1 mb-1">
                                    <span class="badge bg-light text-dark border shadow-sm" style="font-size: 0.65rem;">
                                        <i class="bi bi-building text-muted me-1"></i> {{ optional($asset->company)->name ?? 'Pusat' }}
                                    </span>
                                    <span class="badge bg-light text-dark border shadow-sm" style="font-size: 0.65rem;">
                                        <i class="bi bi-geo-alt-fill text-danger me-1"></i> {{ optional($asset->warehouse)->name ?? 'Gudang Utama' }}
                                    </span>
                                </div>
                                <div class="text-muted fst-italic text-truncate" style="max-width: 250px; font-size: 0.7rem;" title="{{ $asset->spesifikasi_detail }}">
                                    {{ $asset->spesifikasi_detail ?? 'Tidak ada spesifikasi khusus.' }}
                                </div>
                            </td>

                            {{-- Info Perolehan (Dilengkapi simbol mata uang dinamis) --}}
                            <td class="py-3 small">
                                <div class="text-dark mb-1">
                                    <i class="bi bi-calendar-event text-muted me-1"></i>
                                    {{ $asset->acquisition_date ? \Carbon\Carbon::parse($asset->acquisition_date)->format('d M Y') : 'Tgl Kosong' }}
                                </div>
                                <div class="fw-bold text-success">
                                    {{ optional($asset->currency)->symbol ?? 'Rp' }} {{ number_format($asset->purchase_price, 0, ',', '.') }}
                                </div>
                            </td>

                            {{-- Status Aset --}}
                            <td class="py-3 small">
                                @if($asset->status)
                                    <span class="px-3 py-2 border shadow-sm badge bg-{{ $asset->status->color }}-subtle text-{{ $asset->status->color }} border-{{ $asset->status->color }}-subtle rounded-pill">
                                        {{ $asset->status->name }}
                                    </span>
                                @else
                                    <span class="px-3 py-2 border shadow-sm badge bg-secondary-subtle text-secondary border-secondary-subtle rounded-pill">
                                        Tidak Diketahui
                                    </span>
                                @endif
                            </td>

                            {{-- Pemegang (Peminjam) --}}
                            <td class="py-3 pe-4 small">
                                @if($asset->assigned_to)
                                    <div class="fw-bold text-dark mb-1">
                                        <i class="bi bi-person-check-fill text-success me-1"></i> {{ optional($asset->assignee)->name ?? 'User Dihapus' }}
                                    </div>
                                    <div class="text-muted" style="font-size: 0.7rem;">
                                        <i class="bi bi-briefcase text-muted me-1"></i> {{ optional($asset->assignee)->job_title ?? 'Karyawan' }}
                                    </div>
                                @else
                                    <span class="badge bg-light text-muted border px-2 py-1"><i class="bi bi-dash"></i> Belum Diserahkan</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-5 text-center text-muted">
                                <i class="mb-3 opacity-50 bi bi-box-seam display-4 d-block"></i>
                                Tidak ada data aset pada Batch ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 bg-white card-footer border-top rounded-bottom-4">
            {{ $assets->links() }}
        </div>
    </div>

</div>
@endsection
