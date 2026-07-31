<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\InventoryStock;
use App\Models\StockMutation;
use App\Models\FixedAsset;
use App\Models\FixedAssetHistory;
use App\Models\Status;
use App\Models\AssetPhoto; // 🔥 Panggil model foto
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage; // 🔥 Panggil Storage untuk hapus folder

class AssetCapitalizationController extends Controller
{

    public function index()
    {
        // 🔥 Tambahkan 'photos' agar bisa menampilkan thumbnail di halaman index
        $assets = \App\Models\FixedAsset::with(['item', 'status', 'photos'])
                    ->orderBy('created_at', 'desc')
                    ->paginate(10);

        return view('asset_capitalizations.index', compact('assets'));
    }


    // SHOW: Menampilkan Detail 1 Aset dengan Lengkap
    // =========================================================================
    public function show($id)
    {
        // 🔥 Tambahkan 'histories' dan 'photos'
        $asset = \App\Models\FixedAsset::with([
            'item',
            'status',
            'goodsReceipt.po.vendor',
            'histories',
            'photos' // 🔥 Relasi ke tabel asset_photos
        ])->findOrFail($id);

        $categoryName = '-';
        if ($asset->asset_category_id) {
            $cat = \DB::table('asset_categories')->where('id', $asset->asset_category_id)->first();
            $categoryName = $cat ? $cat->name . ' (' . $cat->useful_life_years . ' Tahun)' : '-';
        }

        return view('asset_capitalizations.show', compact('asset', 'categoryName'));
    }

    // =========================================================================
    // 🔥 1. FUNGSI CREATE: MENAMPILKAN HALAMAN & DOKUMEN GR 🔥
    // =========================================================================
    public function create()
    {
        $rawGrs = \App\Models\GoodsReceipt::with(['items.item', 'po.vendor'])
                    ->orderBy('received_date', 'desc')
                    ->limit(100)
                    ->get();

        $grIds = $rawGrs->pluck('id')->toArray();

        // Tarik semua aset yang pernah dikapitalisasi dari GR tersebut
        $allCapitalizedAssets = \App\Models\FixedAsset::with('status')
            ->whereIn('goods_receipt_id', $grIds)
            ->get();

        $validGrs = collect();

        foreach ($rawGrs as $gr) {
            $hasSisa = false;

            foreach ($gr->items as $grItem) {
                $grConvRate = 1;
                $rawUom = $grItem->getRawOriginal('uom') ?: '';

                if (preg_match('/Isi\s*[:=]?\s*([0-9.]+)/i', $rawUom, $matches)) {
                    $grConvRate = (float) $matches[1];
                } elseif ($grItem->uom_id) {
                    $uomDb = \App\Models\ItemUom::find($grItem->uom_id);
                    if ($uomDb) $grConvRate = (float) $uomDb->conversion_qty;
                }

                $baseQtyReceived = ($grItem->qty_received - ($grItem->qty_returned ?? 0)) * $grConvRate;

                // =========================================================================
                // 🔥 SUPER FILTER VOID MENGGUNAKAN PHP COLLECTION (AKURASI 1000%) 🔥
                // =========================================================================
                $alreadyCapitalized = $allCapitalizedAssets->filter(function($ast) use ($gr, $grItem) {
                    // Pastikan barang dan dokumennya sesuai
                    if ($ast->goods_receipt_id != $gr->id || $ast->item_id != $grItem->item_id) {
                        return false;
                    }

                    $statusName = strtolower(optional($ast->status)->name ?? '');
                    $statusSlug = strtolower(optional($ast->status)->slug ?? '');
                    $notes = strtolower($ast->notes ?? '');

                    $isVoid = false;
                    // Deteksi dari Status
                    if (str_contains($statusName, 'void') || str_contains($statusName, 'batal')) $isVoid = true;
                    if (str_contains($statusSlug, 'void') || str_contains($statusSlug, 'batal')) $isVoid = true;
                    // Deteksi dari Catatan
                    if (str_contains($notes, '[dibatalkan')) $isVoid = true;

                    // Kembalikan TRUE (Dihitung) HANYA JIKA BUKAN VOID
                    return !$isVoid;
                })->count();
                // =========================================================================

                // Suntikkan hasil hitungan
                $grItem->sisa_bisa_diakui = $baseQtyReceived - $alreadyCapitalized;

                // Jika masih ada sisa, tampilkan dokumen GR ini
                if ($grItem->sisa_bisa_diakui > 0) {
                    $hasSisa = true;
                }
            }

            if ($hasSisa) {
                $validGrs->push($gr);
            }
        }

        $assetCategories = \App\Models\AssetCategory::where('is_active', true)->get();

        return view('asset_capitalizations.create', [
            'grs' => $validGrs,
            'assetCategories' => $assetCategories
        ]);
    }


