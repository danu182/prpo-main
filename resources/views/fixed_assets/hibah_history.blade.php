@extends('layouts.app')

@push('css')
<style>
    /* Hover Row Tabel & Animasi Expand */
    .table-hover > tbody > tr.main-row:hover > td { background-color: #f8f9fa !important; cursor: pointer; }
    .collapse-row td { padding: 0 !important; border-bottom: none; background-color: #fcfcfc; }
    .inner-collapse-box {
        border-left: 4px solid #ffc107; background-color: #ffffff;
        box-shadow: inset 0 4px 6px -6px rgba(0,0,0,0.1), inset 0 -4px 6px -6px rgba(0,0,0,0.1);
    }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    <div class="gap-3 mb-4 d-flex justify-content-between align-items-center">
        <div>
            <a href="{{ route('fixed-assets.index') }}" class="mb-2 text-decoration-none text-muted small fw-bold d-inline-block">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Register Aset
            </a>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="bi bi-gift text-warning me-2"></i> Riwayat Penerimaan Manual & Hibah
            </h4>
            <div class="mt-1 text-muted small">Daftar rekapitulasi aset yang diregistrasikan secara manual atau melalui hibah.</div>
        </div>

        {{-- 🔥 Tombol Link ke halaman Index 🔥 --}}
        <a href="{{ route('fixed-assets.index') }}" class="shadow-sm btn btn-primary rounded-pill fw-bold">
            <i class="bi bi-plus-circle me-1"></i> Kembali ke Register untuk Input
        </a>
    </div>

    <div class="border-0 border-4 shadow-sm card border-top border-warning rounded-4">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light fw-bold text-muted border-bottom text-uppercase small">
                    <tr>
                        <th class="py-3 ps-4" width="25%">No. Penerimaan (Batch)</th>
                        <th class="py-3" width="20%">Waktu Registrasi</th>
                        <th class="py-3" width="20%">Contoh Barang & Jumlah</th>
                        <th class="py-3" width="20%">Catatan Hibah</th>
                        <th class="py-3 pe-4 text-end" width="15%">Aksi & Dokumen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($hibahs as $index => $hibah)
                    {{-- BARIS INDUK (Bisa diklik untuk expand) --}}
                    <tr class="main-row border-bottom">
                        <td class="py-3 ps-4">
                            <div class="fw-bold text-dark fs-6">{{ $hibah->batch_id }}</div>
                        </td>
                        <td class="py-3">
                            <div class="fw-bold text-secondary">{{ \Carbon\Carbon::parse($hibah->created_at)->format('d M Y') }}</div>
                            <div class="small text-muted"><i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($hibah->created_at)->format('H:i') }} WIB</div>
                        </td>
                        <td class="py-3">
                            <div class="fw-bold text-dark">{{ $hibah->sample_name }}</div>
                            <span class="mt-1 border badge bg-warning text-dark fw-bold"><i class="bi bi-boxes me-1"></i>{{ $hibah->total_items }} Unit Aset</span>
                        </td>
                        <td class="py-3">
                            <span class="small text-muted fst-italic">{{ Str::limit($hibah->notes, 50) ?? '-' }}</span>
                        </td>
                        <td class="py-3 pe-4 text-end text-nowrap">
                            {{-- Tombol Buka File (Jika ada) --}}
                            @if($hibah->supporting_document)
                                <a href="{{ asset('storage/' . $hibah->supporting_document) }}" target="_blank" class="shadow-sm btn btn-sm btn-outline-danger rounded-pill fw-bold me-1" title="Lihat Dokumen Bukti">
                                    <i class="bi bi-file-pdf-fill"></i>
                                </a>
                            @endif

                            {{-- Tombol Dropdown Detail --}}
                            <button class="shadow-sm btn btn-sm btn-dark rounded-pill fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $index }}" aria-expanded="false">
                                Rincian <i class="bi bi-chevron-down ms-1"></i>
                            </button>
                        </td>
                    </tr>

                    {{-- BARIS ANAK (Rincian Aset yang Tersembunyi) --}}
                    <tr class="collapse-row">
                        <td colspan="5" class="p-0 border-0">
                            <div class="collapse" id="collapse-{{ $index }}">
                                <div class="p-4 m-3 inner-collapse-box rounded-3">

                                    <div class="mb-3 d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check text-warning me-2"></i>Daftar Aset pada Batch {{ $hibah->batch_id }}</h6>
                                        @if(!$hibah->supporting_document)
                                            <span class="border badge bg-light text-muted border-secondary-subtle"><i class="bi bi-exclamation-circle me-1"></i>Tanpa Dokumen Pendukung</span>
                                        @endif
                                    </div>

                                    {{-- 🔥 SIHIR BLADE: Tarik Aset Anak Berdasarkan Batch ID 🔥 --}}
                                    @php
                                        $detailAssets = \App\Models\FixedAsset::with(['company', 'status'])->where('batch_id', $hibah->batch_id)->get();
                                    @endphp

                                    <div class="border rounded table-responsive border-secondary-subtle">
                                        <table class="table mb-0 table-sm table-hover small">
                                            <thead class="bg-light text-muted">
                                                <tr>
                                                    <th class="py-2 ps-3" width="5%">No</th>
                                                    <th class="py-2" width="20%">No. Aset / Akuntansi</th>
                                                    <th class="py-2" width="30%">Nama Spesifik Aset</th>
                                                    <th class="py-2" width="15%">Serial Number</th>
                                                    <th class="py-2" width="15%">Milik PT</th>
                                                    <th class="py-2 text-end pe-3" width="15%">Nilai Wajar (Rp)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($detailAssets as $det)
                                                <tr>
                                                    <td class="py-2 ps-3 text-muted">{{ $loop->iteration }}</td>
                                                    <td class="py-2">
                                                        <div class="fw-bold text-primary">{{ $det->asset_number }}</div>
                                                        <div class="text-muted" style="font-size: 0.7rem;">FA: {{ $det->accounting_asset_number ?? '-' }}</div>
                                                    </td>
                                                    <td class="py-2 fw-bold text-dark">{{ $det->name }}</td>
                                                    <td class="py-2"><span class="font-monospace text-muted">{{ $det->serial_number ?? 'Belum diset' }}</span></td>
                                                    <td class="py-2">{{ optional($det->company)->name ?? '-' }}</td>
                                                    <td class="py-2 text-end pe-3 text-success fw-bold">{{ number_format($det->purchase_price, 0, ',', '.') }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-5 text-center text-muted">
                            <i class="mb-3 opacity-25 bi bi-gift display-4 d-block"></i>
                            <h6 class="fw-bold text-dark">Belum Ada Riwayat Hibah</h6>
                            <p class="mb-0 text-muted small">Data penerimaan aset manual atau hibah akan muncul di sini.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($hibahs->hasPages())
        <div class="p-3 bg-white border-0 card-footer rounded-bottom-4">
            {{ $hibahs->links() }}
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    $(document).ready(function() {
        // 🔥 UX Enhancement: Klik Baris Induk Untuk Expand/Collapse Detail 🔥
        $('.main-row').on('click', function(e) {
            // Jangan expand jika yang diklik adalah tombol/link (seperti tombol PDF)
            if(e.target.tagName === 'BUTTON' || e.target.tagName === 'A' || $(e.target).closest('a').length || $(e.target).closest('button').length) {
                return;
            }
            let btn = $(this).find('[data-bs-toggle="collapse"]');
            if(btn.length) {
                btn.click();
            }
        });
    });
</script>
@endpush
@endsection
