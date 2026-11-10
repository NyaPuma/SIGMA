@extends('ui.layout')
@section('page_key', 'hr-employees')
@section('content')
<x-ui.partials.page-header title="Horários/Escalas — {{ $employee->full_name }}" subtitle="Gestão de horários semanais do colaborador." badge="RH">
    <x-slot:actions>
        <a href="{{ route('ui.hr.employees.show', $employee) }}" class="px-4 py-2 text-xs font-semibold rounded-xl border border-[var(--border)]">Voltar à ficha</a>
    </x-slot:actions>
    @if(session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @php $days = [0=>'Domingo',1=>'Segunda',2=>'Terça',3=>'Quarta',4=>'Quinta',5=>'Sexta',6=>'Sábado']; @endphp
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 ui-listing-table w-full overflow-hidden bg-[var(--surface)] border border-[var(--border)] rounded-2xl shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[var(--border)] text-left text-xs">
                    <thead class="bg-[var(--surface-2)] text-[var(--text-soft)] uppercase tracking-wider font-bold text-xs">
                        <tr><th class="px-6 py-4">Nome</th><th class="px-6 py-4">Dia</th><th class="px-6 py-4">Início</th><th class="px-6 py-4">Fim</th><th class="px-6 py-4">Pausa</th><th class="px-6 py-4">Ativo</th><th class="px-6 py-4 text-right">Ações</th></tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--border)]">
                        @forelse($employee->schedules as $s)
                        <tr>
                            <td class="px-6 py-4">{{ $s->name ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $days[$s->day_of_week] ?? $s->day_of_week }}</td>
                            <td class="px-6 py-4">{{ \Illuminate\Support\Str::of($s->start_time)->substr(0,5) }}</td>
                            <td class="px-6 py-4">{{ \Illuminate\Support\Str::of($s->end_time)->substr(0,5) }}</td>
                            <td class="px-6 py-4">{{ $s->break_minutes }} min</td>
                            <td class="px-6 py-4">{{ $s->active ? 'Sim' : 'Não' }}</td>
                            <td class="px-6 py-4 text-right">
                                <form action="{{ route('ui.hr.employees.schedules.destroy', [$employee, $s]) }}" method="POST" class="inline" onsubmit="return confirm('Remover horário?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 font-semibold">Remover</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="px-6 py-8 text-center text-[var(--text-soft)]">Sem horários definidos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="bg-[var(--surface)] border border-[var(--border)] rounded-2xl p-6">
            <h3 class="font-bold text-base mb-4">Novo horário</h3>
            <form method="POST" action="{{ route('ui.hr.employees.schedules.store', $employee) }}" class="space-y-3">
                @csrf
                <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Nome</label><input name="name" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm" placeholder="Ex: Turno manhã"></div>
                <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Dia da semana *</label><select name="day_of_week" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm">@foreach($days as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Início *</label><input type="time" name="start_time" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"></div>
                    <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Fim *</label><input type="time" name="end_time" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"></div>
                </div>
                <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Pausa (min)</label><input type="number" name="break_minutes" value="0" min="0" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"></div>
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" checked> Ativo</label>
                <button type="submit" class="ui-button ui-button--primary px-4 py-2 text-xs font-bold rounded-xl w-full">Adicionar horário</button>
            </form>
        </div>
    </div>
</x-ui.partials.page-header>
@endsection
