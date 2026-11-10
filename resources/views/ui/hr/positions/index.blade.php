@extends('ui.layout')
@section('page_key', 'hr-positions')
@section('content')
<x-ui.partials.page-header title="Cargos" subtitle="Gestão de cargos/funções." badge="RH">
    <x-slot:actions>
        <a href="{{ route('ui.hr.positions.create') }}" class="ui-button ui-button--primary px-4 py-2 text-xs font-bold rounded-xl">Novo Cargo</a>
    </x-slot:actions>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="ui-listing-table w-full overflow-hidden bg-[var(--surface)] border border-[var(--border)] rounded-2xl shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[var(--border)] text-left text-xs">
                <thead class="bg-[var(--surface-2)] text-[var(--text-soft)] uppercase tracking-wider font-bold text-xs">
                    <tr>
                        <th class="px-6 py-4">Nome</th>
                        <th class="px-6 py-4">Departamento</th>
                        <th class="px-6 py-4">Estado</th>
                        <th class="px-6 py-4 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)] text-[var(--text)]">
                    @forelse($positions as $position)
                        <tr>
                            <td class="px-6 py-4 font-medium">{{ $position->name }}</td>
                            <td class="px-6 py-4">{{ $position->department?->name ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $position->active ? 'Ativo' : 'Inativo' }}</td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('ui.hr.positions.edit', $position) }}" class="text-primary font-semibold mr-3">Editar</a>
                                <form action="{{ route('ui.hr.positions.destroy', $position) }}" method="POST" class="inline" onsubmit="return confirm('Remover este cargo?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 font-semibold">Remover</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-[var(--text-soft)]">Sem cargos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-ui.partials.page-header>
@endsection
