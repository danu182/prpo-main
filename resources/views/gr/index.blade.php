@extends('layouts.app')

@section('content')
<div class="pb-5 container-fluid">
    {{-- HEADER HALAMAN & TOMBOL AKSI --}}
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam me-2 text-success"></i>Penerimaan Barang (GR)</h3>
            <p class="mb-0 text-muted">Log barang masuk, aset, dan dokumen surat jalan</p>
        </div>

        <div class="gap-2 d-flex flex-column flex-md-row align-items-md-center">

            {{-- TOMBOL TRIGGER MODAL PENERIMAAN BARU (KHUSUS GUDANG) --}}
            @can('manage_gi') {{-- Sesuaikan dengan permission staf gudang Anda --}}
                <button type="button" class="px-4 shadow-sm btn btn-success rounded-3 fw-bold text-nowrap" data-bs-toggle="modal" data-bs-target="#receiveModal">
                    <i class="bi bi-plus-lg me-1"></i> Terima Barang Baru
                </button>
            @endcan

            {{-- 1. FITUR PENCARIAN (SEARCH BAR) --}}
            <form action="{{ route('gr.index') }}" method="GET" class="m-0 d-flex">
                <div class="shadow-sm input-group">
                    {{-- 🔥 Placeholder diupdate 🔥 --}}
                    <input type="text" name="search" class="form-control" placeholder="Cari No. GR, PO, Vendor, Nama Barang..." value="{{ request('search') }}">
                    <button class="px-3 btn btn-primary" type="submit" title="Cari Data">
                        <i class="bi bi-search"></i>
                    </button>
                    @if(request('search'))
                        <a href="{{ route('gr.index') }}" class="px-3 btn btn-outline-danger" title="Reset Pencarian">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </div>
            </form>

            {{-- 2. TOMBOL GABUNG TAGIHAN (BULK INVOICE) --}}
            @can('create_invoices')
            <form id="bulk-invoice-form" action="{{ route('vendor-invoices.createBulkFromGr') }}" method="POST" class="m-0 d-inline">
                @csrf
                <input type="hidden" name="gr_ids" id="hidden_gr_ids">
                <button type="button" onclick="prosesGabungTagihan()" class="px-4 shadow-sm btn btn-warning fw-bold text-nowrap rounded-3 text-dark">
                    <i class="bi bi-layers me-1"></i> Gabung Tagihan
                </button>
            </form>
            @endcan
        </div>
    </div>

    {{-- ========================================================================= --}}
    {{-- MODAL PILIH PO UNTUK DITERIMA DENGAN LIVE SEARCH --}}
    {{-- ========================================================================= --}}
    <div class="modal fade" id="receiveModal" tabindex="-1" aria-labelledby="receiveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="border-0 shadow-lg modal-content rounded-4">
                <div class="py-3 text-white border-0 modal-header bg-success rounded-top-4">
                    <h5 class="modal-title fw-bold" id="receiveModalLabel"><i class="bi bi-truck me-2"></i>Pilih PO yang Sedang Dikirim</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="p-0 modal-body position-relative bg-light">
                    {{-- 🔍 FITUR PENCARIAN LIVE DI DALAM MODAL --}}
                    <div class="p-3 bg-white border-bottom sticky-top" style="z-index: 10;">
                        <div class="shadow-sm input-group">
                            <span class="bg-white input-group-text border-end-0"><i class="bi bi-search text-muted"></i></span>
                            {{-- 🔥 Placeholder diupdate 🔥 --}}
                            <input type="text" id="searchInputModal" class="form-control border-start-0 ps-0 fw-semibold text-primary" placeholder="Ketik No PO, Vendor, atau Nama Barang (Live Search)...">
                        </div>
                    </div>

                    <div class="bg-white list-group list-group-flush" id="poListContainer" style="max-height: 450px; overflow-y: auto;">
                        @forelse($readyPOs as $po)
                            <a href="{{ route('gr.create', $po->po_number) }}" class="p-4 list-group-item list-group-item-action border-bottom hover-bg-light po-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1 fw-bold text-primary">{{ $po->po_number }}</h6>
                                        <div class="mb-1 text-dark fw-semibold"><i class="bi bi-shop me-1"></i> {{ optional($po->vendor)->name }}</div>
                                        <div class="mb-1 small text-muted"><i class="bi bi-building me-1"></i> Tujuan: {{ optional($po->company)->name ?? 'Head Office' }}</div>

                                        {{-- 🔥 TAMPILAN NAMA BARANG AGAR BISA DIBACA OLEH JS LIVE SEARCH 🔥 --}}
                                        <div class="small text-muted text-truncate" style="max-width: 350px;">
                                            <i class="bi bi-box-seam me-1"></i>
                                            @php
                                                $itemNames = $po->items->map(function($i) { return optional($i->item)->name; })->filter()->implode(', ');
                                            @endphp
                                            <span style="font-size: 0.7rem;">Item: {{ $itemNames }}</span>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="px-3 py-2 mb-2 border shadow-sm badge bg-success-subtle text-success border-success-subtle rounded-pill"><i class="bi bi-box-arrow-in-down me-1"></i> Siap Diterima</span>
                                        <div class="small text-muted fw-bold"><i class="bi bi-calendar-event me-1"></i> Tgl PO: {{ \Carbon\Carbon::parse($po->po_date)->format('d M Y') }}</div>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="p-5 text-center text-muted" id="emptyStateModal">
                                <i class="mb-2 opacity-50 bi bi-check2-circle fs-1 d-block text-success"></i>
                                <h6>Tidak ada pengiriman yang tertunda.</h6>
                                <small>Semua PO sudah diterima atau belum ada PO baru yang diterbitkan.</small>
                            </div>
                        @endforelse

                        {{-- Pesan jika pencarian tidak ditemukan --}}
                        <div id="noResultMessage" class="p-5 text-center text-muted d-none">
                            <i class="mb-3 opacity-50 bi bi-search fs-1 d-block text-secondary"></i>
                            <h6 class="fw-bold">Data tidak ditemukan.</h6>
                            <small>Coba periksa kembali Nomor PO, Vendor, atau Nama Barang.</small>
                        </div>
                    </div>
                </div>
                <div class="border-0 modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="px-4 btn btn-secondary rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                </div>
            </div>
        </div>
    </div>
    {{-- ========================================================================= --}}

    {{-- 🔥 NOTIFIKASI SUKSES DENGAN TOMBOL CETAK INSTAN 🔥 --}}
    @if(session('success'))
        <div class="p-4 mb-4 border-0 shadow-sm alert alert-success rounded-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between" role="alert">
            <div class="mb-3 d-flex align-items-center mb-md-0">
                <i class="bi bi-check-circle-fill fs-3 text-success me-3"></i>
                <div>
                    <div class="fw-bold fs-6 text-dark">{{ session('success') }}</div>
                    @if(session('new_gr'))
                        <small class="text-muted">Nomor GR Baru: <strong class="text-success">{{ session('new_gr') }}</strong></small>
                    @endif
                </div>
            </div>

            @if(session('print_url'))
                <div class="gap-2 d-flex">
                    <a href="{{ session('print_url') }}" target="_blank" class="px-4 shadow-sm btn btn-dark rounded-pill fw-bold">
                        <i class="bi bi-printer me-2"></i> Cetak GR Sekarang
                    </a>
                    <button type="button" class="top-0 btn-close position-relative end-0 ms-2" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
        </div>
    @endif

    {{-- Notifikasi Error --}}
    @if(session('error'))
        <div class="border-0 border-4 shadow-sm alert alert-danger alert-dismissible fade show rounded-3 border-start border-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- 3. TABEL RIWAYAT PENERIMAAN BARANG --}}
    <div class="overflow-hidden border-0 border-4 shadow-sm card border-top border-primary rounded-3">
        <div class="p-0 card-body">
            <div class="table-responsive">
                <table class="table mb-0 align-middle table-hover">
                    <thead class="table-light text-muted small text-uppercase fw-bold border-bottom">
                        <tr>
                            @can('create_invoices')
                            <th class="py-3 text-center" width="50">
                                <input class="form-check-input" type="checkbox" id="checkAll" style="transform: scale(1.2);" title="Pilih Semua">
                            </th>
                            @endcan
                            <th class="py-3 ps-4">No Penerimaan (GR)</th>
                            <th class="py-3">Tanggal Terima</th>
                            <th class="py-3">Referensi PO</th>
                            <th class="py-3">Vendor</th>
                            <th class="py-3">Surat Jalan / Invoice</th>
                            <th class="py-3">Diterima Oleh</th>
                            <th class="py-3 text-end pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($grs as $gr)
                        <tr>
                            @can('create_invoices')
                            <td class="text-center">
                                @php
                                    $hasInvoice = \App\Models\VendorInvoiceItem::whereIn('goods_receipt_item_id', $gr->items->pluck('id'))->exists();
                                @endphp

                                @if(!$hasInvoice)
                                    <input type="checkbox" class="form-check-input gr-checkbox border-secondary" value="{{ $gr->id }}" data-po="{{ $gr->purchase_order_id }}" style="transform: scale(1.3);">
                                @else
                                    <i class="bi bi-check-circle-fill text-success fs-5" title="Sudah Ditagihkan"></i>
                                @endif
                            </td>
                            @endcan

                            <td class="py-3 ps-4">
                                <a href="{{ route('gr.show', $gr->gr_number) }}" class="fw-bold text-primary text-decoration-none d-block">
                                    {{ $gr->gr_number }}
                                </a>
                                @if($gr->return_to_vendors_count > 0)
                                    <span class="mt-1 border shadow-sm badge bg-danger-subtle text-danger border-danger-subtle" style="font-size: 0.65rem;">
                                        <i class="bi bi-arrow-return-left me-1"></i> Ada Retur
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 fw-semibold text-dark">
                                {{ \Carbon\Carbon::parse($gr->received_date)->format('d M Y') }}
                            </td>
                            <td class="py-3">
                                <a href="{{ route('po.show', $gr->purchase_order_id) }}" class="text-decoration-none fw-bold text-secondary">
                                    {{ optional($gr->po)->po_number ?? '-' }}
                                </a>
                            </td>
                            <td class="py-3 fw-bold text-dark">{{ optional($gr->po?->vendor)->name ?? '-' }}</td>
                            <td class="py-3">
                                <div><small class="text-muted">DO:</small> <span class="fw-bold">{{ $gr->delivery_note_number ?? '-' }}</span></div>
                                @if($gr->invoice_number)
                                    <div><small class="text-muted">Inv:</small> {{ $gr->invoice_number }}</div>
                                @endif
                            </td>
                            <td class="py-3">{{ optional($gr->receiver)->name ?? '-' }}</td>

                            {{-- KOLOM AKSI --}}
                            <td class="py-3 text-end pe-4">
                                <div class="gap-1 d-flex flex-column align-items-end">

                                    {{-- 1. Tombol Cetak GR --}}
                                    <a href="{{ route('gr.print', $gr->gr_number) }}" target="_blank" class="px-3 mb-1 shadow-sm btn btn-sm btn-primary fw-bold w-100">
                                        <i class="bi bi-printer me-1"></i> Cetak GR
                                    </a>

                                    {{-- 2. Lampiran File --}}
                                    @if(isset($gr->attachments) && $gr->attachments->count() > 0)
                                        @foreach($gr->attachments as $file)
                                            <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="btn btn-sm btn-outline-info text-start text-truncate w-100 fw-semibold" style="max-width: 150px; font-size: 0.7rem;" title="{{ $file->file_name }}">
                                                <i class="bi bi-file-earmark-text me-1"></i> {{ $file->file_name }}
                                            </a>
                                        @endforeach
                                    @endif

                                    {{-- 3. Logika Tagihan (Invoice) --}}
                                    @php
                                        if(!isset($hasInvoice)) {
                                            $hasInvoice = \App\Models\VendorInvoiceItem::whereIn('goods_receipt_item_id', $gr->items->pluck('id'))->exists();
                                        }
                                    @endphp

                                    @if(!$hasInvoice)
                                        @can('create_invoices')
                                        <form id="form-invoice-{{ $gr->id }}" action="{{ route('vendor-invoices.createFromGr', $gr->id) }}" method="POST" class="mt-1 w-100">
                                            @csrf
                                            <button type="button" onclick="confirmBuatTagihan({{ $gr->id }})" class="px-3 shadow-sm btn btn-sm btn-warning fw-bold w-100 text-dark">
                                                <i class="bi bi-receipt me-1"></i> Buat Tagihan
                                            </button>
                                        </form>
                                        @endcan
                                    @else
                                        @can('view_invoices')
                                        @php
                                            $invoiceItem = \App\Models\VendorInvoiceItem::whereIn('goods_receipt_item_id', $gr->items->pluck('id'))->first();
                                            $existingInvId = $invoiceItem ? $invoiceItem->vendor_invoice_id : null;
                                        @endphp
                                        @if($existingInvId)
                                        <a href="{{ route('vendor-invoices.show', $existingInvId) }}" class="px-3 mt-1 shadow-sm btn btn-sm btn-outline-success fw-bold w-100 text-truncate">
                                            <i class="bi bi-check-circle me-1"></i> Lihat Tagihan
                                        </a>
                                        @endif
                                        @endcan
                                    @endif

                                    {{-- 4. TOMBOL BARU: RETUR BARANG (RTV) --}}
                                    <a href="{{ route('rtv.create', $gr->gr_number) }}" class="px-3 mt-1 shadow-sm btn btn-sm btn-outline-danger fw-bold w-100" title="Retur Barang ke Vendor">
                                        <i class="bi bi-arrow-return-left me-1"></i> Retur RTV
                                    </a>

                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="py-5 text-center text-muted">
                                <i class="mb-2 opacity-50 bi bi-search fs-1 d-block text-secondary"></i>
                                @if(request('search'))
                                    Tidak ada data penerimaan barang yang cocok dengan "<b>{{ request('search') }}</b>".
                                @else
                                    Belum ada riwayat penerimaan barang di gudang.
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="pt-3 bg-white border-0 card-footer pe-4">
            {{ $grs->appends(request()->query())->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // ==============================================================
    // 🔍 JAVASCRIPT LIVE SEARCH UNTUK MODAL TERIMA BARANG
    // ==============================================================
    let searchInputModal = document.getElementById('searchInputModal');
    if (searchInputModal) {
        searchInputModal.addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let items = document.querySelectorAll('.po-item');
            let visibleCount = 0;

            items.forEach(function(item) {
                let text = item.innerText.toLowerCase();
                if (text.includes(filter)) {
                    item.classList.remove('d-none');
                    visibleCount++;
                } else {
                    item.classList.add('d-none');
                }
            });

            let noResultMessage = document.getElementById('noResultMessage');
            if (noResultMessage) {
                if (visibleCount === 0 && items.length > 0) {
                    noResultMessage.classList.remove('d-none');
                } else {
                    noResultMessage.classList.add('d-none');
                }
            }
        });
    }
    // ==============================================================

    // Fitur Check All untuk Checkbox Gabung Tagihan
    let checkAllBtn = document.getElementById('checkAll');
    if(checkAllBtn) {
        checkAllBtn.addEventListener('change', function() {
            let checkboxes = document.querySelectorAll('.gr-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }

    // Modal Konfirmasi Buat 1 Tagihan
    function confirmBuatTagihan(grId) {
        Swal.fire({
            title: 'Buat Tagihan Otomatis?',
            html: "Sistem akan mengkalkulasi tagihan secara akurat berdasarkan <b>Harga PO</b> dan <b>Kuantitas GR</b>.",
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-check-circle text-dark me-1"></i> Ya, Buat Tagihan',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            customClass: {
                confirmButton: 'text-dark fw-bold rounded-pill px-4',
                cancelButton: 'rounded-pill px-4'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Memproses...', allowOutsideClick: false, didOpen: () => { Swal.showLoading() } });
                document.getElementById('form-invoice-' + grId).submit();
            }
        });
    }

    // Modal Konfirmasi Gabung Tagihan (Bulk)
    function prosesGabungTagihan() {
        let checkedBoxes = document.querySelectorAll('.gr-checkbox:checked');

        if (checkedBoxes.length === 0) {
            Swal.fire('Peringatan', 'Silakan centang minimal 1 dokumen penerimaan (GR) di tabel.', 'warning');
            return;
        }

        let poId = null;
        let isValid = true;
        let grIds = [];

        checkedBoxes.forEach(function(box) {
            let currentPoId = box.getAttribute('data-po');
            if (poId === null) {
                poId = currentPoId;
            } else if (poId !== currentPoId) {
                isValid = false;
            }
            grIds.push(box.value);
        });

        if (!isValid) {
            Swal.fire('Aksi Ditolak!', 'Dokumen GR yang digabungkan <b>HARUS</b> berasal dari Referensi PO yang sama. Silakan periksa kembali centang Anda.', 'error');
            return;
        }

        Swal.fire({
            title: 'Gabungkan ' + checkedBoxes.length + ' Tagihan?',
            html: "Sistem akan mengkalkulasi total barang, diskon, dan pajak secara proporsional dari dokumen yang Anda pilih menjadi <b>1 Lembar Faktur Tagihan</b>.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-layers text-dark me-1"></i> Ya, Gabungkan!',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            customClass: {
                confirmButton: 'text-dark fw-bold rounded-pill px-4',
                cancelButton: 'rounded-pill px-4'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Menggabungkan Data...', allowOutsideClick: false, didOpen: () => { Swal.showLoading() } });
                document.getElementById('hidden_gr_ids').value = grIds.join(',');
                document.getElementById('bulk-invoice-form').submit();
            }
        });
    }
</script>
<style>
    .hover-bg-light:hover { background-color: #f8f9fa !important; transition: 0.2s; cursor: pointer; }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // 🔥 TANGKAP SINYAL PRINT DARI CONTROLLER SETELAH GR DISIMPAN 🔥
        @if(session('print_url'))
            Swal.fire({
                title: 'Penerimaan Berhasil! 🎉',
                text: "Barang sudah masuk ke sistem dan stok bertambah. Apakah Anda ingin langsung mencetak Bukti Penerimaan Barang (Goods Receipt) ini?",
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#198754', // Warna Hijau Success
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-printer"></i> Ya, Cetak Sekarang',
                cancelButtonText: 'Nanti Saja',
                borderRadius: '15px'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Buka dokumen GR di tab baru untuk diprint
                    window.open('{{ session('print_url') }}', '_blank');
                }
            });
        @endif
    });
</script>
@endpush
