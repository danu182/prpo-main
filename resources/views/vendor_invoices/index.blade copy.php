@extends('layouts.app')

@section('content')
<div class="container pb-5 text-dark">

    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="bi bi-receipt me-2 text-primary"></i> Daftar Tagihan Vendor (A/P)
            </h4>
            <div class="mt-1 text-muted small">Kelola semua faktur penagihan dan riwayat pembayaran ke vendor.</div>
        </div>

        <form action="{{ route('vendor-invoices.index') }}" method="GET" class="d-flex" style="min-width: 350px;">
            <div class="overflow-hidden border shadow-sm input-group rounded-pill">
                <input type="text" name="search" class="px-4 bg-white border-0 form-control" placeholder="Cari No Tagihan, Vendor, atau PO..." value="{{ request('search') }}">
                <button class="px-4 border-0 btn btn-primary fw-bold" type="submit"><i class="bi bi-search"></i></button>
                @if(request('search'))
                    <a href="{{ route('vendor-invoices.index') }}" class="px-3 btn btn-light border-start text-danger" title="Reset Pencarian"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </form>
    </div>

    <div class="border-0 border-4 shadow-sm card border-top border-primary rounded-3">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light fw-bold text-muted border-bottom">
                    <tr>
                        <th class="py-3 ps-4" width="18%">No. Tagihan Sistem</th>
                        <th class="py-3" width="20%">Vendor</th>
                        <th class="py-3" width="15%">Ref. Dokumen</th>
                        <th class="py-3" width="12%">Tgl Tagihan</th>
                        <th class="py-3 text-end" width="15%">Grand Total</th>
                        <th class="py-3 text-center" width="10%">Status</th>
                        <th class="py-3 pe-4 text-end" width="10%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $inv)
                    <tr>
                        <td class="py-3 ps-4 fw-bold text-primary">{{ $inv->invoice_number }}</td>
                        <td class="py-3 fw-bold text-dark"><i class="bi bi-building me-1 text-muted"></i> {{ optional($inv->vendor)->name ?? '-' }}</td>
                        <td class="py-3">
                            @if($inv->purchase_order_id)
                                <a href="{{ route('po.show', $inv->purchase_order_id) }}" class="pb-1 text-decoration-none fw-bold text-secondary border-bottom border-secondary"><i class="bi bi-cart2"></i> {{ optional($inv->purchaseOrder)->po_number }}</a>
                            @else
                                <span class="shadow-sm badge bg-secondary-subtle text-secondary rounded-pill"><i class="bi bi-collection"></i> Multi PO/GR</span>
                            @endif
                        </td>
                        <td class="py-3 text-muted small">{{ \Carbon\Carbon::parse($inv->invoice_date)->format('d M Y') }}</td>
                        <td class="py-3 text-end fw-bold fs-6">IDR {{ number_format($inv->grand_total, 0, ',', '.') }}</td>
                        <td class="py-3 text-center">
                            @if($inv->status)
                                <span class="badge bg-{{ $inv->status->color }}-subtle text-{{ $inv->status->color }} rounded-pill px-3 py-2 shadow-sm border border-{{ $inv->status->color }}-subtle">
                                    {{ $inv->status->name }}
                                </span>
                            @else
                                <span class="px-3 py-2 border shadow-sm badge bg-secondary-subtle text-secondary rounded-pill border-secondary-subtle">
                                    DRAFT
                                </span>
                            @endif
                        </td>
                        <td class="py-3 pe-4 text-end">
                            <a href="{{ route('vendor-invoices.show', $inv->id) }}" class="px-3 shadow-sm btn btn-sm btn-outline-primary rounded-pill fw-bold"><i class="bi bi-eye me-1"></i> Detail</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-5 text-center text-muted">
                            @if(request('search')) Tidak ada tagihan yang cocok dengan pencarian "<b>{{ request('search') }}</b>".
                            @else Belum ada data tagihan vendor.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(isset($invoices) && $invoices->hasPages())
        <div class="pt-3 pb-2 bg-white border-0 card-footer rounded-bottom-4">
            {{ $invoices->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
