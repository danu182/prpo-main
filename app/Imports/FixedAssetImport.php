<?php

namespace App\Imports;

use App\Models\FixedAsset;
use App\Models\FixedAssetHistory;
use App\Models\Item;
use App\Models\Company;
use App\Models\Status;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\InventoryStock;
use App\Models\StockMutation;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;

class FixedAssetImport implements ToCollection, WithHeadingRow
{
    protected $batchId;
    protected $docPath;

    // 🔥 currencyId dihapus dari konstruktor, ditarik dari Excel langsung
    public function __construct($batchId = null, $docPath = null)
    {
        $this->batchId = $batchId ?? 'IMP-' . date('Ymd-His');
        $this->docPath = $docPath;
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            $yearMonth = date('Y/m');
            $defaultStatus = Status::where('type', 'AST')->where('slug', 'available')->first();

            foreach ($rows as $row) {
                if (empty($row['kode_barang'])) continue;

                $item = Item::lockForUpdate()->where('code', trim($row['kode_barang']))->first();
                if (!$item) continue;

                $companyId = null;
                if (!empty($row['nama_pt'])) {
                    $company = Company::where('name', 'like', "%" . trim($row['nama_pt']) . "%")->first();
                    $companyId = $company ? $company->id : null;
                }

                $warehouseId = null;
                if (!empty($row['nama_gudang'])) {
                    $warehouse = Warehouse::where('name', 'like', "%" . trim($row['nama_gudang']) . "%")->first();
                    $warehouseId = $warehouse ? $warehouse->id : 1;
                }

                $statusName = $row['status'] ?? 'Available (Tersedia)';
                $cleanStatusName = trim(explode('(', $statusName)[0]);
                $status = Status::where('type', 'AST')->where('name', 'like', "%{$cleanStatusName}%")->first() ?? $defaultStatus;

                $assignedTo = null;
                if ($status && $status->slug === 'in_use' && !empty($row['nama_peminjam'])) {
                    $user = User::where('name', 'like', "%" . trim($row['nama_peminjam']) . "%")->first();
                    $assignedTo = $user ? $user->id : null;
                }

                $rawDate = $row['tanggal_perolehan'] ?? null;
                $acqDate = null;
                if (!empty($rawDate)) {
                    if (is_numeric($rawDate)) { $acqDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($rawDate)->format('Y-m-d'); }
                    else { try { $acqDate = \Carbon\Carbon::parse(str_replace('/', '-', $rawDate))->format('Y-m-d'); } catch (\Exception $e) { $acqDate = null; } }
                }

                $purchasePrice = $row['harga_beli_angka_murni'] ?? 0;
                $cleanPrice = preg_replace('/[^0-9]/', '', $purchasePrice);

                // 🔥 TANGKAP MATA UANG DARI EXCEL 🔥
                $currencyCode = strtoupper(trim($row['mata_uang'] ?? 'IDR'));
                if (empty($currencyCode)) $currencyCode = 'IDR';
                $currency = \App\Models\Currency::where('code', $currencyCode)->first();
                $currencyId = $currency ? $currency->id : 1;

                $finalName = !empty($row['nama_spesifik_aset']) ? trim($row['nama_spesifik_aset']) : $item->name;

                $lastAsset = FixedAsset::where('asset_number', 'like', "AST/{$yearMonth}/%")->orderBy('id', 'desc')->lockForUpdate()->first();
                $nextSeq = $lastAsset ? ((int) substr($lastAsset->asset_number, -4)) + 1 : 1;
                $assetNumber = "AST/{$yearMonth}/" . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

                // SIMPAN ASET
                $asset = FixedAsset::create([
                    'asset_number'            => $assetNumber,
                    'item_id'                 => $item->id,
                    'name'                    => $finalName,
                    'warehouse_id'            => $warehouseId,
                    'company_id'              => $companyId,
                    'serial_number'           => $row['serial_number'] ?? null,
                    'accounting_asset_number' => $row['label_akuntansi'] ?? null,
                    'status_id'               => $status->id,
                    'assigned_to'             => $assignedTo,
                    'spesifikasi_detail'      => $row['spesifikasi'] ?? null,
                    'notes'                   => $row['catatan'] ?? 'Data migrasi Excel',
                    'acquisition_date'        => $acqDate,
                    'purchase_price'          => $cleanPrice ?: 0,
                    'currency_id'             => $currencyId, // 🔥 Mata uang per baris
                    'supporting_document'     => $this->docPath,
                    'batch_id'                => $this->batchId,
                ]);

                $historyNotes = "Aset diregistrasi via Import Excel.\n[Batch ID: {$this->batchId}]";
                FixedAssetHistory::create([
                    'fixed_asset_id' => $asset->id,
                    'status'         => $status->name,
                    'assigned_to'    => $assignedTo,
                    'notes'          => $historyNotes,
                    'created_by'     => auth()->id() ?? 1,
                ]);

                // MUTASI STOK
                $currentStock = (float) $item->current_stock;
                $balanceAfter = $currentStock + 1;

                InventoryStock::create([
                    'company_id'       => $companyId ?? 1,
                    'warehouse_id'     => $warehouseId,
                    'item_id'          => $item->id,
                    'stock_qty'        => 1,
                    'reference_number' => $assetNumber,
                    'notes'            => "Masuk via Excel Import: " . $assetNumber,
                ]);

                StockMutation::create([
                    'item_id'          => $item->id,
                    'warehouse_id'     => $warehouseId,
                    'type'             => 'IN',
                    'qty'              => 1,
                    'balance_before'   => $currentStock,
                    'balance_after'    => $balanceAfter,
                    'reference_number' => $assetNumber,
                    'notes'            => "Penerimaan Import Excel",
                    'created_by'       => auth()->id() ?? 1,
                ]);

                $item->update(['current_stock' => $balanceAfter]);
            }
        });
    }
}
