@extends('ui.layout')
@section('page_key', 'hr-employees')
@section('content')
<x-ui.partials.page-header title="Colaboradores" subtitle="Gestão de trabalhadores/colaboradores." badge="RH">
    <x-slot:actions>
        <a href="{{ route('ui.hr.employees.create') }}" class="ui-button ui-button--primary px-4 py-2 text-xs font-bold rounded-xl">Novo Colaborador</a>
    </x-slot:actions>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="ui-listing-table w-full overflow-hidden bg-[var(--surface)] border border-[var(--border)] rounded-2xl shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[var(--border)] text-left text-xs">
                <thead class="bg-[var(--surface-2)] text-[var(--text-soft)] uppercase tracking-wider font-bold text-xs">
                    <tr>
                        <th class="px-6 py-4">N.º</th>
                        <th class="px-6 py-4">Nome</th>
                        <th class="px-6 py-4">Departamento</th>
                        <th class="px-6 py-4">Cargo</th>
                        <th class="px-6 py-4">Estado</th>
                        <th class="px-6 py-4 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)] text-[var(--text)]">
                    @forelse($employees as $employee)
                        <tr>
                            <td class="px-6 py-4">{{ $employee->employee_number ?? '-' }}</td>
                            <td class="px-6 py-4 font-medium"><a href="{{ route('ui.hr.employees.show', $employee) }}" class="text-primary hover:underline">{{ $employee->full_name }}</a></td>
                            <td class="px-6 py-4">{{ $employee->department?->name ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $employee->position?->name ?? '-' }}</td>
                            <td class="px-6 py-4">{{ ['active'=>'Ativo','on_leave'=>'Em licença','terminated'=>'Terminado'][$employee->status] ?? $employee->status }}</td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <a href="{{ route('ui.hr.employees.show', $employee) }}" class="text-primary font-semibold mr-3">Ver</a>
                                <a href="{{ route('ui.hr.employees.edit', $employee) }}" class="text-primary font-semibold mr-3">Editar</a>
                                <form action="{{ route('ui.hr.employees.destroy', $employee) }}" method="POST" class="inline" onsubmit="return confirm('Remover este colaborador?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 font-semibold">Remover</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-[var(--text-soft)]">Sem colaboradores.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-ui.partials.page-header>
@endsection
