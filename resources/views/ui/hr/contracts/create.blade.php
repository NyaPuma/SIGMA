@extends('ui.layout')
@section('page_key', 'hr-employees')
@section('content')
<x-ui.partials.page-header title="Novo Contrato" subtitle="{{ $employee->full_name }}" badge="RH">
    <form method="POST" action="{{ route('ui.hr.employees.contracts.store', $employee) }}" class="max-w-2xl space-y-4 bg-[var(--surface)] border border-[var(--border)] rounded-2xl p-6">
        @csrf
        <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Tipo *</label><select name="type" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2.5 text-sm">@foreach(['permanent'=>'Sem termo','fixed_term'=>'Termo certo','temporary'=>'Temporário','intern'=>'Estágio','contractor'=>'Prestador'] as $k=>$v)<option value="{{ $k }}" @selected(old('type')==$k)>{{ $v }}</option>@endforeach</select></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Início *</label><input type="date" name="start_date" value="{{ old('start_date') }}" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2.5 text-sm"></div>
            <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Fim</label><input type="date" name="end_date" value="{{ old('end_date') }}" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2.5 text-sm"></div>
        </div>
        <div class="grid grid-cols-3 gap-4">
            <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Salário</label><input type="number" step="0.01" min="0" name="salary_amount" value="{{ old('salary_amount') }}" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2.5 text-sm"></div>
            <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Moeda</label><input name="salary_currency" value="{{ old('salary_currency','EUR') }}" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2.5 text-sm"></div>
            <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Horas/sem</label><input type="number" step="0.5" min="0" name="work_hours_per_week" value="{{ old('work_hours_per_week') }}" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2.5 text-sm"></div>
        </div>
        <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Notas</label><textarea name="notes" rows="3" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2.5 text-sm">{{ old('notes') }}</textarea></div>
        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" {{ old('active', true) ? 'checked' : '' }}> Ativo</label>
        <div class="flex gap-3 pt-2">
            <button type="submit" class="ui-button ui-button--primary px-4 py-2 text-xs font-bold rounded-xl">Guardar</button>
            <a href="{{ route('ui.hr.employees.contracts.index', $employee) }}" class="px-4 py-2 text-xs font-semibold rounded-xl border border-[var(--border)]">Cancelar</a>
        </div>
    </form>
</x-ui.partials.page-header>
@endsection
