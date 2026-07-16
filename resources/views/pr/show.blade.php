@extends('layouts.app')

@push('css')
<style>
    /* Tipografi & Warna Dasar */
    .info-label { font-size: 0.75rem; font-weight: 700; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }
    .info-value { font-size: 0.95rem; font-weight: 600; color: #212529; }

    /* Tabel Nested (Rincian Vendor) */
    .table-nested-container { background-color: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef; overflow: hidden; }
    .table-nested { margin-bottom: 0; }
    .table-nested th { font-size: 0.75rem; background-color: #f1f3f5; color: #495057; text-transform: uppercase; font-weight: 700; border-bottom: 1px solid #e9ecef; }
    .table-nested td { font-size: 0.8rem; vertical-align: middle; border-bottom: 1px solid #f1f3f5; }
    .table-nested tr:last-child td { border-bottom: none; }

    /* Kotak Spesifikasi */
    .spec-box { background-color: #fff9e6; border-left: 4px solid #ffc107; border-radius: 0 6px 6px 0; font-size: 0.85rem; color: #495057; }
    .spec-box p { margin-bottom: 0.5rem; }
    .spec-box p:last-child { margin-bottom: 0; }

    /* Timeline Riwayat */
    .timeline { border-left: 3px solid #e9ecef; padding-left: 20px; list-style: none; position: relative; margin-bottom: 0; }
    .timeline-item { position: relative; margin-bottom: 25px; }
    .timeline-item:last-child { margin-bottom: 0; }
    .timeline-item::before { content: ""; position: absolute; left: -28px; top: 0; width: 14px; height: 14px; border-radius: 50%; background-color: var(--bs-primary); border: 3px solid #fff; box-shadow: 0 0 0 2px var(--bs-primary); }
    .timeline-date { font-size: 0.75rem; color: #6c757d; font-weight: bold; margin-bottom: 4px; }
    .timeline-content { background: #fff; padding: 15px; border-radius: 10px; border: 1px solid #e9ecef; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }

    /* Kolom Approval Khusus */
    .approval-column { background-color: #f4fdf8 !important; border-left: 2px dashed #198754 !important; }

    /* Form Elements */
    input:disabled, select:disabled, textarea:disabled { cursor: not-allowed !important; background-color: #e9ecef !important; opacity: 0.7; }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    {{-- ========================================== --}}
    {{-- 1. HEADER & TOMBOL AKSI --}}
    {{-- ========================================== --}}
    <div class="pb-3 mb-4 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center border-bottom">
        <div class="mb-3 mb-lg-0">
            <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Detail PR</h4>
                <span class="badge bg-dark rounded-pill px-3 py-2 shadow-sm fs-6">{{ $pr->pr_number }}</span>
                @if($pr->status)
                    <span class="badge bg-{{ $pr->status->color ?? 'secondary' }}-subtle text-{{ $pr->status->color ?? 'secondary' }} border border-{{ $pr->status->color ?? 'secondary' }}-subtle px-3 py-2 rounded-pill shadow-sm fs-6">
                        {{ mb_strtoupper($pr->status->name) }}
                    </span>
                @endif
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('pr.index') }}" class="px-4 shadow-sm btn btn-light rounded-pill fw-bold border">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>

            {{-- Dropdown Cetak agar rapi --}}
            <div class="dropdown">
                <button class="px-4 shadow-sm btn btn-outline-secondary rounded-pill fw-bold dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-printer-fill me-1"></i> Opsi Cetak
                </button>
                <ul class="dropdown-menu shadow-sm rounded-3">
                    <li><a class="dropdown-item py-2 fw-medium" href="{{ route('pr.print', $pr->pr_number) }}" target="_blank"><i class="bi bi-file-pdf text-danger me-2"></i> Cetak Dokumen Standar</a></li>
                    <li><a class="dropdown-item py-2 fw-medium text-success" href="{{ route('pr.print_complete', $pr->pr_number) }}" target="_blank"><i class="bi bi-file-earmark-pdf-fill me-2"></i> Cetak Lengkap (+ Lampiran)</a></li>
                </ul>
            </div>

            @if($isEditable)
                <a href="{{ route('pr.edit', $pr->pr_number) }}" class="px-4 shadow-sm btn btn-warning text-dark rounded-pill fw-bold">
                    <i class="bi bi-pencil-square me-1"></i> Revisi PR
                </a>
            @endif

            @php
                $currentStatusSlug = strtolower(optional($pr->status)->slug);
                $unallowableStatuses = ['po_issued', 'partial_po', 'canceled', 'cancelled', 'batal', 'dibatalkan', 'rejected', 'ditolak', 'completed'];
                $canCancel = auth()->check() && (auth()->id() == $pr->user_id || auth()->id() == $pr->created_by || auth()->user()->hasRole('Super Admin'));
            @endphp

            @if(!in_array($currentStatusSlug, $unallowableStatuses) && $canCancel)
                <button type="button" class="px-4 shadow-sm btn btn-danger rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#cancelPrModal">
                    <i class="bi bi-x-octagon-fill me-1"></i> Batalkan PR
                </button>
            @endif
        </div>
    </div>

    {{-- ALERT NOTIFIKASI --}}
    @if(session('success'))
        <div class="mb-4 border-0 shadow-sm alert alert-success rounded-4 fw-bold"><i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 border-0 shadow-sm alert alert-danger rounded-4 fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}</div>
    @endif

    <div class="mb-4 row g-4">
        {{-- ========================================== --}}
        {{-- 2. KARTU INFORMASI UTAMA --}}
        {{-- ========================================== --}}
        <div class="col-lg-12">
            <div class="overflow-hidden border-0 border-4 shadow-sm card rounded-4 border-start border-primary">
                <div class="p-4 bg-white card-body">
                    <div class="row g-4">
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">User Peminta</div>
                            <div class="mt-1 info-value"><i class="bi bi-person-circle text-muted me-2"></i>{{ optional($pr->user)->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Unit / PT Penanggung</div>
                            <div class="mt-1 info-value"><i class="bi bi-buildings text-muted me-2"></i>{{ optional($pr->company)->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Tanggal Request</div>
                            <div class="mt-1 info-value"><i class="bi bi-calendar-check text-muted me-2"></i>{{ \Carbon\Carbon::parse($pr->request_date)->format('d M Y') }}</div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Target Selesai</div>
                            <div class="mt-1 info-value text-danger"><i class="bi bi-calendar-exclamation text-danger me-2"></i>{{ $pr->need_date ? \Carbon\Carbon::parse($pr->need_date)->format('d M Y') : '-' }}</div>
                        </div>
                        <div class="col-md-12">
                            <div class="info-label mb-1">Tujuan / Keterangan Pengadaan</div>
                            <div class="p-3 border rounded-3 info-value bg-light text-secondary">{{ $pr->description ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ========================================== --}}
        {{-- 3. TABEL ITEM & PERSETUJUAN --}}
        {{-- ========================================== --}}
        <div class="col-lg-12">
            <form action="{{ route('pr.decide', $pr->pr_number) }}" method="POST" id="approvalForm">
                @csrf
                <input type="hidden" name="global_action" id="globalActionInput" value="">

                @if($canApprove)
                    <div class="mb-3 border-0 shadow-sm alert alert-success rounded-4 d-flex align-items-center">
                        <i class="bi bi-shield-check fs-2 me-3"></i>
                        <div>
                            <h6 class="mb-1 fw-bold">Mode Persetujuan Aktif ({{ $currentRoleName }})</h6>
                            <small class="mb-0">Silakan periksa detail vendor dan berikan keputusan (Setuju/Tolak) untuk masing-masing item pada tabel di bawah.</small>
                        </div>
                    </div>
                @endif

                <div class="overflow-hidden border-0 shadow-sm card rounded-4">
                    <div class="py-3 card-header bg-white border-bottom">
                        <h6 class="mb-0 fw-bold text-dark text-uppercase"><i class="bi bi-box-seam me-2 text-primary"></i>Detail Item & Referensi Vendor</h6>
                    </div>
                    <div class="p-0 bg-white card-body">
                        <div class="table-responsive">
                            <table class="table mb-0 align-middle table-hover">
                                <thead class="bg-light border-bottom text-muted small text-uppercase fw-bold">
                                    <tr>
                                        <th class="py-3 ps-4" width="30%">Barang & Spesifikasi</th>
                                        <th class="py-3" width="35%">Referensi Vendor & Penawaran</th>
                                        @if($canApprove)
                                            <th class="py-3 text-center bg-success text-white" width="12%">Qty Disetujui</th>
                                            <th class="py-3 bg-success text-white pe-4" width="23%">Keputusan & Alasan</th>
                                        @else
                                            <th class="py-3 text-center" width="15%">Qty Permintaan</th>
                                            <th class="py-3 text-center pe-4" width="20%">Status Item</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pr->items as $item)
                                        @php
                                            // 🔥 LOGIKA PEMBERSIH JSON UOM 🔥
                                            $masterItem = $item->item;
                                            $baseUomName = optional(optional($masterItem)->uom)->name ?? 'Unit';

                                            $uomStr = $item->uom ?? 'Unit';
                                            if (is_string($uomStr) && str_starts_with(trim($uomStr), '{')) {
                                                $uomObj = json_decode($uomStr);
                                                $uomStr = $uomObj->code ?? $uomObj->name ?? 'Unit';
                                            } elseif (is_object($uomStr) || is_array($uomStr)) {
                                                $uomStr = $uomStr->code ?? $uomStr->name ?? (is_array($uomStr) ? ($uomStr['code'] ?? 'Unit') : 'Unit');
                                            }

                                            $tampilanSatuanLengkap = strtoupper($uomStr);
                                            $convQty = 1;
                                            $savedUomId = $item->uom_id ?? $item->item_uom_id ?? null;

                                            if (!empty($savedUomId) && optional($masterItem)->itemUoms) {
                                                $altUom = collect($masterItem->itemUoms)->where('id', $savedUomId)->first();
                                                if ($altUom) {
                                                    $convQty = (float) $altUom->conversion_qty;
                                                    $tampilanSatuanLengkap = strtoupper($altUom->uom_name) . ' (Isi: ' . $convQty . ' ' . $baseUomName . ')';
                                                }
                                            }
                                            $totalBase = (float)$item->qty * $convQty;
                                        @endphp

                                        <tr class="align-top border-bottom">
                                            {{-- KOLOM 1: BARANG --}}
                                            <td class="py-4 ps-4">
                                                <div class="fw-bold text-dark fs-6">{{ optional($item->item)->name ?? 'Item Terhapus' }}</div>
                                                <div class="mb-3 small text-muted">Kode: {{ optional($item->item)->code ?? '-' }}</div>

                                                @if($item->specification)
                                                    <div class="p-3 mb-2 shadow-sm spec-box">
                                                        <div class="mb-1 fw-bold" style="font-size: 0.75rem; text-transform: uppercase;">Spesifikasi / Detail Khusus:</div>
                                                        {!! $item->specification !!}
                                                    </div>
                                                @endif

                                                @if($item->notes || $item->description || optional($item->item)->specification)
                                                    <div class="mt-2 text-muted small fst-italic">
                                                        <strong>Catatan:</strong> {{ $item->notes ?? $item->description ?? optional($item->item)->specification ?? '-' }}
                                                    </div>
                                                @endif
                                            </td>

                                            {{-- KOLOM 2: VENDOR --}}
                                            <td class="py-4 pe-3">
                                                @if($item->vendorQuotes && $item->vendorQuotes->count() > 0)
                                                    <div class="table-nested-container">
                                                        <table class="table table-borderless table-nested">
                                                            <thead>
                                                                <tr>
                                                                    <th width="45%" class="ps-3">Vendor & Harga</th>
                                                                    <th width="35%">Referensi</th>
                                                                    <th width="20%" class="text-center">Lampiran</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($item->vendorQuotes as $quote)
                                                                    @php
                                                                        $currCode = 'IDR';
                                                                        if($quote->currency_id) {
                                                                            $currObj = \App\Models\Currency::find($quote->currency_id);
                                                                            if($currObj) $currCode = $currObj->code;
                                                                        }
                                                                    @endphp
                                                                    <tr>
                                                                        <td class="py-3 ps-3">
                                                                            <div class="fw-bold text-dark small">{{ optional($quote->vendor)->name ?? 'Vendor Terhapus' }}</div>
                                                                            <div class="mt-1 badge bg-success-subtle text-success border border-success-subtle">{{ $currCode }} {{ number_format($quote->quoted_price ?? $quote->price ?? 0, 0, ',', '.') }}</div>
                                                                        </td>
                                                                        <td class="py-3">
                                                                            @if($quote->reference_link)
                                                                                <a href="{{ $quote->reference_link }}" target="_blank" class="mb-1 text-decoration-none badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-link-45deg"></i> Link Referensi</a><br>
                                                                            @endif
                                                                            @if($quote->notes)
                                                                                <div class="small text-muted fst-italic mt-1" style="font-size: 0.7rem;">"{{ $quote->notes }}"</div>
                                                                            @endif
                                                                        </td>
                                                                        <td class="py-3 text-center align-middle">
                                                                            @if($quote->attachments && $quote->attachments->count() > 0)
                                                                                <div class="d-flex flex-column gap-1 align-items-center">
                                                                                    @foreach($quote->attachments as $idx => $file)
                                                                                        <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2 text-truncate" style="font-size: 0.7rem; max-width: 90px;" title="{{ $file->file_name }}">
                                                                                            <i class="bi bi-paperclip"></i> File {{ $idx + 1 }}
                                                                                        </a>
                                                                                    @endforeach
                                                                                </div>
                                                                            @else
                                                                                <span class="text-muted small fst-italic" style="font-size: 0.7rem;">- Tdk ada -</span>
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @else
                                                    <div class="p-3 text-center border rounded text-muted small fst-italic bg-light border-dashed">Belum ada penawaran vendor.</div>
                                                @endif
                                            </td>

                                            {{-- KOLOM 3 & 4: APPROVAL ATAU STATUS --}}
                                            @if($canApprove)
                                                <td class="py-4 text-center approval-column">
                                                    <div class="input-group input-group-sm mx-auto shadow-sm" style="max-width: 120px;">
                                                        <input type="number" name="items[{{ $item->id }}][qty]" class="text-center form-control fw-bold text-success border-success" value="{{ (float)$item->qty }}" max="{{ (float)$item->qty }}" min="0" step="0.01">
                                                    </div>
                                                    <div class="mt-2 text-center">
                                                        <span class="badge bg-white text-dark border border-secondary shadow-sm">{{ $tampilanSatuanLengkap }}</span>
                                                    </div>
                                                    <div class="mt-1 small text-muted text-center" style="font-size: 0.7rem;">Maks: {{ (float)$item->qty }}</div>
                                                </td>

                                                <td class="py-4 pe-4 approval-column">
                                                    <select name="items[{{ $item->id }}][status]" class="mb-2 form-select form-select-sm fw-bold border-secondary shadow-sm" onchange="toggleRejectReason(this, {{ $item->id }})">
                                                        <option value="APPROVED" class="text-success">✅ Setujui Item Ini</option>
                                                        <option value="REJECTED" class="text-danger">❌ Tolak Item Ini</option>
                                                    </select>

                                                    @if($item->vendorQuotes && $item->vendorQuotes->count() > 0)
                                                        <select name="items[{{ $item->id }}][vendor_id]" id="vendor_{{ $item->id }}" class="mb-2 form-select form-select-sm text-dark bg-white shadow-sm" style="font-size: 0.75rem;">
                                                            <option value="">-- Bebas Vendor --</option>
                                                            @foreach($item->vendorQuotes as $quote)
                                                                <option value="{{ $quote->vendor_id }}">Pilih: {{ optional($quote->vendor)->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <input type="hidden" name="items[{{ $item->id }}][vendor_id]" value="">
                                                    @endif

                                                    <textarea name="items[{{ $item->id }}][reject_reason]" id="reason_{{ $item->id }}" class="form-control form-control-sm bg-white text-danger d-none mt-2 shadow-sm" rows="2" placeholder="Alasan tolak wajib diisi..." disabled></textarea>
                                                </td>
                                            @else
                                                <td class="py-4 text-center">
                                                    <div class="fw-bolder fs-5 text-dark">{{ (float)$item->qty }}</div>
                                                    <div class="mt-1">
                                                        <span class="px-2 py-1 badge bg-secondary-subtle text-secondary border border-secondary-subtle fw-bold">{{ $tampilanSatuanLengkap }}</span>
                                                    </div>
                                                </td>

                                                <td class="py-4 text-center align-middle pe-4">
                                                    @php
                                                        $statusColor = 'secondary';
                                                        $statusText = strtoupper($item->status ?? 'PENDING');
                                                        if($statusText === 'APPROVED') $statusColor = 'success';
                                                        elseif($statusText === 'REJECTED') $statusColor = 'danger';
                                                    @endphp
                                                    <span class="badge bg-{{ $statusColor }} rounded-pill px-3 py-2 shadow-sm fs-6">{{ $statusText }}</span>

                                                    @if($statusText === 'REJECTED' && $item->rejection_reason)
                                                        <div class="mt-2 text-danger small fw-bold fst-italic" style="font-size: 0.75rem;">
                                                            <i class="bi bi-chat-left-text me-1"></i> "{{ $item->rejection_reason }}"
                                                        </div>
                                                    @endif
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($canApprove)
                        <div class="p-3 bg-light border-top d-flex justify-content-end gap-3">
                            <button type="button" class="px-4 py-2 shadow-sm btn btn-outline-danger fw-bold rounded-pill bg-white" onclick="submitGlobalReject()">
                                <i class="bi bi-x-circle-fill me-2"></i> Tolak Seluruh Dokumen
                            </button>
                            <button type="button" class="px-5 py-2 shadow-sm btn btn-success fw-bold rounded-pill" onclick="submitApproval()">
                                <i class="bi bi-check-circle-fill me-2"></i> Proses & Teruskan
                            </button>
                        </div>
                        @endif

                    </div>
                </div>
            </form>
        </div>

        {{-- ========================================== --}}
        {{-- 4. RIWAYAT PERJALANAN DOKUMEN --}}
        {{-- ========================================== --}}
        <div class="col-lg-12">
            <div class="overflow-hidden border-0 shadow-sm card rounded-4 border-start border-4 border-info">
                <div class="py-3 card-header bg-white border-bottom">
                    <h6 class="mb-0 fw-bold text-dark text-uppercase"><i class="bi bi-clock-history me-2 text-info"></i>Riwayat Perjalanan Dokumen</h6>
                </div>
                <div class="p-4 bg-white card-body">
                    @if($pr->histories && $pr->histories->count() > 0)
                        <ul class="timeline">
                            @foreach($pr->histories->sortByDesc('created_at') as $history)
                                <li class="timeline-item">
                                    <div class="timeline-date">{{ \Carbon\Carbon::parse($history->created_at)->translatedFormat('d M Y - H:i:s') }} WIB</div>
                                    <div class="timeline-content">
                                        <div class="mb-2 d-flex justify-content-between align-items-center">
                                            <span class="fw-bold fs-6 text-dark"><i class="bi bi-person-fill me-1 text-muted"></i> {{ optional($history->user)->name ?? 'Sistem Otomatis' }}</span>
                                            @php
                                                $actionColor = 'secondary';
                                                $actionText = strtolower($history->action);
                                                if(str_contains($actionText, 'approve') || str_contains($actionText, 'setuju')) $actionColor = 'success';
                                                if(str_contains($actionText, 'reject') || str_contains($actionText, 'tolak') || str_contains($actionText, 'batal')) $actionColor = 'danger';
                                                if(str_contains($actionText, 'create') || str_contains($actionText, 'buat')) $actionColor = 'primary';
                                                if(str_contains($actionText, 'update') || str_contains($actionText, 'revisi')) $actionColor = 'warning text-dark';
                                            @endphp
                                            <span class="badge bg-{{ $actionColor }} rounded-pill px-3 shadow-sm">{{ $history->action }}</span>
                                        </div>
                                        <div class="mb-0 small text-secondary lh-sm">{!! nl2br(preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', e($history->note))) !!}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="py-4 text-center text-muted fst-italic">
                            <i class="bi bi-info-circle fs-3 d-block mb-2 text-secondary opacity-50"></i>
                            Belum ada riwayat terekam untuk dokumen ini.
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>

{{-- 🔥 MODAL BATALKAN PR 🔥 --}}
<div class="modal fade" id="cancelPrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="border-0 shadow modal-content rounded-4">
            <form action="{{ route('pr.cancel', $pr->pr_number) }}" method="POST">
                @csrf
                <div class="text-white modal-header bg-danger rounded-top-4 border-0">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Batalkan Dokumen PR
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="p-4 modal-body bg-light text-center">
                    <i class="bi bi-x-octagon text-danger opacity-75 mb-3 d-block" style="font-size: 3rem;"></i>
                    <p class="text-dark fw-medium">Anda yakin ingin membatalkan dokumen <strong>{{ $pr->pr_number }}</strong>?</p>
                    <p class="text-muted small mb-4">Tindakan ini akan menghentikan seluruh proses persetujuan secara permanen.</p>

                    <div class="text-start">
                        <label class="form-label fw-bold small text-dark">Alasan Pembatalan <span class="text-danger">*</span></label>
                        <textarea name="cancel_reason" class="form-control border-secondary-subtle shadow-sm" rows="3" placeholder="Wajib diisi..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-white rounded-bottom-4 justify-content-center">
                    <button type="button" class="px-4 btn btn-light fw-bold rounded-pill border" data-bs-dismiss="modal">Kembali</button>
                    <button type="submit" class="px-4 btn btn-danger fw-bold rounded-pill shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Konfirmasi Pembatalan
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
    @if($canApprove)
    function toggleRejectReason(select, itemId) {
        let reasonInput = document.getElementById('reason_' + itemId);
        let vendorSelect = document.getElementById('vendor_' + itemId);

        if (select.value === 'REJECTED') {
            reasonInput.classList.remove('d-none');
            reasonInput.disabled = false;
            reasonInput.required = true;
            select.classList.remove('text-success');
            select.classList.add('text-danger', 'border-danger');

            if(vendorSelect) {
                vendorSelect.disabled = true;
                vendorSelect.value = '';
                vendorSelect.classList.add('opacity-50');
            }
        } else {
            reasonInput.classList.add('d-none');
            reasonInput.disabled = true;
            reasonInput.required = false;
            reasonInput.value = '';
            select.classList.remove('text-danger', 'border-danger');
            select.classList.add('text-success');

            if(vendorSelect) {
                vendorSelect.disabled = false;
                vendorSelect.classList.remove('opacity-50');
            }
        }
    }

    function submitGlobalReject() {
        Swal.fire({
            title: 'Tolak Seluruh PR?',
            text: "Dokumen ini akan ditolak sepenuhnya dan dikembalikan ke pembuat.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Tolak Semua!',
            cancelButtonText: 'Batal',
            borderRadius: '12px'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('globalActionInput').value = 'REJECT';
                Swal.fire({ title: 'Memproses Penolakan...', didOpen: () => { Swal.showLoading() }, allowOutsideClick: false });
                document.getElementById('approvalForm').submit();
            }
        });
    }

    function submitApproval() {
        const form = document.getElementById('approvalForm');
        if (!form.checkValidity()) { form.reportValidity(); return; }

        Swal.fire({
            title: 'Kirim Keputusan?',
            text: "Keputusan Setuju/Tolak untuk setiap item akan disimpan secara permanen.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Lanjutkan!',
            cancelButtonText: 'Batal',
            borderRadius: '12px'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('globalActionInput').value = 'APPROVE';
                Swal.fire({ title: 'Menyimpan Persetujuan...', didOpen: () => { Swal.showLoading() }, allowOutsideClick: false });
                form.submit();
            }
        });
    }
    @endif
</script>
@endpush
