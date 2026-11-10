@extends('ui.layout')
@section('page_key', 'hr-positions')
@section('content')
<x-ui.partials.page-header title="Novo Cargo" subtitle="Criar cargo/função." badge="RH">
    <form method="POST" action="{{ route('ui.hr.positions.store') }}" class="max-w-2xl space-y-4 bg-[var(--surface)] border border-[var(--border)] rounded-2xl p-6">
        @csrf
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Nome *</label>
            <input name="name" value="{{ old('name') }}" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2.5 text-sm">
            @error('name')<p class="text-red-600 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Departamento</label>
            <select name="department_id" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2.5 text-sm">
                <option value="">—</option>
                @foreach($departments as $d)
                    <option value="{{ $d->id }}" @selected(old('department_id')==$d->id)>{{ $d->name }}</option>
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
            <a href="{{ route('ui.hr.positions.index') }}" class="px-4 py-2 text-xs font-semibold rounded-xl border border-[var(--border)]">Cancelar</a>
        </div>
    </form>
</x-ui.partials.page-header>
@endsection
