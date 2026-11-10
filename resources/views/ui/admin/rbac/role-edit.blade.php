@extends('ui.layout')
@section('page_key', 'rbac-roles')
@section('content')
<x-ui.partials.page-header title="Editar Role: {{ $role->display_name ?? $role->name }}" subtitle="Configurar permissões por área." badge="Admin">
    <form method="POST" action="{{ route('ui.admin.rbac.roles.update', $role) }}" class="space-y-6">
        @csrf
        @method('PATCH')
        <div class="bg-[var(--surface)] border border-[var(--border)] rounded-2xl p-6 grid grid-cols-1 md:grid-cols-2 gap-4 max-w-4xl">
            <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Nome de apresentação *</label><input name="display_name" value="{{ old('display_name', $role->display_name) }}" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2.5 text-sm"></div>
            <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Área</label><input name="area" value="{{ old('area', $role->area) }}" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2.5 text-sm"></div>
            <div class="md:col-span-2"><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Descrição</label><textarea name="description" rows="2" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2.5 text-sm">{{ old('description', $role->description) }}</textarea></div>
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" {{ old('active', $role->active) ? 'checked' : '' }}> Ativa</label>
        </div>

        <div class="bg-[var(--surface)] border border-[var(--border)] rounded-2xl p-6">
            <h3 class="font-bold text-base mb-4">Permissões</h3>
            @php $rolePerms = $role->permissions->pluck('id')->all(); @endphp
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                @foreach($permissions as $module => $perms)
                    <div class="border border-[var(--border)] rounded-xl p-4">
                        <h4 class="font-bold text-sm uppercase tracking-wider text-[var(--text-soft)] mb-3">{{ $module }}</h4>
                        <div class="space-y-2">
                            @foreach($perms as $perm)
                                <label class="flex items-start gap-2 text-sm">
                                    <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" {{ in_array($perm->id, old('permissions', $rolePerms)) ? 'checked' : '' }} class="mt-1">
                                    <span><span class="font-medium">{{ $perm->name }}</span><br><span class="text-xs text-[var(--text-soft)]">{{ $perm->slug }}</span></span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="ui-button ui-button--primary px-4 py-2 text-xs font-bold rounded-xl">Guardar</button>
            <a href="{{ route('ui.admin.rbac.roles') }}" class="px-4 py-2 text-xs font-semibold rounded-xl border border-[var(--border)]">Voltar</a>
        </div>
    </form>
</x-ui.partials.page-header>
@endsection
