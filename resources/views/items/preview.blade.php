@extends('layouts.app')

@section('content')
<div class="container-fluid pb-5 text-dark">

    <div class="mb-4">
        <h4 class="mb-0 fw-bold text-dark d-flex align-items-center">
            <i class="bi bi-eye text-success me-2"></i> Pratinjau (Preview) Import Master Barang
        </h4>
        <div class="mt-1 text-muted small">Periksa kembali data KTP Barang di bawah sebelum dimasukkan ke Katalog.</div>
    </div>

    @if($hasError)
        <div class="mb-4 border-0 border-4 shadow-sm alert alert-danger border-start border-danger rounded-3 d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill fs-3 text-danger me-3"></i>
            <div>
                <h6 class="mb-1 fw-bold">Ditemukan Kesalahan Data!</h6>
                <p class="mb-0 small">Ada baris data yang berwarna merah karena <strong>Kode Duplikat, Satuan Tidak Valid, atau Format Salah</strong>. Harap perbaiki file Excel Anda lalu upload ulang.</p>
            </div>
        </div>
    @else
        <div class="mb-4 border-0 border-4 shadow-sm alert alert-success border-start border-success rounded-3 d-flex align-items-center">
            <i class="bi bi-check-circle-fill fs-3 text-success me-3"></i>
            <div>
                <h6 class="mb-1 fw-bold">Data Valid dan Siap Diproses!</h6>
                <p class="mb-0 small">Semua baris valid. Sistem akan membuat item baru atau meng-update item lama berdasarkan Kode Barang.</p>
            </div>
        </div>
    @endif

    <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-success">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover" style="min-width: 1300px;">
                <thead class="bg-light fw-bold text-muted small border-bottom text-uppercase">
                    <tr>
                        <th class="py-3 ps-4" width="5%">No</th>
                        <th class="py-3" width="15%">Kode & Status</th>
                        <th class="py-3" width="25%">Nama & Kategori</th>
                        <th class="py-3 text-center" width="10%">Satuan</th>
                        <th class="py-3" width="15%">Karakteristik</th>
                        <th class="py-3" width="15%">Batas Stok</th>
                        <th class="py-3 pe-4" width="15%">Status Validasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($previewData as $index => $row)
                        <tr class="{{ !$row['is_row_valid'] ? 'table-danger' : '' }}">
                            <td class="py-3 ps-4 text-muted small">{{ $loop->iteration }}</td>

                            <td class="py-3">
                                <div class="fw-bold {{ !$row['is_row_valid'] ? 'text-danger' : 'text-dark' }} fs-6">
                                    {{ $row['kode_barang'] }}
                                </div>
                                @if($row['is_update'] && $row['is_row_valid'])
                                    <span class="mt-1 border shadow-sm badge bg-warning text-dark border-warning" style="font-size: 0.65rem;">UPDATE DATA LAMA</span>
                                @elseif($row['is_row_valid'])
                                    <span class="mt-1 border shadow-sm badge bg-success-subtle text-success border-success-subtle" style="font-size: 0.65rem;">BARANG BARU</span>
                                @endif
                            </td>

                            <td class="py-3">
                                <div class="fw-bold text-primary">{{ $row['nama_barang'] }}</div>
                                <div class="mt-1 text-muted small"><i class="bi bi-tags me-1"></i>{{ $row['kategori'] }}</div>
                            </td>

                            <td class="py-3 text-center fw-bold text-secondary">
                                {{ $row['satuan'] }}
                            </td>

                            <td class="py-3 small">
                                {{-- 🔥 Tambahkan baris Stok ini 🔥 --}}
                                <div class="{{ $row['is_stockable'] === 'YA' ? 'text-success fw-bold' : 'text-muted' }} mb-1"><i class="bi bi-box-seam me-1"></i>Stok: {{ $row['is_stockable'] }}</div>

                                <div class="{{ $row['is_asset'] === 'YA' ? 'text-info fw-bold' : 'text-muted' }}"><i class="bi bi-pc-display me-1"></i>Aset: {{ $row['is_asset'] }}</div>
                                <div class="{{ $row['is_trackable'] === 'YA' ? 'text-warning fw-bold' : 'text-muted' }} mt-1"><i class="bi bi-person-badge me-1"></i>Lacak: {{ $row['is_trackable'] }}</div>
                            </td>

                            <td class="py-3 small text-muted font-monospace">
                                Min: {{ $row['min_stock'] }} <br>
                                Max: {{ $row['max_stock'] }}
                            </td>

                            <td class="py-3 pe-4 small">
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
                            <td colspan="7" class="py-5 text-center text-muted">
                                <i class="mb-3 opacity-50 bi bi-file-earmark-x display-4 d-block"></i>
                                Tidak ada data yang terbaca dari file Excel.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="gap-3 p-3 bg-white card-footer border-top d-flex flex-column flex-md-row justify-content-between align-items-md-end rounded-bottom-4">
            <div class="pb-md-2">
                <span class="text-muted small fw-bold">Total Data Terbaca: <span class="text-success fs-6">{{ count($previewData) }}</span> Baris</span>
            </div>

            <form action="{{ route('items.process_import') }}" method="POST" class="m-0">
                @csrf
                <input type="hidden" name="file_path" value="{{ $filePath }}">

                <div class="gap-2 d-flex">
                    <a href="{{ route('items.index') }}" class="px-4 border shadow-sm btn btn-light fw-bold rounded-pill">
                        <i class="bi bi-x-lg me-1"></i> Batal & Kembali
                    </a>
                    <button type="submit" class="px-4 shadow-sm btn btn-success fw-bold rounded-pill" {{ $hasError || empty($previewData) ? 'disabled' : '' }}>
                        <i class="bi bi-cloud-check-fill me-1"></i> Konfirmasi & Eksekusi Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
