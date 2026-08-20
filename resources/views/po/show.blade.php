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
<div class="pb-5 container-fluid text-dark">

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
            {{-- 🔥 MEGA DROPDOWN: 18 PILIHAN MENU CETAK (DIGITAL, MANUAL, HYBRID) 🔥 --}}
            @if(!in_array($statusSlug, ['rejected', 'canceled', 'cancelled']))
                <div class="shadow-sm btn-group">
                    <button class="btn btn-dark fw-bold rounded-start-pill" type="button" style="pointer-events: none;">
                        <i class="bi bi-printer me-1"></i> Opsi Cetak Dokumen
                    </button>
                    <button type="button" class="btn btn-dark dropdown-toggle dropdown-toggle-split rounded-end-pill px-3" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="visually-hidden">Toggle Dropdown</span>
                    </button>
                    
                    <ul class="mt-2 border-0 shadow-lg dropdown-menu dropdown-menu-end" style="width: 450px; max-height: 80vh; overflow-y: auto;">
                        
                        {{-- ================= OPSI 1: DIGITAL (FULL STEMPEL & TTD) ================= --}}
                        <li><h6 class="dropdown-header text-primary fw-bold fs-6 bg-primary-subtle py-2"><i class="bi bi-laptop me-1"></i> VERSI DIGITAL (TTD/Stempel Otomatis)</h6></li>
                        <li><a class="py-2 dropdown-item fw-medium" href="{{ route('po.print', ['slug' => $po->po_number, 'type' => 'digital']) }}" target="_blank"><i class="bi bi-file-earmark text-primary me-2"></i> Cetak PO Standar</a></li>
                        <li><a class="py-2 dropdown-item fw-medium" href="{{ route('po.print_complete', ['slug' => $po->po_number, 'type' => 'digital']) }}" target="_blank"><i class="bi bi-file-earmark-plus text-primary me-2"></i> Cetak PO + Lampiran</a></li>
                        <li><a class="py-2 dropdown-item fw-medium" href="{{ route('po.print_bpr', ['slug' => $po->po_number, 'type' => 'digital']) }}" target="_blank"><i class="bi bi-grid-1x2 text-success me-2"></i> Cetak BPR Standar (Global)</a></li>
                        <li><a class="py-2 dropdown-item fw-medium" href="{{ route('po.print_bpr_standar_attachments', ['slug' => $po->po_number, 'type' => 'digital']) }}" target="_blank"><i class="bi bi-grid-1x2-fill text-success me-2"></i> Cetak BPR Standar (Global) + Lampiran</a></li>
                        <li><a class="py-2 dropdown-item fw-medium" href="{{ route('po.print_bpr_detail', ['slug' => $po->po_number, 'type' => 'digital']) }}" target="_blank"><i class="bi bi-list-columns-reverse text-warning-emphasis me-2"></i> Cetak BPR Detail (Pajak/Diskon)</a></li>
                        <li><a class="py-2 dropdown-item fw-medium" href="{{ route('po.print_bpr_attachments', ['slug' => $po->po_number, 'type' => 'digital']) }}" target="_blank"><i class="bi bi-collection text-warning-emphasis me-2"></i> Cetak BPR Detail + Lampiran</a></li>
                        
                        {{-- ================= OPSI 2: MANUAL (KOSONG TOTAL) ================= --}}
                        <li><h6 class="dropdown-header text-danger fw-bold fs-6 bg-danger-subtle py-2 mt-2"><i class="bi bi-pen me-1"></i> VERSI MANUAL (TTD Basah Penuh)</h6></li>
                        <li><a class="py-2 dropdown-item fw-medium" href="{{ route('po.print', ['slug' => $po->po_number, 'type' => 'manual']) }}" target="_blank"><i class="bi bi-file-earmark text-danger me-2"></i> Cetak PO Standar</a></li>
                        <li><a class="py-2 dropdown-item fw-medium" href="{{ route('po.print_complete', ['slug' => $po->po_number, 'type' => 'manual']) }}" target="_blank"><i class="bi bi-file-earmark-plus text-danger me-2"></i> Cetak PO + Lampiran</a></li>
                        <li><a class="py-2 dropdown-item fw-medium" href="{{ route('po.print_bpr', ['slug' => $po->po_number, 'type' => 'manual']) }}" target="_blank"><i class="bi bi-grid-1x2 text-danger me-2"></i> Cetak BPR Standar (Global)</a></li>
                        <li><a class="py-2 dropdown-item fw-medium" href="{{ route('po.print_bpr_standar_attachments', ['slug' => $po->po_number, 'type' => 'manual']) }}" target="_blank"><i class="bi bi-grid-1x2-fill text-danger me-2"></i> Cetak BPR Standar (Global) + Lampiran</a></li>
                        <li><a class="py-2 dropdown-item fw-medium" href="{{ route('po.print_bpr_detail', ['slug' => $po->po_number, 'type' => 'manual']) }}" target="_blank"><i class="bi bi-list-columns-reverse text-danger me-2"></i> Cetak BPR Detail (Pajak/Diskon)</a></li>
                        <li><a class="py-2 dropdown-item fw-medium" href="{{ route('po.print_bpr_attachments', ['slug' => $po->po_number, 'type' => 'manual']) }}" target="_blank"><i class="bi bi-collection text-danger me-2"></i> Cetak BPR Detail + Lampiran</a></li>

                        {{-- ================= OPSI 3: HYBRID (CERDAS) ================= --}}
                        <li><h6 class="dropdown-header text-success fw-bold fs-6 bg-success-subtle py-2 mt-2"><i class="bi bi-magic me-1"></i> VERSI HYBRID (Gambar Jika Ada)</h6></li>
                        <li><a class="py-2 dropdown-item fw-medium" href="{{ route('po.print', ['slug' => $po->po_number, 'type' => 'hybrid']) }}" target="_blank"><i class="bi bi-file-earmark text-success me-2"></i> Cetak PO Standar</a></li>
                        <li><a class="py-2 dropdown-item fw-medium" href="{{ route('po.print_complete', ['slug' => $po->po_number, 'type' => 'hybrid']) }}" target="_blank"><i class="bi bi-file-earmark-plus text-success me-2"></i> Cetak PO + Lampiran</a></li>
                        <li><a class="py-2 dropdown-item fw-medium" href="{{ route('po.print_bpr', ['slug' => $po->po_number, 'type' => 'hybrid']) }}" target="_blank"><i class="bi bi-grid-1x2 text-success me-2"></i> Cetak BPR Standar (Global)</a></li>
                        <li><a class="py-2 dropdown-item fw-medium" href="{{ route('po.print_bpr_standar_attachments', ['slug' => $po->po_number, 'type' => 'hybrid']) }}" target="_blank"><i class="bi bi-grid-1x2-fill text-success me-2"></i> Cetak BPR Standar (Global) + Lampiran</a></li>
                        <li><a class="py-2 dropdown-item fw-medium" href="{{ route('po.print_bpr_detail', ['slug' => $po->po_number, 'type' => 'hybrid']) }}" target="_blank"><i class="bi bi-list-columns-reverse text-success me-2"></i> Cetak BPR Detail (Pajak/Diskon)</a></li>
                        <li><a class="py-2 dropdown-item fw-medium" href="{{ route('po.print_bpr_attachments', ['slug' => $po->po_number, 'type' => 'hybrid']) }}" target="_blank"><i class="bi bi-collection text-success me-2"></i> Cetak BPR Detail + Lampiran</a></li>
                    </ul>
                </div>
            @endif

            {{-- 2. TOMBOL EDIT PO (Hanya untuk Pembuat PO / Super Admin & Belum Ada Persetujuan Masuk) --}}
            @if(in_array($statusSlug, ['draft', '', 'pending_approval', 'rejected']))
                @if(auth()->id() == $po->created_by || auth()->user()->hasAnyRole(['Super Admin', 'Super Administrator', 'super-admin']))
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
            @endif

            {{-- 3. TOMBOL AJUKAN APPROVAL (SIMPLE) --}}
            @if(in_array($statusSlug, ['draft', '', 'rejected']))
                <form action="{{ route('po.submit_approval', $po->po_number) }}" method="POST" class="d-inline" id="formSubmitApproval">
                    @csrf
                    <button type="button" onclick="confirmSubmit()" class="px-4 shadow-sm btn btn-primary rounded-pill fw-bold">
                        <i class="bi bi-send-fill me-1"></i> Ajukan Approval
                    </button>
                </form>
            @endif

            {{-- 4. LOGIKA SMART APPROVAL BERGILIRAN --}}
            @php
                $canApprove = false;

                $currentPendingApproval = \App\Models\DocumentApproval::with('role')
                    ->where('document_id', $po->id)
                    ->where('document_type', get_class($po))
                    ->where('status', 'PENDING')
                    ->orderBy('step_order', 'asc')
                    ->first();

                if ($currentPendingApproval) {
                    $user = auth()->user();
                    $isSuperAdmin = $user->hasAnyRole(['Super Admin', 'Super Administrator', 'super-admin']);

                    $reqRoleName = optional($currentPendingApproval->role)->name;
                    $hasRequiredRole = $reqRoleName ? $user->roles()->where('name', $reqRoleName)->exists() : false;

                    $targetDeptId = $currentPendingApproval->target_department_id;
                    $hasRequiredDept = false;

                    if (empty($targetDeptId)) {
                        $pembuatPo = \App\Models\User::find($po->created_by);
                        $hasRequiredDept = ($pembuatPo && (int) $user->department_id === (int) $pembuatPo->department_id);
                    } elseif ($targetDeptId === 'all' || $targetDeptId == 0) {
                        $hasRequiredDept = true;
                    } else {
                        $hasRequiredDept = ((int) $user->department_id === (int) $targetDeptId);
                    }

                    if ($isSuperAdmin || ($hasRequiredRole && $hasRequiredDept)) {
                        $canApprove = true;
                    }
                }
            @endphp

            @if($canApprove)
                {{-- Form Approve --}}
                <form action="{{ route('po.decide', $po->po_number) }}" method="POST" class="d-inline" id="formApprove">
                    @csrf
                    <input type="hidden" name="action" value="APPROVE">
                    <button type="button" onclick="confirmApprove()" class="px-4 shadow-sm btn btn-success fw-bold rounded-pill">
                        <i class="bi bi-check-circle-fill me-1"></i> Setujui PO
                    </button>
                </form>

                {{-- Form Reject --}}
                <form action="{{ route('po.decide', $po->po_number) }}" method="POST" class="d-inline" id="formReject">
                    @csrf
                    <input type="hidden" name="action" value="REJECT">
                    <input type="hidden" name="note" id="rejectNoteInput">
                    <button type="button" onclick="confirmRejectWithNote()" class="px-4 shadow-sm btn btn-danger fw-bold rounded-pill">
                        <i class="bi bi-x-circle-fill me-1"></i> Tolak PO
                    </button>
                </form>
            @elseif($currentPendingApproval)
                {{-- Mode Menunggu Persetujuan --}}
                <div class="px-4 py-2 border shadow-sm rounded-pill bg-light text-muted fw-bold d-inline-block">
                    <i class="bi bi-hourglass-split me-1"></i> Menunggu Persetujuan: {{ $currentPendingApproval->role->name ?? 'Atasan' }}
                </div>
            @endif

            {{-- 5. TOMBOL TERIMA BARANG (GOODS RECEIPT) --}}
            @if(in_array($statusSlug, ['issued', 'approved', 'partial_receipt', 'partial_received']))
                @can('create_gr')
                <a href="{{ route('gr.create', $po->id) }}" class="px-4 shadow-sm btn btn-success rounded-pill fw-bold">
                    <i class="bi bi-box-seam me-1"></i> Terima Barang
                </a>
                @endcan
            @endif

            {{-- 6. TOMBOL BATALKAN PO (Hanya untuk Pembuat PO / Super Admin) --}}
            @if(in_array($statusSlug, ['draft', 'pending_approval', 'issued', 'approved', '']))
                @if(auth()->id() == $po->created_by || auth()->user()->hasAnyRole(['Super Admin', 'Super Administrator', 'super-admin']))
                    <form action="{{ route('po.cancel', $po->po_number) }}" method="POST" class="d-inline" id="formCancel">
                        @csrf
                        <input type="hidden" name="cancel_reason" id="cancelReasonInput">
                        <button type="button" onclick="confirmCancelWithReason()" class="px-4 shadow-sm btn btn-outline-danger rounded-pill fw-bold">
                            <i class="bi bi-slash-circle me-1"></i> Batalkan PO
                        </button>
                    </form>
                @endif
            @endif

            {{-- 7. TOMBOL FORCE CLOSE PO --}}
            @if(in_array($statusSlug, ['issued', 'approved', 'partial_receipt', 'partial_received']))
                <form action="{{ route('po.force_close', $po->po_number) }}" method="POST" class="d-inline" id="formForceClose">
                    @csrf
                    <input type="hidden" name="reason" id="forceCloseReasonInput">
                    <button type="button" onclick="confirmForceClose()" class="px-4 shadow-sm btn btn-warning text-dark rounded-pill fw-bold">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Tutup Paksa PO
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
                        <strong>Ref PR:</strong> <span class="text-dark">{{ optional($po->purchaseRequest)->pr_number ?? '-' }}</span>
                    </div>
                </div>

                {{-- INFO COMPANY --}}
                @php
                    $companyName = optional($po->company)->name ?? optional(optional($po->purchaseRequest)->company)->name ?? 'PT. Kantor Pusat Internal';
                    $companyAddr = optional($po->company)->address ?? optional(optional($po->purchaseRequest)->company)->address ?? 'Alamat belum diatur dalam sistem master data.';
                    $companyEmail = optional($po->company)->email ?? optional(optional($po->purchaseRequest)->company)->email ?? 'info@perusahaan.com';
                    $companyPhone = optional($po->company)->phone ?? optional(optional($po->purchaseRequest)->company)->phone ?? '-';
                @endphp
                <div class="col-sm-6 text-end">
                    <h3 class="mb-1 fw-bold text-dark">{{ $companyName }}</h3>
                    <div class="text-muted small">
                        {!! nl2br(e($companyAddr)) !!}<br>
                        Email: {{ $companyEmail }} | Telp: {{ $companyPhone }}
                    </div>
                </div>
            </div>

            {{-- INFORMASI VENDOR & PENGIRIMAN --}}
            <div class="mb-5 row">
                <div class="col-sm-5">
                    <h6 class="mb-3 fw-bold text-muted text-uppercase"><i class="bi bi-building me-1"></i> Vendor (Kepada):</h6>
                    <div class="p-3 border bg-light rounded-3">
                        <h6 class="mb-1 fw-bold">{{ optional($po->vendor)->name ?? $po->vendor_name ?? 'N/A' }}</h6>
                        <div class="small text-muted">
                            {!! nl2br(e(optional($po->vendor)->address ?? '-')) !!}<br>
                            PIC: {{ optional($po->vendor)->pic_name ?? '-' }} ({{ optional($po->vendor)->pic_phone ?? '-' }})<br>
                            Email: {{ optional($po->vendor)->email ?? '-' }}
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
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-muted text-uppercase"><i class="bi bi-info-circle me-1"></i> Detail Pembayaran:</h6>
                        {{-- Tombol Edit Khusus Invoice (Hanya tampil untuk Pembuat PO atau Super Admin) --}}
                        @if(auth()->id() == $po->created_by || auth()->user()->hasAnyRole(['Super Admin', 'super-admin']))
                            <button class="p-0 btn btn-link text-primary text-decoration-none small fw-bold" data-bs-toggle="modal" data-bs-target="#modalEditBilling">
                                <i class="bi bi-pencil-square"></i> Isi / Edit
                            </button>
                        @endif
                    </div>

                    <div class="p-3 border small text-muted rounded-3 h-100 bg-light position-relative">
                        <strong>Mata Uang:</strong> {{ $po->currency ?? 'IDR' }}<br>
                        <strong>Termin:</strong> {{ $po->payment_terms ?? '-' }}<br>
                        <strong>Estimasi Tiba:</strong> {{ $po->delivery_date ? \Carbon\Carbon::parse($po->delivery_date)->format('d M Y') : 'TBD' }}<br>
                        <hr class="my-2 border-secondary-subtle">
                        <strong class="text-dark">No. Invoice:</strong> <span class="text-primary fw-bold">{{ $po->invoice_number ?? '-' }}</span><br>
                        <strong class="text-dark">No. Rekening:</strong> <span class="text-success fw-bold">{{ $po->account_number ?? '-' }}</span>
                    </div>

                    {{-- 🔥 MODAL KHUSUS EDIT INVOICE & REKENING 🔥 --}}
                    <div class="modal fade" id="modalEditBilling" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="overflow-hidden border-0 shadow-lg modal-content rounded-4 text-start">
                                <form action="{{ route('po.update_billing', $po->po_number) }}" method="POST">
                                    @csrf
                                    <div class="py-3 text-white border-0 modal-header bg-primary">
                                        <h6 class="mb-0 modal-title fw-bold"><i class="bi bi-receipt me-2"></i>Lengkapi Data Penagihan</h6>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="p-4 modal-body bg-light">
                                        <div class="mb-3 alert alert-info small border-info-subtle">
                                            <i class="bi bi-info-circle-fill me-1"></i> Data ini dapat diedit kapan saja dan <strong>tidak akan</strong> mereset persetujuan atasan.
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold text-dark small">Nomor Invoice</label>
                                            <input type="text" name="invoice_number" class="shadow-sm form-control border-primary-subtle fw-bold text-primary" value="{{ $po->invoice_number }}" placeholder="Contoh: INV/2026/08/001">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold text-dark small">Nomor Rekening (Account No)</label>
                                            <input type="text" name="account_number" class="shadow-sm form-control border-success-subtle fw-bold text-success" value="{{ $po->account_number }}" placeholder="Contoh: BCA - 1234567890 a.n Vendor">
                                        </div>
                                    </div>
                                    <div class="p-3 bg-white modal-footer border-top">
                                        <button type="button" class="px-4 border btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" class="px-4 text-white shadow-sm btn btn-primary rounded-pill fw-bold">
                                            <i class="bi bi-save me-1"></i> Simpan Data
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Failsafe Accumulators --}}
            @php
                $calcSubtotalGross = 0;
                $calcTotalDiscount = 0;
                $calcTotalTax = 0;
            @endphp

            {{-- 🔥 TABEL ITEM: DIDESAIN KHUSUS UNTUK MEMANTAU SISA QTY 🔥 --}}
            <div class="mb-4 table-responsive">
                <table class="table mb-0 align-middle border table-hover">
                    <thead class="bg-primary bg-opacity-10 text-primary">
                        <tr>
                            <th width="5%" class="py-3 text-center">NO</th>
                            <th width="32%" class="py-3">IDENTITAS BARANG</th>
                            <th width="10%" class="py-3 text-center bg-white border-start">QTY PESAN</th>
                            <th width="10%" class="py-3 text-center bg-success-subtle text-success">SUDAH GR</th>
                            <th width="10%" class="py-3 text-center bg-danger-subtle text-danger border-end">SISA PENDING</th>
                            <th width="11%" class="py-3 text-end">HARGA SAT.</th>
                            <th width="10%" class="py-3 text-center">DISC/TAX</th>
                            <th width="12%" class="py-3 text-end pe-3">SUBTOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($po->items as $index => $item)
                            @php
                                // --- LOGIKA PERHITUNGAN QTY ---
                                $qtyPesan = (float) ($item->qty ?? $item->qty_ordered ?? 0);
                                if ($qtyPesan <= 0) $qtyPesan = 1;

                                $qtyTerima = (float) ($item->qty_received ?? 0);
                                $qtySisa = $qtyPesan - $qtyTerima;
                                if($qtySisa < 0) $qtySisa = 0; // Cegah minus jika penerimaan lebih

                                // --- LOGIKA HARGA & PAJAK ---
                                $hargaSatuan = (float) ($item->unit_price ?? $item->price ?? 0);
                                $subtotalDB = (float) ($item->subtotal ?? $item->total_price ?? 0);

                                if ($hargaSatuan == 0 && $subtotalDB > 0) {
                                    $hargaSatuan = $subtotalDB / $qtyPesan;
                                }

                                $diskon = (float) ($item->discount_amount ?? 0);
                                $pajak = (float) ($item->tax_amount ?? 0);

                                if ($pajak >= ($qtyPesan * $hargaSatuan) && ($qtyPesan * $hargaSatuan) > 0) {
                                    $pajak = 0;
                                }

                                $subtotalItem = ($qtyPesan * $hargaSatuan) - $diskon + $pajak;

                                $calcSubtotalGross += ($qtyPesan * $hargaSatuan);
                                $calcTotalDiscount += $diskon;
                                $calcTotalTax += $pajak;

                                // --- LOGIKA UOM DARI DATABASE MASTER ---
                                $masterItem = $item->item;
                                $baseUomName = optional(optional($masterItem)->uom)->name ?? 'UNIT';

                                $uomStr = $item->uom ?? $baseUomName;
                                if (is_string($uomStr) && str_starts_with(trim($uomStr), '{')) {
                                    $uomObj = json_decode($uomStr);
                                    $uomStr = $uomObj->code ?? $uomObj->name ?? $baseUomName;
                                } elseif (is_object($uomStr) || is_array($uomStr)) {
                                    $uomStr = is_object($uomStr) ? ($uomStr->code ?? $uomStr->name ?? $baseUomName) : ($uomStr['code'] ?? $uomStr['name'] ?? $baseUomName);
                                }

                                $tampilanSatuanLengkap = strtoupper(trim($uomStr));
                                if (empty($tampilanSatuanLengkap)) $tampilanSatuanLengkap = strtoupper($baseUomName);
                            @endphp

                            <tr>
                                <td class="text-center fw-bold text-muted">{{ $index + 1 }}</td>

                                {{-- IDENTITAS BARANG --}}
                                <td>
                                    <span class="px-2 py-1 mb-2 border badge bg-secondary-subtle text-secondary border-secondary-subtle font-monospace d-inline-block">
                                        {{ optional($item->item)->code ?? 'SKU-UNKNOWN' }}
                                    </span>
                                    <div class="mb-1 fw-bolder text-dark" style="font-size: 0.95rem;">
                                        {{ $item->item_name ?? optional($item->item)->name ?? 'Item Tidak Ditemukan' }}
                                    </div>
                                    <div class="text-muted fst-italic" style="font-size: 0.80rem;">
                                        {!! strip_tags($item->description ?? '') !!}
                                    </div>

                                    @if(isset($item->raw_attachments) && count($item->raw_attachments) > 0)
                                        <div class="flex-wrap gap-2 pt-1 mt-1 d-flex d-print-none">
                                            @foreach($item->raw_attachments as $idx => $vFile)
                                                <a href="{{ asset('storage/' . $vFile->file_path) }}" target="_blank" class="px-2 py-0 border badge bg-info-subtle text-info-emphasis text-decoration-none border-info-subtle">
                                                    <i class="bi bi-paperclip"></i> Dok
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>

                                {{-- KOLOM QTY PESAN --}}
                                <td class="text-center bg-white border-start">
                                    <div class="fw-bolder text-dark fs-6">{{ $qtyPesan }}</div>
                                    <div class="mt-1 badge bg-primary-subtle text-primary text-wrap lh-sm" style="font-size: 0.70rem;">
                                        {{ $tampilanSatuanLengkap }}
                                    </div>
                                </td>

                                {{-- KOLOM SUDAH DITERIMA (GR) --}}
                                <td class="text-center bg-success-subtle text-success">
                                    <div class="fw-bolder fs-5">{{ $qtyTerima }}</div>
                                </td>

                                {{-- KOLOM SISA GANTUNG --}}
                                <td class="text-center bg-danger-subtle text-danger border-end border-danger">
                                    <div class="fw-bolder fs-5">{{ $qtySisa }}</div>
                                    @if($qtySisa > 0)
                                        <div class="small fw-bold" style="font-size: 0.65rem;">Pending</div>
                                    @else
                                        <div class="text-success small fw-bold" style="font-size: 0.65rem;"><i class="bi bi-check2-all"></i> Genap</div>
                                    @endif
                                </td>

                                {{-- KOLOM HARGA SATUAN --}}
                                <td class="text-end fw-bold text-secondary">
                                    {{ number_format($hargaSatuan, 2, '.', ',') }}
                                </td>

                                {{-- KOLOM DISKON & PAJAK --}}
                                <td class="text-center" style="font-size: 0.75rem;">
                                    <div class="mb-1 text-danger fw-bold">
                                        Disc: {{ number_format($diskon, 2, '.', ',') }}
                                        <span class="small text-muted">{{ ($item->discount_type == 'PERCENT' && $item->discount_value > 0) ? '('.(float)$item->discount_value.'%)' : '' }}</span>
                                    </div>
                                    <div class="text-info fw-bold">
                                        Tax: {{ number_format($pajak, 2, '.', ',') }}
                                        <span class="small text-muted">{{ ($item->tax_type == 'PERCENT' && $item->tax_value > 0) ? '('.(float)$item->tax_value.'%)' : '' }}</span>
                                    </div>
                                </td>

                                {{-- KOLOM SUBTOTAL --}}
                                <td class="text-end fw-bolder text-dark pe-3" style="font-size: 1rem;">
                                    {{ number_format($subtotalItem, 2, '.', ',') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-4 text-center text-muted fst-italic">Tidak ada item barang dalam PO ini.</td>
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
                        {!! nl2br(e($po->description ?? $po->notes ?? 'Tidak ada catatan khusus.')) !!}
                    </div>
                </div>

                <div class="col-sm-5">
                    @php
                        $sumSubtotal = (float)($po->subtotal ?? 0) > 0 ? (float)$po->subtotal : $calcSubtotalGross;

                        $dbDiscount = (float)($po->discount_total ?? $po->discount_amount ?? 0);
                        $sumDiscount = $dbDiscount > 0 ? $dbDiscount : $calcTotalDiscount;

                        $dbTax = (float)($po->tax_total ?? $po->tax_amount ?? 0);
                        $sumTax = $dbTax > 0 ? $dbTax : $calcTotalTax;

                        $sumCharges = 0;
                        if(isset($charges) && count($charges) > 0) {
                            foreach($charges as $c) { $sumCharges += (float)$c->amount; }
                        }

                        $sumGrandTotal = (float)($po->grand_total ?? 0);
                        if ($sumGrandTotal <= 0) {
                            $sumGrandTotal = $sumSubtotal - $sumDiscount + $sumTax + $sumCharges;
                            if(isset($extraDiscounts) && count($extraDiscounts) > 0) {
                                foreach($extraDiscounts as $d) { $sumGrandTotal -= (float)$d->amount; }
                            }
                        }
                    @endphp

                    <div class="table-responsive">
                        <table class="table mb-0 table-sm table-borderless">
                            <tbody>
                                <tr>
                                    <td class="text-muted">Subtotal Gross</td>
                                    <td class="text-end fw-bold">{{ $po->currency }} {{ number_format($sumSubtotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-danger">Diskon Komersial {{ ($po->global_discount_type == 'PERCENT' && $po->global_discount_value > 0) ? '('.(float)$po->global_discount_value.'%)' : '' }}</td>
                                    <td class="text-end fw-bold text-danger">- {{ $po->currency }} {{ number_format($sumDiscount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-primary">Total Pajak (VAT/Ppn) {{ ($po->global_tax_type == 'PERCENT' && $po->global_tax_value > 0) ? '('.(float)$po->global_tax_value.'%)' : '' }}</td>
                                    <td class="text-end fw-bold text-primary">+ {{ $po->currency }} {{ number_format($sumTax, 2) }}</td>
                                </tr>

                                @if(isset($charges) && count($charges) > 0)
                                    @foreach($charges as $charge)
                                    <tr>
                                        <td class="text-secondary small ps-3">↳ Biaya: {{ $charge->name ?? 'Biaya Lainnya' }}</td>
                                        <td class="text-end small text-secondary">+ {{ $po->currency }} {{ number_format($charge->amount, 2) }}</td>
                                    </tr>
                                    @endforeach
                                @endif

                                @if(isset($extraDiscounts) && count($extraDiscounts) > 0)
                                    @foreach($extraDiscounts as $disc)
                                    <tr>
                                        <td class="text-danger small ps-3">↳ Potongan: {{ $disc->name ?? 'Diskon Tambahan' }}</td>
                                        <td class="text-end small text-danger">- {{ $po->currency }} {{ number_format($disc->amount, 2) }}</td>
                                    </tr>
                                    @endforeach
                                @endif

                                <tr class="border-2 border-top">
                                    <td class="pt-2 fs-5 fw-bold text-dark">GRAND TOTAL</td>
                                    <td class="pt-2 text-end fs-5 fw-bold text-success">{{ $po->currency }} {{ number_format($sumGrandTotal, 2) }}</td>
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
                $creatorDept = '';

                // Ambil nama departemen pembuat secara langsung dari database agar aman
                if ($creator && $creator->department_id) {
                    $creatorDept = \Illuminate\Support\Facades\DB::table('departments')->where('id', $creator->department_id)->value('name');
                }

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
                    <p class="mb-0 text-muted" style="font-size: 0.75rem;">{{ $creatorRole ? $creatorRole->name : 'Staff / Pembuat' }}</p>
                    <p class="mb-0 text-muted fw-bold" style="font-size: 0.65rem;">{{ $creatorDept ?: '-' }}</p>
                    <p class="mb-0 text-muted mt-1" style="font-size: 0.7rem;">Tgl: {{ \Carbon\Carbon::parse($po->created_at)->format('d/m/Y') }}</p>
                </div>

                @foreach($approvals as $approval)
                    @php
                        $roleName = optional($approval->role)->name ?? 'Atasan';
                        $deptName = '';

                        // Tentukan nama departemen
                        if (in_array($approval->status, ['APPROVED', 'REJECTED'])) {
                            // Jika sudah direspon, ambil dari data user yang klik setuju
                            if ($approval->approved_by) {
                                $apprUser = \App\Models\User::find($approval->approved_by);
                                if ($apprUser && $apprUser->department_id) {
                                    $deptName = \Illuminate\Support\Facades\DB::table('departments')->where('id', $apprUser->department_id)->value('name');
                                }
                            }
                        } else {
                            // Jika belum direspon (PENDING), baca dari aturan matriks
                            if ($approval->target_department_id === 'all' || $approval->target_department_id == 0) {
                                $deptName = 'Semua Departemen';
                            } elseif (!empty($approval->target_department_id)) {
                                $deptName = \Illuminate\Support\Facades\DB::table('departments')->where('id', $approval->target_department_id)->value('name');
                            } else {
                                $deptName = $creatorDept; // Atasan Langsung
                            }
                        }
                    @endphp

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
                            <p class="mb-0 text-muted" style="font-size: 0.75rem;">{{ $roleName }}</p>
                            <p class="mb-0 text-muted fw-bold" style="font-size: 0.65rem;">{{ $deptName ?: '-' }}</p>
                            <p class="mb-0 text-muted mt-1" style="font-size: 0.7rem;">Tgl: {{ \Carbon\Carbon::parse($approval->approved_at)->format('d/m/Y') }}</p>
                        @else
                            <p class="mb-0"><u><strong>....................................</strong></u></p>
                            <p class="mb-0 text-muted" style="font-size: 0.75rem;">{{ $roleName }}</p>
                            <p class="mb-0 text-muted fw-bold" style="font-size: 0.65rem;">{{ $deptName ?: '-' }}</p>
                            <p class="mb-0 text-muted mt-1" style="font-size: 0.7rem;">Tgl: ........................</p>
                        @endif
                    </div>
                @endforeach

                <div class="px-2 signature-box">
                    <p class="mb-1 text-muted">Diterima Oleh Vendor,</p>
                    <div class="sign-space" style="height: 80px;"></div>
                    <p class="mb-0"><u><strong>....................................</strong></u></p>
                    <p class="mb-0 text-muted" style="font-size: 0.75rem;">Cap & Tanda Tangan</p>
                    <p class="mb-0 text-white" style="font-size: 0.65rem;">-</p>
                    <p class="mb-0 text-muted mt-1" style="font-size: 0.7rem;">Tgl: ........................</p>
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
    @php
        $allAttachments = collect();

        if(isset($po->attachments) && count($po->attachments) > 0) {
            foreach($po->attachments as $att) {
                $allAttachments->push((object)[
                    'file_name' => $att->file_name,
                    'file_path' => $att->file_path,
                    'label' => 'Header PO'
                ]);
            }
        }

        if(isset($po->items)) {
            foreach($po->items as $idx => $itm) {
                if(isset($itm->raw_attachments) && count($itm->raw_attachments) > 0) {
                    foreach($itm->raw_attachments as $att) {
                        $allAttachments->push((object)[
                            'file_name' => $att->file_name,
                            'file_path' => $att->file_path,
                            'label' => 'Item: ' . ($itm->item_name ?? optional($itm->item)->name ?? 'Unknown')
                        ]);
                    }
                }
            }
        }
    @endphp

    <div class="mt-4 border-0 shadow-sm card rounded-4 d-print-none">
        <div class="py-3 bg-white card-header border-bottom d-flex align-items-center">
            <div class="p-2 me-3 d-flex align-items-center justify-content-center rounded-circle bg-info bg-opacity-10 text-info" style="width: 35px; height: 35px;">
                <i class="bi bi-paperclip fs-5"></i>
            </div>
            <h6 class="mb-0 fw-bold text-dark">Lampiran Dokumen Pendukung Lengkap</h6>
        </div>
        <div class="p-4 card-body">
            @if($allAttachments->count() > 0)
                <div class="flex-wrap gap-3 d-flex">
                    @foreach($allAttachments as $vFile)
                        <a href="{{ asset('storage/' . $vFile->file_path) }}" target="_blank" class="px-3 py-2 border file-card-hover badge bg-info-subtle text-info-emphasis text-decoration-none border-info-subtle fs-6" title="{{ $vFile->file_name }}">
                            <i class="bi bi-paperclip"></i> {{ $vFile->file_name }}
                            <div class="mt-1 fw-normal text-muted" style="font-size: 0.7rem;">(Sumber: {{ $vFile->label }})</div>
                        </a>
                    @endforeach
                </div>
            @else
                <span class="text-muted small fst-italic">Tidak ada dokumen lampiran pendukung untuk PO ini.</span>
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
                    @foreach($po->histories->sortByDesc('created_at') as $log)
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

    // 🔥 SCRIPT UNTUK FORCE CLOSE PO 🔥
    function confirmForceClose() {
        Swal.fire({
            title: 'Tutup Paksa (Force Close)?',
            text: "Sisa penerimaan barang akan dihentikan dan PO ini dianggap selesai. Berikan alasan:",
            input: 'textarea',
            inputPlaceholder: 'Cth: Vendor tidak sanggup kirim sisa pesanan...',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-check-circle-fill"></i> Ya, Tutup Paksa!',
            cancelButtonText: 'Batal',
            preConfirm: (reason) => {
                if (!reason || reason.trim() === '') {
                    Swal.showValidationMessage('Alasan tutup paksa wajib diisi!')
                }
                return reason;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('forceCloseReasonInput').value = result.value;
                document.getElementById('formForceClose').submit();
            }
        });
    }
</script>
@endpush
