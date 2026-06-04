<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\Warehouse;
use App\Models\InventoryStock;
use App\Models\StockMutation;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str; // 🔥 Tambahkan ini untuk generator text random

class InventoryImport implements ToCollection, WithHeadingRow
{


    protected $attachment;

    // 🔥 Tangkap path lampiran lewat Constructor
    public function __construct($attachment = null) {
        $this->attachment = $attachment;
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                if (empty($row['kode_barang'])) continue;

                $item = Item::where('code', trim($row['kode_barang']))->first();
                $warehouse = Warehouse::where('name', 'like', '%' . trim($row['nama_gudang']) . '%')->first();

                if ($item && $warehouse) {
                    $qty = (float) ($row['jumlah_qty'] ?? 0);
                    $price = (float) ($row['harga_satuan_angka'] ?? 0);

                    // 🔥 PERBAIKAN 1: Tarik nilai Mata Uang dari Excel (Biar tidak undefined error)
                    $currency = strtoupper(trim($row['mata_uang'] ?? 'IDR'));

                    // 🔥 PERBAIKAN 2: Bikin Reference Number unik per baris (mencegah duplikat di loop yang cepat)
                    $refNumber = 'SA-' . date('YmdHis') . '-' . strtoupper(Str::random(4));

                    // 1. Update Saldo di Item Master (Global)
                    $balanceBefore = (float) $item->current_stock;
                    $balanceAfter = $balanceBefore + $qty;
                    $item->update(['current_stock' => $balanceAfter]);

                    // 2. 🔥 PERBAIKAN 3: Cek Stok Gudang Dulu (Jangan main langsung create)
                    $invStock = InventoryStock::where('item_id', $item->id)
                                              ->where('warehouse_id', $warehouse->id)
                                              ->first();

                    if ($invStock) {
                        // Kalau barang sudah ada di gudang, tambahkan saja qty-nya
                        $invStock->increment('stock_qty', $qty);
                    } else {
                        // Kalau belum ada, baru kita buat slot baru
                        InventoryStock::create([
                            'company_id'       => 1, // Default ke Kantor Pusat
                            'warehouse_id'     => $warehouse->id,
                            'item_id'          => $item->id,
                            'stock_qty'        => $qty,
                            'reference_number' => $refNumber,
                            'notes'            => "Saldo Awal: " . ($row['catatan'] ?? 'Import dari Excel'),
                        ]);
                    }

                    // 3. Catat Kartu Mutasi
                    StockMutation::create([
                        'item_id'          => $item->id,
                        'warehouse_id'     => $warehouse->id,
                        'type'             => 'IN',
                        'qty'              => $qty,
                        'price'            => $price,      // Simpan Harga
                        'attachment_path' => $this->attachment, // 🔥 CATAT LAMPIRAN DI SINI
                        'currency'         => $currency,   // 🔥 Sekarang $currency sudah aman
                        'balance_before'   => $balanceBefore,
                        'balance_after'    => $balanceAfter,
                        'reference_number' => $refNumber,
                        'notes'            => "Saldo Awal - " . ($row['catatan'] ?? 'Migrasi Data'),
                        'created_by'       => auth()->id() ?? 1,
                    ]);
                }
            }
        });
    }
}
