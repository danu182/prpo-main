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
                            // Logika Kategori Otomatis (Sama persis dengan Master List)
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
                        <tr>
                            <td class="text-center ps-3">{{ $assets->firstItem() + $index }}</td>

                            {{-- KOLOM 1: IDENTITAS & KODE ASET --}}
                            <td>
                                <div class="fw-bold text-primary">{{ $asset->asset_number ?? 'Belum Ada Kode' }}</div>
                                <div class="text-secondary" style="font-size: 0.72rem;"><span class="fw-bold text-dark">Acc Code:</span> {{ $asset->accounting_asset_number ?? '-' }}</div>
                                <div class="text-secondary" style="font-size: 0.72rem;"><span class="fw-bold text-dark">S/N:</span> {{ $asset->serial_number ?? '-' }}</div>
                            </td>

                            {{-- KOLOM 2: KATEGORI / TYPE --}}
                            <td>
                                <span class="px-2 py-1 mb-1 border badge bg-primary-subtle text-primary border-primary">
                                    {{ $subCategoryName }}
                                </span>
                                    <div class="ms-1 text-muted" style="font-size: 0.72rem; fw-semibold">Name: {{ optional($asset->item)->name ?? 'TANPA NAMA' }}</div>
                            </td>

                            {{-- KOLOM 3: NAMA BARANG & SPESIFIKASI --}}
                            <td>
                                <div class="mb-1">
                                    <span class="badge bg-secondary" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                        <i class="bi bi-upc-scan me-1"></i> {{ optional($asset->item)->code ?? 'TANPA-SKU' }}
                                    </span>
                                </div>
                                <div class="fw-bold text-dark text-wrap" style="max-width: 220px;">
                                    {{ $asset->name ?? optional($asset->item)->name ?? 'Nama Tidak Diketahui' }}
                                </div>
                                <div class="mt-1 text-muted text-wrap" style="max-width: 220px; font-size: 0.75rem; line-height: 1.2;">
                                    {{ $asset->spesifikasi_detail ?? '-' }}
                                </div>
                            </td>

                            {{-- KOLOM 4: USER / GUDANG & DEPT --}}
                            <td>
                                @if(!empty($asset->assigned_to))
                                    <div class="fw-bold text-danger">
                                        <i class="bi bi-person-workspace me-1"></i> {{ optional($asset->assignee)->name ?? 'Unknown User' }}
                                    </div>
                                    <div class="mt-1 text-muted fw-medium" style="font-size: 0.72rem;">
                                        <i class="bi bi-diagram-3"></i> Dept: {{ optional(optional($asset->assignee)->department)->name ?? optional($asset->department)->name ?? '-' }}
                                    </div>
                                @else
                                    <div class="fw-bold text-success">
                                        <i class="bi bi-shop me-1"></i> {{ optional($asset->warehouse)->name ?? 'Gudang Belum Di-set' }}
                                    </div>
                                    <div class="mt-1 text-muted fw-medium" style="font-size: 0.72rem;">
                                        <i class="bi bi-box-seam"></i> Nganggur (Tersedia di Gudang)
                                    </div>
                                @endif
                            </td>

                            {{-- KOLOM 5: PT, TGL PEROLEHAN, & HARGA --}}
                            <td>
                                <div class="fw-bold text-secondary" title="Entitas Pemilik Aset">
                                    <i class="bi bi-buildings text-primary me-1"></i> {{ optional($asset->company)->name ?? '-' }}
                                </div>
                                <div class="mt-1 text-muted" style="font-size: 0.72rem;">
                                    <i class="bi bi-calendar3"></i> {{ $asset->acquisition_date ? \Carbon\Carbon::parse($asset->acquisition_date)->format('d M Y') : '-' }}
                                </div>
                                <div class="mt-1 text-success fw-bold" style="font-size: 0.75rem;">
                                    {{ optional($asset->currency)->code ?? 'IDR' }} {{ number_format($asset->purchase_price, 0, ',', '.') }}
                                </div>
                            </td>

                            {{-- KOLOM 6: CATATAN (NOTES) --}}
                            <td>
                                <div class="text-muted text-wrap" style="max-width: 180px; font-size: 0.75rem; line-height: 1.3;">
                                    @if(!empty($asset->notes))
                                        <i class="bi bi-chat-left-text text-secondary me-1"></i> {{ $asset->notes }}
                                    @else
                                        -
                                    @endif
                                </div>
                            </td>

                            {{-- KOLOM 7: AKSI TRANSAKSI & CETAK BAST/BAPA --}}
                            <td class="text-center pe-3">
                                @if(!empty($asset->assigned_to))
                                    {{-- Jika sedang dipakai: Bisa Retur & Cetak BAST --}}
                                    <button type="button" class="py-1 mb-1 btn btn-sm btn-outline-danger fw-bold w-100" data-bs-toggle="modal" data-bs-target="#returnModal{{ $asset->id }}">
                                        <i class="bi bi-arrow-return-left"></i> Retur ke Gudang
                                    </button>
                                    <a href="{{ route('fixed-assets.bast', $asset->id) }}" target="_blank" class="py-1 btn btn-sm btn-secondary fw-bold w-100">
                                        <i class="bi bi-printer"></i> Cetak BAST
                                    </a>
                                @else
                                    {{-- Jika di gudang: Bisa Serahkan & Cetak BAPA --}}
                                    <button type="button" class="py-1 mb-1 shadow-sm btn btn-sm btn-primary fw-bold w-100" data-bs-toggle="modal" data-bs-target="#handoverModal{{ $asset->id }}">
                                        <i class="bi bi-person-plus"></i> Serahkan Aset
                                    </button>
                                    <a href="{{ route('fixed-assets.bapa', $asset->id) }}" target="_blank" class="py-1 btn btn-sm btn-outline-secondary fw-bold w-100">
                                        <i class="bi bi-printer"></i> Cetak BAPA
                                    </a>
                                @endif
                            </td>
                        </tr>

                        {{-- ========================================== --}}
                        {{-- 🔥 MODAL PROSES PENGEMBALIAN ASET (RETUR) 🔥 --}}
                        {{-- ========================================== --}}
                        @if(!empty($asset->assigned_to))
                        {{-- ... (BIARKAN KODE MODAL RETURN YANG LAMA TETAP ADA DI SINI) ... --}}
                        @endif

                        {{-- ========================================== --}}
                        {{-- 🔥 MODAL PROSES PENYERAHAN ASET (GI) 🔥 --}}
                        {{-- ========================================== --}}
                        @if(empty($asset->assigned_to))
                        <div class="modal fade" id="handoverModal{{ $asset->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="border-0 shadow-lg modal-content">
                                    <div class="text-white modal-header bg-primary">
                                        <h5 class="modal-title fw-bold">
                                            <i class="bi bi-person-plus me-2"></i> Form Penyerahan Aset
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form action="{{ route('fixed-assets.handover', $asset->id) }}" method="POST">
                                        @csrf
                                        <div class="modal-body bg-light">

                                            <div class="p-3 mb-3 border-0 shadow-sm alert alert-primary bg-primary-subtle">
                                                <div class="fw-bold text-primary"><i class="bi bi-qr-code-scan me-1"></i> {{ $asset->asset_number }}</div>
                                                <div class="mt-1 small text-dark fw-bold">{{ $asset->name ?? optional($asset->item)->name }}</div>
                                                <div class="mt-1 small text-muted">
                                                    <i class="bi bi-shop me-1"></i> Posisi Gudang: {{ optional($asset->warehouse)->name }}
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold text-dark small">Pilih Staf Penerima <span class="text-danger">*</span></label>
                                                <select name="assigned_to" class="form-select select2-user" required>
                                                    <option value="">-- Cari Nama Karyawan --</option>
                                                    @foreach($users as $user)
                                                        <option value="{{ $user->id }}">
                                                            {{ $user->name }} (Dept: {{ optional($user->department)->name ?? '-' }} | PT: {{ optional($user->company)->name ?? '-' }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold text-dark small">Update Status Aset <span class="text-danger">*</span></label>
                                                <select name="status_id" class="form-select" required>
                                                    <option value="">-- Wajib Pilih 'In Use / Dipakai' --</option>
                                                    @foreach($statuses as $st)
                                                        {{-- Coba auto-select status In Use jika ada --}}
                                                        <option value="{{ $st->id }}" {{ str_contains(strtolower($st->name), 'use') || str_contains(strtolower($st->name), 'pakai') ? 'selected' : '' }}>
                                                            {{ $st->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <div class="form-text text-muted" style="font-size: 0.7rem;">
                                                    <i class="bi bi-info-circle"></i> Pastikan memilih status <b>In Use (Dipakai)</b> atau <b>Normal</b> saat diserahkan.
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold text-dark small">Tanggal Penyerahan (BAST) <span class="text-danger">*</span></label>
                                                <input type="date" name="handover_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                                            </div>

                                            <div class="mb-0">
                                                <label class="form-label fw-bold text-dark small">Catatan Tambahan (Kelengkapan)</label>
                                                <textarea name="handover_notes" class="form-control" rows="2" placeholder="Contoh: Diserahkan lengkap beserta tas dan charger original..."></textarea>
                                            </div>

                                        </div>
                                        <div class="bg-white modal-footer">
                                            <button type="button" class="border btn btn-light" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="shadow-sm btn btn-primary fw-bold"><i class="bi bi-send-check"></i> Proses Penyerahan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endif
                        </tr>

                        {{-- MODAL PROSES PENGEMBALIAN ASET --}}
                        @if(!empty($asset->assigned_to))
                        <div class="modal fade" id="returnModal{{ $asset->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="border-0 shadow-lg modal-content">
                                    <div class="text-white modal-header bg-danger">
                                        <h5 class="modal-title fw-bold">
                                            <i class="bi bi-arrow-return-left me-2"></i> Proses Pengembalian Aset
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form action="{{ route('fixed-assets.return', $asset->id) }}" method="POST">
                                        @csrf
                                        <div class="modal-body bg-light">

                                            {{-- Info Ringkas Identitas Aset --}}
                                            <div class="p-3 mb-3 border-0 shadow-sm alert alert-secondary">
                                                <div class="fw-bold text-dark"><i class="bi bi-qr-code-scan text-primary me-1"></i> {{ $asset->asset_number }}</div>
                                                <div class="mt-1 small text-dark fw-medium">{{ $asset->name ?? optional($asset->item)->name }}</div>
                                                <div class="small text-muted" style="font-size: 0.7rem;">PT: {{ optional($asset->company)->name }}</div>
                                                <hr class="my-2">
                                                <div class="small text-danger fw-bold">
                                                    <i class="bi bi-person-x me-1"></i> Pengguna Saat Ini: {{ optional($asset->assignee)->name }}
                                                </div>
                                            </div>

                                            {{-- Form Inputs --}}
                                            <div class="mb-3">
                                                <label class="form-label fw-bold text-dark small">Tujuan Gudang Pengembalian <span class="text-danger">*</span></label>
                                                <select name="warehouse_id" class="form-select" required>
                                                    <option value="">-- Pilih Gudang Sesuai Entitas --</option>
                                                    @foreach($warehouses as $wh)
                                                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold text-dark small">Kondisi Fisik Saat Dikembalikan <span class="text-danger">*</span></label>
                                                <select name="status_id" class="form-select" required>
                                                    <option value="">-- Pilih Kondisi --</option>
                                                    @foreach($statuses as $st)
                                                        <option value="{{ $st->id }}" {{ $asset->status_id == $st->id ? 'selected' : '' }}>{{ $st->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label fw-bold text-dark small">Tanggal Pengembalian <span class="text-danger">*</span></label>
                                                <input type="date" name="return_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                                            </div>

                                            <div class="mb-0">
                                                <label class="form-label fw-bold text-dark small">Catatan / Kelengkapan Minus / Kerusakan</label>
                                                <textarea name="return_notes" class="form-control" rows="3" placeholder="Contoh: Baterai drop, ada lecet di casing belakang, charger bawaan hilang..."></textarea>
                                            </div>

                                        </div>
                                        <div class="bg-white modal-footer">
                                            <button type="button" class="border btn btn-light" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="shadow-sm btn btn-danger fw-bold"><i class="bi bi-check2-circle"></i> Simpan & Update Stok Gudang</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endif

                    @empty
                        <tr>
                            <td colspan="8" class="py-5 text-center text-muted">
                                <i class="mb-2 bi bi-inbox fs-1 d-block"></i>
                                Tidak ada data aset yang ditemukan untuk transaksi.
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


