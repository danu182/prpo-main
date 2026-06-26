<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Pembayaran - {{ $bill->bill_number }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 9pt; color: #333; margin: 0; padding: 20px; }

        /* Header Modern */
        .header-table { width: 100%; border-bottom: 3px double #0d6efd; padding-bottom: 15px; margin-bottom: 20px; }
        .company-name { font-size: 16pt; font-weight: 900; color: #0d6efd; text-transform: uppercase; margin: 0; }
        .doc-title { font-size: 18pt; font-weight: 900; text-align: right; color: #1e293b; text-transform: uppercase; margin: 0; letter-spacing: 1px; }
        .doc-subtitle { text-align: right; font-size: 9pt; color: #64748b; margin-top: 5px; }

        /* Grid Informasi Dokumen */
        .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        .info-grid td { vertical-align: top; padding: 12px; border: 1px solid #e2e8f0; }
        .info-grid .bg-light { background-color: #f8fafc; width: 50%; }
        .info-label { font-size: 7.5pt; font-weight: bold; color: #64748b; text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.5px; }
        .info-value { font-size: 10pt; font-weight: bold; color: #0f172a; }

        /* Tabel Data Pembayaran */
        .main-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .main-table th { background-color: #0d6efd; color: white; padding: 10px 8px; text-align: left; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5px; }
        .main-table td { padding: 10px 8px; border-bottom: 1px solid #e2e8f0; font-size: 9pt; vertical-align: middle; }
        .main-table tbody tr:nth-child(even) { background-color: #f8fafc; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Badge Status */
        .badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 8pt; font-weight: bold; text-transform: uppercase; color: white; }
        .badge-paid { background-color: #16a34a; }
        .badge-partial { background-color: #f59e0b; }
        .badge-other { background-color: #64748b; }

        /* Area Ringkasan Kalkulasi */
        .summary-wrapper { width: 100%; margin-top: 20px; }
        .summary-box { float: right; width: 45%; border-collapse: collapse; }
        .summary-box td { padding: 8px 10px; font-size: 10pt; }
        .summary-box .border-bottom { border-bottom: 1px solid #cbd5e1; }
        .summary-box .total-row { background-color: #f1f5f9; font-weight: bold; font-size: 11pt; color: #0f172a; border-top: 2px solid #0d6efd; }
        .summary-box .balance-row { font-weight: bold; font-size: 12pt; color: #ef4444; border-top: 1px solid #ef4444; background-color: #fef2f2; }
        .summary-box .balance-zero { color: #16a34a; border-top: 1px solid #16a34a; background-color: #f0fdf4; }

        /* Tanda Tangan */
        .signature-table { width: 100%; margin-top: 80px; text-align: center; table-layout: fixed; clear: both; }
        .signature-table td { width: 33.33%; vertical-align: bottom; }
        .sign-line { border-top: 1px solid #333; width: 60%; margin: 0 auto; margin-top: 70px; padding-top: 5px; font-weight: bold; font-size: 9pt; }
        .sign-role { font-size: 8pt; color: #64748b; margin-top: 2px; }
    </style>
</head>
<body>

    @php
        $statusSlug = strtolower(optional($bill->status)->slug ?? 'unknown');
        $totalBilled = $bill->amount;
        $totalPaid = $bill->payments->sum('amount_paid');
        $balance = $totalBilled - $totalPaid;
    @endphp

    <table class="header-table">
        <tr>
            <td width="50%">
                <h1 class="company-name">{{ $bill->company->name ?? 'Perusahaan Internal' }}</h1>
                <p style="margin: 5px 0 0 0; color: #64748b; font-size: 9pt;">Finance & Accounting Department</p>
            </td>
            <td width="50%">
                <h2 class="doc-title">STATEMENT OF ACCOUNT</h2>
                <div class="doc-subtitle">Dicetak pada: {{ date('d M Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <table class="info-grid">
        <tr>
            <td class="bg-light">
                <div class="info-label">Informasi Vendor / Penerima</div>
                <div class="info-value">{{ $bill->vendor_name }}</div>
                <div style="font-size: 8.5pt; color: #475569; margin-top: 3px;">Tagihan: {{ $bill->title ?? '-' }}</div>
            </td>
            <td>
                <div class="info-label">Detail Dokumen</div>
                <table style="width: 100%; font-size: 9pt; margin-top: 5px;">
                    <tr>
                        <td width="40%" style="color: #64748b; padding: 2px 0;">No. Tagihan</td>
                        <td width="60%">: <strong>{{ $bill->bill_number }}</strong></td>
                    </tr>
                    <tr>
                        <td style="color: #64748b; padding: 2px 0;">Tgl. Tagihan</td>
                        <td>: {{ \Carbon\Carbon::parse($bill->invoice_date)->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td style="color: #64748b; padding: 2px 0;">Status Dokumen</td>
                        <td>:
                            @if($statusSlug == 'paid') <span class="badge badge-paid">LUNAS</span>
                            @elseif($statusSlug == 'partial') <span class="badge badge-partial">CICILAN</span>
                            @else <span class="badge badge-other">{{ strtoupper(optional($bill->status)->name ?? 'UNKNOWN') }}</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="main-table">
        <thead>
            <tr>
                <th width="5%" class="text-center">No</th>
                <th width="15%">Tgl Bayar</th>
                <th width="25%">No. Referensi (Voucher)</th>
                <th width="20%">Metode Bayar</th>
                <th width="15%">Keterangan</th>
                <th width="20%" class="text-right">Nominal ({{ $bill->currency }})</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bill->payments as $index => $pay)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ \Carbon\Carbon::parse($pay->payment_date)->format('d M Y') }}</td>
                <td>
                    <strong>{{ $pay->payment_number }}</strong>
                    @if($pay->transaction_reference)
                        <br><span style="font-size: 7.5pt; color: #64748b;">Ref: {{ $pay->transaction_reference }}</span>
                    @endif
                </td>
                <td>{{ $pay->paymentMethod->name ?? '-' }}</td>
                <td style="font-size: 8pt; color: #475569; font-style: italic;">{{ $pay->note ?? '-' }}</td>
                <td class="text-right fw-bold">{{ number_format($pay->amount_paid, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center" style="padding: 20px; color: #94a3b8; font-style: italic;">Belum ada riwayat pembayaran untuk tagihan ini.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary-wrapper">
        <table class="summary-box">
            <tr>
                <td class="border-bottom" style="color: #64748b;">Total Tagihan Awal</td>
                <td class="text-right border-bottom">{{ $bill->currency }} {{ number_format($totalBilled, 0, ',', '.') }}</td>
            </tr>
            <tr class="total-row">
                <td>Total Telah Dibayar</td>
                <td class="text-right">{{ $bill->currency }} {{ number_format($totalPaid, 0, ',', '.') }}</td>
            </tr>
            <tr class="{{ $balance > 0 ? 'balance-row' : 'balance-row balance-zero' }}">
                <td>SISA HUTANG (BALANCE)</td>
                <td class="text-right">{{ $bill->currency }} {{ number_format($balance, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    <table class="signature-table">
        <tr>
            <td>
                <p style="margin-bottom: 10px; font-weight: bold; color: #475569;">Dibuat Oleh,</p>
                <div class="sign-line">{{ auth()->user()->name ?? 'Admin Finance' }}</div>
                <p class="sign-role">Finance Staff</p>
            </td>
            <td></td>
            <td>
                <p style="margin-bottom: 10px; font-weight: bold; color: #475569;">Diperiksa Oleh,</p>
                <div class="sign-line">Manajer Keuangan</div>
                <p class="sign-role">Finance & Accounting Manager</p>
            </td>
        </tr>
    </table>

</body>
</html>
