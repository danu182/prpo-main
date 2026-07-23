@extends('layouts.app')

@push('css')
<style>
    /* Hover Row Tabel & Animasi Expand */
    .table-hover > tbody > tr.main-row:hover > td { background-color: #fcf4f4 !important; cursor: pointer; }
    .collapse-row td { padding: 0 !important; border-bottom: none; background-color: #fcfcfc; }
    .inner-collapse-box {
        border-left: 4px solid #dc3545; /* Garis merah khas pengeluaran */
        background-color: #ffffff;
        box-shadow: inset 0 4px 6px -6px rgba(0,0,0,0.1), inset 0 -4px 6px -6px rgba(0,0,0,0.1);
    }

    /* Scrollbar minimalis untuk kotak spesifikasi */
    .scroll-spek::-webkit-scrollbar { width: 5px; }
    .scroll-spek::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
    .scroll-spek::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .scroll-spek::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
@endpush

@section('content')
<div class="px-0 container-fluid">

    {{-- HEADER HALAMAN & TOMBOL AKSI --}}
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="bi bi-box-arrow-up text-danger me-2"></i> Riwayat Pengeluaran Barang (GI)
            </h4>
            <div class="mt-1 text-muted small">Daftar riwayat barang yang dikeluarkan untuk operasional karyawan/departemen.</div>
        </div>

        <div class="gap-2 d-flex flex-column flex-md-row align-items-md-center">

            {{-- 🔥 FORM PENCARIAN (DEEP SEARCH) 🔥 --}}
            <form action="{{ route('goods-issues.index') }}" method="GET" class="m-0 d-flex" style="min-width: 350px;">
                <div class="overflow-hidden border shadow-sm input-group rounded-pill">
                    <input type="text" name="search" class="px-4 bg-white border-0 form-control" placeholder="Ketik No GI, Karyawan, atau Nama Barang..." value="{{ request('search') }}">
                    <button class="px-4 text-white border-0 btn btn-danger fw-bold" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>

            {{-- TOMBOL KELUARKAN BARANG BARU (GI BARU) --}}
            <a href="{{ route('goods-issues.create') }}" class="px-4 shadow-sm btn btn-danger fw-bold rounded-pill text-nowrap">
                <i class="bi bi-plus-lg me-1"></i> Keluarkan Barang
            </a>
        </div>
    </div>

    {{-- ALERT NOTIFIKASI --}}
    @if(session('success'))
        <div class="border-0 border-4 shadow-sm alert alert-success rounded-3 fw-bold border-start border-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="border-0 border-4 shadow-sm alert alert-danger rounded-3 fw-bold border-start border-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- KARTU TABEL DATA PENGELUARAN --}}
    <div class="border-0 border-4 shadow-sm card border-top border-danger rounded-4">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light text-muted small text-uppercase fw-bold border-bottom">
                    <tr>
                        <th class="py-3 ps-4" width="16%">No. Dokumen (GI)</th>
                        <th class="py-3" width="12%">Tgl Keluar</th>
                        <th class="py-3" width="16%">Diserahkan Kepada</th>
                        <th class="py-3" width="12%">Departemen</th>
                        <th class="py-3" width="16%">Gudang Asal</th>
                        <th class="py-3 text-center" width="12%">Status</th>
                        <th class="py-3 pe-4 text-end" width="16%">Aksi & Rincian</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($goodsIssues as $index => $gi)

                    @php
                        $totalIssued = $gi->items->sum('qty_issued');
                        $totalReturned = $gi->items->sum('qty_returned');
                    @endphp

                    {{-- BARIS INDUK (Bisa diklik untuk Expand) --}}
                    <tr class="main-row border-bottom">
                        <td class="py-3 ps-4">
                            {{-- 🔥 PERBAIKAN: GUNAKAN SLUG (GI NUMBER) 🔥 --}}
                            <a href="{{ route('goods-issues.show', $gi->gi_number) }}" class="fw-bold text-danger text-decoration-none">
                                {{ $gi->gi_number }}
                            </a>
                            <div class="mt-1 small text-muted"><i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($gi->created_at)->format('H:i') }} WIB</div>
                        </td>
                        <td class="py-3 text-dark fw-semibold">{{ \Carbon\Carbon::parse($gi->issue_date)->format('d M Y') }}</td>
                        <td class="align-middle">
                            <div class="fw-bold text-dark fs-6">{{ $gi->requester_name }}</div>

                            @php
                                // Robot mencari data profil karyawan ini secara dinamis untuk mengambil PT-nya
                                $userPenerima = \App\Models\User::with('company')->where('name', $gi->requester_name)->first();
                                $namaPt = $userPenerima ? (optional($userPenerima->company)->name ?? 'Kantor Pusat') : 'Unknown PT';
                            @endphp

                            <div class="gap-1 mt-1 d-flex flex-column">
                                <div class="small text-muted" style="font-size: 0.75rem;">
                                    <i class="bi bi-building me-1 text-primary"></i> {{ $namaPt }}
                                </div>
                                <div class="small text-muted" style="font-size: 0.75rem;">
                                    <i class="bi bi-diagram-3 me-1 text-info"></i> {{ $gi->department ?? 'Tanpa Dept' }}
                                </div>
                            </div>
                        </td>
                        <td class="py-3 text-muted">{{ $gi->department ?? '-' }}</td>

                        <td class="py-3">
                            <span class="px-3 py-2 border shadow-sm badge bg-light text-secondary border-secondary-subtle rounded-pill">
                                <i class="bi bi-shop me-1"></i> {{ optional($gi->warehouse)->name ?? 'Gudang Utama' }}
                            </span>
                        </td>

                        <td class="py-3 text-center">
                            @if($gi->status)
                                <span class="border badge rounded-pill px-3 py-2 bg-{{ $gi->status->color }} border-{{ $gi->status->color }} shadow-sm">
                                    {{ $gi->status->name }}
                                </span>
                            @else
                                <span class="px-3 py-2 badge bg-secondary rounded-pill">-</span>
                            @endif
                        </td>

                        <td class="py-3 pe-4 text-end text-nowrap">
                            <div class="gap-2 d-flex justify-content-end align-items-center">
                                {{-- 🔥 PERBAIKAN: GUNAKAN SLUG (GI NUMBER) 🔥 --}}
                                <a href="{{ route('goods-issues.show', $gi->gi_number) }}" class="px-3 shadow-sm btn btn-sm btn-outline-danger rounded-pill fw-bold" title="Lihat Detail & Cetak BAST">
                                    <i class="bi bi-printer-fill"></i> Detail
                                </a>

                                @if(optional($gi->status)->slug === 'void')
                                    <button class="px-3 text-white border shadow-sm btn btn-sm btn-secondary fw-bold rounded-pill" disabled title="Transaksi Dibatalkan">
                                        <i class="bi bi-ban"></i> Void
                                    </button>
                                @elseif($totalReturned < $totalIssued)
                                    {{-- Catatan: Pastikan route create retur menerima ID atau Slug sesuai konfigurasi web.php --}}
                                    <a href="{{ route('goods-issue-returns.create', $gi->id) }}" class="px-3 shadow-sm btn btn-sm btn-warning text-dark fw-bold rounded-pill" title="Terima Pengembalian Barang">
                                        <i class="bi bi-arrow-return-left"></i> Retur
                                    </a>
                                @else
                                    <button class="px-3 border shadow-sm btn btn-sm btn-light text-muted fw-bold rounded-pill" disabled title="Semua barang sudah dikembalikan">
                                        <i class="bi bi-check2-all"></i> Selesai
                                    </button>
                                @endif

                                <button class="shadow-sm btn btn-sm btn-dark rounded-pill fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $index }}" aria-expanded="false">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                            </div>
                        </td>
                    </tr>

                    {{-- BARIS ANAK (Rincian Barang Keluar yang Tersembunyi) --}}
                    <tr class="collapse-row">
                        <td colspan="7" class="p-0 border-0">
                            <div class="collapse" id="collapse-{{ $index }}">
                                <div class="p-4 m-3 inner-collapse-box rounded-3">
                                    <div class="mb-3 d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-ul text-danger me-2"></i>Rincian Barang pada {{ $gi->gi_number }}</h6>
                                        <span class="border badge bg-light text-dark"><i class="bi bi-boxes me-1"></i> Total Keluar: {{ $totalIssued }} Unit</span>
                                    </div>

                                    <div class="border rounded table-responsive border-secondary-subtle">
                                        <table class="table mb-0 table-sm table-hover small">
                                            <thead class="bg-light text-muted">
                                                <tr>
                                                    <th class="py-2 ps-3" width="5%">No</th>
                                                    <th class="py-2" width="12%">Kode Barang</th>
                                                    <th class="py-2" width="18%">Nama Barang</th>
                                                    <th class="py-2 text-center" width="12%">Qty Keluar</th>
                                                    <th class="py-2 text-center" width="10%">Qty Retur</th>
                                                    <th class="py-2" width="15%">Catatan / S/N</th>
                                                    <th class="py-2 pe-3" width="28%">Spesifikasi Aset</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($gi->items as $det)

                                                @php
                                                    $rawNotes = $det->notes ?? '-';
                                                    $snPart = $rawNotes;
                                                    $spekPart = '-';

                                                    if (strpos($rawNotes, 'Spek:') !== false) {
                                                        $parts = explode('Spek:', $rawNotes);
                                                        $snPart = trim(str_replace(['➔', '->', '-'], '', $parts[0]));
                                                        $spekPart = trim($parts[1] ?? '-');
                                                    }
                                                @endphp

                                                <tr>
                                                    <td class="py-2 align-middle ps-3 text-muted">{{ $loop->iteration }}</td>
                                                    <td class="py-2 align-middle font-monospace text-muted">{{ optional($det->item)->code ?? '-' }}</td>
                                                    <td class="py-2 align-middle fw-bold text-dark">{{ optional($det->item)->name ?? '-' }}</td>

                                                    {{-- 🔥 BONUS: TAMBAHKAN SATUAN UOM DI SINI 🔥 --}}
                                                    <td class="py-2 text-center align-middle text-danger fw-bold">
                                                        <i class="bi bi-box-arrow-up-right me-1"></i>{{ (float)$det->qty_issued }} <span class="fw-normal text-muted" style="font-size: 0.75rem;">{{ optional(optional($det->item)->uom)->name ?? 'Unit' }}</span>
                                                    </td>

                                                    @if((float)$det->qty_returned > 0)
                                                        <td class="py-2 text-center align-middle text-warning fw-bold"><i class="bi bi-arrow-return-left me-1"></i>{{ (float)$det->qty_returned }}</td>
                                                    @else
                                                        <td class="py-2 text-center align-middle text-muted">0</td>
                                                    @endif

                                                    <td class="py-2 align-middle">
                                                        <span class="px-2 py-1 border text-dark fw-bold badge bg-light border-secondary-subtle">{{ $snPart }}</span>
                                                    </td>

                                                    <td class="py-2 align-middle pe-3">
                                                        @if($spekPart !== '-')
                                                            <div class="p-2 border rounded text-muted bg-light scroll-spek" style="max-height: 80px; overflow-y: auto; font-size: 0.7rem; line-height: 1.4;">
                                                                {!! nl2br(e($spekPart)) !!}
                                                            </div>
                                                        @else
                                                            <span class="text-muted fst-italic">-</span>
                                                        @endif
                                                    </td>
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
                        <td colspan="7" class="py-5 text-center text-muted">
                            <i class="mb-3 opacity-50 bi bi-search fs-1 d-block"></i>
                            @if(request('search'))
                                <h6 class="fw-bold">Data tidak ditemukan!</h6>
                                <small>Pencarian untuk "<b>{{ request('search') }}</b>" tidak ada di riwayat Pengeluaran maupun rincian barang.</small>
                            @else
                                <h6 class="fw-bold">Gudang Masih Penuh.</h6>
                                <small>Belum ada riwayat pengeluaran barang untuk karyawan.</small>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINASI --}}
        @if($goodsIssues->hasPages())
        <div class="p-3 bg-white card-footer border-top rounded-bottom-4">
            {{ $goodsIssues->appends(request()->query())->links() }}
        </div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {

        // 🔥 UX Enhancement: Klik Baris Induk Untuk Expand/Collapse Detail 🔥
        $('.main-row').on('click', function(e) {
            if(e.target.tagName === 'BUTTON' || e.target.tagName === 'A' || $(e.target).closest('a').length || $(e.target).closest('button').length) {
                return;
            }
            let btn = $(this).find('[data-bs-toggle="collapse"]');
            if(btn.length) {
                btn.click();
            }
        });

        // 🔥 PERBAIKAN: CEK SESSION SLUG BUKAN ID 🔥
        @if(session('print_gi_slug'))
            Swal.fire({
                title: 'Pengeluaran Berhasil!',
                text: "Stok telah dipotong dari gudang. Cetak Berita Acara (BAST) Serah Terima sekarang?",
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-printer me-1"></i> Ya, Cetak BAST',
                cancelButtonText: 'Nanti Saja',
                borderRadius: '15px'
            }).then((result) => {
                if (result.isConfirmed) {
                    // 🔥 PERBAIKAN: GUNAKAN SLUG DI SINI 🔥
                    window.open("{{ route('goods-issues.show', session('print_gi_slug')) }}", '_blank');
                }
            });
        @endif
    });
</script>
@endpush
