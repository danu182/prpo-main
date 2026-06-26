@extends('layouts.app')

@section('styles')
    {{-- CSS DataTables & Custom Styling --}}
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        /* Sembunyikan search box bawaan DataTables */
        .dataTables_filter { display: none; }

        /* Modern Tabs */
        .nav-custom-pills { background-color: #f1f5f9; padding: 5px; border-radius: 50rem; display: inline-flex; }
        .nav-custom-pills .nav-link { color: #64748b; border-radius: 50rem; padding: 8px 24px; font-weight: 600; transition: all 0.3s ease; border: none; }
        .nav-custom-pills .nav-link:hover { color: #0f172a; }
        .nav-custom-pills .nav-link.active { background-color: #fff; color: #0d6efd; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); }

        /* Table Row Hover & Overdue */
        .table-hover tbody tr:hover { background-color: #f8fafc; transition: 0.2s; }
        .overdue-row { border-left: 4px solid #ef4444; background-color: #fef2f2 !important; }
        .normal-row { border-left: 4px solid transparent; }

        /* Vendor Avatar Initials */
        .vendor-avatar { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: white; font-size: 1rem; }
    </style>
@endsection

@section('content')

{{-- =================================================================== --}}
{{-- 1. HEADER & RINGKASAN WIDGET --}}
{{-- =================================================================== --}}
<div class="flex-wrap gap-3 mb-4 d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-safe me-2 text-primary"></i>Meja Kasir (Pembayaran)</h4>
        <p class="mb-0 text-muted small">Kelola, cicil, dan lunasi tagihan operasional perusahaan.</p>
    </div>

    <div class="flex-wrap gap-3 d-flex">
        {{-- WIDGET: Total Invoice --}}
        <div class="bg-white border-0 shadow-sm card rounded-4" style="min-width: 200px;">
            <div class="p-3 card-body d-flex align-items-center">
                <div class="p-3 bg-primary-subtle text-primary rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="bi bi-receipt fs-5"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px;">Total Antrean</div>
                    <div class="fs-5 fw-bolder text-dark">{{ $debts->count() }} <span class="fs-6 fw-normal text-muted">Tagihan</span></div>
                </div>
            </div>
        </div>

        {{-- WIDGET: Sisa Hutang --}}
        <div class="bg-white border-0 border-4 shadow-sm card rounded-4 border-bottom border-danger" style="min-width: 250px;">
            <div class="p-3 card-body d-flex align-items-center">
                <div class="p-3 bg-danger-subtle text-danger rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="bi bi-cash-coin fs-5"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px;">Total Sisa Hutang</div>
                    @php
                        $groupedDebts = $debts->groupBy('currency');
                        $hasDebt = false;
                    @endphp

                    @foreach($groupedDebts as $currency => $billsInCurrency)
                        @php
                            $totalPerCurrency = $billsInCurrency->sum(function($b) {
                                return $b->amount - $b->payments->sum('amount_paid');
                            });
                        @endphp
                        @if($totalPerCurrency > 0)
                            @php $hasDebt = true; @endphp
                            <div class="fs-5 fw-bolder text-danger lh-1">
                                <span class="fs-6 me-1">{{ $currency }}</span>{{ number_format($totalPerCurrency, 0, ',', '.') }}
                            </div>
                        @endif
                    @endforeach

                    @if(!$hasDebt)
                        <div class="fs-5 fw-bolder text-success"><i class="bi bi-check-circle-fill me-1"></i> Rp 0</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- =================================================================== --}}
{{-- 2. TAB MENU (MODERN PILLS) --}}
{{-- =================================================================== --}}
<div class="mb-4">
    <ul class="shadow-sm nav nav-custom-pills">
        <li class="nav-item">
            <a class="nav-link {{ $tab == 'unpaid' ? 'active' : '' }}" href="{{ route('payments.index', ['tab' => 'unpaid']) }}">
                <i class="bi bi-hourglass-split me-2"></i> Belum Lunas / Cicilan
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab == 'paid' ? 'active text-success' : '' }}" href="{{ route('payments.index', ['tab' => 'paid']) }}">
                <i class="bi bi-check-all me-2"></i> Riwayat Lunas
            </a>
        </li>
    </ul>
</div>

{{-- =================================================================== --}}
{{-- 3. AREA FILTER PENCARIAN --}}
{{-- =================================================================== --}}
<div class="p-3 mb-4 bg-white border-0 shadow-sm card rounded-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="mb-1 small fw-bold text-muted text-uppercase" style="font-size: 0.7rem;">Cari Teks / No. Bill</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="customSearch" class="form-control border-start-0 bg-light" placeholder="Ketik pencarian...">
            </div>
        </div>
        <div class="col-md-2">
            <label class="mb-1 small fw-bold text-muted text-uppercase" style="font-size: 0.7rem;">Filter PT</label>
            <select id="filterPT" class="form-select form-select-sm bg-light">
                <option value="">-- Semua PT --</option>
                @foreach($companies as $comp) <option value="{{ $comp->name }}">{{ $comp->name }}</option> @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="mb-1 small fw-bold text-muted text-uppercase" style="font-size: 0.7rem;">Filter Vendor</label>
            <select id="filterVendor" class="form-select form-select-sm bg-light">
                <option value="">-- Semua Vendor --</option>
                @foreach($vendors as $ven) <option value="{{ $ven }}">{{ $ven }}</option> @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="mb-1 small fw-bold text-muted text-uppercase" style="font-size: 0.7rem;">Status Bayar</label>
            <select id="filterStatus" class="form-select form-select-sm bg-light">
                <option value="">Semua Status</option>
                <option value="BARU">Baru (0%)</option>
                <option value="CICILAN">Cicilan (Partial)</option>
                <option value="LUNAS">Lunas (Paid)</option>
            </select>
        </div>
        <div class="col-md-1">
            <label class="mb-1 small fw-bold text-muted text-uppercase" style="font-size: 0.7rem;">Baris</label>
            <select id="filterLength" class="form-select form-select-sm bg-light">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
        <div class="col-md-2">
            <button id="btnReset" class="btn btn-outline-secondary btn-sm w-100 rounded-pill fw-bold">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
            </button>
        </div>
    </div>
</div>

{{-- =================================================================== --}}
{{-- 4. TABEL DATA PEMBAYARAN --}}
{{-- =================================================================== --}}
<div class="overflow-hidden border-0 shadow-sm card rounded-4">
    <div class="table-responsive">
        <table id="tablePayments" class="table mb-0 align-middle table-hover w-100">
            <thead class="bg-secondary bg-opacity-10 text-secondary small text-uppercase">
                <tr>
                    <th class="py-3 ps-4" width="25%">Informasi Dokumen</th>
                    <th class="py-3" width="25%">Penerima (Vendor)</th>
                    <th class="py-3" width="12%">Jatuh Tempo</th>
                    <th class="py-3 text-end" width="20%">Progres Pelunasan</th>
                    <th class="py-3 text-center" width="8%">Status</th>
                    <th class="py-3 text-center pe-4" width="10%">Aksi</th>
                    <th class="d-none">HiddenPT</th>
                </tr>
            </thead>
            <tbody class="border-top-0">
                @foreach($debts as $bill)
                    @php
                        $paid = $bill->payments->sum('amount_paid');
                        $remaining = $bill->amount - $paid;
                        $percent = $bill->amount > 0 ? ($paid / $bill->amount) * 100 : 0;

                        $dueDate = \Carbon\Carbon::parse($bill->due_date);
                        $isOverdue = $dueDate->isPast() && $remaining > 0;

                        // Tentukan Status untuk Pencarian
                        $statusText = 'BARU';
                        if(strtoupper(optional($bill->status)->slug ?? '') == 'PAID') $statusText = 'LUNAS';
                        elseif(strtoupper(optional($bill->status)->slug ?? '') == 'PARTIAL') $statusText = 'CICILAN';

                        // Generator Warna Avatar Random berdasarkan Huruf Pertama
                        $colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
                        $firstLetter = strtoupper(substr($bill->vendor_name, 0, 1));
                        $avatarColor = $colors[ord($firstLetter) % count($colors)];
                    @endphp

                    <tr class="{{ $isOverdue ? 'overdue-row' : 'normal-row' }}">
                        {{-- Kolom 1: Info Tagihan --}}
                        <td class="py-3 ps-4">
                            {{-- 🔥 PERBAIKAN: Gunakan $bill->bill_number 🔥 --}}
                            <a href="{{ route('payments.process', $bill->bill_number) }}" class="mb-1 fw-bold text-decoration-none text-primary d-block">
                                {{ $bill->bill_number }}
                            </a>
                            <div class="small text-muted d-flex align-items-center">
                                <i class="bi bi-building me-1"></i> PT: {{ Str::limit($bill->company->name ?? '-', 20) }}
                            </div>
                        </td>

                        {{-- Kolom 2: Vendor dengan Avatar --}}
                        <td class="py-3" data-search="{{ $bill->vendor_name }}">
                            <div class="d-flex align-items-center">
                                <div class="shadow-sm vendor-avatar me-3" style="background-color: {{ $avatarColor }};">
                                    {{ $firstLetter }}
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">{{ $bill->vendor_name }}</div>
                                    <div class="small text-muted text-truncate" style="max-width: 180px;" title="{{ $bill->title }}">
                                        {{ $bill->title }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        {{-- Kolom 3: Jatuh Tempo --}}
                        <td class="py-3">
                            <div class="{{ $isOverdue ? 'text-danger fw-bold' : 'text-dark fw-semibold' }}">
                                {{ $dueDate->format('d M Y') }}
                            </div>
                            @if($isOverdue)
                                <span class="mt-1 badge bg-danger text-uppercase" style="font-size: 0.65rem;">
                                    <i class="bi bi-exclamation-triangle"></i> Overdue
                                </span>
                            @endif
                        </td>

                        {{-- Kolom 4: Progres Pelunasan (UI Mewah) --}}
                        <td class="py-3 pr-2">
                            <div class="mb-1 d-flex justify-content-between small">
                                <span class="text-muted">Total: {{ $bill->currency }} {{ number_format($bill->amount, 0, ',', '.') }}</span>
                                <span class="fw-bold {{ $remaining > 0 ? 'text-danger' : 'text-success' }}">
                                    Sisa: {{ number_format($remaining, 0, ',', '.') }}
                                </span>
                            </div>
                            <div class="progress" style="height: 6px; border-radius: 10px; background-color: #e2e8f0;">
                                <div class="progress-bar {{ $percent >= 100 ? 'bg-success' : 'bg-primary' }}"
                                     role="progressbar"
                                     style="width: {{ $percent }}%; border-radius: 10px;"
                                     aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div>
                        </td>

                        {{-- Kolom 5: Status Badge --}}
                        <td class="py-3 text-center" data-search="{{ $statusText }}">
                            @if($statusText == 'LUNAS')
                                <span class="px-3 py-2 border badge bg-success-subtle text-success border-success rounded-pill"><i class="bi bi-check-circle me-1"></i> LUNAS</span>
                            @elseif($statusText == 'CICILAN')
                                <span class="px-3 py-2 border badge bg-warning-subtle text-warning-emphasis border-warning rounded-pill"><i class="bi bi-pie-chart me-1"></i> CICILAN</span>
                            @else
                                <span class="px-3 py-2 border badge bg-primary-subtle text-primary border-primary rounded-pill"><i class="bi bi-file-earmark me-1"></i> BARU</span>
                            @endif
                        </td>

                        {{-- Kolom 6: Aksi Dinamis --}}
                        <td class="py-3 text-center pe-4">
                            @if($remaining > 0)
                                {{-- 🔥 PERBAIKAN: Gunakan $bill->bill_number 🔥 --}}
                                <a href="{{ route('payments.process', $bill->bill_number) }}" class="px-3 shadow-sm btn btn-sm btn-primary rounded-pill fw-bold">
                                    Bayar <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            @else
                                {{-- 🔥 PERBAIKAN: Gunakan $bill->bill_number 🔥 --}}
                                <a href="{{ route('payments.process', $bill->bill_number) }}" class="px-3 border btn btn-sm btn-light rounded-pill fw-bold text-secondary">
                                    <i class="bi bi-eye me-1"></i> Detail
                                </a>
                            @endif
                        </td>

                        {{-- Kolom 7: Hidden untuk filter Datatables --}}
                        <td class="d-none">{{ $bill->company->name ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

</div>
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            var table = $('#tablePayments').DataTable({
                "dom": 'rt<"d-flex justify-content-between align-items-center p-3"ip>',
                "ordering": true,
                "pageLength": 10,
                "language": {
                    "emptyTable": "<div class='py-5 text-center'><i class='mb-2 opacity-50 bi bi-inbox fs-1 text-muted d-block'></i><h6 class='fw-bold text-dark'>Antrean Bersih!</h6><p class='text-muted small'>Tidak ada tagihan yang menunggu pembayaran saat ini.</p></div>",
                    "info": "<span class='text-muted small'>Menampilkan _START_ sampai _END_ dari _TOTAL_ tagihan</span>",
                    "paginate": { "next": "Selanjutnya", "previous": "Sebelumnya" }
                },
                "columnDefs": [
                    { "orderable": false, "targets": 5 }, // Hilangkan panah sorting di kolom Aksi
                    { "searchable": true, "targets": 6 }  // Kolom hidden PT bisa dicari
                ]
            });

            // Logika Filter Custom
            $('#customSearch').on('keyup', function () { table.search(this.value).draw(); });
            $('#filterPT').on('change', function () { table.column(6).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw(); });
            $('#filterVendor').on('change', function () { table.column(1).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw(); });
            $('#filterStatus').on('change', function () { table.column(4).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw(); });
            $('#filterLength').on('change', function() { table.page.len(this.value).draw(); });

            // Tombol Reset Filter
            $('#btnReset').on('click', function () {
                $('#customSearch, #filterPT, #filterVendor, #filterStatus').val('');
                $('#filterLength').val('10');
                table.search('').columns().search('').page.len(10).draw();
            });
        });
    </script>
@endpush
