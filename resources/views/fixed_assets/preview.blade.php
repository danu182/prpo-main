@extends('layouts.app')

@section('content')
<div class="container pb-5 text-dark">

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

    {{-- TABEL PREVIEW DATA --}}
    <div class="card border-0 shadow-sm rounded-4 border-top border-primary border-4">
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover align-middle mb-0" style="min-width: 1300px;">
                <thead class="bg-light fw-bold text-muted small border-bottom text-uppercase">
                    <tr>
                        <th class="py-3 ps-4" width="4%">No</th>
                        <th class="py-3" width="16%">Identitas Barang</th>
                        <th class="py-3" width="13%">PT & Gudang</th>
                        <th class="py-3" width="15%">Spesifikasi Aset</th>
                        <th class="py-3" width="11%">Info Perolehan</th>
                        <th class="py-3" width="10%">Status</th>
                        <th class="py-3" width="10%">Pemegang</th>
                        <th class="py-3" width="9%">Catatan</th>
                        <th class="py-3 pe-4" width="12%">Status Validasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($previewData as $index => $row)
                        <tr class="{{ !$row['is_row_valid'] ? 'table-danger' : '' }}">
                            <td class="py-3 ps-4 text-muted small">{{ $loop->iteration }}</td>

                            {{-- 🔥 Kolom Identitas Barang (Kode, Nama, SN, LABEL AKUNTANSI) 🔥 --}}
                            <td class="py-3">
                                <div class="fw-bold {{ !$row['item_valid'] ? 'text-danger' : 'text-dark' }}">
                                    {{ $row['kode_barang'] }}
                                </div>
                                <div class="small fw-bold text-primary mt-1">
                                    {{ $row['nama_custom'] !== '-' ? $row['nama_custom'] : $row['nama_barang'] }}
                                </div>
                                <div class="small text-muted mt-2 font-monospace" style="font-size: 0.7rem;">
                                    <div class="{{ !$row['serial_valid'] ? 'text-danger fw-bold' : '' }}">
                                        <i class="bi bi-upc-scan me-1"></i> S/N: {{ $row['serial'] }}
                                    </div>
                                    <div class="mt-1 {{ !$row['accounting_valid'] ? 'text-danger fw-bold' : '' }}">
                                        <i class="bi bi-tags me-1"></i> AKT: {{ $row['akuntansi'] }}
                                    </div>
                                </div>
                            </td>

                            {{-- Kolom PT & Gudang --}}
                            <td class="py-3 small">
                                <div class="fw-bold {{ !$row['company_valid'] ? 'text-danger' : 'text-dark' }}">
                                    <i class="bi bi-building me-1 text-muted"></i>{{ $row['nama_pt'] }}
                                </div>
                                <div class="mt-1 fw-bold {{ !$row['warehouse_valid'] ? 'text-danger' : 'text-info' }}">
                                    <i class="bi bi-geo-alt-fill me-1"></i>{{ $row['nama_gudang'] }}
                                </div>
                            </td>

                            {{-- Kolom Spesifikasi --}}
                            <td class="py-3 small text-muted" style="font-size: 0.75rem; line-height: 1.3;">
                                {!! nl2br(e($row['spesifikasi'])) !!}
                            </td>

                            {{-- Kolom Info Perolehan --}}
                            <td class="py-3 small">
                                @if($row['tanggal'])
                                    <div class="text-dark"><i class="bi bi-calendar-event me-1 text-muted"></i>{{ \Carbon\Carbon::parse($row['tanggal'])->format('d M Y') }}</div>
                                @else
                                    <div class="text-muted fst-italic"><i class="bi bi-calendar-event me-1"></i>Tgl Kosong</div>
                                @endif

                                <div class="mt-1 fw-bold {{ $row['currency_valid'] ? 'text-success' : 'text-danger' }}">
                                    {{ $row['currency_symbol'] }} {{ number_format((float)$row['harga'], 0, ',', '.') }}
                                </div>
                            </td>

                            {{-- Kolom Status --}}
                            <td class="py-3 small text-muted">
                                <span class="badge {{ !$row['status_valid'] ? 'bg-danger' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }} rounded-pill text-wrap lh-sm">
                                    {{ $row['status'] }}
                                </span>
                            </td>

                            {{-- Kolom Peminjam --}}
                            <td class="py-3 small fw-bold {{ !$row['user_valid'] ? 'text-danger' : 'text-dark' }}">
                                {{ $row['peminjam'] }}
                            </td>

                            {{-- Kolom Catatan --}}
                            <td class="py-3 small text-muted fst-italic">
                                {{ $row['catatan'] }}
                            </td>

                            {{-- Kolom Validasi --}}
                            <td class="py-3 pe-4 small">
                                @if($row['is_row_valid'])
                                    <span class="badge bg-success rounded-pill px-3 shadow-sm"><i class="bi bi-check-circle-fill me-1"></i> OK</span>
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
                                <i class="bi bi-file-earmark-x display-4 d-block mb-3 opacity-50"></i>
                                Tidak ada data yang terbaca dari file Excel. Pastikan Anda mengisi data mulai dari baris ke-2.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

       <div class="card-footer bg-white border-top p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-end rounded-bottom-4 gap-3">

            <div class="pb-md-2">
                <span class="text-muted small fw-bold">Total Data Terbaca: <span class="text-primary fs-6">{{ count($previewData) }}</span> Baris</span>
            </div>

            <form action="{{ route('fixed-assets.process_import') }}" method="POST" class="m-0">
                @csrf
                <input type="hidden" name="file_path" value="{{ $filePath }}">
                <input type="hidden" name="doc_path" value="{{ $docPath }}">

                <div class="d-flex flex-column align-items-end">
                    @if($docPath)
                        <div class="badge bg-info text-dark rounded-pill px-3 py-1 mb-2 shadow-sm border border-info">
                            <i class="bi bi-paperclip me-1"></i> Dokumen Pendukung Terlampir
                        </div>
                    @endif

                    <div class="d-flex gap-2">
                        <a href="{{ route('fixed-assets.index') }}" class="btn btn-light border fw-bold rounded-pill px-4 shadow-sm">
                            <i class="bi bi-x-lg me-1"></i> Batal & Kembali
                        </a>

                        <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm" {{ $hasError || empty($previewData) ? 'disabled' : '' }}>
                            <i class="bi bi-cloud-check-fill me-1"></i> Konfirmasi & Eksekusi Import
                        </button>
                    </div>
                </div>
            </form>

        </div>
    </div>
</div>
@endsection
