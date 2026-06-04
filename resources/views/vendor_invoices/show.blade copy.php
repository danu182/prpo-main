@extends('layouts.app')

@section('content')
<div class="mb-4 d-flex justify-content-between align-items-center">
    <div>
        <h3 class="mb-0">Detail Tagihan Vendor (Invoice)</h3>
        <p class="text-muted">Kalkulasi otomatis berdasarkan PO dan Penerimaan Barang (GR)</p>
    </div>
    <div class="gap-2 d-flex">
        {{-- TOMBOL KEMBALI --}}
        <a href="{{ route('vendor-invoices.index') }}" class="px-4 shadow-sm btn btn-outline-secondary rounded-pill fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>

        {{-- TOMBOL LENGKAPI & POSTING (Hanya muncul jika DRAFT) --}}
        @if(optional($invoice->status)->slug == 'draft')
            <button type="button" class="px-4 shadow-sm btn btn-primary rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#modalLengkapiTagihan">
                <i class="bi bi-pencil-square me-1"></i> Lengkapi & Posting
            </button>
        @endif

        {{-- TOMBOL BAYAR (Hanya muncul jika POSTED atau PARTIAL) --}}
        @if(in_array(optional($invoice->status)->slug, ['posted', 'partial']))
            <button type="button" class="px-4 shadow-sm btn btn-success rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#modalBayarTagihan">
                <i class="bi bi-wallet2 me-1"></i> Bayar Tagihan
            </button>
        @endif
    </div>
</div>

