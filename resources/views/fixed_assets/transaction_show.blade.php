@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
@endpush

@section('content')
@php
    $isDisposedTrx = $transaction->transaction_type === 'STATUS_CHANGE' && optional($transaction->status)->slug === 'disposed';
    $isPrintable   = in_array($transaction->transaction_type, ['HANDOVER', 'RETURN', 'REPAIR_SEND', 'REPAIR_FINISH']) || $isDisposedTrx;
    $docLabel      = match ($transaction->transaction_type) {
        'HANDOVER'      => 'BAST',
        'RETURN'        => 'BAPA',
        'REPAIR_SEND'   => 'BASP (Perbaikan)',
        'REPAIR_FINISH' => 'BAPA-SRV (Selesai Servis)',
        default         => ($isDisposedTrx ? 'BAPP (Penghapusan Aset)' : 'Dokumen'),
    };
    $typeLabel     = match ($transaction->transaction_type) {
        'HANDOVER'      => 'Penyerahan Aset ke Staf (BAST)',
        'RETURN'        => 'Pengembalian Aset ke Gudang (BAPA)',
        'REPAIR_SEND'   => 'Kirim Perbaikan ke Service Center (BASP)',
        'REPAIR_FINISH' => 'Selesai Perbaikan / Masuk Gudang (BAPA-SRV)',
        default         => ($isDisposedTrx ? 'Penghapusan Aset (BAPP / Disposal)' : 'Update Status Aset'),
    };

    // Parse info Service Center jika transaksi REPAIR_SEND
    $scVendor = '-';
    $scRma    = '-';
    $scIssue  = $transaction->notes ?: '-';
    if ($transaction->transaction_type === 'REPAIR_SEND' && str_contains((string) $transaction->notes, 'Service Center:')) {
        foreach (explode('|', $transaction->notes) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 'Service Center:')) {
                $scVendor = trim(str_replace('Service Center:', '', $part));
            } elseif (str_starts_with($part, 'No. RMA/Tiket:')) {
                $scRma = trim(str_replace('No. RMA/Tiket:', '', $part));
            } elseif (str_starts_with($part, 'Keluhan/Keterangan:')) {
                $scIssue = trim(str_replace('Keluhan/Keterangan:', '', $part));
            }
        }
    }
@endphp

