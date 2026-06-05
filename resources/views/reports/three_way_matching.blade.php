@extends('layouts.app')

@push('css')
<style>
    .progress-sm {
        height: 6px;
        border-radius: 4px;
    }
    .table-hover tbody tr:hover {
        background-color: #f8f9fa;
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        transition: all 0.2s;
    }
    .badge-status {
        width: 100px;
        text-align: center;
        padding: 0.5em;
    }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid text-dark">

    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 fw-bolder text-dark"><i class="bi bi-intersect me-2 text-primary"></i> 3-Way Matching Dashboard</h4>
            <div class="text-muted small">Pelacakan Pemenuhan Item (PR <i class="bi bi-arrow-right"></i> PO <i class="bi bi-arrow-right"></i> GR)</div>
        </div>
        <div>
            <button class="shadow-sm btn btn-outline-primary rounded-pill fw-bold btn-sm" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Cetak Laporan
            </button>
        </div>
    </div>

    {{-- KOTAK PENCARIAN --}}
    <div class="mb-4 border-0 shadow-sm card rounded-4">
        <div class="p-3 card-body">
            <form action="{{ route('reports.3way') }}" method="GET" class="gap-2 d-flex">
                <input type="text" name="search" class="form-control rounded-pill" placeholder="Cari No PR, Nama Item, atau Kode SKU..." value="{{ $search ?? '' }}">
                <button type="submit" class="px-4 shadow-sm btn btn-primary rounded-pill fw-bold">Cari</button>
                @if($search)
                    <a href="{{ route('reports.3way') }}" class="px-4 border btn btn-light rounded-pill fw-bold">Reset</a>
                @endif
            </form>
        </div>
    </div>

    {{-- TABEL LAPORAN --}}
    <div class="overflow-hidden border-0 shadow-sm card rounded-4">
        <div class="table-responsive">
            <table class="table mb-0 align-middle table-borderless" style="font-size: 0.85rem;">
                <thead class="bg-light text-muted small text-uppercase fw-bold border-bottom">
                    <tr>
                        <th class="py-3 ps-4" width="20%">Dokumen Awal (PR)</th>
                        <th class="py-3" width="25%">Item & Kebutuhan (PR)</th>
                        <th class="py-3" width="35%">Pelacakan Progres (Tracking)</th>
                        <th class="py-3 text-center pe-4" width="20%">Status Matching</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData as $row)
                        @php
                            // Perhitungan persentase progres bar
                            $poPercentage = $row->pr_qty > 0 ? min(100, ($row->po_qty / $row->pr_qty) * 100) : 0;
                            $grPercentage = $row->pr_qty > 0 ? min(100, ($row->gr_qty / $row->pr_qty) * 100) : 0;
                        @endphp
                        <tr class="border-bottom">
                            <td class="py-3 ps-4">
                                <div class="mb-1 fw-bolder text-primary">{{ $row->pr_number }}</div>
                                <div class="text-muted" style="font-size: 0.7rem;"><i class="bi bi-building me-1"></i>{{ $row->company }}</div>
                                <div class="mt-1 text-muted" style="font-size: 0.7rem;"><i class="bi bi-calendar3 me-1"></i>{{ \Carbon\Carbon::parse($row->pr_date)->format('d M Y') }}</div>
                            </td>

                            <td class="py-3">
                                <div class="mb-1 fw-bold text-dark">{{ $row->item_name }}</div>
                                <span class="mb-2 border badge bg-secondary-subtle text-secondary border-secondary-subtle">{{ $row->item_code }}</span>

                                <div class="p-2 mt-1 border border-opacity-25 rounded bg-light border-info">
                                    <span class="text-muted small">Target Kebutuhan:</span>
                                    <h6 class="mt-1 mb-0 fw-bolder text-dark">{{ $row->pr_qty }} <span class="text-primary">{{ $row->uom }}</span></h6>
                                </div>
                            </td>

                            <td class="py-3">
                                {{-- PROGRES PO --}}
                                <div class="mb-3">
                                    <div class="mb-1 d-flex justify-content-between small fw-bold">
                                        <span class="text-muted">Proses PO ({{ $poPercentage }}%)</span>
                                        <span class="{{ $row->po_qty >= $row->pr_qty ? 'text-success' : 'text-primary' }}">{{ $row->po_qty }} / {{ $row->pr_qty }}</span>
                                    </div>
                                    <div class="progress progress-sm">
                                        <div class="progress-bar bg-{{ $row->po_qty >= $row->pr_qty ? 'success' : 'primary' }}" role="progressbar" style="width: {{ $poPercentage }}%"></div>
                                    </div>

                                    {{-- 🔥 REFERENSI PO DIBUAT JADI BADGE / LIST RAPI 🔥 --}}
                                    <div class="flex-wrap gap-1 mt-2 d-flex">
                                        @forelse($row->po_numbers as $poNum)
                                            <a href="{{ route('po.show', $poNum) }}" target="_blank" class="border badge bg-light border-secondary-subtle text-secondary fw-normal text-decoration-none">
                                                <i class="bi bi-file-earmark-text text-primary me-1"></i>{{ $poNum }}
                                            </a>
                                        @empty
                                            <span class="text-muted fst-italic" style="font-size: 0.65rem;">Belum ada PO</span>
                                        @endforelse
                                    </div>
                                </div>

                                {{-- PROGRES GR --}}
                                <div>
                                    <div class="mb-1 d-flex justify-content-between small fw-bold">
                                        <span class="text-muted">Proses GR ({{ $grPercentage }}%)</span>
                                        <span class="{{ $row->gr_qty >= $row->pr_qty ? 'text-success' : 'text-info' }}">{{ $row->gr_qty }} / {{ $row->pr_qty }}</span>
                                    </div>
                                    <div class="progress progress-sm">
                                        <div class="progress-bar bg-{{ $row->gr_qty >= $row->pr_qty ? 'success' : 'info' }}" role="progressbar" style="width: {{ $grPercentage }}%"></div>
                                    </div>

                                    {{-- 🔥 REFERENSI GR DIBUAT JADI BADGE / LIST RAPI 🔥 --}}
                                    <div class="flex-wrap gap-1 mt-2 d-flex">
                                        @forelse($row->gr_numbers as $grNum)
                                            <a href="{{ route('gr.show', $grNum) }}" target="_blank" class="border badge bg-light border-secondary-subtle text-secondary fw-normal text-decoration-none">
                                                <i class="bi bi-truck text-info me-1"></i>{{ $grNum }}
                                            </a>
                                        @empty
                                            <span class="text-muted fst-italic" style="font-size: 0.65rem;">Belum ada GR</span>
                                        @endforelse
                                    </div>
                                </div>
                            </td>

                            <td class="py-3 text-center pe-4">
                                <span class="badge bg-{{ $row->color }}-subtle text-{{ $row->color }} border border-{{ $row->color }}-subtle badge-status rounded-pill shadow-sm">
                                    @if($row->status == 'MATCHED') <i class="bi bi-check-circle-fill me-1"></i>
                                    @elseif($row->status == 'PENDING PO') <i class="bi bi-cart-x-fill me-1"></i>
                                    @elseif($row->status == 'PENDING GR') <i class="bi bi-truck me-1"></i>
                                    @else <i class="bi bi-arrow-repeat me-1"></i>
                                    @endif
                                    {{ $row->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-5 text-center text-muted">
                                <i class="mb-3 opacity-50 bi bi-inboxes fs-1 d-block"></i>
                                <h5>Belum Ada Data</h5>
                                <p>Tidak ada riwayat pengadaan yang ditemukan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="py-3 bg-white card-footer border-top d-flex justify-content-center">
            {{ $prItems->appends(['search' => $search])->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>
@endsection
