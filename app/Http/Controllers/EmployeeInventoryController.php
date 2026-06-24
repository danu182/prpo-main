<?php

namespace App\Http\Controllers;

use App\Models\EmployeeInventory;
use App\Models\EmployeeInventoryHistory;
use App\Models\FixedAsset;
use Illuminate\Http\Request;

class EmployeeInventoryController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->search;

        // 1. Ambil Data Minor Asset (Dari Pengeluaran Gudang)
        $inventories = EmployeeInventory::with(['item', 'goodsReceipt'])
            ->whereHas('item', function($q) {
                // 🔥 PERBAIKAN: Bukan is_asset, melainkan item_type_code bukan AST 🔥
                $q->where('item_type_code', '!=', 'AST')->orWhereNull('item_type_code');
            })
            ->where('qty', '>', 0)
            ->get()
            ->groupBy('employee_name');

        // 2. Ambil Data Major Asset (Aset Tetap yang sedang 'In Use')
        $fixedAssets = FixedAsset::with(['item', 'assignee', 'status'])
            ->whereHas('status', function($query) {
                $query->where('slug', 'in_use');
            })
            ->whereNotNull('assigned_to')
            ->when($search, function($q) use ($search) {
                $q->whereHas('assignee', function($qa) use ($search) {
                    $qa->where('name', 'like', "%{$search}%");
                })->orWhereHas('item', function($qi) use ($search) {
                    $qi->where('name', 'like', "%{$search}%");
                });
            })
            ->get()
            ->groupBy(function($asset) {
                return optional($asset->assignee)->name;
            });

        // 3. Gabungkan daftar nama (Yang punya Minor Asset ATAU Major Asset)
        $allNames = $inventories->keys()
            ->merge($fixedAssets->keys())
            ->filter()
            ->unique()
            ->sort();

        return view('employee_inventories.index', compact('inventories', 'fixedAssets', 'allNames', 'search'));
    }

    public function history($employee_name)
    {
        // 1. Ambil Histori Minor Asset (Stok Barang)
        $minorHistories = \App\Models\EmployeeInventoryHistory::with('item')
            ->where('employee_name', $employee_name)
            ->whereHas('item', function($q) {
                // 🔥 PERBAIKAN: Blokir Barang Aset Tetap (AST) agar tidak muncul di Kotak Minor 🔥
                $q->where('item_type_code', '!=', 'AST')->orWhereNull('item_type_code');
            })
            ->get()
            ->map(function ($item) {
                // Cari ID Inventaris Aktif agar bisa dicetak Labelnya
                $inventoryId = \App\Models\EmployeeInventory::where('employee_name', $item->employee_name)
                                ->where('item_id', $item->item_id)
                                ->first()?->id;

                // Cari SN MINOR ASSET DARI DOKUMEN PENGELUARAN
                $snList = [];
                if (str_starts_with($item->reference_number, 'GI/')) {
                    $gi = \App\Models\GoodsIssue::where('gi_number', $item->reference_number)->first();
                    if ($gi) {
                        $giItem = \App\Models\GoodsIssueItem::where('goods_issue_id', $gi->id)->where('item_id', $item->item_id)->first();
                        if ($giItem && $giItem->notes) {
                            $snList = array_map('trim', explode('|', $giItem->notes));
                        }
                    }
                }

                return (object) [
                    'date'             => $item->created_at,
                    'type'             => 'MINOR',
                    'print_id'         => $inventoryId,
                    'action'           => $item->type == 'IN' ? 'TERIMA' : 'KEMBALI',
                    'item_name'        => optional($item->item)->name,
                    'specification'    => null,
                    'item_code'        => optional($item->item)->code,
                    'qty_or_sn'        => ($item->type == 'IN' ? '+' : '-') . ' ' . (float)$item->qty . ' unit',
                    'sn_list'          => $snList,
                    'reference_number' => $item->reference_number,
                    // 🔥 PERBAIKAN: Menghapus variabel hantu, memanggil data notes yang asli dari database 🔥
                    'notes'            => $item->notes ?? "Transaksi logistik via: " . $item->reference_number,
                ];
            });

        // 2. Ambil Histori Major Asset (Laptop/Mobil)
        $user = \App\Models\User::where('name', $employee_name)->first();
        $majorHistories = collect();

        if ($user) {
            // A. Cari ID Aset apa saja yang PERNAH atau SEDANG dipegang oleh User ini
            $assetIdsPernahDipegang = \App\Models\FixedAssetHistory::where('assigned_to', $user->id)
                                        ->pluck('fixed_asset_id')
                                        ->unique();

            if ($assetIdsPernahDipegang->isNotEmpty()) {
                // Tarik Histori dari yang paling lama (ASC) untuk membaca perjalanan aset
                $allAssetHistories = \App\Models\FixedAssetHistory::with(['fixedAsset.item', 'fixedAsset.company'])
                    ->whereIn('fixed_asset_id', $assetIdsPernahDipegang)
                    ->orderBy('created_at', 'asc') // Harus ASC untuk simulasi waktu
                    ->get()
                    ->groupBy('fixed_asset_id');

                foreach ($allAssetHistories as $assetId => $histories) {
                    $isHolding = false; // Penanda apakah user sedang memegang aset ini

                    foreach ($histories as $item) {
                        $asset = $item->fixedAsset;
                        $baseName = $asset->name ?? optional($asset->item)->name;
                        $spesifikasi = $asset->spesifikasi_detail;

                        // Pisahkan Nama dan Spek
                        $kodeLengkap = $asset->asset_number;
                        if ($asset->accounting_asset_number) { $kodeLengkap .= ' | Tag: ' . $asset->accounting_asset_number; }
                        if ($asset->company) { $kodeLengkap .= ' | Milik: ' . $asset->company->name; }

                        $historyObj = (object) [
                            'date'             => $item->created_at,
                            'type'             => 'MAJOR',
                            'print_id'         => $asset->id,
                            'item_name'        => $baseName,
                            'specification'    => \Illuminate\Support\Str::limit($spesifikasi, 75, '...'),
                            'item_code'        => $kodeLengkap,
                            'qty_or_sn'        => $asset->serial_number ?? 'S/N Tidak Ada',
                            'sn_list'          => [],
                            'reference_number' => 'ASET-TETAP',
                            'notes'            => $item->notes
                        ];

                        // Jika aset dipindah ke orang ini (dan sebelumnya belum dia pegang)
                        if ($item->assigned_to == $user->id && !$isHolding) {
                            $isHolding = true;
                            $historyObj->action = 'TERIMA';
                            $majorHistories->push($historyObj);
                        }
                        // Jika aset dicabut dari orang ini (dikembalikan/diberikan ke orang lain)
                        elseif ($item->assigned_to != $user->id && $isHolding) {
                            $isHolding = false;
                            $historyObj->action = 'KEMBALI';
                            $majorHistories->push($historyObj);
                        }
                    }
                }
            }
        }

        // 3. Gabungkan dan Urutkan dari yang terbaru (DESC)
        $allHistories = $minorHistories->concat($majorHistories)->sortByDesc('date');

        return view('employee_inventories.history', compact('allHistories', 'employee_name'));
    }


    // =========================================================================
    // FUNGSI CETAK QR MINOR ASSET
    // =========================================================================
    public function printQrLabel($id)
    {
        // Tarik data minor asset beserta relasi item-nya
        $inventory = \App\Models\EmployeeInventory::with('item')->findOrFail($id);

        // Kita bungkus ke dalam collection (array) agar formatnya sama dengan looping di Blade
        $assets = collect([$inventory]);

        return view('employee_inventories.print_qr', compact('assets'));
    }
}
