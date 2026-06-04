<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak PR - {{ $pr->pr_number }}</title>
    <style>
        /* ==========================================
           PENGATURAN GAYA CETAK (PRINT STYLESHEET)
           ========================================== */
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11pt; color: #000; background-color: #e2e8f0; margin: 0; }
        .a4-container { width: 210mm; min-height: 297mm; margin: 20px auto; padding: 15mm 20mm; background: white; box-shadow: 0 4px 15px rgba(0,0,0,0.1); box-sizing: border-box; }

        .header-title { font-size: 18pt; font-weight: bold; margin: 0 0 5px 0; text-transform: uppercase; color: #0f172a; }
        .header-subtitle { font-size: 10pt; margin: 0; color: #475569; }
        .doc-title { font-size: 16pt; font-weight: bold; color: #0284c7; margin: 0; text-align: right; }
        .header-border { border-bottom: 2px solid #0f172a; margin-top: 15px; margin-bottom: 20px; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 10pt; }
        .info-table td { padding: 4px 0; vertical-align: top; }
        .info-label { width: 120px; font-weight: bold; color: #334155; }
        .info-separator { width: 15px; text-align: center; font-weight: bold; }

        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 10pt; }
        .item-table th, .item-table td { border: 1px solid #334155; padding: 10px 8px; }
        .item-table th { background-color: #f1f5f9 !important; -webkit-print-color-adjust: exact; text-align: center; font-weight: bold; text-transform: uppercase; }

        /* AREA TANDA TANGAN DIGITAL */
        .signature-area { width: 100%; display: table; margin-top: 40px; table-layout: fixed; }
        .signature-box { display: table-cell; width: 33.33%; text-align: center; vertical-align: bottom; }
        .signature-space { height: 90px; display: flex; align-items: center; justify-content: center; margin: 5px 0; }
        .signature-img { max-height: 80px; max-width: 150px; object-fit: contain; } /* Kunci agar TTD proporsional */
        .signature-name { font-weight: bold; text-decoration: underline; margin: 0 0 3px 0; font-size: 11pt; text-transform: uppercase; }
        .signature-role { font-size: 9pt; margin: 0; color: #475569; }

        .print-footer { margin-top: 40px; font-size: 8pt; color: #64748b; text-align: right; font-style: italic; border-top: 1px solid #cbd5e1; padding-top: 8px; }

        /* Tombol UI (Hanya di Layar) */
        .ui-buttons { text-align: center; padding: 20px 0; }
        .btn { padding: 10px 20px; font-weight: bold; border: none; border-radius: 5px; cursor: pointer; margin: 0 5px; font-size: 14px; }
        .btn-primary { background-color: #0d6efd; color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }

        @media print {
            body { background-color: #fff; margin: 0; }
            .a4-container { width: 100%; margin: 0; padding: 0; box-shadow: none; border: none; }
            .ui-buttons { display: none !important; }
            @page { size: A4; margin: 12mm 15mm; }
        }
    </style>
</head>
<body>

    <div class="ui-buttons no-print">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Cetak Dokumen</button>
        <button onclick="window.close()" class="btn btn-secondary">❌ Tutup</button>
    </div>

    <div class="a4-container">

        <table style="width: 100%;">
            <tr>
                <td style="width: 60%; vertical-align: top;">
                    <h3 class="header-title">{{ optional($pr->company)->name ?? 'PERUSAHAAN INTERNAL' }}</h3>
                    <p class="header-subtitle">Dokumen Pengadaan Barang & Jasa Internal</p>
                </td>
                <td style="width: 40%; vertical-align: top; text-align: right;">
                    <h4 class="doc-title" style="-webkit-print-color-adjust: exact;">PURCHASE REQUEST (PR)</h4>
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
                <td style="font-weight: bold; color: #b91c1c; -webkit-print-color-adjust: exact;">{{ \Carbon\Carbon::parse($pr->need_date)->translatedFormat('d F Y') }}</td>
            </tr>
            <tr>
                <td class="info-label">Unit / Departemen</td><td class="info-separator">:</td>
                <td>{{ optional($pr->company)->name ?? '-' }}</td>
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
                            {{ $item->status_badge['label'] }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align: center; font-style: italic;">Tidak ada data rincian barang.</td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- ==============================================
             LOGIKA PENCARIAN APPROVER & TANDA TANGAN
             ============================================== --}}
        {{-- @php
            // 1. Ambil Data Pembuat PR
            $pembuat = $pr->user;

            // 2. Lacak Manager dari Jejak Audit (Histories)
            $historyManager = $pr->histories->where('action', 'Disetujui Manager')->last();
            $manager = $historyManager ? $historyManager->user : null;

            // 3. Lacak Direktur dari Jejak Audit (Histories)
            $historyDirektur = $pr->histories->where('action', 'Disetujui Direktur (Final)')->last();
            $direktur = $historyDirektur ? $historyDirektur->user : null;
        @endphp --}}

        {{-- AREA TANDA TANGAN DIGITAL --}}
        {{-- 🔥 CSS UNTUK TANDA TANGAN DINAMIS (Taruh di tag <style> atas jika perlu) 🔥 --}}
    <style>
        .signature-container {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            width: 100%;
            page-break-inside: avoid; /* Agar tidak terpotong halaman baru */
        }
        .signature-box {
            text-align: center;
            flex: 1; /* Akan membagi ruang sama rata berapapun jumlahnya */
            padding: 0 10px;
            font-size: 12px;
        }
        .signature-box p {
            margin: 3px 0;
        }
        .sign-space {
            height: 70px; /* Jarak untuk tanda tangan fisik/digital */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stamp-approved {
            color: #198754;
            font-weight: bold;
            border: 2px solid #198754;
            padding: 5px 10px;
            border-radius: 5px;
            transform: rotate(-10deg);
        }
        .stamp-rejected {
            color: #dc3545;
            font-weight: bold;
            border: 2px solid #dc3545;
            padding: 5px 10px;
            border-radius: 5px;
            transform: rotate(-10deg);
        }
    </style>

    {{-- 🔥 BLOK TANDA TANGAN DINAMIS 🔥 --}}
    <div class="signature-container">
        
        {{-- 1. Tanda Tangan Pembuat (Selalu Muncul Paling Kiri) --}}
        <div class="signature-box">
            <p>Dibuat Oleh,</p>
            <div class="sign-space">
                <span class="stamp-approved">SUBMITTED</span>
            </div>
            <p><u><strong>{{ optional($pr->user)->name ?? '....................................' }}</strong></u></p>
            <p>Peminta (Requester)</p>
            <p>Tgl: {{ \Carbon\Carbon::parse($pr->created_at)->format('d/m/Y') }}</p>
        </div>

        {{-- 2. Looping Tanda Tangan Approver (Dinamis dari Database) --}}
        @foreach($approvals as $approval)
            <div class="signature-box">
                <p>Disetujui Oleh,</p>
                
                <div class="sign-space">
                    @if(strtolower(optional($pr->status)->slug) === 'cancelled')
                        <span class="stamp-rejected">VOID / CANCELLED</span>
                    @elseif($approval->status === 'APPROVED')
                        <span class="stamp-approved">APPROVED</span>
                    @elseif($approval->status === 'REJECTED')
                        <span class="stamp-rejected">REJECTED</span>
                    @endif
                </div>

                @if($approval->status === 'APPROVED' || $approval->status === 'REJECTED')
                    <p><u><strong>{{ optional($approval->approver)->name ?? '....................................' }}</strong></u></p>
                    <p>{{ optional($approval->role)->name ?? '....................................' }}</p>
                    <p>Tgl: {{ \Carbon\Carbon::parse($approval->approved_at)->format('d/m/Y') }}</p>
                @else
                    <p><u><strong>....................................</strong></u></p>
                    <p>{{ optional($approval->role)->name ?? '....................................' }}</p>
                    <p>Tgl: ........................</p>
                @endif
            </div>
        @endforeach

    </div>

        <div class="print-footer">
            * Dokumen elektronik ini telah diaudit dan diterbitkan oleh sistem ProcureApp pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }} WIB
        </div>

    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 800); // Waktu jeda diperpanjang sedikit agar gambar TTD selesai dimuat
        }
    </script>
</body>
</html>
