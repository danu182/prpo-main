@extends('layouts.app')


@section('content')

<div class="container pb-5">

    {{-- ================= 1. HEADER ACTIONS (TOMBOL NAVIGASI) ================= --}}
    <div class="mb-4 d-flex justify-content-between align-items-center no-print">
        <div>
            <a href="{{ route('po.index') }}" class="btn btn-outline-secondary rounded-pill fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke List
            </a>
        </div>

        <div class="gap-2 d-flex">

            {{-- LOGIC TOMBOL APPROVAL BERTINGKAT DENGAN SPATIE --}}

            {{-- 1. Jika User Punya Izin Manager DAN Status Pending --}}
            @can('approve_manager_po')
                @if($po->status && $po->status->slug === 'pending_approval')
                    <form id="form-approve" action="{{ route('po.approve', $po->id) }}" method="POST">
                        @csrf
                        <button type="button" onclick="confirmApprove('Setujui & Teruskan ke Direktur')" class="px-3 shadow-sm btn btn-success rounded-pill fw-bold">
                            <i class="bi bi-check-circle-fill me-1"></i> Setujui & Teruskan
                        </button>
                    </form>

                    <button type="button" class="px-3 shadow-sm btn btn-danger rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#rejectModal">
                        <i class="bi bi-x-circle-fill me-1"></i> Tolak
                    </button>
                @endif
            @endcan

            {{-- 2. Jika User Punya Izin Direktur DAN Status Menunggu Direktur --}}
            @can('approve_director_po')
                @if($po->status && $po->status->slug === 'approved_manager')
                    <form id="form-approve" action="{{ route('po.approve', $po->id) }}" method="POST">
                        @csrf
                        <button type="button" onclick="confirmApprove('Setujui Final & Terbitkan')" class="px-3 shadow-sm btn btn-success rounded-pill fw-bold">
                            <i class="bi bi-check-circle-fill me-1"></i> Setujui Final & Terbitkan
                        </button>
                    </form>

                    <button type="button" class="px-3 shadow-sm btn btn-danger rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#rejectModal">
                        <i class="bi bi-x-circle-fill me-1"></i> Tolak
                    </button>
                @endif
            @endcan

            {{-- ... Tombol Print ... --}}
        </div>


    </div>

    {{-- ================= 2. KERTAS PO (AREA CETAK) ================= --}}
    <div class="overflow-hidden border-0 shadow-lg card rounded-4" id="print-area">

        {{-- HEADER STRIP WARNA --}}
        @php
            $headerColor = match($po->status->slug ?? '') {
                'pending_approval', 'approved_manager' => 'bg-warning text-dark',
                'rejected' => 'bg-danger text-white',
                'issued', 'completed' => 'bg-primary text-white',
                default => 'bg-secondary text-white'
            };
        @endphp

        <div class="card-header py-3 {{ $headerColor }}">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0 fw-bold ls-1 text-uppercase">
                    <i class="bi bi-file-earmark-text me-2"></i>PURCHASE ORDER
                </h4>
                <span class="px-3 py-2 bg-white shadow-sm badge text-dark rounded-pill">
                    {{ $po->status->name ?? 'Draft' }}
                </span>
            </div>
        </div>

        <div class="p-5 card-body">

            {{-- ALERT STATUS (WATERMARK VISUAL) --}}
            @if($po->status && in_array($po->status->slug, ['pending_approval', 'approved_manager']))
                <div class="mb-4 alert alert-warning border-warning bg-warning bg-opacity-10 d-flex align-items-center rounded-3">
                    <i class="bi bi-hourglass-split fs-1 me-3 text-warning"></i>
                    <div>
                        <h5 class="mb-1 fw-bold text-dark">
                            {{ $po->status->slug == 'approved_manager' ? 'MENUNGGU PERSETUJUAN DIREKTUR' : 'MENUNGGU PERSETUJUAN MANAGER' }}
                        </h5>
                        <small class="text-muted">Dokumen ini masih dalam tahap review. Belum sah untuk dikirim ke vendor.</small>
                    </div>
                </div>
            @elseif($po->status && $po->status->slug === 'rejected')
                <div class="mb-4 alert alert-danger border-danger bg-danger bg-opacity-10 d-flex align-items-center rounded-3">
                    <i class="bi bi-x-octagon-fill fs-1 me-3 text-danger"></i>
                    <div>
                        <h5 class="mb-1 fw-bold text-dark">PO DITOLAK</h5>
                        <div class="text-danger small fw-bold">Alasan: {{ $po->notes }}</div>
                    </div>
                </div>
            @endif

            {{-- A. INFO PERUSAHAAN & DETAIL PO --}}
            <div class="mb-5 row">
                <div class="col-6">
                    <h4 class="mb-1 fw-bold text-dark">{{ $po->billTo->name ?? 'Nama Perusahaan Anda' }}</h4>
                    <p class="mb-0 text-muted small w-75">
                        {{ $po->billTo->address ?? 'Alamat kantor belum disetting.' }}
                    </p>
                </div>
                <div class="col-6 text-end">
                    <table class="table w-auto mb-0 table-sm table-borderless ms-auto text-end">
                        <tr>
                            <td class="align-middle text-muted small">Nomor PO :</td>
                            <td class="align-middle fw-bold text-dark fs-5">{{ $po->po_number }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Tanggal Terbit :</td>
                            <td class="fw-bold text-dark">{{ \Carbon\Carbon::parse($po->po_date)->format('d M Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Jatuh Tempo :</td>
                            <td class="fw-bold text-danger">{{ $po->due_date ? \Carbon\Carbon::parse($po->due_date)->format('d M Y') : '-' }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            {{-- B. ALAMAT (VENDOR & SHIP TO) --}}
            <div class="mb-5 row g-4">
                <div class="col-md-6">
                    <div class="p-3 border h-100 bg-light rounded-3">
                        <small class="mb-2 d-block fw-bold text-muted text-uppercase">Vendor (Kepada)</small>
                        <h6 class="mb-1 fw-bold text-primary">{{ $po->vendor->name }}</h6>
                        <p class="mb-1 small text-dark">{{ $po->vendor->address ?? 'Alamat vendor kosong' }}</p>
                        <p class="mb-0 small text-dark"><i class="bi bi-telephone me-1"></i> {{ $po->vendor->phone ?? '-' }}</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 border h-100 bg-light rounded-3">
                        <small class="mb-2 d-block fw-bold text-muted text-uppercase">Dikirim Ke (Ship To)</small>
                        <p class="mb-0 small text-dark fst-italic" style="white-space: pre-line;">
                            {{ !empty($po->shipping_address) ? $po->shipping_address : 'Sesuai alamat kantor pusat.' }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- C. TABEL ITEM --}}
            <div class="mb-4 table-responsive">
                <table class="table align-middle table-hover border-top">
                    <thead class="bg-light text-secondary small text-uppercase">
                        <tr>
                            <th class="py-3 ps-3" width="5%">#</th>
                            <th class="py-3" width="40%">Deskripsi Barang</th>
                            <th class="py-3 text-center" width="10%">Qty</th>
                            <th class="py-3 text-end" width="15%">Harga Satuan</th>
                            <th class="py-3 text-center" width="10%">Disc</th>
                            <th class="py-3 text-end pe-3" width="15%">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($po->items as $item)
                            <tr>
                                <td class="ps-3">{{ $loop->iteration }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $item->description }}</div>
                                    @if($item->notes)
                                        <div class="mt-1 text-muted small fst-italic"><i class="bi bi-sticky me-1"></i>{{ $item->notes }}</div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold">{{ $item->qty_ordered + 0 }}</span>
                                    <span class="small text-muted">{{ $item->uom }}</span>
                                </td>
                                <td class="text-end">
                                    <span class="text-muted small me-1">{{ $po->currency }}</span>
                                    {{ number_format($item->unit_price, 0, ',', '.') }}
                                </td>
                                <td class="text-end text-danger">
                                    @if($item->discount_amount > 0)
                                        <span class="text-muted small me-1">- {{ $po->currency }}</span>
                                        {{ number_format($item->discount_amount, 0, ',', '.') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-end fw-bold text-dark pe-3">
                                    <span class="text-muted small me-1">{{ $po->currency }}</span>
                                    {{ number_format($item->subtotal, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- D. SUMMARY & TOTALS --}}
            <div class="row">
                <div class="col-md-7">
                    <div class="mt-2">
                        <label class="mb-1 small fw-bold text-muted">Catatan / Instruksi:</label>
                        <div class="p-3 border rounded bg-light small text-dark fst-italic">
                            {{ $po->notes ?: 'Tidak ada catatan khusus.' }}
                        </div>
                    </div>
                    <div class="mt-4 row small text-muted">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Termin Pembayaran:</strong> <br> {{ $po->payment_terms ?? 'Standard' }}</p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Mata Uang:</strong> <br> {{ $po->currency }}</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 ms-auto">
                    <div class="p-3 border rounded bg-light">
                        <div class="mb-2 d-flex justify-content-between">
                            <span class="text-muted">Subtotal</span>
                            <span class="fw-bold text-dark">
                                <small class="text-muted me-1">{{ $po->currency }}</small>
                                {{ number_format($po->subtotal, 0, ',', '.') }}
                            </span>
                        </div>
                        @if($po->discount_total > 0)
                        <div class="mb-2 d-flex justify-content-between text-danger">
                            <span>Diskon Global</span>
                            <span class="fw-bold">
                                <small class="me-1">- {{ $po->currency }}</small>
                                {{ number_format($po->discount_total, 0, ',', '.') }}
                            </span>
                        </div>
                        @endif
                        <div class="mb-2 d-flex justify-content-between">
                            <span class="text-muted">Pajak (Total Tax)</span>
                            <span class="fw-bold text-dark">
                                <small class="text-muted me-1">{{ $po->currency }}</small>
                                {{ number_format($po->tax_total, 0, ',', '.') }}
                            </span>
                        </div>

                        @php $totalCharges = $po->charges->sum('amount'); @endphp
                        <div class="mb-2 d-flex justify-content-between">
                            <span class="text-muted">Biaya Lain (Charges)</span>
                            <span class="fw-bold text-dark">
                                <small class="text-muted me-1">{{ $po->currency }}</small>
                                {{ number_format($totalCharges, 0, ',', '.') }}
                            </span>
                        </div>

                        @foreach($po->charges as $charge)
                            <div class="mb-1 d-flex justify-content-between ps-3 border-start border-3 border-secondary">
                                <small class="text-muted fst-italic" style="font-size: 0.8rem;">- {{ $charge->name }}</small>
                                <small class="text-muted" style="font-size: 0.8rem;">
                                    {{ number_format($charge->amount, 0, ',', '.') }}
                                </small>
                            </div>
                        @endforeach

                        <hr class="my-2 border-secondary">

                        <div class="d-flex justify-content-between align-items-center">
                            <span class="mb-0 h6 fw-bold text-dark">GRAND TOTAL</span>
                            <span class="mb-0 h5 fw-bold text-primary">
                                {{ $po->currency }} {{ number_format($po->grand_total, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- E. TANDA TANGAN --}}
            <div class="pt-5 mt-5 text-center row page-break-inside-avoid">
                <div class="col-4">
                    <p class="pb-5 mb-5 small text-muted">Dibuat Oleh,</p>
                    <div class="mx-auto border-bottom border-dark w-75"></div>
                    <p class="mt-2 small fw-bold">{{ $po->creator->name ?? 'Staff Procurement' }}</p>
                </div>
                <div class="col-4">
                    <p class="pb-5 mb-5 small text-muted">Disetujui Oleh,</p>
                    <div class="mx-auto border-bottom border-dark w-75"></div>
                    <p class="mt-2 small fw-bold">Manager Procurement</p>
                </div>
                <div class="col-4">
                    <p class="pb-5 mb-5 small text-muted">Disetujui Final Oleh,</p>
                    <div class="mx-auto border-bottom border-dark w-75"></div>
                    <p class="mt-2 small fw-bold">Direktur Utama</p>
                </div>
            </div>

        </div>
    </div>

</div>

{{-- ================= MODAL REJECT ================= --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('po.reject', $po->id) }}" method="POST" class="border-0 shadow modal-content rounded-4">
            @csrf
            <div class="text-white modal-header bg-danger">
                <h5 class="modal-title fw-bold">Tolak Purchase Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="p-4 modal-body">
                <div class="mb-3 alert alert-warning small">
                    <i class="bi bi-exclamation-circle me-1"></i> PO ini akan dikembalikan ke status <strong>REJECTED</strong>.
                </div>
                <label class="form-label fw-bold small text-uppercase">Alasan Penolakan <span class="text-danger">*</span></label>
                <textarea name="reject_reason" class="form-control" rows="3" required placeholder="Contoh: Harga tidak sesuai kesepakatan..."></textarea>
            </div>
            <div class="border-0 modal-footer bg-light rounded-bottom-4">
                <button type="button" class="btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="px-4 btn btn-danger rounded-pill fw-bold">Konfirmasi Tolak</button>
            </div>
        </form>
    </div>
</div>

{{-- ================= CSS PRINT ================= --}}
<style>
    @media print {
        body { background-color: white; -webkit-print-color-adjust: exact; }
        .no-print, nav, header, footer { display: none !important; }
        .container { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
        .card { border: none !important; box-shadow: none !important; margin-bottom: 0 !important; }
        .card-header { -webkit-print-color-adjust: exact; color-adjust: exact; }
        .bg-primary { background-color: #0d6efd !important; color: white !important; }
        .bg-warning { background-color: #ffc107 !important; }
        .bg-danger { background-color: #dc3545 !important; color: white !important; }

        #print-area { position: relative; left: 0; top: 0; width: 100%; }
        .page-break-inside-avoid { break-inside: avoid; }
    }
</style>

@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Update fungsi SweetAlert agar menerima teks dinamis
        function confirmApprove(actionText) {
            Swal.fire({
                title: 'Konfirmasi Persetujuan?',
                text: "Anda akan melakukan: " + actionText,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-check-circle me-1"></i> Ya, Lanjutkan!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Memproses...',
                        text: 'Mohon tunggu sebentar.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    document.getElementById('form-approve').submit();
                }
            });
        }
    </script>
@endpush
