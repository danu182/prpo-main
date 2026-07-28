@extends('layouts.app')

@push('css')
<style>
    .detail-label { font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #6c757d; margin-bottom: 0.2rem; }
    .detail-value { font-size: 1rem; font-weight: 500; color: #212529; margin-bottom: 1rem; }
    .card-header-custom { background: linear-gradient(135deg, #0dcaf0 0%, #0d6efd 100%); color: white; }
    .is-void-bg { background: linear-gradient(135deg, #dc3545 0%, #a71d2a 100%) !important; color: white !important; }
    .text-strike { text-decoration: line-through; opacity: 0.6; }
</style>
@endpush

@section('content')
<div class="px-0 container-fluid">
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <a href="{{ route('asset-capitalizations.index') }}" class="border shadow-sm btn btn-light rounded-pill fw-bold text-secondary">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
        </a>
    </div>

    @php
        // Deteksi apakah aset ini sudah dibatalkan dari catatan sistemnya
        $isVoid = str_contains($asset->notes, '[DIBATALKAN');
    @endphp

    @if(session('success'))
        <div class="shadow-sm alert alert-success fw-bold rounded-3"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="shadow-sm alert alert-danger fw-bold rounded-3"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}</div>
    @endif

    {{-- BANNER PERINGATAN JIKA ASET BATAL --}}
    @if($isVoid)
    <div class="mb-4 border-0 shadow-sm alert alert-danger fw-bold rounded-4 d-flex align-items-center">
        <i class="bi bi-exclamation-octagon-fill fs-3 me-3"></i>
        <div>
            <div class="fs-5">ASET TELAH DIBATALKAN (VOID)</div>
            <div class="small fw-normal">Data ini hanya sebagai riwayat. Serial Number telah dilepas dan stok fisik telah dikembalikan ke Gudang.</div>
        </div>
    </div>
    @endif

    <div class="border-0 shadow-sm card rounded-4">
        <div class="card-header p-4 border-0 rounded-top-4 d-flex justify-content-between align-items-center {{ $isVoid ? 'is-void-bg' : 'card-header-custom' }}">
            <div>
                <h4 class="mb-1 fw-bold"><i class="bi bi-pc-display-horizontal me-2"></i> Detail Aset Tetap</h4>
                <div class="small text-white-50">Nomor Registrasi Sistem: <strong class="text-white">{{ $asset->asset_number }}</strong></div>
            </div>

            @php
                $statusAvailableId = \App\Models\Status::where('type', 'AST')->where('slug', 'available')->value('id') ?? 31;
            @endphp

            @if($asset->status_id == $statusAvailableId && !$isVoid)
                <form id="form-void-asset" action="{{ route('asset-capitalizations.void', $asset->id) }}" method="POST" class="m-0">
                    @csrf
                    <button type="button" class="border-2 border-white shadow-sm btn btn-danger rounded-pill fw-bold" onclick="confirmVoid()">
                        <i class="bi bi-x-circle me-1"></i> Batalkan Aset (Void)
                    </button>
                </form>
            @endif
        </div>

        <div class="p-4 card-body bg-light">
            <div class="row g-4">

                {{-- KOTAK KIRI --}}
                <div class="col-md-7">
                    <div class="border-0 shadow-sm card h-100 rounded-4">
                        <div class="py-3 bg-white card-header border-bottom">
                            <h6 class="mb-0 fw-bold {{ $isVoid ? 'text-danger' : 'text-primary' }}"><i class="bi bi-box-seam me-2"></i> Informasi Identitas Barang</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="detail-label">Nama Spesifik Aset</div>
                                    <div class="detail-value text-uppercase fw-bold {{ $isVoid ? 'text-danger text-strike' : 'text-primary' }}">{{ $asset->name }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-label">Master Item Code</div>
                                    <div class="detail-value"><span class="badge bg-secondary">{{ $asset->item->code ?? '-' }}</span></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-label">Kategori (Pajak)</div>
                                    <div class="detail-value">{{ $categoryName }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-label">Status Aset</div>
                                    <div class="detail-value">
                                        @if($isVoid)
                                            <span class="px-3 py-2 border badge bg-danger border-danger">DIBATALKAN / VOID</span>
                                        @else
                                            <span class="px-3 py-2 badge bg-success">{{ optional($asset->status)->name ?? 'Tersedia' }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-label">Serial Number (SN)</div>
                                    <div class="detail-value fw-bold text-dark">{{ $asset->serial_number ?: '-' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-label">No. Akuntansi (FA)</div>
                                    <div class="detail-value fw-bold text-dark">{{ $asset->accounting_asset_number ?: '-' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- KOTAK KANAN --}}
                <div class="col-md-5">
                    <div class="border-0 shadow-sm card h-100 rounded-4">
                        <div class="py-3 bg-white card-header border-bottom">
                            <h6 class="mb-0 fw-bold {{ $isVoid ? 'text-danger' : 'text-success' }}"><i class="bi bi-cash-coin me-2"></i> Data Finansial & Dokumen</h6>
                        </div>
                        <div class="card-body">
                            <div class="px-3 py-2 mb-3 border {{ $isVoid ? 'bg-danger-subtle text-danger-emphasis border-danger-subtle text-strike' : 'bg-success-subtle text-success-emphasis border-success-subtle' }} rounded-3">
                                <div class="mb-1 small fw-bold text-uppercase">Harga Perolehan (PSAK 16)</div>
                                <div class="fs-4 fw-bolder">Rp {{ number_format($asset->purchase_price, 2, ',', '.') }}</div>
                            </div>

                            <div class="row">
                                <div class="col-6">
                                    <div class="detail-label">Tanggal Perolehan</div>
                                    <div class="detail-value">{{ \Carbon\Carbon::parse($asset->acquisition_date)->format('d F Y') }}</div>
                                </div>
                                <div class="col-6">
                                    <div class="detail-label">Tanggal Diregistrasi</div>
                                    <div class="detail-value">{{ $asset->created_at->format('d/m/Y H:i') }}</div>
                                </div>
                                <div class="mt-2 col-12">
                                    <div class="detail-label">Dokumen Referensi (GR)</div>
                                    <div class="detail-value">
                                        <div class="fw-bold">{{ optional($asset->goodsReceipt)->gr_number ?? '-' }}</div>
                                        @if(optional(optional($asset->goodsReceipt)->po)->vendor)
                                            <div class="mt-1 small text-muted"><i class="bi bi-building me-1"></i> Vendor: {{ $asset->goodsReceipt->po->vendor->name }}</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- KOTAK BAWAH --}}
                <div class="col-12">
                    <div class="border-0 shadow-sm card rounded-4">
                        <div class="p-4 card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="mb-3 fw-bold text-dark"><i class="bi bi-card-text text-secondary me-2"></i> Spesifikasi Detail</h6>
                                    <div class="p-3 bg-white border rounded text-secondary" style="min-height: 100px;">
                                        {!! $asset->spesifikasi_detail ?: '<i>Tidak ada spesifikasi teknis yang dicatat.</i>' !!}
                                    </div>
                                </div>
                                <div class="mt-4 col-md-6 mt-md-0">
                                    <h6 class="mb-3 fw-bold text-dark"><i class="bi bi-journal-text text-secondary me-2"></i> Catatan Sistem</h6>
                                    <div class="p-3 {{ $isVoid ? 'bg-danger-subtle border-danger text-danger' : 'bg-white border text-secondary' }} rounded" style="min-height: 100px;">
                                        {!! nl2br(e($asset->notes)) ?: '<i>Tidak ada catatan.</i>' !!}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmVoid() {
        Swal.fire({
            title: 'Batalkan Pengakuan Aset?',
            text: "Status aset ini akan dibatalkan (Void). Stok fisik (1 Unit) beserta Serial Number-nya akan dikembalikan ke Gudang (berstatus Available) agar bisa digunakan kembali.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-check-circle me-1"></i> Ya, Batalkan!',
            cancelButtonText: 'Kembali',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('form-void-asset').submit();
            }
        });
    }
</script>
@endpush
