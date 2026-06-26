<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use Spatie\Permission\Models\Role;

class ApprovalWorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ===================================================================
        // 1. PASTIKAN ROLE SUDAH ADA DI DATABASE (Gunakan Role Spatie)
        // ===================================================================
        // Jika belum ada, sistem akan otomatis membuatnya (firstOrCreate)
        $roleSupervisor = Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        $roleManager    = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $roleDirektur   = Role::firstOrCreate(['name' => 'direktur', 'guard_name' => 'web']);


        // ===================================================================
        // 2. MATRIKS UNTUK PURCHASE REQUEST (PR) -> 3 LAPIS
        // ===================================================================
        $workflowPR = ApprovalWorkflow::updateOrCreate(
            ['document_type' => 'App\Models\PurchaseRequest'], // Model yang diikat
            [
                'name'      => 'Matriks Persetujuan PR (1 Lapis)',
                'is_active' => true
            ]
        );

        // Bersihkan step lama (jika di-seed ulang agar tidak dobel)
        $workflowPR->steps()->delete();

        // Lapis 1: Supervisor
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflowPR->id,
            'step_order'           => 1,
            'role_id'              => $roleSupervisor->id,
        ]);



        // ===================================================================
        // 3. MATRIKS UNTUK PURCHASE ORDER (PO) -> 1 LAPIS SAJA (Misalnya)
        // ===================================================================
        $workflowPO = ApprovalWorkflow::updateOrCreate(
            ['document_type' => 'App\Models\PurchaseOrder'], // Model yang diikat
            [
                'name'      => 'Matriks Persetujuan PO (2 Lapis)',
                'is_active' => true
            ]
        );

        $workflowPO->steps()->delete();

        // Lapis 1 menager
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflowPO->id,
            'step_order'           => 1,
            'role_id'              => $roleManager->id,
        ]);

        // Lapis 3: Direktur final
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflowPO->id,
            'step_order'           => 2,
            'role_id'              => $roleDirektur->id,
        ]);


        // ===================================================================
        // 4. MATRIKS UNTUK Bills Opex -> 2 LAPIS SAJA (Misalnya)
        // ===================================================================
        $workflowPO = ApprovalWorkflow::updateOrCreate(
            ['document_type' => 'App\Models\BillRequest'], // Model yang diikat
            [
                'name'      => 'Matriks Persetujuan Bills Opex (2 Lapis)',
                'is_active' => true
            ]
        );

        $workflowPO->steps()->delete();

        // Lapis 1 menager
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflowPO->id,
            'step_order'           => 1,
            'role_id'              => $roleManager->id,
        ]);

        // Lapis 3: Direktur final
        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $workflowPO->id,
            'step_order'           => 2,
            'role_id'              => $roleDirektur->id,
        ]);


        $this->command->info('✅ Mesin Matriks Persetujuan (Workflow) berhasil di-deploy!');
    }
}
