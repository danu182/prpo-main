@extends('layouts.app')

@push('css')
<style>
    .summary-card { transition: transform 0.2s ease; }
    .summary-card:hover { transform: translateY(-3px); }
    .icon-circle { width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 50%; }
</style>
@endpush

@section('content')
<div class="container py-4">

    {{-- HEADER & EXPORT BUTTONS --}}
    <div class="mb-4 d-flex justify-content-between align-items-end">
        <div>
            <h3 class="mb-1 fw-bold text-dark"><i class="bi bi-file-earmark-bar-graph text-primary me-2"></i>Laporan Keuangan OPEX</h3>
            <p class="mb-0 text-muted small">Rekapitulasi tagihan operasional dan status pembayaran perusahaan.</p>
        </div>
        <div class="gap-2 d-flex">
            {{-- Tombol Export (Akan kita aktifkan di langkah selanjutnya) --}}
            <button class="shadow-sm btn btn-outline-danger rounded-pill fw-bold" onclick="alert('Fitur Export PDF segera hadir!')">
                <i class="bi bi-file-pdf-fill me-1"></i> Export PDF
            </button>
            <button class="shadow-sm btn btn-success rounded-pill fw-bold" onclick="alert('Fitur Export Excel segera hadir!')">
                <i class="bi bi-file-excel-fill me-1"></i> Export Excel
            </button>
        </div>
    </div>

    {{-- FILTER KOTAK PENCARIAN --}}
    <div class="mb-4 border-0 shadow-sm card rounded-4 bg-light">
        <div class="p-3 card-body">
            <form action="{{ route('reports.finance') }}" method="GET">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="mb-1 small fw-bold text-muted">Periode Awal</label>
                        <input type="date" name="start_date" class="border-0 shadow-sm form-control" value="{{ $startDate }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="mb-1 small fw-bold text-muted">Periode Akhir</label>
                        <input type="date" name="end_date" class="border-0 shadow-sm form-control" value="{{ $endDate }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="mb-1 small fw-bold text-muted">Filter Status</label>
                        <select name="status" class="border-0 shadow-sm form-select">
                            <option value="all">-- Semua Status (Kecuali Draft & Batal) --</option>
                            @foreach($statuses as $st)
                                {{-- Jangan tampilkan draft dan cancelled di opsi laporan utama --}}
                                @if(!in_array($st->slug, ['draft', 'cancelled']))
                                    <option value="{{ $st->slug }}" {{ $statusSlug == $st->slug ? 'selected' : '' }}>
                                        {{ $st->name }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="shadow-sm btn btn-primary w-100 fw-bold rounded-3">
                            <i class="bi bi-search me-1"></i> Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 3 KARTU RINGKASAN (EXECUTIVE SUMMARY) --}}
    <div class="mb-4 row g-4">
        {{-- Total Tagihan --}}
        <div class="col-md-4">
            <div class="text-white border-0 shadow-sm card rounded-4 bg-primary summary-card h-100">
                <div class="p-4 card-body d-flex align-items-center">
                    <div class="bg-white bg-opacity-25 icon-circle me-3">
                        <i class="bi bi-receipt-cutoff fs-3"></i>
                    </div>
                    <div>
                        <div class="mb-1 opacity-75 small fw-bold text-uppercase">Total Nilai Tagihan</div>
                        <h4 class="mb-0 fw-bold">Rp {{ number_format($totalExpense, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
        </div>

        {{-- Total Dibayar --}}
        <div class="col-md-4">
            <div class="text-white border-0 shadow-sm card rounded-4 bg-success summary-card h-100">
                <div class="p-4 card-body d-flex align-items-center">
                    <div class="bg-white bg-opacity-25 icon-circle me-3">
                        <i class="bi bi-cash-coin fs-3"></i>
                    </div>
                    <div>
                        <div class="mb-1 opacity-75 small fw-bold text-uppercase">Total Sudah Dibayar</div>
                        <h4 class="mb-0 fw-bold">Rp {{ number_format($totalPaid, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sisa Hutang --}}
        <div class="col-md-4">
            <div class="text-white border-0 shadow-sm card rounded-4 bg-danger summary-card h-100">
                <div class="p-4 card-body d-flex align-items-center">
                    <div class="bg-white bg-opacity-25 icon-circle me-3">
                        <i class="bi bi-exclamation-triangle fs-3"></i>
                    </div>
                    <div>
                        <div class="mb-1 opacity-75 small fw-bold text-uppercase">Sisa Hutang Berjalan</div>
                        <h4 class="mb-0 fw-bold">Rp {{ number_format($totalUnpaid, 0, ',', '.') }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TABEL DATA RINCIAN LAPORAN --}}
    <div class="border-0 shadow-sm card rounded-4">
        <div class="px-4 pt-4 pb-2 bg-white border-0 card-header">
            <h6 class="mb-0 fw-bold text-dark">Rincian Transaksi OPEX ({{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }})</h6>
        </div>
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light text-muted small fw-bold text-uppercase">
                    <tr>
                        <th class="py-3 ps-4">Informasi Tagihan</th>
                        <th>Vendor & PT</th>
                        <th class="text-end">Total Tagihan</th>
                        <th class="text-end">Telah Dibayar</th>
                        <th class="text-end">Sisa Hutang</th>
                        <th class="text-center pe-4">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bills as $bill)
                        @php
                            $paidAmount = $bill->payments->sum('amount_paid');
                            $sisaHutang = $bill->amount - $paidAmount;

                            $statusName = optional($bill->status)->name ?? 'UNKNOWN';
                            $statusColor = optional($bill->status)->color ?? 'secondary';
                        @endphp
                        <tr class="border-bottom">
                            <td class="py-3 ps-4">
                                <a href="{{ route('bills.show', $bill->id) }}" class="mb-1 fw-bold text-decoration-none text-primary d-block" target="_blank">
                                    {{ $bill->bill_number }}
                                </a>
                                <small class="text-muted"><i class="bi bi-calendar-event me-1"></i>{{ \Carbon\Carbon::parse($bill->invoice_date)->format('d M Y') }}</small>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $bill->vendor_name }}</div>
                                <div class="small text-muted">{{ $bill->company->name ?? '-' }}</div>
                            </td>
                            <td class="text-end fw-semibold text-dark">
                                {{ number_format($bill->amount, 0, ',', '.') }}
                            </td>
                            <td class="text-end fw-semibold text-success">
                                {{ number_format($paidAmount, 0, ',', '.') }}
                            </td>
                            <td class="text-end fw-bold {{ $sisaHutang > 0 ? 'text-danger' : 'text-muted' }}">
                                {{ number_format($sisaHutang, 0, ',', '.') }}
                            </td>
                            <td class="text-center pe-4">
                                <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} border border-{{ $statusColor }} px-3 py-1 rounded-pill text-uppercase">
                                    {{ $statusName }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-5 text-center text-muted bg-light">
                                <i class="mb-3 opacity-50 bi bi-folder-x display-4 d-block"></i>
                                <h6 class="fw-bold">Tidak Ada Data Ditemukan</h6>
                                <p class="mb-0 small">Belum ada transaksi OPEX pada rentang tanggal dan status yang dipilih.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
