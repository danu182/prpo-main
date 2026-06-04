@extends('layouts.app')

@section('content')
<div class="container pb-5 text-dark">

    <div class="mb-4">
        <h4 class="mb-0 fw-bold text-dark d-flex align-items-center">
            <i class="bi bi-eye text-success me-2"></i> Pratinjau (Preview) Import Saldo Awal
        </h4>
        <div class="mt-1 text-muted small">Validasi penempatan stok sebelum disuntikkan ke gudang fisik.</div>
    </div>

    @if($hasError)
        <div class="mb-4 border-0 border-4 shadow-sm alert alert-danger border-start border-danger rounded-3 d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill fs-3 text-danger me-3"></i>
            <div>
                <h6 class="mb-1 fw-bold">Ditemukan Kesalahan Data!</h6>
                <p class="mb-0 small">Ada baris data yang berwarna merah karena <strong>Kode Barang Tidak Dikenal, Nama Gudang Salah, atau QTY Kosong</strong>. Harap perbaiki file Excel Anda lalu upload ulang.</p>
            </div>
        </div>
    @else
        <div class="mb-4 border-0 border-4 shadow-sm alert alert-success border-start border-success rounded-3 d-flex align-items-center">
            <i class="bi bi-check-circle-fill fs-3 text-success me-3"></i>
            <div>
                <h6 class="mb-1 fw-bold">Data Saldo Valid & Siap Eksekusi!</h6>
                <p class="mb-0 small">Sistem mengenali semua Kode Barang dan Gudang. Silakan konfirmasi di bawah untuk menyimpan ke mutasi.</p>
            </div>
        </div>
    @endif

    <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-success">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover" style="min-width: 1200px;">
                <thead class="bg-light fw-bold text-muted small border-bottom text-uppercase">
                    <tr>
                        <th>No</th>
                        <th>Barang</th>
                        <th>Gudang</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Harga Satuan</th>
                        <th class="text-center">Mata Uang</th> {{-- 🔥 Tambah Header --}}
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($previewData as $row)
                    <tr class="{{ !$row['is_row_valid'] ? 'table-danger' : '' }}">
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $row['kode_barang'] }}</td>
                        <td>{{ $row['nama_gudang'] }}</td>
                        <td class="text-center">{{ $row['qty'] }}</td>
                        <td class="text-end">{{ number_format($row['harga'], 0) }}</td>
                        <td class="text-center">
                            <span class="badge bg-primary">{{ $row['currency'] }}</span> {{-- 🔥 Tampilkan Currency --}}
                        </td>
                        {{-- 🔥 PERBAIKAN KOLOM STATUS VALIDASI 🔥 --}}
                        <td class="py-3 pe-4 small text-start">
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
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="gap-3 p-3 bg-white card-footer border-top d-flex flex-column flex-md-row justify-content-between align-items-md-end rounded-bottom-4">
            <div class="pb-md-2">
                <span class="text-muted small fw-bold">Total Baris Saldo: <span class="text-success fs-6">{{ count($previewData) }}</span> Data</span>
            </div>

            <form action="{{ route('inventory.process_import') }}" method="POST" class="m-0">
                @csrf
                <input type="hidden" name="file_path" value="{{ $filePath }}">

                <div class="card-footer bg-white border-top p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-end rounded-bottom-4 gap-3">

                    <div class="pb-md-2">
                        <span class="text-muted small fw-bold">Total Baris Saldo: <span class="text-success fs-6">{{ count($previewData) }}</span> Data</span>
                    </div>

                    {{-- 🔥 KITA HANYA GUNAKAN 1 FORM UTAMA 🔥 --}}
                    <form action="{{ route('inventory.process_import') }}" method="POST" class="m-0 d-flex gap-2">
                        @csrf
                        <input type="hidden" name="file_path" value="{{ $filePath }}">

                        {{-- 🔥 INI YANG TERTINGGAL! Wajib ditambahkan agar file bukti ikut terbawa ke Controller 🔥 --}}
                        @if(isset($attachmentPath) && $attachmentPath)
                            <input type="hidden" name="attachment_path" value="{{ $attachmentPath }}">
                        @endif

                        {{-- TOMBOL 1: DOWNLOAD ERROR (MENGGUNAKAN FORMACTION UNTUK MEMBELOKKAN RUTE) --}}
                        @if($hasError)
                            <button type="submit" formaction="{{ route('inventory.download_errors') }}" class="btn btn-outline-danger fw-bold rounded-pill px-4 shadow-sm">
                                <i class="bi bi-file-earmark-arrow-down-fill me-1"></i> Download Log Error
                            </button>
                        @endif

                        {{-- TOMBOL 2: BATAL (LINK BIASA) --}}
                        <a href="{{ route('inventory.index') }}" class="btn btn-light border fw-bold rounded-pill px-4 shadow-sm">
                            <i class="bi bi-x-lg me-1"></i> Batal
                        </a>

                        {{-- TOMBOL 3: EKSEKUSI (RUTE DEFAULT ACTION) --}}
                        <button type="submit" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm" {{ $hasError || empty($previewData) ? 'disabled' : '' }}>
                            <i class="bi bi-send-check-fill me-1"></i> Eksekusi Saldo ke Gudang
                        </button>
                    </form>

                </div>
            </form>
        </div>
    </div>
</div>
@endsection