    // =========================================================================
    // 🔥 FUNGSI AJAX: MENGAMBIL ITEM GR (FILTER VOID MURNI PHP - 1000% AKURAT) 🔥
    // =========================================================================
    // public function getItems($id)
    // {
    //     $gr = \App\Models\GoodsReceipt::with(['items.item', 'items.uom', 'warehouse'])->findOrFail($id);

    //     $items = [];

    //     foreach ($gr->items as $grItem) {
    //         $grConvRate = 1;
    //         $rawUom = $grItem->getRawOriginal('uom') ?: '';

    //         if (preg_match('/Isi\s*[:=]?\s*([0-9.]+)/i', $rawUom, $matches)) {
    //             $grConvRate = (float) $matches[1];
    //         } elseif ($grItem->uom_id) {
    //             $uomDb = \App\Models\ItemUom::find($grItem->uom_id);
    //             if ($uomDb) $grConvRate = (float) $uomDb->conversion_qty;
    //         }

    //         $baseQtyReceived = ($grItem->qty_received - ($grItem->qty_returned ?? 0)) * $grConvRate;

    //         // =========================================================================
    //         // 🔥 TARIK DATA KE PHP & FILTER MANUAL (ANTI-BUG SQL) 🔥
    //         // =========================================================================
    //         $capitalizedAssets = \App\Models\FixedAsset::with('status')
    //             ->where('goods_receipt_id', $gr->id)
    //             ->where('item_id', $grItem->item_id)
    //             ->get(); // Tarik dulu semua data asetnya ke PHP!

    //         $alreadyCapitalized = 0; // Mulai dari 0

    //         foreach ($capitalizedAssets as $ast) {
    //             // Ambil nilai teks dan ubah ke huruf kecil semua agar gampang dicek
    //             $statusString = strtolower(optional($ast->status)->name . ' ' . optional($ast->status)->slug);
    //             $notesString  = strtolower($ast->notes ?? '');

    //             // JIKA ASET MENGANDUNG KATA VOID / BATAL -> ABAIKAN!
    //             if (str_contains($statusString, 'void') ||
    //                 str_contains($statusString, 'batal') ||
    //                 str_contains($notesString, '[dibatalkan')) {
    //                 continue; // Lewati, JANGAN DIHITUNG!
    //             }

    //             // Jika aset normal/valid, tambahkan ke hitungan
    //             $alreadyCapitalized++;
    //         }
    //         // =========================================================================

    //         $maxCapitalizable = $baseQtyReceived - $alreadyCapitalized;

    //         // Masukkan ke array jika sisa masih ada
    //         if ($maxCapitalizable > 0) {

    //             $availableSns = [];
    //             if (\Schema::hasTable('item_serials')) {
    //                 $availableSns = \DB::table('item_serials')
    //                     ->where('item_id', $grItem->item_id)
    //                     ->where('goods_receipt_id', $gr->id)
    //                     ->where('status', 'AVAILABLE') // Pastikan SN yang di-Void bisa ditarik lagi
    //                     ->pluck('serial_number')
    //                     ->toArray();
    //             }

