@extends('layouts.app')

@push('css')
<style>
    /* Styling Avatar Inisial */
    .avatar-circle {
        width: 40px; height: 40px;
        background: linear-gradient(135deg, #0dcaf0, #0aa2c0);
        color: white;
        font-weight: bold;
        display: flex; align-items: center; justify-content: center;
        border-radius: 50%;
        font-size: 1rem;
        box-shadow: 0 2px 5px rgba(13, 202, 240, 0.3);
    }

    /* Hover Row Tabel */
    .table-hover > tbody > tr.main-row:hover > td {
        background-color: #f8f9fa !important;
        cursor: pointer;
    }

    /* Animasi Baris Expandable */
    .collapse-row td {
        padding: 0 !important;
        border-bottom: none;
        background-color: #fcfcfc;
    }
    .inner-collapse-box {
        border-left: 4px solid #0dcaf0;
        background-color: #ffffff;
        box-shadow: inset 0 4px 6px -6px rgba(0,0,0,0.1), inset 0 -4px 6px -6px rgba(0,0,0,0.1);
    }

    /* Desain List Barang Dalam Tabel */
    .item-list-group { border-radius: 8px; overflow: hidden; border: 1px solid #e9ecef; }
    .item-list-group .list-group-item { border-left: none; border-right: none; font-size: 0.85rem; padding: 10px 15px; }
    .item-list-group .list-group-item:first-child { border-top: none; }
    .item-list-group .list-group-item:last-child { border-bottom: none; }

    /* Scrollbar Minimalis */
    .custom-scrollbar::-webkit-scrollbar { width: 5px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f8f9fa; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #dee2e6; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #adb5bd; }
</style>
@endpush

@section('content')
<div class="container pb-5 text-dark">

    {{-- HEADER & PENCARIAN --}}
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="bi bi-person-lines-fill text-info me-2"></i> Inventaris & Aset Karyawan
            </h4>
            <div class="mt-1 text-muted small">Daftar rekapitulasi barang perusahaan yang dipegang oleh personil.</div>
        </div>

        <form action="{{ route('employee-inventories.index') }}" method="GET" class="d-flex" style="min-width: 350px;">
            <div class="overflow-hidden border shadow-sm input-group rounded-pill">
                <input type="text" name="search" class="px-4 bg-white border-0 form-control" placeholder="Cari nama karyawan..." value="{{ request('search') }}">
                <button class="px-4 text-white border-0 btn btn-info fw-bold" type="submit">
                    <i class="bi bi-search"></i>
                </button>
                @if(request('search'))
                    <a href="{{ route('employee-inventories.index') }}" class="px-3 btn btn-light border-start text-danger" title="Reset">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- MODERN DATA TABLE UNTUK 1000+ KARYAWAN --}}
    <div class="border-0 border-4 shadow-sm card rounded-4 border-top border-info">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light fw-bold text-muted border-bottom text-uppercase small" style="letter-spacing: 0.5px;">
                    <tr>
                        <th class="py-3 ps-4" width="30%">Identitas Karyawan</th>
                        <th class="py-3 text-center" width="15%">Aset Tetap (Major)</th>
                        <th class="py-3 text-center" width="15%">Inventaris (Minor)</th>
                        <th class="py-3 text-center" width="15%">Total Tanggungan</th>
                        <th class="py-3 pe-4 text-end" width="25%">Aksi & Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allNames as $index => $employeeName)
                        @php
                            // Filter data per nama
                            $minorItems = $inventories->get($employeeName, collect());
                            $majorItems = $fixedAssets->get($employeeName, collect());

                            // 🔥 PERBAIKAN: Gunakan sum('qty') agar yang dihitung adalah TOTAL FISIK (2 pcs)
                            $activeMinorCount = $minorItems->where('qty', '>', 0)->sum('qty');

                            // Major Asset tetap pakai count karena 1 baris = 1 unit
                            $majorCount = $majorItems->count();

                            $totalCount = $activeMinorCount + $majorCount;
                        @endphp

                        {{-- BARIS INDUK (Data Karyawan) --}}
                        <tr class="main-row border-bottom">
                            <td class="py-3 ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle me-3 flex-shrink-0">
                                        {{ strtoupper(substr($employeeName, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark fs-6">{{ $employeeName }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 text-center">
                                <span class="px-3 badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill fw-bold">
                                    {{ $majorCount }} Unit
                                </span>
                            </td>
                            <td class="py-3 text-center">
                                <span class="px-3 badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill fw-bold">
                                    {{ $activeMinorCount }} Item
                                </span>
                            </td>
                            <td class="py-3 text-center">
                                @if($totalCount > 0)
                                    <span class="px-3 badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill fw-bold fs-6 shadow-sm">
                                        {{ $totalCount }} Barang
                                    </span>
                                @else
                                    <span class="px-3 text-muted small fw-bold"><i class="bi bi-check2-circle text-success me-1"></i> Bersih</span>
                                @endif
                            </td>
                            <td class="py-3 pe-4 text-end">
                                <div class="gap-2 d-flex justify-content-end">
                                    @if($totalCount > 0)
                                        <button class="shadow-sm btn btn-sm btn-outline-dark rounded-pill fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $index }}" aria-expanded="false">
                                            <i class="bi bi-chevron-down me-1"></i> Rincian Barang
                                        </button>
                                    @endif
                                    <a href="{{ route('employee-inventories.history', $employeeName) }}" class="shadow-sm btn btn-sm btn-info text-white rounded-pill fw-bold" title="Riwayat Serah Terima">
                                        <i class="bi bi-clock-history me-1"></i> Riwayat
                                    </a>
                                </div>
                            </td>
                        </tr>

                        {{-- BARIS ANAK (Rincian Barang - Tersembunyi/Collapse) --}}
                        @if($totalCount > 0)
                        <tr class="collapse-row">
                            <td colspan="5" class="p-0 border-0">
                                <div class="collapse" id="collapse-{{ $index }}">
                                    <div class="p-4 m-3 inner-collapse-box rounded-3">
                                        <div class="row g-4">

                                            {{-- KOLOM ASET TETAP --}}
                                            <div class="col-md-6">
                                                <h6 class="mb-3 text-primary fw-bold small text-uppercase"><i class="bi bi-pc-display me-1"></i> Aset Tetap (Major)</h6>
                                                @if($majorCount > 0)
                                                    <div class="list-group item-list-group custom-scrollbar" style="max-height: 250px; overflow-y: auto;">
                                                        @foreach($majorItems as $asset)
                                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <div class="fw-bold text-dark">{{ optional($asset->item)->name }}</div>
                                                                    <div class="text-muted" style="font-size: 0.7rem;">{{ $asset->spesifikasi_detail ?? '-' }}</div>
                                                                </div>
                                                                <div class="text-end">
                                                                    <div class="badge bg-light text-dark border mb-1">{{ $asset->asset_number }}</div><br>
                                                                    <a href="{{ route('fixed-assets.print_qr', $asset->id) }}" target="_blank" class="btn btn-sm btn-link text-primary p-0" style="font-size: 0.7rem; text-decoration: none;">
                                                                        <i class="bi bi-qr-code"></i> Print Label
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="p-3 text-center border rounded border-light bg-light-subtle text-muted small">Tidak ada aset tetap.</div>
                                                @endif
                                            </div>

                                            {{-- KOLOM INVENTARIS MINOR --}}
                                            <div class="col-md-6">
                                                <h6 class="mb-3 text-secondary fw-bold small text-uppercase"><i class="bi bi-box-seam me-1"></i> Inventaris (Minor)</h6>
                                                @if($activeMinorCount > 0)
                                                    <div class="list-group item-list-group custom-scrollbar" style="max-height: 250px; overflow-y: auto;">
                                                        @foreach($minorItems as $inv)
                                                            @if($inv->qty > 0)
                                                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <div class="fw-bold text-dark">{{ optional($inv->item)->name }}</div>
                                                                        <div class="text-muted" style="font-size: 0.7rem;">Kode: {{ optional($inv->item)->code }}</div>
                                                                    </div>
                                                                    <div class="text-end">
                                                                        <div class="badge bg-light text-dark border mb-1">{{ (float)$inv->qty }} {{ optional($inv->item)->unit ?? 'pcs' }}</div><br>
                                                                        <a href="{{ route('employee-inventories.print_qr', $inv->id) }}" target="_blank" class="btn btn-sm btn-link text-secondary p-0" style="font-size: 0.7rem; text-decoration: none;">
                                                                            <i class="bi bi-qr-code"></i> Print Label
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="p-3 text-center border rounded border-light bg-light-subtle text-muted small">Tidak ada inventaris minor.</div>
                                                @endif
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endif

                    @empty
                        <tr>
                            <td colspan="5" class="py-5 text-center">
                                <div class="p-4 mx-auto max-w-sm">
                                    <i class="mb-3 opacity-25 bi bi-search text-muted display-4 d-block"></i>
                                    <h6 class="fw-bold text-dark">Karyawan tidak ditemukan</h6>
                                    <p class="mb-0 text-muted small">Belum ada data barang yang direkam atau coba kata kunci lain.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINATION (Jika controller menggunakan Paginate) --}}
        @if(method_exists($allNames, 'links'))
            <div class="p-3 bg-white border-0 card-footer rounded-bottom-4">
                {{ $allNames->links() }}
            </div>
        @endif
    </div>

</div>

@push('scripts')
<script>
    // UX Enhancement: Klik baris utama untuk otomatis expand rinciannya
    document.addEventListener('DOMContentLoaded', function () {
        const rows = document.querySelectorAll('.main-row');
        rows.forEach(row => {
            row.addEventListener('click', function(e) {
                // Jangan jalankan kalau yang diklik adalah tombol/link di dalam baris
                if(e.target.tagName === 'BUTTON' || e.target.tagName === 'A' || e.target.closest('a') || e.target.closest('button')) {
                    return;
                }
                const btn = this.querySelector('[data-bs-toggle="collapse"]');
                if(btn) {
                    btn.click();
                }
            });
        });
    });
</script>
@endpush

@endsection
