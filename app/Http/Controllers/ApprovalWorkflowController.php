<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ApprovalWorkflow;
use App\Models\Department;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ApprovalWorkflowController extends Controller
{
    public function index()
    {
        // Panggil juga relasi department agar bisa ditampilkan di tabel
        $workflows = ApprovalWorkflow::withCount('steps')->with('department')->get();
        return view('workflows.index', compact('workflows'));
    }

    public function create()
    {
        $roles = Role::where('name', '!=', 'Super Admin')->orderBy('name')->get();
        $departments = Department::orderBy('name')->get();
        $supportedModels = \App\Models\DocumentType::where('is_active', true)->pluck('name', 'model_class');

        return view('workflows.create', compact('roles', 'departments', 'supportedModels'));
    }

    public function store(Request $request)
    {
        $deptId = $request->department_id ?: null;

        $request->validate([
            // Validasi Kombinasi: Tidak boleh ada jenis dokumen & departemen yang persis sama
            'document_type' => [
                'required', 'string',
                Rule::unique('approval_workflows')->where(function ($query) use ($deptId) {
                    return $query->where('department_id', $deptId);
                })
            ],
            'department_id'   => 'nullable|exists:departments,id',
            'name'            => 'required|string|max:255',
            'steps'           => 'nullable|array',
            'steps.*.role_id' => 'required|exists:roles,id'
        ]);

        try {
            DB::transaction(function() use ($request, $deptId) {
                $workflow = ApprovalWorkflow::create([
                    'document_type' => $request->document_type,
                    'department_id' => $deptId,
                    'name'          => $request->name,
                    'is_active'     => true
                ]);

                if($request->has('steps')) {
                    $order = 1;
                    foreach($request->steps as $step) {
                        $targetDept = $step['target_department_id'] ?? null;
                        if ($targetDept === 'all') $targetDept = 0;
                        elseif ($targetDept === '') $targetDept = null;

                        $workflow->steps()->create([
                            'step_order'           => $order,
                            'role_id'              => $step['role_id'],
                            'target_department_id' => $targetDept,
                            'min_amount'           => $step['min_amount'] ?? 0
                        ]);
                        $order++;
                    }
                }
            });

            return redirect()->route('workflows.index')->with('success', "Matriks Persetujuan Baru berhasil dibuat!");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Gagal membuat matriks: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $workflow = ApprovalWorkflow::with(['steps' => function($q) {
            $q->orderBy('step_order', 'asc');
        }])->findOrFail($id);

        $roles = Role::where('name', '!=', 'Super Admin')->orderBy('name')->get();
        $departments = Department::orderBy('name')->get();
        $supportedModels = \App\Models\DocumentType::where('is_active', true)->pluck('name', 'model_class');

        return view('workflows.edit', compact('workflow', 'roles', 'departments', 'supportedModels'));
    }

    public function update(Request $request, $id)
    {
        $workflow = ApprovalWorkflow::findOrFail($id);
        $deptId = $request->department_id ?: null;

        $request->validate([
            // Validasi kombinasi unik, abaikan ID matriks ini sendiri
            'document_type' => [
                'required', 'string',
                Rule::unique('approval_workflows')->where(function ($query) use ($deptId) {
                    return $query->where('department_id', $deptId);
                })->ignore($workflow->id)
            ],
            'department_id'   => 'nullable|exists:departments,id',
            'name'            => 'required|string|max:255',
            'steps'           => 'nullable|array',
            'steps.*.role_id' => 'required|exists:roles,id'
        ]);

        try {
            DB::transaction(function() use ($request, $workflow, $deptId) {
                $workflow->update([
                    'document_type' => $request->document_type,
                    'department_id' => $deptId,
                    'name'          => $request->name
                ]);

                $workflow->steps()->delete();

                if($request->has('steps')) {
                    $order = 1;
                    foreach($request->steps as $step) {
                        $targetDept = $step['target_department_id'] ?? null;
                        if ($targetDept === 'all') $targetDept = 0;
                        elseif ($targetDept === '') $targetDept = null;

                        $workflow->steps()->create([
                            'step_order'           => $order,
                            'role_id'              => $step['role_id'],
                            'target_department_id' => $targetDept,
                            'min_amount'           => $step['min_amount'] ?? 0
                        ]);
                        $order++;
                    }
                }
            });

            return redirect()->route('workflows.index')->with('success', "Matriks Persetujuan untuk {$request->name} berhasil diperbarui!");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memperbarui matriks: ' . $e->getMessage());
        }
    }
}
