@extends('layouts.app')

@push('css')
<style>
    /* 🔥 KUSTOMISASI KARTU MODERN 🔥 */
    .summary-card {
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        background: #fff;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .summary-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    }
    .icon-wrapper {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    /* 🔥 KUSTOMISASI TABEL SAAS 🔥 */
    .card-table-wrapper {
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        background: #fff;
    }
    .table-modern { margin-bottom: 0; }
    .table-modern thead th {
        background-color: #f8fafc;
        color: #64748b;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1.2rem 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 700;
        border-top: none;
    }
    .table-modern tbody td {
        padding: 1.25rem 1.5rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 0.9rem;
    }
    .table-modern tbody tr { transition: all 0.2s ease; background-color: #ffffff; }
    .table-modern tbody tr:hover { background-color: #f8fafc !important; }
</style>
@endpush

@section('content')
{{-- 🔥 DIUBAH MENJADI FULL WIDTH (CONTAINER-FLUID) 🔥 --}}
<div class="pb-5 container-fluid text-dark px-md-4">

    {{-- Header Info Batch --}}
    <div class="mb-4 d-flex justify-content-between align-items-end">
        <div>
            <a href="{{ route('fixed-assets.import_history') }}" class="mb-3 border shadow-sm btn btn-sm btn-light rounded-pill fw-bold text-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Riwayat
            </a>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam text-primary me-2"></i> Detail Batch Import</h3>
            <div class="mt-1 text-muted small">Menampilkan rincian aset yang sukses masuk pada Batch: <span class="fw-bold text-primary">{{ $batch->batch_id }}</span></div>
        </div>

        <div class="flex-wrap gap-2 d-flex">
            <a href="{{ route('fixed-assets.print_mass_qr', $batch->batch_id) }}" target="_blank" class="px-4 shadow-sm btn btn-dark fw-bold rounded-pill">
                <i class="bi bi-qr-code me-1"></i> Cetak QR Massal
            </a>
            <a href="{{ route('fixed-assets.print_bast_batch', $batch->batch_id) }}" target="_blank" class="px-4 shadow-sm btn btn-danger fw-bold rounded-pill">
                <i class="bi bi-printer-fill me-1"></i> Cetak BAST Massal
            </a>
        </div>
    </div>

    {{-- Kotak Info Ringkasan Modern --}}
    <div class="mb-4 row g-4">
        <div class="col-xl-3 col-lg-6">
            <div class="p-3 summary-card h-100 d-flex align-items-center">
                <div class="icon-wrapper bg-info-subtle text-info me-3"><i class="bi bi-clock-history"></i></div>
                <div>
                    <div class="mb-1 text-muted small fw-bold text-uppercase">Waktu Eksekusi</div>
                    <div class="fw-bold text-dark fs-6">{{ $batch->created_at->format('d M Y, H:i') }} WIB</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6">
            <div class="p-3 summary-card h-100 d-flex align-items-center">
                <div class="icon-wrapper bg-primary-subtle text-primary me-3"><i class="bi bi-person-circle"></i></div>
                <div>
                    <div class="mb-1 text-muted small fw-bold text-uppercase">Dieksekusi Oleh</div>
                    <div class="fw-bold text-dark fs-6">{{ $batch->uploader->name ?? 'System' }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6">
            <div class="p-3 summary-card h-100 d-flex align-items-center">
                <div class="icon-wrapper bg-success-subtle text-success me-3"><i class="bi bi-boxes"></i></div>
                <div>
                    <div class="mb-1 text-muted small fw-bold text-uppercase">Total Aset Masuk</div>
                    <div class="fw-bold text-success fs-5">{{ $batch->total_items }} Unit</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6">
            <div class="p-3 summary-card h-100 d-flex align-items-center">
                <div class="icon-wrapper bg-warning-subtle text-warning me-3"><i class="bi bi-paperclip"></i></div>
                <div>
                    <div class="mb-1 text-muted small fw-bold text-uppercase">Dokumen Pendukung</div>
                    @if($batch->support_doc)
                        <a href="{{ asset('storage/' . $batch->support_doc) }}" target="_blank" class="px-3 py-1 mt-1 border shadow-sm badge bg-warning-subtle text-dark border-warning-subtle text-decoration-none rounded-pill">
                            <i class="bi bi-file-earmark-pdf-fill me-1 text-danger"></i> Lihat Dokumen
                        </a>
                    @else
                        <div class="mt-1 text-muted fst-italic small"><i class="bi bi-folder-x me-1"></i> Tidak ada lampiran</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel Daftar Aset Modern --}}
    <div class="mb-5 border-4 card-table-wrapper border-top border-primary">
        <div class="p-0 table-responsive">
            <table class="table align-middle table-modern" style="min-width: 1200px;">
                <thead>
                    <tr>
                        <th class="ps-4" width="5%">No</th>
                        <th width="22%">Identitas Aset</th>
                        <th width="25%">Rincian Barang & Lokasi</th>
                        <th width="15%">Info Perolehan</th>
                        <th width="15%">Status Saat Ini</th>
                        <th class="pe-4" width="18%">Dipegang Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $index => $asset)
                        <tr>
                            <td class="ps-4 text-muted fw-bold">{{ $assets->firstItem() + $index }}</td>

                            {{-- Identitas Aset --}}
                            <td>
                                <div class="mb-1 fw-bold text-dark">
                                    <i class="bi bi-tag-fill text-primary me-1"></i> {{ $asset->asset_number }}
                                </div>
                                <div class="mb-1 text-muted font-monospace" style="font-size: 0.75rem;">
                                    <i class="bi bi-upc-scan me-1"></i>S/N: <span class="fw-bold text-dark">{{ $asset->serial_number ?? '-' }}</span>
                                </div>
                                <div class="text-muted font-monospace" style="font-size: 0.75rem;">
                                    <i class="bi bi-tags me-1"></i>AKT: <span class="fw-bold text-info">{{ $asset->accounting_asset_number ?? '-' }}</span>
                                </div>
                            </td>

                            {{-- Rincian Barang & Lokasi --}}
                            <td>
                                <div class="mb-1 fw-bold text-primary">
                                    {{ $asset->name ?? optional($asset->item)->name ?? 'N/A' }}
                                </div>
                                <div class="flex-wrap gap-1 mb-1 d-flex">
                                    <span class="border shadow-sm badge bg-light text-dark" style="font-size: 0.65rem;">
                                        <i class="bi bi-building text-muted me-1"></i> {{ optional($asset->company)->name ?? 'Pusat' }}
                                    </span>
                                    <span class="border shadow-sm badge bg-light text-dark" style="font-size: 0.65rem;">
                                        <i class="bi bi-geo-alt-fill text-danger me-1"></i> {{ optional($asset->warehouse)->name ?? 'Gudang Utama' }}
                                    </span>
                                </div>
                            </td>

                            {{-- Info Perolehan --}}
                            <td>
                                <div class="mb-1 text-dark small fw-medium">
                                    <i class="bi bi-calendar-event text-muted me-1"></i>
                                    {{ $asset->acquisition_date ? \Carbon\Carbon::parse($asset->acquisition_date)->format('d M Y') : '-' }}
                                </div>
                                <div class="fw-bold text-success small">
                                    {{ optional($asset->currency)->symbol ?? 'Rp' }} {{ number_format($asset->purchase_price, 0, ',', '.') }}
                                </div>
                            </td>

                            {{-- Status Aset --}}
                            <td>
                                @if($asset->status)
                                    <span class="px-3 py-1 border shadow-sm badge bg-{{ $asset->status->color }}-subtle text-{{ $asset->status->color }} border-{{ $asset->status->color }}-subtle rounded-pill">
                                        {{ $asset->status->name }}
                                    </span>
                                @else
                                    <span class="px-3 py-1 border shadow-sm badge bg-secondary-subtle text-secondary border-secondary-subtle rounded-pill">
                                        Tidak Diketahui
                                    </span>
                                @endif
                            </td>

                            {{-- Dipegang Oleh --}}
                            <td class="pe-4">
                                @if($asset->assigned_to)
                                    <div class="mb-1 fw-bold text-dark small">
                                        <i class="bi bi-person-check-fill text-success me-1"></i> {{ optional($asset->assignee)->name ?? 'User Dihapus' }}
                                    </div>
                                    <div class="text-muted" style="font-size: 0.7rem;">
                                        <i class="bi bi-briefcase text-muted me-1"></i> {{ optional($asset->assignee)->job_title ?? 'Karyawan' }}
                                    </div>
                                @else
                                    <span class="px-2 py-1 border shadow-sm badge bg-light text-muted"><i class="bi bi-dash"></i> Belum Diserahkan</span>
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
