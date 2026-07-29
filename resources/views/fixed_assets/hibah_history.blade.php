@extends('layouts.app')

@push('css')
<style>
    /* 🔥 KUSTOMISASI TABEL SAAS MODERN 🔥 */
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
        padding: 1.2rem 1.25rem;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 700;
        border-top: none;
    }
    .table-modern tbody td {
        padding: 1.2rem 1.25rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 0.9rem;
    }
    .table-modern tbody tr.main-row { transition: all 0.2s ease; background-color: #ffffff; }
    .table-modern tbody tr.main-row:hover { background-color: #f8fafc !important; cursor: pointer; }

    /* Detail Child Panel (Collapse) */
    .collapse-row td { padding: 0 !important; border: none; background-color: #f8fafc; }
    .inner-collapse-box {
        margin: 0.75rem 1.25rem 1.25rem 1.25rem;
        padding: 1.25rem;
        background-color: #ffffff;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        border-left: 4px solid #f59e0b !important;
    }

    /* Badges & Button Hover */
    .badge-soft {
        padding: 0.45em 0.8em;
        font-weight: 700;
        border-radius: 8px;
        font-size: 0.725rem;
    }
    .btn-action { transition: transform 0.2s; }
    .btn-action:hover { transform: translateY(-2px); }
</style>
@endpush

@section('content')
{{-- 🔥 FULL WIDTH CONTAINER 🔥 --}}
<div class="pb-5 container-fluid text-dark px-md-4">

    {{-- HEADER --}}
    <div class="mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <a href="{{ route('fixed-assets.index') }}" class="mb-2 border shadow-sm btn btn-sm btn-light rounded-pill fw-bold text-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Register Aset
            </a>
            <h3 class="mb-0 fw-bold text-dark">
                <i class="bi bi-gift text-warning me-2"></i> Riwayat Penerimaan Manual & Hibah
            </h3>
            <div class="mt-1 text-muted small">Daftar rekapitulasi aset yang diregistrasikan secara manual atau melalui hibah.</div>
        </div>

        <div class="mt-3 mt-md-0">
            <a href="{{ route('fixed-assets.create_manual') }}" class="px-4 shadow-sm btn btn-primary rounded-pill fw-bold btn-action">
                <i class="bi bi-plus-circle me-1"></i> Input Manual / Hibah Baru
            </a>
        </div>
    </div>

    {{-- TABEL BATCH HIBAH MODERN --}}
    <div class="mb-5 border-4 card-table-wrapper border-top border-warning">
        <div class="p-0 table-responsive">
            <table class="table align-middle table-modern">
                <thead>
                    <tr>
                        <th class="ps-4" width="22%">No. Penerimaan (Batch)</th>
                        <th width="18%">Waktu Registrasi</th>
                        <th width="25%">Contoh Barang & Jumlah</th>
                        <th width="20%">Catatan Hibah</th>
                        <th class="pe-4 text-end" width="15%">Aksi & Dokumen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($hibahs as $index => $hibah)
                    {{-- BARIS INDUK --}}
                    <tr class="main-row">
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="p-2.5 bg-warning-subtle rounded-3 me-3 text-warning border border-warning-subtle">
                                    <i class="bi bi-box-seam-fill fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark fs-6">{{ $hibah->batch_id }}</div>
                                    <span class="mt-1 border badge bg-light text-muted font-monospace" style="font-size: 0.65rem;">MANUAL / HIBAH</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold text-dark">{{ \Carbon\Carbon::parse($hibah->created_at)->format('d M Y') }}</div>
                            <div class="small text-muted font-monospace"><i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($hibah->created_at)->format('H:i') }} WIB</div>
                        </td>
                        <td>
                            <div class="mb-1 fw-bold text-primary">{{ $hibah->sample_name }}</div>
                            <span class="border badge badge-soft bg-warning-subtle text-warning-emphasis border-warning-subtle">
                                <i class="bi bi-boxes me-1"></i>{{ $hibah->total_items }} Unit Aset
                            </span>
                        </td>
                        <td>
                            <span class="small text-muted fst-italic">{{ Str::limit($hibah->notes, 50) ?: 'Tidak ada catatan.' }}</span>
                        </td>
                        <td class="pe-4 text-end text-nowrap">
                            <div class="gap-2 d-inline-flex align-items-center">
                                @if($hibah->supporting_document)
                                    <a href="{{ asset('storage/' . $hibah->supporting_document) }}" target="_blank" class="shadow-sm btn btn-sm btn-outline-danger rounded-pill fw-bold btn-action" title="Lihat Dokumen BAST/Nota">
                                        <i class="bi bi-file-earmark-pdf-fill me-1"></i> BAST
                                    </a>
                                @endif

                                <button class="px-3 shadow-sm btn btn-sm btn-dark rounded-pill fw-bold btn-action" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $index }}" aria-expanded="false">
                                    Rincian <i class="bi bi-chevron-down ms-1"></i>
                                </button>
                            </div>
                        </td>
                    </tr>

                    {{-- BARIS RINCIAN ANAK (COLLAPSE) --}}
                    <tr class="collapse-row">
                        <td colspan="5">
                            <div class="collapse" id="collapse-{{ $index }}">
                                <div class="inner-collapse-box">
                                    <div class="mb-3 d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check text-warning me-2"></i>Daftar Aset pada Batch {{ $hibah->batch_id }}</h6>
                                        @if(!$hibah->supporting_document)
                                            <span class="border badge bg-light text-muted border-secondary-subtle"><i class="bi bi-exclamation-circle me-1"></i>Tanpa Dokumen Pendukung</span>
                                        @endif
                                    </div>

                                    @php
                                        $detailAssets = \App\Models\FixedAsset::with(['company', 'status'])->where('batch_id', $hibah->batch_id)->get();
                                    @endphp

                                    <div class="overflow-hidden border shadow-sm rounded-3 border-secondary-subtle">
                                        <table class="table mb-0 align-middle bg-white table-sm table-hover small">
                                            <thead class="bg-light text-muted fw-bold">
                                                <tr>
                                                    <th class="py-2 ps-3" width="5%">No</th>
                                                    <th class="py-2" width="22%">No. Aset / Akuntansi</th>
                                                    <th class="py-2" width="28%">Nama Spesifik Aset</th>
                                                    <th class="py-2" width="18%">Serial Number (S/N)</th>
                                                    <th class="py-2" width="12%">Milik PT</th>
                                                    <th class="py-2 text-end pe-3" width="15%">Nilai Beli / Wajar</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($detailAssets as $det)
                                                <tr>
                                                    <td class="py-2 ps-3 text-muted fw-bold">{{ $loop->iteration }}</td>
                                                    <td class="py-2">
                                                        <div class="fw-bold text-primary">{{ $det->asset_number }}</div>
                                                        <div class="text-muted font-monospace" style="font-size: 0.68rem;">FA: {{ $det->accounting_asset_number ?: '-' }}</div>
                                                    </td>
                                                    <td class="py-2 fw-bold text-dark">{{ $det->name }}</td>
                                                    <td class="py-2"><span class="font-monospace text-muted">{{ $det->serial_number ?: 'Belum diset' }}</span></td>
                                                    <td class="py-2">{{ optional($det->company)->name ?: '-' }}</td>
                                                    <td class="py-2 text-end pe-3 text-success fw-bold">Rp {{ number_format($det->purchase_price, 0, ',', '.') }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-5 text-center border-0 text-muted">
                            <div class="py-4">
                                <i class="mb-3 opacity-25 bi bi-gift display-1 d-block text-warning"></i>
                                <h5 class="fw-bold text-dark">Belum Ada Riwayat Hibah</h5>
                                <p class="mb-0 small text-muted">Data penerimaan aset manual atau hibah akan muncul di sini.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($hibahs->hasPages())
        <div class="p-3 bg-white border-top rounded-bottom-4">
            {{ $hibahs->links() }}
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    $(document).ready(function() {
        // Klik baris induk untuk expand/collapse detail
        $('.main-row').on('click', function(e) {
            if(e.target.tagName === 'BUTTON' || e.target.tagName === 'A' || $(e.target).closest('a').length || $(e.target).closest('button').length) {
                return;
            }
            let btn = $(this).find('[data-bs-toggle="collapse"]');
            if(btn.length) {
                btn.click();
            }
        });
    });
</script>
@endpush
