<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print - {{ $bill->bill_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: white !important; font-size: 12px; }
        .invoice-box { padding: 30px; border: 1px solid #eee; }
        .table th { background-color: #f8f9fa !important; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; margin: 0; }
            .invoice-box { border: none; }
        }
    </style>
</head>
<body onload="window.print()">

<div class="container my-4 invoice-box">
    {{-- Header Invoice --}}
    <div class="mb-4 row">
        <div class="col-6">
            <h2 class="fw-bold text-primary">INVOICE REQUEST</h2>
            <div class="text-muted small">Nomor: {{ $bill->bill_number }}</div>
        </div>
        <div class="col-6 text-end">
            <h5 class="fw-bold">{{ $bill->company->name }}</h5>
            <div class="small">{{ $bill->company->address ?? 'Alamat Perusahaan' }}</div>
        </div>
    </div>

    <hr>

    {{-- Info Vendor & Tanggal --}}
    <div class="mt-4 mb-4 row">
        <div class="col-6">
            <div class="text-muted fw-bold small text-uppercase">Ditujukan Kepada:</div>
            <div class="fw-bold fs-6">{{ $bill->vendor_name }}</div>
        </div>
        <div class="col-6 text-end">
            <div class="row">
                <div class="col-7 text-muted small fw-bold">TANGGAL INVOICE:</div>
                <div class="col-5 small">{{ \Carbon\Carbon::parse($bill->invoice_date)->format('d/m/Y') }}</div>

                <div class="col-7 text-muted small fw-bold">JATUH TEMPO:</div>
                <div class="col-5 small">{{ \Carbon\Carbon::parse($bill->due_date)->format('d/m/Y') }}</div>

                <div class="col-7 text-muted small fw-bold">STATUS:</div>
                <div class="col-5 small fw-bold text-primary">{{ $bill->status }}</div>
            </div>
        </div>
    </div>

    {{-- Tabel Item --}}
    <table class="table mt-4 table-bordered">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th>Deskripsi & Catatan</th>
                <th width="10%" class="text-center">Qty</th>
                <th width="20%" class="text-end">Harga Satuan</th>
                <th width="20%" class="text-end">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bill->items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>
                    <div class="fw-bold">{{ $item->description }}</div>
                    @if($item->note) <small class="text-muted fst-italic">{{ $item->note }}</small> @endif
                </td>
                <td class="text-center">{{ $item->qty }}</td>
                <td class="text-end">{{ $bill->currency }} {{ number_format($item->price, 0, ',', '.') }}</td>
                <td class="text-end fw-bold">{{ $bill->currency }} {{ number_format($item->amount, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="py-3 text-end fw-bold">TOTAL KESELURUHAN</td>
                <td class="py-3 text-end fw-bold text-primary fs-5">
                    {{ $bill->currency }} {{ number_format($bill->amount, 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- Footer/Tanda Tangan --}}
    <div class="mt-5 row">
        <div class="text-center col-4">
            <div class="mb-5 small text-muted">Diajukan Oleh,</div>
            <br><br>
            <div class="fw-bold">({{ $bill->user->name }})</div>
            <div class="small text-muted">User</div>
        </div>
        <div class="text-center col-4">
            {{-- Tambahkan Logic Approval jika ada --}}
        </div>
        <div class="text-center col-4">
            <div class="mb-5 small text-muted">Disetujui Oleh,</div>
            <br><br>
            <div class="fw-bold">( ............................ )</div>
            <div class="small text-muted">Manager / Director</div>
        </div>
    </div>

    {{-- Tombol Kembali (Hanya tampil di layar, tidak saat di-print) --}}
    <div class="mt-5 text-center no-print">
        <hr>
        <button onclick="window.print()" class="px-4 btn btn-primary fw-bold rounded-pill">
            <i class="bi bi-printer me-2"></i> Cetak Sekarang
        </button>
        <a href="{{ route('bills.show', $bill->id) }}" class="px-4 border btn btn-light rounded-pill">Kembali ke Detail</a>
    </div>
</div>

</body>
</html>
