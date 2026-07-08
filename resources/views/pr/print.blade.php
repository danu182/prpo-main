<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak PR - {{ $pr->pr_number }}</title>
    <style>
        /* ==========================================
           PENGATURAN GAYA PDF (DOMPDF READY)
           ========================================== */
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10pt; color: #000; margin: 0; }
        .header-title { font-size: 16pt; font-weight: bold; margin: 0 0 5px 0; text-transform: uppercase; color: #0f172a; }
        .header-subtitle { font-size: 10pt; margin: 0; color: #475569; }
        .doc-title { font-size: 16pt; font-weight: bold; color: #0284c7; margin: 0; text-align: right; }
        .header-border { border-bottom: 2px solid #0f172a; margin-top: 15px; margin-bottom: 20px; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10pt; }
        .info-table td { padding: 4px 0; vertical-align: top; }
        .info-label { width: 120px; font-weight: bold; color: #334155; }
        .info-separator { width: 15px; text-align: center; font-weight: bold; }

        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 9pt; }
        .item-table th, .item-table td { border: 1px solid #334155; padding: 8px 6px; }
        .item-table th { background-color: #f1f5f9; text-align: center; font-weight: bold; text-transform: uppercase; }

        /* AREA TANDA TANGAN (MENGGUNAKAN TABEL AGAR AMAN DI PDF) */
        .signature-table { width: 100%; table-layout: fixed; margin-top: 30px; page-break-inside: avoid; }
        .signature-table td { text-align: center; vertical-align: bottom; padding: 0 5px; font-size: 10pt; }
        .sign-space { height: 80px; text-align: center; padding: 10px 0; }

        .signature-img { max-height: 70px; max-width: 130px; object-fit: contain; }

        /* STEMPEL TEKS (JIKA TIDAK ADA FOTO) */
        .stamp { font-weight: bold; padding: 5px 10px; border-radius: 5px; display: inline-block; font-size: 10pt; letter-spacing: 1px; }
        .stamp-approved { color: #198754; border: 2px solid #198754; }
        .stamp-rejected { color: #dc3545; border: 2px solid #dc3545; }
        .stamp-pending { color: #6c757d; border: 2px dashed #6c757d; }

        .print-footer { margin-top: 40px; font-size: 8pt; color: #64748b; text-align: right; font-style: italic; border-top: 1px solid #cbd5e1; padding-top: 8px; }
    </style>
</head>
<body>

    <table style="width: 100%;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <h3 class="header-title">{{ optional($pr->company)->name ?? 'PERUSAHAAN INTERNAL' }}</h3>
                <p class="header-subtitle">Dokumen Pengadaan Barang & Jasa Internal</p>
            </td>
            <td style="width: 40%; vertical-align: top; text-align: right;">
                <h4 class="doc-title">PURCHASE REQUEST (PR)</h4>
            </td>
        </tr>
    </table>

    <div class="header-border"></div>

    <table class="info-table">
        <tr>
            <td class="info-label">Nomor PR</td><td class="info-separator">:</td>
            <td style="width: 40%; font-weight: bold;">{{ $pr->pr_number }}</td>
            <td class="info-label">Tanggal Request</td><td class="info-separator">:</td>
            <td>{{ \Carbon\Carbon::parse($pr->request_date)->translatedFormat('d F Y') }}</td>
        </tr>
        <tr>
            <td class="info-label">User Peminta</td><td class="info-separator">:</td>
            <td>{{ optional($pr->user)->name ?? '-' }}</td>
            <td class="info-label">Dibutuhkan Pada</td><td class="info-separator">:</td>
            <td style="font-weight: bold; color: #b91c1c;">{{ \Carbon\Carbon::parse($pr->need_date)->translatedFormat('d F Y') }}</td>
        </tr>
        <tr>
            <td class="info-label">Unit / Dept</td><td class="info-separator">:</td>
            <td>
                {{-- Tampilkan Nama Company --}}
                <span style="font-weight: bold;">{{ optional($pr->company)->name ?? '-' }}</span>

                {{-- Tampilkan Nama Departemen (Bisa dari PR langsung atau dari User) --}}
                @if(isset($pr->department))
                    / {{ $pr->department->name }}
                @elseif(isset($pr->user->department))
                    / {{ $pr->user->department->name }}
                @endif
            </td>
            <td class="info-label">Status Dokumen</td><td class="info-separator">:</td>
            <td style="font-weight: bold; text-transform: uppercase;">{{ optional($pr->status)->name ?? 'UNKNOWN' }}</td>
        </tr>
        <tr>
            <td class="info-label">Tujuan Pengadaan</td><td class="info-separator">:</td>
            <td colspan="4">{{ $pr->description }}</td>
        </tr>
    </table>

    <table class="item-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 15%;">Kode Barang</th>
                <th style="width: 40%;">Nama Barang / Jasa</th>
                <th style="width: 20%;">Kuantitas & Satuan</th>
                <th style="width: 20%;">Status Item</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pr->items as $index => $item)
                @php
                    $namaSatuan = 'Unit';
                    $masterItem = $item->item;
                    if ($masterItem) {
                        if ($masterItem->uom_id == $item->uom_id && $masterItem->uom) {
                            $namaSatuan = $masterItem->uom->name;
                        } else {
                            $altUom = $masterItem->itemUoms ? $masterItem->itemUoms->where('id', $item->uom_id)->first() : null;
                            if ($altUom) {
                                $namaSatuan = $altUom->uom_name . ' (' . $altUom->conversion_qty . ' ' . optional($masterItem->uom)->name . ')';
                            } elseif ($item->uom) {
                                $namaSatuan = $item->uom->name;
                            }
                        }
                    } elseif ($item->uom) {
                        $namaSatuan = $item->uom->name;
                    }
                @endphp
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td style="text-align: center;">{{ optional($item->item)->code ?? '-' }}</td>
                    <td>{{ optional($item->item)->name ?? '-' }}</td>
                    <td style="text-align: center; font-weight: bold;">{{ number_format($item->qty, 2, ',', '.') }} {{ $namaSatuan }}</td>
                    <td style="text-align: center; font-weight: bold; text-transform: uppercase;">
                        {{ $item->status_badge['label'] ?? 'MENUNGGU' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align: center; font-style: italic;">Tidak ada data rincian barang.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- 🔥 TANDA TANGAN DINAMIS DENGAN TABEL (AMAN UNTUK DOMPDF) 🔥 --}}
    <table class="signature-table">
        <tr>
            {{-- 1. PEMBUAT (REQUESTER) --}}
            <td>
                <div style="margin-bottom: 5px;">Dibuat Oleh,</div>
                <div class="sign-space">
                    @if(optional($pr->user)->signature)
                        {{-- WAJIB public_path agar PDF bisa membaca gambar di local storage --}}
                        <img src="{{ public_path('storage/' . $pr->user->signature) }}" class="signature-img">
                    @else
                        <div class="stamp stamp-approved">SUBMITTED</div>
                    @endif
                </div>
                <div><u><strong>{{ optional($pr->user)->name ?? '...........................' }}</strong></u></div>
                <div>Peminta (Requester)</div>
                <div>Tgl: {{ \Carbon\Carbon::parse($pr->created_at)->format('d/m/Y') }}</div>
            </td>

            {{-- 2. APPROVER (DINAMIS DARI WORKFLOW) --}}
            @foreach($approvals as $approval)
                <td>
                    <div style="margin-bottom: 5px;">Disetujui Oleh,</div>
                    <div class="sign-space">
                        @if(strtolower(optional($pr->status)->slug) === 'cancelled')
                            <div class="stamp stamp-rejected">VOID / CANCELLED</div>
                        @elseif($approval->status === 'APPROVED')

                            {{-- Cek Jika Approver punya Tanda Tangan Digital --}}
                            @if(optional($approval->approver)->signature)
                                <img src="{{ public_path('storage/' . $approval->approver->signature) }}" class="signature-img">
                            @else
                                <div class="stamp stamp-approved">APPROVED</div>
                            @endif

                        @elseif($approval->status === 'REJECTED')
                            <div class="stamp stamp-rejected">REJECTED</div>
                        @else
                            {{-- Kosong atau Pending --}}
                            <div class="stamp stamp-pending" style="opacity: 0.3;">PENDING</div>
                        @endif
                    </div>

                    @if($approval->status === 'APPROVED' || $approval->status === 'REJECTED')
                        <div><u><strong>{{ optional($approval->approver)->name ?? '...........................' }}</strong></u></div>
                        <div>{{ optional($approval->role)->name ?? '...........................' }}</div>
                        <div>Tgl: {{ \Carbon\Carbon::parse($approval->approved_at)->format('d/m/Y') }}</div>
                    @else
                        <div><u><strong>....................................</strong></u></div>
                        <div>{{ optional($approval->role)->name ?? '...........................' }}</div>
                        <div>Tgl: ........................</div>
                    @endif
                </td>
            @endforeach
        </tr>
    </table>

    <div class="print-footer">
        * Dokumen elektronik ini diterbitkan oleh sistem ProcureApp pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }} WIB
    </div>

</body>
</html>
