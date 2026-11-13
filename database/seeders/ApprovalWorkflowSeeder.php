<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\Department;
use App\Models\User;
use Spatie\Permission\Models\Role;

class ApprovalWorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ===================================================================
        // 1. PASTIKAN ROLE SUDAH ADA DI DATABASE
        // ===================================================================
        $roleSupervisor = Role::firstOrCreate(['name' => 'Supervisor', 'guard_name' => 'web']);
        $roleManager    = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $roleDirektur   = Role::firstOrCreate(['name' => 'Direktur', 'guard_name' => 'web']);

        // ===================================================================
        // 2. CARI USER SPESIFIK BERDASARKAN NAMA
        // ===================================================================
        $danu = User::where('name', 'Danu Marwanto')->first();
        $resliana = User::where('name', 'Resliana')->first();

        // ===================================================================
        // 3. MATRIKS PURCHASE REQUEST (PR) -> BERLAKU SEMUA DEVISI (DANU)
        // ===================================================================
        $workflowPR = ApprovalWorkflow::updateOrCreate(
            [
                'document_type' => 'App\Models\PurchaseRequest',
                'department_id' => null // Berlaku untuk semua departemen
            ],
            [
                'name'      => 'Matriks Persetujuan PR (Umum)',
                'is_active' => true
            ]
        );
        $workflowPR->steps()->delete();

        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflowPR->id,
            'step_order'           => 1,
            'role_id'              => $roleManager->id, // Formalitas Role
            'user_id'              => $danu ? $danu->id : null, // 🔥 DIPERBAIKI: Menggunakan user_id
            'min_amount'           => 0
        ]);

        // ===================================================================
        // 4. MATRIKS PURCHASE ORDER (PO) -> BERLAKU SEMUA DEVISI (DANU -> RESLIANA)
        // ===================================================================
        $workflowPO = ApprovalWorkflow::updateOrCreate(
            [
                'document_type' => 'App\Models\PurchaseOrder',
                'department_id' => null
            ],
            [
                'name'      => 'Matriks Persetujuan PO (Umum)',
                'is_active' => true
            ]
        );
        $workflowPO->steps()->delete();

        // Lapis 1: Danu Marwanto
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflowPO->id,
            'step_order'           => 1,
            'role_id'              => $roleManager->id,
            'user_id'              => $danu ? $danu->id : null, // 🔥 DIPERBAIKI
            'min_amount'           => 0
        ]);

        // Lapis 2: Resliana
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflowPO->id,
            'step_order'           => 2,
            'role_id'              => $roleManager->id,
            'user_id'              => $resliana ? $resliana->id : null, // 🔥 DIPERBAIKI
            'min_amount'           => 0
        ]);

        // ===================================================================
        // 5. MATRIKS BILLS OPEX -> BERLAKU SEMUA DEVISI (DANU -> RESLIANA)
        // ===================================================================
        $workflowOpexUmum = ApprovalWorkflow::updateOrCreate(
            [
                'document_type' => 'App\Models\BillRequest',
                'department_id' => null
            ],
            [
                'name'      => 'Matriks OPEX (Umum)',
                'is_active' => true
            ]
        );
        $workflowOpexUmum->steps()->delete();

        // Lapis 1: Danu Marwanto
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflowOpexUmum->id,
            'step_order'           => 1,
            'role_id'              => $roleManager->id,
            'user_id'              => $danu ? $danu->id : null, // 🔥 DIPERBAIKI
            'min_amount'           => 0
        ]);

        // Lapis 2: Resliana
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflowOpexUmum->id,
            'step_order'           => 2,
            'role_id'              => $roleManager->id,
            'user_id'              => $resliana ? $resliana->id : null, // 🔥 DIPERBAIKI
            'min_amount'           => 0
        ]);

        // ===================================================================
        // 6. MATRIKS IMPORT MASTER ITEM (Katalog Barang)
        // ===================================================================
        $workflowItemImport = ApprovalWorkflow::updateOrCreate(
            ['document_type' => 'App\Models\ItemImportBatch', 'department_id' => null],
            ['name' => 'Persetujuan Master Item', 'is_active' => true]
        );
        $workflowItemImport->steps()->delete();

        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflowItemImport->id,
            'step_order'           => 1,
            'role_id'              => $roleManager->id,
            'min_amount'           => 0
        ]);

        // ===================================================================
        // 7. MATRIKS IMPORT FIXED ASSET (Buku Aset)
        // ===================================================================
        $workflowAssetImport = ApprovalWorkflow::updateOrCreate(
            ['document_type' => 'App\Models\FixedAssetImportBatch', 'department_id' => null],
            ['name' => 'Persetujuan Import Aset', 'is_active' => true]
        );
        $workflowAssetImport->steps()->delete();

        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflowAssetImport->id,
            'step_order'           => 1,
            'role_id'              => $roleManager->id,
            'min_amount'           => 0
        ]);

        // ===================================================================
        // 🔥 8. MATRIKS STOCK OPNAME (Penyesuaian Saldo Fisik Gudang) 🔥
        // ===================================================================
        $workflowOpname = ApprovalWorkflow::updateOrCreate(
            [
                'document_type' => 'App\Models\StockOpname',
                'department_id' => null
            ],
            [
                'name'      => 'Matriks Persetujuan Stock Opname',
                'is_active' => true
            ]
        );
        $workflowOpname->steps()->delete();

        // Lapis 1: SPV Gudang wajib periksa (Berapapun nominal selisihnya)
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflowOpname->id,
            'step_order'           => 1,
            'role_id'              => $roleSupervisor->id,
            'min_amount'           => 0
        ]);

        // Lapis 2: Manager baru akan dimintai approval jika selisihnya di atas Rp 5.000.000
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflowOpname->id,
            'step_order'           => 2,
            'role_id'              => $roleManager->id,
            'min_amount'           => 5000000
        ]);

        $this->command->info('✅ Mesin Matriks Persetujuan (Workflow) dengan Spesifik User berhasil di-deploy!');
    }
}
