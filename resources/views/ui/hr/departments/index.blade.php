@extends('ui.layout')
@section('page_key', 'hr-departments')
@section('content')
<x-ui.partials.page-header
    title="Departamentos"
    subtitle="Gestão de departamentos da organização."
    badge="RH"
>
    <x-slot:actions>
        <a href="{{ route('ui.hr.departments.create') }}" class="ui-button ui-button--primary px-4 py-2 text-xs font-bold rounded-xl">Novo Departamento</a>
    </x-slot:actions>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="ui-listing-table w-full overflow-hidden bg-[var(--surface)] border border-[var(--border)] rounded-2xl shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[var(--border)] text-left text-xs">
                <thead class="bg-[var(--surface-2)] text-[var(--text-soft)] uppercase tracking-wider font-bold text-xs">
                    <tr>
                        <th scope="col" class="px-6 py-4">Nome</th>
                        <th scope="col" class="px-6 py-4">Código</th>
                        <th scope="col" class="px-6 py-4">Gestor</th>
                        <th scope="col" class="px-6 py-4">Estado</th>
                        <th scope="col" class="px-6 py-4 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)] text-[var(--text)]">
                    @forelse($departments as $department)
                        <tr>
                            <td class="px-6 py-4 font-medium">{{ $department->name }}</td>
                            <td class="px-6 py-4">{{ $department->code ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $department->manager?->name ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $department->active ? 'Ativo' : 'Inativo' }}</td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('ui.hr.departments.edit', $department) }}" class="text-primary font-semibold mr-3">Editar</a>
                                <form action="{{ route('ui.hr.departments.destroy', $department) }}" method="POST" class="inline" onsubmit="return confirm('Remover este departamento?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 font-semibold">Remover</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-[var(--text-soft)]">Sem departamentos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-ui.partials.page-header>
@endsection
