<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentType;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            [
                'name'        => 'Purchase Request (PR)',
                'model_class' => 'App\Models\PurchaseRequest',
                'is_active'   => true,
            ],
            [
                'name'        => 'Purchase Order (PO)',
                'model_class' => 'App\Models\PurchaseOrder',
                'is_active'   => true,
            ],
            [
                'name'        => 'Bills Opex',
                'model_class' => 'App\Models\BillRequest',
                'is_active'   => true,
            ],
            [
                'name'        => 'Import Master Item', // 🔥 PERBAIKAN: Nama diubah agar sesuai modelnya
                'model_class' => 'App\Models\ItemImportBatch',
                'is_active'   => true,
            ],
            // =======================================================
            // 🔥 TAMBAHAN BARU: UNTUK KARANTINA ASET TETAP 🔥
            // =======================================================
            [
                'name'        => 'Import Fixed Asset (Aset Tetap)',
                'model_class' => 'App\Models\FixedAssetImportBatch',
                'is_active'   => true,
            ],
            // =======================================================
            // 🔥 TAMBAHAN BARU: persetujaun Stok Opname 🔥
            // =======================================================
            [
                'name'        => 'persetujaun Stok Opname',
                'model_class' => 'App\Models\StockOpname',
                'is_active'   => true,
            ],
        ];

        foreach ($documents as $doc) {
            DocumentType::updateOrCreate(
                ['model_class' => $doc['model_class']], // Jangan sampai duplikat
                ['name' => $doc['name'], 'is_active' => $doc['is_active']]
            );
        }

        $this->command->info('✅ Master Jenis Dokumen berhasil dipatenkan!');
    }
}
