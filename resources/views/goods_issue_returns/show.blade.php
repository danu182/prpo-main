@extends('layouts.app')

@section('content')
<div class="container pb-5 text-dark">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <a href="{{ route('goods-issue-returns.index') }}" class="mb-2 text-decoration-none text-muted small fw-bold d-inline-block">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Retur
            </a>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="bi bi-arrow-return-left text-danger me-2"></i> Detail Retur Barang
            </h4>
        </div>
        <div>
            {{-- TOMBOL CETAK PDF --}}
            <a href="{{ route('goods-issue-returns.print', $return->id) }}" target="_blank" class="px-4 shadow-sm btn btn-danger rounded-pill fw-bold">
                <i class="bi bi-printer-fill me-2"></i> Cetak Bukti Retur
            </a>
        </div>
    </div>

    <div class="mb-4 border-0 border-4 shadow-sm card rounded-4 border-top border-danger">
        <div class="p-4 card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <table class="table mb-0 table-sm table-borderless">
                        <tr><td width="35%" class="text-muted fw-bold">No. Dokumen Retur</td><td width="5%">:</td><td class="fw-bold fs-6 text-danger">{{ $return->return_number }}</td></tr>
                        <tr><td class="text-muted fw-bold">Referensi Dok. Keluar</td><td>:</td><td><a href="{{ route('goods-issues.show', $return->goodsIssue->gi_number) }}" class="fw-bold text-decoration-none">{{ $return->goodsIssue->gi_number }}</a></td></tr>
                        <tr><td class="text-muted fw-bold">Tanggal Retur</td><td>:</td><td>{{ \Carbon\Carbon::parse($return->return_date)->translatedFormat('d F Y') }}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table mb-0 table-sm table-borderless">
                        <tr><td width="35%" class="text-muted fw-bold">Dikembalikan Oleh</td><td width="5%">:</td><td class="fw-bold">{{ $return->returned_by_name }}</td></tr>
                        <tr><td class="text-muted fw-bold">Gudang Penerima</td><td>:</td><td><span class="border badge bg-success-subtle text-success-emphasis border-success">{{ optional($return->warehouse)->name ?? 'Gudang Utama' }}</span></td></tr>
                        <tr><td class="text-muted fw-bold">Admin Penerima</td><td>:</td><td>{{ optional($return->receiver)->name ?? '-' }}</td></tr>
                    </table>
                </div>
            </div>
            @if($return->notes)
            <div class="p-3 mt-3 border bg-light rounded-3 text-muted small fst-italic">
                <strong>Catatan:</strong> {{ $return->notes }}
            </div>
            @endif
        </div>
    </div>

    <div class="border-0 shadow-sm card rounded-4">
        <div class="px-4 py-3 bg-white card-header border-bottom">
            <h6 class="mb-0 fw-bold"><i class="bi bi-box-seam me-2 text-primary"></i>Rincian Barang yang Dikembalikan</h6>
        </div>
        <div class="p-0 card-body table-responsive">
            <table class="table mb-0 align-middle table-hover">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th width="5%" class="py-3 text-center ps-4">No</th>
                        <th width="35%" class="py-3">Nama Barang & Kode</th>
                        <th width="15%" class="py-3 text-center">Qty Retur</th>
                        <th width="45%" class="py-3 pe-4">Catatan / Serial Number</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($return->items as $index => $item)
                    @php
                        // Cek aman apakah item ini aset tetap
                        $isAsset = optional($item->item)->item_type_code === 'AST';

                        // 🔥 SIHIR PEMISAH SATUAN DARI CATATAN 🔥
                        $uomName = 'PCS'; // Default
                        $cleanNotes = [];

                        if($item->notes) {
                            $notesArray = explode(' | ', $item->notes);
                            foreach($notesArray as $noteLine) {
                                // Jika baris catatan mengandung kata "Satuan:"
                                if (\Illuminate\Support\Str::startsWith(trim($noteLine), 'Satuan:')) {
                                    // Ambil nama satuannya saja
                                    $uomName = trim(str_replace('Satuan:', '', $noteLine));
                                } elseif (!empty(trim($noteLine))) {
                                    // Sisa catatan lainnya dimasukkan ke array bersih
                                    $cleanNotes[] = trim($noteLine);
                                }
                            }
                        }
                    @endphp
                    <tr class="border-bottom">
                        <td class="text-center ps-4">{{ $index + 1 }}</td>
                        <td>
                            <strong class="text-dark">{{ optional($item->item)->name }}</strong>
                            <div class="mt-1 small text-muted">{{ optional($item->item)->code }}</div>
                            @if($isAsset)
                                <span class="mt-1 border badge bg-primary-subtle text-primary border-primary-subtle" style="font-size: 0.65rem;">[ASET TETAP]</span>
                            @endif
                        </td>
                        <td class="text-center align-middle">
                            <span class="fw-bold fs-5 text-danger">{{ (float) $item->qty_returned }}</span>
                            {{-- 🔥 Satuan diletakkan di sini, tepat di bawah angka 🔥 --}}
                            <div class="mt-1 text-muted fw-bold" style="font-size: 0.75rem;">{{ $uomName }}</div>
                        </td>
                        <td class="align-middle pe-4">
                            @if(count($cleanNotes) > 0)
                                <div class="gap-1 mt-2 d-flex flex-column">
                                    @foreach($cleanNotes as $noteLine)
                                        <div class="small text-muted">
                                            <i class="bi bi-check2-circle text-success me-1"></i> {{ $noteLine }}
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
