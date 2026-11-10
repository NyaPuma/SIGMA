@extends('ui.layout')
@section('page_key', 'hr-attendance')
@section('content')
<x-ui.partials.page-header title="Assiduidade" subtitle="Registo de presenças, faltas e horários." badge="RH">
    @if(session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 ui-listing-table w-full overflow-hidden bg-[var(--surface)] border border-[var(--border)] rounded-2xl shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[var(--border)] text-left text-xs">
                    <thead class="bg-[var(--surface-2)] text-[var(--text-soft)] uppercase tracking-wider font-bold text-xs">
                        <tr>
                            <th class="px-6 py-4">Data</th>
                            <th class="px-6 py-4">Colaborador</th>
                            <th class="px-6 py-4">Estado</th>
                            <th class="px-6 py-4">Entrada</th>
                            <th class="px-6 py-4">Saída</th>
                            <th class="px-6 py-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--border)]">
                        @forelse($records as $r)
                        <tr>
                            <td class="px-6 py-4">{{ optional($r->date)->format('Y-m-d') }}</td>
                            <td class="px-6 py-4">{{ $r->employee?->full_name ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $r->status }}</td>
                            <td class="px-6 py-4">{{ $r->check_in ? $r->check_in->format('H:i') : '-' }}</td>
                            <td class="px-6 py-4">{{ $r->check_out ? $r->check_out->format('H:i') : '-' }}</td>
                            <td class="px-6 py-4 text-right">
                                <form action="{{ route('ui.hr.attendance.destroy', $r) }}" method="POST" class="inline" onsubmit="return confirm('Remover registo?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 font-semibold">Remover</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-[var(--text-soft)]">Sem registos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="bg-[var(--surface)] border border-[var(--border)] rounded-2xl p-6">
            <h3 class="font-bold text-base mb-4">Registar / Atualizar</h3>
            <form method="POST" action="{{ route('ui.hr.attendance.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Colaborador *</label>
                    <select name="employee_id" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm">
                        <option value="">Selecionar...</option>
                        @foreach($employees as $e)
                            <option value="{{ $e->id }}">{{ $e->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Data *</label><input type="date" name="date" value="{{ date('Y-m-d') }}" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"></div>
                <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Estado *</label>
                    <select name="status" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm">
                        <option value="present">Presente</option>
                        <option value="absent">Ausente</option>
                        <option value="late">Atraso</option>
                        <option value="half_day">Meio dia</option>
                        <option value="remote">Remoto</option>
                        <option value="holiday">Feriado</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Entrada</label><input type="datetime-local" name="check_in" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"></div>
                    <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Saída</label><input type="datetime-local" name="check_out" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"></div>
                </div>
                <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Pausa (min)</label><input type="number" name="break_minutes" value="0" min="0" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"></div>
                <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Notas</label><textarea name="notes" rows="2" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"></textarea></div>
                <button type="submit" class="ui-button ui-button--primary px-4 py-2 text-xs font-bold rounded-xl w-full">Guardar</button>
            </form>
        </div>
    </div>
</x-ui.partials.page-header>
@endsection
