@extends('layouts.app')

@section('content')
<div class="container-fluid pb-5 text-dark">
    <div class="mb-4">
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-pencil-square me-2 text-warning"></i> Input Hasil Hitung Fisik</h3>
        <div class="text-muted">Dokumen Opname: <strong class="text-primary">{{ $opname->document_number }}</strong></div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-3 fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}</div>
    @endif

    <div class="alert alert-info border-0 shadow-sm rounded-4 fw-bold p-3 mb-4 d-flex align-items-center border-start border-4 border-info">
        <i class="bi bi-shield-lock-fill fs-4 me-3 text-info"></i>
        <div>
            <span class="d-block text-dark">Mode Blind Count Aktif</span>
            <small class="fw-normal text-muted">Stok sistem disembunyikan. Pindahkan angka persis seperti yang tertulis di Lembar Kerja oleh staf gudang.</small>
        </div>
    </div>

    <form action="{{ route('stock-opnames.update', $opname->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-list-check me-2 text-primary"></i>Daftar Barang di Lokasi: {{ optional($opname->warehouse)->name }}</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4 py-3" width="30%">Barang</th>
                                {{-- KOLOM STOK SISTEM DIHAPUS DEMI KEAMANAN BLIND COUNT --}}
                                <th width="30%">Qty Hitung Fisik (Dari Kertas) <span class="text-danger">*</span></th>
                                <th class="pe-4" width="40%">Keterangan / Alasan Selisih</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($opname->items as $item)
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="fw-bold text-dark">{{ optional($item->item)->name }}</div>
                                    <span class="badge bg-secondary-subtle text-secondary mt-1 border">{{ optional($item->item)->code }}</span>
                                </td>

                                <td>
                                    <div class="input-group shadow-sm">
                                        {{-- VALUE DIKOSONGKAN AGAR USER WAJIB NGETIK MANUAL --}}
                                        <input type="number" name="items[{{ $item->id }}][actual_qty]" class="form-control fw-bold border-warning text-dark bg-warning-subtle text-center"
                                               value="{{ $item->actual_qty > 0 ? (float) $item->actual_qty : '' }}"
                                               placeholder="Ketik angka..." min="0" step="any" required>
                                        <span class="input-group-text bg-light fw-bold text-muted">{{ $item->base_uom }}</span>
                                    </div>
                                </td>

                                <td class="pe-4">
                                    <input type="text" name="items[{{ $item->id }}][notes]" class="form-control border-light shadow-sm bg-light" value="{{ $item->notes }}" placeholder="Ketik alasan jika ada...">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Area Upload Bukti Fisik --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-images me-2 text-primary"></i>Upload Bukti Hitung <span class="text-danger">*</span></h6>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill fw-bold shadow-sm" id="btn-add-file">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Dokumen
                </button>
            </div>
            <div class="card-body p-4">
                <div class="text-muted small mb-3">
                    Wajib: Lampirkan foto kertas yang telah dicoret-coret staf gudang. Jika file berada di folder yang berbeda, klik tombol <strong>Tambah Dokumen</strong> di atas.
                </div>

                {{-- Container (Wadah) untuk menampung baris-baris input file --}}
                <div id="file-upload-container">
                    <div class="input-group mb-2 shadow-sm file-row">
                        <span class="input-group-text bg-white"><i class="bi bi-file-earmark-pdf text-danger"></i></span>
                        <input type="file" name="attachments[]" class="form-control form-control-lg bg-light" accept=".pdf,.jpg,.jpeg,.png" required>
                        {{-- Baris pertama tidak bisa dihapus karena wajib ada minimal 1 file --}}
                    </div>
                </div>
            </div>
        </div>

        {{-- Tombol Aksi --}}
        <div class="text-end">
            <a href="{{ route('stock-opnames.show', $opname->id) }}" class="btn btn-light fw-bold px-4 rounded-pill border me-2 shadow-sm">Batal</a>
            <button type="submit" class="btn btn-success fw-bold px-5 rounded-pill shadow-sm" onclick="this.innerHTML='<span class=\'spinner-border spinner-border-sm me-2\'></span>Menyimpan...';">
                <i class="bi bi-save me-1"></i> Simpan Hasil Hitungan
            </button>
        </div>
    </form>
</div>
@endsection


@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('btn-add-file').addEventListener('click', function() {
            let container = document.getElementById('file-upload-container');

            // Buat elemen div input-group
            let row = document.createElement('div');
            row.className = 'input-group mb-2 shadow-sm file-row';

            // Ikon file
            let iconSpan = document.createElement('span');
            iconSpan.className = 'input-group-text bg-white';
            iconSpan.innerHTML = '<i class="bi bi-file-earmark-pdf text-danger"></i>';

            // Input file baru
            let input = document.createElement('input');
            input.type = 'file';
            input.name = 'attachments[]'; // Array agar terbaca semua di backend
            input.className = 'form-control form-control-lg bg-light';
            input.accept = '.pdf,.jpg,.jpeg,.png';
            // Note: required tidak dipasang di baris tambahan agar user bisa membiarkannya kosong jika tidak jadi dipakai

            // Tombol hapus baris
            let btnRemove = document.createElement('button');
            btnRemove.type = 'button';
            btnRemove.className = 'btn btn-outline-danger';
            btnRemove.innerHTML = '<i class="bi bi-trash"></i>';
            btnRemove.onclick = function() {
                row.remove();
            };

            // Gabungkan elemen ke dalam baris
            row.appendChild(iconSpan);
            row.appendChild(input);
            row.appendChild(btnRemove);

            // Masukkan baris ke dalam container
            container.appendChild(row);
        });
    });
</script>
@endpush
