<?php

namespace App\Services;

use App\Models\ApprovalWorkflow;
use App\Models\DocumentApproval;

class ApprovalService
{
    /**
     * Generate atau Reset antrean persetujuan (Bisa dipakai di Store & Update)
     *
     * @param object $document (Model dokumen seperti BillRequest, PurchaseRequest, dll)
     * @return bool (True = Butuh Approval, False = Auto Approve)
     */
    public static function generateWorkflow($document)
    {
        $documentTypeClass = get_class($document);

        // Pastikan data relasi user/pembuat dokumen dipanggil
        $document->loadMissing('user');
        $userDeptId = $document->user->department_id ?? null;
        $documentAmount = $document->amount ?? 0;

        // 1. BERSIHKAN ANTREAN LAMA (Sangat berguna saat fungsi Edit/Update dipanggil)
        DocumentApproval::where('document_id', $document->id)
            ->where('document_type', $documentTypeClass)
            ->delete();

        // 2. CARI MATRIKS SPESIFIK (Berdasarkan Departemen Pembuat Dokumen)
        $workflow = ApprovalWorkflow::with('steps')
            ->where('document_type', $documentTypeClass)
            ->where('department_id', $userDeptId)
            ->where('is_active', true)
            ->first();

        // 3. JIKA SPESIFIK TIDAK ADA, GUNAKAN MATRIKS UMUM (DEFAULT)
        if (!$workflow) {
            $workflow = ApprovalWorkflow::with('steps')
                ->where('document_type', $documentTypeClass)
                ->whereNull('department_id')
                ->where('is_active', true)
                ->first();
        }

        // 4. GENERATE ANTREAN PERSETUJUAN BARU
        if ($workflow && $workflow->steps->count() > 0) {
            $hasValidStep = false;

            foreach ($workflow->steps as $step) {
                // Cek Minimal Nominal (Jika di bawah batas, lewati atasan ini)
                if ($step->min_amount > 0 && $documentAmount < $step->min_amount) {
                    continue;
                }

                DocumentApproval::create([
                    'document_id'          => $document->id,
                    'document_type'        => $documentTypeClass,
                    'step_order'           => $step->step_order,
                    'role_id'              => $step->role_id,
                    'target_department_id' => $step->target_department_id,
                    'status'               => 'PENDING'
                ]);

                $hasValidStep = true;
            }

            return $hasValidStep; // Mengembalikan TRUE (Dokumen masuk mode PENDING)
        }

        return false; // Mengembalikan FALSE (Dokumen bebas melenggang jadi APPROVED)
    }
}