<div class="pb-5 container-fluid px-md-4">
    @if(session('success'))
        <div class="border-0 border-4 shadow-sm alert alert-success alert-dismissible fade show rounded-4 border-start border-success">
            <i class="bi bi-check-circle-fill me-2 text-success"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <a href="{{ route('fixed-assets.transactions') }}" class="mb-2 border btn btn-sm btn-light rounded-pill fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Transaksi
            </a>
            <div class="flex-wrap gap-2 d-flex align-items-center">
                <h3 class="mb-0 fw-bold text-dark font-monospace">{{ $transaction->transaction_number }}</h3>
                <span class="px-3 py-1 border badge bg-primary-subtle text-primary border-primary-subtle">{{ $typeLabel }}</span>
            </div>
            <p class="mt-1 mb-0 text-muted small">
                Diproses pada <strong>{{ $transaction->created_at->format('d M Y H:i') }} WIB</strong> oleh <strong>{{ optional($transaction->creator)->name ?? 'System' }}</strong>
            </p>
        </div>

        <div class="gap-2 mt-3 d-flex mt-md-0">
            @if($isPrintable)
                <button type="button" class="px-4 shadow-sm btn btn-dark fw-bold rounded-pill" data-bs-toggle="modal" data-bs-target="#printSignersModal">
                    <i class="bi bi-printer me-1"></i> Cetak Dokumen {{ $docLabel }}
                </button>
            @endif
        </div>
    </div>

    {{-- BARIS ATAS: INFORMASI DOKUMEN & PIHAK TERKAIT --}}
    <div class="mb-4 row g-4">
        <div class="col-lg-6">
            <div class="p-4 bg-white border shadow-sm rounded-4 h-100">
                <h6 class="pb-2 mb-3 fw-bold text-uppercase text-secondary border-bottom">
                    <i class="bi bi-file-text me-1 text-primary"></i> 1. Informasi Dokumen Transaksi
                </h6>
                <table class="table mb-0 table-borderless table-sm">
                    <tr>
                        <td class="text-muted" width="38%">No. Dokumen</td>
                        <td class="fw-bold font-monospace text-primary">{{ $transaction->transaction_number }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Jenis Transaksi</td>
                        <td class="fw-bold">{{ $typeLabel }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Tanggal Transaksi</td>
                        <td class="fw-bold">{{ \Carbon\Carbon::parse($transaction->transaction_date)->translatedFormat('d F Y') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status Aset Ditetapkan</td>
                        <td><span class="px-2.5 py-1 badge bg-secondary">{{ optional($transaction->status)->name ?? '-' }}</span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Total Unit Diproses</td>
                        <td class="fw-bold">{{ $transaction->details->count() }} Unit Aset</td>
                    </tr>
                    @if($transaction->transaction_type === 'REPAIR_SEND')
                        <tr>
                            <td class="text-muted">Service Center / Vendor</td>
                            <td class="fw-bold text-info-emphasis">{{ $scVendor }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">No. RMA / Tiket Servis</td>
                            <td class="fw-bold font-monospace">{{ $scRma }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Keluhan Kerusakan</td>
                            <td>{{ $scIssue }}</td>
                        </tr>
                    @else
                        <tr>
                            <td class="text-muted">Catatan / Keterangan</td>
                            <td>{{ $transaction->notes ?: '-' }}</td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="p-4 bg-white border shadow-sm rounded-4 h-100">
                <h6 class="pb-2 mb-3 fw-bold text-uppercase text-secondary border-bottom">
                    <i class="bi bi-people me-1 text-primary"></i> 2. Pihak Terkait & Lokasi Tujuan
                </h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-light h-100">
                            <div class="mb-1 small text-uppercase fw-bold text-muted" style="font-size: 0.72rem;">Pihak Pertama (Penyerah / PIC)</div>
                            <div class="fw-bold text-dark">{{ optional($transaction->fromUser)->name ?? '-' }}</div>
                            <div class="small text-muted">{{ optional($transaction->fromUser)->job_title ?? 'Karyawan' }}</div>
                            @if(optional($transaction->fromUser)->department)
                                <div class="mt-1 small text-secondary">
                                    <i class="bi bi-diagram-3 me-1"></i>{{ $transaction->fromUser->department->name }}
                                </div>
                            @endif
                            @if(optional($transaction->fromUser)->company)
                                <div class="small text-secondary">
                                    <i class="bi bi-building me-1"></i>{{ $transaction->fromUser->company->name }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-light h-100">
                            <div class="mb-1 small text-uppercase fw-bold text-muted" style="font-size: 0.72rem;">Pihak Kedua / Tujuan Aset</div>
                            @if($transaction->transaction_type === 'REPAIR_SEND')
                                <div class="fw-bold text-info-emphasis"><i class="bi bi-tools me-1"></i>{{ $scVendor }}</div>
                                <div class="small text-muted">Pelaksana Perbaikan Eksternal</div>
                                <div class="mt-1 small text-secondary font-monospace">RMA: {{ $scRma }}</div>
                            @elseif($transaction->toUser && $transaction->transaction_type === 'HANDOVER')
                                <div class="fw-bold text-primary">{{ $transaction->toUser->name }}</div>
                                <div class="small text-muted">{{ $transaction->toUser->job_title ?? 'Karyawan' }}</div>
                                @if($transaction->toUser->department)
                                    <div class="mt-1 small text-secondary">
                                        <i class="bi bi-diagram-3 me-1"></i>{{ $transaction->toUser->department->name }}
                                    </div>
                                @endif
                                @if($transaction->toUser->company)
                                    <div class="small text-secondary">
                                        <i class="bi bi-building me-1"></i>{{ $transaction->toUser->company->name }}
                                    </div>
                                @endif
                            @elseif($transaction->warehouse)
                                <div class="fw-bold text-success"><i class="bi bi-shop me-1"></i>{{ $transaction->warehouse->name }}</div>
                                <div class="small text-muted">Gudang Penyimpanan Aset</div>
                                @if($transaction->toUser)
                                    <div class="mt-1 small text-secondary">Diterima oleh: {{ $transaction->toUser->name }}</div>
                                @endif
                            @else
                                <div class="fw-bold text-dark">{{ $isDisposedTrx ? 'Penghapusan Inventaris (Disposed)' : '-' }}</div>
                                <div class="small text-muted">Perubahan Status Internal</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- BARIS BAWAH: TABEL LENGKAP RINCIAN ASET --}}
    <div class="p-4 bg-white border shadow-sm rounded-4">
        <div class="pb-2 mb-3 d-flex justify-content-between align-items-center border-bottom">
            <h6 class="mb-0 fw-bold text-uppercase text-secondary">
                <i class="bi bi-pc-display me-1 text-primary"></i> 3. Daftar Lengkap Unit Aset dalam Dokumen Ini ({{ $transaction->details->count() }} Unit)
            </h6>
        </div>

        <div class="table-responsive">
            <table class="table mb-0 align-middle table-bordered table-hover">
                <thead class="bg-light small text-uppercase">
                    <tr>
                        <th width="4%" class="text-center">No</th>
                        <th width="18%">Identitas & Kode Aset</th>
                        <th width="24%">Nama Unit, Master Item & Spesifikasi</th>
                        <th width="14%">Foto & Lampiran</th>
                        <th width="14%">Perolehan & Nilai</th>
                        <th width="15%">Status & Lokasi Terkini</th>
                        <th width="6%">Ref</th>
                        <th width="5%" class="text-center">Log</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transaction->details as $idx => $det)
                    @php
                        $ast          = $det->asset;
                        $kodeItem     = optional(optional($ast)->item)->code ?: '-';
                        $namaItem     = optional(optional($ast)->item)->name ?: '-';
                        $kategoriAset = optional(optional($ast)->assetCategory)->name
                                        ?? optional(optional(optional($ast)->item)->category)->name
                                        ?? '-';
                        $perusahaan   = optional(optional($ast)->company)->name ?: '-';
                        $rawSpek      = optional($ast)->spesifikasi_detail ?: optional(optional($ast)->item)->specification;
                        $cleanSpek    = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string) $rawSpek))));

                        $currCode = strtoupper(optional(optional($ast)->currency)->code ?? 'IDR');
                        $currSym  = optional(optional($ast)->currency)->symbol ?? ($currCode === 'IDR' ? 'Rp' : $currCode);
                        $currDec  = ($currCode === 'IDR') ? 0 : 2;

                        $refTrx = null;
                        if ($ast) {
                            if ($transaction->transaction_type === 'HANDOVER') {
                                $refTrx = \App\Models\FixedAssetTransaction::where('transaction_type', 'RETURN')
                                    ->where('id', '>', $transaction->id)
                                    ->when($transaction->to_user_id, fn($q) => $q->where('from_user_id', $transaction->to_user_id))
                                    ->where(function ($q) use ($ast) {
                                        $q->where('fixed_asset_id', $ast->id)
                                          ->orWhereHas('details', fn($qd) => $qd->where('fixed_asset_id', $ast->id));
                                    })
                                    ->orderBy('id', 'asc')
                                    ->first();
                            } elseif ($transaction->transaction_type === 'RETURN') {
                                $refTrx = \App\Models\FixedAssetTransaction::where('transaction_type', 'HANDOVER')
                                    ->where('id', '<', $transaction->id)
                                    ->where(function ($q) use ($ast) {
                                        $q->where('fixed_asset_id', $ast->id)
                                          ->orWhereHas('details', fn($qd) => $qd->where('fixed_asset_id', $ast->id));
                                    })
                                    ->orderBy('id', 'desc')
                                    ->first();
                            }
                        }
                    @endphp
                    <tr>
                        <td class="text-center text-muted">{{ $idx + 1 }}</td>

                        {{-- 1. IDENTITAS & KODE ASET --}}
                        <td>
                            <div class="fw-bold text-primary font-monospace">{{ optional($ast)->asset_number ?? '-' }}</div>
                            <div class="mt-1 small">
                                <span class="border badge bg-secondary-subtle text-dark font-monospace">
                                    FA: {{ optional($ast)->accounting_asset_number ?: '-' }}
                                </span>
                            </div>
                            <div class="mt-1 small font-monospace text-muted">
                                <i class="bi bi-upc-scan me-1"></i>S/N: <strong class="text-dark">{{ optional($ast)->serial_number ?: '-' }}</strong>
                            </div>
                            <div class="mt-1 small text-muted" style="font-size: 0.74rem;">
                                <i class="bi bi-building me-1"></i>{{ $perusahaan }}
                            </div>
                        </td>

                        {{-- 2. NAMA UNIT, MASTER ITEM & SPESIFIKASI --}}
                        <td>
                            <div class="fw-bold text-dark">{{ optional($ast)->name ?? '-' }}</div>
                            <div class="mt-1 small">
                                <span class="border badge bg-info-subtle text-info-emphasis border-info-subtle font-monospace">{{ $kodeItem }}</span>
                                <span class="text-dark fw-semibold">{{ $namaItem }}</span>
                            </div>
                            <div class="mt-1 small text-muted" style="font-size: 0.75rem;">
                                Kategori: <strong class="text-dark">{{ $kategoriAset }}</strong>
                            </div>
                            @if(!empty($cleanSpek) && strtolower($cleanSpek) !== 'item dibuat otomatis dari import aset')
                                <div class="p-1.5 mt-1.5 border rounded bg-light text-muted" style="font-size: 0.74rem; line-height: 1.3;">
                                    {{ \Illuminate\Support\Str::limit($cleanSpek, 140) }}
                                </div>
                            @endif
                        </td>

                        {{-- 3. FOTO & LAMPIRAN FISIK ASET --}}
                        <td>
                            @php
                                $assetPhotos = $ast ? $ast->photos : collect();
                                $hasSupportDoc = $ast && !empty($ast->supporting_document);
                            @endphp

                            @if($assetPhotos->count() > 0 || $hasSupportDoc)
                                <div class="flex-wrap gap-1.5 d-flex align-items-center">
                                    @foreach($assetPhotos as $photo)
                                        @php
                                            $ext = strtolower(pathinfo($photo->file_path, PATHINFO_EXTENSION));
                                            $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                        @endphp
                                        @if($isImg)
                                            <a href="{{ asset('storage/' . $photo->file_path) }}" target="_blank" class="border rounded shadow-sm d-inline-block overflow-hidden" title="Klik untuk memperbesar foto">
                                                <img src="{{ asset('storage/' . $photo->file_path) }}" alt="Foto Aset" style="width: 48px; height: 48px; object-fit: cover;">
                                            </a>
                                        @else
                                            <a href="{{ asset('storage/' . $photo->file_path) }}" target="_blank" class="border rounded bg-light d-flex flex-column align-items-center justify-content-center text-decoration-none" style="width: 48px; height: 48px;" title="Buka Lampiran ({{ strtoupper($ext) }})">
                                                <i class="bi {{ $ext === 'pdf' ? 'bi-file-earmark-pdf-fill text-danger' : 'bi-file-earmark-text-fill text-primary' }} fs-5"></i>
                                                <span class="text-dark fw-bold" style="font-size: 0.6rem;">{{ strtoupper($ext) }}</span>
                                            </a>
                                        @endif
                                    @endforeach

                                    @if($hasSupportDoc)
                                        <a href="{{ asset('storage/' . $ast->supporting_document) }}" target="_blank" class="border rounded bg-warning-subtle d-flex flex-column align-items-center justify-content-center text-decoration-none" style="width: 48px; height: 48px;" title="Dokumen BAST / Nota Awal">
                                            <i class="bi bi-file-earmark-check-fill text-warning-emphasis fs-5"></i>
                                            <span class="text-dark fw-bold" style="font-size: 0.58rem;">DOC</span>
                                        </a>
                                    @endif
                                </div>
                            @else
                                <span class="small text-muted fst-italic">Belum ada foto</span>
                            @endif
                        </td>

                        {{-- 4. STATUS & LOKASI TERKINI ASET --}}
                        <td class="small">
                            <div class="mb-1">
                                <span class="badge bg-dark">{{ optional(optional($ast)->status)->name ?? '-' }}</span>
                            </div>
                            @if($ast && $ast->assignee)
                                <div class="text-primary fw-semibold">
                                    <i class="bi bi-person-fill me-1"></i>{{ $ast->assignee->name }}
                                </div>
                                <div class="text-muted" style="font-size: 0.73rem;">
                                    {{ optional($ast->assignee->department)->name ?? '-' }}
                                </div>
                            @elseif($ast && $ast->warehouse)
                                <div class="text-success fw-semibold">
                                    <i class="bi bi-shop me-1"></i>{{ $ast->warehouse->name }}
                                </div>
                            @else
                                <div class="text-muted fst-italic" style="font-size: 0.74rem;">
                                    Di luar gudang (Servis / Disposed)
                                </div>
                            @endif

                            @if($det->previous_user_id || $det->previous_warehouse_id)
                                <div class="pt-1 mt-1 border-top text-muted" style="font-size: 0.72rem;">
                                    Asal Sebelumnya:
                                    <strong class="text-dark">
                                        {{ $det->previous_user_id
                                            ? (optional(\App\Models\User::find($det->previous_user_id))->name ?? '-')
                                            : (optional(\App\Models\Warehouse::find($det->previous_warehouse_id))->name ?? 'Gudang') }}
                                    </strong>
                                </div>
                            @endif
                        </td>

                        {{-- 5. REFERENSI DOKUMEN TERKAIT --}}
                        <td class="small">
                            @if($transaction->transaction_type === 'HANDOVER')
                                @if($refTrx)
                                    <a href="{{ route('fixed-assets.transactions.show', $refTrx->id) }}" class="border badge bg-danger-subtle text-danger border-danger-subtle text-decoration-none font-monospace">
                                        <i class="bi bi-box-arrow-in-left me-1"></i>{{ $refTrx->transaction_number }}
                                    </a>
                                    <div class="mt-1 text-muted" style="font-size: 0.73rem;">
                                        Retur: {{ \Carbon\Carbon::parse($refTrx->transaction_date)->format('d M Y') }}
                                    </div>
                                @else
                                    <span class="border badge bg-success-subtle text-success border-success-subtle">
                                        Aktif / Belum Retur
                                    </span>
                                @endif
                            @elseif($transaction->transaction_type === 'RETURN')
                                @if($refTrx)
                                    <a href="{{ route('fixed-assets.transactions.show', $refTrx->id) }}" class="border badge bg-primary-subtle text-primary border-primary-subtle text-decoration-none font-monospace">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>{{ $refTrx->transaction_number }}
                                    </a>
                                    <div class="mt-1 text-muted" style="font-size: 0.73rem;">
                                        Serah: {{ \Carbon\Carbon::parse($refTrx->transaction_date)->format('d M Y') }}
                                    </div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>

                        {{-- 6. TIMELINE --}}
                        <td class="text-center">
                            @if($ast)
                                <a href="{{ route('fixed-assets.history', $ast->id) }}" class="btn btn-sm btn-outline-dark rounded-pill" title="Lihat Riwayat Lengkap Aset">
                                    <i class="bi bi-clock-history"></i>
                                </a>
                            @endif
                        </td>

                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- MODAL PILIH PENANDA TANGAN DOKUMEN --}}
