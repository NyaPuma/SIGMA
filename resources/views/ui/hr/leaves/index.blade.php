@extends('ui.layout')
@section('page_key', 'hr-leaves')
@section('content')
<x-ui.partials.page-header title="Ausências e Férias" subtitle="Gestão de pedidos de ausência/férias e tipos." badge="RH">
    @if(session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <div class="ui-listing-table w-full overflow-hidden bg-[var(--surface)] border border-[var(--border)] rounded-2xl shadow-sm">
                <div class="px-6 py-4 border-b border-[var(--border)] font-bold">Pedidos</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[var(--border)] text-left text-xs">
                        <thead class="bg-[var(--surface-2)] text-[var(--text-soft)] uppercase tracking-wider font-bold text-xs">
                            <tr>
                                <th class="px-6 py-4">Colaborador</th>
                                <th class="px-6 py-4">Tipo</th>
                                <th class="px-6 py-4">Período</th>
                                <th class="px-6 py-4">Dias</th>
                                <th class="px-6 py-4">Estado</th>
                                <th class="px-6 py-4 text-right">Ação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--border)]">
                            @forelse($requests as $r)
                            <tr>
                                <td class="px-6 py-4">{{ $r->employee?->full_name ?? '-' }}</td>
                                <td class="px-6 py-4">{{ $r->leaveType?->name ?? '-' }}</td>
                                <td class="px-6 py-4">{{ optional($r->start_date)->format('Y-m-d') }} → {{ optional($r->end_date)->format('Y-m-d') }}</td>
                                <td class="px-6 py-4">{{ $r->days }}</td>
                                <td class="px-6 py-4">{{ $r->status }}</td>
                                <td class="px-6 py-4 text-right">
                                    @if($r->status === 'pending')
                                    <form method="POST" action="{{ route('ui.hr.leaves.status', $r) }}" class="inline-flex gap-2">
                                        @csrf @method('PATCH')
                                        <button name="status" value="approved" class="text-green-700 font-semibold">Aprovar</button>
                                        <button name="status" value="rejected" class="text-red-600 font-semibold">Rejeitar</button>
                                    </form>
                                    @else
                                        <span class="text-[var(--text-soft)]">—</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="px-6 py-8 text-center text-[var(--text-soft)]">Sem pedidos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-[var(--surface)] border border-[var(--border)] rounded-2xl p-6">
                <h3 class="font-bold text-base mb-4">Novo pedido</h3>
                <form method="POST" action="{{ route('ui.hr.leaves.store') }}" class="space-y-3">
                    @csrf
                    <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Colaborador *</label>
                        <select name="employee_id" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"><option value="">Selecionar...</option>@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->full_name }}</option>@endforeach</select>
                    </div>
                    <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Tipo *</label>
                        <select name="leave_type_id" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"><option value="">Selecionar...</option>@foreach($types as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Início *</label><input type="date" name="start_date" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"></div>
                        <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Fim *</label><input type="date" name="end_date" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"></div>
                    </div>
                    <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Dias *</label><input type="number" step="0.5" min="0.5" name="days" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"></div>
                    <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Motivo</label><textarea name="reason" rows="2" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"></textarea></div>
                    <button type="submit" class="ui-button ui-button--primary px-4 py-2 text-xs font-bold rounded-xl w-full">Criar pedido</button>
                </form>
            </div>

            <div class="bg-[var(--surface)] border border-[var(--border)] rounded-2xl p-6">
                <h3 class="font-bold text-base mb-4">Novo tipo de ausência</h3>
                <form method="POST" action="{{ route('ui.hr.leaves.types.store') }}" class="space-y-3">
                    @csrf
                    <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Nome *</label><input name="name" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"></div>
                    <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Código *</label><input name="code" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm" placeholder="vacation, sick..."></div>
                    <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Dias/ano</label><input type="number" name="days_per_year" min="0" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"></div>
                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="paid" value="1" checked> Remunerada</label>
                    <button type="submit" class="ui-button ui-button--primary px-4 py-2 text-xs font-bold rounded-xl w-full">Criar tipo</button>
                </form>
            </div>
        </div>
    </div>
</x-ui.partials.page-header>
@endsection
