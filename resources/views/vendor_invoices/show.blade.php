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

            <button class="shadow-sm btn btn-dark rounded-pill fw-bold" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Cetak
            </button>
            
            @if($isPayable)
                <button type="button" class="shadow-sm btn btn-success rounded-pill fw-bold px-4" data-bs-toggle="modal" data-bs-target="#paymentModal">
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
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="postInvoiceCheck" name="post_invoice" value="1">
                                    <label class="fw-bold form-check-label text-dark" for="postInvoiceCheck">Posting Tagihan Ini (Kunci & Siap Dibayar)</label>
                                </div>
                                <div class="small text-muted mb-3"><i class="bi bi-info-circle me-1"></i>Jika di-posting, tagihan tidak dapat diubah lagi angkanya dan akan masuk ke daftar hutang yang siap dibayar.</div>
                                <button type="submit" class="shadow-sm btn btn-primary fw-bold"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
                            </div>
                        @endif
                    </form>
                </div>
            </div>

            {{-- LACI DOKUMEN FAKTUR PAJAK / GARANSI --}}
            @if(!$isDraft)
                <div class="mb-4 border-0 shadow-sm card rounded-4">
                    <div class="py-3 bg-white border-bottom card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-folder2-open me-2 text-warning"></i>Laci Dokumen Invoice</h6>
                        <span class="badge bg-light text-dark border"><i class="bi bi-info-circle me-1"></i>Bisa diupload kapan saja</span>
                    </div>
                    <div class="p-4 card-body">
                        
                        {{-- Form Upload Multi File --}}
                        <form action="{{ route('vendor-invoices.uploadAttachment', $invoice->invoice_number) }}" method="POST" enctype="multipart/form-data" class="mb-4">
                            @csrf
                            
                            {{-- Wadah Baris --}}
                            <div id="invoice-attachment-container">
                                <div class="row g-2 align-items-end mb-2 invoice-attachment-row">
                                    <div class="col-md-5">
                                        <label class="small fw-bold text-muted mb-1">Jenis Dokumen</label>
                                        <input list="documentTypeOptions" name="document_types[]" class="form-control form-control-sm" placeholder="Ketik atau pilih jenis..." required autocomplete="off">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="small fw-bold text-muted mb-1">Pilih File (PDF/JPG/PNG)</label>
                                        <input type="file" name="attachment_files[]" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" required>
                                    </div>
                                    <div class="col-md-1 text-end">
                                        {{-- Tombol hapus baris --}}
                                        <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeInvoiceDocRow(this)" title="Hapus"><i class="bi bi-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                            
                            {{-- Tombol Aksi Bawah --}}
                            <div class="d-flex justify-content-between mt-3">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill fw-bold px-3" onclick="addInvoiceDocRow()">
                                    <i class="bi bi-plus-circle me-1"></i> Tambah File
                                </button>
                                <button type="submit" class="btn btn-sm btn-dark rounded-pill fw-bold px-4">
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
                                <table class="table table-sm table-hover align-middle border mb-0">
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
                                                <form action="{{ route('vendor-invoices.deleteAttachment', $doc->id) }}" method="POST" onsubmit="return confirm('Hapus dokumen ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Hapus"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center text-muted small p-3 bg-light rounded border border-dashed">
                                Belum ada dokumen yang dilampirkan.
                            </div>
                        @endif
                    </div>
                </div>
            @else
                {{-- JIKA MASIH DRAFT: GEMBOK LACI! --}}
                <div class="mb-4 border-0 shadow-sm card rounded-4 opacity-75 bg-light">
                    <div class="py-3 bg-transparent border-bottom card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold text-muted"><i class="bi bi-folder-x me-2"></i>Laci Dokumen Terkunci</h6>
                    </div>
                    <div class="p-4 card-body text-center">
                        <i class="bi bi-lock-fill text-muted" style="font-size: 2rem;"></i>
                        <p class="mb-0 mt-2 text-muted small fw-bold">Selesaikan dan Posting tagihan (di kotak atas) terlebih dahulu untuk membuka Laci Dokumen.</p>
                    </div>
                </div>
            @endif
            
            {{-- TABEL RINCIAN BARANG --}}
            <div class="mb-4 border-0 shadow-sm card rounded-4">
                <div class="py-3 bg-white border-bottom card-header">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-columns me-2 text-primary"></i>Rincian Barang Ditagih</h6>
                </div>
                <div class="p-0 card-body table-responsive">
                    <table class="table mb-0 align-middle table-hover table-items">
                        <thead>
                            <tr>
                                <th class="py-3 ps-4">Barang</th>
                                <th class="py-3 text-center">Qty Ditagih</th>
                                <th class="py-3 text-end">Harga Satuan</th>
                                <th class="py-3 text-end pe-4">Subtotal Baris</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->items as $item)
                            <tr>
                                <td class="py-3 ps-4">
                                    <div class="fw-bold text-dark">{{ optional($item->item)->name }}</div>
                                    <div class="text-muted small">{{ optional($item->item)->code }}</div>
                                </td>
                                <td class="text-center fw-bold text-primary">
                                    {{ (float) $item->qty_invoiced }}<br>
                                    <span class="text-muted fw-normal" style="font-size: 0.65rem;">{{ optional($item->goodsReceiptItem->purchaseOrderItem)->uom }}</span>
                                </td>
                                <td class="text-end">
                                    {{ number_format($item->price, 2, ',', '.') }}
                                    @if($item->discount_amount > 0)
                                        <div class="text-danger small"><i class="bi bi-arrow-down-short"></i> Disc: {{ number_format($item->discount_amount, 2, ',', '.') }}</div>
                                    @endif
                                </td>
                                <td class="text-end pe-4 fw-bold text-dark">
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
                    <div class="d-flex justify-content-between mb-2 small text-muted">
                        <span>Total Dasar (Gross)</span>
                        <span class="fw-bold text-dark">IDR {{ number_format($invoice->subtotal, 2, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small text-danger">
                        <span>Total Diskon Barang</span>
                        <span class="fw-bold">- IDR {{ number_format($invoice->item_discount_total, 2, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 small text-danger">
                        <span>Diskon Global (PO)</span>
                        <span class="fw-bold">- IDR {{ number_format($invoice->global_discount_total, 2, ',', '.') }}</span>
                    </div>

                    @if($invoice->extra_discount_total > 0)
                    <div class="d-flex justify-content-between mb-2 small text-danger">
                        <span>Potongan Tambahan</span>
                        <span class="fw-bold">- IDR {{ number_format($invoice->extra_discount_total, 2, ',', '.') }}</span>
                    </div>
                    @endif

                    <div class="d-flex justify-content-between mb-2 small text-muted">
                        <span>Total Pajak (PPN)</span>
                        <span class="fw-bold text-dark">+ IDR {{ number_format($invoice->tax_amount, 2, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 small text-muted">
                        <span>Biaya Tambahan (PO)</span>
                        <span class="fw-bold text-dark">+ IDR {{ number_format($invoice->charge_total, 2, ',', '.') }}</span>
                    </div>
                    <hr class="border-secondary opacity-25">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-bold text-uppercase" style="font-size: 0.9rem;">Grand Total</span>
                        <span class="fs-4 fw-bolder text-primary">IDR {{ number_format($invoice->grand_total, 2, ',', '.') }}</span>
                    </div>
                    
                    @if($totalPaid > 0)
                    <div class="p-3 mt-4 border border-success-subtle bg-success-subtle bg-opacity-10 rounded-3">
                        <div class="d-flex justify-content-between mb-1 small">
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
                            <li class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div class="fw-bold text-dark">{{ $pay->payment_number }}</div>
                                    <div class="badge bg-success">IDR {{ number_format($pay->amount, 0, ',', '.') }}</div>
                                </div>
                                <div class="text-muted small mb-1"><i class="bi bi-calendar-check me-1"></i>{{ \Carbon\Carbon::parse($pay->payment_date)->format('d M Y') }} | {{ strtoupper($pay->payment_method) }}</div>
                                @if($pay->bank_name)
                                    <div class="text-muted mb-2" style="font-size: 0.7rem;">Bank: {{ $pay->bank_name }} (Ref: {{ $pay->reference_number }})</div>
                                @endif
                                
                                {{-- Menampilkan File Bukti Transfer Jika Ada --}}
                                @if($pay->attachments && $pay->attachments->count() > 0)
                                    <div class="mt-2 d-flex flex-wrap gap-2">
                                        @foreach($pay->attachments as $file)
                                            <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="badge bg-light text-primary border text-decoration-none py-1 px-2">
                                                <i class="bi bi-paperclip"></i> {{ Str::limit($file->file_name, 15) }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- 🔥 Tombol Batalkan Pembayaran (SweetAlert UI) 🔥 --}}
                                <div class="mt-3 text-end border-top pt-2">
                                    <form action="{{ route('vendor-invoices.cancelPayment', $pay->id) }}" method="POST" class="form-cancel-payment">
                                        @csrf
                                        @method('DELETE')
                                        {{-- Input tersembunyi untuk menyimpan alasan dari pop-up --}}
                                        <input type="hidden" name="cancel_reason" class="cancel-reason-input">
                                        
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-3 py-1 btn-trigger-cancel" style="font-size: 0.7rem;">
                                            <i class="bi bi-x-circle-fill me-1"></i>Batalkan Pembayaran
                                        </button>
                                    </form>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="p-4 text-center text-muted small">
                            <i class="bi bi-wallet2 fs-2 d-block mb-2 opacity-50"></i>
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
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-success text-white border-bottom-0" style="border-radius: 16px 16px 0 0;">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-stack me-2"></i>Catat Pembayaran Keluar</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('vendor-invoices.storePayment', $invoice->invoice_number) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4 bg-light">
                    <div class="p-3 mb-4 text-center border border-success-subtle bg-white rounded-3">
                        <div class="small text-muted text-uppercase fw-bold mb-1">Sisa yang harus dibayar</div>
                        <h3 class="fw-bolder text-danger mb-0">IDR {{ number_format($sisaTagihan, 2, ',', '.') }}</h3>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold small text-muted mb-1">Tanggal Bayar *</label>
                        <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="fw-bold small text-muted mb-1">Metode *</label>
                            <select name="payment_method" class="form-select" required>
                                <option value="transfer">Transfer Bank</option>
                                <option value="cash">Tunai (Cash)</option>
                                <option value="giro">Cek / Giro</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold small text-muted mb-1">Nominal Bayar (IDR) *</label>
                            <input type="number" name="amount" class="form-control fw-bold text-primary" value="{{ $sisaTagihan }}" max="{{ $sisaTagihan }}" step="0.01" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="fw-bold small text-muted mb-1">Bank Asal (Opsional)</label>
                            <input type="text" name="bank_name" class="form-control" placeholder="Cth: BCA Pusat">
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold small text-muted mb-1">No. Referensi (Opsional)</label>
                            <input type="text" name="reference_number" class="form-control" placeholder="No. Transaksi / Giro">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold small text-muted mb-2">Upload Bukti Bayar / Dokumen (Opsional)</label>
                        
                        {{-- Wadah untuk baris-baris upload --}}
                        <div id="attachment-container">
                            <div class="input-group mb-2 attachment-row">
                                <span class="input-group-text bg-light"><i class="bi bi-paperclip"></i></span>
                                <input type="file" name="attachments[]" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                                <button class="btn btn-outline-danger btn-remove-file" type="button" onclick="this.parentElement.remove()" title="Hapus baris ini">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Tombol sakti penambah baris --}}
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill fw-bold mt-1" onclick="addAttachmentRow()">
                            <i class="bi bi-plus-circle me-1"></i> Tambah File dari Folder Lain
                        </button>
                    </div>
                    <div>
                        <label class="fw-bold small text-muted mb-1">Catatan Tambahan</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan pembayaran..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer border-top-0 pt-0 bg-light" style="border-radius: 0 0 16px 16px;">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success rounded-pill fw-bold px-4"><i class="bi bi-save me-1"></i> Simpan Pembayaran</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
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
            <div class="row g-2 align-items-end mb-2 invoice-attachment-row">
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

</script>
@endpush