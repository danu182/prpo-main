<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ApprovalWorkflow;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class ApprovalWorkflowController extends Controller
{
    // Tampilkan daftar dokumen yang butuh Approval
    public function index()
    {
        $workflows = ApprovalWorkflow::withCount('steps')->get();
        return view('workflows.index', compact('workflows'));
    }


    // Tampilkan halaman Tambah Matriks Baru
    public function create()
    {
        $roles = \Spatie\Permission\Models\Role::where('name', '!=', 'Super Admin')->orderBy('name')->get();

        // 🔥 BACA DARI DATABASE YANG BARU SAJA DIPATENKAN
        $supportedModels = \App\Models\DocumentType::where('is_active', true)
                            ->pluck('name', 'model_class');

        return view('workflows.create', compact('roles', 'supportedModels'));
    }

    // Simpan Matriks Baru ke Database
    public function store(Request $request)
    {
        $request->validate([
            'document_type'   => 'required|string|unique:approval_workflows,document_type',
            'name'            => 'required|string|max:255',
            'steps'           => 'nullable|array',
            'steps.*.role_id' => 'required|exists:roles,id'
        ]);

        try {
            DB::transaction(function() use ($request) {
                // 1. Buat Induk Matriks
                $workflow = ApprovalWorkflow::create([
                    'document_type' => $request->document_type,
                    'name'          => $request->name,
                    'is_active'     => true
                ]);

                // 2. Susun formasi jika ada
                if($request->has('steps')) {
                    $order = 1;
                    foreach($request->steps as $step) {
                        $workflow->steps()->create([
                            'step_order' => $order,
                            'role_id'    => $step['role_id']
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


    // Tampilkan halaman Edit formasi (Matriks)
    public function edit($id)
    {
        $workflow = ApprovalWorkflow::with(['steps' => function($q) {
            $q->orderBy('step_order', 'asc');
        }])->findOrFail($id);

        $roles = Role::where('name', '!=', 'Super Admin')->orderBy('name')->get();

        // 🔥 1. TAMBAHKAN BARIS INI UNTUK MENGAMBIL DATA DOKUMEN
        $supportedModels = \App\Models\DocumentType::where('is_active', true)
                            ->pluck('name', 'model_class');

        // 🔥 2. PASTIKAN 'supportedModels' IKUT MASUK KE DALAM COMPACT
        return view('workflows.edit', compact('workflow', 'roles', 'supportedModels'));
    }

    public function update(Request $request, $id)
    {
        $workflow = ApprovalWorkflow::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255', // 🔥 VALIDASI NAMA BARU
            'steps' => 'nullable|array',
            'steps.*.role_id' => 'required|exists:roles,id'
        ]);

        try {
            DB::transaction(function() use ($request, $workflow) {
                // 🔥 1. UPDATE NAMA MATRIKSNYA
                $workflow->update([
                    'name' => $request->name
                ]);

                // 2. Hapus semua formasi lama
                $workflow->steps()->delete();

                // 3. Susun ulang formasi baru berdasarkan urutan
                if($request->has('steps')) {
                    $order = 1;
                    foreach($request->steps as $step) {
                        $workflow->steps()->create([
                            'step_order' => $order,
                            'role_id'    => $step['role_id']
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
