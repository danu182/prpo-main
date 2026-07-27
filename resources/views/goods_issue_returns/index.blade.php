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
<div class="pb-5 container-fluid text-dark">
    <div class="gap-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="bi bi-arrow-return-left text-warning me-2"></i> Riwayat Pengembalian (Retur GI)
            </h4>
            <div class="mt-1 text-muted small">Catatan barang operasional yang dikembalikan ke gudang.</div>
        </div>

        <form action="{{ route('goods-issue-returns.index') }}" method="GET" class="d-flex" style="min-width: 300px;">
            <div class="overflow-hidden border shadow-sm input-group rounded-pill">
                <input type="text" name="search" class="px-4 bg-white border-0 form-control" placeholder="Cari No. Retur, Pengembali..." value="{{ request('search') }}">
                <button class="px-4 text-white border-0 btn btn-warning fw-bold" type="submit">
                    <i class="bi bi-search text-dark"></i>
                </button>
            </div>
        </form>
    </div>

    <div class="border-0 border-4 shadow-sm card border-top border-warning rounded-4">
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light text-muted small text-uppercase fw-bold border-bottom">
                    <tr>
                        <th class="py-3 ps-4" width="20%">No. Retur</th>
                        <th class="py-3" width="15%">Tgl Kembali</th>
                        <th class="py-3" width="15%">Oleh</th>
                        <th class="py-3" width="20%">Gudang Retur</th>
                        <th class="py-3" width="15%">Ref. GI</th>
                        <th class="py-3 pe-4 text-end" width="15%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($returns as $index => $ret)
                    {{-- BARIS INDUK (Bisa diklik untuk expand) --}}
                    <tr class="main-row border-bottom">
                        <td class="py-3 ps-4">
                            <div class="fw-bold text-dark fs-6">{{ $ret->return_number }}</div>
                            <div class="small text-muted"><i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($ret->created_at)->format('H:i') }} WIB</div>
                        </td>
                        <td class="py-3">
                            <div class="fw-bold text-secondary">{{ \Carbon\Carbon::parse($ret->return_date)->format('d M Y') }}</div>
                        </td>
                        <td class="py-3">
                            <div class="fw-bold text-dark"><i class="bi bi-person-badge me-2 text-info"></i>{{ $ret->returned_by_name }}</div>
                        </td>
                        <td class="py-3">
                            <span class="px-3 py-2 border badge bg-secondary-subtle text-secondary border-secondary-subtle rounded-pill">
                                <i class="bi bi-shop me-1"></i> {{ optional($ret->warehouse)->name ?? 'Gudang Utama' }}
                            </span>
                        </td>
                        <td class="py-3 text-muted">
                            <a href="{{ route('goods-issues.show', $ret->goods_issue_id) }}" target="_blank" class="text-decoration-none fw-bold text-primary">
                                <i class="bi bi-box-arrow-up-right me-1"></i> {{ optional($ret->goodsIssue)->gi_number }}
                            </a>
                        </td>

                        {{-- KOLOM AKSI --}}
                        <td class="py-3 pe-4 text-end text-nowrap">
                            <div class="gap-2 d-flex justify-content-end align-items-center">
                                <a href="{{ route('goods-issue-returns.show', $ret->id) }}" class="shadow-sm btn btn-sm btn-outline-secondary rounded-pill fw-bold" title="Lihat Detail Penuh">
                                    <i class="bi bi-eye-fill me-1"></i> Detail
                                </a>
                                <a href="{{ route('goods-issue-returns.print', $ret->id) }}" class="shadow-sm btn btn-sm btn-warning text-dark rounded-pill fw-bold" target="_blank" title="Cetak BAST Retur">
                                    <i class="bi bi-printer-fill"></i>
                                </a>
                                <button class="shadow-sm btn btn-sm btn-dark rounded-pill fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $index }}" aria-expanded="false">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                            </div>
                        </td>
                    </tr>

                    {{-- BARIS ANAK (Rincian Barang Retur yang Tersembunyi) --}}
                    <tr class="collapse-row">
                        <td colspan="6" class="p-0 border-0">
                            <div class="collapse" id="collapse-{{ $index }}">
                                <div class="p-4 m-3 inner-collapse-box rounded-3">
                                    <div class="mb-3 d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check text-warning me-2"></i>Rincian Barang yang Diretur</h6>
                                    </div>

                                    @php
                                        // 🔥 Tambahkan relasi goodsIssueItem agar bisa narik nama dari GI
                                        $detailItems = \App\Models\GoodsIssueReturnItem::with(['item', 'goodsIssueItem'])->where('goods_issue_return_id', $ret->id)->get();
                                    @endphp

                                    <div class="border rounded table-responsive border-secondary-subtle">
                                        <table class="table mb-0 table-sm table-hover small">
                                            <thead class="bg-light text-muted">
                                                <tr>
                                                    <th class="py-2 ps-3" width="5%">No</th>
                                                    <th class="py-2" width="15%">Kode Barang</th>
                                                    <th class="py-2" width="30%">Nama Barang</th>
                                                    <th class="py-2 text-center" width="15%">Qty Dikembalikan</th>
                                                    <th class="py-2 pe-3" width="35%">Keterangan / Kondisi / S/N</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($detailItems as $det)
                                                @php
                                                    $masterName = optional($det->item)->name ?? '-';
                                                    $specificName = $det->item_name ?? optional($det->goodsIssueItem)->item_name ?? $masterName;
                                                @endphp
                                                <tr>
                                                    <td class="py-2 ps-3 text-muted">{{ $loop->iteration }}</td>
                                                    <td class="py-2 font-monospace text-muted">{{ optional($det->item)->code ?? '-' }}</td>

                                                    {{-- 🔥 MENAMPILKAN KEDUA NAMA (SPESIFIK & MASTER) 🔥 --}}
                                                    <td class="py-2">
                                                        <div class="fw-bold text-dark">{{ $specificName }}</div>
                                                        @if(strtolower(trim($specificName)) !== strtolower(trim($masterName)))
                                                            <div class="text-muted" style="font-size: 0.7rem;">
                                                                <i class="bi bi-box me-1"></i>Master: {{ $masterName }}
                                                            </div>
                                                        @endif
                                                    </td>

                                                    <td class="py-2 text-center text-danger fw-bold"><i class="bi bi-box-arrow-in-down me-1"></i>{{ (float)$det->qty_returned }}</td>
                                                    <td class="py-2 pe-3 text-muted fst-italic">{{ $det->notes ?? 'Tidak ada catatan' }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    @if($ret->notes)
                                    <div class="p-3 mt-3 rounded bg-light text-muted small">
                                        <span class="fw-bold">Catatan General:</span> {{ $ret->notes }}
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-5 text-center text-muted">
                            <i class="mb-2 opacity-50 bi bi-inbox fs-1 d-block"></i> Belum ada riwayat retur pengeluaran.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($returns->hasPages())
        <div class="p-3 bg-white card-footer border-top rounded-bottom-4">
            {{ $returns->links() }}
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        $('.main-row').on('click', function(e) {
            if(e.target.tagName === 'BUTTON' || e.target.tagName === 'A' || $(e.target).closest('a').length || $(e.target).closest('button').length) {
                return;
            }
            let btn = $(this).find('[data-bs-toggle="collapse"]');
            if(btn.length) {
                btn.click();
            }
        });

        @if(session('print_ret_id'))
            Swal.fire({
                title: 'Retur Berhasil!',
                text: "Stok telah dikembalikan ke gudang. Cetak Berita Acara (BAST) Retur sekarang?",
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-printer me-1"></i> Ya, Cetak',
                cancelButtonText: 'Nanti',
                borderRadius: '15px'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.open("{{ route('goods-issue-returns.show', session('print_ret_id')) }}", '_blank');
                }
            });
        @elseif(session('success'))
            Swal.fire('Berhasil!', "{{ session('success') }}", 'success');
        @endif
    });
</script>
@endpush
