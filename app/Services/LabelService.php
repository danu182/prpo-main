<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Item;
use App\Models\FixedAsset;

class LabelService
{
    /**
     * Menyiapkan data aset/stok untuk dicetak ke label Tom & Jerry
     */
    public function generateLabelData($request, $assetId = null)
    {
        $assets = collect();
        $assetIds = [];

        // 🔥 Skenario 1: Cetak SN Mentah (Stok Biasa)
        if ($request->has('raw_sn') && $request->has('item_id')) {
            $item = Item::find($request->item_id);
            $rawSns = explode(',', $request->query('raw_sn'));

            foreach($rawSns as $sn) {
                $cleanSn = trim($sn);
                $companyName = null;
                $tglIn = now()->format('d M Y');
                $foundGrId = null;
                $foundWarehouseId = null;

                $masterItemName = $item->name ?? 'Barang Umum';
                $specificItemName = $masterItemName;
                $grNumber = '-';

                // Lacak via item_serials & goods_issue_items
                $serialData = DB::table('item_serials')->where('item_id', $item->id ?? 0)->where('serial_number', $cleanSn)->first();
                if ($serialData) {
                    if (isset($serialData->goods_receipt_id)) $foundGrId = $serialData->goods_receipt_id;
                    if (isset($serialData->warehouse_id)) $foundWarehouseId = $serialData->warehouse_id;

                    if (isset($serialData->goods_issue_id) && $serialData->goods_issue_id) {
                        $giItemRow = DB::table('goods_issue_items')->where('goods_issue_id', $serialData->goods_issue_id)->where('item_id', $item->id ?? 0)->first();
                        if ($giItemRow && isset($giItemRow->item_name) && $giItemRow->item_name) $specificItemName = $giItemRow->item_name;
                    }
                }

                // Lacak via goods_receipt_items
                if (!$foundGrId) {
                    $grItem = DB::table('goods_receipt_items')->where('item_id', $item->id ?? 0)
                        ->where(function($q) use ($cleanSn) {
                            $q->where('notes', 'like', '%' . $cleanSn . '%')->orWhere('serial_number', $cleanSn);
                        })->first();
                    if ($grItem && isset($grItem->goods_receipt_id)) $foundGrId = $grItem->goods_receipt_id;
                }

                // Tarik Data PO dan Company
                if ($foundGrId) {
                    $gr = DB::table('goods_receipts')->where('id', $foundGrId)->first();
                    if ($gr) {
                        $grNumber = $gr->gr_number ?? '-';
                        $tglIn = isset($gr->receipt_date) ? date('d M Y', strtotime($gr->receipt_date)) : (isset($gr->created_at) ? date('d M Y', strtotime($gr->created_at)) : $tglIn);
                        if (isset($gr->warehouse_id) && !$foundWarehouseId) $foundWarehouseId = $gr->warehouse_id;

                        if (isset($gr->purchase_order_id)) {
                            $po = DB::table('purchase_orders')->where('id', $gr->purchase_order_id)->first();
                            if ($po) {
                                $poArray = (array) $po;
                                if (array_key_exists('company_id', $poArray) && $po->company_id) {
                                    $companyName = DB::table('companies')->where('id', $po->company_id)->value('name');
                                } elseif (array_key_exists('purchase_request_id', $poArray) && $po->purchase_request_id) {
                                    $pr = DB::table('purchase_requests')->where('id', $po->purchase_request_id)->first();
                                    $prArray = (array) $pr;
                                    if ($pr && array_key_exists('company_id', $prArray) && $pr->company_id) $companyName = DB::table('companies')->where('id', $pr->company_id)->value('name');
                                }

                                if ($specificItemName === $masterItemName) {
                                    $poItemRow = DB::table('purchase_order_items')->where('purchase_order_id', $po->id)->where('item_id', $item->id ?? 0)->first();
                                    if ($poItemRow) {
                                        $poItemArr = (array) $poItemRow;
                                        if (array_key_exists('item_name', $poItemArr) && $poItemRow->item_name) {
                                            $specificItemName = $poItemRow->item_name;
                                        } elseif (array_key_exists('description', $poItemArr) && $poItemRow->description) {
                                            $specificItemName = $poItemRow->description;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                if (!$companyName && $foundWarehouseId) {
                    $stock = DB::table('inventory_stocks')->where('item_id', $item->id ?? 0)->where('warehouse_id', $foundWarehouseId)->first();
                    $stockArray = (array) $stock;
                    if ($stock && array_key_exists('company_id', $stockArray) && $stock->company_id) $companyName = DB::table('companies')->where('id', $stock->company_id)->value('name');
                }

                $fallbackQr = ($item->code ?? 'BRG') . '-' . ($grNumber !== '-' ? $grNumber : uniqid());

                $assets->push((object)[
                    'master_item_name' => $masterItemName,
                    'po_item_name' => $specificItemName,
                    'item_code' => $item->code ?? '-',
                    'company_name' => $companyName ?: 'PERUSAHAAN INTERNAL',
                    'serial_number' => $cleanSn ?: '-',
                    'asset_number' => '-',
                    'tanggal_in' => $tglIn,
                    'reference_number' => $grNumber,
                    'qr_data' => $cleanSn ?: $fallbackQr,
                ]);
            }
        }
        // 🔥 Skenario 2: Cetak Aset Resmi
        else {
            $assetIds = $request->query('ids') ? explode(',', $request->query('ids')) : [$assetId];
            $assetsData = FixedAsset::with(['item', 'company', 'goodsReceipt'])->whereIn('id', $assetIds)->get();

            foreach ($assetsData as $ast) {
                // Prioritas Dokumen: GR (dari field / relasi) -> Batch ID -> '-'
                $docNumber = '-';
                if (!empty($ast->reference_number) && str_starts_with($ast->reference_number, 'GR-')) {
                    $docNumber = $ast->reference_number;
                } elseif (!empty($ast->goods_receipt_id) && optional($ast->goodsReceipt)->gr_number) {
                    $docNumber = $ast->goodsReceipt->gr_number;
                } elseif (!empty($ast->batch_id)) {
                    $docNumber = $ast->batch_id;
                }

                $fallbackQr = (optional($ast->item)->code ?? 'BRG') . '-' . $docNumber;

                $assets->push((object)[
                    'master_item_name' => optional($ast->item)->name ?? '-',
                    'po_item_name' => $ast->name ?? '-',
                    'item_code' => optional($ast->item)->code ?? '-',
                    'company_name' => optional($ast->company)->name ?? 'PERUSAHAAN INTERNAL',
                    'serial_number' => $ast->serial_number ?: '-',
                    'asset_number' => $ast->asset_number ?: '-',
                    'tanggal_in' => $ast->acquisition_date ? date('d M Y', strtotime($ast->acquisition_date)) : '-',
                    'reference_number' => $docNumber,
                    'qr_data' => $ast->asset_number ?: ($ast->serial_number ?: $fallbackQr),
                ]);
            }
        }

        // Kembalikan dua variabel yang dibutuhkan oleh view
        return [
            'assets' => $assets,
            'assetIds' => $assetIds
        ];
    }
}
