@extends('ui.layout')

@section('page_key', 'equipment-qr')

@section('content')
<x-ui.partials.page-header
    :title="__('common.Código QR')"
    :subtitle="trim(($equipment->brand ?? '') . ' ' . ($equipment->model ?? '')) ?: $equipment->name"
    :badge="$equipment->serial"
>
    <x-slot:actions>
        <x-ui.page-actions.group>
            <x-ui.page-actions.back-button :href="route('ui.equipments.show', $equipment)" :label="__('equipment.Voltar ao Equipamento')" />
        </x-ui.page-actions.group>
    </x-slot:actions>

    <div class="grid items-start gap-6 lg:grid-cols-[1fr_1.1fr]">

        {{-- QR Code --}}
        <div class="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-8 shadow-sm">
            <div class="mb-4 flex items-center justify-between border-b border-[var(--border)] pb-3">
                <h2 class="text-xs font-black uppercase tracking-wider text-[var(--text)]">{{ __('equipment.QR Code do Equipamento') }}</h2>
                <span class="rounded-lg border border-[var(--border)] bg-[var(--surface-2)] px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-[var(--text-soft)]">{{ $equipment->asset_tag ?? __('common.sem etiqueta') }}</span>
            </div>

            <div class="mx-auto flex w-fit flex-col items-center rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 shadow-inner">
                <img src="{{ $qrDataUri }}" alt="{{ __('common.QR Code de :name', ['name' => $equipment->name]) }}" class="h-64 w-64">
                <p class="mt-4 text-center text-xs font-semibold leading-5 text-[var(--text)]">{{ $equipment->name }}</p>
                <p class="text-center text-xs text-[var(--text-soft)]">{{ __('ui.Aponte a câmara para abrir o formulário de avaria.') }}</p>
            </div>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('ui.equipments.qr.download', $equipment) }}"
                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 text-xs font-black uppercase tracking-wider text-[var(--on-primary)] shadow-lg shadow-primary/20 transition hover:opacity-90">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    {{ __('ui.Descarregar PNG') }}
                </a>
                <button type="button" data-action="print"
                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3 text-xs font-black uppercase tracking-wider text-[var(--text)] transition hover:bg-[var(--border)] cursor-pointer">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5z"/></svg>
                    {{ __('common.Imprimir') }}
                </button>
            </div>
        </div>

        {{-- Information and Actions --}}
        <div class="space-y-6">
            <div class="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-sm">
                <h2 class="mb-4 text-xs font-black uppercase tracking-wider text-[var(--text)]">{{ __('equipment.Informação do Equipamento') }}</h2>
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wider text-[var(--text-soft)]">{{ __('common.Número de Série') }}</dt>
                        <dd class="mt-1 font-mono text-xs font-semibold text-[var(--text)]">{{ $equipment->serial ?? __('common.Não definido') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wider text-[var(--text-soft)]">{{ __('equipment.Etiqueta de Ativo') }}</dt>
                        <dd class="mt-1 font-mono text-xs font-semibold text-[var(--text)]">{{ $equipment->asset_tag ?? __('common.Não definida') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wider text-[var(--text-soft)]">{{ __('common.Categoria') }}</dt>
                        <dd class="mt-1 text-xs font-semibold text-[var(--text)]">{{ $equipment->category?->name ?? __('common.Sem categoria') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wider text-[var(--text-soft)]">{{ __('room.Sala') }}</dt>
                        <dd class="mt-1 text-xs font-semibold text-[var(--text)]">{{ $equipment->room?->name ?? __('common.Não associada') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-sm">
                <h2 class="mb-4 text-xs font-black uppercase tracking-wider text-[var(--text)]">{{ __('common.Link Codificado') }}</h2>
                <div class="rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3">
                    <p class="break-all font-mono text-xs leading-5 text-[var(--text-soft)]">{{ $ticketUrl }}</p>
                </div>
                <p class="mt-3 text-xs leading-5 text-[var(--text-soft)]">
                    {{ __('equipment.Este endereço abre o formulário público de reporte para este equipamento, sem necessidade de conta.') }}
                </p>
            </div>

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-sm">
                <h2 class="mb-3 text-xs font-black uppercase tracking-wider text-[var(--text)]">{{ __('common.Exportação em Lote') }}</h2>
                <p class="mb-4 text-xs leading-5 text-[var(--text-soft)]">
                    {{ __('equipment.Gere um PDF com os QR Codes de todos os equipamentos ativos para impressão e afixação.') }}
                </p>
                <div data-async-message class="mb-4 hidden rounded-xl border border-[var(--border)] px-4 py-3 text-xs font-medium text-(--text-muted)"></div>
                <a href="{{ route('ui.equipments.qr.export') }}"
                    data-async-export="pdf"
                    data-processing-label="A gerar PDF..."
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3 text-xs font-black uppercase tracking-wider text-[var(--text)] transition hover:bg-[var(--border)]">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    {{ __('common.Exportar Todos (PDF)') }}
                </a>
            </div>
        </div>
    </div>
</x-ui.partials.page-header>
@endsection
