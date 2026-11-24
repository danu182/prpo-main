@extends('layouts.app')

@push('css')
<style>
    .matrix-table {
        border-collapse: collapse;
        width: 100%;
        font-size: 0.82rem;
        background: #fff;
    }
    .matrix-table thead th {
        background-color: #e2e8f0;
        color: #0f172a;
        font-weight: 800;
        text-align: center;
        vertical-align: middle;
        border: 1px solid #94a3b8;
        padding: 10px 10px;
        white-space: nowrap;
    }
    .matrix-table tbody td {
        border: 1px solid #cbd5e1;
        padding: 8px 10px;
        vertical-align: middle;
        color: #1e293b;
    }
    .matrix-table tbody tr:hover td {
        background-color: #f8fafc;
    }
    .cell-center { text-align: center; }
    .cell-right { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .nowrap-col { white-space: nowrap; }
    .badge-personal {
        background-color: #fef08a;
        color: #854d0e;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid #eab308;
    }
    @media print {
        .no-print { display: none !important; }
        .matrix-table { font-size: 10px; }
    }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid text-dark">
    {{-- HEADER --}}
    <div class="mb-4 d-flex flex-wrap gap-2 justify-content-between align-items-center no-print">
        <div>
            <h4 class="mb-1 fw-bold text-dark">
                <i class="bi bi-table text-primary me-2"></i> Matriks Aset & Upgrade per Karyawan
            </h4>
            <div class="text-muted small">Rekapitulasi kepemilikan perangkat dengan kolom terpisah untuk No. Aset, No. FA Akuntansi, Varian, Nama Spesifik, dan Biaya per Mata Uang.</div>
        </div>
        <div class="gap-2 d-flex">
            <button type="button" onclick="exportMatrixToExcel('tableEmployeeMatrix', 'Matriks_Aset_Karyawan_{{ date('Ymd') }}')" class="btn btn-success rounded-pill fw-bold shadow-sm">
                <i class="bi bi-file-earmark-excel-fill me-1"></i> Export Excel
            </button>
            <button type="button" onclick="window.print()" class="btn btn-outline-dark rounded-pill fw-bold">
                <i class="bi bi-printer-fill me-1"></i> Cetak
            </button>
        </div>
    </div>

    {{-- FILTER BAR --}}
    <div class="mb-3 border-0 shadow-sm card rounded-4 no-print">
        <div class="p-3 card-body">
            <form method="GET" action="{{ route('fixed-assets.employee_matrix') }}" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Cari Nama, No. Aset, No. FA, Varian, Spek, Upgrade...">
                </div>
                <div class="col-md-3">
                    <select name="company_id" class="form-select">
                        <option value="">-- Semua Perusahaan (PT) --</option>
                        @foreach($companies as $c)
                            <option value="{{ $c->id }}" {{ $companyId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="assignment_status" class="form-select">
                        <option value="assigned" {{ $statusFilter === 'assigned' ? 'selected' : '' }}>Aset Dipegang Karyawan</option>
                        <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>Semua Aset (Karyawan + Gudang)</option>
                        <option value="unassigned" {{ $statusFilter === 'unassigned' ? 'selected' : '' }}>Stok di Gudang Saja</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary fw-bold w-100 rounded-pill"><i class="bi bi-filter me-1"></i> Filter</button>
                    @if($search || $companyId || $statusFilter !== 'assigned')
                        <a href="{{ route('fixed-assets.employee_matrix') }}" class="btn btn-light border rounded-pill" title="Reset"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- TABEL MATRIKS ROWSPAN (KOLOM TERPISAH) --}}
    <div class="border-0 shadow-sm card rounded-4 overflow-hidden">
        <div class="p-0 card-body table-responsive">
            <table class="matrix-table" id="tableEmployeeMatrix">
                <thead>
                    <tr>
                        <th style="width: 42px;">No</th>
                        <th style="min-width: 155px;">Name</th>
                        <th style="width: 80px;">Company</th>
                        <th style="min-width: 140px;">Position</th>
                        <th style="min-width: 140px;">Asset Company</th>
                        <th style="min-width: 135px;">No. Aset</th>
                        <th style="min-width: 110px;">No. FA</th>
                        <th style="min-width: 165px;">Varian Perangkat</th>
                        <th style="min-width: 210px;">Nama Spesifik Aset</th>
                        <th style="min-width: 185px;">Spesifikasi</th>
                        <th style="min-width: 130px;">Asset Cost</th>
                        <th style="min-width: 175px;">Upgrade</th>
                        <th style="min-width: 130px;">Upgrade Cost</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $assetTotalsByCurr   = [];
                        $upgradeTotalsByCurr = [];
                    @endphp

                    @forelse($groupedHolders as $holderIdx => $holder)
                        @foreach($holder->assets as $assetIdx => $assetItem)
                            @php
                                $ast        = $assetItem->asset;
                                $upgrades   = $assetItem->upgrades;
                                $astSpan    = $assetItem->rowspan;
                                $hasUpgrade = $upgrades->isNotEmpty();

                                $variantName      = trim((string) ($ast->item_alias_name ?? ''));
                                $specificName     = trim((string) ($ast->name ?? optional($ast->item)->name ?? '-'));
                                $cleanSpec        = trim(strip_tags((string) ($ast->spesifikasi_detail ?? '')));
                                $assetCompanyName = optional($ast->company)->name ?? '-';
                                $isPersonal       = stripos($variantName . ' ' . $specificName, 'personal') !== false;

                                $astCurrCode = strtoupper(trim(optional($ast->currency)->code ?? 'IDR'));
                                $basePrice   = (float) ($ast->purchase_price ?? 0);
                                $astDec      = ($astCurrCode === 'IDR') ? 0 : 2;

                                if ($basePrice > 0) {
                                    $assetTotalsByCurr[$astCurrCode] = ($assetTotalsByCurr[$astCurrCode] ?? 0) + $basePrice;
                                }
                            @endphp

                            @if($hasUpgrade)
                                @foreach($upgrades as $upgIdx => $upg)
                                    @php
                                        $upgCurrCode = strtoupper(trim(optional($upg->currency)->code ?? $astCurrCode));
                                        $upgCost     = (float) ($upg->cost ?? 0);
                                        $upgDec      = ($upgCurrCode === 'IDR') ? 0 : 2;

                                        if ($upgCost > 0) {
                                            $upgradeTotalsByCurr[$upgCurrCode] = ($upgradeTotalsByCurr[$upgCurrCode] ?? 0) + $upgCost;
                                        }
                                    @endphp
                                    <tr>
                                        {{-- KOLOM PEMEGANG ASET (ROWSPAN SELURUH ASET MILIK USER) --}}
                                        @if($assetIdx === 0 && $upgIdx === 0)
                                            <td rowspan="{{ $holder->total_rows }}" class="cell-center fw-bold">{{ $holderIdx + 1 }}</td>
                                            <td rowspan="{{ $holder->total_rows }}" class="fw-bold text-dark">{{ $holder->holder_name }}</td>
                                            <td rowspan="{{ $holder->total_rows }}" class="cell-center fw-semibold">{{ $holder->holder_company }}</td>
                                            <td rowspan="{{ $holder->total_rows }}">{{ $holder->holder_position }}</td>
                                        @endif

                                        {{-- KOLOM IDENTITAS ASET TERPISAH (ROWSPAN SEBANYAK JUMLAH UPGRADE) --}}
                                        @if($upgIdx === 0)
                                            <td rowspan="{{ $astSpan }}" class="cell-center">{{ $assetCompanyName }}</td>
                                            <td rowspan="{{ $astSpan }}" class="nowrap-col">
                                                <a href="{{ route('asset-capitalizations.show', $ast->id) }}" class="fw-bold text-primary text-decoration-none">
                                                    {{ $ast->asset_number }}
                                                </a>
                                            </td>
                                            <td rowspan="{{ $astSpan }}" class="cell-center nowrap-col fw-semibold {{ $ast->accounting_asset_number ? 'text-success' : 'text-muted' }}">
                                                {{ $ast->accounting_asset_number ?: '-' }}
                                            </td>
                                            <td rowspan="{{ $astSpan }}" class="fw-semibold text-dark">
                                                @if($variantName !== '')
                                                    @if($isPersonal)
                                                        <span class="badge-personal">{{ $variantName }}</span>
                                                    @else
                                                        {{ $variantName }}
                                                    @endif
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td rowspan="{{ $astSpan }}" class="text-dark">
                                                @if($isPersonal && $variantName === '')
                                                    <span class="badge-personal">{{ $specificName }}</span>
                                                @else
                                                    {{ $specificName }}
                                                @endif
                                            </td>
                                            <td rowspan="{{ $astSpan }}">{{ $cleanSpec ?: '-' }}</td>
                                            <td rowspan="{{ $astSpan }}" class="cell-right fw-semibold">
                                                @if($basePrice > 0)
                                                    <span class="text-muted small me-1">{{ $astCurrCode }}</span>{{ number_format($basePrice, $astDec, ',', '.') }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        @endif

                                        {{-- KOLOM RINCIAN UPGRADE & BIAYA UPGRADE --}}
                                        <td class="fw-medium text-dark">{{ $upg->component_name }}</td>
                                        <td class="cell-right fw-semibold text-primary">
                                            @if($upgCost > 0)
                                                <span class="text-muted small me-1">{{ $upgCurrCode }}</span>{{ number_format($upgCost, $upgDec, ',', '.') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    {{-- KOLOM PEMEGANG ASET --}}
                                    @if($assetIdx === 0)
                                        <td rowspan="{{ $holder->total_rows }}" class="cell-center fw-bold">{{ $holderIdx + 1 }}</td>
                                        <td rowspan="{{ $holder->total_rows }}" class="fw-bold text-dark">{{ $holder->holder_name }}</td>
                                        <td rowspan="{{ $holder->total_rows }}" class="cell-center fw-semibold">{{ $holder->holder_company }}</td>
                                        <td rowspan="{{ $holder->total_rows }}">{{ $holder->holder_position }}</td>
                                    @endif

                                    <td class="cell-center">{{ $assetCompanyName }}</td>
                                    <td class="nowrap-col">
                                        <a href="{{ route('asset-capitalizations.show', $ast->id) }}" class="fw-bold text-primary text-decoration-none">
                                            {{ $ast->asset_number }}
                                        </a>
                                    </td>
                                    <td class="cell-center nowrap-col fw-semibold {{ $ast->accounting_asset_number ? 'text-success' : 'text-muted' }}">
                                        {{ $ast->accounting_asset_number ?: '-' }}
                                    </td>
                                    <td class="fw-semibold text-dark">
                                        @if($variantName !== '')
                                            @if($isPersonal)
                                                <span class="badge-personal">{{ $variantName }}</span>
                                            @else
                                                {{ $variantName }}
                                            @endif
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-dark">
                                        @if($isPersonal && $variantName === '')
                                            <span class="badge-personal">{{ $specificName }}</span>
                                        @else
                                            {{ $specificName }}
                                        @endif
                                    </td>
                                    <td>{{ $cleanSpec ?: '-' }}</td>
                                    <td class="cell-right fw-semibold">
                                        @if($basePrice > 0)
                                            <span class="text-muted small me-1">{{ $astCurrCode }}</span>{{ number_format($basePrice, $astDec, ',', '.') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-muted"></td>
                                    <td class="cell-right text-muted">-</td>
                                </tr>
                            @endif
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="13" class="py-5 text-center text-muted">
                                Tidak ada data aset yang sesuai dengan filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                {{-- FOOTER REKAP TOTAL TERPISAH PER MATA UANG --}}
                @if($groupedHolders->isNotEmpty())
                    @php
                        $allCurrencies = array_unique(array_merge(array_keys($assetTotalsByCurr), array_keys($upgradeTotalsByCurr)));
                        if (empty($allCurrencies)) $allCurrencies = ['IDR'];
                        sort($allCurrencies);
                    @endphp
                    <tfoot>
                        @foreach($allCurrencies as $currCode)
                            @php
                                $dec      = ($currCode === 'IDR') ? 0 : 2;
                                $totAsset = $assetTotalsByCurr[$currCode] ?? 0;
                                $totUpg   = $upgradeTotalsByCurr[$currCode] ?? 0;
                            @endphp
                            <tr style="background-color: #f1f5f9; font-weight: 800;">
                                <td colspan="10" class="text-end py-2 px-3">
                                    TOTAL TERCATAT ({{ $currCode }}) :
                                </td>
                                <td class="cell-right py-2 px-2 text-dark">
                                    {{ $totAsset > 0 ? $currCode . ' ' . number_format($totAsset, $dec, ',', '.') : '-' }}
                                </td>
                                <td class="text-end py-2 px-2 text-muted small">Total Upgrade ({{ $currCode }}):</td>
                                <td class="cell-right py-2 px-2 text-primary">
                                    {{ $totUpg > 0 ? $currCode . ' ' . number_format($totUpg, $dec, ',', '.') : '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function exportMatrixToExcel(tableID, filename = '') {
        let tableSelect = document.getElementById(tableID);
        let tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');

        filename = filename ? filename + '.xls' : 'excel_data.xls';
        let downloadLink = document.createElement("a");
        document.body.appendChild(downloadLink);

        if (navigator.msSaveOrOpenBlob) {
            let blob = new Blob(['\ufeff', tableHTML], { type: 'application/vnd.ms-excel' });
            navigator.msSaveOrOpenBlob(blob, filename);
        } else {
            downloadLink.href = 'data:application/vnd.ms-excel;charset=utf-8,' + tableHTML;
            downloadLink.download = filename;
            downloadLink.click();
        }
        document.body.removeChild(downloadLink);
    }
</script>
@endpush