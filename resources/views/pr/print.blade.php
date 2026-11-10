<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak PR - {{ $pr->pr_number }}</title>
    <style>
        /* ==========================================
           PENGATURAN GAYA PDF (DOMPDF READY)
           ========================================== */
        @page { margin: 40px 40px 60px 40px; }
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

        /* AREA TANDA TANGAN (SAMA SEPERTI BPR & PO) */
        .signature-table { width: 100%; table-layout: fixed; margin-top: 30px; page-break-inside: avoid; border-collapse: collapse; }
        .signature-table td { text-align: center; vertical-align: top; padding: 0 5px; font-size: 10pt; border: none; }
        .sign-space { height: 90px; text-align: center; padding: 10px 0; vertical-align: bottom; }

        .signature-img { max-height: 70px; max-width: 140px; object-fit: contain; margin-bottom: 5px; }

        /* STEMPEL TEKS */
        .stamp { font-weight: bold; padding: 5px 10px; display: inline-block; font-size: 10pt; letter-spacing: 1px; }
        .stamp-approved { color: #198754; border: 2px solid #198754; }
        .stamp-rejected { color: #dc3545; border: 2px solid #dc3545; }
        .stamp-pending { color: #6c757d; border: 2px dashed #6c757d; }

        /* 🔥 POSISI FOOTER SELALU DI BAWAH 🔥 */
        footer { position: fixed; bottom: -30px; left: 0px; right: 0px; height: 30px; font-size: 8pt; color: #555; font-style: italic; text-align: right; }
    </style>
</head>
<body>

    @php
        // 🔥 HELPER SAKTI: PENCARI FILE GAMBAR TTD ANTI-GAGAL 🔥
        $getSignatureBase64 = function($signatureDbPath) {
            if (empty($signatureDbPath)) return null;

            // Bersihkan path dari awalan public/ atau storage/
            $cleanPath = ltrim(str_replace(['public/', 'storage/'], '', $signatureDbPath), '/');

            // Daftar kemungkinan lokasi file TTD
            $paths = [
                storage_path('app/public/' . $cleanPath),
                public_path('storage/' . $cleanPath),
                storage_path('app/' . $cleanPath)
            ];

            foreach($paths as $path) {
                if (file_exists($path) && is_file($path)) {
                    $type = pathinfo($path, PATHINFO_EXTENSION);
                    $data = file_get_contents($path);
                    return 'data:image/' . $type . ';base64,' . base64_encode($data);
                }
            }
            return null; // File fisik tidak ditemukan
        };

        // Siapkan Tanda Tangan Pembuat (Requester)
        $prepSigBase64 = null;
        if ($pr->user && !empty($pr->user->signature)) {
            $prepSigBase64 = $getSignatureBase64($pr->user->signature);
        }
    @endphp

    {{-- 🔥 FOOTER OTOMATIS (MUNCUL DI SETIAP HALAMAN BAWAH) 🔥 --}}
    <footer>* Dokumen elektronik ini diterbitkan oleh sistem ProcureApp pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }} WIB</footer>

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
                <span style="font-weight: bold;">{{ optional($pr->company)->name ?? '-' }}</span>
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

    {{-- 🔥 TANDA TANGAN DINAMIS DENGAN TABEL 🔥 --}}
    <table class="signature-table">
        <tr>
            {{-- 1. PEMBUAT (REQUESTER) --}}
            <td>
                <div style="margin-bottom: 5px;">Dibuat Oleh,</div>
                <div class="sign-space">
                    @if($prepSigBase64)
                        <img src="{{ $prepSigBase64 }}" class="signature-img">
                    @else
                        <div class="stamp stamp-approved">SUBMITTED</div>
                    @endif
                </div>
                <div><u><strong>{{ optional($pr->user)->name ?? '...........................' }}</strong></u></div>
                <div style="font-size: 8pt; color: #666; margin-top: 2px;">Peminta (Requester)</div>
                <div style="font-size: 8pt; color: #666;">Tgl: {{ \Carbon\Carbon::parse($pr->created_at)->format('d/m/Y') }}</div>
            </td>

            {{-- 2. APPROVER (DINAMIS DARI WORKFLOW) --}}
            @foreach($approvals as $approval)
                <td>
                    {{-- 🔥 LOGIKA JUDUL TTD DINAMIS (Diperiksa vs Disetujui) 🔥 --}}
                    <div style="margin-bottom: 5px;">
                        @if($loop->last)
                            Disetujui Oleh,
                        @else
                            @if($approvals->count() > 2)
                                Diperiksa Oleh {{ $loop->iteration }},
                            @else
                                Diperiksa Oleh,
                            @endif
                        @endif
                    </div>

                    <div class="sign-space">
                        @php
                            // Ambil Base64 TTD Approver jika statusnya APPROVED
                            $apprSigBase64 = null;
                            if ($approval->status === 'APPROVED' && optional($approval->approver)->signature) {
                                $apprSigBase64 = $getSignatureBase64($approval->approver->signature);
                            }
                        @endphp

                        @if(strtolower(optional($pr->status)->slug) === 'cancelled')
                            <div class="stamp stamp-rejected">VOID / CANCELLED</div>
                        @elseif($approval->status === 'APPROVED')
                            @if($apprSigBase64)
                                <img src="{{ $apprSigBase64 }}" class="signature-img">
                            @else
                                <div class="stamp stamp-approved">APPROVED</div>
                            @endif
                        @elseif($approval->status === 'REJECTED')
                            <div class="stamp stamp-rejected">REJECTED</div>
                        @else
                            {{-- <div class="stamp stamp-pending" style="opacity: 0.3;">PENDING</div> --}}
                        @endif
                    </div>

                    @php
                        // 🔥 LOGIKA SMART LOOKUP SEPERTI DI BPR 🔥
                        $namaTtd = "";
                        $uid = $approval->user_id ?? $approval->approver_id ?? $approval->specific_user_id ?? null;

                        if (!empty($uid)) {
                            $userTtd = \App\Models\User::find($uid);
                            $namaTtd = $userTtd ? $userTtd->name : '';
                        }

                        $roleName = optional($approval->role)->name ?? 'Manager';
                        $deptName = '';
                        if (!empty($approval->target_department_id) && $approval->target_department_id !== 'all') {
                            $dept = \DB::table('departments')->where('id', $approval->target_department_id)->first();
                            $deptName = $dept ? $dept->name : '';
                        } else {
                            $deptName = optional(optional($pr->user)->department)->name ?? '';
                        }

                        $jabatanLengkap = trim($roleName . ' ' . $deptName);

                        if (empty($namaTtd) && !empty($roleName)) {
                            $potentialUsers = \App\Models\User::role($roleName);

                            if (!empty($approval->target_department_id) && $approval->target_department_id !== 'all') {
                                $potentialUsers->where('department_id', $approval->target_department_id);
                            } elseif (empty($approval->target_department_id)) {
                                $potentialUsers->where('department_id', optional($pr->user)->department_id);
                            }

                            $firstUser = $potentialUsers->first();
                            if ($firstUser) {
                                $namaTtd = $firstUser->name;
                            }
                        }

                        if (empty($namaTtd)) {
                            $namaTtd = $jabatanLengkap;
                        }
                    @endphp

                    {{-- Cetak Nama Selalu (Dari Smart Lookup) --}}
                    <div><u><strong>{{ $namaTtd }}</strong></u></div>

                    {{-- Cetak Tanggal Hanya Jika Sudah Direspon --}}
                    @if($approval->status === 'APPROVED' || $approval->status === 'REJECTED')
                        <div style="font-size: 8pt; color: #666; margin-top: 2px;">Tgl: {{ \Carbon\Carbon::parse($approval->approved_at)->format('d/m/Y') }}</div>
                    @else
                        <div style="font-size: 8pt; color: transparent; margin-top: 2px;">-</div>
                    @endif
                </td>
            @endforeach
        </tr>
    </table>

</body>
</html>
