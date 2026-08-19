@extends('layouts.app')

@push('css')
    <style>
        .hover-shadow:hover { box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important; transition: all 0.3s ease; }
    </style>
@endpush

@section('content')
<div class="py-4 container-fluid">

    @php
        // DEKLARASI STATUS DI AWAL AGAR MUDAH DIPANGGIL KE BAWAH
        $statusSlug = optional($bill->status)->slug ?? 'unknown';
        $statusName = optional($bill->status)->name ?? 'UNKNOWN';
        $statusColor = optional($bill->status)->color ?? 'secondary';
    @endphp

    {{-- HEADER & TOMBOL AKSI --}}
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 fw-bold text-dark">
                Detail Tagihan Opex: <span class="text-primary">{{ $bill->bill_number }}</span>
            </h4>
            <div class="text-muted small">
                Dibuat oleh: <span class="fw-bold">{{ $bill->user->name ?? 'System' }}</span> pada {{ $bill->created_at->format('d M Y H:i') }}
            </div>
        </div>
        <div class="gap-2 d-flex">
            <a href="{{ route('bills.index') }}" class="btn btn-outline-secondary rounded-pill fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>

            @if(in_array($statusSlug, ['pending', 'draft']))
                <a href="{{ route('bills.edit', $bill->bill_number) }}" class="btn btn-warning rounded-pill text-dark fw-bold">
                    <i class="bi bi-pencil me-1"></i> Edit Tagihan
                </a>
            @endif

            {{-- 🔥 TOMBOL CETAK DIPERBARUI: SPLIT DROPDOWN DENGAN BPR 🔥 --}}
            <div class="shadow-sm btn-group">
                {{-- Tombol Utama (Kiri) --}}
                <a href="{{ route('bills.print', $bill->bill_number) }}" target="_blank" class="btn btn-dark fw-bold rounded-start-pill">
                    <i class="bi bi-printer me-1"></i> Cetak PDF Resmi
                </a>
                {{-- Tombol Panah Dropdown (Kanan) --}}
                <button type="button" class="btn btn-dark dropdown-toggle dropdown-toggle-split rounded-end-pill" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="visually-hidden">Toggle Dropdown</span>
                </button>
                {{-- Menu Pilihan (Dropdown) --}}
                <ul class="mt-2 border-0 shadow dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="py-2 dropdown-item fw-medium" href="{{ route('bills.print_with_attachments', $bill->bill_number) }}" target="_blank">
                            <i class="bi bi-file-earmark-plus text-primary me-2"></i> Cetak + Lampiran
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="py-2 dropdown-item fw-medium" href="{{ route('bills.prinBpr', $bill->bill_number) }}" target="_blank">
                            <i class="bi bi-bank text-success me-2"></i> Cetak Form BPR
                        </a>
                    </li>
                    <li>
                        <a class="py-2 dropdown-item fw-medium" href="{{ route('bills.printBprWithAttachments', $bill->bill_number) }}" target="_blank">
                            <i class="bi bi-bank text-success me-2"></i> Cetak Form BPR + Lampiran
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- KOLOM KIRI: INFO UTAMA & ITEM --}}
        <div class="col-lg-8">

            {{-- STATUS & INFO DASAR --}}
            <div class="mb-4 border-0 shadow-sm card rounded-4">
                <div class="p-4 card-body">
                    <div class="mb-4 d-flex justify-content-between align-items-start">

                        <span class="badge bg-{{ $statusColor }}-subtle text-{{ $statusColor }} px-3 py-2 rounded-pill fw-bold border border-{{ $statusColor }} text-uppercase">
                            <i class="bi bi-info-circle me-1"></i> STATUS: {{ $statusName }}
                        </span>

                        {{-- Info Jatuh Tempo --}}
                        <div class="text-end">
                            <small class="mb-1 text-muted d-block fw-bold">JATUH TEMPO</small>
                            <span class="px-3 py-1 border border-opacity-25 fs-5 fw-bold text-danger bg-danger-subtle rounded-3 border-danger">
                                {{ $bill->due_date ? date('d M Y', strtotime($bill->due_date)) : '-' }}
                            </span>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="mb-1 small text-muted fw-bold">DIBAYARKAN OLEH (PT)</label>
                            <div class="p-2 border d-flex align-items-center bg-light rounded-3">
                                <div class="p-2 bg-white shadow-sm text-primary rounded-circle me-2">
                                    <i class="bi bi-building"></i>
                                </div>
                                <div class="fw-bold text-dark">{{ $bill->company->name ?? '-' }}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="mb-1 small text-muted fw-bold">VENDOR / SUPPLIER</label>
                            <div class="p-2 border d-flex align-items-center bg-light rounded-3 h-100">
                                <div class="p-2 bg-white shadow-sm text-warning rounded-circle me-2">
                                    <i class="bi bi-shop"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">{{ $bill->vendor_name }}</div>
                                    <small class="text-muted"><i class="bi bi-calendar-event me-1"></i>Tgl Inv: {{ date('d M Y', strtotime($bill->invoice_date)) }}</small>
                                </div>
                            </div>
                        </div>

                        {{-- 🔥 BLOK INFO INVOICE & REKENING (BARU) 🔥 --}}
                        <div class="col-md-12">
                            <label class="mb-1 small text-muted fw-bold">DETAIL PEMBAYARAN</label>
                            <div class="p-3 border d-flex flex-column flex-sm-row justify-content-between bg-light rounded-3">
                                <div>
                                    <div class="mb-1 text-muted small">Mata Uang: <span class="fw-bold text-dark">{{ $bill->currency ?? 'IDR' }}</span></div>
                                    <div class="mb-1 text-muted small">No. Invoice: <span class="fw-bold text-primary">{{ $bill->vendor_invoice_number ?: '-' }}</span></div>
                                    <div class="mb-0 text-muted small">No. Rekening: <span class="fw-bold text-success">{{ $bill->account_number ?: '-' }}</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TABEL ITEM UTAMA --}}
            <div class="mb-4 border-0 shadow-sm card rounded-4">
                <div class="py-3 bg-white card-header border-bottom-0">
                    <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-list-check me-2"></i>Rincian Item Jasa / Opex</h6>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0 align-middle table-borderless table-striped">
                        <thead class="bg-light text-secondary small fw-bold">
                            <tr>
                                <th class="py-3 ps-4">NAMA ITEM & DESKRIPSI</th>
                                <th class="py-3 text-center">QTY</th>
                                <th class="py-3 text-end">HARGA SATUAN</th>
                                <th class="py-3 text-end">PAJAK & DISKON</th>
                                <th class="py-3 text-end pe-4">TOTAL BERSIH</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bill->items as $item)
                            <tr class="border-bottom border-light">
                                <td class="py-3 ps-4">
                                    <div class="fw-bold text-dark">{{ $item->name }}</div>
                                    @if($item->description)
                                        <div class="small text-muted fst-italic">{{ $item->description }}</div>
                                    @endif
                                </td>
                                <td class="py-3 text-center">
                                    <span class="bg-white border badge text-dark">{{ $item->qty }}</span>
                                </td>
                                <td class="py-3 text-end">
                                    {{ $bill->currency }} {{ number_format($item->price, 0, ',', '.') }}
                                </td>
                                <td class="py-3 text-end small">
                                    @if($item->discount_amount > 0)
                                        <div class="mb-1 text-danger" title="Diskon Item">
                                            <i class="bi bi-tag-fill me-1"></i>- {{ number_format($item->discount_amount, 0, ',', '.') }}
                                        </div>
                                    @endif
                                    @if($item->tax_amount > 0)
                                        <div class="text-info" title="Pajak Item">
                                            + {{ number_format($item->tax_amount, 0, ',', '.') }}
                                        </div>
                                    @endif
                                    @if($item->discount_amount == 0 && $item->tax_amount == 0)
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="py-3 text-end pe-4 fw-bold text-primary">
                                    {{ $bill->currency }} {{ number_format($item->amount, 0, ',', '.') }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- PEMBAGIAN 2 KOLOM UNTUK CHARGE & DISCOUNT --}}
            <div class="mb-4 row g-4">
                {{-- TABEL BIAYA TAMBAHAN (CHARGES) --}}
                @if($bill->charges->count() > 0)
                <div class="col-md-6">
                    <div class="border-0 border-opacity-50 shadow-sm card rounded-4 border-warning h-100">
                        <div class="py-3 bg-white card-header border-bottom-0">
                            <h6 class="mb-0 fw-bold text-warning-emphasis"><i class="bi bi-plus-circle-dotted me-2"></i>Biaya Tambahan</h6>
                        </div>
                        <div class="p-0 card-body">
                            <table class="table mb-0 align-middle table-sm small">
                                <tbody>
                                    @foreach($bill->charges as $charge)
                                    <tr>
                                        <td class="py-2 ps-3">
                                            <div class="fw-bold">{{ optional($charge->chargeType)->name ?? 'Biaya Lainnya' }}</div>
                                            @if($charge->note) <div class="text-xs text-muted fst-italic">{{ $charge->note }}</div> @endif
                                        </td>
                                        <td class="py-2 text-end pe-3 fw-bold text-dark">
                                            + {{ number_format($charge->amount, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif

                {{-- TABEL POTONGAN BIAYA (DISCOUNTS) --}}
                @if($bill->discounts->count() > 0)
                <div class="col-md-6">
                    <div class="border-0 border-opacity-50 shadow-sm card rounded-4 border-danger h-100">
                        <div class="py-3 bg-white card-header border-bottom-0">
                            <h6 class="mb-0 fw-bold text-danger"><i class="bi bi-dash-circle-dotted me-2"></i>Potongan Tambahan</h6>
                        </div>
                        <div class="p-0 card-body">
                            <table class="table mb-0 align-middle table-sm small">
                                <tbody>
                                    @foreach($bill->discounts as $discount)
                                    <tr>
                                        <td class="py-2 ps-3">
                                            <div class="fw-bold">{{ optional($discount->discountType)->name ?? 'Diskon Lainnya' }}</div>
                                            @if($discount->note) <div class="text-xs text-muted fst-italic">{{ $discount->note }}</div> @endif
                                        </td>
                                        <td class="py-2 text-end pe-3 fw-bold text-danger">
                                            - {{ number_format($discount->amount, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            {{-- LAMPIRAN FILE --}}
            @if($attachments->count() > 0)
            <div class="mb-4 border-0 shadow-sm card rounded-4">
                <div class="py-3 bg-white card-header border-bottom-0 rounded-top-4">
                    <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-paperclip me-2"></i>Lampiran Dokumen Bukti</h6>
                </div>
                <div class="p-4 pt-0 card-body">
                    <div class="row g-3">
                        @foreach($attachments as $file)
                        <div class="col-md-6">
                            <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="text-decoration-none">
                                <div class="p-3 border rounded-4 bg-light d-flex align-items-center hover-shadow">
                                    <div class="p-2 bg-white shadow-sm rounded-circle me-3">
                                        @if(Str::endsWith(strtolower($file->file_name), ['.jpg', '.jpeg', '.png']))
                                            <i class="bi bi-file-image fs-4 text-primary"></i>
                                        @elseif(Str::endsWith(strtolower($file->file_name), ['.pdf']))
                                            <i class="bi bi-file-pdf fs-4 text-danger"></i>
                                        @else
                                            <i class="bi bi-file-earmark-text fs-4 text-secondary"></i>
                                        @endif
                                    </div>
                                    <div class="overflow-hidden">
                                        <div class="fw-bold text-dark text-truncate" title="{{ $file->file_name }}">{{ $file->file_name }}</div>
                                        <small class="text-muted">Klik untuk melihat</small>
                                    </div>
                                </div>
                            </a>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            {{-- AREA AUDIT TRAIL / HISTORY --}}
            <div class="mt-4 border-0 shadow-sm card rounded-4 bg-light">
                <div class="p-4 card-body">
                    <h6 class="mb-4 fw-bold text-dark"><i class="bi bi-clock-history me-2"></i>Riwayat Aktivitas & Audit Trail</h6>

                    <div class="timeline-wrapper ms-2">
                        @forelse($bill->histories->sortByDesc('created_at') as $history)
                            <div class="d-flex gap-3 {{ !$loop->last ? 'mb-4' : '' }}">
                                <div class="flex-shrink-0 text-center" style="width: 40px; position: relative;">
                                    @php
                                        $color = match($history->action) {
                                            'CREATED'  => 'primary',
                                            'UPDATED'  => 'warning',
                                            'APPROVED' => 'success',
                                            'REJECTED' => 'danger',
                                            default    => 'secondary'
                                        };
                                        $icon = match($history->action) {
                                            'CREATED'  => 'bi-plus-lg',
                                            'UPDATED'  => 'bi-pencil',
                                            'APPROVED' => 'bi-check-lg',
                                            'REJECTED' => 'bi-x-lg',
                                            default    => 'bi-circle'
                                        };
                                    @endphp

                                    <div class="rounded-circle bg-{{ $color }}-subtle text-{{ $color }} d-flex align-items-center justify-content-center shadow-sm position-relative z-1" style="width: 36px; height: 36px; border: 2px solid white;">
                                        <i class="bi {{ $icon }} small fw-bold"></i>
                                    </div>
                                    @if(!$loop->last)
                                        <div class="bg-opacity-25 position-absolute start-50 translate-middle-x bg-secondary" style="top: 36px; bottom: -24px; width: 2px;"></div>
                                    @endif
                                </div>

                                <div class="pb-2 w-100">
                                    <div class="mb-1 d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-dark">{{ $history->action }}</span>
                                        <small class="text-muted fw-semibold">{{ $history->created_at->format('d M Y, H:i') }}</small>
                                    </div>
                                    <div class="p-2 mb-1 bg-white border shadow-sm text-secondary small rounded-3">
                                        {!! nl2br(e($history->note ?? 'Tidak ada catatan.')) !!}
                                    </div>
                                    <div class="mt-1 d-flex align-items-center">
                                        <i class="bi bi-person-circle text-primary me-1 small"></i>
                                        <span class="text-primary small fw-bold">{{ $history->user->name ?? 'Sistem Robot' }}</span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="py-3 text-center bg-white border text-muted small rounded-3">Belum ada riwayat tercatat.</div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>

        {{-- KOLOM KANAN: SUMMARY & ACTIONS --}}
        <div class="col-lg-4">

            @php
                // 1. Cari Antrean PENDING untuk dokumen ini
                $currentApproval = \App\Models\DocumentApproval::with('role')
                    ->where('document_id', $bill->id)
                    ->whereIn('document_type', ['App\Models\BillRequest', 'OPEX', 'BillRequest', get_class($bill)])
                    ->where('status', 'PENDING')
                    ->orderBy('step_order', 'asc')
                    ->first();

                // 2. Cek Siapa yang sedang Login
                $userRoleNames = auth()->user()->getRoleNames()->toArray();
                $userRoleIds = auth()->user()->roles->pluck('id')->toArray();

                // 3. Apakah dia Super Admin?
                $isSuperAdmin = in_array('Super Administrator', $userRoleNames) || in_array('Super Admin', $userRoleNames) || auth()->id() === 1;

                // 4. Logika Penentu Muncul/Tidaknya Tombol Approval
                $canApprove = false;
                if ($currentApproval) {
                    if (in_array($currentApproval->role_id, $userRoleIds) || $isSuperAdmin) {
                        $canApprove = true;
                    }
                } else {
                    if ($isSuperAdmin) {
                        $canApprove = true;
                    }
                }
            @endphp

            {{-- PANEL APPROVAL --}}
            @if(in_array($statusSlug, ['pending', 'partial_approved', 'draft']) && $canApprove)
            <div class="mb-4 border border-0 shadow-sm card rounded-4 border-warning">
                <div class="p-4 card-body bg-warning-subtle rounded-4">
                    <h6 class="mb-2 fw-bold text-dark">
                        <i class="bi bi-shield-lock-fill me-2 text-warning"></i>Tindakan Persetujuan
                        @if(!$currentApproval && $isSuperAdmin)
                            <span class="badge bg-danger ms-2" style="font-size: 0.6rem;">BYPASS MODE</span>
                        @endif
                    </h6>
                    <p class="mb-4 small text-muted">Silakan tinjau tagihan ini. Status tagihan akan berubah dan tidak dapat dibatalkan setelah diproses.</p>

                    <div class="gap-2 d-grid">
                        <form action="{{ route('bills.approve', $bill->bill_number) }}" method="POST" id="form-approve">
                            @csrf
                            <button type="button" class="py-2 shadow-sm btn btn-success w-100 fw-bold rounded-pill" id="btn-approve">
                                <i class="bi bi-check-circle-fill me-2"></i> SETUJUI (APPROVE)
                            </button>
                        </form>
                        <button type="button" class="py-2 bg-white btn btn-outline-danger w-100 fw-bold rounded-pill" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="bi bi-x-circle me-2"></i> TOLAK TAGIHAN
                        </button>
                    </div>
                </div>
            </div>
            @endif

            {{-- PANEL PEMBATALAN TAGIHAN (VOID BILL) JIKA BELUM LUNAS --}}
            @php
                $isBillActive = !in_array($statusSlug, ['paid', 'lunas', 'void', 'cancelled', 'rejected']);
                $hasPartialPayment = in_array($statusSlug, ['partial', 'partial_paid', 'dicicil', 'partial_approved']);
            @endphp

            @if($isBillActive && $isSuperAdmin)
            <div class="mb-4 border border-0 shadow-sm card rounded-4 border-danger">
                <div class="p-4 card-body bg-danger-subtle rounded-4">
                    <h6 class="mb-2 fw-bold text-danger">
                        <i class="bi bi-trash3-fill me-2"></i>Pembatalan Tagihan
                    </h6>

                    @if($hasPartialPayment)
                        <div class="p-3 mb-0 border-0 alert alert-danger small rounded-3">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>Aksi Terkunci:</strong> Tagihan ini sudah memiliki riwayat pembayaran. Batalkan riwayat pembayarannya terlebih dahulu jika ingin me-Void tagihan ini.
                        </div>
                    @else
                        <p class="mb-3 small text-muted">Tagihan ini belum dibayar/lunas. Anda dapat membatalkan (Void) keseluruhan tagihan ini.</p>

                        <form id="formVoidBill" action="{{ route('bills.void', $bill->bill_number) }}" method="POST" class="w-100">
                            @csrf
                            <button type="button" class="py-2 bg-white shadow-sm btn btn-outline-danger w-100 fw-bold rounded-pill" onclick="window.prosesVoidBill()">
                                <i class="bi bi-x-octagon-fill me-2"></i> Void / Batal Tagihan
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            @endif

            {{-- PANEL REJECTED INFO --}}
            @if($statusSlug == 'rejected')
            <div class="mb-4 text-white border-0 shadow-sm card rounded-4 bg-danger">
                <div class="p-4 card-body">
                    <h6 class="mb-3 fw-bold"><i class="bi bi-exclamation-octagon-fill me-2"></i>Tagihan Ditolak</h6>
                    <div class="mb-1 opacity-75 small">Alasan Penolakan:</div>
                    <div class="p-3 bg-white shadow-sm small text-danger rounded-3 fw-semibold">
                        "{{ $bill->rejection_reason }}"
                    </div>
                </div>
            </div>
            @endif

            {{-- PANEL JIKA SUDAH LUNAS (PAID) --}}
            @if(in_array(strtolower($statusSlug), ['paid', 'lunas']) || strtoupper($bill->status) === 'PAID')
            <div class="mb-4 border border-0 shadow-sm card rounded-4 border-success">
                <div class="p-4 card-body bg-success-subtle rounded-4">
                    <h6 class="mb-2 fw-bold text-success">
                        <i class="bi bi-check-circle-fill me-2"></i>Tagihan Lunas (Paid)
                    </h6>
                    <p class="mb-4 small text-muted">Tambahkan bukti transfer susulan jika tertinggal. Pembatalan pembayaran hanya bisa dilakukan oleh Atasan.</p>

                    <div class="gap-2 d-grid">
                        <button type="button" class="py-2 shadow-sm btn btn-success w-100 fw-bold rounded-pill" data-bs-toggle="modal" data-bs-target="#uploadSusulanModal">
                            <i class="bi bi-cloud-upload me-2"></i> Upload Bukti Susulan
                        </button>

                        @if($isSuperAdmin || in_array('manager', array_map('strtolower', $userRoleNames)))
                            <button type="button" class="py-2 bg-white shadow-sm btn btn-outline-danger w-100 fw-bold rounded-pill" data-bs-toggle="modal" data-bs-target="#voidPaymentModal">
                                <i class="bi bi-x-octagon-fill me-2"></i> Batalkan Pembayaran (Void)
                            </button>
                        @endif
                    </div>
                </div>
            </div>
            @endif


            {{-- KARTU TOTAL HARGA --}}
            <div class="mb-4 overflow-hidden text-white border-0 shadow card rounded-4 bg-primary position-relative">
                <div class="top-0 p-3 opacity-25 position-absolute end-0">
                    <i class="bi bi-cash-stack" style="font-size: 5rem;"></i>
                </div>
                <div class="p-4 text-center card-body position-relative z-1">
                    <small class="tracking-wide opacity-75 fw-bold text-uppercase">Total Tagihan Bersih</small>
                    <h2 class="mt-2 mb-0 fw-bold display-6">
                        <span class="fs-4">{{ $bill->currency }}</span> {{ number_format($bill->amount, 0, ',', '.') }}
                    </h2>
                </div>
            </div>

            {{-- RINCIAN KALKULASI --}}
            <div class="mb-4 border-0 shadow-sm card rounded-4">
                <div class="p-4 card-body">
                    <h6 class="mb-4 fw-bold text-secondary small text-uppercase"><i class="bi bi-calculator-fill me-2"></i>Ringkasan Biaya</h6>

                    @php
                        $grossItems = $bill->items->sum('subtotal');
                        $totalItemDisc = $bill->items->sum('discount_amount');
                        $totalExtDisc = $bill->discounts->sum('amount');
                        $totalDiscounts = $totalItemDisc + $totalExtDisc;
                    @endphp

                    <div class="mb-3 d-flex justify-content-between small">
                        <span class="text-muted fw-semibold">Subtotal Item</span>
                        <span class="fw-bold text-dark">{{ number_format($grossItems, 0, ',', '.') }}</span>
                    </div>

                    @if($totalDiscounts > 0)
                    <div class="mb-3 d-flex justify-content-between small text-danger">
                        <span class="fw-semibold">Total Diskon / Potongan</span>
                        <span class="fw-bold">- {{ number_format($totalDiscounts, 0, ',', '.') }}</span>
                    </div>
                    @endif

                    @if($bill->total_tax > 0)
                    <div class="mb-3 d-flex justify-content-between small text-info">
                        <span class="fw-semibold">Total Pajak Item</span>
                        <span class="fw-bold">+ {{ number_format($bill->total_tax, 0, ',', '.') }}</span>
                    </div>
                    @endif

                    @if($bill->total_charge > 0)
                    <div class="pt-3 mb-3 d-flex justify-content-between small text-warning-emphasis border-top">
                        <span class="fw-semibold">Total Biaya Tambahan</span>
                        <span class="fw-bold">+ {{ number_format($bill->total_charge, 0, ',', '.') }}</span>
                    </div>
                    @endif

                    <hr class="opacity-25 text-muted">
                    <div class="d-flex justify-content-between fw-bold fs-5 text-primary">
                        <span>Grand Total</span>
                        <span>{{ number_format($bill->amount, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            {{-- INFO RECURRING --}}
            @if($bill->is_recurring)
            <div class="mb-4 border border-0 shadow-sm card rounded-4 border-info">
                <div class="p-4 card-body bg-info-subtle rounded-4">
                    <div class="mb-3 d-flex align-items-center">
                        <div class="p-2 bg-white shadow-sm rounded-circle text-info me-3">
                            <i class="bi bi-arrow-repeat fs-5"></i>
                        </div>
                        <h6 class="mb-0 fw-bold text-info-emphasis">Siklus Berulang Aktif</h6>
                    </div>
                    <p class="p-2 mb-3 bg-white border rounded small text-muted">
                        Sistem akan membuat tagihan ini secara otomatis setiap <strong>{{ $bill->recurring_interval }} {{ $bill->recurring_period }}</strong>.
                    </p>
                    @if($bill->next_generation_date)
                        <div class="mb-3 small">
                            <span class="text-muted fw-bold">Jadwal Generate Berikutnya:</span><br>
                            <div class="mt-1 fs-6 fw-bold text-dark">
                                <i class="bi bi-calendar-check text-success me-1"></i> {{ date('d F Y', strtotime($bill->next_generation_date)) }}
                            </div>
                        </div>
                    @endif

                    {{-- TOMBOL STOP LANGGANAN --}}
                    <form action="{{ route('bills.stop_recurring', $bill->bill_number) }}" method="POST" class="pt-3 mt-2 border-top border-info">
                        @csrf
                        <button type="button" class="bg-white btn btn-outline-danger btn-sm w-100 rounded-pill fw-bold" onclick="if(confirm('Yakin ingin menghentikan langganan ini? Sistem tidak akan membuat tagihan otomatis lagi untuk dokumen ini.')) this.form.submit();">
                            <i class="bi bi-slash-circle me-1"></i> Hentikan Langganan
                        </button>
                    </form>
                </div>
            </div>
            @endif

            {{-- CATATAN --}}
            @if($bill->description)
            <div class="border-0 shadow-sm card rounded-4 bg-light">
                <div class="p-4 card-body">
                    <h6 class="mb-2 fw-bold text-secondary small text-uppercase"><i class="bi bi-journal-text me-2"></i>Catatan Tagihan</h6>
                    <p class="mb-0 small text-dark fst-italic fw-semibold">
                        "{{ $bill->description }}"
                    </p>
                </div>
            </div>
            @endif

        </div>
    </div>
</div>

{{-- MODAL REJECT --}}
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="border-0 shadow-lg modal-content rounded-4">
            <form action="{{ route('bills.reject', $bill->bill_number) }}" method="POST">
                @csrf
                <div class="pb-0 border-0 modal-header">
                    <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-circle-fill me-2"></i>Tolak Tagihan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="p-4 modal-body">
                    <div class="mb-4 border-0 alert alert-warning small rounded-3">
                        Tindakan ini akan mengubah status menjadi <strong>REJECTED</strong>. Tagihan tidak akan bisa dibayar.
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-muted">ALASAN PENOLAKAN <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control rounded-3" rows="4" placeholder="Contoh: Dokumen kurang lengkap, Nominal tidak sesuai kesepakatan..." required></textarea>
                    </div>
                </div>
                <div class="px-4 pt-0 pb-4 border-0 modal-footer">
                    <button type="button" class="px-4 btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="px-4 shadow-sm btn btn-danger rounded-pill fw-bold">
                        <i class="bi bi-send-x me-1"></i> Konfirmasi Tolak
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL PEMBATALAN KESELURUHAN TAGIHAN (VOID BILL) --}}
<div class="modal fade" id="voidBillModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="border-0 shadow-lg modal-content rounded-4">
            <form action="{{ route('bills.void', $bill->bill_number) }}" method="POST">
                @csrf
                <div class="pb-0 border-0 modal-header">
                    <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Void / Batal Tagihan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="p-4 modal-body">
                    <div class="mb-4 border-0 alert alert-danger small rounded-3">
                        Tindakan ini akan membatalkan tagihan secara <strong>PERMANEN</strong> menjadi status VOID. Tagihan ini tidak akan dapat diproses lagi.
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-muted">ALASAN PEMBATALAN <span class="text-danger">* (minimal 5 karakter)</span></label>
                        <textarea name="void_reason" class="form-control rounded-3" rows="3" placeholder="Contoh: Salah input vendor, dobel tagihan..." required></textarea>
                    </div>
                </div>
                <div class="px-4 pt-0 pb-4 border-0 modal-footer">
                    <button type="button" class="px-4 btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="px-4 shadow-sm btn btn-danger rounded-pill fw-bold">
                        <i class="bi bi-trash me-1"></i> Konfirmasi Void
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // FUNGSI VOID BILL DENGAN SWEETALERT INPUT
        window.prosesVoidBill = function() {
            Swal.fire({
                title: 'Void / Batal Tagihan?',
                text: "Tindakan ini akan membatalkan tagihan secara PERMANEN menjadi status VOID.",
                icon: 'warning',
                input: 'textarea',
                inputPlaceholder: 'Ketik alasan pembatalan di sini (Wajib)...',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash"></i> Konfirmasi Void',
                cancelButtonText: 'Batal',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Alasan pembatalan tidak boleh kosong!';
                    }
                    if (value.length < 5) {
                        return 'Alasan minimal harus 5 karakter!';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Memproses...',
                        text: 'Membatalkan tagihan secara permanen...',
                        icon: 'info',
                        showConfirmButton: false,
                        allowOutsideClick: false
                    });

                    // Sisipkan teks alasan ke dalam Form lalu Submit ke Laravel
                    const form = document.getElementById('formVoidBill');
                    const reasonInput = document.createElement('input');
                    reasonInput.type = 'hidden';
                    reasonInput.name = 'void_reason';
                    reasonInput.value = result.value;
                    form.appendChild(reasonInput);

                    form.submit();
                }
            });
        };

        document.getElementById('btn-approve')?.addEventListener('click', function() {
            Swal.fire({
                title: 'Setujui Tagihan?',
                html: "Tagihan sebesar <strong class='text-primary fs-4'>{{ $bill->currency }} {{ number_format($bill->amount, 0, ',', '.') }}</strong> akan diteruskan ke tim Finance untuk dibayar.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Approve!',
                cancelButtonText: 'Cek Lagi',
                reverseButtons: true,
                borderRadius: '15px'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Memproses...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading() }
                    });
                    document.getElementById('form-approve').submit();
                }
            })
        });

        @if(session('success'))
            const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true });
            Toast.fire({ icon: 'success', title: "{{ session('success') }}" });
        @endif

        @if(session('error'))
            Swal.fire({ icon: 'error', title: 'Oops...', text: "{{ session('error') }}", borderRadius: '15px' });
        @endif
    </script>
@endpush
