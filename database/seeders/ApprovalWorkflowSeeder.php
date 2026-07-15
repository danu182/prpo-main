<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\Department;
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
        // 2. AMBIL DATA DEPARTEMEN UNTUK ROUTING SPESIFIK (HYBRID)
        // ===================================================================
        $deptIT = Department::where('code', 'IT')->first();
        $deptHR = Department::where('code', 'HR')->first();
        $deptFA = Department::where('code', 'FA')->first();

        // ===================================================================
        // 3. MATRIKS PURCHASE REQUEST (PR) -> UMUM (Default)
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
            'role_id'              => $roleSupervisor->id,
            'min_amount'           => 0
        ]);
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflowPR->id,
            'step_order'           => 2,
            'role_id'              => $roleManager->id,
            'min_amount'           => 5000000 // Manager hanya ACC jika > 5 Juta
        ]);

        // ===================================================================
        // 4. MATRIKS PURCHASE ORDER (PO) -> UMUM (Default)
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

        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflowPO->id,
            'step_order'           => 1,
            'role_id'              => $roleManager->id,
            'min_amount'           => 0
        ]);
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflowPO->id,
            'step_order'           => 2,
            'role_id'              => $roleDirektur->id,
            'min_amount'           => 10000000 // Direktur turun tangan jika > 10 Juta
        ]);

        // ===================================================================
        // 5. MATRIKS BILLS OPEX -> 🔥 SPESIFIK UNTUK DEPARTEMEN IT 🔥
        // ===================================================================
        if ($deptIT && $deptHR && $deptFA) {
            $workflowOpexIT = ApprovalWorkflow::updateOrCreate(
                [
                    'document_type' => 'App\Models\BillRequest',
                    'department_id' => $deptIT->id // HANYA BERLAKU JIKA PEMBUAT DARI IT
                ],
                [
                    'name'      => 'Matriks OPEX (Khusus IT)',
                    'is_active' => true
                ]
            );
            $workflowOpexIT->steps()->delete();

            // Lapis 1: Dilempar ke Manager HR&GA
            ApprovalWorkflowStep::create([
                'approval_workflow_id' => $workflowOpexIT->id,
                'step_order'           => 1,
                'role_id'              => $roleManager->id,
                'target_department_id' => $deptHR->id, // Lintas Departemen
                'min_amount'           => 0
            ]);

            // Lapis 2: Dilempar ke Manager Finance
            ApprovalWorkflowStep::create([
                'approval_workflow_id' => $workflowOpexIT->id,
                'step_order'           => 2,
                'role_id'              => $roleManager->id,
                'target_department_id' => $deptFA->id, // Lintas Departemen
                'min_amount'           => 0
            ]);
        }

        // ===================================================================
        // 6. MATRIKS BILLS OPEX -> UMUM (Untuk selain IT)
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

        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflowOpexUmum->id,
            'step_order'           => 1,
            'role_id'              => $roleManager->id,
            'target_department_id' => null, // Atasan satu departemen
            'min_amount'           => 0
        ]);

        // ===================================================================
        // 7. MATRIKS IMPORT MASTER ITEM (Katalog Barang)
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
        // 8. MATRIKS IMPORT FIXED ASSET (Buku Aset)
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

        $this->command->info('✅ Mesin Matriks Persetujuan (Workflow) Hybrid berhasil di-deploy!');
    }
}
