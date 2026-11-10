@extends('ui.layout')
@section('page_key', 'hr-departments')
@section('content')
<x-ui.partials.page-header title="Novo Departamento" subtitle="Criar departamento." badge="RH">
    <form method="POST" action="{{ route('ui.hr.departments.store') }}" class="max-w-2xl space-y-4 bg-[var(--surface)] border border-[var(--border)] rounded-2xl p-6">
        @csrf
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Nome *</label>
            <input name="name" value="{{ old('name') }}" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2.5 text-sm">
            @error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Código</label>
            <input name="code" value="{{ old('code') }}" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2.5 text-sm">
            @error('code')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Gestor</label>
            <select name="manager_id" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2.5 text-sm">
                <option value="">—</option>
                @foreach($managers as $m)
                    <option value="{{ $m->id }}" @selected(old('manager_id')==$m->id)>{{ $m->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Descrição</label>
            <textarea name="description" rows="3" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2.5 text-sm">{{ old('description') }}</textarea>
        </div>
        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" {{ old('active', true) ? 'checked' : '' }}> Ativo</label>
        <div class="flex gap-3 pt-2">
            <button type="submit" class="ui-button ui-button--primary px-4 py-2 text-xs font-bold rounded-xl">Guardar</button>
            <a href="{{ route('ui.hr.departments.index') }}" class="px-4 py-2 text-xs font-semibold rounded-xl border border-[var(--border)]">Cancelar</a>
        </div>
    </form>
</x-ui.partials.page-header>
@endsection