@if($isPrintable)
<div class="modal fade" id="printSignersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="border-0 shadow-lg modal-content rounded-4">
            <div class="modal-header {{ $isDisposedTrx ? 'bg-danger text-white' : ($transaction->transaction_type === 'HANDOVER' ? 'bg-dark text-white' : 'bg-warning text-dark') }} border-bottom-0 rounded-top-4">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-printer me-2"></i> Cetak {{ $docLabel }} ({{ $transaction->transaction_number }})
                </h6>
                <button type="button" class="btn-close {{ ($isDisposedTrx || $transaction->transaction_type === 'HANDOVER') ? 'btn-close-white' : '' }}" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('fixed-assets.transactions.print', $transaction->id) }}" method="GET" target="_blank">
                <div class="modal-body bg-light">
                    @php
                        $p1Id = $transaction->from_user_id ?? auth()->id();
                        $p2Id = $transaction->to_user_id ?? ($isDisposedTrx ? null : auth()->id());
                    @endphp
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Daftar Penanda Tangan <span class="text-danger">*</span></label>
                        <select name="signers[]" id="select2_signers" class="form-select" multiple required style="width: 100%;">
                            @foreach($users as $usr)
                                <option value="{{ $usr->id }}" {{ in_array($usr->id, array_filter([$p1Id, $p2Id])) ? 'selected' : '' }}>
                                    {{ $usr->name }} ({{ optional($usr->department)->name ?? '-' }})
                                </option>
                            @endforeach
                        </select>
                        <div class="mt-2 form-text small">
                            <i class="bi bi-info-circle text-primary"></i>
                            @if($isDisposedTrx)
                                <strong>Info BAPP:</strong> Urutan pertama sebagai <strong>Yang Mengajukan</strong>, urutan kedua sebagai <strong>Pemeriksa (Finance / GA)</strong>, dan urutan ketiga sebagai <strong>Menyetujui</strong>.
                            @elseif($transaction->transaction_type === 'REPAIR_SEND')
                                <strong>Info BASP:</strong> Pihak Pertama diisi oleh staf IT/Perusahaan (<strong>{{ optional($transaction->fromUser)->name ?? auth()->user()->name }}</strong>), sedangkan kolom Pihak Kedua disiapkan kosong untuk tanda tangan basah/stempel <strong>Service Center / Kurir</strong>.
                            @else
                                <strong>Otomatis Terisi:</strong> Pihak Pertama (<strong>{{ optional($transaction->fromUser)->name ?? auth()->user()->name }}</strong>) &amp; Pihak Kedua (<strong>{{ optional($transaction->toUser)->name ?? '-' }}</strong>). Tambahkan nama lain jika memerlukan kolom <strong>Saksi / Mengetahui</strong>.
                            @endif
                        </div>
                    </div>

                    {{-- PILIHAN MODE TANDA TANGAN --}}
                    <div class="mb-2">
                        <label class="form-label fw-bold text-dark small">Metode Tanda Tangan pada PDF <span class="text-danger">*</span></label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="signature_mode" id="sig_digital" value="digital" checked>
                                <label class="p-2 text-start btn btn-outline-primary w-100 rounded-3" for="sig_digital">
                                    <div class="fw-bold small"><i class="bi bi-pen-fill me-1"></i> Tanda Tangan Digital</div>
                                    <div style="font-size: 0.72rem;" class="opacity-75">Tampilkan gambar TTD dari profil user</div>
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="signature_mode" id="sig_manual" value="manual">
                                <label class="p-2 text-start btn btn-outline-secondary w-100 rounded-3" for="sig_manual">
                                    <div class="fw-bold small"><i class="bi bi-pencil-square me-1"></i> Tanda Tangan Manual</div>
                                    <div style="font-size: 0.72rem;" class="opacity-75">Kosongkan kolom untuk TTD basah</div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-white modal-footer border-top-0 rounded-bottom-4">
                    <button type="button" class="px-4 btn btn-light fw-bold rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="px-4 shadow-sm btn {{ $isDisposedTrx ? 'btn-danger' : ($transaction->transaction_type === 'HANDOVER' ? 'btn-dark' : 'btn-warning text-dark') }} fw-bold rounded-pill">
                        <i class="bi bi-printer me-1"></i> Cetak PDF
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('#printSignersModal').on('shown.bs.modal', function () {
            $('#select2_signers').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#printSignersModal'),
                placeholder: 'Pilih Karyawan Penanda Tangan...',
                allowClear: true
            });
        });
    });
</script>
@endpush
