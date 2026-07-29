@extends('layouts.app') {{-- Sesuaikan dengan nama layout utama Anda --}}

@section('content')
<div class="mb-5 container-fluid">
    {{-- ALERT PESAN SUKSES / ERROR --}}
    @if(session('success'))
        <div class="shadow-sm alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="shadow-sm alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- HEADER & PENCARIAN --}}
    <div class="mb-4 border-0 shadow-sm card">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-1 fw-bold"><i class="bi bi-arrow-left-right text-danger me-2"></i> Transaksi & Pengembalian Aset</h4>
                <p class="mb-0 text-muted" style="font-size: 0.85rem;">Proses penyerahan aset ke staf dan pengembalian aset ke gudang dengan detail informasi lengkap.</p>
            </div>

            <form action="{{ route('fixed-assets.transactions') }}" method="GET" class="d-flex" style="width: 350px;">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Cari aset / S/N / nama staf..." value="{{ request('search') }}">
                    <button class="btn btn-dark" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>
    </div>

    {{-- TABEL DATA TRANSAKSI SAKTI (SELENGKAP MASTER LIST) --}}
    <div class="border-0 shadow-sm card">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover table-striped text-nowrap" style="font-size: 0.85rem;">
                <thead class="text-white bg-dark">
                    <tr>
                        <th class="text-center ps-3" width="4%">No</th>
                        <th width="12%">Identitas & Kode Aset</th>
                        <th width="10%">Kategori / Type</th>
                        <th width="20%">Nama Barang & Spesifikasi</th>
                        <th width="15%">User Pengguna / Gudang</th>
                        <th width="14%">Perolehan & Status</th>
                        <th width="13%">Catatan / Notes</th>
                        <th width="12%" class="text-center pe-3">Aksi Transaksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $index => $asset)
                        @php
                            // Logika Kategori Otomatis
                            $subCategoryName = optional(optional($asset->item)->category)->name ?? '';
                            $subCategoryCode = optional(optional($asset->item)->category)->code ?? '';

                            if(empty($subCategoryName)) {
                                $namaBarangLower = strtolower($asset->name);
                                if(str_contains($namaBarangLower, 'laptop') || str_contains($namaBarangLower, 'macbook')) {
                                    $subCategoryName = 'Laptops'; $subCategoryCode = 'LAP';
                                } elseif(str_contains($namaBarangLower, 'pc') || str_contains($namaBarangLower, 'desktop') || str_contains($namaBarangLower, 'imac')) {
                                    $subCategoryName = 'Elektronik & IT'; $subCategoryCode = 'ELK';
                                } elseif(str_contains($namaBarangLower, 'iphone') || str_contains($namaBarangLower, 'phone') || str_contains($namaBarangLower, 'hp')) {
                                    $subCategoryName = 'Handphone'; $subCategoryCode = 'HPN';
                                } else {
                                    $subCategoryName = 'Fixed Asset'; $subCategoryCode = 'AST';
                                }
                            }
                        @endphp
                        <tr style="height: 100px;"> {{-- 🔥 Menambah tinggi baris agar lega --}}
                            <td class="text-center align-middle ps-3">{{ $assets->firstItem() + $index }}</td>

                            {{-- KOLOM 1: IDENTITAS & KODE ASET --}}
                            <td class="align-middle">
                                <div class="mb-1 fw-bold text-primary" style="font-size: 0.9rem;">{{ $asset->asset_number ?? 'Belum Ada Kode' }}</div>
                                <div class="mb-1 text-secondary" style="font-size: 0.72rem;"><span class="fw-bold text-dark">Acc Code:</span> {{ $asset->accounting_asset_number ?? '-' }}</div>
                                <div class="text-secondary" style="font-size: 0.72rem;"><span class="fw-bold text-dark">S/N:</span> <span class="px-1 border rounded bg-light">{{ $asset->serial_number ?? '-' }}</span></div>
                            </td>

                            {{-- KOLOM 2: KATEGORI / TYPE --}}
                            <td class="align-middle">
                                <span class="px-2 py-1 mb-2 border badge bg-primary-subtle text-primary border-primary d-inline-block">
                                    {{ $subCategoryName }}
                                </span>
                                <div class="text-muted" style="font-size: 0.7rem; font-weight: 600;">
                                    <i class="opacity-50 bi bi-tag-fill me-1"></i>{{ $subCategoryCode }}
                                </div>
                            </td>

                            {{-- KOLOM 3: NAMA BARANG & SPESIFIKASI --}}
                            <td class="align-middle">
                                <div class="mb-2">
                                    <span class="opacity-75 badge bg-secondary" style="font-size: 0.65rem; letter-spacing: 0.5px;">
                                        <i class="bi bi-upc-scan me-1"></i> {{ optional($asset->item)->code ?? 'TANPA-SKU' }}
                                    </span>
                                </div>
                                <div class="mb-1 fw-bold text-dark text-wrap" style="max-width: 250px; font-size: 0.85rem;">
                                    {{ $asset->name ?? optional($asset->item)->name ?? 'Nama Tidak Diketahui' }}
                                </div>
                                <div class="text-muted text-wrap" style="max-width: 250px; font-size: 0.7rem; line-height: 1.3;">
                                    {{ Str::limit($asset->spesifikasi_detail ?? '-', 60) }}
                                </div>
                            </td>

                            {{-- KOLOM 4: USER / GUDANG & DEPT --}}
                            <td class="align-middle">
                                @if(!empty($asset->assigned_to))
                                    <div class="mb-1 fw-bold text-danger">
                                        <i class="bi bi-person-workspace me-1"></i> {{ optional($asset->assignee)->name ?? 'Unknown User' }}
                                    </div>
                                    <div class="text-muted fw-medium" style="font-size: 0.7rem;">
                                        <i class="opacity-50 bi bi-diagram-3 me-1"></i> Dept: {{ Str::limit(optional(optional($asset->assignee)->department)->name ?? optional($asset->department)->name ?? '-', 20) }}
                                    </div>
                                @else
                                    <div class="mb-1 fw-bold text-success">
                                        <i class="bi bi-shop me-1"></i> {{ Str::limit(optional($asset->warehouse)->name ?? 'Gudang Belum Di-set', 20) }}
                                    </div>
                                    <div class="text-muted fw-medium" style="font-size: 0.7rem;">
                                        <span class="py-1 border badge border-success text-success bg-success-subtle"><i class="bi bi-check2-circle me-1"></i> Available</span>
                                    </div>
                                @endif
                            </td>

                            {{-- KOLOM 5: PT, TGL PEROLEHAN, & HARGA --}}
                            <td class="align-middle">
                                <div class="mb-1 fw-bold text-dark" title="Entitas Pemilik Aset" style="font-size: 0.8rem;">
                                    <i class="bi bi-buildings text-primary me-1"></i> {{ Str::limit(optional($asset->company)->name ?? '-', 20) }}
                                </div>
                                <div class="mb-1 text-muted" style="font-size: 0.7rem;">
                                    <i class="opacity-50 bi bi-calendar3 me-1"></i> {{ $asset->acquisition_date ? \Carbon\Carbon::parse($asset->acquisition_date)->format('d M Y') : '-' }}
                                </div>
                                <div class="text-success fw-bold" style="font-size: 0.75rem;">
                                    {{ optional($asset->currency)->code ?? 'IDR' }} {{ number_format($asset->purchase_price, 0, ',', '.') }}
                                </div>
                            </td>

                            {{-- KOLOM 6: CATATAN (NOTES) --}}
                            <td class="align-middle">
                                <div class="p-2 border rounded text-muted text-wrap bg-light" style="max-width: 180px; font-size: 0.7rem; line-height: 1.4; min-height: 40px;">
                                    @if(!empty($asset->notes))
                                        <i class="bi bi-chat-quote-fill text-secondary me-1"></i> {{ Str::limit($asset->notes, 50) }}
                                    @else
                                        <span class="opacity-50"><i>Tidak ada catatan</i></span>
                                    @endif
                                </div>
                            </td>

                            {{-- KOLOM 7: AKSI TRANSAKSI & CETAK BAST/BAPA --}}
                            <td class="text-center align-middle pe-3">
                                <div class="gap-1 d-flex flex-column"> {{-- 🔥 Menggunakan gap agar rapi --}}
                                    @if(!empty($asset->assigned_to))
                                        {{-- Jika sedang dipakai: Bisa Retur & Cetak BAST --}}
                                        <button type="button" class="shadow-sm btn btn-sm btn-outline-danger fw-bold" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#returnModal{{ $asset->id }}">
                                            <i class="bi bi-arrow-return-left"></i> Retur ke Gudang
                                        </button>
                                        <a href="{{ route('fixed-assets.bast', $asset->id) }}" target="_blank" class="shadow-sm btn btn-sm btn-dark fw-bold" style="font-size: 0.75rem;">
                                            <i class="bi bi-printer"></i> Cetak BAST
                                        </a>
                                    @else
                                        {{-- Jika di gudang: Bisa Serahkan & Cetak BAPA --}}
                                        <button type="button" class="shadow-sm btn btn-sm btn-primary fw-bold" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#handoverModal{{ $asset->id }}">
                                            <i class="bi bi-person-plus"></i> Serahkan Aset
                                        </button>
                                        <a href="{{ route('fixed-assets.bapa', $asset->id) }}" target="_blank" class="shadow-sm btn btn-sm btn-outline-dark fw-bold" style="font-size: 0.75rem;">
                                            <i class="bi bi-printer"></i> Cetak BAPA
                                        </a>
                                    @endif

                                    {{-- 🔥 Tombol Riwayat selalu muncul untuk semua kondisi aset 🔥 --}}
                                    <a href="{{ route('fixed-assets.history', $asset->id) }}" class="mt-1 shadow-sm btn btn-sm btn-warning text-dark fw-bold" style="font-size: 0.75rem;">
                                        <i class="bi bi-clock-history"></i> Lihat Riwayat
                                    </a>
                                </div>
                            </td>
                        </tr>

                        {{-- MODAL PROSES PENGEMBALIAN ASET (RETUR) --}}
                        @if(!empty($asset->assigned_to))
                        <div class="modal fade text-start" id="returnModal{{ $asset->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="border-0 shadow-lg modal-content rounded-4">
                                    <div class="text-white modal-header bg-danger border-bottom-0 rounded-top-4">
                                        <h6 class="modal-title fw-bold">
                                            <i class="bi bi-arrow-return-left me-2"></i> Form Pengembalian Aset
                                        </h6>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form action="{{ route('fixed-assets.return', $asset->id) }}" method="POST">
                                        @csrf
                                        <div class="modal-body bg-light">
                                            <div class="p-3 mb-4 border-0 shadow-sm alert alert-danger bg-danger-subtle rounded-3">
                                                <div class="mb-1 fw-bold text-danger"><i class="bi bi-qr-code-scan me-1"></i> {{ $asset->asset_number }}</div>
                                                <div class="small text-dark fw-bold">{{ $asset->name ?? optional($asset->item)->name }}</div>
                                                <hr class="my-2 opacity-25 border-danger">
                                                <div class="small text-danger fw-bold">
                                                    <i class="bi bi-person-workspace me-1"></i> Kembali dari: {{ optional($asset->assignee)->name }}
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold text-dark small">Tujuan Gudang <span class="text-danger">*</span></label>
                                                <select name="warehouse_id" class="form-select border-danger-subtle" required>
                                                    <option value="">-- Pilih Gudang Sesuai Entitas --</option>
                                                    @foreach($warehouses as $wh)
                                                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="row">
                                                <div class="mb-3 col-md-6">
                                                    <label class="form-label fw-bold text-dark small">Kondisi Aset <span class="text-danger">*</span></label>
                                                    <select name="status_id" class="form-select border-danger-subtle" required>
                                                        <option value="">-- Pilih Kondisi --</option>
                                                        @foreach($statuses as $st)
                                                            <option value="{{ $st->id }}" {{ str_contains(strtolower($st->name), 'normal') || str_contains(strtolower($st->name), 'available') ? 'selected' : '' }}>{{ $st->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="mb-3 col-md-6">
                                                    <label class="form-label fw-bold text-dark small">Tanggal Kembali <span class="text-danger">*</span></label>
                                                    <input type="date" name="return_date" class="form-control border-danger-subtle" value="{{ date('Y-m-d') }}" required>
                                                </div>
                                            </div>

                                            <div class="mb-1">
                                                <label class="form-label fw-bold text-dark small">Catatan Minus / Kerusakan</label>
                                                <textarea name="return_notes" class="form-control border-danger-subtle" rows="2" placeholder="Contoh: Lecet pemakaian, charger hilang..."></textarea>
                                            </div>
                                        </div>
                                        <div class="bg-white modal-footer border-top-0 rounded-bottom-4">
                                            <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="shadow-sm btn btn-danger fw-bold"><i class="bi bi-save me-1"></i> Simpan & Update Stok</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- MODAL PROSES PENYERAHAN ASET (GI) --}}
                        @if(empty($asset->assigned_to))
                        <div class="modal fade text-start" id="handoverModal{{ $asset->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="border-0 shadow-lg modal-content rounded-4">
                                    <div class="text-white modal-header bg-primary border-bottom-0 rounded-top-4">
                                        <h6 class="modal-title fw-bold">
                                            <i class="bi bi-person-plus me-2"></i> Form Penyerahan Aset
                                        </h6>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form action="{{ route('fixed-assets.handover', $asset->id) }}" method="POST">
                                        @csrf
                                        <div class="modal-body bg-light">
                                            <div class="p-3 mb-4 border-0 shadow-sm alert alert-primary bg-primary-subtle rounded-3">
                                                <div class="mb-1 fw-bold text-primary"><i class="bi bi-qr-code-scan me-1"></i> {{ $asset->asset_number }}</div>
                                                <div class="small text-dark fw-bold">{{ $asset->name ?? optional($asset->item)->name }}</div>
                                                <hr class="my-2 opacity-25 border-primary">
                                                <div class="small text-muted">
                                                    <i class="bi bi-shop me-1"></i> Dari Gudang: {{ optional($asset->warehouse)->name }}
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold text-dark small">Pilih Staf Penerima <span class="text-danger">*</span></label>
                                                <select name="assigned_to" class="form-select select2-user border-primary-subtle" required>
                                                    <option value="">-- Ketik Nama Karyawan --</option>
                                                    @foreach($users as $user)
                                                        <option value="{{ $user->id }}">
                                                            {{ $user->name }} (Dept: {{ optional($user->department)->name ?? '-' }} | PT: {{ optional($user->company)->name ?? '-' }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="row">
                                                <div class="mb-3 col-md-6">
                                                    <label class="form-label fw-bold text-dark small">Update Status <span class="text-danger">*</span></label>
                                                    <select name="status_id" class="form-select border-primary-subtle" required>
                                                        <option value="">-- Pilih Status --</option>
                                                        @foreach($statuses as $st)
                                                            <option value="{{ $st->id }}" {{ str_contains(strtolower($st->name), 'use') || str_contains(strtolower($st->name), 'pakai') ? 'selected' : '' }}>
                                                                {{ $st->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="mb-3 col-md-6">
                                                    <label class="form-label fw-bold text-dark small">Tgl. Serah Terima <span class="text-danger">*</span></label>
                                                    <input type="date" name="handover_date" class="form-control border-primary-subtle" value="{{ date('Y-m-d') }}" required>
                                                </div>
                                            </div>

                                            <div class="mb-1">
                                                <label class="form-label fw-bold text-dark small">Catatan (Kelengkapan)</label>
                                                <textarea name="handover_notes" class="form-control border-primary-subtle" rows="2" placeholder="Contoh: Lengkap dengan tas & charger..."></textarea>
                                            </div>
                                        </div>
                                        <div class="bg-white modal-footer border-top-0 rounded-bottom-4">
                                            <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="shadow-sm btn btn-primary fw-bold"><i class="bi bi-send-check me-1"></i> Proses Penyerahan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endif

                    @empty
                        <tr>
                            <td colspan="8" class="py-5 text-center align-middle text-muted">
                                <i class="mb-3 opacity-25 bi bi-inbox display-4 d-block"></i>
                                <h6 class="fw-bold">Data Transaksi Kosong</h6>
                                <p class="small">Belum ada aset yang didaftarkan untuk bertransaksi.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINASI --}}
        <div class="bg-white card-footer border-top-0">
            {{ $assets->links() }}
        </div>
    </div>
</div>
@endsection


