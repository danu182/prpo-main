@extends('layouts.app')

@push('css')
<style>
    .invoice-header { border-left: 5px solid #0d6efd; background-color: #f8f9fa; }
    .status-draft { background-color: #e2e3e5; color: #383d41; }
    .status-posted { background-color: #cce5ff; color: #004085; }
    .status-partial { background-color: #fff3cd; color: #856404; }
    .status-paid { background-color: #d4edda; color: #155724; }
    .table-items th { font-size: 0.8rem; text-transform: uppercase; background-color: #f1f4f9; }
    .summary-box { background: #f8f9fa; border-radius: 10px; padding: 15px; }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    @php
        $statusSlug = strtolower(optional($invoice->status)->slug ?? 'draft');
        $isDraft = $statusSlug === 'draft';
        $isPayable = in_array($statusSlug, ['posted', 'partial']);
        $totalPaid = $invoice->payments->sum('amount');
        $sisaTagihan = $invoice->grand_total - $totalPaid;
    @endphp

    {{-- HEADER HALAMAN & AKSI --}}
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-file-earmark-invoice me-2 text-primary"></i>Detail Tagihan Vendor</h4>
            <div class="text-muted small">
                No. Internal: <strong class="text-primary">{{ $invoice->invoice_number }}</strong>
            </div>
        </div>
        <div class="gap-2 d-flex">
            <a href="{{ route('vendor-invoices.index') }}" class="shadow-sm btn btn-outline-secondary rounded-pill fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>

            {{-- Tombol Batal Tagihan (SweetAlert UI) --}}
            @if($totalPaid == 0)
                <form id="form-void-invoice" action="{{ route('vendor-invoices.cancelInvoice', $invoice->invoice_number) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="cancel_reason" id="invoice-cancel-reason">
                    <button type="button" class="shadow-sm btn btn-danger rounded-pill fw-bold" onclick="confirmVoidInvoice()">
                        <i class="bi bi-trash-fill me-1"></i> Batalkan Tagihan
                    </button>
                </form>
            @endif

            {{-- KODE BARU: MENGARAHKAN KE HALAMAN KERTAS CETAK (TAB BARU) --}}
            <a href="{{ route('vendor-invoices.print', $invoice->invoice_number) }}" target="_blank" class="shadow-sm btn btn-dark rounded-pill fw-bold">
                <i class="bi bi-printer me-1"></i> Cetak Resmi
            </a>
            {{-- TAMPILKAN TOMBOL KWITANSI HANYA JIKA STATUS SUDAH LUNAS (PAID) --}}
            @if(optional($invoice->status)->slug === 'paid')
                <a href="{{ route('vendor-invoices.print', ['slug' => $invoice->invoice_number]) }}" target="_blank" class="btn btn-outline-success fw-bold">
                    <i class="bi bi-patch-check-fill me-1"></i> Cetak Kwitansi Lunas
                </a>
            @endif

            @if($isPayable)
                <button type="button" class="px-4 shadow-sm btn btn-success rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#paymentModal">
                    <i class="bi bi-cash-coin me-1"></i> Bayar Tagihan
                </button>
            @endif
        </div>
    </div>

    {{-- FLASH MESSAGES --}}
    @if(session('success'))
        <div class="border-0 shadow-sm alert alert-success alert-dismissible fade show rounded-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="border-0 shadow-sm alert alert-danger alert-dismissible fade show rounded-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        {{-- KOLOM KIRI: INFO & FORM --}}
        <div class="col-lg-8">
            {{-- INFORMASI UTAMA --}}
            <div class="p-4 mb-4 border-0 shadow-sm card rounded-4 invoice-header">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="mb-3 text-uppercase fw-bold text-muted small">Informasi Vendor</h6>
                        <h5 class="mb-1 fw-bold text-dark">{{ optional($invoice->vendor)->name }}</h5>
                        <p class="mb-0 text-muted small"><i class="bi bi-geo-alt-fill me-1"></i>{{ optional($invoice->vendor)->address ?? 'Alamat tidak tersedia' }}</p>
                    </div>
                    <div class="col-md-6 border-start">
                        <h6 class="mb-3 text-uppercase fw-bold text-muted small">Referensi Dokumen</h6>
                        <table class="table mb-0 table-sm table-borderless small">
                            <tr><td class="text-muted" width="40%">Dari PO</td><td class="fw-bold">: <a href="#">{{ optional($invoice->purchaseOrder)->po_number }}</a></td></tr>
                            <tr><td class="text-muted">Dari GR</td><td class="fw-bold text-success">: <a href="#" class="text-success">{{ optional($invoice->goodsReceipt)->gr_number ?? 'GABUNGAN (BULK)' }}</a></td></tr>
                            <tr><td class="text-muted">Dibuat Oleh</td><td class="fw-bold">: {{ optional($invoice->creator)->name }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            {{-- FORM EDIT DRAF / TAMPILAN POSTED --}}
            <div class="mb-4 border-0 shadow-sm card rounded-4">
                <div class="py-3 bg-white border-bottom card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-info-square me-2 text-primary"></i>Data Faktur Fisik</h6>
                    <span class="badge status-{{ $statusSlug }} rounded-pill px-3 py-2 fw-bold border">
                        Status: {{ strtoupper(optional($invoice->status)->name ?? 'DRAFT') }}
                    </span>
                </div>
                <div class="p-4 card-body">
                    <form action="{{ route('vendor-invoices.update', $invoice->invoice_number) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="mb-1 fw-bold small text-muted">No. Faktur Vendor <span class="text-danger">*</span></label>
                                <input type="text" name="vendor_invoice_number" class="form-control fw-bold" value="{{ old('vendor_invoice_number', $invoice->vendor_invoice_number) }}" {{ !$isDraft ? 'readonly' : 'required' }} placeholder="Ketik nomor tagihan fisik dari vendor...">
                            </div>
                            <div class="col-md-6">
                                <label class="mb-1 fw-bold small text-muted">Tanggal Jatuh Tempo <span class="text-danger">*</span></label>
                                <input type="date" name="due_date" class="form-control" value="{{ old('due_date', \Carbon\Carbon::parse($invoice->due_date)->format('Y-m-d')) }}" {{ !$isDraft ? 'readonly' : 'required' }}>
                            </div>
                        </div>

                        @if($isDraft)
                            <div class="p-3 mt-4 border rounded bg-light border-info-subtle">
                                <div class="mb-2 form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="postInvoiceCheck" name="post_invoice" value="1">
                                    <label class="fw-bold form-check-label text-dark" for="postInvoiceCheck">Posting Tagihan Ini (Kunci & Siap Dibayar)</label>
                                </div>
                                <div class="mb-3 small text-muted"><i class="bi bi-info-circle me-1"></i>Jika di-posting, tagihan tidak dapat diubah lagi angkanya dan akan masuk ke daftar hutang yang siap dibayar.</div>
                                <button type="submit" class="shadow-sm btn btn-primary fw-bold"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
                            </div>
                        @endif
                    </form>
                </div>
            </div>

            {{-- LACI DOKUMEN FAKTUR PAJAK / GARANSI (SEKARANG SELALU TERBUKA) --}}
            <div class="mb-4 border-0 shadow-sm card rounded-4">
                <div class="py-3 bg-white border-bottom card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-folder2-open me-2 text-warning"></i>Laci Dokumen Invoice</h6>
                    <span class="border badge bg-light text-dark"><i class="bi bi-info-circle me-1"></i>Bisa diupload kapan saja</span>
                </div>
                <div class="p-4 card-body">

                    {{-- Form Upload Multi File --}}
                    <form action="{{ route('vendor-invoices.uploadAttachment', $invoice->invoice_number) }}" method="POST" enctype="multipart/form-data" class="mb-4">
                        @csrf

                        {{-- Wadah Baris --}}
                        <div id="invoice-attachment-container">
                            <div class="mb-2 row g-2 align-items-end invoice-attachment-row">
                                <div class="col-md-5">
                                    <label class="mb-1 small fw-bold text-muted">Jenis Dokumen</label>
                                    <input list="documentTypeOptions" name="document_types[]" class="form-control form-control-sm" placeholder="Ketik atau pilih jenis..." required autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label class="mb-1 small fw-bold text-muted">Pilih File (PDF/JPG/PNG)</label>
                                    <input type="file" name="attachment_files[]" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" required>
                                </div>
                                <div class="col-md-1 text-end">
                                    {{-- Tombol hapus baris --}}
                                    <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeInvoiceDocRow(this)" title="Hapus"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        </div>

                        {{-- Tombol Aksi Bawah --}}
                        <div class="mt-3 d-flex justify-content-between">
                            <button type="button" class="px-3 btn btn-sm btn-outline-primary rounded-pill fw-bold" onclick="addInvoiceDocRow()">
                                <i class="bi bi-plus-circle me-1"></i> Tambah File
                            </button>
                            <button type="submit" class="px-4 btn btn-sm btn-dark rounded-pill fw-bold">
                                <i class="bi bi-upload me-1"></i> Upload Semua File
                            </button>
                        </div>
                    </form>

                    {{-- Datalist --}}
                    <datalist id="documentTypeOptions">
                        <option value="Faktur Pajak (E-Faktur)"></option>
                        <option value="Surat Jalan (DO)"></option>
                        <option value="Sertifikat Garansi"></option>
                        <option value="Kwitansi Fisik"></option>
                        <option value="Bukti Potong PPh"></option>
                    </datalist>

                    {{-- Daftar File Laci --}}
                    @if(isset($invoice->attachments) && $invoice->attachments->count() > 0)
                        <div class="table-responsive">
                            <table class="table mb-0 align-middle border table-sm table-hover">
                                <thead class="bg-light text-muted small">
                                    <tr>
                                        <th>Jenis</th>
                                        <th>Nama File</th>
                                        <th>Waktu Upload</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="small">
                                    @foreach($invoice->attachments as $doc)
                                    <tr>
                                        <td class="fw-bold text-dark"><i class="bi bi-file-earmark-check text-success me-1"></i>{{ $doc->document_type }}</td>
                                        <td><a href="{{ asset('storage/' . $doc->file_path) }}" target="_blank" class="text-decoration-none">{{ Str::limit($doc->file_name, 35) }}</a></td>
                                        <td class="text-muted">{{ $doc->created_at->format('d M Y, H:i') }}</td>
                                        <td class="text-center">
                                            <form action="{{ route('vendor-invoices.deleteAttachment', $doc->id) }}" method="POST" class="form-delete-doc">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="p-0 btn btn-sm btn-link text-danger btn-delete-doc" title="Hapus Dokumen">
                                                    <i class="bi bi-trash fs-5"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-3 text-center border border-dashed rounded text-muted small bg-light">
                            Belum ada dokumen yang dilampirkan.
                        </div>
                    @endif
                </div>
            </div>

            {{-- TABEL RINCIAN BARANG DITAGIH --}}
                <div class="mb-4 border-0 shadow-sm card rounded-4">
                    <div class="py-3 bg-white border-bottom card-header">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-columns me-2 text-primary"></i>Rincian Barang Ditagih</h6>
                    </div>
                    <div class="p-0 card-body table-responsive">
                        <table class="table mb-0 align-middle table-hover table-items">
                            <thead class="bg-light text-muted small text-uppercase fw-bold border-bottom">
                                <tr>
                                    <th class="py-3 ps-4" width="25%">Barang & Spesifikasi</th>
                                    <th class="py-3" width="25%">Referensi Dokumen</th>
                                    <th class="py-3 text-center" width="15%">Qty Ditagih</th>
                                    <th class="py-3 text-end" width="15%">Harga Satuan</th>
                                    <th class="py-3 text-end pe-4" width="20%">Subtotal Baris</th>
                                </tr>
                            </thead>
                            <tbody>
                        @foreach($invoice->items as $item)
                        @php
                            // 1. 🔥 BYPASS RELASI: Cari Langsung Pakai ID (Anti-Gagal) 🔥
                            $grItem = \App\Models\GoodsReceiptItem::find($item->goods_receipt_item_id);
                            $poItem = $grItem ? \App\Models\PurchaseOrderItem::find($grItem->purchase_order_item_id) : null;

                            // 2. Tarik Nomor Header (GR & PO)
                            $grNum = $grItem ? optional($grItem->goodsReceipt)->gr_number : null;
                            $poNum = $poItem ? optional($poItem->purchaseOrder)->po_number : null;

                            // 3. Hitung Potongan Retur
                            $qtyGrAsli = $grItem ? (float) $grItem->qty_received : (float) $item->qty_invoiced;
                            $returQty = $qtyGrAsli - (float) $item->qty_invoiced;

                            // 4. TARIK DOKUMEN RTV (JIKA ADA RETUR)
                            $rtvDocs = [];
                            if ($returQty > 0 && $grItem && $grItem->goods_receipt_id) {
                                $rtvRecords = \Illuminate\Support\Facades\DB::table('return_to_vendors')
                                    ->where('goods_receipt_id', $grItem->goods_receipt_id)
                                    ->select('rtv_number')
                                    ->get();

                                $rtvDocs = $rtvRecords->pluck('rtv_number')->filter()->toArray();
                            }

                            // 5. 🔥 LOGIKA NAMA SPESIFIK & MASTER 🔥
                            $masterItem = $item->item;
                            $masterName = optional($masterItem)->name ?? '-';

                            // Ambil nama alias dari GR, jika tidak ada, ambil dari PO, jika tidak ada, ambil Master
                            $specificName = optional($grItem)->item_name ?? optional($poItem)->item_name ?? $masterName;
                        @endphp
                        <tr class="border-bottom">
                            {{-- KOLOM 1: NAMA BARANG --}}
                            <td class="py-3 ps-4">
                                {{-- 🔥 MENAMPILKAN NAMA SPESIFIK & MASTER BERSUSUN 🔥 --}}
                                <h6 class="mb-0 fw-bold text-dark text-uppercase">{{ $specificName }}</h6>

                                @if(strtolower(trim($specificName)) !== strtolower(trim($masterName)))
                                    <div class="mt-1 mb-1 text-muted" style="font-size: 0.75rem;">
                                        <i class="bi bi-box me-1"></i>Master: {{ $masterName }}
                                    </div>
                                @endif

                                <div class="mt-1 text-muted small">{{ optional($masterItem)->code }}</div>
                            </td>

                            {{-- KOLOM 2: SUMBER REFERENSI (PO, GR, RTV) --}}
                            <td class="py-3">
                                <div class="gap-1 d-flex flex-column" style="font-size: 0.75rem;">

                                    {{-- Link ke PO --}}
                                    @if($poNum)
                                        <a href="{{ route('po.show', $poNum) }}" target="_blank" class="text-decoration-none text-primary fw-semibold" title="Lihat PO">
                                            <i class="bi bi-cart2 me-1"></i> {{ $poNum }}
                                        </a>
                                    @endif

                                    {{-- Link ke GR --}}
                                    @if($grNum)
                                        <a href="{{ route('gr.show', $grNum) }}" target="_blank" class="text-decoration-none text-success fw-semibold" title="Lihat Surat Jalan / GR">
                                            <i class="bi bi-box-arrow-in-down-left me-1"></i> {{ $grNum }}
                                        </a>
                                    @endif

                                    {{-- Label & Link RTV --}}
                                    @if($returQty > 0)
                                        <span class="mt-1 text-danger fw-bold" style="font-size: 0.7rem;">
                                            <i class="bi bi-arrow-return-left me-1"></i> Potong Retur: {{ $returQty }}
                                        </span>

                                        {{-- Munculkan Nomor RTV --}}
                                        @if(!empty($rtvDocs))
                                            @foreach($rtvDocs as $rtvNum)
                                                <a href="{{ route('rtv.show', $rtvNum) }}" target="_blank" class="text-decoration-none text-danger fw-semibold" title="Lihat Detail Retur">
                                                    <i class="bi bi-file-earmark-minus me-1"></i> {{ $rtvNum }}
                                                </a>
                                            @endforeach
                                        @endif
                                    @endif

                                </div>
                            </td>

                            {{-- KOLOM 3: QTY DITAGIH --}}
                            <td class="text-center fw-bold text-primary">
                                <span class="fs-6">{{ (float) $item->qty_invoiced }}</span><br>
                                <span class="text-muted fw-normal" style="font-size: 0.65rem; text-transform: uppercase;">
                                    {{ optional($poItem)->uom ?? 'Unit' }}
                                </span>
                            </td>

                            {{-- KOLOM 4: HARGA SATUAN --}}
                            <td class="text-end">
                                {{ number_format($item->price, 2, ',', '.') }}
                                @if($item->discount_amount > 0)
                                    <div class="text-danger small fw-semibold"><i class="bi bi-arrow-down-short"></i> Disc: {{ number_format($item->discount_amount, 2, ',', '.') }}</div>
                                @endif
                            </td>

                            {{-- KOLOM 5: SUBTOTAL --}}
                            <td class="text-end pe-4 fw-bold text-dark fs-6">
                                {{ number_format($item->subtotal, 2, ',', '.') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                        </table>
                    </div>
                </div>
        </div>

        {{-- KOLOM KANAN: RINGKASAN & PEMBAYARAN --}}
        <div class="col-lg-4">
            {{-- RINGKASAN BIAYA --}}
            <div class="mb-4 border-0 shadow-sm card rounded-4">
                <div class="py-3 bg-white border-bottom card-header">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-calculator me-2 text-primary"></i>Ringkasan Tagihan</h6>
                </div>
                <div class="p-4 card-body">
                    <div class="mb-2 d-flex justify-content-between small text-muted">
                        <span>Total Dasar (Gross)</span>
                        <span class="fw-bold text-dark">IDR {{ number_format($invoice->subtotal, 2, ',', '.') }}</span>
                    </div>
                    <div class="mb-2 d-flex justify-content-between small text-danger">
                        <span>Total Diskon Barang</span>
                        <span class="fw-bold">- IDR {{ number_format($invoice->item_discount_total, 2, ',', '.') }}</span>
                    </div>
                    <div class="mb-2 d-flex justify-content-between small text-danger">
                        <span>Diskon Global (PO)</span>
                        <span class="fw-bold">- IDR {{ number_format($invoice->global_discount_total, 2, ',', '.') }}</span>
                    </div>

                    @if($invoice->extra_discount_total > 0)
                    <div class="mb-2 d-flex justify-content-between small text-danger">
                        <span>Potongan Tambahan</span>
                        <span class="fw-bold">- IDR {{ number_format($invoice->extra_discount_total, 2, ',', '.') }}</span>
                    </div>
                    @endif

                    <div class="mb-2 d-flex justify-content-between small text-muted">
                        <span>Total Pajak (PPN)</span>
                        <span class="fw-bold text-dark">+ IDR {{ number_format($invoice->tax_amount, 2, ',', '.') }}</span>
                    </div>
                    <div class="mb-3 d-flex justify-content-between small text-muted">
                        <span>Biaya Tambahan (PO)</span>
                        <span class="fw-bold text-dark">+ IDR {{ number_format($invoice->charge_total, 2, ',', '.') }}</span>
                    </div>
                    <hr class="opacity-25 border-secondary">
                    <div class="mb-1 d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-uppercase" style="font-size: 0.9rem;">Grand Total</span>
                        <span class="fs-4 fw-bolder text-primary">IDR {{ number_format($invoice->grand_total, 2, ',', '.') }}</span>
                    </div>

                    @if($totalPaid > 0)
                    <div class="p-3 mt-4 border border-success-subtle bg-success-subtle bg-opacity-10 rounded-3">
                        <div class="mb-1 d-flex justify-content-between small">
                            <span class="text-success fw-bold">Telah Dibayar:</span>
                            <span class="text-success fw-bold">IDR {{ number_format($totalPaid, 2, ',', '.') }}</span>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span class="text-danger fw-bold">Sisa Tagihan:</span>
                            <span class="text-danger fw-bold">IDR {{ number_format($sisaTagihan, 2, ',', '.') }}</span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- RIWAYAT PEMBAYARAN --}}
            @if(!$isDraft)
            <div class="border-0 shadow-sm card rounded-4">
                <div class="py-3 bg-white border-bottom card-header">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history me-2 text-success"></i>Riwayat Pembayaran</h6>
                </div>
                <div class="p-0 card-body">
                    @if($invoice->payments->count() > 0)
                        <ul class="list-group list-group-flush rounded-bottom-4">
                            @foreach($invoice->payments as $pay)
                            <li class="p-3 list-group-item">
                                <div class="mb-1 d-flex justify-content-between align-items-start">
                                    <div class="fw-bold text-dark">{{ $pay->payment_number }}</div>
                                    <div class="badge bg-success">IDR {{ number_format($pay->amount, 0, ',', '.') }}</div>
                                </div>

                                {{-- Menampilkan Tanggal dan Nama Metode Pembayaran --}}
                                <div class="mb-1 text-muted small">
                                    <i class="bi bi-calendar-check me-1"></i>{{ \Carbon\Carbon::parse($pay->payment_date)->format('d M Y') }} |
                                    <span class="fw-bold">{{ strtoupper(optional($pay->paymentMethod)->name ?? $pay->payment_method ?? 'METODE LAINNYA') }}</span>
                                </div>

                                @if($pay->bank_name)
                                    <div class="mb-2 text-muted" style="font-size: 0.7rem;">Bank: {{ $pay->bank_name }} (Ref: {{ $pay->reference_number ?? '-' }})</div>
                                @endif

                                {{-- Menampilkan File Bukti Transfer Jika Ada --}}
                                @if($pay->attachments && $pay->attachments->count() > 0)
                                    <div class="flex-wrap gap-2 mt-2 d-flex">
                                        @foreach($pay->attachments as $file)
                                            <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="px-2 py-1 border badge bg-light text-primary text-decoration-none">
                                                <i class="bi bi-paperclip"></i> {{ Str::limit($file->file_name, 15) }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- 🔥 ACTION BUTTONS: CETAK MULTI-VERSION & BATALKAN 🔥 --}}
                                <div class="gap-2 pt-2 mt-3 border-top d-flex justify-content-end align-items-center">

                                    {{-- Dropdown Pilihan Cetak PDF --}}
                                    <div class="dropdown">
                                        <button class="py-1 btn btn-sm btn-outline-dark rounded-3 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.7rem;">
                                            <i class="bi bi-file-earmark-pdf-fill me-1"></i>Cetak PDF
                                        </button>
                                        <ul class="border-0 shadow dropdown-menu dropdown-menu-end" style="font-size: 0.75rem;">
                                            <li>
                                                <a class="py-2 dropdown-item" href="{{ route('vendor-invoices.vendor-payments.pdf-voucher', $pay->id) }}" target="_blank">
                                                    <i class="bi bi-file-text me-2 text-primary"></i>1. Hanya Bukti Pengeluaran Kas
                                                </a>
                                            </li>
                                            @if($pay->attachments && $pay->attachments->count() > 0)
                                            <li>
                                                <a class="py-2 dropdown-item" href="{{ route('vendor-invoices.vendor-payments.pdf-complete', $pay->id) }}" target="_blank">
                                                    <i class="bi bi-file-earmark-zip me-2 text-success"></i>2. Bukti Kas + Gabung Lampiran
                                                </a>
                                            </li>
                                            @else
                                            <li>
                                                <span class="py-2 cursor-not-allowed dropdown-item text-muted bg-light">
                                                    <i class="bi bi-exclamation-circle me-2"></i>2. Gabung Lampiran (Tidak ada file)
                                                </span>
                                            </li>
                                            @endif
                                        </ul>
                                    </div>

                                    {{-- Tombol Batalkan Pembayaran (SweetAlert UI) --}}
                                    <form action="{{ route('vendor-invoices.cancelPayment', $pay->id) }}" method="POST" class="m-0 form-cancel-payment">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="cancel_reason" class="cancel-reason-input">

                                        <button type="button" class="py-1 btn btn-sm btn-outline-danger rounded-3 btn-trigger-cancel" style="font-size: 0.7rem;">
                                            <i class="bi bi-x-circle-fill me-1"></i>Batalkan
                                        </button>
                                    </form>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="p-4 text-center text-muted small">
                            <i class="mb-2 opacity-50 bi bi-wallet2 fs-2 d-block"></i>
                            Belum ada pembayaran dicatat.
                        </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- MODAL BAYAR TAGIHAN --}}
@if($isPayable)
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="border-0 shadow-lg modal-content rounded-4">
            <div class="text-white modal-header bg-success border-bottom-0" style="border-radius: 16px 16px 0 0;">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-stack me-2"></i>Catat Pembayaran Keluar</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('vendor-invoices.storePayment', $invoice->invoice_number) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="p-4 modal-body bg-light">
                    <div class="p-3 mb-4 text-center bg-white border border-success-subtle rounded-3">
                        <div class="mb-1 small text-muted text-uppercase fw-bold">Sisa yang harus dibayar</div>
                        <h3 class="mb-0 fw-bolder text-danger">IDR {{ number_format($sisaTagihan, 2, ',', '.') }}</h3>
                    </div>

                    <div class="mb-3">
                        <label class="mb-1 fw-bold small text-muted">Tanggal Bayar *</label>
                        <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="mb-3 row g-3">
                        <div class="mb-3 col-md-6">
                            <label class="mb-1 fw-bold small text-muted">Metode Pembayaran <span class="text-danger">*</span></label>
                            <select name="payment_method" id="payment_method_select" class="form-select" required>
                                <option value="" data-require-ref="1">-- Pilih Metode --</option>
                                @foreach ($payment_method as $paymed)
                                    {{-- 🔥 SISIPKAN DATA REQUIRE_REFERENCE DI SINI 🔥 --}}
                                    <option value="{{ $paymed->name }}" data-require-ref="{{ $paymed->require_reference }}">
                                        {{ $paymed->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="mb-1 fw-bold small text-muted">Nominal Bayar (IDR) *</label>
                            <input type="number" name="amount" class="form-control fw-bold text-primary" value="{{ $sisaTagihan }}" max="{{ $sisaTagihan }}" step="0.01" required>
                        </div>
                    </div>
                    <div class="mb-3 row g-3">
                        <div class="col-md-6">
                            <label class="mb-1 fw-bold small text-muted">Bank Asal (Opsional)</label>
                            <input type="text" name="bank_name" class="form-control" placeholder="Cth: BCA Pusat">
                        </div>
                        <div class="mb-3 col-md-6" id="reference_wrapper">
                            <label class="mb-1 fw-bold small text-muted">Nomor Referensi / Bank</label>
                            <input type="text" name="reference_number" id="reference_input" class="form-control" placeholder="Cth: BCA / TRX-123456...">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="mb-2 fw-bold small text-muted">Upload Bukti Bayar / Dokumen (Opsional)</label>

                        {{-- Wadah untuk baris-baris upload --}}
                        <div id="attachment-container">
                            <div class="mb-2 input-group attachment-row">
                                <span class="input-group-text bg-light"><i class="bi bi-paperclip"></i></span>
                                <input type="file" name="attachments[]" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                                <button class="btn btn-outline-danger btn-remove-file" type="button" onclick="this.parentElement.remove()" title="Hapus baris ini">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Tombol sakti penambah baris --}}
                        <button type="button" class="mt-1 btn btn-sm btn-outline-primary rounded-pill fw-bold" onclick="addAttachmentRow()">
                            <i class="bi bi-plus-circle me-1"></i> Tambah File dari Folder Lain
                        </button>
                    </div>
                    <div>
                        <label class="mb-1 fw-bold small text-muted">Catatan Tambahan</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan pembayaran..."></textarea>
                    </div>
                </div>

                <div class="pt-0 modal-footer border-top-0 bg-light" style="border-radius: 0 0 16px 16px;">
                    <button type="button" class="px-4 btn btn-secondary rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="px-4 btn btn-success rounded-pill fw-bold"><i class="bi bi-save me-1"></i> Simpan Pembayaran</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // ---------------------------------------------------------
    // 1. FUNGSI UNTUK MODAL PEMBAYARAN (TOMBOL HIJAU)
    // ---------------------------------------------------------
    function addAttachmentRow() {
        const container = document.getElementById('attachment-container');
        const row = document.createElement('div');
        row.className = 'input-group mb-2 attachment-row';
        row.innerHTML = `
            <span class="input-group-text bg-light"><i class="bi bi-paperclip"></i></span>
            <input type="file" name="attachments[]" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
            <button class="btn btn-outline-danger btn-remove-file" type="button" onclick="this.parentElement.remove()" title="Hapus baris ini">
                <i class="bi bi-trash"></i>
            </button>
        `;
        container.appendChild(row);
    }

    // ---------------------------------------------------------
    // 2. FUNGSI UNTUK LACI DOKUMEN INVOICE (TOMBOL HITAM)
    // ---------------------------------------------------------
    function addInvoiceDocRow() {
        const container = document.getElementById('invoice-attachment-container');
        const template = `
            <div class="mb-2 row g-2 align-items-end invoice-attachment-row">
                <div class="col-md-5">
                    <input list="documentTypeOptions" name="document_types[]" class="form-control form-control-sm" placeholder="Ketik atau pilih jenis..." required autocomplete="off">
                </div>
                <div class="col-md-6">
                    <input type="file" name="attachment_files[]" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" required>
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeInvoiceDocRow(this)" title="Hapus"><i class="bi bi-trash"></i></button>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', template);
    }

    function removeInvoiceDocRow(btn) {
        let rows = document.querySelectorAll('.invoice-attachment-row');
        if(rows.length > 1) {
            btn.closest('.invoice-attachment-row').remove();
        } else {
            let row = btn.closest('.invoice-attachment-row');
            row.querySelector('input[name="document_types[]"]').value = '';
            row.querySelector('input[name="attachment_files[]"]').value = '';
        }
    }

    // 🔥 LOGIKA POP-UP HAPUS DOKUMEN LAMPIRAN (SWEETALERT) 🔥
    document.querySelectorAll('.btn-delete-doc').forEach(button => {
        button.addEventListener('click', function() {
            let form = this.closest('.form-delete-doc');

            Swal.fire({
                title: 'Hapus Dokumen?',
                text: "File lampiran ini akan dihapus secara permanen dari sistem dan tidak dapat dikembalikan.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545', // Warna merah danger
                cancelButtonColor: '#6c757d', // Warna abu-abu secondary
                confirmButtonText: '<i class="bi bi-trash"></i> Ya, Hapus File!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Tampilkan animasi loading saat menghapus
                    Swal.fire({
                        title: 'Menghapus Dokumen...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });
                    form.submit();
                }
            });
        });
    });


    // 🔥 LOGIKA POP-UP ALASAN PEMBATALAN PEMBAYARAN 🔥
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.btn-trigger-cancel').forEach(button => {
            button.addEventListener('click', function() {
                let form = this.closest('.form-cancel-payment');
                let reasonInput = form.querySelector('.cancel-reason-input');

                Swal.fire({
                    title: 'Batalkan Pembayaran?',
                    text: 'Uang akan ditarik dari sistem. Silakan masukkan alasan pembatalan ini untuk keperluan Audit.',
                    icon: 'warning',
                    input: 'textarea', // Memunculkan kotak teks
                    inputPlaceholder: 'Cth: Salah input nominal, harusnya 10 juta...',
                    inputAttributes: {
                        'aria-label': 'Alasan Pembatalan'
                    },
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="bi bi-trash"></i> Ya, Batalkan!',
                    cancelButtonText: 'Tutup',
                    preConfirm: (text) => {
                        if (!text || text.trim() === '') {
                            Swal.showValidationMessage('Alasan pembatalan WAJIB diisi!');
                            return false;
                        }
                        return text;
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Masukkan alasan ke input tersembunyi
                        reasonInput.value = result.value;

                        // Tampilkan loading lalu submit form
                        Swal.fire({
                            title: 'Membatalkan Transaksi...',
                            allowOutsideClick: false,
                            didOpen: () => Swal.showLoading()
                        });
                        form.submit();
                    }
                });
            });
        });
    });


    // 🔥 LOGIKA POP-UP ALASAN PEMBATALAN TAGIHAN (INVOICE) 🔥
    function confirmVoidInvoice() {
        Swal.fire({
            title: 'Batalkan Tagihan Ini?',
            text: 'Tindakan ini permanen. Dokumen GR akan dikembalikan ke antrean siap tagih. Masukkan alasan pembatalan:',
            icon: 'error',
            input: 'textarea',
            inputPlaceholder: 'Cth: Salah input item, vendor salah kirim invoice fisik...',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Batalkan Tagihan!',
            cancelButtonText: 'Tutup',
            preConfirm: (text) => {
                if (!text || text.trim() === '') {
                    Swal.showValidationMessage('Alasan pembatalan WAJIB diisi!');
                    return false;
                }
                return text;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('invoice-cancel-reason').value = result.value;
                Swal.fire({
                    title: 'Menghapus Tagihan...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
                document.getElementById('form-void-invoice').submit();
            }
        });
    }

    $(document).ready(function() {
        // 🔥 DETEKSI PERUBAHAN PADA DROPDOWN METODE PEMBAYARAN 🔥
        $('#payment_method_select').on('change', function() {

            // Tangkap nilai require_reference (0 atau 1) dari opsi yang sedang dipilih
            let requireRef = $(this).find(':selected').data('require-ref');

            // Jika metode pembayaran TIDAK butuh referensi (Contoh: Kas Tunai = 0)
            if (requireRef == 0) {
                $('#reference_wrapper').slideUp(); // Animasi menyembunyikan kolom
                $('#reference_input').val('');     // Kosongkan isinya otomatis agar tidak masuk ke database
                $('#reference_input').removeAttr('required'); // Hapus atribut wajib isi (jika ada)
            }
            // Jika butuh referensi (Contoh: Transfer = 1)
            else {
                $('#reference_wrapper').slideDown(); // Animasi memunculkan kolom
                // $('#reference_input').attr('required', true); // Aktifkan jika Anda ingin referensi wajib diisi
            }
        });
    });

</script>
@endpush
