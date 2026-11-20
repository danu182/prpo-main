@extends('layouts.app')

@push('css')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
@endpush

@section('content')
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
            <h3 class="mb-0 fw-bold text-dark font-monospace">{{ $transaction->transaction_number }}</h3>
            <p class="mb-0 text-muted small">Diproses pada {{ $transaction->created_at->format('d M Y H:i') }} WIB oleh {{ optional($transaction->creator)->name ?? 'System' }}</p>
        </div>

        <div class="gap-2 mt-3 d-flex mt-md-0">
            @if(in_array($transaction->transaction_type, ['HANDOVER', 'RETURN']))
                <button type="button" class="px-4 shadow-sm btn btn-dark fw-bold rounded-pill" data-bs-toggle="modal" data-bs-target="#printSignersModal">
                    <i class="bi bi-printer me-1"></i> Cetak Dokumen {{ $transaction->transaction_type === 'HANDOVER' ? 'BAST' : 'BAPA' }}
                </button>
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="p-4 bg-white border shadow-sm rounded-4 h-100">
                <h6 class="pb-2 mb-3 fw-bold text-uppercase text-secondary border-bottom">Informasi Dokumen</h6>
                <table class="table mb-0 table-borderless table-sm">
                    <tr>
                        <td class="text-muted" width="42%">No. Transaksi</td>
                        <td class="fw-bold font-monospace text-primary">{{ $transaction->transaction_number }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Jenis Transaksi</td>
                        <td class="fw-bold">{{ $transaction->transaction_type }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Tanggal</td>
                        <td class="fw-bold">{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('d F Y') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Pihak Pertama</td>
                        <td class="fw-bold">{{ optional($transaction->fromUser)->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Pihak Kedua</td>
                        <td class="fw-bold">{{ optional($transaction->toUser)->name ?? optional($transaction->warehouse)->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status Aset</td>
                        <td><span class="badge bg-secondary">{{ optional($transaction->status)->name ?? '-' }}</span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Catatan</td>
                        <td>{{ $transaction->notes ?: '-' }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="p-4 bg-white border shadow-sm rounded-4 h-100">
                <h6 class="pb-2 mb-3 fw-bold text-uppercase text-secondary border-bottom">
                    Daftar Aset dalam Dokumen Ini ({{ $transaction->details->count() }} Unit)
                </h6>
                <div class="table-responsive">
                    <table class="table mb-0 align-middle table-bordered">
                        <thead class="bg-light small text-uppercase">
                            <tr>
                                <th width="5%" class="text-center">No</th>
                                <th width="22%">No. Aset & Label FA</th>
                                <th>Nama Barang</th>
                                <th width="16%">Serial Number</th>
                                <th width="20%">
                                    {{ $transaction->transaction_type === 'HANDOVER' ? 'Status / Ref. BAPA' : ($transaction->transaction_type === 'RETURN' ? 'Ref. BAST Asal' : 'Referensi') }}
                                </th>
                                <th width="10%" class="text-center">Timeline</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transaction->details as $idx => $det)
                            @php
                                $refTrx = null;
                                if ($det->asset) {
                                    if ($transaction->transaction_type === 'HANDOVER') {
                                        // Cari BAPA pengembalian setelah BAST ini oleh penerima BAST ini
                                        $refTrx = \App\Models\FixedAssetTransaction::where('transaction_type', 'RETURN')
                                            ->where('id', '>', $transaction->id)
                                            ->when($transaction->to_user_id, function ($q) use ($transaction) {
                                                $q->where('from_user_id', $transaction->to_user_id);
                                            })
                                            ->where(function ($q) use ($det) {
                                                $q->where('fixed_asset_id', $det->asset->id)
                                                  ->orWhereHas('details', fn($qd) => $qd->where('fixed_asset_id', $det->asset->id));
                                            })
                                            ->orderBy('id', 'asc')
                                            ->first();
                                    } elseif ($transaction->transaction_type === 'RETURN') {
                                        // Cari BAST penyerahan sebelum BAPA ini
                                        $refTrx = \App\Models\FixedAssetTransaction::where('transaction_type', 'HANDOVER')
                                            ->where('id', '<', $transaction->id)
                                            ->where(function ($q) use ($det) {
                                                $q->where('fixed_asset_id', $det->asset->id)
                                                  ->orWhereHas('details', fn($qd) => $qd->where('fixed_asset_id', $det->asset->id));
                                            })
                                            ->orderBy('id', 'desc')
                                            ->first();
                                    }
                                }
                            @endphp
                            <tr>
                                <td class="text-center text-muted">{{ $idx + 1 }}</td>
                                <td>
                                    <div class="fw-bold text-primary">{{ optional($det->asset)->asset_number ?? '-' }}</div>
                                    <div class="small text-muted">FA: {{ optional($det->asset)->accounting_asset_number ?? '-' }}</div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ optional($det->asset)->name ?? '-' }}</div>
                                    <div class="small text-muted">Master: {{ optional(optional($det->asset)->item)->name ?? '-' }}</div>
                                </td>
                                <td class="font-monospace small">{{ optional($det->asset)->serial_number ?: '-' }}</td>
                                <td>
                                    @if($transaction->transaction_type === 'HANDOVER')
                                        @if($refTrx)
                                            <a href="{{ route('fixed-assets.transactions.show', $refTrx->id) }}" class="border badge bg-danger-subtle text-danger border-danger-subtle text-decoration-none font-monospace">
                                                <i class="bi bi-box-arrow-in-left me-1"></i>{{ $refTrx->transaction_number }}
                                            </a>
                                            <div class="mt-1 small text-muted" style="font-size: 0.75rem;">
                                                Tgl Retur: {{ \Carbon\Carbon::parse($refTrx->transaction_date)->format('d M Y') }}
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
                                            <div class="mt-1 small text-muted" style="font-size: 0.75rem;">
                                                Tgl Serah: {{ \Carbon\Carbon::parse($refTrx->transaction_date)->format('d M Y') }}
                                            </div>
                                        @else
                                            <span class="small text-muted">-</span>
                                        @endif
                                    @else
                                        <span class="small text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($det->asset)
                                        <a href="{{ route('fixed-assets.history', $det->asset->id) }}" class="btn btn-sm btn-outline-dark rounded-pill" title="Lihat Timeline Aset">
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
    </div>
</div>

{{-- MODAL PILIH PENANDA TANGAN DOKUMEN BAST / BAPA --}}
@if(in_array($transaction->transaction_type, ['HANDOVER', 'RETURN']))
<div class="modal fade" id="printSignersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="border-0 shadow-lg modal-content rounded-4">
            <div class="modal-header {{ $transaction->transaction_type === 'HANDOVER' ? 'bg-dark text-white' : 'bg-warning text-dark' }} border-bottom-0 rounded-top-4">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-printer me-2"></i> Cetak {{ $transaction->transaction_type === 'HANDOVER' ? 'BAST' : 'BAPA' }} ({{ $transaction->transaction_number }})
                </h6>
                <button type="button" class="btn-close {{ $transaction->transaction_type === 'HANDOVER' ? 'btn-close-white' : '' }}" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('fixed-assets.transactions.print', $transaction->id) }}" method="GET" target="_blank">
                <div class="modal-body bg-light">
                    @php
                        $p1Id = $transaction->from_user_id ?? auth()->id();
                        $p2Id = $transaction->to_user_id ?? auth()->id();
                    @endphp
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Daftar Penanda Tangan <span class="text-danger">*</span></label>
                        <select name="signers[]" id="select2_signers" class="form-select" multiple required style="width: 100%;">
                            @foreach($users as $usr)
                                <option value="{{ $usr->id }}" {{ in_array($usr->id, [$p1Id, $p2Id]) ? 'selected' : '' }}>
                                    {{ $usr->name }} ({{ optional($usr->department)->name ?? '-' }})
                                </option>
                            @endforeach
                        </select>
                        <div class="mt-2 form-text small">
                            <i class="bi bi-info-circle text-primary"></i>
                            <strong>Otomatis Terisi:</strong> Pihak Pertama (<strong>{{ optional($transaction->fromUser)->name ?? auth()->user()->name }}</strong>) &amp; Pihak Kedua (<strong>{{ optional($transaction->toUser)->name ?? '-' }}</strong>). Tambahkan nama lain jika memerlukan kolom <strong>Saksi / Mengetahui</strong>.
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
                    <button type="submit" class="px-4 shadow-sm btn {{ $transaction->transaction_type === 'HANDOVER' ? 'btn-dark' : 'btn-warning text-dark' }} fw-bold rounded-pill">
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