{{-- ALERT NOTIFIKASI --}}
@if(session('success'))
    <div class="shadow-sm alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="shadow-sm alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    {{-- KOLOM KIRI (INFO TAGIHAN & RINCIAN BARANG) --}}
    <div class="col-md-8">

        {{-- CARD: INFORMASI TAGIHAN --}}
        <div class="mb-4 shadow-sm card border-primary" style="border: 2px solid #0d6efd;">
            <div class="py-3 bg-white card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-file-earmark-text me-2"></i>Informasi Tagihan</h6>
                <span class="badge bg-{{ optional($invoice->status)->color }} fs-6 px-3">
                    {{ optional($invoice->status)->name }}
                </span>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-sm-6">
                        <small class="mb-1 text-muted d-block">Nomor Sistem (Auto)</small>
                        <h5 class="fw-bold text-dark">{{ $invoice->invoice_number }}</h5>
                    </div>
                    <div class="col-sm-6 text-end">
                        <small class="mb-1 text-muted d-block">Nomor Faktur Fisik Vendor</small>
                        <h6 class="fw-bold text-dark">{{ $invoice->vendor_invoice_number ?? 'Belum diinput Keuangan' }}</h6>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-4">
                        <small class="mb-1 text-muted d-block">Referensi PO</small>
                        <a href="{{ route('po.show', $invoice->purchase_order_id) }}" class="text-decoration-none fw-bold">
                            <i class="bi bi-cart2 me-1"></i> {{ optional($invoice->purchaseOrder)->po_number }}
                        </a>
                    </div>
                    <div class="col-sm-4">
                        <small class="mb-1 text-muted d-block">Referensi GR (Penerimaan)</small>
                        <span class="fw-bold text-dark">
                            <i class="bi bi-box-seam me-1"></i>
                            {{ optional($invoice->goodsReceipt)->gr_number ?? 'Banyak GR (Gabungan)' }}
                        </span>
                    </div>
                    <div class="col-sm-4 text-end">
                        <small class="mb-1 text-muted d-block">Tanggal Tagihan Dibuat</small>
                        <span class="fw-bold text-dark">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- CARD: RINCIAN KALKULASI BARANG --}}
        <div class="mb-4 shadow-sm card">
            <div class="py-3 bg-white card-header">
                <h6 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2"></i>Rincian Kalkulasi Barang</h6>
            </div>
            <div class="p-0 card-body">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Barang</th>
                                <th class="text-center">Qty Terima (GR)</th>
                                <th class="text-center">Harga Satuan (PO)</th>
                                <th class="text-center">Diskon / Pajak</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->items as $item)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ optional($item->item)->name }}</div>
                                </td>
                                <td class="text-center fw-bold text-primary">{{ (float) $item->qty_invoiced }}</td>
                                <td class="text-center">IDR {{ number_format($item->price, 2, '.', ',') }}</td>
                                <td class="text-center">
                                    <div class="small text-danger">Disc: {{ number_format($item->discount_amount, 2, '.', ',') }}</div>
                                    <div class="small text-info">Tax: {{ number_format($item->tax_amount, 2, '.', ',') }}</div>
                                </td>
                                <td class="text-end fw-bold">IDR {{ number_format($item->subtotal, 2, '.', ',') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="3" class="border-0"></td>
                                <th class="py-1 text-end text-muted border-0">Subtotal Gross</th>
                                <th class="py-1 text-end border-0">IDR {{ number_format($invoice->subtotal, 2, '.', ',') }}</th>
                            </tr>
                            <tr>
                                <td colspan="3" class="border-0"></td>
                                <th class="py-1 text-end text-danger border-0">Diskon Komersial</th>
                                <th class="py-1 text-end text-danger border-0">- IDR {{ number_format($invoice->item_discount_total, 2, '.', ',') }}</th>
                            </tr>
                            <tr>
                                <td colspan="3" class="border-0"></td>
                                <th class="py-1 text-end text-primary border-0">Total Pajak (VAT/PPn)</th>
                                <th class="py-1 text-end text-primary border-0">+ IDR {{ number_format($invoice->tax_amount, 2, '.', ',') }}</th>
                            </tr>

                            {{-- GLOBAL CHARGES & DISCOUNTS --}}
                            @php
                                $globalCharges = \DB::table('purchase_order_charges')->where('purchase_order_id', $invoice->purchase_order_id)->get();
                                $globalDiscounts = \DB::table('purchase_order_discounts')->where('purchase_order_id', $invoice->purchase_order_id)->get();
                                $poSubtotal = optional($invoice->purchaseOrder)->subtotal ?? 1;
                                $proporsi = $invoice->subtotal / ($poSubtotal > 0 ? $poSubtotal : 1);
                            @endphp

                            @foreach($globalCharges as $charge)
                            <tr>
                                <td colspan="3" class="border-0"></td>
                                <th class="py-1 text-end text-muted border-0 fw-normal"><i class="bi bi-arrow-return-right"></i> Biaya: {{ $charge->name }}</th>
                                <th class="py-1 text-end text-muted border-0">+ IDR {{ number_format($charge->amount * $proporsi, 2, '.', ',') }}</th>
                            </tr>
                            @endforeach

                            @foreach($globalDiscounts as $disc)
                            <tr>
                                <td colspan="3" class="border-0"></td>
                                <th class="py-1 text-end text-muted border-0 fw-normal"><i class="bi bi-arrow-return-right"></i> Potongan: {{ $disc->name }}</th>
                                <th class="py-1 text-end text-danger border-0">- IDR {{ number_format($disc->amount * $proporsi, 2, '.', ',') }}</th>
                            </tr>
                            @endforeach

                            <tr>
                                <td colspan="3" class="border-0 border-top border-dark"></td>
                                <th class="py-3 text-end fs-5 border-top border-dark">GRAND TOTAL :</th>
                                <th class="py-3 text-end fs-5 text-success fw-bold border-top border-dark">IDR {{ number_format($invoice->grand_total, 2, '.', ',') }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- CARD: RIWAYAT PEMBAYARAN --}}
        @php
            $totalPaid = optional($invoice->payments)->sum('amount') ?? 0;
            $sisaTagihan = $invoice->grand_total - $totalPaid;
        @endphp
        <div class="mb-4 shadow-sm card border-top border-4 border-success">
            <div class="py-3 bg-white card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-cash-stack me-2 text-success"></i>Riwayat Pembayaran (Payment)</h6>
                <div class="text-end">
                    <span class="badge bg-success fs-6">Terbayar: IDR {{ number_format($totalPaid, 2, '.', ',') }}</span>
                    <span class="badge bg-danger fs-6 ms-2">Sisa: IDR {{ number_format($sisaTagihan, 2, '.', ',') }}</span>
                </div>
            </div>
            <div class="p-0 card-body">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle table-hover">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Tgl Bayar</th>
                                <th>No. Referensi / Kasir</th>
                                <th>Metode Bayar</th>
                                <th class="text-end">Nominal</th>
                                <th class="text-center pe-3">Bukti</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoice->payments ?? [] as $pay)
                            <tr>
                                <td class="ps-3">{{ \Carbon\Carbon::parse($pay->payment_date)->format('d M Y') }}</td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $pay->payment_number }}</div>
                                    <div class="small text-muted">{{ $pay->bank_name ?? '-' }} | Ref: {{ $pay->reference_number ?? '-' }}</div>
                                </td>
                                <td>{{ $pay->payment_method }}</td>
                                <td class="text-end fw-bold text-success">IDR {{ number_format($pay->amount, 2, '.', ',') }}</td>
                                <td class="text-center pe-3">
                                    @if($pay->proof_file)
                                        <a href="{{ asset('storage/'.$pay->proof_file) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Lihat Bukti">
                                            <i class="bi bi-file-earmark-image"></i>
                                        </a>
                                    @else
                                        <span class="small text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-muted">Belum ada pembayaran untuk tagihan ini.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    {{-- KOLOM KANAN (SIDEBAR INFO VENDOR & BILL TO) --}}
    <div class="col-md-4">
        <div class="mb-4 shadow-sm card">
            <div class="py-3 bg-white card-header">
                <h6 class="mb-0 fw-bold"><i class="bi bi-shop me-2"></i>Ditagihkan Oleh (Vendor)</h6>
            </div>
            <div class="card-body">
                <h6 class="mb-1 fw-bold text-primary">{{ optional($invoice->vendor)->name }}</h6>
                <p class="mb-3 small text-muted"><i class="bi bi-geo-alt me-1"></i> {{ optional($invoice->vendor)->address ?? '-' }}</p>

                <div class="small">
                    <div class="mb-1"><span class="text-muted">Kontak:</span> {{ optional($invoice->vendor)->contact_person ?? '-' }}</div>
                    <div class="mb-1"><span class="text-muted">Telepon:</span> {{ optional($invoice->vendor)->phone ?? '-' }}</div>
                    <div><span class="text-muted">Email:</span> {{ optional($invoice->vendor)->email ?? '-' }}</div>
                </div>
            </div>
        </div>

        <div class="mb-4 shadow-sm card">
            <div class="py-3 bg-white card-header">
                <h6 class="mb-0 fw-bold"><i class="bi bi-building me-2"></i>Dibayar Oleh (Bill To)</h6>
            </div>
            <div class="card-body">
                <h6 class="mb-1 fw-bold text-dark">{{ optional($invoice->company)->name }}</h6>
                <p class="mb-3 small text-muted">{{ optional($invoice->company)->address ?? '-' }}</p>

                <hr>
                <div class="small text-muted">
                    <span class="mb-1 fw-bold text-dark d-block">Catatan Sistem:</span>
                    <em>{{ $invoice->notes ?? 'Tidak ada catatan.' }}</em>
                </div>
                <div class="mt-3 small text-muted">
                    Dibuat oleh: {{ optional($invoice->creator)->name ?? 'System' }} pada {{ \Carbon\Carbon::parse($invoice->created_at)->format('d M Y, H:i') }}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ========================================================== --}}
{{-- MODALS --}}
{{-- ========================================================== --}}

