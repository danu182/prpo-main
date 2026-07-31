@extends('layouts.app')

@push('css')
<style>
    /* 🔥 KUSTOMISASI TABEL SAAS MODERN 🔥 */
    .card-table-wrapper {
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        background: #fff;
    }
    .table-modern { margin-bottom: 0; }
    .table-modern thead th {
        background-color: #f8fafc;
        color: #64748b;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1.2rem 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 700;
        border-top: none;
    }
    .table-modern tbody td {
        padding: 1.25rem 1.5rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 0.85rem;
    }
    .table-modern tbody tr { transition: all 0.2s ease; background-color: #ffffff; }
    .table-modern tbody tr:hover:not(.table-danger) { background-color: #f8fafc !important; }
</style>
@endpush

@section('content')
{{-- 🔥 DIUBAH MENJADI FULL WIDTH (CONTAINER-FLUID) 🔥 --}}
<div class="pb-5 container-fluid text-dark px-md-4">

    {{-- HEADER --}}
    <div class="mb-4">
        <h4 class="mb-0 fw-bold text-dark d-flex align-items-center">
            <i class="bi bi-eye text-primary me-2"></i> Pratinjau (Preview) Data Import Aset
        </h4>
        <div class="mt-1 text-muted small">Harap periksa kembali data di bawah sebelum dieksekusi masuk ke dalam database.</div>
    </div>

    {{-- ALERT STATUS VALIDASI --}}
    @if($hasError)
        <div class="alert alert-danger shadow-sm border-0 border-start border-danger border-4 rounded-3 mb-4 d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill fs-3 text-danger me-3"></i>
            <div>
                <h6 class="fw-bold mb-1">Ditemukan Kesalahan Data!</h6>
                <p class="mb-0 small">Ada baris data dengan warna merah karena tidak memenuhi syarat kolom WAJIB atau ada <strong>DUPLIKAT LABEL AKUNTANSI / SERIAL NUMBER</strong>. Harap perbaiki file Excel Anda lalu upload ulang.</p>
            </div>
        </div>
    @else
        <div class="alert alert-success shadow-sm border-0 border-start border-success border-4 rounded-3 mb-4 d-flex align-items-center">
            <i class="bi bi-check-circle-fill fs-3 text-success me-3"></i>
            <div>
                <h6 class="fw-bold mb-1">Data Valid dan Siap Diproses!</h6>
                <p class="mb-0 small">Semua data valid dan tidak ada duplikasi. Anda dapat melanjutkan proses Import.</p>
            </div>
        </div>
    @endif

    {{-- TABEL PREVIEW DATA MODERN --}}
    <div class="mb-4 border-4 card-table-wrapper border-top border-primary">
        <div class="p-0 table-responsive">
            <table class="table align-middle table-modern" style="min-width: 1300px;">
                <thead>
                    <tr>
                        <th class="ps-4" width="4%">No</th>
                        <th width="16%">Identitas Barang</th>
                        <th width="13%">PT & Gudang</th>
                        <th width="15%">Spesifikasi Aset</th>
                        <th width="11%">Info Perolehan</th>
                        <th width="10%">Status</th>
                        <th width="10%">Pemegang</th>
                        <th width="9%">Catatan</th>
                        <th class="pe-4" width="12%">Status Validasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($previewData as $index => $row)
                        <tr class="{{ !$row['is_row_valid'] ? 'table-danger' : '' }}">
                            <td class="ps-4 text-muted fw-bold">{{ $loop->iteration }}</td>

                            {{-- 🔥 Kolom Identitas Barang (Kode, Nama, SN, LABEL AKUNTANSI) 🔥 --}}
                            <td>
                                <div class="fw-bold {{ !$row['item_valid'] ? 'text-danger' : 'text-dark' }}">
                                    {{ $row['kode_barang'] }}
                                </div>
                                <div class="mt-1 fw-bold text-primary" style="font-size: 0.8rem;">
                                    {{ $row['nama_custom'] !== '-' ? $row['nama_custom'] : $row['nama_barang'] }}
                                </div>
                                <div class="mt-2 text-muted font-monospace" style="font-size: 0.7rem;">
                                    <div class="{{ !$row['serial_valid'] ? 'text-danger fw-bold' : '' }}">
                                        <i class="bi bi-upc-scan me-1"></i> S/N: <span class="text-dark fw-bold">{{ $row['serial'] }}</span>
                                    </div>
                                    <div class="mt-1 {{ !$row['accounting_valid'] ? 'text-danger fw-bold' : '' }}">
                                        <i class="bi bi-tags me-1"></i> AKT: <span class="text-info fw-bold">{{ $row['akuntansi'] }}</span>
                                    </div>
                                </div>
                            </td>

                            {{-- Kolom PT & Gudang --}}
                            <td>
                                <div class="fw-bold {{ !$row['company_valid'] ? 'text-danger' : 'text-dark' }} small">
                                    <i class="bi bi-building me-1 text-muted"></i>{{ $row['nama_pt'] }}
                                </div>
                                <div class="mt-1 fw-bold {{ !$row['warehouse_valid'] ? 'text-danger' : 'text-info' }} small">
                                    <i class="bi bi-geo-alt-fill me-1"></i>{{ $row['nama_gudang'] }}
                                </div>
                            </td>

                            {{-- Kolom Spesifikasi --}}
                            <td class="text-muted" style="font-size: 0.75rem; line-height: 1.3;">
                                {!! nl2br(e($row['spesifikasi'])) !!}
                            </td>

                            {{-- Kolom Info Perolehan --}}
                            <td>
                                @if($row['tanggal'])
                                    <div class="text-dark small fw-medium"><i class="bi bi-calendar-event me-1 text-muted"></i>{{ \Carbon\Carbon::parse($row['tanggal'])->format('d M Y') }}</div>
                                @else
                                    <div class="text-muted fst-italic small"><i class="bi bi-calendar-event me-1"></i>Tgl Kosong</div>
                                @endif

                                <div class="mt-1 fw-bold small {{ $row['currency_valid'] ? 'text-success' : 'text-danger' }}">
                                    {{ $row['currency_symbol'] }} {{ number_format((float)$row['harga'], 0, ',', '.') }}
                                </div>
                            </td>

                            {{-- Kolom Status (Raw Data Excel) --}}
                            <td>
                                <span class="badge {{ !$row['status_valid'] ? 'bg-danger' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }} rounded-pill text-wrap lh-sm">
                                    {{ $row['status'] }}
                                </span>
                            </td>

                            {{-- Kolom Peminjam --}}
                            <td class="fw-bold small {{ !$row['user_valid'] ? 'text-danger' : 'text-dark' }}">
                                {{ $row['peminjam'] }}
                            </td>

                            {{-- Kolom Catatan --}}
                            <td class="text-muted fst-italic" style="font-size: 0.75rem; line-height: 1.3;">
                                {{ $row['catatan'] }}
                            </td>

                            {{-- Kolom Validasi --}}
                            <td class="pe-4">
                                @if($row['is_row_valid'])
                                    <span class="px-3 shadow-sm badge bg-success rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> OK</span>
                                @else
                                    <div class="text-danger fw-bold lh-sm" style="font-size: 0.75rem;">
                                        @foreach($row['error_messages'] as $errorMsg)
                                            <div class="mb-1"><i class="bi bi-x-circle-fill me-1"></i>{{ $errorMsg }}</div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-5 text-center text-muted">
                                <i class="mb-3 opacity-50 bi bi-file-earmark-x display-4 d-block"></i>
                                Tidak ada data yang terbaca dari file Excel. Pastikan Anda mengisi data mulai dari baris ke-2.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 bg-white card-footer border-top d-flex flex-column flex-md-row justify-content-between align-items-md-end rounded-bottom-4 gap-3">
            <div class="pb-md-2">
                <span class="text-muted small fw-bold">Total Data Terbaca: <span class="text-primary fs-6">{{ count($previewData) }}</span> Baris</span>
            </div>

            <form action="{{ route('fixed-assets.process_import') }}" method="POST" class="m-0">
                @csrf
                <input type="hidden" name="file_path" value="{{ $filePath }}">
                <input type="hidden" name="doc_path" value="{{ $docPath }}">

                <div class="d-flex flex-column align-items-end">
                    @if($docPath)
                        <div class="px-3 py-1 mb-2 border shadow-sm badge bg-info-subtle text-info-emphasis rounded-pill border-info-subtle">
                            <i class="bi bi-paperclip me-1"></i> Dokumen Pendukung Terlampir
                        </div>
                    @endif

                    <div class="gap-2 d-flex">
                        <a href="{{ route('fixed-assets.index') }}" class="px-4 shadow-sm btn btn-light border-secondary-subtle fw-bold rounded-pill text-dark">
                            <i class="bi bi-x-lg me-1"></i> Batal & Kembali
                        </a>

                        <button type="submit" class="px-4 shadow-sm btn btn-primary fw-bold rounded-pill" {{ $hasError || empty($previewData) ? 'disabled' : '' }}>
                            <i class="bi bi-cloud-check-fill me-1"></i> Konfirmasi & Eksekusi Import
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
