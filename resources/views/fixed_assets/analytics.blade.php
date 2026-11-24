@extends('layouts.app')

@push('css')
<style>
    .kpi-card {
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        background: #fff;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.08);
    }
    .chart-card {
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        background: #fff;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04);
        height: 100%;
    }
    .chart-box {
        position: relative;
        height: 270px;
        width: 100%;
    }
    .nav-pills-custom .nav-link {
        border-radius: 50rem;
        font-weight: 700;
        font-size: 0.82rem;
        color: #475569;
        background: #f1f5f9;
        margin-right: 6px;
        padding: 0.5rem 1rem;
    }
    .nav-pills-custom .nav-link.active {
        background: #0d6efd;
        color: #fff;
        box-shadow: 0 4px 10px rgba(13, 110, 253, 0.25);
    }
    @media print {
        .no-print { display: none !important; }
    }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid text-dark">

    {{-- HEADER --}}
    <div class="mb-4 d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 fw-bold text-dark">
                <i class="bi bi-graph-up-arrow text-primary me-2"></i> Executive Dashboard & Analisa Aset
            </h4>
            <div class="text-muted small">Visualisasi kesehatan aset, distribusi usia, valuasi multi-mata uang, proyeksi penyusutan, dan peringatan peremajaan.</div>
        </div>
        <div class="gap-2 d-flex no-print">
            <a href="{{ route('fixed-assets.employee_matrix') }}" class="btn btn-outline-primary rounded-pill fw-bold">
                <i class="bi bi-table me-1"></i> Matriks Karyawan
            </a>
            <button type="button" onclick="window.print()" class="btn btn-dark rounded-pill fw-bold shadow-sm">
                <i class="bi bi-printer-fill me-1"></i> Cetak Laporan
            </button>
        </div>
    </div>

    {{-- FILTER BAR --}}
    <div class="mb-4 border-0 shadow-sm card rounded-4 no-print">
        <div class="p-3 card-body">
            <form method="GET" action="{{ route('fixed-assets.analytics') }}" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <select name="company_id" class="form-select">
                        <option value="">-- Semua Entitas Perusahaan (PT) --</option>
                        @foreach($companies as $c)
                            <option value="{{ $c->id }}" {{ $companyId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="warehouse_id" class="form-select">
                        <option value="">-- Semua Lokasi / Gudang --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ $warehouseId == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary fw-bold px-4 rounded-pill">
                        <i class="bi bi-funnel-fill me-1"></i> Terapkan Filter
                    </button>
                    @if($companyId || $warehouseId)
                        <a href="{{ route('fixed-assets.analytics') }}" class="btn btn-light border rounded-pill">Reset</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- BARIS 1: KPI RINGKASAN UNIT & UTILISASI --}}
    @php
        $totalActive   = $activeAssets->count();
        $inUseCount    = $activeAssets->filter(fn($a) => optional($a->status)->slug === 'in_use' || !empty($a->assigned_to))->count();
        $availCount    = $activeAssets->filter(fn($a) => optional($a->status)->slug === 'available' && empty($a->assigned_to))->count();
        $maintCount    = $activeAssets->filter(fn($a) => optional($a->status)->slug === 'maintenance')->count();
        $utilRate      = $totalActive > 0 ? round(($inUseCount / $totalActive) * 100, 1) : 0;
    @endphp

    <div class="mb-4 row g-3">
        <div class="col-xl-3 col-md-6">
            <div class="p-3 kpi-card h-100 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-bold text-muted text-uppercase">Total Unit Aset Aktif</div>
                        <div class="mt-1 fs-2 fw-bolder text-dark">{{ number_format($totalActive) }} <span class="fs-6 fw-normal text-muted">Unit</span></div>
                    </div>
                    <div class="p-2 rounded-3 bg-primary-subtle text-primary"><i class="bi bi-pc-display fs-4"></i></div>
                </div>
                <div class="mt-2 small text-muted">
                    Disposed / Dihapus: <strong class="text-danger">{{ $disposedAssets->count() }} unit</strong>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="p-3 kpi-card h-100 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-bold text-muted text-uppercase">Tingkat Utilisasi (Dipakai)</div>
                        <div class="mt-1 fs-2 fw-bolder text-success">{{ $utilRate }}%</div>
                    </div>
                    <div class="p-2 rounded-3 bg-success-subtle text-success"><i class="bi bi-person-check-fill fs-4"></i></div>
                </div>
                <div class="mt-2 small text-muted">
                    <strong>{{ $inUseCount }}</strong> Dipakai &bull; <strong>{{ $availCount }}</strong> di Gudang &bull; <strong>{{ $maintCount }}</strong> Servis
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="p-3 kpi-card h-100 border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-bold text-muted text-uppercase">Kandidat Peremajaan (&le;6 Bln)</div>
                        <div class="mt-1 fs-2 fw-bolder text-warning-emphasis">{{ $refreshCandidates->count() }} <span class="fs-6 fw-normal text-muted">Unit</span></div>
                    </div>
                    <div class="p-2 rounded-3 bg-warning-subtle text-warning-emphasis"><i class="bi bi-hourglass-bottom fs-4"></i></div>
                </div>
                <div class="mt-2 small text-muted">
                    Aset dengan sisa masa manfaat &le; 6 bulan / NBV 0
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="p-3 kpi-card h-100 border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small fw-bold text-muted text-uppercase">Peringatan Rasio Upgrade</div>
                        <div class="mt-1 fs-2 fw-bolder text-danger">{{ $highUpgradeRatio->count() }} <span class="fs-6 fw-normal text-muted">Unit</span></div>
                    </div>
                    <div class="p-2 rounded-3 bg-danger-subtle text-danger"><i class="bi bi-cpu-fill fs-4"></i></div>
                </div>
                <div class="mt-2 small text-muted">
                    Biaya upgrade &ge; 40% dari harga beli perangkat
                </div>
            </div>
        </div>
    </div>

    {{-- BARIS 2: RINGKASAN VALUASI & PENYUSUTAN TERPISAH PER MATA UANG --}}
    <div class="mb-4 border-0 shadow-sm card rounded-4">
        <div class="py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-cash-stack text-success me-2"></i>Valuasi Aset, Kapitalisasi Upgrade & Beban Penyusutan (Dipisah per Mata Uang)</h6>
            <span class="badge bg-light text-secondary border">Standar Prospektif PSAK 16</span>
        </div>
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="table-light small text-uppercase text-muted">
                    <tr>
                        <th class="ps-4">Mata Uang</th>
                        <th class="text-center">Jumlah Unit</th>
                        <th class="text-end">Harga Perolehan Awal</th>
                        <th class="text-end">Upgrade Kapitalisasi (CapEx)</th>
                        <th class="text-end">Total Nilai Tercatat</th>
                        <th class="text-end">Akumulasi Penyusutan</th>
                        <th class="text-end">Nilai Buku Bersih (NBV)</th>
                        <th class="text-end pe-4">Penyusutan / Bulan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($valuationByCurrency as $val)
                        @php
                            $cCode = $val['currency'];
                            $dec   = ($cCode === 'IDR') ? 0 : 2;
                        @endphp
                        <tr>
                            <td class="ps-4"><span class="badge bg-dark px-3 py-2">{{ $cCode }}</span></td>
                            <td class="text-center fw-bold">{{ $val['unit_count'] }}</td>
                            <td class="text-end fw-semibold">{{ $cCode }} {{ number_format($val['initial_cost'], $dec, ',', '.') }}</td>
                            <td class="text-end text-primary fw-semibold">+ {{ $cCode }} {{ number_format($val['cap_upgrade_cost'], $dec, ',', '.') }}</td>
                            <td class="text-end fw-bold text-dark">{{ $cCode }} {{ number_format($val['historical_cost'], $dec, ',', '.') }}</td>
                            <td class="text-end text-muted">({{ $cCode }} {{ number_format($val['accumulated_dep'], $dec, ',', '.') }})</td>
                            <td class="text-end fw-bolder text-success fs-6">{{ $cCode }} {{ number_format($val['net_book_value'], $dec, ',', '.') }}</td>
                            <td class="text-end pe-4 fw-bold text-danger">{{ $cCode }} {{ number_format($val['monthly_dep'], $dec, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-4 text-center text-muted">Belum ada data valuasi aset.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- BARIS 3: 3 GRAFIK UTAMA (STATUS, USIA ASET, STATUS UPGRADE) --}}
    <div class="mb-4 row g-4">
        {{-- GRAFIK 1: KOMPOSISI STATUS ASET --}}
        <div class="col-lg-4">
            <div class="p-4 chart-card">
                <h6 class="mb-1 fw-bold text-dark"><i class="bi bi-pie-chart-fill text-primary me-2"></i>Komposisi Status Aset</h6>
                <div class="mb-3 small text-muted">Proporsi perangkat aktif dipakai, tersedia di gudang, servis & disposed.</div>
                <div class="chart-box">
                    <canvas id="chartStatus"></canvas>
                </div>
            </div>
        </div>

        {{-- GRAFIK 2: DISTRIBUSI USIA ASET (AGING BUCKET) --}}
        <div class="col-lg-4">
            <div class="p-4 chart-card">
                <h6 class="mb-1 fw-bold text-dark"><i class="bi bi-bar-chart-fill text-warning me-2"></i>Distribusi Usia Aset (Aging)</h6>
                <div class="mb-3 small text-muted">Kelompok umur perangkat sejak tanggal perolehan (Acquisition Date).</div>
                <div class="chart-box">
                    <canvas id="chartAging"></canvas>
                </div>
            </div>
        </div>

        {{-- GRAFIK 3: ANALISA KOMPONEN UPGRADE (CAPEX VS OPEX VS RETUR) --}}
        <div class="col-lg-4">
            <div class="p-4 chart-card">
                <h6 class="mb-1 fw-bold text-dark"><i class="bi bi-cpu text-danger me-2"></i>Analisa Upgrade & Reliabilitas Part</h6>
                <div class="mb-3 small text-muted">Perbandingan part aktif (CapEx vs Expense) dan part dicopot (Retur/Rusak).</div>
                <div class="chart-box">
                    <canvas id="chartUpgradeStats"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- BARIS 4: GRAFIK SEBARAN PER PT, DEPARTEMEN & VARIAN PERANGKAT --}}
    <div class="mb-4 row g-4">
        {{-- GRAFIK 4: SEBARAN UNIT PER ENTITAS PT --}}
        <div class="col-lg-4">
            <div class="p-4 chart-card">
                <h6 class="mb-1 fw-bold text-dark"><i class="bi bi-building text-info me-2"></i>Sebaran Unit Aset per PT</h6>
                <div class="mb-3 small text-muted">Kepemilikan jumlah unit aset aktif per entitas perusahaan.</div>
                <div class="chart-box">
                    <canvas id="chartCompany"></canvas>
                </div>
            </div>
        </div>

        {{-- GRAFIK 5: TOP 8 DEPARTEMEN PENGGUNA --}}
        <div class="col-lg-4">
            <div class="p-4 chart-card">
                <h6 class="mb-1 fw-bold text-dark"><i class="bi bi-diagram-3-fill text-success me-2"></i>Top Departemen Pengguna Aset</h6>
                <div class="mb-3 small text-muted">Alokasi unit perangkat terbanyak berdasarkan divisi karyawan.</div>
                <div class="chart-box">
                    <canvas id="chartDepartment"></canvas>
                </div>
            </div>
        </div>

        {{-- GRAFIK 6: TOP 7 VARIAN / MODEL PERANGKAT --}}
        <div class="col-lg-4">
            <div class="p-4 chart-card">
                <h6 class="mb-1 fw-bold text-dark"><i class="bi bi-laptop text-secondary me-2"></i>Top Varian / Model Perangkat</h6>
                <div class="mb-3 small text-muted">Standarisasi tipe hardware yang paling banyak dimiliki perusahaan.</div>
                <div class="chart-box">
                    <canvas id="chartVariants"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- BARIS 5: GRAFIK PROYEKSI BEBAN PENYUSUTAN 6 BULAN KE DEPAN --}}
    <div class="mb-4 row g-4">
        <div class="col-12">
            <div class="p-4 chart-card">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="mb-1 fw-bold text-dark"><i class="bi bi-graph-down-arrow text-danger me-2"></i>Proyeksi Beban Penyusutan 6 Bulan ke Depan (Per Mata Uang)</h6>
                        <div class="small text-muted">Estimasi penurunan beban penyusutan bulanan seiring berakhirnya masa manfaat beberapa unit aset.</div>
                    </div>
                </div>
                <div style="position: relative; height: 260px; width: 100%;">
                    <canvas id="chartForecast"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- BARIS 6: ACTIONABLE INSIGHTS (4 TAB DAFTAR PERINGATAN DINI) --}}
    <div class="border-0 shadow-sm card rounded-4">
        <div class="p-3 bg-white card-header border-bottom">
            <ul class="nav nav-pills nav-pills-custom" id="insightTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tabRefresh" type="button">
                        <i class="bi bi-hourglass-split me-1"></i> Kandidat Peremajaan ({{ $refreshCandidates->count() }})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabHighUpgrade" type="button">
                        <i class="bi bi-exclamation-diamond me-1"></i> Rasio Upgrade Tinggi &ge;40% ({{ $highUpgradeRatio->count() }})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabIdle" type="button">
                        <i class="bi bi-box-seam me-1"></i> Aset Menganggur &gt;30 Hari ({{ $idleAssets->count() }})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabAudit" type="button">
                        <i class="bi bi-clipboard-check me-1"></i> Audit Kelengkapan Data ({{ $incompleteAudit->count() }})
                    </button>
                </li>
            </ul>
        </div>

        <div class="p-0 card-body tab-content">
            {{-- TAB 1: KANDIDAT PEREMAJAAN --}}
            <div class="tab-pane fade show active table-responsive" id="tabRefresh">
                <table class="table mb-0 align-middle table-hover small">
                    <thead class="table-light text-uppercase text-muted">
                        <tr>
                            <th class="ps-4">No. Aset</th>
                            <th>Perangkat / Varian</th>
                            <th>Pemegang / Lokasi</th>
                            <th>Tgl Perolehan</th>
                            <th class="text-center">Sisa Umur</th>
                            <th class="text-end pe-4">Nilai Buku Saat Ini</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($refreshCandidates as $row)
                            @php $a = $row->asset; $dec = $row->currency === 'IDR' ? 0 : 2; @endphp
                            <tr>
                                <td class="ps-4 fw-bold"><a href="{{ route('asset-capitalizations.show', $a->id) }}">{{ $a->asset_number }}</a></td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $a->item_alias_name ?: $a->name }}</div>
                                    <div class="text-muted">{{ optional($a->company)->name }}</div>
                                </td>
                                <td>{{ optional($a->assignee)->name ?? ('Gudang: ' . optional($a->warehouse)->name) }}</td>
                                <td>{{ $a->acquisition_date ? \Carbon\Carbon::parse($a->acquisition_date)->format('d M Y') : '-' }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $row->remaining_months == 0 ? 'bg-danger' : 'bg-warning text-dark' }}">
                                        {{ $row->remaining_months }} Bulan
                                    </span>
                                </td>
                                <td class="text-end pe-4 fw-bold">{{ $row->currency }} {{ number_format($row->nbv, $dec, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-4 text-center text-muted">Tidak ada aset yang mendekati habis masa manfaat dalam 6 bulan ke depan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- TAB 2: RASIO UPGRADE TINGGI --}}
            <div class="tab-pane fade table-responsive" id="tabHighUpgrade">
                <table class="table mb-0 align-middle table-hover small">
                    <thead class="table-light text-uppercase text-muted">
                        <tr>
                            <th class="ps-4">No. Aset</th>
                            <th>Perangkat / Pemegang</th>
                            <th class="text-end">Harga Beli Awal</th>
                            <th class="text-end">Total Biaya Upgrade</th>
                            <th class="text-center">Rasio Upgrade</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($highUpgradeRatio as $row)
                            @php $a = $row->asset; $dec = $row->currency === 'IDR' ? 0 : 2; @endphp
                            <tr>
                                <td class="ps-4 fw-bold"><a href="{{ route('asset-capitalizations.show', $a->id) }}">{{ $a->asset_number }}</a></td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $a->item_alias_name ?: $a->name }}</div>
                                    <div class="text-muted">Pemegang: {{ optional($a->assignee)->name ?? 'Gudang' }} ({{ $row->parts_count }} komponen)</div>
                                </td>
                                <td class="text-end">{{ $row->currency }} {{ number_format($row->initial_price, $dec, ',', '.') }}</td>
                                <td class="text-end fw-bold text-primary">{{ $row->currency }} {{ number_format($row->upgrade_cost, $dec, ',', '.') }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $row->ratio_pct >= 75 ? 'bg-danger' : 'bg-warning text-dark' }}">
                                        {{ $row->ratio_pct }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-muted">Tidak ada unit aset dengan rasio biaya upgrade &ge; 40%.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- TAB 3: ASET IDLE DI GUDANG --}}
            <div class="tab-pane fade table-responsive" id="tabIdle">
                <table class="table mb-0 align-middle table-hover small">
                    <thead class="table-light text-uppercase text-muted">
                        <tr>
                            <th class="ps-4">No. Aset</th>
                            <th>Perangkat / Varian</th>
                            <th>Lokasi Gudang & PT</th>
                            <th class="text-center">Durasi Menganggur</th>
                            <th class="text-end pe-4">Nilai Buku</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($idleAssets as $row)
                            @php $a = $row->asset; $dec = $row->currency === 'IDR' ? 0 : 2; @endphp
                            <tr>
                                <td class="ps-4 fw-bold"><a href="{{ route('asset-capitalizations.show', $a->id) }}">{{ $a->asset_number }}</a></td>
                                <td class="fw-bold text-dark">{{ $a->item_alias_name ?: $a->name }}</td>
                                <td>{{ optional($a->warehouse)->name ?? '-' }} ({{ optional($a->company)->name ?? '-' }})</td>
                                <td class="text-center"><span class="badge bg-warning text-dark">{{ $row->idle_days }} Hari</span></td>
                                <td class="text-end pe-4 fw-semibold">{{ $row->currency }} {{ number_format($row->nbv, $dec, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-muted">Semua aset tersedia di gudang berumur &lt; 30 hari.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- TAB 4: AUDIT KELENGKAPAN DATA --}}
            <div class="tab-pane fade table-responsive" id="tabAudit">
                <table class="table mb-0 align-middle table-hover small">
                    <thead class="table-light text-uppercase text-muted">
                        <tr>
                            <th class="ps-4">No. Aset</th>
                            <th>Nama Perangkat</th>
                            <th>Pemegang / Lokasi</th>
                            <th>Data yang Belum Lengkap</th>
                            <th class="text-center pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($incompleteAudit as $row)
                            @php $a = $row->asset; @endphp
                            <tr>
                                <td class="ps-4 fw-bold">{{ $a->asset_number }}</td>
                                <td>{{ $a->item_alias_name ?: $a->name }}</td>
                                <td>{{ optional($a->assignee)->name ?? ('Gudang: ' . optional($a->warehouse)->name) }}</td>
                                <td>
                                    @foreach($row->missing_fields as $mf)
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle me-1">{{ $mf }}</span>
                                    @endforeach
                                </td>
                                <td class="text-center pe-4">
                                    <a href="{{ route('fixed-assets.edit', $a->id) }}" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-3">
                                        Lengkapi
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-success fw-bold">Seluruh aset aktif sudah memiliki No. FA, S/N, dan Kategori Penyusutan yang lengkap!</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const palette = ['#0d6efd', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#64748b'];

    // 1. Donut Chart: Komposisi Status Aset
    new Chart(document.getElementById('chartStatus'), {
        type: 'doughnut',
        data: {
            labels: @json($chartStatus['labels']),
            datasets: [{
                data: @json($chartStatus['counts']),
                backgroundColor: palette,
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
        }
    });

    // 2. Bar Chart: Distribusi Usia Aset (Aging)
    new Chart(document.getElementById('chartAging'), {
        type: 'bar',
        data: {
            labels: @json($chartAging['labels']),
            datasets: [{
                label: 'Jumlah Unit',
                data: @json($chartAging['counts']),
                backgroundColor: ['#10b981', '#0d6efd', '#f59e0b', '#ef4444'],
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { ticks: { font: { size: 10 } } }
            }
        }
    });

    // 3. Doughnut Chart: Analisa Komponen Upgrade
    new Chart(document.getElementById('chartUpgradeStats'), {
        type: 'doughnut',
        data: {
            labels: ['Aktif (Kapitalisasi CapEx)', 'Aktif (Beban OpEx)', 'Dicopot (Retur Gudang)', 'Dicopot (Rusak/RMA)'],
            datasets: [{
                data: [
                    {{ $upgradeStats['active_capex'] }},
                    {{ $upgradeStats['active_opex'] }},
                    {{ $upgradeStats['removed_good'] }},
                    {{ $upgradeStats['removed_defective'] }}
                ],
                backgroundColor: ['#0d6efd', '#06b6d4', '#f59e0b', '#ef4444'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '60%',
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
        }
    });

    // 4. Horizontal Bar: Sebaran Unit per PT
    new Chart(document.getElementById('chartCompany'), {
        type: 'bar',
        data: {
            labels: @json($chartCompany['labels']),
            datasets: [{
                label: 'Unit Aset',
                data: @json($chartCompany['counts']),
                backgroundColor: '#0284c7',
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    // 5. Horizontal Bar: Top Departemen
    new Chart(document.getElementById('chartDepartment'), {
        type: 'bar',
        data: {
            labels: @json($chartDepartment['labels']),
            datasets: [{
                label: 'Unit Aset',
                data: @json($chartDepartment['counts']),
                backgroundColor: '#10b981',
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    // 6. Horizontal Bar: Top Varian Perangkat
    new Chart(document.getElementById('chartVariants'), {
        type: 'bar',
        data: {
            labels: @json($chartVariants['labels']),
            datasets: [{
                label: 'Unit Aset',
                data: @json($chartVariants['counts']),
                backgroundColor: '#8b5cf6',
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    // 7. Line Chart: Proyeksi Penyusutan 6 Bulan (Multi-Currency)
    const forecastSeriesRaw = @json($chartForecast['series']);
    const forecastDatasets = Object.keys(forecastSeriesRaw).map((curr, idx) => ({
        label: 'Beban Penyusutan (' + curr + ')',
        data: forecastSeriesRaw[curr],
        borderColor: palette[idx % palette.length],
        backgroundColor: palette[idx % palette.length] + '22',
        tension: 0.35,
        fill: true,
        pointRadius: 4
    }));

    new Chart(document.getElementById('chartForecast'), {
        type: 'line',
        data: {
            labels: @json($chartForecast['labels']),
            datasets: forecastDatasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true } }
        }
    });
});
</script>
@endpush