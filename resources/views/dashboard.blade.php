@extends('layouts.app')

@push('css')
<style>
    .widget-card { transition: transform 0.3s ease; border-radius: 1rem; border: none; }
    .widget-card:hover { transform: translateY(-5px); }
    .icon-box { width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 12px; }
    .chart-container { position: relative; height: 350px; width: 100%; }
</style>
@endpush

@section('content')
<div class="container py-4">

    {{-- ============================================================== --}}
    {{-- ZONA VVIP: HANYA BISA DILIHAT OLEH BOS & FINANCE               --}}
    {{-- ============================================================== --}}
    @hasanyrole('Super Admin|direktur|manager|Finance')

        {{-- WELCOME HEADER --}}
        <div class="mb-4">
            <h3 class="mb-1 fw-bold text-dark">Markas Komando (Dashboard) 📊</h3>
            <p class="text-muted">Ringkasan performa finansial: Purchase Order, Tagihan Vendor (A/P), dan Opex.</p>
        </div>

        {{-- 1. WIDGET CARDS --}}
        <div class="mb-4 row g-4">
            <div class="col-md-3">
                <div class="bg-white shadow-sm card widget-card h-100 border-bottom border-success border-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="mb-1 text-muted fw-bold small">TOTAL PO (BULAN INI)</p>
                                <h4 class="mb-0 fw-bolder text-success" style="font-size: 1.3rem;">Rp {{ number_format($poBulanIni ?? 0, 0, ',', '.') }}</h4>
                            </div>
                            <div class="shadow-sm icon-box bg-success bg-opacity-10 text-success fs-4">
                                <i class="bi bi-cart-check-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="bg-white shadow-sm card widget-card h-100 border-bottom border-primary border-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="mb-1 text-muted fw-bold small">OPEX (BULAN INI)</p>
                                <h4 class="mb-0 fw-bolder text-primary" style="font-size: 1.3rem;">Rp {{ number_format($opexBulanIni ?? 0, 0, ',', '.') }}</h4>
                            </div>
                            <div class="shadow-sm icon-box bg-primary bg-opacity-10 text-primary fs-4">
                                <i class="bi bi-tools"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="bg-white shadow-sm card widget-card h-100 border-bottom border-warning border-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="mb-1 text-muted fw-bold small">HUTANG A/P (VENDOR)</p>
                                <h4 class="mb-0 fw-bolder text-warning-emphasis" style="font-size: 1.3rem;">Rp {{ number_format($apUnpaid ?? 0, 0, ',', '.') }}</h4>
                            </div>
                            <div class="shadow-sm icon-box bg-warning bg-opacity-10 text-warning fs-4">
                                <i class="bi bi-shop"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="bg-white shadow-sm card widget-card h-100 border-bottom border-danger border-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="mb-1 text-muted fw-bold small">HUTANG OPEX</p>
                                <h4 class="mb-0 fw-bolder text-danger" style="font-size: 1.3rem;">Rp {{ number_format($opexUnpaid ?? 0, 0, ',', '.') }}</h4>
                            </div>
                            <div class="shadow-sm icon-box bg-danger bg-opacity-10 text-danger fs-4">
                                <i class="bi bi-wallet2"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. CHART AREA UTAMA --}}
        <div class="mb-4 row">
            <div class="col-12">
                <div class="border-0 shadow-sm card rounded-4 h-100">
                    <div class="pt-4 pb-0 bg-white border-0 card-header">
                        <h6 class="fw-bold text-dark"><i class="bi bi-bar-chart-line-fill text-primary me-2"></i>Tren Komparatif: PO vs A/P vs OPEX (Tahun {{ $currentYear ?? date('Y') }})</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="mainChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. TABEL TAGIHAN MENDESAK --}}
        <div class="row">
            <div class="col-12">
                <div class="border-0 shadow-sm card rounded-4">
                    <div class="pt-4 pb-3 bg-white border-0 card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-danger"><i class="bi bi-exclamation-circle-fill me-2"></i>Tagihan Opex Mendesak (Belum Lunas)</h6>
                        <a href="{{ route('bills.index') }}" class="btn btn-sm btn-outline-primary rounded-pill">Lihat Semua</a>
                    </div>
                    <div class="p-0 card-body table-responsive">
                        <table class="table mb-0 align-middle table-hover">
                            <thead class="bg-light text-muted small fw-bold">
                                <tr>
                                    <th class="py-3 ps-4">No. Tagihan</th>
                                    <th>Vendor</th>
                                    <th>Jatuh Tempo</th>
                                    <th class="text-end">Sisa Hutang</th>
                                    <th class="text-center pe-4">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($urgentBills ?? [] as $bill)
                                    @php
                                        $sisa = $bill->amount - $bill->payments->sum('amount_paid');
                                        $isOverdue = $bill->due_date ? \Carbon\Carbon::parse($bill->due_date)->isPast() : false;
                                    @endphp
                                    <tr>
                                        <td class="py-3 ps-4 fw-bold text-primary">
                                            <a href="{{ route('bills.show', $bill->id) }}" class="text-decoration-none">{{ $bill->bill_number }}</a>
                                        </td>
                                        <td class="fw-semibold">{{ $bill->vendor_name }}</td>
                                        <td>
                                            <span class="{{ $isOverdue ? 'text-danger fw-bold' : 'text-dark' }}">
                                                {{ $bill->due_date ? \Carbon\Carbon::parse($bill->due_date)->format('d M Y') : '-' }}
                                                @if($isOverdue) <i class="bi bi-exclamation-circle-fill ms-1" title="Overdue!"></i> @endif
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold text-dark">
                                            Rp {{ number_format($sisa, 0, ',', '.') }}
                                        </td>
                                        <td class="text-center pe-4">
                                            <span class="badge bg-{{ optional($bill->status)->color ?? 'secondary' }}-subtle text-{{ optional($bill->status)->color ?? 'secondary' }} border border-{{ optional($bill->status)->color ?? 'secondary' }} rounded-pill px-3 py-1 text-uppercase">
                                                {{ optional($bill->status)->name ?? 'UNKNOWN' }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-4 text-center text-muted">
                                            <i class="mb-2 bi bi-check-circle fs-3 d-block text-success"></i>
                                            Tidak ada tagihan mendesak. Semua aman!
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    {{-- ============================================================== --}}
    {{-- ZONA STAFF: TAMPILAN KHUSUS KARYAWAN BIASA / PURCHASING / GUDANG --}}
    {{-- ============================================================== --}}
    @else

        <div class="mt-5 row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="p-5 text-center bg-white border-0 shadow-sm card rounded-4">
                    <div class="mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle" style="width: 100px; height: 100px;">
                            <i class="bi bi-person-bounding-box" style="font-size: 3rem;"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark">Selamat Datang, {{ auth()->user()->name }}! 👋</h3>
                    <p class="mt-2 mb-4 text-muted fs-6 px-md-4">
                        Ini adalah portal internal ProcureApp. Melalui portal ini, Anda dapat mengajukan permintaan barang operasional (Purchase Request) dan memantau status persetujuannya.
                    </p>

                    <div class="flex-wrap gap-3 d-flex justify-content-center">
                        @can('view_pr')
                        <a href="{{ route('pr.index') }}" class="px-4 py-2 shadow-sm btn btn-primary rounded-pill fw-bold">
                            <i class="bi bi-cart-plus me-2"></i> Ajukan Permintaan (PR)
                        </a>
                        @endcan

                        <a href="{{ route('profile.edit') }}" class="px-4 py-2 border shadow-sm btn btn-light rounded-pill fw-bold text-dark">
                            <i class="bi bi-gear me-2"></i> Pengaturan Profil
                        </a>
                    </div>
                </div>
            </div>
        </div>

    @endhasanyrole

</div>
@endsection

@push('scripts')
{{-- JAVASCRIPT HANYA DIMUAT UNTUK ROLE BOS/FINANCE --}}
@hasanyrole('Super Admin|direktur|manager|Finance')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const canvas = document.getElementById('mainChart');
    if(!canvas) return;

    const ctx = canvas.getContext('2d');
    const labels = {!! json_encode($chartBulan ?? []) !!};
    const dataPO = {!! json_encode($chartDataPO ?? []) !!};
    const dataAP = {!! json_encode($chartDataAP ?? []) !!};
    const dataOpex = {!! json_encode($chartDataOpex ?? []) !!};

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Purchase Orders (PO)',
                    data: dataPO,
                    backgroundColor: 'rgba(25, 135, 84, 0.85)',
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                },
                {
                    label: 'Tagihan Vendor (A/P)',
                    data: dataAP,
                    backgroundColor: 'rgba(255, 193, 7, 0.85)',
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                },
                {
                    label: 'Biaya Operasional (OPEX)',
                    data: dataOpex,
                    backgroundColor: 'rgba(13, 110, 253, 0.85)',
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true, padding: 20, font: { weight: 'bold' } } },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) label += ': ';
                            if (context.parsed.y !== null) {
                                label += 'Rp ' + context.parsed.y.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { borderDash: [5, 5], color: '#e2e8f0' },
                    ticks: {
                        callback: function(value) {
                            if(value >= 1000000000) return 'Rp ' + (value / 1000000000) + ' M';
                            if(value >= 1000000) return 'Rp ' + (value / 1000000) + ' Jt';
                            return value;
                        }
                    }
                },
                x: { grid: { display: false } }
            }
        }
    });
});
</script>
@endhasanyrole
@endpush
