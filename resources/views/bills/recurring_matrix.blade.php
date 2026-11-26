@extends('layouts.app')

@push('css')
<style>
    .recurring-table {
        border-collapse: collapse;
        width: 100%;
        font-size: 0.78rem;
        background: #fff;
    }
    .recurring-table thead th {
        background-color: #1e293b;
        color: #f8fafc;
        font-weight: 700;
        text-align: center;
        vertical-align: middle;
        border: 1px solid #334155;
        padding: 9px 8px;
        white-space: nowrap;
    }
    .recurring-table tbody td {
        border: 1px solid #cbd5e1;
        padding: 8px 10px;
        vertical-align: middle;
    }
    .recurring-table tbody tr:hover td {
        background-color: #f8fafc;
    }
    .month-cell {
        min-width: 145px;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .bill-chip-wrap {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 4px;
        padding: 4px 7px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 0.74rem;
        margin-bottom: 3px;
    }
    .bill-chip-link {
        text-decoration: none;
        color: inherit;
        flex-grow: 1;
        text-align: right;
    }
    .bill-chip-link:hover {
        text-decoration: underline;
    }
    .btn-chip-edit {
        padding: 1px 5px;
        border-radius: 4px;
        background: rgba(255, 255, 255, 0.75);
        color: #0f172a;
        border: 1px solid rgba(15, 23, 42, 0.15);
        font-size: 0.7rem;
        line-height: 1.2;
        text-decoration: none;
    }
    .btn-chip-edit:hover {
        background: #0d6efd;
        color: #fff;
    }
    .chip-paid     { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .chip-approved { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
    .chip-pending  { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
    .chip-overdue  { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    @media print {
        .no-print { display: none !important; }
    }
</style>
@endpush

@section('content')
@php
    $monthNamesFull = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $monthNamesShort = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];
    $periodLabel = $month ? ($monthNamesFull[$month] . ' ' . $year) : ('Tahun ' . $year);
@endphp

<div class="pb-5 container-fluid text-dark">

    {{-- HEADER --}}
    <div class="mb-4 d-flex flex-wrap gap-2 justify-content-between align-items-center no-print">
        <div>
            <h4 class="mb-1 fw-bold text-dark">
                <i class="bi bi-arrow-repeat text-primary me-2"></i> Monitoring & Matriks Tagihan Opex Berulang
            </h4>
            <div class="text-muted small">Periode Aktif: <strong class="text-primary">{{ $periodLabel }}</strong> — Pantau jadwal langganan rutin per Perusahaan (PT), Vendor, Item/Layanan, dan edit langsung tagihan.</div>
        </div>
        <div class="gap-2 d-flex">
            <a href="{{ route('bills.index') }}" class="btn btn-outline-secondary rounded-pill fw-bold">
                <i class="bi bi-list-ul me-1"></i> Semua Tagihan Opex
            </a>
            <button type="button" onclick="exportRecurringExcel('tableRecurringMatrix', 'Opex_Berulang_{{ $month ? $monthNamesShort[$month].'_' : '' }}{{ $year }}')" class="btn btn-success rounded-pill fw-bold shadow-sm">
                <i class="bi bi-file-earmark-excel-fill me-1"></i> Export Excel
            </button>
        </div>
    </div>

    {{-- KPI RINGKASAN MULTI-MATA UANG --}}
    <div class="mb-4 row g-3">
        <div class="col-md-3">
            <div class="p-3 bg-white border-0 shadow-sm card rounded-4 border-start border-4 border-primary h-100">
                <div class="small fw-bold text-muted text-uppercase">Tagihan Berulang ({{ $periodLabel }})</div>
                <div class="mt-1 fs-2 fw-bolder text-dark">{{ $matrixRows->count() }} <span class="fs-6 fw-normal text-muted">Layanan</span></div>
                <div class="mt-1 small text-muted">
                    Aktif: <strong class="text-success">{{ $matrixRows->where('is_active', true)->count() }}</strong> &bull;
                    Berhenti: <strong class="text-secondary">{{ $matrixRows->where('is_active', false)->count() }}</strong>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="p-3 bg-white border-0 shadow-sm card rounded-4 border-start border-4 border-success h-100">
                <div class="small fw-bold text-muted text-uppercase">Total Tagihan Berulang ({{ $periodLabel }})</div>
                <div class="mt-1 d-flex flex-wrap gap-2">
                    @forelse($totalsByCurrency as $curr => $tot)
                        @php $dec = ($curr === 'IDR') ? 0 : 2; @endphp
                        <span class="px-3 py-1 badge bg-success-subtle text-success border border-success-subtle fs-6">
                            {{ $curr }} {{ number_format($tot, $dec, ',', '.') }}
                        </span>
                    @empty
                        <span class="text-muted small">Belum ada tagihan pada {{ $periodLabel }}</span>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="p-3 bg-white border-0 shadow-sm card rounded-4 border-start border-4 border-warning h-100">
                <div class="small fw-bold text-muted text-uppercase">Belum Lunas ({{ $periodLabel }})</div>
                <div class="mt-1 d-flex flex-wrap gap-2">
                    @forelse($unpaidByCurrency as $curr => $unp)
                        @php $dec = ($curr === 'IDR') ? 0 : 2; @endphp
                        <span class="px-3 py-1 badge bg-warning-subtle text-warning-emphasis border border-warning fs-6">
                            {{ $curr }} {{ number_format($unp, $dec, ',', '.') }}
                        </span>
                    @empty
                        <span class="text-success fw-bold small"><i class="bi bi-check-circle-fill me-1"></i>Tidak ada tunggakan pada periode ini</span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- FILTER SPESIFIK --}}
    <div class="mb-3 border-0 shadow-sm card rounded-4 no-print">
        <div class="p-3 card-body">
            <form method="GET" action="{{ route('bills.recurring_matrix') }}" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Bulan</label>
                    <select name="month" class="form-select form-select-sm fw-bold border-primary">
                        <option value="all" {{ is_null($month) ? 'selected' : '' }}>-- Semua Bulan (Jan–Des) --</option>
                        @foreach($monthNamesFull as $mNum => $mLabel)
                            <option value="{{ $mNum }}" {{ $month === $mNum ? 'selected' : '' }}>{{ $mLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Tahun</label>
                    <select name="year" class="form-select form-select-sm fw-bold border-primary">
                        @foreach($availableYears as $y)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Perusahaan (PT)</label>
                    <select name="company_id" class="form-select form-select-sm">
                        <option value="">-- Semua PT --</option>
                        @foreach($companies as $c)
                            <option value="{{ $c->id }}" {{ $companyId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Vendor</label>
                    <input type="text" name="vendor" list="vendorDatalist" class="form-control form-control-sm" value="{{ $vendorName }}" placeholder="Ketik / pilih vendor...">
                    <datalist id="vendorDatalist">
                        @foreach($vendors as $v)
                            <option value="{{ $v }}">
                        @endforeach
                    </datalist>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Item / Layanan / ID Akun</label>
                    <input type="text" name="item_search" list="itemDatalist" class="form-control form-control-sm" value="{{ $itemSearch }}" placeholder="Cari item / ID pelanggan...">
                    <datalist id="itemDatalist">
                        @foreach($recurringItems as $itName)
                            <option value="{{ $itName }}">
                        @endforeach
                    </datalist>
                </div>

                <div class="col-md-2 d-flex gap-1">
                    <div class="flex-grow-1">
                        <label class="form-label small fw-bold text-muted mb-1">Siklus</label>
                        <select name="cycle_status" class="form-select form-select-sm">
                            <option value="all" {{ $cycleStatus === 'all' ? 'selected' : '' }}>Semua</option>
                            <option value="active" {{ $cycleStatus === 'active' ? 'selected' : '' }}>Aktif</option>
                            <option value="stopped" {{ $cycleStatus === 'stopped' ? 'selected' : '' }}>Berhenti</option>
                        </select>
                    </div>
                    <div class="d-flex align-items-end gap-1">
                        <button type="submit" class="btn btn-sm btn-primary fw-bold px-3 rounded-pill" title="Terapkan Filter">
                            <i class="bi bi-funnel-fill"></i>
                        </button>
                        <a href="{{ route('bills.recurring_matrix') }}" class="btn btn-sm btn-light border rounded-pill" title="Reset ke Bulan & Tahun Ini">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- LEGENDA STATUS --}}
    <div class="mb-2 d-flex flex-wrap gap-3 small align-items-center no-print">
        <span class="fw-bold text-muted">Keterangan:</span>
        <span><span class="badge chip-paid">Paid</span> Sudah Lunas</span>
        <span><span class="badge chip-approved">Approved</span> Siap Bayar Finance</span>
        <span><span class="badge chip-pending">Pending</span> Menunggu Approval</span>
        <span><span class="badge chip-overdue">Overdue</span> Lewat Jatuh Tempo</span>
        <span class="text-muted">| Klik nominal untuk <strong>Detail</strong> atau ikon <i class="bi bi-pencil-square text-primary"></i> untuk <strong>Edit Langsung</strong></span>
    </div>

    {{-- TABEL MATRIKS --}}
    <div class="border-0 shadow-sm card rounded-4 overflow-hidden">
        <div class="p-0 card-body table-responsive">
            <table class="recurring-table" id="tableRecurringMatrix">
                <thead>
                    <tr>
                        <th style="min-width: 40px;">No</th>
                        <th style="min-width: 160px;">Perusahaan (PT)</th>
                        <th style="min-width: 170px;">Vendor</th>
                        <th style="min-width: 210px;">Item / Layanan & ID Akun</th>
                        <th style="min-width: 135px;">Siklus & PIC</th>
                        @foreach($visibleMonths as $mNum)
                            <th style="min-width: 155px;">{{ $monthNamesShort[$mNum] }} {{ $year }}</th>
                        @endforeach
                        @if(count($visibleMonths) > 1)
                            <th style="min-width: 135px;">Total {{ $year }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @php
                        $monthlyTotalsByCurr = [];
                        $grandTotalsByCurr   = [];
                    @endphp

                    @forelse($matrixRows as $idx => $row)
                        @php
                            $cCode = $row['currency'];
                            $dec   = ($cCode === 'IDR') ? 0 : 2;
                            if ($row['period_total'] > 0) {
                                $grandTotalsByCurr[$cCode] = ($grandTotalsByCurr[$cCode] ?? 0) + $row['period_total'];
                            }
                        @endphp
                        <tr>
                            <td class="text-center fw-bold">{{ $idx + 1 }}</td>
                            <td class="fw-semibold text-dark">{{ $row['company_name'] }}</td>
                            <td class="fw-bold text-primary">{{ $row['vendor_name'] }}</td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $row['item_names'] }}</div>
                                @if($row['account_number'] !== '-')
                                    <div class="text-muted" style="font-size: 0.7rem;">ID/Akun: {{ $row['account_number'] }}</div>
                                @endif
                            </td>
                            <td>
                                @if($row['is_active'])
                                    <span class="badge bg-success-subtle text-success border border-success-subtle mb-1">
                                        <i class="bi bi-arrow-repeat me-1"></i>{{ $row['interval_label'] }}
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border mb-1">Berhenti</span>
                                @endif
                                <div class="text-muted" style="font-size: 0.7rem;"><i class="bi bi-person me-1"></i>{{ $row['pic_name'] }}</div>
                            </td>

                            @foreach($visibleMonths as $mNum)
                                <td class="month-cell">
                                    @if(!empty($row['months'][$mNum]))
                                        @foreach($row['months'][$mNum] as $bItem)
                                            @php
                                                $monthlyTotalsByCurr[$cCode][$mNum] = ($monthlyTotalsByCurr[$cCode][$mNum] ?? 0) + $bItem['amount'];
                                                $chipClass = 'chip-pending';
                                                if ($bItem['status_slug'] === 'paid') {
                                                    $chipClass = 'chip-paid';
                                                } elseif ($bItem['is_overdue']) {
                                                    $chipClass = 'chip-overdue';
                                                } elseif (in_array($bItem['status_slug'], ['approved', 'partial'])) {
                                                    $chipClass = 'chip-approved';
                                                }
                                            @endphp
                                            <div class="bill-chip-wrap {{ $chipClass }}"
                                                 title="{{ $bItem['bill_number'] }} | Status: {{ $bItem['status_name'] }} | Jatuh Tempo: {{ $bItem['due_date'] }}">
                                                @if($bItem['can_edit'])
                                                    <a href="{{ route('bills.edit', $bItem['bill_number']) }}"
                                                       class="btn-chip-edit no-print"
                                                       title="Edit Tagihan {{ $bItem['bill_number'] }}">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>
                                                @endif
                                                <a href="{{ route('bills.show', $bItem['bill_number']) }}" class="bill-chip-link">
                                                    {{ $cCode }} {{ number_format($bItem['amount'], $dec, ',', '.') }}
                                                </a>
                                            </div>
                                        @endforeach
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            @endforeach

                            @if(count($visibleMonths) > 1)
                                <td class="month-cell fw-bolder bg-light text-dark">
                                    @if($row['period_total'] > 0)
                                        {{ $cCode }} {{ number_format($row['period_total'], $dec, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 5 + count($visibleMonths) + (count($visibleMonths) > 1 ? 1 : 0) }}" class="py-5 text-center text-muted">
                                Tidak ada data tagihan opex berulang pada periode {{ $periodLabel }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                {{-- FOOTER TOTAL DIPISAH PER MATA UANG --}}
                @if($matrixRows->isNotEmpty() && !empty($grandTotalsByCurr))
                    <tfoot>
                        @foreach($grandTotalsByCurr as $cCode => $gTot)
                            @php $dec = ($cCode === 'IDR') ? 0 : 2; @endphp
                            <tr style="background-color: #f1f5f9; font-weight: 800;">
                                <td colspan="5" class="text-end py-2 px-3">TOTAL ({{ $cCode }}) :</td>
                                @foreach($visibleMonths as $mNum)
                                    @php $mTot = $monthlyTotalsByCurr[$cCode][$mNum] ?? 0; @endphp
                                    <td class="month-cell py-2">
                                        {{ $mTot > 0 ? $cCode . ' ' . number_format($mTot, $dec, ',', '.') : '-' }}
                                    </td>
                                @endforeach
                                @if(count($visibleMonths) > 1)
                                    <td class="month-cell py-2 text-primary">
                                        {{ $cCode }} {{ number_format($gTot, $dec, ',', '.') }}
                                    </td>
                                @endif
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
    function exportRecurringExcel(tableID, filename = '') {
        let tableSelect = document.getElementById(tableID);
        let tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');

        filename = filename ? filename + '.xls' : 'opex_berulang.xls';
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