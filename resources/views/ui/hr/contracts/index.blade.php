@extends('ui.layout')
@section('page_key', 'hr-employees')
@section('content')
<x-ui.partials.page-header title="Contratos — {{ $employee->full_name }}" subtitle="Gestão de contratos do colaborador." badge="RH">
    <x-slot:actions>
        <a href="{{ route('ui.hr.employees.contracts.create', $employee) }}" class="ui-button ui-button--primary px-4 py-2 text-xs font-bold rounded-xl">Novo Contrato</a>
        <a href="{{ route('ui.hr.employees.show', $employee) }}" class="px-4 py-2 text-xs font-semibold rounded-xl border border-[var(--border)]">Voltar à ficha</a>
    </x-slot:actions>
    @if(session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    <div class="ui-listing-table w-full overflow-hidden bg-[var(--surface)] border border-[var(--border)] rounded-2xl shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[var(--border)] text-left text-xs">
                <thead class="bg-[var(--surface-2)] text-[var(--text-soft)] uppercase tracking-wider font-bold text-xs">
                    <tr><th class="px-6 py-4">Tipo</th><th class="px-6 py-4">Início</th><th class="px-6 py-4">Fim</th><th class="px-6 py-4">Salário</th><th class="px-6 py-4">Horas/sem</th><th class="px-6 py-4">Ativo</th><th class="px-6 py-4 text-right">Ações</th></tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)]">
                    @forelse($employee->contracts as $contract)
                    <tr>
                        <td class="px-6 py-4 font-medium">{{ $contract->type }}</td>
                        <td class="px-6 py-4">{{ optional($contract->start_date)->format('Y-m-d') }}</td>
                        <td class="px-6 py-4">{{ optional($contract->end_date)->format('Y-m-d') ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $contract->salary_amount !== null ? number_format((float)$contract->salary_amount, 2, ',', '.') . ' ' . $contract->salary_currency : '-' }}</td>
                        <td class="px-6 py-4">{{ $contract->work_hours_per_week ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $contract->active ? 'Sim' : 'Não' }}</td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('ui.hr.employees.contracts.edit', [$employee, $contract]) }}" class="text-primary font-semibold mr-3">Editar</a>
                            <form action="{{ route('ui.hr.employees.contracts.destroy', [$employee, $contract]) }}" method="POST" class="inline" onsubmit="return confirm('Remover contrato?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 font-semibold">Remover</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-6 py-8 text-center text-[var(--text-soft)]">Sem contratos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-ui.partials.page-header>
@endsection
