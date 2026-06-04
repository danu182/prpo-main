<div class="mb-3">
    <label class="form-label fw-bold small">Jabatan (Role) Sistem</label>
    <select name="role" class="form-select">
        <option value="">-- Pilih Jabatan --</option>
        @foreach(\Spatie\Permission\Models\Role::all() as $r)
            <option value="{{ $r->name }}" {{ $user->hasRole($r->name) ? 'selected' : '' }}>
                {{ $r->name }}
            </option>
        @endforeach
    </select>
</div>