    //             $items[] = [
    //                 'item_id' => $grItem->item_id,
    //                 'item_code' => optional($grItem->item)->code,
    //                 'item_name' => optional($grItem->item)->name,
    //                 'specific_name' => $grItem->specific_name ?? optional($grItem->item)->name,
    //                 'gr_qty' => $baseQtyReceived,
    //                 'max_capitalizable' => $maxCapitalizable, // 🔥 PASTI KEMBALI KE 8! 🔥
    //                 'base_uom' => optional($grItem->uom)->name ?? 'Pieces',
    //                 'default_price' => $grItem->unit_price ?? 0,
    //                 'default_date' => date('Y-m-d', strtotime($gr->received_date)),
    //                 'default_spec' => $grItem->notes ?? optional($grItem->item)->specification ?? '',
    //                 'available_sns' => $availableSns
    //             ];
    //         }
    //     }

    //     return response()->json([
    //         'warehouse_id' => $gr->warehouse_id,
    //         'items' => $items
    //     ]);
    // }




    public function getGrItems($gr_id)
    {
        try {
            $gr = \App\Models\GoodsReceipt::with([
                'items.item.uom',
                'warehouse',
                'purchaseOrder.items',
                'po.items'
            ])->findOrFail($gr_id);

            $grDate = $gr->received_date;
            $po = $gr->purchaseOrder ?? $gr->po;

            $biayaTambahanPerUnit = 0;
            $diskonHeaderPerUnit = 0;

            if ($po) {
                $totalBiayaTambahan = (float) ($po->charge_total ?? $po->additional_cost ?? 0);
                $totalDiskonHeader  = (float) ($po->discount_total ?? $po->header_discount ?? 0);

                $poItems = $po->items ?? $po->details ?? collect([]);
                $totalQtyPO = $poItems->sum('qty_ordered') > 0 ? $poItems->sum('qty_ordered') : $poItems->sum('qty');

                if ($totalQtyPO <= 0) {
                    $totalQtyPO = 1;
                }

                $biayaTambahanPerUnit = $totalBiayaTambahan / $totalQtyPO;
                $diskonHeaderPerUnit  = $totalDiskonHeader / $totalQtyPO;
            }

            $items = [];
            foreach ($gr->items as $grItem) {
                $masterItem = $grItem->item;
                if (!$masterItem) continue;

                $grConvRate = 1;
                if (preg_match('/Isi\s*[:=]?\s*([0-9.]+)/i', $grItem->getRawOriginal('uom'), $matches)) {
                    $grConvRate = (float) $matches[1];
                } elseif ($grItem->uom_id) {
                    $uomDb = \App\Models\ItemUom::find($grItem->uom_id);
                    if ($uomDb) $grConvRate = (float) $uomDb->conversion_qty;
                }

                $poItem = null;
                if ($po) {
                    $poItems = $po->items ?? $po->details ?? collect([]);
                    $poItem = $poItems->where('item_id', $masterItem->id)->first();
                    if (!$poItem) $poItem = $poItems->first();
                }

                $netUnitPrice = 0;
                $specificName = $masterItem->name;

                if ($poItem) {
                    $specificName = $poItem->item_name ?? $masterItem->name;

                    $qtyOrdered = (float) ($poItem->qty_ordered ?? $poItem->qty ?? 1);
                    if ($qtyOrdered <= 0) $qtyOrdered = 1;

                    $subtotalBaris = (float) ($poItem->subtotal ?? 0);
                    $unitPriceAsli = (float) ($poItem->unit_price ?? 0);

                    $discountBaris = (float) ($poItem->discount_amount ?? $poItem->discount ?? 0);

                    if ($subtotalBaris > 0) {
                        $netUnitPrice = $subtotalBaris / $qtyOrdered;
                    } else {
                        $netUnitPrice = $unitPriceAsli - $discountBaris;
                    }
                } else {
                    $netUnitPrice = (float) ($masterItem->purchase_price ?? 0);
                }

                $hargaPerolehan = $netUnitPrice + $biayaTambahanPerUnit - $diskonHeaderPerUnit;

                $baseQtyReceived = ($grItem->qty_received - ($grItem->qty_returned ?? 0)) * $grConvRate;
                if ($baseQtyReceived <= 0) continue;

                $currentStock = \App\Models\InventoryStock::where('item_id', $masterItem->id)->sum('stock_qty');

                // =========================================================================
                // 🔥 JURUS SNIPER PHP: FILTER VOID ASET (MENGGANTIKAN COUNT BAWAAN) 🔥
                // =========================================================================
                $capitalizedAssets = \App\Models\FixedAsset::with('status')
                    ->where('goods_receipt_id', $gr->id)
                    ->where('item_id', $masterItem->id)
                    ->get(); // Tarik semua aset ke memori PHP

                $alreadyCapitalized = 0;

                foreach ($capitalizedAssets as $ast) {
                    $statusString = strtolower(optional($ast->status)->name . ' ' . optional($ast->status)->slug);
                    $notesString  = strtolower($ast->notes ?? '');

                    // JIKA ASET MENGANDUNG KATA VOID / BATAL -> ABAIKAN!
                    if (str_contains($statusString, 'void') ||
                        str_contains($statusString, 'batal') ||
                        str_contains($notesString, '[dibatalkan')) {
                        continue;
                    }

                    // Jika aset normal/valid, tambahkan ke hitungan
                    $alreadyCapitalized++;
                }
                // =========================================================================

                $maxCapitalizable = $baseQtyReceived - $alreadyCapitalized;

                $availableSns = [];
                if (isset($masterItem->is_trackable) && $masterItem->is_trackable) {
                    $availableSns = \DB::table('item_serials')
                        ->where('item_id', $masterItem->id)
                        ->where('goods_receipt_id', $gr->id)
                        ->whereNotIn('status', ['CAPITALIZED', 'RETURNED'])
                        ->pluck('serial_number')
                        ->toArray();
                }

                if ($maxCapitalizable > 0) {
                    $defaultSpec = $poItem ? ($poItem->description ?? $poItem->specification ?? $poItem->notes ?? '') : '';

                    $items[] = [
                        'item_id'           => $masterItem->id,
                        'item_code'         => $masterItem->code,
                        'item_name'         => $specificName,
                        'master_name'       => $masterItem->name,
                        'specific_name'     => $specificName,
                        'base_uom'          => optional($masterItem->uom)->name ?? 'Unit',
                        'gr_qty'            => $baseQtyReceived,
                        'current_stock'     => $currentStock,
                        'max_capitalizable' => floor($maxCapitalizable), // 🔥 PASTI KEMBALI JADI 8! 🔥
                        'available_sns'     => $availableSns,
                        'default_price'     => round($hargaPerolehan, 2),
                        'default_date'      => date('Y-m-d', strtotime($grDate)),
                        'default_spec'      => $defaultSpec
                    ];
                }
            }

            return response()->json([
                'warehouse_id' => $gr->warehouse_id,
                'warehouse_name' => optional($gr->warehouse)->name ?? 'Gudang Global',
                'items' => $items
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'goods_receipt_id' => 'required|exists:goods_receipts,id',
            'items'            => 'required|array',
            'items.*.qty'      => 'required|numeric|min:0',
            'items.*.details.*.accounting_no'     => 'nullable|string|distinct|unique:fixed_assets,accounting_asset_number',
            'items.*.details.*.asset_category_id' => 'required|exists:asset_categories,id',
            // Validasi foto (Maksimal 2MB per foto, tipe harus gambar)
            'items.*.details.*.photos.*'          => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ], [
            'items.*.details.*.accounting_no.distinct' => 'Nomor Akuntansi (FA) ada yang kembar di dalam form ini.',
            'items.*.details.*.accounting_no.unique'   => 'Nomor Akuntansi (FA) tersebut sudah dipakai oleh aset lain.',
            'items.*.details.*.asset_category_id.required' => 'Kategori penyusutan wajib dipilih untuk setiap unit.',
            'items.*.details.*.photos.*.image' => 'File yang diupload harus berupa gambar (JPG, PNG).',
            'items.*.details.*.photos.*.max' => 'Ukuran setiap gambar maksimal 2MB.',
        ]);

        $snList = [];
        foreach ($request->items as $itemId => $data) {
            if (!isset($data['details'])) continue;
            foreach ($data['details'] as $detail) {
                if (!empty($detail['serial_number'])) {
                    if (in_array($detail['serial_number'], $snList)) {
                        return back()->withInput()->with('error', 'GAGAL: Terdapat Serial Number (SN) yang kembar: ' . $detail['serial_number']);
                    }
                    $snList[] = $detail['serial_number'];
                }
            }
        }

        try {
            DB::transaction(function () use ($request) {
                $gr = GoodsReceipt::with(['purchaseOrder', 'po'])->findOrFail($request->goods_receipt_id);

                $statusAvailableId = Status::where('type', 'AST')->where('slug', 'available')->value('id') ?? 31;
                $statusInUseId     = Status::where('type', 'AST')->where('slug', 'in_use')->value('id') ?? 32;

                $poData = $gr->purchaseOrder ?? $gr->po;
                $currencyCode = optional($poData)->currency ?? 'IDR';
                $currencyDb = \DB::table('currencies')->where('code', $currencyCode)->first();
                $currencyId = $currencyDb ? $currencyDb->id : 1;
                $companyId  = optional($poData)->company_id ?? optional($poData)->bill_to_company_id ?? null;

                foreach ($request->items as $itemId => $data) {
                    $qtyToCapitalize = (int) ($data['qty'] ?? 0);
                    if ($qtyToCapitalize <= 0) continue;

                    $masterItem = Item::findOrFail($itemId);

                    $availableStocks = InventoryStock::where('item_id', $itemId)
                                        ->where('stock_qty', '>', 0)
                                        ->orderBy('created_at', 'asc')->lockForUpdate()->get();

                    $totalAvailable = $availableStocks->sum('stock_qty');

                    $qtyFromStock = min($qtyToCapitalize, $totalAvailable);
                    $qtyRetroactive = $qtyToCapitalize - $qtyFromStock;

                    $actualWarehouseId = $gr->warehouse_id ?? 1;

                    if ($qtyFromStock > 0) {
                        $qtySisaPotong = $qtyFromStock;
                        $saldoTotalSaatIni = (float) $masterItem->current_stock;

                        foreach ($availableStocks as $stockRow) {
                            if ($qtySisaPotong <= 0) break;
                            $potong = min($stockRow->stock_qty, $qtySisaPotong);
                            $actualWarehouseId = $stockRow->warehouse_id;

                            $stockRow->decrement('stock_qty', $potong);
                            $qtySisaPotong -= $potong;

                            StockMutation::create([
                                'item_id'          => $masterItem->id,
                                'warehouse_id'     => $stockRow->warehouse_id,
                                'type'             => 'OUT',
                                'qty'              => $potong,
                                'balance_before'   => $saldoTotalSaatIni,
                                'balance_after'    => $saldoTotalSaatIni - $potong,
                                'reference_number' => $gr->gr_number,
                                'notes'            => "[CAPITALIZE] Kapitalisasi menjadi Aset Tetap",
                                'created_by'       => auth()->id(),
                            ]);
                        }
                    }

                    $details = $data['details'] ?? [];
                    for ($i = 0; $i < $qtyToCapitalize; $i++) {
                        $detail = $details[$i] ?? [];

                        $isRetroactive = $i >= $qtyFromStock;
                        $currentAssetStatus = $isRetroactive ? $statusInUseId : $statusAvailableId;

                        $year = date('Y'); $month = date('m');
                        $prefix = "AST/{$year}/{$month}/";
                        $lastAsset = FixedAsset::where('asset_number', 'like', "{$prefix}%")->orderBy('id', 'desc')->first();
                        $nextSeq = $lastAsset ? ((int) substr($lastAsset->asset_number, -4)) + 1 : 1;
                        $sysAssetNumber = $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

                        $accountingNo = $detail['accounting_no'] ?? null;
                        $specificName = $detail['specific_name'] ?? $masterItem->name;
                        $acqDate      = $detail['acquisition_date'] ?? date('Y-m-d');
                        $accValue     = $detail['accounting_value'] ?? 0;
                        $categoryId   = $detail['asset_category_id'] ?? null;
                        $specDetail   = $detail['notes'] ?? '';

                        $serialNumber = $detail['serial_number'] ?? null;

                        $extraNote = "Diakui dari dokumen penerimaan: {$gr->gr_number}.";
                        if ($isRetroactive) {
                            $extraNote .= " [RETROACTIVE: Diakui setelah barang didistribusikan / Goods Issue].";
                        }

                        $newAsset = FixedAsset::create([
                            'asset_number'            => $sysAssetNumber,
                            'item_id'                 => $masterItem->id,
                            'asset_category_id'       => $categoryId,
                            'warehouse_id'            => $actualWarehouseId,
                            'company_id'              => $companyId,
                            'goods_receipt_id'        => $gr->id,
                            'name'                    => $specificName,
                            'serial_number'           => $serialNumber,
                            'accounting_asset_number' => $accountingNo,
                            'acquisition_date'        => $acqDate,
                            'purchase_price'          => $accValue,
                            'currency_id'             => $currencyId,
                            'spesifikasi_detail'      => $specDetail,
                            'status_id'               => $currentAssetStatus,
                            'notes'                   => $extraNote,
                        ]);

                        // 🔥 PROSES UPLOAD FOTO MULTIPLE KE TABEL ASSET_PHOTOS 🔥
                        if ($request->hasFile("items.{$itemId}.details.{$i}.photos")) {
                            $uploadedFiles = $request->file("items.{$itemId}.details.{$i}.photos");


                            // 🔥 Ubah garis miring (/) jadi strip (-) khusus untuk nama folder
                            $safeFolderName = str_replace('/', '-', $sysAssetNumber);
                            $folderPath = "FixAsset/{$safeFolderName}";

                            foreach ($uploadedFiles as $file) {
                                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                                $path = $file->storeAs($folderPath, $filename, 'public');

                                // Simpan ke tabel relasi asset_photos
                                AssetPhoto::create([
                                    'fixed_asset_id' => $newAsset->id,
                                    'file_path'      => $path
                                ]);
                            }
                        }

                        if (!empty($serialNumber)) {
                            \DB::table('item_serials')
                                ->where('item_id', $masterItem->id)
                                ->where('serial_number', $serialNumber)
                                ->update(['status' => 'CAPITALIZED', 'updated_at' => now()]);
                        }

                        $historyText = $isRetroactive ? "Aset diregistrasi secara Retroactive (Barang sudah dipakai user)." : "Aset diregistrasi dari Stok Gudang.";

                        FixedAssetHistory::create([
                            'fixed_asset_id' => $newAsset->id,
                            'status'         => 'Registered (Terdaftar)',
                            'notes'          => $historyText . " (Ref GR: {$gr->gr_number}).",
                            'created_by'     => auth()->id(),
                        ]);
                    }
                }
            });

            return redirect()->route('asset-capitalizations.create')->with('success', 'Luar biasa! Pengakuan Aset berhasil disimpan beserta foto fisiknya.');

        } catch (\Exception $e) {
            Log::error('Error Kapitalisasi Aset: ' . $e->getMessage());
            return back()->withInput()->with('error', $e->getMessage());
        }
    }


    // =========================================================================
    // VOID: Proses Pembatalan Pengakuan Aset & Pengembalian Stok
    // =========================================================================
    public function voidAsset($id)
    {
        try {
            \DB::beginTransaction();

            $asset = \App\Models\FixedAsset::with('item')->findOrFail($id);

            // 1. Validasi Status
            $statusAvailableId = \App\Models\Status::where('type', 'AST')->where('slug', 'available')->value('id') ?? 31;

            if ($asset->status_id != $statusAvailableId) {
                throw new \Exception("GAGAL: Aset tidak bisa dibatalkan karena sedang digunakan di luar gudang.");
            }

            // 1.5 GEMBOK AUDIT: Tolak VOID jika aset sudah pernah diserahkan/dipakai
            $hasUsedHistory = \App\Models\FixedAssetHistory::where('fixed_asset_id', $id)
                ->where(function($q) {
                    $q->where('status', 'like', '%Diserahkan%')
                      ->orWhere('status', 'like', '%Dikembalikan%')
                      ->orWhere('notes', 'like', '%Diserahkan%');
                })->exists();

            if ($hasUsedHistory) {
                throw new \Exception("DITOLAK SISTEM: Aset tidak bisa di-VOID karena sudah memiliki riwayat transaksi/pemakaian. Jika aset rusak, gunakan menu Penghapusan (Disposal Aset).");
            }

            $masterItem = $asset->item;
            $balanceBefore = (float) $masterItem->current_stock;
            $balanceAfter = $balanceBefore + 1;

            // 🔥 TAMBAHKAN BARIS INI UNTUK UPDATE STOK MASTER BARANG 🔥
            $masterItem->update(['current_stock' => $balanceAfter]);

            // 2. KEMBALIKAN STOK FISIK KE GUDANG BIASA
            $invStock = \App\Models\InventoryStock::where('item_id', $masterItem->id)
                            ->where('warehouse_id', $asset->warehouse_id)
                            ->first();

            if ($invStock) {
                $invStock->increment('stock_qty', 1);
            } else {
                \App\Models\InventoryStock::create([
                    'company_id'       => $asset->company_id,
                    'warehouse_id'     => $asset->warehouse_id,
                    'item_id'          => $masterItem->id,
                    'stock_qty'        => 1,
                    'reference_number' => $asset->asset_number . '-VOID',
                    'notes'            => 'Pengembalian dari Void Aset',
                ]);
            }

            // 3. CATAT MUTASI REVERT (Tipe: IN)
            if (class_exists(\App\Models\StockMutation::class)) {
                \App\Models\StockMutation::create([
                    'item_id'          => $masterItem->id,
                    'warehouse_id'     => $asset->warehouse_id,
                    'type'             => 'IN',
                    'qty'              => 1,
                    'balance_before'   => $balanceBefore,
                    'balance_after'    => $balanceAfter,
                    'reference_number' => $asset->asset_number,
                    'notes'            => "[DE-CAPITALIZE] Pembatalan Pengakuan Aset (VOID).",
                    'created_by'       => auth()->id(),
                ]);
            }

            // 4. BEBASKAN SERIAL NUMBER (SN) AGAR BISA DIPAKAI LAGI
            if (!empty($asset->serial_number)) {
                \DB::table('item_serials')
                    ->where('item_id', $masterItem->id)
                    ->where('serial_number', $asset->serial_number)
                    ->update(['status' => 'AVAILABLE', 'updated_at' => now()]);
            }

            // 5. UBAH STATUS ASET MENJADI VOID
            $statusVoid = \App\Models\Status::where('type', 'AST')
                ->where(function($q) {
                    $q->where('slug', 'like', '%void%')
                      ->orWhere('slug', 'like', '%batal%')
                      ->orWhere('name', 'like', '%Batal%')
                      ->orWhere('name', 'like', '%Void%');
                })->first();

            if (!$statusVoid) {
                $statusVoid = \App\Models\Status::create([
                    'type' => 'AST',
                    'name' => 'Batal / Void',
                    'slug' => 'void',
                    'is_active' => true
                ]);
            }

            $asset->update([
                'status_id' => $statusVoid->id,
                'notes'     => $asset->notes . "\n[DIBATALKAN PADA " . date('d-m-Y H:i') . "]"
            ]);

            // 🔥 6. HAPUS FOLDER FOTO FISIK & DATA TABEL SAAT DIBATALKAN 🔥
            $safeFolderName = str_replace('/', '-', $asset->asset_number);
            $folderPath = "FixAsset/{$safeFolderName}";
            if (Storage::disk('public')->exists($folderPath)) {
                Storage::disk('public')->deleteDirectory($folderPath);
            }
            // Bersihkan data relasi foto dari database
            AssetPhoto::where('fixed_asset_id', $asset->id)->delete();

            \DB::commit();
            return back()->with('success', "Aset {$asset->asset_number} berhasil dibatalkan. Foto fisik telah dihapus otomatis dan stok dikembalikan ke gudang.");

        } catch (\Exception $e) {
            \DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }


     // =========================================================================
    // 🔥 FUNGSI AJAX: MENGAMBIL ITEM GR (FILTER VOID MURNI PHP - 1000% AKURAT) 🔥
    // =========================================================================
    public function getItems($id)
    {
        $gr = \App\Models\GoodsReceipt::with(['items.item', 'items.uom', 'warehouse'])->findOrFail($id);

        $items = [];

        foreach ($gr->items as $grItem) {
            $grConvRate = 1;
            $rawUom = $grItem->getRawOriginal('uom') ?: '';

            if (preg_match('/Isi\s*[:=]?\s*([0-9.]+)/i', $rawUom, $matches)) {
                $grConvRate = (float) $matches[1];
            } elseif ($grItem->uom_id) {
                $uomDb = \App\Models\ItemUom::find($grItem->uom_id);
                if ($uomDb) $grConvRate = (float) $uomDb->conversion_qty;
            }

            $baseQtyReceived = ($grItem->qty_received - ($grItem->qty_returned ?? 0)) * $grConvRate;

            // =========================================================================
            // 🔥 TARIK DATA KE PHP & FILTER MANUAL (ANTI-BUG SQL) 🔥
            // =========================================================================
            $capitalizedAssets = \App\Models\FixedAsset::with('status')
                ->where('goods_receipt_id', $gr->id)
                ->where('item_id', $grItem->item_id)
                ->get(); // Tarik dulu semua data asetnya ke PHP!

            $alreadyCapitalized = 0; // Mulai dari 0

            foreach ($capitalizedAssets as $ast) {
                // Ambil nilai teks dan ubah ke huruf kecil semua agar gampang dicek
                $statusString = strtolower(optional($ast->status)->name . ' ' . optional($ast->status)->slug);
                $notesString  = strtolower($ast->notes ?? '');

                // JIKA ASET MENGANDUNG KATA VOID / BATAL -> ABAIKAN!
                if (str_contains($statusString, 'void') ||
                    str_contains($statusString, 'batal') ||
                    str_contains($notesString, '[dibatalkan')) {
                    continue; // Lewati, JANGAN DIHITUNG!
                }

                // Jika aset normal/valid, tambahkan ke hitungan
                $alreadyCapitalized++;
            }
            // =========================================================================

            $maxCapitalizable = $baseQtyReceived - $alreadyCapitalized;

            // Masukkan ke array jika sisa masih ada
            if ($maxCapitalizable > 0) {

                $availableSns = [];
                if (\Schema::hasTable('item_serials')) {
                    $availableSns = \DB::table('item_serials')
                        ->where('item_id', $grItem->item_id)
                        ->where('goods_receipt_id', $gr->id)
                        ->where('status', 'AVAILABLE') // Pastikan SN yang di-Void bisa ditarik lagi
                        ->pluck('serial_number')
                        ->toArray();
                }

                $items[] = [
                    'item_id' => $grItem->item_id,
                    'item_code' => optional($grItem->item)->code,
                    'item_name' => optional($grItem->item)->name,
                    'specific_name' => $grItem->specific_name ?? optional($grItem->item)->name,
                    'gr_qty' => $baseQtyReceived,
                    'max_capitalizable' => $maxCapitalizable, // 🔥 PASTI KEMBALI KE 8! 🔥
                    'base_uom' => optional($grItem->uom)->name ?? 'Pieces',
                    'default_price' => $grItem->unit_price ?? 0,
                    'default_date' => date('Y-m-d', strtotime($gr->received_date)),
                    'default_spec' => $grItem->notes ?? optional($grItem->item)->specification ?? '',
                    'available_sns' => $availableSns
                ];
            }
        }

        return response()->json([
            'warehouse_id' => $gr->warehouse_id,
            'items' => $items
        ]);
    }


}