{{-- MODAL LENGKAPI & POSTING TAGIHAN (DRAFT) --}}
@if(optional($invoice->status)->slug == 'draft')
<div class="modal fade" id="modalLengkapiTagihan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('vendor-invoices.update', $invoice->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="text-white modal-header bg-primary">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Lengkapi & Posting Tagihan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small">
                        Pastikan angka rincian tagihan sudah sesuai dengan faktur fisik dari Vendor. Jika sudah di-Posting, tagihan akan dikunci dan siap dibayar.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nomor Faktur Fisik Vendor <span class="text-danger">*</span></label>
                        <input type="text" name="vendor_invoice_number" class="form-control" value="{{ $invoice->vendor_invoice_number }}" required placeholder="Cth: INV/VND/2026/01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal Jatuh Tempo (Due Date) <span class="text-danger">*</span></label>
                        <input type="date" name="due_date" class="form-control" value="{{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('Y-m-d') : '' }}" required>
                    </div>
                </div>
                <div class="bg-light modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    {{-- name="post_invoice" memicu Controller untuk merubah status dari DRAFT menjadi POSTED --}}
                    <button type="submit" name="post_invoice" value="1" class="btn btn-primary fw-bold"><i class="bi bi-send-check me-1"></i> Simpan & Posting</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- MODAL BAYAR TAGIHAN (POSTED / PARTIAL) --}}
@if(in_array(optional($invoice->status)->slug, ['posted', 'partial']))
<div class="modal fade" id="modalBayarTagihan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('vendor-invoices.storePayment', $invoice->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="text-white modal-header bg-success">
                    <h5 class="modal-title"><i class="bi bi-wallet2 me-2"></i> Form Pembayaran Vendor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 alert alert-info">
                        Sisa Tagihan yang harus dibayar: <br>
                        <strong class="fs-4">IDR {{ number_format($sisaTagihan, 2, '.', ',') }}</strong>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tanggal Bayar <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="row">
                        <div class="mb-3 col-md-6">
                            <label class="form-label">Metode Bayar <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select" required>
                                <option value="Transfer Bank">Transfer Bank</option>
                                <option value="Cek / Bilyet Giro">Cek / Bilyet Giro</option>
                                <option value="Tunai / Cash">Tunai / Cash</option>
                            </select>
                        </div>
                        <div class="mb-3 col-md-6">
                            <label class="form-label">Nama Bank Pengirim</label>
                            <input type="text" name="bank_name" class="form-control" placeholder="Cth: BCA Perusahaan">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No. Referensi / Surat</label>
                        <input type="text" name="reference_number" class="form-control" placeholder="Cth: TRX-998123">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nominal Dibayar (IDR) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control text-end fw-bold text-success fs-5" max="{{ $sisaTagihan }}" value="{{ $sisaTagihan }}" step="0.01" required>
                        <small class="text-muted">Bisa diubah jika hanya membayar DP / Cicilan sebagian.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Upload Bukti Transfer (Opsional)</label>
                        <input type="file" name="proof_file" class="form-control" accept="image/jpeg,image/png,application/pdf">
                        <small class="text-muted">Max 2MB. Format: JPG, PNG, PDF</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan Tambahan</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan..."></textarea>
                    </div>
                </div>
                <div class="bg-light modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success fw-bold"><i class="bi bi-save me-1"></i> Simpan Pembayaran</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection
