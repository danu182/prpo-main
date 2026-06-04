@extends('layouts.app')

@section('content')

@php
    // LOGIKA HAK AKSES & STATUS
    $statusSlug = $pr->status->slug ?? 'draft';

    // Apakah ini giliran Manager untuk memproses?
    $isManagerTurn = auth()->user()->hasAnyRole(['manager', 'Super Admin']) && $statusSlug === 'pending_approval';

    // Apakah ini giliran Direktur untuk memproses?
    $isDirectorTurn = auth()->user()->hasAnyRole(['direktur', 'Super Admin']) && $statusSlug === 'approved_manager';

    // Apakah user yang login punya hak untuk mengubah form ini SEKARANG?
    $canDecide = $isManagerTurn || $isDirectorTurn;
@endphp

<div class="container pb-5">
    {{-- HEADER KEMBALI & PRINT --}}
    <div class="mb-4 d-flex justify-content-between align-items-center no-print">
        <a href="{{ route('pr.index') }}" class="bg-white shadow-sm btn btn-outline-secondary rounded-pill fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>

        @if(in_array($statusSlug, ['approved', 'po_issued']))
            <button onclick="window.print()" class="shadow-sm btn btn-primary rounded-pill fw-bold">
                <i class="bi bi-printer-fill me-1"></i> Cetak PR
            </button>
        @endif
    </div>

    {{-- BANNER STATUS GLOBAL --}}
    @if($statusSlug === 'rejected')
        <div class="mb-4 shadow-sm alert alert-danger bg-danger bg-opacity-10 border-danger d-flex align-items-center rounded-4">
            <i class="bi bi-x-octagon-fill fs-1 me-3 text-danger"></i>
            <div>
                <h5 class="mb-1 fw-bold text-dark">PR DITOLAK SECARA GLOBAL</h5>
                <div class="text-danger small fw-bold">Permintaan ini telah ditolak dan tidak dapat diproses lebih lanjut.</div>
            </div>
        </div>
    @elseif($statusSlug === 'approved')
        <div class="mb-4 shadow-sm alert alert-success bg-success bg-opacity-10 border-success d-flex align-items-center rounded-4">
            <i class="bi bi-check-circle-fill fs-1 me-3 text-success"></i>
            <div>
                <h5 class="mb-1 fw-bold text-dark">PR DISETUJUI FINAL</h5>
                <div class="text-success small fw-bold">Menunggu tim Purchasing / Admin PO untuk menerbitkan Purchase Order.</div>
            </div>
        </div>
    @endif

    {{-- INFO HEADER DOKUMEN --}}
    <div class="mb-4 border-0 shadow-sm card rounded-4">
        <div class="p-4 card-body">
            <div class="mb-3 d-flex justify-content-between align-items-start">
                <div>
                    <h5 class="mb-0 fw-bold text-dark">Purchase Request</h5>
                    <span class="mt-2 border badge bg-light text-primary border-primary fs-6">{{ $pr->pr_number }}</span>
                </div>

                @if($pr->status)
                    <span class="badge bg-{{ $pr->status->color }}-subtle text-{{ $pr->status->color }} border border-{{ $pr->status->color }}-subtle rounded-pill px-3 py-2 shadow-sm fs-6">
                        <i class="bi bi-circle-fill me-1" style="font-size: 0.6rem;"></i> {{ $pr->status->name }}
                    </span>
                @else
                    <span class="px-3 py-2 border shadow-sm badge bg-secondary-subtle text-secondary border-secondary-subtle rounded-pill fs-6">
                        <i class="bi bi-circle-fill me-1" style="font-size: 0.6rem;"></i> Pending
                    </span>
                @endif
            </div>

            <div class="mt-3 row g-4">
                <div class="col-md-3">
                    <small class="text-muted fw-bold d-block text-uppercase" style="font-size: 0.75rem;">Requester</small>
                    <p class="mt-1 mb-0 fw-bold text-dark">{{ $pr->user->name }}</p>
                </div>
                <div class="col-md-3">
                    <small class="text-muted fw-bold d-block text-uppercase" style="font-size: 0.75rem;">Departemen</small>
                    <p class="mt-1 mb-0 fw-bold text-dark">{{ $pr->company->name ?? 'Head Office' }}</p>
                </div>
                <div class="col-md-3">
                    <small class="text-muted fw-bold d-block text-uppercase" style="font-size: 0.75rem;">Tanggal Request</small>
                    <p class="mt-1 mb-0 fw-bold text-dark">{{ \Carbon\Carbon::parse($pr->request_date)->format('d M Y') }}</p>
                </div>
                <div class="col-md-3">
                    <small class="text-muted fw-bold d-block text-uppercase" style="font-size: 0.75rem;">Target Selesai</small>
                    @php
                        $needDate = \Carbon\Carbon::parse($pr->need_date);
                        $isUrgent = $needDate->isPast() || $needDate->diffInDays(now()) <= 3;
                    @endphp
                    <p class="mt-1 mb-0 fw-bold {{ $isUrgent ? 'text-danger' : 'text-dark' }}">
                        <i class="bi bi-calendar-check me-1"></i> {{ $needDate->format('d M Y') }}
                        @if($isUrgent && !in_array($statusSlug, ['approved', 'rejected']))
                            <span class="badge bg-danger ms-1" style="font-size: 0.6rem;">Mendesak</span>
                        @endif
                    </p>
                </div>

                <div class="col-12">
                    <div class="p-3 border border-secondary-subtle bg-light rounded-3">
                        <small class="mb-1 text-muted fw-bold d-block">Keterangan / Tujuan PR:</small>
                        <p class="mb-0 text-dark fst-italic">{{ $pr->description ?? '-' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>




    {{-- ========================================================================= --}}
    {{-- FORM PERSETUJUAN --}}
    {{-- ========================================================================= --}}
    <form action="{{ route('pr.decide', $pr->id) }}" method="POST" id="form-approval">
        @csrf

        {{-- DAFTAR BARANG (ITEMS) & VENDOR REKOMENDASI --}}
        <div class="mb-4 overflow-hidden border-0 shadow-sm card rounded-4">
            <div class="py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam me-2 text-primary"></i>Detail Barang & Keputusan</h6>
                @if($canDecide)
                    <span class="shadow-sm badge bg-warning text-dark"><i class="bi bi-pencil-square me-1"></i> Mode Review Aktif</span>
                @endif
            </div>

            <div class="p-0 card-body">
                @foreach($pr->items as $item)
                    @php
                        $isRejectedByManager = ($item->status === 'REJECTED');
                    @endphp

                    <div id="item_wrapper_{{$item->id}}" class="p-4 border-bottom {{ $isRejectedByManager && !$isDirectorTurn ? 'bg-danger bg-opacity-10' : ($loop->even ? 'bg-light bg-opacity-25' : 'bg-white') }}">
                        <div class="row g-4">

                            {{-- 1. IDENTITAS BARANG & UBAH QTY --}}
                            <div class="col-lg-4 border-end-lg">
                                <h6 class="mb-1 fw-bold text-dark">{{ $item->item->name }}</h6>
                                <div class="mb-3 text-muted small"><i class="bi bi-upc-scan me-1"></i> {{ $item->item->code }}</div>

                                @if($canDecide)
                                    <label class="mb-1 small fw-bold text-muted">Kuantitas Disetujui ({{ $item->item->unit }}):</label>
                                    <div class="shadow-sm input-group input-group-sm w-75">
                                        <input type="number" name="items[{{ $item->id }}][qty]" class="form-control fw-bold text-primary" value="{{ $item->qty }}" min="0.01" step="0.01">
                                        <span class="bg-white input-group-text">{{ $item->item->unit }}</span>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.65rem;">*Ubah angka jika ingin mengurangi permintaan</small>
                                @else
                                    <span class="p-2 bg-white border shadow-sm badge text-dark">
                                        Qty Final: <strong class="text-primary fs-6">{{ $item->qty }} {{ $item->item->unit }}</strong>
                                    </span>
                                @endif
                            </div>

                            {{-- 2. PILIHAN VENDOR REKOMENDASI --}}
                            <div class="col-lg-4 px-lg-3">
                                <small class="mb-2 text-muted fw-bold d-block text-uppercase" style="font-size: 0.7rem;">Rekomendasi Vendor (Opsional)</small>

                                <div class="gap-2 d-flex flex-column">
                                    @if($canDecide)
                                    <div class="mb-1 form-check small">
                                        <input class="form-check-input" type="radio" name="items[{{ $item->id }}][vendor_id]" id="novendor_{{$item->id}}" value="" checked>
                                        <label class="form-check-label text-muted fst-italic" for="novendor_{{$item->id}}">
                                            Tidak menetapkan vendor (Biar tim PO cari)
                                        </label>
                                    </div>
                                    @endif

                                    @forelse($item->vendorQuotes as $quote)
                                        @php $isSelected = ($item->suggested_vendor_id == $quote->vendor_id); @endphp
                                        <div class="p-3 mb-2 border rounded hover-shadow {{ $isSelected ? 'border-success bg-success bg-opacity-10' : 'bg-white' }}">
                                            <div class="m-0 form-check">
                                                {{-- VALUE DI BAWAH INI SANGAT PENTING --}}
                                                <input type="radio" name="items[{{ $item->id }}][vendor_id]" id="v_{{$quote->id}}" value="{{ $quote->vendor_id }}" class="form-check-input" {{ $isSelected ? 'checked' : '' }} {{ !$canDecide ? 'disabled' : '' }}>
                                                <label class="form-check-label w-100" for="v_{{$quote->id}}">
                                                    <div class="mb-1 d-flex justify-content-between align-items-center">
                                                        <span class="fw-bold text-dark">{{ $quote->vendor->name }}</span>
                                                        <span class="bg-white border badge text-primary fw-bold">
                                                            {{ $quote->currency ?? 'IDR' }} {{ number_format($quote->quoted_price, 0, ',', '.') }}
                                                        </span>
                                                    </div>
                                                    @if($quote->reference_link)
                                                        <div class="mt-1 small"><a href="{{ $quote->reference_link }}" target="_blank" class="text-decoration-none"><i class="bi bi-link-45deg"></i> Link Referensi</a></div>
                                                    @endif
                                                    @if($quote->attachment)
                                                        <div class="mt-2 text-end">
                                                            <a href="{{ asset('storage/' . $quote->attachment) }}" target="_blank" class="px-2 py-0 btn btn-xs btn-outline-primary fw-bold" style="font-size: 0.65rem;">
                                                                <i class="bi bi-file-earmark-pdf"></i> Lihat File
                                                            </a>
                                                        </div>
                                                    @endif
                                                </label>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-muted small fst-italic">Belum ada penawaran.</div>
                                    @endforelse
                                </div>
                            </div>

                            {{-- 3. KEPUTUSAN PER ITEM (Tolak / Setuju) --}}
                            <div class="col-lg-4 border-start-lg ps-lg-3">
                                <small class="mb-2 text-muted fw-bold d-block text-uppercase" style="font-size: 0.7rem;">Status Item</small>

                                @if($canDecide)
                                    <div class="gap-2 mb-2 d-flex">
                                        <input type="radio" class="btn-check" name="items[{{ $item->id }}][status]" id="app_{{$item->id}}" value="APPROVED" {{ !$isRejectedByManager ? 'checked' : '' }} onchange="toggleReason({{$item->id}}, 'APPROVED')">
                                        <label class="shadow-sm btn btn-outline-success btn-sm fw-bold flex-fill" for="app_{{$item->id}}">
                                            <i class="bi bi-check-circle me-1"></i> Disetujui
                                        </label>

                                        <input type="radio" class="btn-check" name="items[{{ $item->id }}][status]" id="rej_{{$item->id}}" value="REJECTED" {{ $isRejectedByManager ? 'checked' : '' }} onchange="toggleReason({{$item->id}}, 'REJECTED')">
                                        <label class="shadow-sm btn btn-outline-danger btn-sm fw-bold flex-fill" for="rej_{{$item->id}}">
                                            <i class="bi bi-x-circle me-1"></i> Tolak Item
                                        </label>
                                    </div>

                                    <div id="reason_box_{{$item->id}}" class="mt-2 {{ $isRejectedByManager ? '' : 'd-none' }}">
                                        <input type="text" name="items[{{ $item->id }}][reject_reason]" id="reason_input_{{$item->id}}" class="form-control form-control-sm border-danger text-danger bg-danger bg-opacity-10" placeholder="Ketik alasan penolakan..." value="{{ $item->rejection_reason }}">
                                        @if($isDirectorTurn && $isRejectedByManager)
                                            <small class="mt-1 text-danger fw-bold d-block" style="font-size: 0.65rem;"><i class="bi bi-info-circle"></i> Sebelumnya ditolak oleh Manager.</small>
                                        @endif
                                    </div>
                                @else
                                    @if($item->status == 'APPROVED')
                                        <span class="px-3 py-2 border badge bg-success bg-opacity-10 text-success border-success"><i class="bi bi-check-circle-fill me-1"></i> Item Disetujui</span>
                                    @elseif($item->status == 'REJECTED')
                                        <div class="p-2 border rounded border-danger bg-danger bg-opacity-10 text-danger small">
                                            <span class="fw-bold d-block"><i class="bi bi-x-circle-fill me-1"></i> Ditolak:</span>
                                            <span class="fst-italic">"{{ $item->rejection_reason }}"</span>
                                        </div>
                                    @else
                                        <span class="badge bg-secondary">Menunggu Keputusan</span>
                                    @endif
                                @endif
                            </div>

                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- PANEL EKSEKUSI AKHIR (MENGGANTIKAN FIXED-BOTTOM LAMA) --}}
        @if($canDecide)
            <div class="mb-4 overflow-hidden shadow-sm card border-primary rounded-4">
                <div class="py-3 text-white card-header bg-primary d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-shield-lock-fill me-2"></i>Eksekusi Keputusan Dokumen</h6>
                </div>

                <div class="p-4 bg-opacity-50 card-body bg-light">
                    <div class="mb-0 border-0 shadow-sm alert alert-primary rounded-3 text-dark d-flex align-items-center">
                        <i class="bi bi-info-circle-fill fs-3 me-3 text-primary"></i>
                        <div>Pastikan Anda sudah meninjau setiap item di atas. Anda dapat menolak seluruh dokumen ini secara global, atau menyetujuinya untuk diteruskan ke tahap selanjutnya.</div>
                    </div>

                    <div class="gap-3 mt-4 d-flex justify-content-end">
                        <button type="button" onclick="confirmAction('REJECT')" class="px-4 bg-white shadow-sm btn btn-outline-danger rounded-pill fw-bold">
                            <i class="bi bi-x-octagon-fill me-1"></i> Tolak Dokumen (Global)
                        </button>

                        <button type="button" onclick="confirmAction('APPROVE')" class="px-5 text-white shadow-sm btn btn-success rounded-pill fw-bold">
                            <i class="bi bi-check-all me-1"></i>
                            {{ $isManagerTurn ? 'Setujui & Teruskan (Ke Direktur)' : 'Setujui Final' }}
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </form>

    {{-- ========================================================================= --}}
    {{-- RIWAYAT AKTIVITAS (HISTORY TIMELINE) --}}
    {{-- ========================================================================= --}}
    <div class="mb-5 border-0 shadow-sm card rounded-4">
        <div class="py-3 bg-white card-header border-bottom">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history me-2 text-primary"></i>Riwayat Dokumen</h6>
        </div>
        <div class="card-body">
            <div class="mt-2 timeline ps-3">
                @forelse($pr->histories as $log)
                    <div class="pb-4 border-2 border-opacity-25 border-start border-primary ps-4 position-relative timeline-item">
                        <span class="top-0 border border-2 border-white position-absolute start-0 translate-middle bg-primary rounded-circle" style="width: 14px; height: 14px;"></span>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark fs-6">{{ $log->action }}</span>
                            <span class="text-muted small"><i class="bi bi-calendar-event me-1"></i> {{ $log->created_at->format('d M Y - H:i') }}</span>
                        </div>
                        <div class="mt-1 small text-muted"><i class="bi bi-person-circle me-1"></i> Oleh: <strong>{{ $log->user->name ?? 'Sistem' }}</strong></div>

                        <div class="p-3 mt-2 border border-secondary-subtle rounded-3 bg-light text-dark" style="font-size: 0.85rem;">
                            {!! nl2br(preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', e($log->note))) !!}
                        </div>
                    </div>
                @empty
                    <p class="py-3 text-center text-muted small"><i class="bi bi-info-circle me-1"></i> Belum ada riwayat dokumen.</p>
                @endforelse
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    function toggleReason(itemId, status) {
        const reasonBox = document.getElementById('reason_box_' + itemId);
        const reasonInput = document.getElementById('reason_input_' + itemId);

        if (status === 'REJECTED') {
            reasonBox.classList.remove('d-none');
            reasonInput.setAttribute('required', 'required');
            reasonBox.style.animation = "fadeIn 0.3s ease-in-out";
        } else {
            reasonBox.classList.add('d-none');
            reasonInput.removeAttribute('required');
            reasonInput.value = '';
        }
    }

    function confirmAction(actionType) {
        let titleText = "";
        let descText = "";
        let confirmBtnColor = "";
        let confirmText = "";
        let iconType = "";

        if(actionType === 'APPROVE') {
            titleText = "Setujui Dokumen?";
            descText = "Pastikan semua rincian kuantitas dan keputusan item sudah benar. Dokumen akan diteruskan.";
            confirmBtnColor = "#198754";
            confirmText = "Ya, Eksekusi!";
            iconType = "question";
        } else {
            titleText = "Tolak Dokumen (Global)?";
            descText = "Seluruh item dalam Purchase Request ini akan dibatalkan secara permanen.";
            confirmBtnColor = "#dc3545";
            confirmText = "Ya, Tolak Semua!";
            iconType = "warning";
        }

        Swal.fire({
            title: titleText,
            text: descText,
            icon: iconType,
            showCancelButton: true,
            confirmButtonColor: confirmBtnColor,
            cancelButtonColor: "#6c757d",
            cancelButtonText: "Batal",
            reverseButtons: true,
            customClass: {
                confirmButton: 'rounded-pill px-4 shadow-sm',
                cancelButton: 'rounded-pill px-4 border'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses Keputusan...',
                    text: 'Sistem sedang menyimpan dan mengirim notifikasi.',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading()
                    }
                });

                const form = document.getElementById('form-approval');

                if(actionType === 'REJECT') {
                    let input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'global_action';
                    input.value = 'REJECT';
                    form.appendChild(input);
                }

                form.submit();
            }
        });
    }
</script>

<style>
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
@endpush
