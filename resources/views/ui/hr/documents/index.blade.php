@extends('ui.layout')
@section('page_key', 'hr-employees')
@section('content')
<x-ui.partials.page-header title="Documentos — {{ $employee->full_name }}" subtitle="Gestão de documentos do colaborador." badge="RH">
    <x-slot:actions>
        <a href="{{ route('ui.hr.employees.show', $employee) }}" class="px-4 py-2 text-xs font-semibold rounded-xl border border-[var(--border)]">Voltar à ficha</a>
    </x-slot:actions>
    @if(session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 ui-listing-table w-full overflow-hidden bg-[var(--surface)] border border-[var(--border)] rounded-2xl shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[var(--border)] text-left text-xs">
                    <thead class="bg-[var(--surface-2)] text-[var(--text-soft)] uppercase tracking-wider font-bold text-xs">
                        <tr><th class="px-6 py-4">Título</th><th class="px-6 py-4">Categoria</th><th class="px-6 py-4">Ficheiro</th><th class="px-6 py-4">Tamanho</th><th class="px-6 py-4">Data</th><th class="px-6 py-4 text-right">Ações</th></tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--border)]">
                        @forelse($employee->documents as $doc)
                        <tr>
                            <td class="px-6 py-4 font-medium">{{ $doc->title }}</td>
                            <td class="px-6 py-4">{{ $doc->category ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $doc->file_name }}</td>
                            <td class="px-6 py-4">{{ $doc->file_size ? number_format($doc->file_size / 1024, 1, ',', '.') . ' KB' : '-' }}</td>
                            <td class="px-6 py-4">{{ $doc->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('ui.hr.employees.documents.download', [$employee, $doc]) }}" class="text-primary font-semibold mr-3">Descarregar</a>
                                <form action="{{ route('ui.hr.employees.documents.destroy', [$employee, $doc]) }}" method="POST" class="inline" onsubmit="return confirm('Remover documento?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 font-semibold">Remover</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-[var(--text-soft)]">Sem documentos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="bg-[var(--surface)] border border-[var(--border)] rounded-2xl p-6">
            <h3 class="font-bold text-base mb-4">Carregar documento</h3>
            <form method="POST" action="{{ route('ui.hr.employees.documents.store', $employee) }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Título *</label><input name="title" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"></div>
                <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Categoria</label><select name="category" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"><option value="">—</option><option value="contract">Contrato</option><option value="id">Identificação</option><option value="certificate">Certificado</option><option value="medical">Médico</option><option value="other">Outro</option></select></div>
                <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Ficheiro * (máx. 10MB)</label><input type="file" name="file" required class="w-full text-sm"></div>
                <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Notas</label><textarea name="notes" rows="2" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"></textarea></div>
                <button type="submit" class="ui-button ui-button--primary px-4 py-2 text-xs font-bold rounded-xl w-full">Carregar</button>
            </form>
        </div>
    </div>
</x-ui.partials.page-header>
@endsection
