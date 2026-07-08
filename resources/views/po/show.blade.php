@extends('layouts.app')

@push('css')
<style>
    /* Efek Hover Lampiran */
    .file-card-hover:hover {
        background-color: #fff !important;
        border-color: #0d6efd !important;
        box-shadow: 0 4px 10px rgba(13, 110, 253, 0.1);
        transform: translateY(-2px);
    }
    .border-dashed {
        border-style: dashed !important;
        border-width: 2px !important;
    }

    /* Menyembunyikan elemen UI saat dicetak (Ctrl+P) */
    @media print {
        body { background-color: white !important; }
        .navbar, .btn, .nav, footer { display: none !important; }
        #printable-area { box-shadow: none !important; border: none !important; }
        .container { max-width: 100% !important; padding: 0 !important; }
        .d-print-none { display: none !important; }
    }

    /* Tambahan CSS untuk Timeline */
    .timeline-item:last-child {
        border-start-color: transparent !important;
        padding-bottom: 0 !important;
    }
    .d-print-none {
        display: block;
    }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    {{-- HEADER HALAMAN & TOMBOL AKSI --}}
    <div class="flex-wrap gap-3 mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="bi bi-file-earmark-text-fill me-2 text-primary"></i> Detail Purchase Order
            </h4>
            <div class="mt-1 text-muted small">
                <a href="{{ route('po.index') }}" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i> Kembali ke Daftar PO</a>
            </div>
        </div>

        {{-- LOGIKA TOMBOL BERDASARKAN STATUS & APPROVAL --}}
        @php
            $statusSlug = strtolower(optional($po->status)->slug ?? 'draft');
        @endphp

        <div class="flex-wrap gap-2 d-flex">
            {{-- 🔥 1. TOMBOL CETAK PO 🔥 --}}
            @if(in_array($statusSlug, ['approved', 'issued', 'partial_receipt', 'fully_received', 'completed']))
                <a href="{{ route('po.print', $po->po_number) }}" target="_blank" class="px-4 shadow-sm btn btn-dark rounded-pill fw-bold">
                    <i class="bi bi-printer-fill me-1"></i> Cetak PO
                </a>
            @endif

            {{-- 2. TOMBOL EDIT PO (Terkunci Otomatis Jika Sudah Ada Approval) --}}
            @if(in_array($statusSlug, ['draft', '', 'pending_approval', 'rejected']))
                @if(!$hasBeenPartiallyApproved)
                    <a href="{{ route('po.edit', $po->po_number) }}" class="px-4 shadow-sm btn btn-warning text-dark rounded-pill fw-bold">
                        <i class="bi bi-pencil-fill me-1"></i> Edit PO
                    </a>
                @else
                    <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="Tidak dapat diedit karena sudah ada persetujuan yang masuk.">
                        <button class="px-4 shadow-sm btn btn-warning text-dark rounded-pill fw-bold" type="button" disabled style="opacity: 0.5;">
                            <i class="bi bi-lock-fill me-1"></i> Terkunci
                        </button>
                    </span>
                @endif
            @endif

            {{-- 3. TOMBOL AJUKAN APPROVAL --}}
            @if(in_array($statusSlug, ['draft', '', 'rejected']))
                <form action="{{ route('po.submit_approval', $po->po_number) }}" method="POST" class="d-inline" id="formSubmitApproval">
                    @csrf
                    <button type="button" onclick="confirmSubmit()" class="px-4 shadow-sm btn btn-primary rounded-pill fw-bold">
                        <i class="bi bi-send-fill me-1"></i> Ajukan Approval
                    </button>
                </form>
            @endif

            {{-- 4. LOGIKA SMART APPROVAL --}}
            @if($statusSlug == 'pending_approval')
                @php
                    $currentApproval = \App\Models\DocumentApproval::with('role')
                        ->where('document_id', $po->id)
                        ->where('document_type', get_class($po))
                        ->where('status', 'PENDING')
                        ->orderBy('step_order', 'asc')
                        ->first();

                    $canApprove = $currentApproval && (auth()->user()->hasRole($currentApproval->role->name) || auth()->user()->hasRole(['Super Admin', 'super-admin', 'Super Administrator']));
                @endphp

                @if($canApprove)
                    <form action="{{ route('po.decide', $po->po_number) }}" method="POST" class="d-inline" id="formApprove">
                        @csrf
                        <input type="hidden" name="action" value="APPROVE">
                        <button type="button" onclick="confirmApprove()" class="px-4 shadow-sm btn btn-success rounded-pill fw-bold">
                            <i class="bi bi-check-circle-fill me-1"></i> Setujui PO
                        </button>
                    </form>

                    <form action="{{ route('po.decide', $po->po_number) }}" method="POST" class="d-inline" id="formReject">
                        @csrf
                        <input type="hidden" name="action" value="REJECT">
                        <input type="hidden" name="note" id="rejectNoteInput">
                        <button type="button" onclick="confirmRejectWithNote()" class="px-4 shadow-sm btn btn-danger rounded-pill fw-bold">
                            <i class="bi bi-x-circle-fill me-1"></i> Tolak PO
                        </button>
                    </form>
                @else
                    <div class="px-4 py-2 border shadow-sm rounded-pill bg-light text-muted fw-bold d-inline-block">
                        <i class="bi bi-hourglass-split me-1"></i> Menunggu Persetujuan: {{ $currentApproval ? $currentApproval->role->name : 'Atasan' }}
                    </div>
                @endif
            @endif

            {{-- 5. TOMBOL TERIMA BARANG (GOODS RECEIPT) --}}
            @if(in_array($statusSlug, ['issued', 'approved', 'partial_receipt']))
                @can('create_gr')
                <a href="{{ route('gr.create', $po->id) }}" class="px-4 shadow-sm btn btn-success rounded-pill fw-bold">
                    <i class="bi bi-box-seam me-1"></i> Terima Barang
                </a>
                @endcan
            @endif

            {{-- 6. TOMBOL BATALKAN PO --}}
            @if(in_array($statusSlug, ['draft', 'pending_approval', 'issued', 'approved', '']))
                <form action="{{ route('po.cancel', $po->po_number) }}" method="POST" class="d-inline" id="formCancel">
                    @csrf
                    <input type="hidden" name="cancel_reason" id="cancelReasonInput">
                    <button type="button" onclick="confirmCancelWithReason()" class="px-4 shadow-sm btn btn-outline-danger rounded-pill fw-bold">
                        <i class="bi bi-slash-circle me-1"></i> Batalkan PO
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- KERTAS DOKUMEN PO --}}
    {{-- ========================================================================= --}}
    <div class="overflow-hidden border-0 shadow card rounded-4" id="printable-area">
        <div class="p-2 bg-primary"></div>

        <div class="p-5 card-body">
            {{-- KOP SURAT PO --}}
            <div class="pb-4 mb-5 row border-bottom">
                <div class="col-sm-6">
                    <h2 class="mb-1 fw-bold text-primary">PURCHASE ORDER</h2>
                    @if($po->status)
                        <span class="badge bg-{{ $po->status->color }}-subtle text-{{ $po->status->color }} border border-{{ $po->status->color }}-subtle rounded-pill px-3 py-2 mb-3 shadow-sm">
                            <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> Status: {{ $po->status->name }}
                        </span>
                    @else
                        <span class="px-3 py-2 mb-3 border shadow-sm badge bg-secondary-subtle text-secondary border-secondary-subtle rounded-pill">
                            Status: DRAFT
                        </span>
                    @endif
                    <div class="mt-2 text-muted">
                        <strong>No. PO:</strong> <span class="text-dark fw-bold">{{ $po->po_number }}</span><br>
                        <strong>Tgl. PO:</strong> <span class="text-dark">{{ \Carbon\Carbon::parse($po->po_date)->format('d F Y') }}</span><br>
                        <strong>Ref PR:</strong> <span class="text-dark">{{ $po->purchaseRequest->pr_number ?? '-' }}</span>
                    </div>
                </div>
                <div class="col-sm-6 text-end">
                    <h3 class="mb-1 fw-bold text-dark">{{ $company->name ?? 'N/A' }}</h3>
                    <div class="text-muted small">
                        {!! nl2br(e($company->address ?? 'Alamat tidak tersedia')) !!}<br>
                        Email: {{ $company->email ?? '-' }} | Telp: {{ $company->phone ?? '-' }}
                    </div>
                </div>
            </div>

            {{-- INFORMASI VENDOR & PENGIRIMAN --}}
            <div class="mb-5 row">
                <div class="col-sm-5">
                    <h6 class="mb-3 fw-bold text-muted text-uppercase"><i class="bi bi-building me-1"></i> Vendor (Kepada):</h6>
                    <div class="p-3 border bg-light rounded-3">
                        <h6 class="mb-1 fw-bold">{{ $po->vendor->name ?? 'N/A' }}</h6>
                        <div class="small text-muted">
                            {!! nl2br(e($po->vendor->address ?? '-')) !!}<br>
                            PIC: {{ $po->vendor->pic_name ?? '-' }} ({{ $po->vendor->pic_phone ?? '-' }})<br>
                            Email: {{ $po->vendor->email ?? '-' }}
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <h6 class="mb-3 fw-bold text-muted text-uppercase"><i class="bi bi-truck me-1"></i> Kirim Ke (Ship To):</h6>
                    <div class="p-3 border small text-muted rounded-3 h-100">
                        {!! nl2br(e($po->shipping_address ?? 'Sesuai alamat perusahaan')) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <h6 class="mb-3 fw-bold text-muted text-uppercase"><i class="bi bi-info-circle me-1"></i> Detail Pembayaran:</h6>
                    <div class="p-3 border small text-muted rounded-3 h-100 bg-light">
                        <strong>Mata Uang:</strong> {{ $po->currency ?? 'IDR' }}<br>
                        <strong>Termin:</strong> {{ $po->payment_terms ?? '-' }}<br>
                        <strong>Estimasi Tiba:</strong> {{ $po->delivery_date ? \Carbon\Carbon::parse($po->delivery_date)->format('d M Y') : 'TBD' }}
                    </div>
                </div>
            </div>

            {{-- TABEL ITEM --}}
            <div class="mb-4 table-responsive">
                <table class="table mb-0 align-middle border table-hover">
                    <thead class="bg-primary bg-opacity-10 text-primary">
                        <tr>
                            <th width="5%" class="py-3 text-center">NO</th>
                            <th width="15%" class="py-3">KODE ITEM</th>
                            <th width="35%" class="py-3">NAMA BARANG & SPESIFIKASI</th>
                            <th width="10%" class="py-3 text-center">QTY</th>
                            <th width="15%" class="py-3 text-end">HARGA SATUAN</th>
                            <th width="10%" class="py-3 text-center">DISKON/PAJAK</th>
                            <th width="10%" class="py-3 text-end pe-3">SUBTOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($po->items as $index => $item)
                            <tr>
                                <td class="text-center fw-bold text-muted">{{ $index + 1 }}</td>

                                {{-- KOLOM 1: KODE BARANG --}}
                                <td>
                                    <span class="p-2 border badge bg-secondary-subtle text-secondary border-secondary-subtle">
                                        {{ optional($item->item)->code ?? 'SKU-UNKNOWN' }}
                                    </span>
                                </td>

                                {{-- KOLOM 2: NAMA BARANG + SPESIFIKASI + LAMPIRAN --}}
                                <td>
                                    <div class="mb-2 fw-bolder text-dark" style="font-size: 0.95rem;">
                                        {{ optional($item->item)->name ?? 'Item Terhapus / Tidak Ditemukan' }}
                                    </div>

                                    <div class="p-2 border rounded text-muted bg-light border-light" style="font-size: 0.85rem;">
                                        {!! $item->description ?? '-' !!}
                                    </div>

                                    @if($item->attachments && $item->attachments->count() > 0)
                                        <div class="flex-wrap gap-2 pt-2 mt-2 border-top border-light d-flex">
                                            @foreach($item->attachments as $idx => $vFile)
                                                <a href="{{ asset('storage/' . $vFile->file_path) }}" target="_blank" class="px-2 py-1 border badge bg-info-subtle text-info-emphasis text-decoration-none border-info-subtle" title="{{ $vFile->file_name }}">
                                                    <i class="bi bi-paperclip"></i> Lampiran {{ $idx + 1 }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>

                                {{-- KOLOM 3: QTY & UOM --}}
                                <td class="text-center">
                                    <div class="fw-bolder text-dark fs-6">{{ (float) $item->qty_ordered }}</div>

                                    @php
                                        $uomDisplay = $item->uom;
                                        if (empty($uomDisplay) || is_numeric($uomDisplay)) {
                                            if (!empty($item->uom_id)) {
                                                $uomMaster = \App\Models\ItemUom::find($item->uom_id);
                                                if ($uomMaster) {
                                                    $baseUnit = optional($item->item)->unit ?? 'Pcs';
                                                    $uomDisplay = $uomMaster->uom_name . ' (Isi: ' . (float)$uomMaster->conversion_qty . ' ' . $baseUnit . ')';
                                                }
                                            }
                                        }
                                        if (empty($uomDisplay) || is_numeric($uomDisplay)) {
                                            $uomDisplay = optional($item->item)->unit ?? 'PCS';
                                        }
                                    @endphp

                                    <span class="mt-1 badge bg-primary-subtle text-primary" style="font-size: 0.75rem;">
                                        {{ $uomDisplay }}
                                    </span>
                                </td>

                                {{-- KOLOM 4: HARGA --}}
                                <td class="text-end fw-bold text-secondary">
                                    {{ number_format($item->unit_price, 2, '.', ',') }}
                                </td>

                                {{-- KOLOM 5: DISKON & PAJAK --}}
                                <td class="text-center" style="font-size: 0.75rem;">
                                    <div class="mb-1 text-danger fw-bold">Disc: {{ number_format($item->discount_amount, 2, '.', ',') }}</div>
                                    <div class="text-info fw-bold">Tax: {{ number_format($item->tax_amount, 2, '.', ',') }}</div>
                                </td>

                                {{-- KOLOM 6: SUBTOTAL --}}
                                <td class="text-end fw-bolder text-dark pe-3" style="font-size: 1rem;">
                                    {{ number_format($item->subtotal + $item->tax_amount, 2, '.', ',') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-4 text-center text-muted fst-italic">Tidak ada item barang dalam PO ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- CATATAN & RINGKASAN FINANSIAL --}}
            <div class="row">
                <div class="col-sm-7">
                    <h6 class="mb-2 fw-bold text-muted text-uppercase">Catatan PO:</h6>
                    <div class="p-3 border bg-light rounded-3 small text-muted min-vh-25">
                        {!! nl2br(e($po->notes ?? 'Tidak ada catatan khusus.')) !!}
                    </div>
                </div>

                <div class="col-sm-5">
                    <div class="table-responsive">
                        <table class="table mb-0 table-sm table-borderless">
                            <tbody>
                                <tr>
                                    <td class="text-muted">Subtotal Gross</td>
                                    <td class="text-end fw-bold">{{ $po->currency }} {{ number_format($po->subtotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-danger">Diskon Komersial</td>
                                    <td class="text-end fw-bold text-danger">- {{ $po->currency }} {{ number_format($po->discount_total, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-primary">Total Pajak (VAT/Ppn)</td>
                                    <td class="text-end fw-bold text-primary">+ {{ $po->currency }} {{ number_format($po->tax_total, 2) }}</td>
                                </tr>

                                @if($charges->count() > 0)
                                    @foreach($charges as $charge)
                                    <tr>
                                        <td class="text-secondary small ps-3">↳ Biaya: {{ $charge->name }}</td>
                                        <td class="text-end small text-secondary">+ {{ $po->currency }} {{ number_format($charge->amount, 2) }}</td>
                                    </tr>
                                    @endforeach
                                @endif

                                @if(isset($extraDiscounts) && $extraDiscounts->count() > 0)
                                    @foreach($extraDiscounts as $disc)
                                    <tr>
                                        <td class="text-danger small ps-3">↳ Potongan: {{ $disc->name }}</td>
                                        <td class="text-end small text-danger">- {{ $po->currency }} {{ number_format($disc->amount, 2) }}</td>
                                    </tr>
                                    @endforeach
                                @endif

                                <tr class="border-2 border-top">
                                    <td class="pt-2 fs-5 fw-bold text-dark">GRAND TOTAL</td>
                                    <td class="pt-2 text-end fs-5 fw-bold text-success">{{ $po->currency }} {{ number_format($po->grand_total, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- BLOK TANDA TANGAN DINAMIS PO --}}
            @php
                $creator = \App\Models\User::find($po->created_by);
                $creatorRole = $creator ? $creator->roles->first() : null;

                $approvals = \App\Models\DocumentApproval::with(['role', 'approver'])
                                ->where('document_id', $po->id)
                                ->where('document_type', get_class($po))
                                ->orderBy('step_order', 'asc')
                                ->get();
            @endphp

            <div class="pt-4 mt-5 text-center signature-container d-flex justify-content-between" style="font-size: 0.85rem; page-break-inside: avoid;">

                <div class="px-2 signature-box">
                    <p class="mb-1 text-muted">Dibuat Oleh,</p>
                    <div class="sign-space" style="height: 80px; display: flex; align-items: center; justify-content: center;">
                        <span class="stamp-approved" style="color: #198754; border: 2px solid #198754; padding: 5px 10px; font-weight: bold; transform: rotate(-5deg); opacity: 0.8; letter-spacing: 2px;">ISSUED</span>
                    </div>
                    <p class="mb-0"><u><strong>{{ $creator->name ?? '....................................' }}</strong></u></p>
                    <p class="mb-0 text-muted" style="font-size: 0.75rem;">{{ $creatorRole ? $creatorRole->name : 'Procurement Dept' }}</p>
                    <p class="mb-0 text-muted" style="font-size: 0.7rem;">Tgl: {{ \Carbon\Carbon::parse($po->created_at)->format('d/m/Y') }}</p>
                </div>

                @foreach($approvals as $approval)
                    <div class="px-2 signature-box">
                        <p class="mb-1 text-muted">Disetujui Oleh,</p>

                        <div class="sign-space" style="height: 80px; display: flex; align-items: center; justify-content: center;">
                            @if(in_array(strtolower(optional($po->status)->slug), ['canceled', 'cancelled']))
                                <span class="stamp-rejected" style="color: #dc3545; border: 2px solid #dc3545; padding: 5px 10px; font-weight: bold; transform: rotate(-10deg); opacity: 0.8; letter-spacing: 2px;">VOID / CANCELLED</span>
                            @elseif($approval->status === 'APPROVED')
                                <span class="stamp-approved" style="color: #0d6efd; border: 2px solid #0d6efd; padding: 5px 10px; font-weight: bold; transform: rotate(-5deg); opacity: 0.8; letter-spacing: 2px;">APPROVED</span>
                            @elseif($approval->status === 'REJECTED')
                                <span class="stamp-rejected" style="color: #dc3545; border: 2px solid #dc3545; padding: 5px 10px; font-weight: bold; transform: rotate(5deg); opacity: 0.8; letter-spacing: 2px;">REJECTED</span>
                            @endif
                        </div>

                        @if($approval->status === 'APPROVED' || $approval->status === 'REJECTED')
                            @php
                                $approverName = optional($approval->approver)->name ?? (\App\Models\User::find($approval->approved_by)->name ?? '....................................');
                            @endphp
                            <p class="mb-0"><u><strong>{{ $approverName }}</strong></u></p>
                            <p class="mb-0 text-muted" style="font-size: 0.75rem;">{{ optional($approval->role)->name ?? 'Atasan' }}</p>
                            <p class="mb-0 text-muted" style="font-size: 0.7rem;">Tgl: {{ \Carbon\Carbon::parse($approval->approved_at)->format('d/m/Y') }}</p>
                        @else
                            <p class="mb-0"><u><strong>....................................</strong></u></p>
                            <p class="mb-0 text-muted" style="font-size: 0.75rem;">{{ optional($approval->role)->name ?? 'Atasan' }}</p>
                            <p class="mb-0 text-muted" style="font-size: 0.7rem;">Tgl: ........................</p>
                        @endif
                    </div>
                @endforeach

                <div class="px-2 signature-box">
                    <p class="mb-1 text-muted">Diterima Oleh Vendor,</p>
                    <div class="sign-space" style="height: 80px;"></div>
                    <p class="mb-0"><u><strong>....................................</strong></u></p>
                    <p class="mb-0 text-muted" style="font-size: 0.75rem;">Cap & Tanda Tangan</p>
                    <p class="mb-0 text-muted" style="font-size: 0.7rem;">Tgl: ........................</p>
                </div>
            </div>

            <div class="pt-3 mt-4 text-center print-footer border-top text-muted" style="font-size: 0.7rem;">
                * Dokumen elektronik ini telah diaudit dan diterbitkan oleh sistem ProcureApp pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }} WIB
            </div>

        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- 🔥 RIWAYAT PENERIMAAN BARANG GUDANG (GOODS RECEIPT) 🔥 --}}
    {{-- ========================================================================= --}}
    @php
        // Tarik data Goods Receipt yang menginduk ke PO ini
        $receipts = [];
        if(class_exists('\App\Models\GoodsReceipt')) {
            $receipts = \App\Models\GoodsReceipt::with(['user', 'status'])
                        ->where('purchase_order_id', $po->id)
                        ->orderBy('created_at', 'desc')
                        ->get();
        }
    @endphp

    <div class="mt-4 border-0 shadow-sm card rounded-4 d-print-none">
        <div class="py-3 bg-white card-header border-bottom">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-boxes me-2 text-success"></i>Riwayat Penerimaan Barang (GR) Gudang</h6>
        </div>
        <div class="p-0 card-body table-responsive">
            @if(count($receipts) > 0)
                <table class="table mb-0 align-middle table-hover">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">No. Dokumen GR</th>
                            <th>Tanggal Terima</th>
                            <th>Petugas Gudang</th>
                            <th>Catatan</th>
                            <th class="text-center pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($receipts as $gr)
                            <tr>
                                <td class="ps-4 fw-bold text-primary">
                                    <a href="{{ route('gr.show', $gr->gr_number) }}" class="text-decoration-none">
                                        <i class="bi bi-box-arrow-in-down-left me-1"></i> {{ $gr->gr_number }}
                                    </a>
                                </td>
                                <td class="text-muted fw-medium">{{ \Carbon\Carbon::parse($gr->receipt_date)->format('d M Y') }}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-person-circle text-muted me-2 fs-5"></i>
                                        <span class="fw-semibold">{{ optional($gr->user)->name ?? 'System' }}</span>
                                    </div>
                                </td>
                                <td class="text-muted small">{{ $gr->notes ?? '-' }}</td>
                                <td class="text-center pe-4">
                                    @if($gr->status)
                                        <span class="badge bg-{{ $gr->status->color }}-subtle text-{{ $gr->status->color }} rounded-pill border border-{{ $gr->status->color }}-subtle px-3">
                                            {{ mb_strtoupper($gr->status->name) }}
                                        </span>
                                    @else
                                        <span class="px-3 badge bg-success rounded-pill">DITERIMA</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="py-5 text-center text-muted small">
                    <i class="mb-2 opacity-50 bi bi-box-seam fs-3 d-block text-secondary"></i>
                    Pihak gudang belum menerima atau mencatat kedatangan barang untuk PO ini.
                </div>
            @endif
        </div>
    </div>


    {{-- ========================================================================= --}}
    {{-- SECTION LAMPIRAN DOKUMEN --}}
    {{-- ========================================================================= --}}
    <div class="mt-4 border-0 shadow-sm card rounded-4 d-print-none">
        <div class="py-3 bg-white card-header border-bottom d-flex align-items-center">
            <div class="p-2 me-3 d-flex align-items-center justify-content-center rounded-circle bg-info bg-opacity-10 text-info" style="width: 35px; height: 35px;">
                <i class="bi bi-paperclip fs-5"></i>
            </div>
            <h6 class="mb-0 fw-bold text-dark">Lampiran Penawaran & Dokumen Pendukung (Header PO)</h6>
        </div>
        <div class="p-4 card-body">
            @if(isset($item->raw_attachments) && count($item->raw_attachments) > 0)
                <div class="flex-wrap gap-2 pt-2 mt-2 border-top border-light d-flex">
                    @foreach($item->raw_attachments as $idx => $vFile)
                        <a href="{{ asset('storage/' . $vFile->file_path) }}" target="_blank" class="px-2 py-1 border badge bg-info-subtle text-info-emphasis text-decoration-none border-info-subtle" title="{{ $vFile->file_name }}">
                            <i class="bi bi-paperclip"></i> Lampiran {{ $idx + 1 }}
                        </a>
                    @endforeach
                </div>
            @else
                <span class="text-muted small fst-italic">Tidak ada dokumen lampiran pendukung.</span>
            @endif
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- RIWAYAT AKTIVITAS PO (HISTORY TIMELINE) --}}
    {{-- ========================================================================= --}}
    <div class="mt-4 mb-5 border-0 shadow-sm card rounded-4 d-print-none">
        <div class="py-3 bg-white card-header border-bottom">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history me-2 text-primary"></i>Riwayat Aktivitas PO</h6>
        </div>
        <div class="card-body">
            <div class="mt-2 timeline ps-3">
                @if(isset($po->histories) && $po->histories->count() > 0)
                    @foreach($po->histories as $log)
                        <div class="pb-4 border-2 border-opacity-25 position-relative timeline-item border-start border-primary ps-4">
                            <span class="top-0 border border-2 border-white position-absolute start-0 translate-middle bg-primary rounded-circle" style="width: 14px; height: 14px;"></span>

                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark fs-6">{{ $log->action }}</span>
                                <span class="text-muted small"><i class="bi bi-calendar-event me-1"></i> {{ \Carbon\Carbon::parse($log->created_at)->format('d M Y - H:i') }}</span>
                            </div>

                            <div class="mt-1 small text-muted">
                                <i class="bi bi-person-circle me-1"></i> Oleh: <strong>{{ optional($log->user)->name ?? 'Sistem' }}</strong>
                            </div>

                            @if($log->note)
                            <div class="p-3 mt-2 border text-dark bg-light rounded-3 border-secondary-subtle" style="font-size: 0.85rem;">
                                {!! nl2br(preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', e($log->note))) !!}
                            </div>
                            @endif
                        </div>
                    @endforeach
                @else
                    <div class="py-4 text-center text-muted small">
                        <i class="mb-2 bi bi-info-circle fs-4 d-block text-secondary"></i> Belum ada riwayat tercatat untuk dokumen ini.
                    </div>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmSubmit() {
        Swal.fire({
            title: 'Ajukan Approval?',
            text: "Pastikan data PO sudah benar sebelum diajukan ke Atasan.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Ajukan!',
            cancelButtonText: 'Batal',
            borderRadius: '15px'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('formSubmitApproval').submit();
            }
        });
    }

    function confirmApprove() {
        Swal.fire({
            title: 'Setujui PO?',
            text: "Anda yakin ingin menyetujui Purchase Order ini?",
            icon: 'success',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Setujui!',
            cancelButtonText: 'Batal',
            borderRadius: '15px'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('formApprove').submit();
            }
        });
    }

    function confirmRejectWithNote() {
        Swal.fire({
            title: 'Tolak PO?',
            text: "Berikan alasan penolakan untuk dokumen ini:",
            input: 'text',
            inputPlaceholder: 'Ketik alasan di sini...',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Tolak!',
            cancelButtonText: 'Batal',
            preConfirm: (note) => {
                if (!note) {
                    Swal.showValidationMessage('Alasan penolakan wajib diisi!')
                }
                return note;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('rejectNoteInput').value = result.value;
                document.getElementById('formReject').submit();
            }
        });
    }

    function confirmCancelWithReason() {
        Swal.fire({
            title: 'Batalkan Purchase Order?',
            text: "Berikan alasan mengapa PO ini dibatalkan (wajib diisi):",
            input: 'text',
            inputPlaceholder: 'Cth: Salah input vendor, Harga berubah...',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Batalkan!',
            cancelButtonText: 'Kembali',
            preConfirm: (reason) => {
                if (!reason || reason.trim() === '') {
                    Swal.showValidationMessage('Alasan pembatalan tidak boleh kosong!')
                }
                return reason;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('cancelReasonInput').value = result.value;
                document.getElementById('formCancel').submit();
            }
        });
    }
</script>
@endpush
