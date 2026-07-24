@extends('layouts.app')


@push('css')

<style>
    /* 🔥 DESIGN STEMPEL VOID SAKTI 🔥 */
    .void-stamp-container {
        position: absolute;
        top: 20%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-15deg);
        z-index: 9999;
        pointer-events: none;
        opacity: 0.15; /* Transparan agar tidak menutupi teks */
        display: none;
    }
    .is-void .void-stamp-container {
        display: block;
    }
    .void-stamp {
        border: 12px double #dc3545;
        color: #dc3545;
        font-family: 'Courier New', Courier, monospace;
        font-size: 100px;
        font-weight: 900;
        padding: 10px 40px;
        border-radius: 20px;
        text-transform: uppercase;
        letter-spacing: 15px;
        background-color: rgba(255, 255, 255, 0.5);
    }
</style>

@endpush

@section('content')
{{-- Tambahkan class 'is-void' jika statusnya void --}}
<div class="container pb-5 text-dark position-relative {{ optional($gi->status)->slug === 'void' ? 'is-void' : '' }}">

    {{-- Elemen Stempel --}}
    <div class="void-stamp-container">
        <div class="void-stamp">VOID</div>
    </div>

    {{-- Sisanya sama seperti sebelumnya... --}}

    {{-- HEADER HALAMAN & TOMBOL AKSI --}}
    <div class="mb-4 d-flex justify-content-between align-items-end">
        <div>
            <h4 class="mb-1 fw-bold text-dark">
                <i class="bi bi-box-arrow-up me-2 text-danger"></i>Detail Pengeluaran Barang
            </h4>
            <div class="text-muted small">
                Dokumen GI: <strong class="text-danger">{{ $gi->gi_number }}</strong>
            </div>
        </div>
        <div class="gap-2 d-flex">
            <a href="{{ route('goods-issues.index') }}" class="border shadow-sm btn btn-light rounded-pill fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>

            {{-- 🔥 Tampilkan tombol ini HANYA jika status belum VOID 🔥 --}}
            @if(optional($gi->status)->slug !== 'void')
                {{-- 🔥 PERBAIKAN: Gunakan gi_number (slug) bukan id 🔥 --}}
                <form action="{{ route('goods-issues.void', $gi->gi_number) }}" method="POST" class="d-inline form-void-gi">
                    @csrf
                    <button type="button" class="shadow-sm btn btn-outline-danger fw-bold rounded-pill btn-void">
                        <i class="bi bi-x-octagon-fill me-1"></i> Void Transaksi
                    </button>
                </form>

                {{-- 🔥 PERBAIKAN: Gunakan gi_number (slug) bukan id 🔥 --}}
                <a href="{{ route('goods-issues.print_labels', $gi->gi_number) }}" target="_blank" class="shadow-sm btn btn-dark rounded-pill fw-bold">
                    <i class="bi bi-upc-scan me-1"></i> Cetak Label
                </a>

                {{-- 🔥 PERBAIKAN: Gunakan gi_number (slug) bukan id 🔥 --}}
                {{-- Tombol Cetak Bukti Pengeluaran (Stok Biasa) --}}
                <a href="{{ route('goods-issues.print', $gi->gi_number) }}" target="_blank" class="px-3 shadow-sm btn btn-warning rounded-pill fw-bold text-dark">
                    <i class="bi bi-printer-fill me-1"></i> Cetak Bukti Keluar
                </a>

                {{-- Tombol Cetak BAST (Khusus Aset) --}}
                <a href="{{ route('goods-issues.bast', $gi->gi_number) }}" target="_blank" class="px-3 shadow-sm btn btn-danger rounded-pill fw-bold">
                    <i class="bi bi-file-earmark-check-fill me-1"></i> Cetak BAST
                </a>
            @else
                {{-- Tanda stempel merah kalau sudah Void --}}
                <span class="px-4 py-2 border border-2 shadow-sm badge bg-danger rounded-pill fs-6 border-danger d-flex align-items-center">
                    <i class="bi bi-ban me-2"></i> DOKUMEN VOID (BATAL)
                </span>
            @endif
        </div>
    </div>

    {{-- KARTU INFORMASI DOKUMEN --}}
    <div class="mb-4 border-0 shadow-sm card rounded-4 bg-light">
        <div class="p-4 card-body">
            <div class="row g-4">
                <div class="col-md-5 border-end">
                    <h6 class="mb-3 fw-bold text-secondary text-uppercase" style="font-size: 0.8rem;">Informasi Penyerahan</h6>
                    <table class="table mb-0 table-sm table-borderless small">

                        <tr>
                            <td class="align-middle text-muted" width="35%">Waktu Keluar</td>
                            <td class="align-middle fw-bold">:
                                {{ \Carbon\Carbon::parse($gi->issue_date)->format('d M Y') }}
                                <span class="ms-1 text-muted fw-normal" style="font-size: 0.75rem;">
                                    <i class="mx-1 bi bi-clock"></i>{{ \Carbon\Carbon::parse($gi->created_at)->format('H:i') }} WIB
                                </span>
                            </td>
                        </tr>

                        <tr><td class="align-middle text-muted">Penerima</td><td class="align-middle fw-bold text-primary">: {{ $gi->requester_name }}</td></tr>
                        <tr><td class="align-middle text-muted">Departemen</td><td class="align-middle fw-bold">: {{ $gi->department ?? '-' }}</td></tr>

                        <tr>
                            <td class="align-middle text-muted">Gudang Asal</td>
                            <td class="align-middle fw-bold">:
                                <span class="px-2 py-1 bg-white border shadow-sm badge text-secondary border-secondary-subtle ms-1">
                                    <i class="bi bi-shop me-1"></i> {{ optional($gi->warehouse)->name ?? 'Gudang Utama' }}
                                </span>
                            </td>
                        </tr>

                        <tr>
                            <td class="align-middle text-muted">Status Dokumen</td>
                            <td class="align-middle fw-bold">:
                                @if($gi->status)
                                    <span class="badge bg-{{ $gi->status->color }}-subtle text-{{ $gi->status->color }} border border-{{ $gi->status->color }}-subtle rounded-pill px-3 shadow-sm ms-1 mt-1">
                                        {{ $gi->status->name }}
                                    </span>
                                @else
                                    <span class="px-3 mt-1 badge bg-secondary-subtle text-secondary rounded-pill ms-1">-</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-7">
                    <h6 class="mb-3 fw-bold text-secondary text-uppercase" style="font-size: 0.8rem;">Catatan / Keperluan</h6>
                    <div class="p-3 bg-white border rounded-3 text-dark small" style="min-height: 90px;">
                        {{ $gi->notes ?: 'Tidak ada catatan khusus.' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TABEL BARANG DIKELUARKAN --}}
    <div class="overflow-hidden border-0 shadow-sm card rounded-4">
        <div class="py-3 bg-white card-header border-bottom">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check me-2 text-danger"></i>Rincian Barang Dikeluarkan</h6>
        </div>
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="py-3 ps-4" width="5%">No</th>
                        <th class="py-3" width="35%">Nama Barang & Kode</th>
                        <th class="py-3 text-center" width="10%">Qty Keluar</th>
                        <th class="py-3 text-center" width="10%">Qty Retur</th>
                        <th class="py-3 pe-4" width="40%">Catatan / SN Aset</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($gi->items as $index => $item)
                        <tr class="border-bottom">
                            <td class="py-3 ps-4 text-muted">{{ $index + 1 }}</td>
                            <td>
                                <h6 class="mb-1 fw-bold text-dark">{{ $item->item_name ?? optional($item->item)->name }}</h6>
                                <span class="border badge bg-secondary-subtle text-secondary border-secondary-subtle">{{ optional($item->item)->code }}</span>
                                @if(optional($item->item)->is_asset)
                                    <span class="border badge bg-primary-subtle text-primary border-primary-subtle ms-1">Aset Tetap</span>
                                @endif
                            </td>

                            {{-- TAMPILAN QTY KELUAR (SUDAH DITAMBAHKAN UOM) --}}
                            <td class="py-3 text-center fw-bold text-danger fs-5">
                                <i class="bi bi-box-arrow-up-right me-1"></i> {{ (float) $item->qty_issued }} <br>
                                <span class="text-nowrap fw-bold text-muted text-uppercase" style="font-size: 0.65rem;">
                                    {{-- Gunakan getRawOriginal untuk memanggil teks "Pack (Isi 10 Pieces)" dari tabel transaksi --}}
                                    {{ $item->getRawOriginal('uom') ?: (optional(optional($item->item)->uom)->name ?? 'PCS') }}
                                </span>
                            </td>

                            {{-- TAMPILAN QTY RETUR --}}
                            <td class="text-center fw-bold fs-6">
                                @if((float)$item->qty_returned > 0)
                                    <span class="text-warning"><i class="bi bi-arrow-return-left me-1"></i>{{ (float)$item->qty_returned }}</span>
                                @else
                                    <span class="opacity-50 text-muted">0</span>
                                @endif
                            </td>

                            <td class="pe-4 small text-muted" style="line-height: 1.5;">
                                @php
                                    $finalNotes = '-';
                                    if (optional($item->item)->is_asset && $item->notes) {
                                        preg_match_all('/AST\/[0-9]{4}\/[0-9]{2}\/[0-9]{4}/', $item->notes, $matches);
                                        $astNumbers = $matches[0];

                                        if (!empty($astNumbers)) {
                                            $liveAssets = \App\Models\FixedAsset::whereIn('asset_number', $astNumbers)->get();
                                            $formattedNotes = [];

                                            foreach ($liveAssets as $liveAst) {
                                                $info = "<strong class='text-dark'>" . $liveAst->asset_number . "</strong>";
                                                if($liveAst->serial_number) { $info .= " (SN: <span class='text-primary'>" . $liveAst->serial_number . "</span>)"; }
                                                if($liveAst->accounting_asset_number) { $info .= " [FA: " . $liveAst->accounting_asset_number . "]"; }
                                                if($liveAst->spesifikasi_detail) {
                                                    $info .= "<br><span class='text-muted' style='font-size:0.7rem;'>Spek: " . $liveAst->spesifikasi_detail . "</span>";
                                                }
                                                $formattedNotes[] = '• ' . $info;
                                            }
                                            $finalNotes = implode('<br><br>', $formattedNotes);
                                        } else {
                                            $finalNotes = str_replace(' | ', '<br>• ', '• ' . $item->notes);
                                        }
                                    } else {
                                        $finalNotes = $item->notes ? str_replace(' | ', '<br>• ', '• ' . $item->notes) : '-';
                                    }
                                @endphp
                                {!! $finalNotes !!}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- 🔥 KARTU RIWAYAT PENGEMBALIAN / RETUR (MUNCUL JIKA ADA) 🔥 --}}
    @if($gi->returns && $gi->returns->count() > 0)
    <div class="mt-4 overflow-hidden border-0 border-4 shadow-sm card rounded-4 border-top border-warning">
        <div class="py-3 bg-white card-header border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-arrow-return-left me-2 text-warning"></i>Riwayat Pengembalian (Document Flow)</h6>
            <span class="border shadow-sm badge bg-light text-dark"><i class="bi bi-receipt me-1"></i> {{ $gi->returns->count() }} Dokumen Retur</span>
        </div>
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="py-3 ps-4" width="20%">No. Dokumen Retur</th>
                        <th class="py-3" width="20%">Waktu Kembali</th>
                        <th class="py-3" width="25%">Dikembalikan Oleh</th>
                        <th class="py-3 pe-4" width="35%">Diterima di Gudang (Tujuan Retur)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($gi->returns as $ret)
                        <tr class="border-bottom">
                            <td class="py-3 ps-4">
                                <a href="{{ route('goods-issue-returns.show', $ret->id) }}" target="_blank" class="fw-bold text-warning-emphasis text-decoration-none">
                                    <i class="bi bi-link-45deg me-1"></i>{{ $ret->return_number }}
                                </a>
                            </td>

                            <td class="py-3">
                                <div class="text-dark fw-bold">{{ \Carbon\Carbon::parse($ret->return_date)->format('d M Y') }}</div>
                                <div class="mt-1 small text-muted">
                                    <i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($ret->created_at)->format('H:i') }} WIB
                                </div>
                            </td>

                            <td class="py-3 text-dark"><i class="bi bi-person-badge text-info me-1"></i> {{ $ret->returned_by_name }}</td>

                            <td class="py-3 pe-4">
                                <span class="px-3 py-2 border shadow-sm badge bg-warning-subtle text-warning-emphasis border-warning-subtle rounded-pill">
                                    <i class="bi bi-shop me-1"></i> {{ optional($ret->warehouse)->name ?? '-' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.querySelectorAll('.btn-void').forEach(btn => {
        btn.addEventListener('click', function(e) {
            let form = this.closest('.form-void-gi');
            Swal.fire({
                title: 'PERINGATAN KERAS! 🚨',
                html: "Anda akan membatalkan (VOID) dokumen ini. <br><br><b>Perhatian:</b><br>1. Stok barang akan otomatis dikembalikan ke gudang.<br>2. Transaksi beda bulan tidak bisa di-Void.<br>3. Tindakan ini tidak bisa dibatalkan!",
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, VOID Dokumen Ini!',
                cancelButtonText: 'Batal',
                customClass: { confirmButton: 'rounded-pill fw-bold px-4' },
                borderRadius: '15px'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
</script>
@endpush
