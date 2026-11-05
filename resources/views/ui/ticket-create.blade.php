@extends('ui.layout')
@section('page_key', 'ticket-create')

@section('content')
<x-ui.partials.page-header
    :title="__('tickets.Criar Ticket')"
    :subtitle="__('tickets.Registe uma nova ocorrência de manutenção com contexto técnico e prioridade.')"
>
    {{-- Main Form Container --}}
    <div class="rounded-3xl border border-[var(--border)] bg-[var(--surface)] p-8 shadow-sm">

        <div class="mb-6 border-b border-[var(--border)] pb-4">
            <h2 class="text-sm font-bold text-[var(--text)]">{{ __('common.Novo pedido de intervenção') }}</h2>
            <p class="text-xs text-[var(--text-soft)] mt-0.5">{{ __('common.Descreva a situação de forma objetiva para que a equipa técnica possa agir rapidamente.') }}</p>
        </div>

        <form id="createTicketForm" class="space-y-6">

            {{-- Request Title --}}
            <div>
                <label for="ticketTitle" class="mb-2 block text-xs font-bold uppercase tracking-[0.2em] text-[var(--text-soft)]">{{ __('common.Título do Pedido *') }}</label>
                <input type="text" id="ticketTitle" name="title" required class="w-full rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3 text-xs text-[var(--text)] outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 transition-all" placeholder="{{ __('common.Ex.: Ruído anómalo no motor principal do torno') }}">
            </div>

            {{-- Detailed Description --}}
            <div>
                <label for="ticketDescription" class="mb-2 block text-xs font-bold uppercase tracking-[0.2em] text-[var(--text-soft)]">{{ __('common.Descrição Detalhada *') }}</label>
                <textarea id="ticketDescription" name="description" rows="4" required class="w-full rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3 text-xs text-[var(--text)] outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 resize-none transition-all" placeholder="{{ __('ticket_detail.Detalhe o problema ocorrido, ruídos, fugas ou comportamentos fora do normal...') }}"></textarea>
            </div>

            {{-- Urgency / Priority Level (Interactive Cards) --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span id="ticketPriority-label" class="block text-xs font-bold uppercase tracking-[0.2em] text-[var(--text-soft)]">{{ __('common.Nível de Urgência / Prioridade *') }}</span>
                    <span class="text-xs text-[var(--text-soft)]">{{ __('common.Selecione o impacto real na produção') }}</span>
                </div>

                <input type="hidden" id="ticketPriority" name="priority" value="média" required>

                <div class="grid gap-4 md:grid-cols-4" role="radiogroup" aria-labelledby="ticketPriority-label">
                    {{-- Low Card --}}
                    <div role="radio" tabindex="0" aria-checked="false" data-priority="baixa"
                        class="priority-card cursor-pointer rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] p-4 transition-all hover:border-success/50">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-success">{{ __('tickets.Baixa') }}</span>
                            </div>
                            <span class="h-2 w-2 rounded-full bg-success"></span>
                        </div>
                        <p class="text-xs leading-relaxed text-[var(--text-soft)]">{{ __('maintenance_plan.Manutenção Ligeira. Anomalia menor sem risco imediato.') }}</p>
                    </div>

                    {{-- Medium Card (Selected by default) --}}
                    <div role="radio" tabindex="0" aria-checked="true" data-priority="média"
                        class="priority-card cursor-pointer rounded-2xl border-2 border-warning bg-[var(--surface-2)] p-4 transition-all shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-warning">{{ __('common.Média') }}</span>
                            </div>
                            <span class="h-2 w-2 rounded-full bg-warning"></span>
                        </div>
                        <p class="text-xs leading-relaxed text-[var(--text-soft)]">{{ __('common.Degradação Parcial. Funcionamento condicionado.') }}</p>
                    </div>

                    {{-- High Card --}}
                    <div role="radio" tabindex="0" aria-checked="false" data-priority="alta"
                        class="priority-card cursor-pointer rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] p-4 transition-all hover:border-danger/50">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-danger">{{ __('tickets.Alta') }}</span>
                            </div>
                            <span class="h-2 w-2 rounded-full bg-danger"></span>
                        </div>
                        <p class="text-xs leading-relaxed text-[var(--text-soft)]">{{ __('common.Paragem Crítica. Linha ou máquina inoperacional.') }}</p>
                    </div>

                    {{-- Critical Card --}}
                    <div role="radio" tabindex="0" aria-checked="false" data-priority="crítica"
                        class="priority-card cursor-pointer rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] p-4 transition-all hover:border-purple-600/50">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-purple-600">{{ __('common.Crítica') }}</span>
                            </div>
                            <span class="h-2 w-2 rounded-full bg-purple-600"></span>
                        </div>
                        <p class="text-xs leading-relaxed text-[var(--text-soft)]">{{ __('ticket_media.Emergência Imediata. Risco de acidente.') }}</p>
                    </div>
                </div>
            </div>

            {{-- Equipment (Autocomplete) & Image --}}
            <div class="grid gap-4 lg:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)]">
                        {{ __('equipment.Equipamento / Ativo Afetado *') }}
                    </label>
                    <div class="relative">
                        <input type="text" id="equipmentSearchInput" autocomplete="off" placeholder="{{ __('equipment.Clique para selecionar equipamento...') }}"
                            class="w-full rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3 text-xs text-[var(--text)] outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 transition-all">
                        <input type="hidden" id="selectedEquipmentId" name="equipment_id" required>
                    </div>
                </div>

                {{-- Upload Image --}}
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)]">{{ __('ticket_media.Inserir Imagem (Opcional)') }}</label>
                    <div class="flex items-center gap-3 w-full rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3">
                        <label for="ticketImage" class="cursor-pointer rounded-xl bg-[var(--surface)] border border-[var(--border)] px-3 py-1.5 text-xs font-semibold text-[var(--text)] hover:bg-[var(--surface-2)] transition">
                            {{ __('ticket_media.Escolher ficheiro') }}
                        </label>
                        <input type="file" id="ticketImage" accept="image/*" class="hidden">
                        <span id="fileName" class="text-xs text-[var(--text-soft)] truncate">{{ __('ticket_media.Nenhum ficheiro selecionado') }}</span>
                    </div>
                </div>
            </div>

            {{-- Feedback Message --}}
            <div id="formMessage" class="min-h-6 text-xs font-medium text-[var(--text-soft)]"></div>

            {{-- Submit Button --}}
            <div class="mt-6 flex flex-wrap items-center gap-3">
                <button type="submit" id="submitBtn" class="inline-flex items-center justify-center rounded-xl px-6 py-3 text-xs font-black uppercase tracking-wider transition hover:opacity-90 disabled:opacity-50 shadow-lg shadow-primary/20 bg-primary text-[var(--on-primary)] cursor-pointer">
                    {{ __('tickets.Guardar Ticket') }}
                </button>
                <a href="/ui/tickets" class="ui-button ui-button--outline inline-flex items-center justify-center rounded-2xl border border-[var(--border)] bg-[var(--surface)] px-5 py-3 text-sm font-semibold text-[var(--text)] transition hover:bg-[var(--surface-2)]">
                    {{ __('ui.Cancelar') }}
                </a>
            </div>
        </form>
    </div>
</x-ui.partials.page-header>
@endsection






