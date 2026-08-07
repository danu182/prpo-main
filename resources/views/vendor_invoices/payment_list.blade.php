@extends('layouts.app')

@section('content')
<div class="container-fluid pb-5 text-dark">

    {{-- HEADER HALAMAN --}}
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-wallet2 me-2 text-success"></i>Daftar Riwayat Pembayaran</h4>
            <div class="text-muted small">Semua log transaksi kas keluar untuk pembayaran tagihan vendor.</div>
        </div>
        <div>
            <a href="{{ route('vendor-invoices.index') }}" class="shadow-sm btn btn-outline-secondary rounded-pill fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Tagihan
            </a>
        </div>
    </div>

    {{-- TABEL DAFTAR PEMBAYARAN --}}
    <div class="mb-4 border-0 shadow-sm card rounded-4">
        <div class="py-3 bg-white border-bottom card-header">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-columns me-2 text-primary"></i>Semua Pembayaran</h6>
        </div>
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light text-muted small text-uppercase fw-bold border-bottom">
                    <tr>
                        <th class="py-3 ps-4" width="15%">Tanggal Bayar</th>
                        <th class="py-3" width="20%">No. Pembayaran</th>
                        <th class="py-3" width="30%">Untuk Tagihan & Vendor</th>
                        <th class="py-3" width="15%">Metode Bank</th>
                        <th class="py-3 text-end pe-4" width="20%">Nominal (IDR)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $pay)
                    @php
                        // Deteksi relasi ke Invoice secara aman
                        // (Mencoba menebak nama relasi Anda: vendorInvoice atau invoice)
                        $inv = $pay->vendorInvoice ?? $pay->invoice ?? null;
                    @endphp
                    <tr>
                        {{-- 1. Tanggal --}}
                        <td class="py-3 ps-4 text-muted small fw-bold">
                            {{ \Carbon\Carbon::parse($pay->payment_date)->format('d M Y') }}
                        </td>

                        {{-- 2. Nomor Bayar --}}
                        <td class="py-3">
                            <span class="fw-bolder text-dark">{{ $pay->payment_number }}</span>
                        </td>

                        {{-- 3. Referensi Dokumen Asal --}}
                        <td class="py-3">
                            @if($inv)
                                <a href="{{ route('vendor-invoices.show', $inv->invoice_number) }}" class="text-decoration-none fw-bold text-primary" target="_blank" title="Buka Detail Tagihan">
                                    <i class="bi bi-file-earmark-invoice me-1"></i> {{ $inv->invoice_number }}
                                </a>
                                <div class="text-muted small mt-1">
                                    <i class="bi bi-shop me-1"></i> {{ optional($inv->vendor)->name ?? 'Vendor tidak diketahui' }}
                                </div>
                            @else
                                <span class="text-muted fst-italic">Referensi terputus</span>
                            @endif
                        </td>

                        {{-- 4. Metode --}}
                        <td class="py-3 text-uppercase small fw-semibold text-secondary">
                            {{ $pay->payment_method }}
                            @if($pay->bank_name)
                                <div class="text-muted" style="font-size: 0.65rem;">{{ $pay->bank_name }}</div>
                            @endif
                        </td>

                        {{-- 5. Nominal --}}
                        <td class="py-3 text-end pe-4">
                            <span class="fw-bolder text-success fs-6">
                                {{ number_format($pay->amount, 0, ',', '.') }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-5 text-center text-muted fst-italic">
                            <i class="mb-2 opacity-50 bi bi-cash-stack fs-2 d-block"></i>
                            Belum ada riwayat pembayaran yang dicatat.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
