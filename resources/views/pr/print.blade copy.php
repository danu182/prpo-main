<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak PR - {{ $pr->pr_number }}</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        @media print {
            @page { size: A4; margin: 1.5cm 2cm; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            .footer-cetak { position: relative !important; bottom: auto !important; }
        }
        body { font-family: "Times New Roman", Times, serif; font-size: 13px; color: #000; line-height: 1.4; }
        .fw-bold { font-weight: bold !important; }
        .header-line-thick { border-bottom: 3px solid #000; margin-bottom: 2px; }
        .header-line-thin { border-bottom: 1px solid #000; margin-bottom: 20px; }
        .table-bordered th, .table-bordered td { border: 1px solid #000 !important; padding: 6px 8px; vertical-align: middle; }
        .table-light { background-color: #f0f0f0 !important; font-weight: bold; }
        .signature-section { margin-top: 3rem; page-break-inside: avoid; }
        .signature-box { height: 100px; display: flex; align-items: flex-end; justify-content: center; }
        .signer-name { font-weight: bold; text-decoration: underline; margin-bottom: 2px; }
        .signer-title { font-size: 12px; }
    </style>
</head>
<body class="bg-white">

    <div class="container p-0 my-4">
        <div class="d-flex justify-content-end mb-4 no-print">
            <button onclick="window.print()" class="btn btn-primary btn-lg fw-bold shadow">
                Cetak Dokumen Ini
            </button>
        </div>

        {{-- HEADER & JUDUL --}}
        <div class="text-center mb-3">
            <h2 class="fw-bold mb-1 text-uppercase" style="font-size: 20px;">{{ $pr->company->name ?? 'NAMA PERUSAHAAN' }}</h2>
            <p class="mb-0">Alamat Kantor: Jl. Contoh No. 123, Kota Bisnis, Indonesia</p>
            <p class="mb-0">Email: procurement@perusahaan.com</p>
        </div>
        <div class="header-line-thick"></div>
        <div class="header-line-thin"></div>

        <div class="text-center mb-4 mt-3">
            <h3 class="fw-bold text-decoration-underline mb-1" style="font-size: 18px;">PURCHASE REQUEST (PR)</h3>
            <p class="fw-bold mb-0">Nomor: {{ $pr->pr_number }}</p>
        </div>

        {{-- INFO UMUM --}}
        <div class="row mb-3">
            <div class="col-7">
                <table class="table table-borderless table-sm w-auto fw-bold">
                    <tr><td>Tanggal Request</td><td>: {{ \Carbon\Carbon::parse($pr->request_date)->translatedFormat('d F Y') }}</td></tr>
                    <tr><td>Requester</td><td>: {{ $pr->user->name }}</td></tr>
                    <tr><td>Departemen/PT</td><td>: {{ $pr->company->name ?? '-' }}</td></tr>
                </table>
            </div>
            <div class="col-5 d-flex justify-content-end align-items-start">
                <div class="text-end">
                    <span class="d-block fw-bold mb-1">Status PR:</span>
                    <span class="border border-2 border-dark px-3 py-1 rounded fw-bold text-uppercase">
                        {{ str_replace('_', ' ', $pr->status) }}
                    </span>
                </div>
            </div>
        </div>

        {{-- TABEL ITEM --}}
        <table class="table table-bordered w-100 mb-2">
            <thead class="table-light text-center text-uppercase">
                <tr>
                    <th width="5%">No</th>
                    <th width="40%">Deskripsi Barang</th>
                    <th width="10%">Qty</th>
                    <th width="25%">Vendor Terpilih</th>
                    <th width="20%">Est. Harga Total</th>
                </tr>
            </thead>
            <tbody>
                @php $grandTotalIDR = 0; @endphp
                @foreach($pr->items as $index => $item)
                    @php
                        $selectedQuote = $item->vendorQuotes->where('is_selected', 1)->first();
                        $vendorName = $selectedQuote ? $selectedQuote->vendor->name : '-';
                        $priceTotal = 0; $currency = 'IDR'; $symbol = 'Rp';
                        if($selectedQuote) {
                            $priceTotal = $selectedQuote->quoted_price * $item->qty;
                            $currency = $selectedQuote->currency ?? 'IDR';
                            $symbol = $currencySymbols[$currency] ?? $currency;
                            if($currency == 'IDR') $grandTotalIDR += $priceTotal;
                        }
                    @endphp
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>
                            <span class="fw-bold">{{ $item->item->name }}</span>
                            <br><small class="fst-italic">{{ $item->item->code }}</small>
                            @if($item->status == 'REJECTED') <br><span class="badge bg-danger">DITOLAK</span> @endif
                        </td>
                        <td class="text-center">{{ $item->qty }} {{ $item->item->unit }}</td>
                        <td>{{ $vendorName }}</td>
                        <td class="text-end font-monospace">
                            @if($selectedQuote) {{ $symbol }} {{ number_format($priceTotal, 0, ',', '.') }} @else - @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            @if($grandTotalIDR > 0)
            <tfoot>
                <tr class="fw-bold" style="background-color: #f9f9f9;">
                    <td colspan="4" class="text-end pe-3">TOTAL ESTIMASI (IDR)</td>
                    <td class="text-end">Rp {{ number_format($grandTotalIDR, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
        <div class="mb-4"><strong>Keterangan:</strong> <span class="fst-italic">{{ $pr->description }}</span></div>

        {{-- AREA TANDA TANGAN (PERBAIKAN UTAMA DISINI) --}}
        <div class="signature-section row text-center">
            {{-- 1. Requester --}}
            <div class="col-4">
                <p class="mb-0 fw-bold">Diajukan Oleh,</p>
                <div class="signature-box d-flex align-items-end justify-content-center">
                    @if(!empty($pr->user->signature) && file_exists(public_path('storage/' . $pr->user->signature)))
                        <img src="{{ asset('storage/' . $pr->user->signature) }}" style="height: 80px; max-width: 100%; object-fit: contain;">
                    @endif
                </div>
                <p class="signer-name">{{ $pr->user->name }}</p>
                <p class="signer-title">Requester</p>
            </div>

            {{-- 2. Manager --}}
            <div class="col-4">
                <p class="mb-0 fw-bold">Disetujui Oleh,</p>
                <div class="signature-box d-flex align-items-end justify-content-center">
                    @if($manager && !empty($manager->signature) && file_exists(public_path('storage/' . $manager->signature)))
                        <img src="{{ asset('storage/' . $manager->signature) }}" style="height: 80px; max-width: 100%; object-fit: contain;">
                    @elseif($manager)
                        <div class="border px-2 py-1 rounded mb-2"><small class="text-muted fst-italic">Digitally Approved</small></div>
                    @endif
                </div>
                {{-- Gunakan $manager->name, bukan $managerName --}}
                <p class="signer-name">{{ $manager->name ?? '( ........................... )' }}</p>
                <p class="signer-title">Manager Dept.</p>
            </div>

            {{-- 3. Direktur --}}
            <div class="col-4">
                <p class="mb-0 fw-bold">Diketahui Oleh,</p>
                <div class="signature-box d-flex align-items-end justify-content-center">
                    @if($director && !empty($director->signature) && file_exists(public_path('storage/' . $director->signature)))
                        <img src="{{ asset('storage/' . $director->signature) }}" style="height: 80px; max-width: 100%; object-fit: contain;">
                    @elseif($director)
                        <div class="border px-2 py-1 rounded mb-2"><small class="text-muted fst-italic">Digitally Approved</small></div>
                    @endif
                </div>
                {{-- Gunakan $director->name, bukan $directorName --}}
                <p class="signer-name">{{ $director->name ?? '( ........................... )' }}</p>
                <p class="signer-title">Direktur Utama</p>
            </div>
        </div>

        <div class="mt-5 pt-4 text-center small text-muted footer-cetak">
            Dicetak otomatis oleh Sistem pada: {{ date('d F Y, H:i') }} WIB.
        </div>
    </div>
</body>
</html>
