<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Exports\PoRecapExport;
use App\Models\PurchaseRequestItem;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    public function generate(Request $request)
    {
        $request->validate([
            'report_type' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $type = $request->report_type;
        $start = $request->start_date;
        $end = $request->end_date;

        // JIKA YANG DIMINTA ADALAH REKAP PO
        if ($type === 'po_recap') {
            $fileName = 'Laporan_Rekap_PO_' . date('Ymd', strtotime($start)) . '_sd_' . date('Ymd', strtotime($end)) . '.xlsx';
            return Excel::download(new PoRecapExport($start, $end), $fileName);
        }
        // ==========================================
        // SUNTIKKAN KODE INI UNTUK MENANGKAP GR
        // ==========================================
        if ($type === 'gr_recap') {
            $fileName = 'Laporan_Penerimaan_Barang_GR_' . date('Ymd', strtotime($start)) . '_sd_' . date('Ymd', strtotime($end)) . '.xlsx';
            return Excel::download(new \App\Exports\GrRecapExport($start, $end), $fileName);
        }
        // ==========================================
        // SUNTIKKAN KODE INI UNTUK MENANGKAP RTV
        // ==========================================
        if ($type === 'rtv_recap') {
            $fileName = 'Laporan_Retur_Vendor_RTV_' . date('Ymd', strtotime($start)) . '_sd_' . date('Ymd', strtotime($end)) . '.xlsx';
            return Excel::download(new \App\Exports\RtvRecapExport($start, $end), $fileName);
        }
        // ==========================================
        // ==========================================
        // SUNTIKKAN KODE INI UNTUK MENANGKAP PEMBAYARAN KASIR
        // ==========================================
        if ($type === 'payment_recap') {
            $fileName = 'Laporan_Pengeluaran_Kas_' . date('Ymd', strtotime($start)) . '_sd_' . date('Ymd', strtotime($end)) . '.xlsx';
            return Excel::download(new \App\Exports\PaymentRecapExport($start, $end), $fileName);
        }
        // ==========================================
        // ==========================================
        // SUNTIKKAN KODE INI UNTUK MENANGKAP TOP SPEND VENDOR
        // ==========================================
        if ($type === 'vendor_spend') {
            $fileName = 'Laporan_Top_Spend_Vendor_' . date('Ymd', strtotime($start)) . '_sd_' . date('Ymd', strtotime($end)) . '.xlsx';
            return Excel::download(new \App\Exports\VendorSpendExport($start, $end), $fileName);
        }
        // ==========================================
        // ==========================================
        // SUNTIKKAN KODE INI UNTUK MENANGKAP KARTU MUTASI
        // ==========================================
        if ($type === 'stock_mutation') {
            $fileName = 'Laporan_Kartu_Mutasi_Stok_' . date('Ymd', strtotime($start)) . '_sd_' . date('Ymd', strtotime($end)) . '.xlsx';
            return Excel::download(new \App\Exports\StockMutationExport($start, $end), $fileName);
        }
        // ==========================================
        // ==========================================
        // SUNTIKKAN KODE INI UNTUK MENANGKAP AGING HUTANG
        // ==========================================
        if ($type === 'aging_ap') {
            $fileName = 'Laporan_Aging_Hutang_Vendor_' . date('Ymd', strtotime($start)) . '_sd_' . date('Ymd', strtotime($end)) . '.xlsx';
            return Excel::download(new \App\Exports\AgingApExport($start, $end), $fileName);
        }
        // ==========================================

        // Jika jenis laporan belum kita buat:
        return back()->with('error', 'Laporan jenis ini masih dalam tahap perakitan di Markas Pusat, Komandan!');
    }



    public function threeWayMatching(Request $request)
    {
        $search = $request->input('search');

        // Tarik Data PR Items beserta relasi ke PO dan GR
        $prItemsQuery = PurchaseRequestItem::with([
            'purchaseRequest.company',
            'item.uom',
            'purchaseOrderItems.purchaseOrder',
            'purchaseOrderItems.goodsReceiptItems.goodsReceipt'
        ])
        ->whereHas('purchaseRequest', function($q) {
            $q->whereNotIn('status_id', [1, 2]); // Asumsi ID 1 & 2 adalah Draft/Ditolak
        });

        if ($search) {
            $prItemsQuery->whereHas('purchaseRequest', function($q) use ($search) {
                $q->where('pr_number', 'like', "%{$search}%");
            })->orWhereHas('item', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $prItems = $prItemsQuery->orderBy('id', 'desc')->paginate(15);

        // Olah data untuk visualisasi Dashboard
        $reportData = collect();

        foreach ($prItems as $prItem) {
            if (!$prItem->purchaseRequest) continue;

            $prConv = $this->getUomFactor($prItem->item_id, $prItem->uom, $prItem->uom_id);

            $prQty = (float) $prItem->qty;
            // Qty PO sudah terekam di PR dalam satuan PR (Hasil Self-Healing kita!)
            $poQty = (float) $prItem->ordered_qty;

            // Hitung total GR dalam satuan PR
            $grQty = 0;
            foreach ($prItem->purchaseOrderItems as $poItem) {
                $poConv = $this->getUomFactor($poItem->item_id, $poItem->uom, $poItem->uom_id);
                // Ubah Qty GR ke Satuan Dasar (Eceran), lalu bagi dengan faktor PR
                $baseGrQty = (float) ($poItem->qty_received ?? 0) * $poConv;
                $grQty += ($baseGrQty / $prConv);
            }

            // Penentuan Status Pencocokan (Matching)
            $status = 'PENDING PO';
            $color = 'danger';

            if (round($grQty, 2) >= round($prQty, 2)) {
                $status = 'MATCHED';
                $color = 'success';
            } elseif ($grQty > 0 && round($grQty, 2) < round($prQty, 2)) {
                $status = 'PARTIAL GR';
                $color = 'info';
            } elseif (round($poQty, 2) >= round($prQty, 2) && $grQty == 0) {
                $status = 'PENDING GR';
                $color = 'warning';
            } elseif ($poQty > 0 && round($poQty, 2) < round($prQty, 2)) {
                $status = 'PARTIAL PO';
                $color = 'primary';
            }

            // Kumpulkan Nomor Dokumen Terkait (Diubah menjadi Array, bukan String berkoma)
            $poNumbers = $prItem->purchaseOrderItems->map(function($poItem) {
                return $poItem->purchaseOrder->po_number ?? null;
            })->filter()->unique()->values()->toArray();

            $grNumbers = $prItem->purchaseOrderItems->flatMap(function($poItem) {
                return $poItem->goodsReceiptItems->map(function($grItem) {
                    return $grItem->goodsReceipt->gr_number ?? null;
                });
            })->filter()->unique()->values()->toArray();

            // 🔥 Tarik Teks UOM untuk Tampilan (Ditambahkan Satuan Dasar) 🔥
            $baseUnit = optional(optional($prItem->item)->uom)->name ?? 'PCS';
            $uomDisplay = $baseUnit;
            $rawUom = $prItem->getRawOriginal('uom');

            if (!empty($prItem->uom_id)) {
                $uomDb = \App\Models\ItemUom::find($prItem->uom_id);
                if ($uomDb) {
                    $uomDisplay = $uomDb->uom_name . ' (Isi: ' . (float)$uomDb->conversion_qty . ' ' . $baseUnit . ')';
                }
            } elseif (is_string($rawUom) && !str_starts_with(trim($rawUom), '{')) {
                $uomDisplay = $rawUom;
                // Jika teksnya "Pack (Isi: 20)" tanpa satuan dasarnya, kita suntikkan!
                if (preg_match('/\(Isi:\s*([0-9.]+)\)$/i', trim($uomDisplay), $matches)) {
                    $uomDisplay = str_replace($matches[0], '(Isi: ' . (float)$matches[1] . ' ' . $baseUnit . ')', $uomDisplay);
                }
            } else {
                $uomDisplay = $baseUnit;
            }

            $reportData->push((object)[
                'pr_number'  => $prItem->purchaseRequest->pr_number,
                'pr_date'    => $prItem->purchaseRequest->created_at,
                'company'    => $prItem->purchaseRequest->company->name ?? '-',
                'item_code'  => $prItem->item->code ?? '-',
                'item_name'  => $prItem->item->name ?? '-',
                'uom'        => $uomDisplay,
                'pr_qty'     => round($prQty, 2),
                'po_qty'     => round($poQty, 2),
                'gr_qty'     => round($grQty, 2),
                'po_numbers' => $poNumbers, // Sekarang berbentuk Array
                'gr_numbers' => $grNumbers, // Sekarang berbentuk Array
                'status'     => $status,
                'color'      => $color,
            ]);
        }
        return view('reports.three_way_matching', compact('reportData', 'prItems', 'search'));
    }



    // Helper Pembaca Konversi UOM (Sama seperti di PO & GR)
    private function getUomFactor($itemId, $uomString, $uomId = null)
    {
        if (!empty($uomId)) {
            $altUom = \App\Models\ItemUom::find($uomId);
            if ($altUom) return (float) $altUom->conversion_qty;
        }
        if (empty($uomString)) return 1;
        if (is_numeric($uomString)) {
            $altUom = \App\Models\ItemUom::find($uomString);
            if ($altUom) return (float) $altUom->conversion_qty;
        }
        if (preg_match('/(?:Isi|Qty|Konversi)\s*[:=]?\s*([0-9.]+)/i', $uomString, $matches)) {
            return (float) $matches[1];
        }
        $cleanUom = trim(preg_replace('/[\[\(\{].*?[\]\)\}]/', '', $uomString));
        if (!empty($cleanUom)) {
            $altUom = \App\Models\ItemUom::where('item_id', $itemId)
                        ->whereRaw('LOWER(uom_name) = ?', [strtolower($cleanUom)])
                        ->first();
            if ($altUom) return (float) $altUom->conversion_qty;
        }
        return 1;
    }





}
