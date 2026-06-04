<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Status;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        // Data Status untuk PR, PO, INV, OPEX, AST, dan IMPORT
        $statuses = [
            // ==========================================================
            // 1. STATUS UNTUK PURCHASE REQUEST (PR)
            // ==========================================================
            [
                'type' => 'PR',
                'name' => 'Menunggu Approval',
                'slug' => 'pending_approval',
                'color' => 'warning',
                'sequence' => 1,
            ],
            [
                'type' => 'PR',
                'name' => 'Disetujui Manager',
                'slug' => 'approved_manager',
                'color' => 'info',
                'sequence' => 2,
            ],
            [
                'type' => 'PR',
                'name' => 'Disetujui (Final)',
                'slug' => 'approved',
                'color' => 'success',
                'sequence' => 3,
            ],
            [
                'type' => 'PR',
                'name' => 'PO Parsial',
                'slug' => 'partial_po',
                'color' => 'primary',
                'sequence' => 4,
            ],
            [
                'type' => 'PR',
                'name' => 'PO Terbit',
                'slug' => 'po_issued',
                'color' => 'dark',
                'sequence' => 5,
            ],
            [
                'type' => 'PR',
                'name' => 'Ditolak',
                'slug' => 'rejected',
                'color' => 'danger',
                'sequence' => 6,
            ],
            [
                'type' => 'PR',
                'name' => 'Dibatalkan',
                'slug' => 'cancelled',
                'color' => 'secondary',
                'sequence' => 7,
            ],
            [
                'type' => 'PR',
                'name' => 'Selesai (Completed)',
                'slug' => 'completed',
                'color' => 'success',
                'sequence' => 8,
            ],

            // ==========================================================
            // 2. STATUS UNTUK PURCHASE ORDER (PO)
            // ==========================================================
            [
                'type' => 'PO',
                'name' => 'Menunggu Approval',
                'slug' => 'pending_approval',
                'color' => 'warning',
                'sequence' => 1,
            ],
            [
                'type' => 'PO',
                'name' => 'Issued (Terbit)',
                'slug' => 'issued',
                'color' => 'primary',
                'sequence' => 2,
            ],
            [
                'type' => 'PO',
                'name' => 'Diterima Sebagian (Lama)',
                'slug' => 'partial_received',
                'color' => 'info',
                'sequence' => 3,
            ],
            [
                'type' => 'PO',
                'name' => 'Selesai (Completed)',
                'slug' => 'completed',
                'color' => 'success',
                'sequence' => 4,
            ],
            [
                'type' => 'PO',
                'name' => 'Ditolak',
                'slug' => 'rejected',
                'color' => 'danger',
                'sequence' => 5,
            ],
            [
                'type' => 'PO',
                'name' => 'Dibatalkan',
                'slug' => 'cancelled',
                'color' => 'secondary',
                'sequence' => 6,
            ],
            [
                'type' => 'PO',
                'name' => 'Canceled',
                'slug' => 'canceled',
                'color' => 'secondary',
                'sequence' => 7,
            ],
            [
                'type' => 'PO',
                'name' => 'Diterima Sebagian',
                'slug' => 'partial_receipt',
                'color' => 'warning',
                'sequence' => 8,
            ],
            [
                'type' => 'PO',
                'name' => 'Diterima Penuh',
                'slug' => 'fully_received',
                'color' => 'success',
                'sequence' => 9,
            ],

            // ==========================================================
            // 3. STATUS UNTUK VENDOR INVOICE (INV)
            // ==========================================================
            [
                'type' => 'INV',
                'name' => 'Draft (Belum Sah)',
                'slug' => 'draft',
                'color' => 'secondary',
                'sequence' => 1,
            ],
            [
                'type' => 'INV',
                'name' => 'POSTED (Siap Bayar)',
                'slug' => 'posted',
                'color' => 'primary',
                'sequence' => 2,
            ],
            [
                'type' => 'INV',
                'name' => 'Dibayar Sebagian',
                'slug' => 'partial',
                'color' => 'warning',
                'sequence' => 3,
            ],
            [
                'type' => 'INV',
                'name' => 'Lunas (PAID)',
                'slug' => 'paid',
                'color' => 'success',
                'sequence' => 4,
            ],
            [
                'type' => 'INV',
                'name' => 'Dibatalkan',
                'slug' => 'canceled',
                'color' => 'danger',
                'sequence' => 5,
            ],

            // ==========================================================
            // 4. STATUS UNTUK BIAYA OPERASIONAL / OPEX (BILL)
            // ==========================================================
            [
                'type' => 'OPEX',
                'name' => 'Draft',
                'slug' => 'draft',
                'color' => 'secondary',
                'sequence' => 1,
            ],
            [
                'type' => 'OPEX',
                'name' => 'Menunggu Approval',
                'slug' => 'pending',
                'color' => 'warning',
                'sequence' => 2,
            ],
            [
                'type' => 'OPEX',
                'name' => 'Disetujui (Siap Bayar)',
                'slug' => 'approved',
                'color' => 'primary',
                'sequence' => 3,
            ],
            [
                'type' => 'OPEX',
                'name' => 'Dicicil (Partial)',
                'slug' => 'partial',
                'color' => 'info',
                'sequence' => 4,
            ],
            [
                'type' => 'OPEX',
                'name' => 'Lunas (Paid)',
                'slug' => 'paid',
                'color' => 'success',
                'sequence' => 5,
            ],
            [
                'type' => 'OPEX',
                'name' => 'Ditolak',
                'slug' => 'rejected',
                'color' => 'danger',
                'sequence' => 6,
            ],
            [
                'type' => 'OPEX',
                'name' => 'Dibatalkan',
                'slug' => 'cancelled',
                'color' => 'dark',
                'sequence' => 7,
            ],

            // ==========================================================
            // 5. STATUS UNTUK ASET TETAP (AST)
            // ==========================================================
            [
                'type' => 'AST',
                'name' => 'Available (Tersedia)',
                'slug' => 'available',
                'color' => 'success',
                'sequence' => 1,
            ],
            [
                'type' => 'AST',
                'name' => 'In Use (Dipakai)',
                'slug' => 'in_use',
                'color' => 'primary',
                'sequence' => 2,
            ],
            [
                'type' => 'AST',
                'name' => 'Maintenance (Diservis)',
                'slug' => 'maintenance',
                'color' => 'warning',
                'sequence' => 3,
            ],
            [
                'type' => 'AST',
                'name' => 'Disposed (Rusak/Dijual)',
                'slug' => 'disposed',
                'color' => 'dark',
                'sequence' => 4,
            ],
            [
                'type' => 'AST',
                'name' => 'Returned (Dikembalikan)',
                'slug' => 'returned',
                'color' => 'secondary',
                'sequence' => 5,
            ],

            // ==========================================================
            // 6. STATUS UNTUK GOOD ISSUE (GI)
            // ==========================================================
            ['type' => 'GI', 'name' => 'Aktif (Valid)', 'slug' => 'active', 'color' => 'success', 'sequence' => 1],
            ['type' => 'GI', 'name' => 'Retur Sebagian', 'slug' => 'partial_return', 'color' => 'warning', 'sequence' => 2],
            ['type' => 'GI', 'name' => 'Retur Penuh', 'slug' => 'full_return', 'color' => 'info', 'sequence' => 3],
            ['type' => 'GI', 'name' => 'VOID (Batal)', 'slug' => 'void', 'color' => 'danger', 'sequence' => 4],

            // ==========================================================
            // 🔥 7. STATUS UNTUK IMPORT MASTER ITEM (IMPORT) 🔥
            // ==========================================================
            [
                'type' => 'IMPORT',
                'name' => 'Draft (Karantina)',
                'slug' => 'draft',
                'color' => 'secondary',
                'sequence' => 1,
            ],
            [
                'type' => 'IMPORT',
                'name' => 'Menunggu Approval',
                'slug' => 'pending',
                'color' => 'warning',
                'sequence' => 2,
            ],
            [
                'type' => 'IMPORT',
                'name' => 'Disetujui (ACC)',
                'slug' => 'approved',
                'color' => 'success',
                'sequence' => 3,
            ],
            [
                'type' => 'IMPORT',
                'name' => 'Ditolak',
                'slug' => 'rejected',
                'color' => 'danger',
                'sequence' => 4,
            ],
        ];

        foreach ($statuses as $status) {
            Status::updateOrCreate(
                ['type' => $status['type'], 'slug' => $status['slug']],
                [
                    'name' => $status['name'],
                    'color' => $status['color'],
                    // Menambahkan ?? 0 agar sistem tidak error jika ada data yang lupa diberi sequence
                    'sequence' => $status['sequence'] ?? 0,
                ]
            );
        }
    }
}
