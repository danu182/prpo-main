<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lembar Stock Opname - {{ $opname->document_number }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #000; margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h2 { margin: 0 0 5px 0; font-size: 18px; text-transform: uppercase; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 3px 0; font-size: 11px; }

        /* Tabel Rincian */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 6px; text-align: left; vertical-align: middle; }
        .data-table th { background-color: #e9ecef; text-align: center; font-weight: bold; }
        .data-table tr { page-break-inside: avoid; }

        .fill-area { height: 25px; }

        /* Area Tanda Tangan Dinamis */
        .sign-table { width: 100%; margin-top: 40px; text-align: center; page-break-inside: avoid; border-collapse: collapse; }
        .sign-table td { padding: 10px; vertical-align: top; }
        .sign-title { font-weight: bold; margin-bottom: 60px; font-size: 11px; }
        .sign-line { border-top: 1px solid #000; width: 90%; margin: 0 auto; padding-top: 5px; font-weight: bold; font-size: 11px; }
        .sign-role { font-size: 10px; margin-top: 2px; color: #333; }
        .sign-date { font-size: 9px; margin-top: 2px; color: #666; font-style: italic; }
    </style>
</head>
<body>

    <div class="header">
        <h2>Lembar Kerja Stock Opname (Blind Count)</h2>
        <strong>No. Dokumen: {{ $opname->document_number }}</strong>
    </div>

    <table class="info-table">
        <tr>
            <td width="15%"><strong>Lokasi / Gudang</strong></td>
            <td width="35%">: {{ optional($opname->warehouse)->name }}</td>
            <td width="15%"><strong>Tanggal Cetak</strong></td>
            <td width="35%">: {{ date('d-m-Y H:i') }}</td>
        </tr>
        <tr>
            <td><strong>Tgl Mulai Opname</strong></td>
            <td>: {{ \Carbon\Carbon::parse($opname->start_date)->format('d-m-Y') }}</td>
            <td><strong>Dibuat Oleh</strong></td>
            <td>: {{ optional($opname->creator)->name }}</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Kode Barang</th>
                <th width="35%">Nama Barang</th>
                <th width="10%">Satuan</th>
                <th width="15%">Hasil Hitung Fisik<br>(Diisi Manual)</th>
                <th width="20%">Keterangan / Kondisi<br>(Diisi Manual)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($opname->items as $index => $item)
            <tr>
                <td style="text-align: center;">{{ $index + 1 }}</td>
                <td>{{ optional($item->item)->code }}</td>
                <td><strong>{{ optional($item->item)->name }}</strong></td>
                <td style="text-align: center;">{{ $item->base_uom }}</td>
                <td class="fill-area"></td>
                <td class="fill-area"></td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center; padding: 20px; font-style: italic;">Tidak ada data stok di gudang ini untuk dihitung.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- 🔥 LOGIKA TANDA TANGAN DINAMIS 🔥 --}}
    @php
        $signatureBoxes = [];

        // 1. Box Pertama: Pembuat / Checker
        $signatureBoxes[] = [
            'title' => 'Dihitung Oleh',
            'name'  => optional($opname->creator)->name ?? '_______________________',
            'role'  => 'Checker / Eksekutor',
            'date'  => \Carbon\Carbon::parse($opname->created_at)->format('d/m/Y')
        ];

        $approvals = $opname->approvals ?? collect();

        if ($approvals->count() > 0) {
            // SKENARIO A: Dokumen sudah diajukan (Tarik dari data Approval yang sudah terbentuk)
            foreach ($approvals->sortBy('step_order') as $approval) {
                $signatureBoxes[] = [
                    'title' => 'Diverifikasi Oleh',
                    'name'  => optional($approval->approver)->name ?? '_______________________',
                    'role'  => optional($approval->role)->name ?? 'Supervisor / Manager',
                    'date'  => $approval->approved_at ? \Carbon\Carbon::parse($approval->approved_at)->format('d/m/Y') : ''
                ];
            }
        } else {
            // SKENARIO B: Dokumen masih Draft / Blind Count (Tarik dari Master Matrix Workflow)
            $workflow = \App\Models\ApprovalWorkflow::with('steps.role')
                        ->where('document_type', 'App\Models\StockOpname')
                        ->where('is_active', true)
                        ->first();

            if ($workflow) {
                foreach ($workflow->steps->sortBy('step_order') as $step) {
                    $signatureBoxes[] = [
                        'title' => 'Diverifikasi Oleh',
                        'name'  => '_______________________',
                        'role'  => optional($step->role)->name ?? 'Manager',
                        'date'  => ''
                    ];
                }
            }
        }

        // Hitung lebar kolom dinamis (membagi 100% dengan jumlah kotak tanda tangan)
        $colWidth = count($signatureBoxes) > 0 ? (100 / count($signatureBoxes)) : 100;
    @endphp

    {{-- CETAK TABEL TANDA TANGAN --}}
    <table class="sign-table">
        <tr>
            @foreach($signatureBoxes as $box)
            <td style="width: {{ $colWidth }}%;">
                <div class="sign-title">{{ $box['title'] }}</div>
                <div class="sign-line">{{ $box['name'] }}</div>
                <div class="sign-role">{{ $box['role'] }}</div>
                @if($box['date'])
                    <div class="sign-date">Tgl: {{ $box['date'] }}</div>
                @endif
            </td>
            @endforeach
        </tr>
    </table>

</body>
</html>
