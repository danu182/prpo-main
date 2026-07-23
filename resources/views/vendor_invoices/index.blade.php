@extends('layouts.app')

@push('css')
<style>
    .card-header-custom {
        border-radius: 16px 16px 0 0 !important;
        background: linear-gradient(90deg, #f8f9fa 0%, #ffffff 100%);
    }
    .table-custom th {
        background-color: #f1f4f9;
        color: #495057;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
    }
    .status-badge {
        font-size: 0.7rem;
        padding: 0.4em 0.8em;
        letter-spacing: 0.5px;
    }
    .gr-modal-table th { font-size: 0.75rem; }
    .gr-modal-table td { font-size: 0.85rem; vertical-align: middle; }

    /* Custom Checkbox */
    .custom-checkbox { width: 18px; height: 18px; cursor: pointer; }
</style>
@endpush

@section('content')
<div class="pb-5 container-fluid text-dark">

    {{-- HEADER HALAMAN --}}
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-receipt me-2 text-primary"></i>Daftar Tagihan Vendor (A/P)</h4>
            <div class="mt-1 text-muted small">Kelola hutang usaha, faktur vendor, dan riwayat pembayaran.</div>
        </div>
        {{-- BUNGKUS KEDUA TOMBOL DI SINI AGAR JARAKNYA PAS --}}
        <div class="gap-2 d-flex">
            <a href="{{ route('vendor-invoices.vendor-payments.list') }}" class="shadow-sm btn btn-outline-success rounded-pill fw-bold">
                <i class="bi bi-clock-history me-1"></i> Riwayat Pembayaran
            </a>
            <button type="button" class="px-4 shadow-sm btn btn-primary rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
                <i class="bi bi-plus-circle me-1"></i> Buat Tagihan Baru
            </button>
        </div>
    </div>

    {{-- FLASH MESSAGES --}}
    @if(session('success'))
        <div class="border-0 shadow-sm alert alert-success alert-dismissible fade show rounded-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="border-0 shadow-sm alert alert-danger alert-dismissible fade show rounded-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- FILTER & SEARCH --}}
    <div class="mb-4 bg-white border-0 shadow-sm card rounded-4">
        <div class="p-3 card-body">
            <form action="{{ route('vendor-invoices.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 bg-light" placeholder="Cari No. Invoice, PO, atau Nama Vendor..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-dark w-100 rounded-pill fw-bold">Cari Data</button>
                </div>
                @if(request('search'))
                <div class="col-md-2">
                    <a href="{{ route('vendor-invoices.index') }}" class="btn btn-sm btn-outline-danger w-100 rounded-pill">Reset</a>
                </div>
                @endif
            </form>
        </div>
    </div>

    {{-- TABEL DATA INVOICE --}}
    <div class="overflow-hidden border-0 shadow-sm card rounded-4">
        <div class="table-responsive">
            <table class="table mb-0 align-middle table-hover table-custom">
                <thead>
                    <tr>
                        <th class="py-3 ps-4" width="20%">No. Tagihan</th>
                        <th class="py-3" width="20%">Vendor & Referensi</th>
                        <th class="py-3 text-center" width="15%">Tgl Jatuh Tempo</th>
                        <th class="py-3 text-end" width="15%">Total Tagihan</th>
                        <th class="py-3 text-center" width="15%">Status</th>
                        <th class="py-3 text-center pe-4" width="15%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $inv)
                        @php
                            $statusSlug = strtolower(optional($inv->status)->slug ?? 'draft');
                            $badgeColor = 'secondary';
                            if($statusSlug == 'draft') $badgeColor = 'secondary';
                            if($statusSlug == 'posted') $badgeColor = 'primary';
                            if($statusSlug == 'partial') $badgeColor = 'warning';
                            if($statusSlug == 'paid') $badgeColor = 'success';

                            $isOverdue = ($statusSlug != 'paid' && \Carbon\Carbon::parse($inv->due_date)->isPast());
                        @endphp
                        <tr>
                            <td class="py-3 ps-4">
                                <a href="{{ route('vendor-invoices.show', $inv->invoice_number) }}" class="fw-bold text-decoration-none text-primary fs-6">
                                    {{ $inv->invoice_number }}
                                </a>
                                <div class="mt-1 text-muted small">
                                    Faktur Vendor: <strong class="text-dark">{{ $inv->vendor_invoice_number ?? 'Belum Diinput' }}</strong>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ optional($inv->vendor)->name }}</div>
                                <div class="mt-1 text-muted small">
                                    <i class="bi bi-link-45deg"></i> {{ optional($inv->purchaseOrder)->po_number }}
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="{{ $isOverdue ? 'text-danger fw-bold' : 'text-dark' }}">
                                    {{ \Carbon\Carbon::parse($inv->due_date)->format('d M Y') }}
                                </span>
                                @if($isOverdue)
                                    <br><span class="mt-1 badge bg-danger-subtle text-danger" style="font-size:0.65rem;">Terlambat</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @php
                                    // 🔥 Perbaikan: Menggunakan $inv, bukan $invoice 🔥
                                    $totalPaid = $inv->payments->sum('amount');
                                    $sisaHutang = $inv->grand_total - $totalPaid;
                                @endphp

                                {{-- Grand Total --}}
                                <div class="fw-bold text-dark">IDR {{ number_format($inv->grand_total, 0, ',', '.') }}</div>

                                {{-- Jika sudah ada pembayaran sebagian, tampilkan sisanya --}}
                                @if($totalPaid > 0 && $sisaHutang > 0)
                                    <span class="mt-1 border badge bg-warning-subtle text-warning-emphasis border-warning-subtle">
                                        Sisa: IDR {{ number_format($sisaHutang, 0, ',', '.') }}
                                    </span>
                                @elseif($sisaHutang <= 0 && $totalPaid > 0)
                                    <span class="mt-1 border badge bg-success-subtle text-success-emphasis border-success-subtle">
                                        LUNAS
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ $badgeColor }}-subtle text-{{ $badgeColor }} border border-{{ $badgeColor }}-subtle rounded-pill status-badge">
                                    {{ strtoupper(optional($inv->status)->name ?? 'DRAFT') }}
                                </span>
                            </td>
                            <td class="text-center pe-4">
                                <a href="{{ route('vendor-invoices.show', $inv->invoice_number) }}" class="px-3 border btn btn-sm btn-light text-primary rounded-pill fw-bold">
                                    Lihat Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-5 text-center">
                                <img src="https://cdn-icons-png.flaticon.com/512/7486/7486754.png" alt="Empty" width="80" class="mb-3 opacity-50">
                                <h6 class="fw-bold text-muted">Belum ada tagihan vendor yang dicatat.</h6>
                                <p class="text-muted small">Klik tombol "Buat Tagihan Baru" di sudut kanan atas untuk memulai.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="py-3 bg-white card-footer border-top d-flex justify-content-between align-items-center">
            <div class="small text-muted">
                Menampilkan {{ $invoices->firstItem() ?? 0 }} - {{ $invoices->lastItem() ?? 0 }} dari total {{ $invoices->total() }} data.
            </div>
            <div>
                {{ $invoices->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>

</div>

{{-- ======================================================== --}}
{{-- MODAL PILIH GR UNTUK DIBUATKAN TAGIHAN (DENGAN FITUR BULK) --}}
{{-- ======================================================== --}}
<div class="modal fade" id="createInvoiceModal" tabindex="-1" aria-labelledby="createInvoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="border-0 shadow-lg modal-content rounded-4">
            <div class="pb-3 modal-header bg-light border-bottom-0" style="border-radius: 16px 16px 0 0;">
                <div>
                    <h5 class="modal-title fw-bold text-dark" id="createInvoiceModalLabel"><i class="bi bi-box-arrow-in-down-right text-success me-2"></i>Pilih Dokumen Penerimaan (GR)</h5>
                    <div class="mt-1 small text-muted">Centang beberapa GR dari PO yang sama untuk digabungkan menjadi 1 Tagihan (Bulk Invoice).</div>
                </div>
                <button type="button" class="shadow-none btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="p-0 modal-body">
                @if($readyGrs->count() > 0)

                    {{-- 🔥 TAMBAHAN: KOTAK PENCARIAN REAL-TIME 🔥 --}}
                    <div class="p-3 bg-light border-bottom sticky-top" style="z-index: 11; top: 0;">
                        <div class="input-group input-group-sm">
                            <span class="bg-white input-group-text border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="searchGrModal" class="shadow-none form-control border-start-0" placeholder="Ketik No. GR, Nama Vendor, atau No. PO untuk menyaring data...">
                        </div>
                    </div>
                    {{-- ======================================= --}}

                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table mb-0 table-hover gr-modal-table" id="grTableList">
                            <thead class="bg-white shadow-sm sticky-top" style="z-index: 10;">
                                <tr>
                                    <th class="text-center ps-4" width="5%">
                                        <input class="form-check-input custom-checkbox border-secondary" type="checkbox" id="checkAllGr">
                                    </th>
                                    <th>No. Penerimaan (GR)</th>
                                    <th>Vendor & PO Asal</th>
                                    <th class="text-center">Tanggal Diterima</th>
                                    <th class="text-center pe-4">Aksi Tunggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($readyGrs as $gr)
                                <tr>
                                    <td class="text-center ps-4">
                                        <input class="form-check-input custom-checkbox border-secondary gr-checkbox" type="checkbox" value="{{ $gr->id }}" data-po="{{ $gr->purchase_order_id }}">
                                    </td>
                                    <td>
                                        <div class="fw-bold text-success">{{ $gr->gr_number }}</div>
                                        <div class="text-muted" style="font-size: 0.7rem;">Penerima: {{ optional($gr->receiver)->name ?? 'Sistem' }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ optional(optional($gr->po)->vendor)->name }}</div>
                                        <div class="text-primary" style="font-size: 0.7rem;"><i class="bi bi-link-45deg"></i> {{ optional($gr->po)->po_number }}</div>
                                    </td>
                                    <td class="text-center">
                                        {{ \Carbon\Carbon::parse($gr->received_date)->format('d M Y') }}
                                    </td>
                                    <td class="text-center pe-4">
                                        {{-- FORM POST UNTUK SINGLE INVOICE --}}
                                        <form action="{{ route('vendor-invoices.createFromGr', $gr->gr_number) }}" method="POST">
                                            @csrf
                                            <button type="button" class="px-3 btn btn-sm btn-outline-dark rounded-pill fw-bold btn-process-inv">
                                                Buat 1 Tagihan
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="px-4 py-5 text-center">
                        <i class="opacity-50 bi bi-clipboard-x text-muted" style="font-size: 3rem;"></i>
                        <h6 class="mt-3 fw-bold text-dark">Tidak Ada Data Penerimaan (GR) yang Siap Ditagih</h6>
                        <p class="mb-0 text-muted small">Semua dokumen penerimaan barang saat ini sudah dibuatkan tagihannya (Invoice) atau belum ada barang masuk yang baru.</p>
                    </div>
                @endif
            </div>

            <div class="pt-3 pb-3 modal-footer bg-light border-top-0 d-flex justify-content-between align-items-center" style="border-radius: 0 0 16px 16px;">
                <button type="button" class="px-4 btn btn-secondary rounded-pill" data-bs-dismiss="modal">Tutup</button>

                {{-- FORM TERSEMBUNYI UNTUK BULK INVOICE --}}
                <form id="bulkInvoiceForm" action="{{ route('vendor-invoices.createBulkFromGr') }}" method="POST" class="d-none">
                    @csrf
                    <input type="hidden" name="gr_ids" id="bulkGrIdsInput">
                </form>

                <button type="button" id="btnBulkInvoice" class="px-4 shadow-sm btn btn-warning rounded-pill fw-bold" disabled>
                    <i class="bi bi-intersect me-1"></i> Gabungkan Tagihan (<span id="bulkCount">0</span>)
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {

        // 1. LOGIKA UNTUK TOMBOL SINGLE INVOICE
        document.querySelectorAll('.btn-process-inv').forEach(button => {
            button.addEventListener('click', function() {
                let form = this.closest('form');
                Swal.fire({
                    title: 'Buat 1 Tagihan?',
                    text: "Sistem akan menarik data barang, harga, dan retur secara otomatis dari dokumen GR ini.",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Buat Tagihan',
                    cancelButtonText: 'Batal',
                    borderRadius: '15px'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                        form.submit();
                    }
                });
            });
        });

        // 2. LOGIKA UNTUK CHECKBOX & BULK INVOICE
        const checkAllBtn = document.getElementById('checkAllGr');
        const grCheckboxes = document.querySelectorAll('.gr-checkbox');
        const btnBulkInvoice = document.getElementById('btnBulkInvoice');
        const bulkCountSpan = document.getElementById('bulkCount');
        const bulkGrIdsInput = document.getElementById('bulkGrIdsInput');
        const bulkInvoiceForm = document.getElementById('bulkInvoiceForm');

        function updateBulkButton() {
            let checkedBoxes = document.querySelectorAll('.gr-checkbox:checked');
            let count = checkedBoxes.length;
            bulkCountSpan.innerText = count;

            // Aktifkan tombol gabung jika ada 1 atau lebih yang dicentang
            btnBulkInvoice.disabled = count === 0;

            if(checkAllBtn) {
                checkAllBtn.checked = (count === grCheckboxes.length && count > 0);
            }
        }

        if(checkAllBtn) {
            checkAllBtn.addEventListener('change', function() {
                // Hanya centang baris yang SEDANG TAMPIL (tidak disembunyikan oleh filter pencarian)
                let visibleCheckboxes = document.querySelectorAll('.gr-modal-table tbody tr:not([style*="display: none"]) .gr-checkbox');
                visibleCheckboxes.forEach(cb => cb.checked = this.checked);
                updateBulkButton();
            });
        }

        grCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateBulkButton);
        });

        if(btnBulkInvoice) {
            btnBulkInvoice.addEventListener('click', function() {
                let checkedBoxes = document.querySelectorAll('.gr-checkbox:checked');
                let selectedIds = [];
                let poIds = new Set(); // Menggunakan Set agar bisa mendeteksi duplikat PO

                checkedBoxes.forEach(cb => {
                    selectedIds.push(cb.value);
                    poIds.add(cb.getAttribute('data-po'));
                });

                // Validasi Keamanan: Pastikan semua berasal dari PO yang sama!
                if (poIds.size > 1) {
                    Swal.fire({
                        title: 'Gagal Menggabungkan!',
                        text: 'Penerimaan (GR) yang dicentang harus berasal dari Nomor PO dan Vendor yang SAMA.',
                        icon: 'error',
                        confirmButtonColor: '#dc3545',
                        borderRadius: '15px'
                    });
                    return;
                }

                bulkGrIdsInput.value = selectedIds.join(',');

                Swal.fire({
                    title: 'Gabungkan Tagihan?',
                    text: `Sistem akan menggabungkan ${selectedIds.length} dokumen GR ke dalam 1 Draf Tagihan. Lanjutkan?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ffc107',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, Gabungkan!',
                    cancelButtonText: 'Batal',
                    borderRadius: '15px'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({ title: 'Menggabungkan...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                        bulkInvoiceForm.submit();
                    }
                });
            });
        }

        // 3. LOGIKA PENCARIAN REAL-TIME DI DALAM MODAL
        const searchGrInput = document.getElementById('searchGrModal');
        if (searchGrInput) {
            searchGrInput.addEventListener('keyup', function() {
                let filter = this.value.toLowerCase();
                let rows = document.querySelectorAll('.gr-modal-table tbody tr');

                rows.forEach(row => {
                    let text = row.textContent.toLowerCase();
                    if (text.includes(filter)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                        // Opsional: Hilangkan centang jika baris disembunyikan
                        let cb = row.querySelector('.gr-checkbox');
                        if (cb) cb.checked = false;
                    }
                });
                // Update counter tombol gabung jika ada centang yang dibatalkan
                updateBulkButton();
            });
        }

    });
</script>
@endpush
