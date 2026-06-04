@extends('layouts.app')

@push('css')
<style>
    .info-label { font-size: 0.75rem; font-weight: bold; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }
    .info-value { font-size: 0.95rem; font-weight: 600; color: #212529; }
    .table-nested { background-color: #f8f9fa; border-radius: 8px; overflow: hidden; border: 1px solid #e9ecef; }
    .table-nested th { font-size: 0.75rem; background-color: #e9ecef; color: #495057; }
    .table-nested td { font-size: 0.8rem; vertical-align: middle; }
    
    .timeline { border-left: 3px solid #dee2e6; padding-left: 20px; list-style: none; position: relative; margin-bottom: 0; }
    .timeline-item { position: relative; margin-bottom: 20px; }
    .timeline-item::before { content: ""; position: absolute; left: -28px; top: 0; width: 14px; height: 14px; border-radius: 50%; background-color: var(--bs-primary); border: 3px solid #fff; box-shadow: 0 0 0 2px var(--bs-primary); }
    .timeline-date { font-size: 0.75rem; color: #6c757d; font-weight: bold; margin-bottom: 4px; }
    .timeline-content { background: #f8f9fa; padding: 12px 16px; border-radius: 8px; border: 1px solid #e9ecef; }
    .timeline-content strong { color: #212529; }
    
    input:disabled, select:disabled, textarea:disabled, .form-control:disabled, .form-select:disabled {
        cursor: default !important;
    }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    <div class="pb-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom">
        <div>
            <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-file-earmark-text me-2 text-primary"></i> Detail Purchase Request</h4>
            <div class="gap-2 mt-2 d-flex align-items-center">
                <span class="px-3 py-2 shadow-sm badge bg-primary rounded-pill" style="font-size: 0.85rem;">{{ $pr->pr_number }}</span>
                @if($pr->status)
                    <span class="badge bg-{{ $pr->status->color ?? 'secondary' }}-subtle text-{{ $pr->status->color ?? 'secondary' }} border border-{{ $pr->status->color ?? 'secondary' }}-subtle px-3 py-2 rounded-pill shadow-sm" style="font-size: 0.85rem;">
                        {{ $pr->status->name }}
                    </span>
                @endif
            </div>
        </div>
        <div class="gap-2 mt-3 mt-md-0 d-flex flex-wrap">
            <a href="{{ route('pr.index') }}" class="px-4 border shadow-sm btn btn-light rounded-pill fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <a href="{{ route('pr.print', $pr->pr_number) }}" target="_blank" class="px-4 shadow-sm btn btn-outline-secondary rounded-pill fw-bold">
                <i class="bi bi-printer-fill me-1"></i> Cetak PDF
            </a>
            @if($isEditable)
                <a href="{{ route('pr.edit', $pr->pr_number) }}" class="px-4 shadow-sm btn btn-warning text-dark rounded-pill fw-bold">
                    <i class="bi bi-pencil-square me-1"></i> Revisi PR
                </a>
            @endif

            @php
                $currentStatusSlug = strtolower(optional($pr->status)->slug);
                $unallowableStatuses = ['po_issued', 'partial_po', 'canceled', 'cancelled', 'batal', 'dibatalkan', 'rejected', 'ditolak', 'completed'];
                $canCancel = false;
                if (auth()->check()) {
                    if (auth()->id() == $pr->user_id || auth()->id() == $pr->created_by || auth()->user()->hasRole('Super Admin')) {
                        $canCancel = true;
                    }
                }
            @endphp
            
            @if(!in_array($currentStatusSlug, $unallowableStatuses) && $canCancel)
                <button type="button" class="px-4 mt-3 shadow-sm btn btn-danger rounded-pill fw-bold mt-md-0" data-bs-toggle="modal" data-bs-target="#cancelPrModal">
                    <i class="bi bi-x-octagon-fill me-1"></i> Batalkan PR
                </button>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 border-0 shadow-sm alert alert-success rounded-4 fw-bold"><i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 border-0 shadow-sm alert alert-danger rounded-4 fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}</div>
    @endif

    <div class="mb-4 row g-4">
        <div class="col-lg-12">
            <div class="overflow-hidden border-0 border-4 shadow-sm card rounded-4 border-start border-primary">
                <div class="p-4 bg-white card-body">
                    <div class="row g-4">
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">User Peminta</div>
                            <div class="mt-1 info-value"><i class="bi bi-person-circle text-muted me-1"></i> {{ optional($pr->user)->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="info-label">Unit / PT Penanggung</div>
                            <div class="mt-1 info-value"><i class="bi bi-buildings text-muted me-1"></i> {{ optional($pr->company)->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <div class="info-label">Tanggal Request</div>
                            <div class="mt-1 info-value"><i class="bi bi-calendar-check text-muted me-1"></i> {{ \Carbon\Carbon::parse($pr->request_date)->format('d M Y') }}</div>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <div class="info-label">Target Selesai</div>
                            <div class="mt-1 info-value text-danger"><i class="bi bi-calendar-exclamation text-danger me-1"></i> {{ $pr->need_date ? \Carbon\Carbon::parse($pr->need_date)->format('d M Y') : '-' }}</div>
                        </div>
                        <div class="col-md-12">
                            <div class="info-label">Tujuan / Keterangan Pengadaan</div>
                            <div class="p-2 mt-1 border rounded info-value bg-light border-light-subtle">{{ $pr->description ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <form action="{{ route('pr.decide', $pr->pr_number) }}" method="POST" id="approvalForm">
                @csrf
                <input type="hidden" name="global_action" id="globalActionInput" value="">

                @if($canApprove)
                    <div class="mb-3 border-0 shadow-sm alert alert-success rounded-3">
                        <h6 class="mb-1 fw-bold"><i class="bi bi-shield-check me-2"></i> Mode Persetujuan Aktif</h6>
                        <small>Anda login sebagai <strong>{{ $currentRoleName }}</strong>. Silakan periksa detail vendor dan berikan keputusan (Setuju/Tolak) langsung pada tabel di bawah ini.</small>
                    </div>
                @endif

                <div class="overflow-hidden border-0 shadow-sm card rounded-4">
                    <div class="py-3 card-header bg-light border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-dark text-uppercase" style="font-size: 0.85rem;"><i class="bi bi-box-seam me-2 text-primary"></i>Detail Item & Referensi Vendor</h6>
                    </div>
                    <div class="p-0 bg-white card-body">
                        <div class="table-responsive">
                            <table class="table mb-0 align-middle table-hover">
                                <thead class="bg-white border-2 text-muted small border-bottom text-uppercase">
                                    <tr>
                                        <th class="py-3 ps-4" width="25%">Barang & Spesifikasi</th>
                                        <th class="py-3" width="35%">Referensi Vendor & Penawaran</th>
                                        @if($canApprove)
                                            <th class="py-3 text-center bg-success-subtle border-success" width="16%">Qty Disetujui</th>
                                            <th class="py-3 bg-success-subtle border-success pe-4" width="24%">Keputusan & Alasan</th>
                                        @else
                                            <th class="py-3 text-center" width="15%">Qty Permintaan</th>
                                            <th class="py-3 text-center pe-4" width="25%">Status Item</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pr->items as $item)
                                        @php
                                            // 🔥 LOGIKA UOM (SAMA SEPERTI DI EDIT) 🔥
                                            $masterItem = $item->item;
                                            $baseUomName = optional(optional($masterItem)->uom)->name ?? 'Unit';
                                            
                                            $tampilanSatuanLengkap = is_string($item->uom) ? $item->uom : $baseUomName; 
                                            $convQty = 1;
                                            
                                            $savedUomId = $item->uom_id ?? $item->item_uom_id ?? null;

                                            if (!empty($savedUomId) && optional($masterItem)->itemUoms) {
                                                $altUom = collect($masterItem->itemUoms)->where('id', $savedUomId)->first();
                                                if ($altUom) {
                                                    $convQty = (float) $altUom->conversion_qty;
                                                    $tampilanSatuanLengkap = $altUom->uom_name . ' (Isi: ' . $convQty . ' ' . $baseUomName . ')';
                                                }
                                            }

                                            $totalBase = (float)$item->qty * $convQty;
                                        @endphp

                                        <tr class="align-top border-bottom">
                                            <td class="py-3 ps-4">
                                                <div class="fw-bold text-dark" style="font-size: 0.95rem;">{{ optional($item->item)->name ?? 'Item Terhapus' }}</div>
                                                <div class="mb-2 small text-muted">Kode: {{ optional($item->item)->code ?? '-' }}</div>

                                                {{-- 🔥 TAMPILKAN SPESIFIKASI KHUSUS JIKA ADA 🔥 --}}
                                               {{-- 🔥 TAMPILKAN SPESIFIKASI KHUSUS JIKA ADA 🔥 --}}
                                                @if($item->specification)
                                                    <div class="info-label" style="font-size: 0.65rem;">Spesifikasi / Detail Khusus:</div>
                                                    <div class="p-2 mt-1 mb-2 border rounded shadow-sm small text-dark bg-warning bg-opacity-10 border-warning border-opacity-50">
                                                        {{-- Gunakan tanda seru agar HTML dari CKEditor terbaca rapi --}}
                                                        {!! $item->specification !!}
                                                    </div>
                                                @endif
                                                 
                                                
                                                @if($item->notes || $item->description || optional($item->item)->specification)
                                                    <div class="info-label" style="font-size: 0.65rem;">Catatan Umum:</div>
                                                    <div class="mt-1 text-secondary small fst-italic">{{ $item->notes ?? $item->description ?? optional($item->item)->specification ?? '-' }}</div>
                                                @endif
                                            </td>

                                            <td class="py-3 pe-3">
                                                @if($item->vendorQuotes && $item->vendorQuotes->count() > 0)
                                                    <div class="table-nested">
                                                        <table class="table mb-0 table-sm">
                                                            <thead>
                                                                <tr>
                                                                    <th width="40%" class="ps-2">Vendor & Harga</th>
                                                                    <th width="35%">Catatan & Link</th>
                                                                    <th width="25%" class="text-center">Lampiran</th>
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
                                                                    <tr class="border-bottom">
                                                                        <td class="py-2 ps-2">
                                                                            <div class="fw-bold text-dark small text-truncate" style="max-width: 150px;" title="{{ optional($quote->vendor)->name }}">{{ optional($quote->vendor)->name ?? 'Vendor Terhapus' }}</div>
                                                                            <div class="mt-1 badge bg-success-subtle text-success">{{ $currCode }} {{ number_format($quote->quoted_price ?? $quote->price ?? 0, 0, ',', '.') }}</div>
                                                                        </td>
                                                                        <td class="py-2">
                                                                            @if($quote->reference_link)
                                                                                <a href="{{ $quote->reference_link }}" target="_blank" class="mb-1 shadow-sm badge bg-info text-dark text-decoration-none"><i class="bi bi-link-45deg"></i> Link Toko</a><br>
                                                                            @endif
                                                                            @if($quote->notes)
                                                                                <div class="small text-muted fst-italic mt-1" style="font-size: 0.7rem;"><i class="bi bi-chat-dots me-1"></i>{{ $quote->notes }}</div>
                                                                            @endif
                                                                        </td>
                                                                        <td class="py-2 text-center align-middle">
                                                                            @if($quote->attachments && $quote->attachments->count() > 0)
                                                                                <div class="gap-1 d-flex flex-column align-items-center">
                                                                                    @foreach($quote->attachments as $idx => $file)
                                                                                        <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="px-2 py-0 shadow-sm btn btn-xs btn-outline-primary fw-bold rounded-pill text-truncate" style="font-size: 0.65rem; max-width: 100px;" title="{{ $file->file_name }}">
                                                                                            <i class="bi bi-file-earmark-pdf-fill"></i> File {{ $idx + 1 }}
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
                                                    <div class="p-2 text-center border rounded text-muted small fst-italic bg-light">Belum ada referensi vendor.</div>
                                                @endif
                                            </td>

                                            @if($canApprove)
                                                <td class="py-3 text-center bg-success-subtle">
                                                    <div class="input-group input-group-sm mx-auto" style="max-width: 150px;">
                                                        <input type="number" name="items[{{ $item->id }}][qty]" class="text-center form-control fw-bold text-primary border-success" value="{{ (float)$item->qty }}" max="{{ (float)$item->qty }}" min="0" step="0.01">
                                                    </div>
                                                    
                                                    <div class="mt-2 text-center">
                                                        <span class="badge bg-white text-dark border border-success p-2 shadow-sm" style="white-space: normal;">{{ $tampilanSatuanLengkap }}</span>
                                                    </div>
                                                    
                                                    <div class="mt-1 small text-muted text-center" style="font-size: 0.7rem;">
                                                        Maks: {{ (float)$item->qty }}
                                                    </div>
                                                    
                                                    @if($convQty > 1)
                                                        <div class="mt-1 text-danger fw-bold text-center" style="font-size: 0.7rem;">
                                                            <i class="bi bi-info-circle"></i> Setara {{ $totalBase }} {{ $baseUomName }}
                                                        </div>
                                                    @endif
                                                </td>

                                                <td class="py-3 pe-4 bg-success-subtle">
                                                    <select name="items[{{ $item->id }}][status]" class="mb-2 form-select form-select-sm fw-bold border-secondary-subtle" onchange="toggleRejectReason(this, {{ $item->id }})">
                                                        <option value="APPROVED" class="text-success">Setujui Item Ini</option>
                                                        <option value="REJECTED" class="text-danger">Tolak Item Ini</option>
                                                    </select>
                                                    
                                                    @if($item->vendorQuotes && $item->vendorQuotes->count() > 0)
                                                        <select name="items[{{ $item->id }}][vendor_id]" id="vendor_{{ $item->id }}" class="mb-2 form-select form-select-sm text-primary border-primary-subtle" style="font-size: 0.75rem;">
                                                            <option value="">-- Bebas / Tanpa Rekomendasi --</option>
                                                            @foreach($item->vendorQuotes as $quote)
                                                                <option value="{{ $quote->vendor_id }}">Pilih: {{ optional($quote->vendor)->name }} (Rp {{ number_format($quote->quoted_price ?? $quote->price ?? 0, 0, ',', '.') }})</option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <input type="hidden" name="items[{{ $item->id }}][vendor_id]" value="">
                                                    @endif

                                                    <input type="text" name="items[{{ $item->id }}][reject_reason]" id="reason_{{ $item->id }}" class="form-control form-control-sm bg-white text-danger d-none mt-2" placeholder="Alasan tolak wajib diisi..." disabled>
                                                </td>
                                            @else
                                                <td class="py-3 text-center">
                                                    <div class="fw-bolder fs-5 text-primary">{{ (float)$item->qty }}</div>
                                                    
                                                    <div class="mt-2">
                                                        <span class="px-3 py-1 badge bg-secondary-subtle text-secondary border border-secondary-subtle fw-bold shadow-sm" style="white-space: normal;">{{ $tampilanSatuanLengkap }}</span>
                                                    </div>
                                                    
                                                    @if($convQty > 1)
                                                        <div class="mt-2 text-danger fw-bold" style="font-size: 0.65rem;"><i class="bi bi-info-circle"></i> Setara {{ $totalBase }} {{ $baseUomName }}</div>
                                                    @endif
                                                </td>

                                                <td class="py-3 text-center align-middle pe-4">
                                                    @php
                                                        $statusColor = 'secondary';
                                                        $statusText = strtoupper($item->status ?? 'PENDING');
                                                        if($statusText === 'APPROVED') $statusColor = 'success';
                                                        elseif($statusText === 'REJECTED') $statusColor = 'danger';
                                                    @endphp
                                                    <span class="badge bg-{{ $statusColor }} rounded-pill px-3 py-2 shadow-sm">{{ $statusText }}</span>

                                                    @if($statusText === 'REJECTED' && $item->rejection_reason)
                                                        <div class="mt-2 text-danger small fw-bold fst-italic" style="font-size: 0.65rem;">
                                                            <i class="bi bi-info-circle"></i> {{ $item->rejection_reason }}
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
                            <button type="button" class="px-4 py-2 shadow-sm btn btn-outline-danger fw-bold rounded-pill" onclick="submitGlobalReject()">
                                <i class="bi bi-x-circle-fill me-2"></i> Tolak Seluruh Dokumen
                            </button>
                            <button type="button" class="px-5 py-2 shadow-sm btn btn-success fw-bold rounded-pill" onclick="submitApproval()">
                                <i class="bi bi-check-circle-fill me-2"></i> Setujui & Teruskan
                            </button>
                        </div>
                        @endif

                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-12">
            <div class="overflow-hidden border-0 shadow-sm card rounded-4 border-start border-4 border-info">
                <div class="py-3 card-header bg-light border-bottom">
                    <h6 class="mb-0 fw-bold text-dark text-uppercase" style="font-size: 0.85rem;"><i class="bi bi-clock-history me-2 text-info"></i>Riwayat Perjalanan Dokumen</h6>
                </div>
                <div class="p-4 bg-white card-body">
                    @if($pr->histories && $pr->histories->count() > 0)
                        <ul class="timeline">
                            @foreach($pr->histories->sortByDesc('created_at') as $history)
                                <li class="timeline-item">
                                    <div class="timeline-date">{{ \Carbon\Carbon::parse($history->created_at)->translatedFormat('d M Y - H:i:s') }} WIB</div>
                                    <div class="timeline-content shadow-sm">
                                        <div class="mb-1 d-flex justify-content-between align-items-center">
                                            <strong>{{ optional($history->user)->name ?? 'System' }}</strong>
                                            @php
                                                $actionColor = 'secondary';
                                                if(str_contains(strtolower($history->action), 'approve') || str_contains(strtolower($history->action), 'setuju')) $actionColor = 'success';
                                                if(str_contains(strtolower($history->action), 'reject') || str_contains(strtolower($history->action), 'tolak')) $actionColor = 'danger';
                                                if(str_contains(strtolower($history->action), 'create')) $actionColor = 'primary';
                                            @endphp
                                            <span class="badge bg-{{ $actionColor }}">{{ $history->action }}</span>
                                        </div>
                                        <div class="mb-0 small text-muted">{!! nl2br(e($history->note)) !!}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="py-4 text-center text-muted fst-italic">Belum ada riwayat terekam untuk dokumen ini.</div>
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>

{{-- 🔥 MODAL BATALKAN PR 🔥 --}}
    <div class="modal fade" id="cancelPrModal" tabindex="-1" aria-labelledby="cancelPrModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="border-0 shadow modal-content rounded-4">
                <form action="{{ route('pr.cancel', $pr->pr_number) }}" method="POST">
                    @csrf
                    <div class="text-white modal-header bg-danger rounded-top-4">
                        <h5 class="modal-title fw-bold" id="cancelPrModalLabel">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>Batalkan Dokumen PR
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="p-4 modal-body">
                        <p class="text-muted small">Anda yakin ingin membatalkan dokumen <strong>{{ $pr->pr_number }}</strong>? Tindakan ini akan menghentikan seluruh proses persetujuan dan tidak dapat dikembalikan.</p>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-dark">Alasan Pembatalan <span class="text-danger">*</span></label>
                            <textarea name="cancel_reason" class="form-control bg-light border-secondary-subtle" rows="3" placeholder="Tuliskan alasan mengapa dokumen ini dibatalkan..." required></textarea>
                        </div>
                    </div>
                    <div class="bg-light modal-footer rounded-bottom-4">
                        <button type="button" class="px-4 btn btn-secondary fw-bold rounded-pill" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" class="px-4 btn btn-danger fw-bold rounded-pill">
                            <i class="bi bi-check-circle-fill me-2"></i>Ya, Batalkan PR
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
            select.classList.add('bg-danger', 'text-white');
            select.classList.remove('text-success');
            
            if(vendorSelect) {
                vendorSelect.disabled = true;
                vendorSelect.value = ''; 
            }
        } else {
            reasonInput.classList.add('d-none');
            reasonInput.disabled = true;
            reasonInput.required = false;
            reasonInput.value = '';
            select.classList.remove('bg-danger', 'text-white');
            select.classList.add('text-success');
            
            if(vendorSelect) vendorSelect.disabled = false;
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
            cancelButtonText: 'Batal'
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
            text: "Keputusan Setuju/Tolak untuk setiap item akan disimpan.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Lanjutkan!',
            cancelButtonText: 'Batal'
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