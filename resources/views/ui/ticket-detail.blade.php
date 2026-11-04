@extends('ui.layout')
@section('page_key', 'ticket-detail')

@section('content')
<x-ui.partials.page-header
    :title="__('tickets.Detalhes do Ticket')"
    data-ticket-id="{{ $ticketId ?? $ticket->id ?? '' }}"
>
    <x-slot:actions>
        <x-ui.page-actions.group>
            <x-ui.page-actions.back-button :href="'/ui/tickets'" :label="__('ui.Voltar à Listagem')" />
        </x-ui.page-actions.group>
    </x-slot:actions>

    <div class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr] items-start">

        <div class="space-y-4">

            <div id="ticketDetails" class="rounded-2xl border border-(--border) bg-(--surface) p-4 shadow-sm space-y-3">
                <div class="text-xs text-(--text-soft) animate-pulse py-4 text-center">{{ __('ui.A carregar...') }}</div>
            </div>

            <div class="rounded-2xl border border-(--border) bg-(--surface) p-4 shadow-sm space-y-3">
                <h2 class="text-xs font-bold uppercase tracking-wider text-(--text) border-b border-(--border) pb-2">{{ __('common.Histórico & Comentários') }}</h2>
                <div id="commentsSection"
                     class="text-xs text-(--text-soft) max-h-28 overflow-y-auto pr-1 space-y-2"
                     data-no-comments="{{ __('ticket_detail.no_comments') }}"
                     data-comments-error="{{ __('ticket_detail.comments_error') }}"
                     data-message-sent="{{ __('ticket_detail.message_sent') }}">
                    <p class="italic py-1 text-center text-xs">{{ __('ui.A carregar histórico...') }}</p>
                </div>
                <form id="commentForm" class="flex gap-2 items-center pt-2 border-t border-(--border)">
                    <input id="commentText" type="text" required class="flex-1 rounded-xl border border-(--border) bg-(--surface-2) px-3 py-2 text-xs text-(--text) outline-none focus:border-primary transition-all" placeholder="{{ __('messages.Escreva uma mensagem...') }}" aria-label="{{ __('messages.Escreva uma mensagem...') }}">
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-xs font-extrabold uppercase tracking-wider bg-primary text-[var(--on-primary)] hover:opacity-90 transition shadow-sm cursor-pointer whitespace-nowrap">
                        {{ __('common.Enviar') }}
                    </button>
                </form>
            </div>

            <div class="rounded-2xl border border-(--border) bg-(--surface) p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-(--border) pb-2">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-(--text)">{{ __('common.Evidências Fotográficas') }}</h2>
                    <span class="text-xs font-bold text-(--text-soft) uppercase tracking-wider">{{ __('ticket_media.Anexos') }}</span>
                </div>
                <form id="photoForm" class="flex items-center gap-2 border-b border-(--border) pb-3">
                    <label for="photoInput" class="cursor-pointer rounded-xl bg-(--surface-2) border border-(--border) px-3 py-1.5 text-xs font-semibold text-(--text) hover:bg-(--border) transition whitespace-nowrap">
                        {{ __('common.Escolher') }}
                    </label>
                    <input id="photoInput" type="file" accept="image/*" class="hidden">
                    <span id="photoFileName" class="text-xs text-(--text-soft) truncate flex-1 block">{{ __('ticket_media.Nenhum ficheiro') }}</span>
                    <button type="submit" class="py-1.5 px-3 bg-(--surface-2) hover:bg-(--border) text-xs font-bold text-(--text) border border-(--border) rounded-xl transition cursor-pointer whitespace-nowrap">
                        {{ __('common.Enviar') }}
                    </button>
                </form>
                <div id="photosSection"
                     class="text-xs text-(--text-soft)"
                     data-photo-empty="{{ __('ticket_media.empty') }}"
                     data-photo-load-error="{{ __('ticket_media.load_error') }}"
                     data-photo-remove-error="{{ __('ticket_media.remove_error') }}"
                     data-photo-removed="{{ __('ticket_media.removed') }}"
                     data-photo-sent="{{ __('ticket_media.sent') }}"
                     data-photo-confirm-remove="{{ __('ticket_media.confirm_remove') }}"
                     data-photo-remove="{{ __('ticket_media.remove_photo') }}"
                     data-file-remove="{{ __('ticket_media.remove_file') }}"
                     data-file-label="{{ __('ticket_media.file') }}">
                    <p class="italic text-xs">{{ __('common.Nenhuma evidência carregada.') }}</p>
                </div>
            </div>

        </div>

        <div class="space-y-4">

            @if(isset($user) && $user && $user->isAdmin())
            <div id="budgetApprovalCard" class="hidden rounded-2xl border border-warning/40 bg-(--surface) p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-(--border) pb-2">
                    <span class="px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-warning bg-warning/10 border border-warning/20 rounded-lg">{{ __('common.Aprovação Orçamental') }}</span>
                    <span id="budgetEstimatedCost" class="text-xs font-black text-warning">(0)</span>
                </div>
                <div>
                    <p class="text-xs text-(--text-soft) leading-tight">{{ __('common.Limiar de aprovação:') }} <span id="budgetThresholdDisplay">(0)</span></p>
                    <p class="text-xs text-(--text-soft) leading-tight">{{ __('common.Técnico:') }} <span id="budgetTechnicianName">—</span></p>
                </div>
                <div id="budgetDetailsContainer" class="hidden">
                    <div class="rounded-xl border border-(--border) bg-(--surface-2) p-3">
                        <div id="budgetDetailsList"></div>
                        <div class="mt-2 pt-2 border-t border-(--border) flex justify-between text-xs font-bold">
                            <span>{{ __('common.Total') }}</span>
                            <span id="budgetDetailsTotal" class="font-mono">(0)</span>
                        </div>
                    </div>
                </div>
                <label for="budgetFeedback" class="block text-xs font-bold uppercase tracking-wider text-(--text-soft) mb-1">{{ __('common.Justificação') }}</label>
                <textarea id="budgetFeedback" rows="2" placeholder="{{ __('validation.Justificação (obrigatório para recusar)...') }}" class="w-full rounded-xl border border-(--border) bg-(--surface-2) px-3 py-2 text-xs text-(--text) resize-none outline-none"></textarea>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" id="btnApproveBudget" class="py-2 bg-success/10 text-success border border-success/30 hover:bg-success/20 text-xs font-bold rounded-xl shadow-sm transition cursor-pointer">{{ __('common.Validar Orçamento') }}</button>
                    <button type="button" id="btnRejectBudget" class="py-2 bg-danger/10 text-danger border border-danger/30 hover:bg-danger/20 text-xs font-bold rounded-xl shadow-sm transition cursor-pointer">{{ __('common.Não Validar') }}</button>
                </div>
            </div>

            <div class="rounded-2xl border border-(--border) bg-(--surface) p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-(--border) pb-2">
                    <span class="px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-primary bg-primary/10 border border-primary/20 rounded-lg">{{ __('dashboard.Painel do Admin') }}</span>
                    <span id="adminTicketId" class="text-xs font-mono font-bold text-(--text-soft)">#{{ $ticketId ?? $ticket->id ?? '' }}</span>
                </div>
                <div>
                    <h2 class="text-xs font-bold text-(--text)">{{ __('common.Atribuição de Técnico') }}</h2>
                    <p class="text-xs text-(--text-soft) mt-0.5 leading-tight">{{ __('common.Defina manualmente o responsável ou solicite à IA para triagem automática.') }}</p>
                </div>
                <div class="space-y-2 pt-1">
                    <div>
                        <label for="assignTechnicianId" class="block text-xs font-bold uppercase tracking-wider text-(--text-soft) mb-1">{{ __('common.ID do Técnico (Manual)') }}</label>
                        <input id="assignTechnicianId" type="number" class="w-full rounded-xl border border-(--border) bg-(--surface-2) px-3 py-2 text-xs text-(--text) outline-none" placeholder="{{ __('common.Ex: 12') }}">
                    </div>
                    <div class="space-y-2 pt-1">
                        <button id="btnAssignManual" type="button" class="w-full py-2 bg-(--surface-2) hover:bg-(--surface-alt) text-(--text) text-xs font-bold rounded-xl border border-(--border) shadow-sm transition cursor-pointer">{{ __('common.Atribuir Técnico') }}</button>
                        <button id="btnAssignAuto" type="button" class="w-full py-2 bg-primary hover:bg-primary-hover text-[var(--on-primary)] text-xs font-extrabold rounded-xl shadow-md transition cursor-pointer flex items-center justify-center gap-1.5">{{ __('common.Atribuição Automática (IA)') }}</button>
                    </div>
                </div>
            </div>
            @endif

            @if(isset($user) && $user && $user->isTechnician())

            <div id="techStartCard" class="hidden rounded-2xl border border-primary/30 bg-(--surface) p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-(--border) pb-2">
                    <span class="text-xs font-bold uppercase tracking-widest text-primary bg-primary/10 px-2 py-0.5 rounded-lg">{{ __('common.Operacional') }}</span>
                    <span class="text-xs font-bold text-warning">{{ __('common.Livre') }}</span>
                </div>
                <div>
                    <h2 class="text-xs font-bold text-(--text)">{{ __('tickets.Assumir Ocorrência') }}</h2>
                    <p class="text-xs text-(--text-soft) mt-0.5 leading-tight">{{ __('tickets.Este ticket encontra-se livre. Caso tenha disponibilidade, assuma a reparação.') }}</p>
                </div>
                <div class="space-y-2">
                    <button type="button" id="btnStartRepair" class="w-full inline-flex items-center justify-center rounded-xl py-2.5 text-xs font-black uppercase tracking-wider bg-primary text-[var(--on-primary)] hover:opacity-90 shadow-md shadow-primary/20 transition cursor-pointer">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z"></path></svg>
                        {{ __('common.Iniciar Intervenção') }}
                    </button>
                    <button type="button" id="btnStartRepairForce" class="hidden w-full py-2 bg-warning/10 text-warning border border-warning/30 hover:bg-warning/20 text-xs font-bold rounded-xl shadow-sm transition cursor-pointer flex items-center justify-center gap-1.5">
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg> {{ __('common.Forçar Início (ignorar prioritários)') }}
                    </button>
                </div>
            </div>

            <div id="techBudgetSubmitCard" class="hidden rounded-2xl border border-primary/30 bg-(--surface) p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-(--border) pb-2">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-(--text)">{{ __('common.Avaliação Orçamental Detalhada') }}</h2>
                </div>
                <p class="text-xs text-(--text-soft) leading-tight">{{ __('tickets.Introduza o orçamento estimado. Se o total exceder 50€, o ticket aguardará autorização da Administração.') }}</p>
                <div class="space-y-3 pt-1">
                    <div>
                        <span class="block text-xs font-bold uppercase tracking-wider text-(--text-soft) mb-1.5">{{ __('common.Itens do Orçamento') }}</span>
                        <div id="budgetItemsContainer" class="space-y-1.5 mb-2"></div>
                        <button type="button" id="btnAddBudgetItem" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold text-primary bg-primary/10 border border-primary/30 rounded-lg hover:bg-primary/20 transition cursor-pointer">
                            + {{ __('ui.ADICIONAR ITEM') }}
                        </button>
                    </div>
                    <div class="p-2.5 bg-(--surface-2) border border-(--border) rounded-xl flex items-center justify-between">
                        <span class="text-xs font-bold uppercase text-(--text-soft)">{{ __('common.Total Estimado') }}</span>
                        <span id="techTotalEstimatedDisplay" class="text-sm font-extrabold text-(--text)">(0)</span>
                    </div>
                    <input type="hidden" id="techEstimatedCostInput" name="estimatedBudget">
                    <button type="button" id="btnSubmitEstimatedBudget" class="w-full py-2.5 px-3 bg-primary hover:bg-primary-hover text-[var(--on-primary)] text-xs font-bold rounded-xl shadow-sm transition cursor-pointer">
                        {{ __('common.Submeter Orçamento Detalhado') }}
                    </button>
                </div>
            </div>

            <div id="techBlockedCard" class="hidden rounded-2xl border border-warning/30 bg-warning/5 p-4 shadow-sm space-y-2 text-center">
                <div class="text-xs font-bold text-warning">{{ __('ui.Aguardar Validação Orçamental') }}</div>
                <p class="text-xs text-(--text-soft) leading-tight">{{ __('tickets.O orçamento excede o limiar. O ticket aguarda aprovação da Administração.') }}</p>
            </div>

            <div id="techApprovedCard" class="hidden rounded-2xl border border-success/30 bg-(--surface) p-4 shadow-sm space-y-2">
                <div class="flex items-center justify-between border-b border-(--border) pb-2">
                    <span class="text-xs font-bold uppercase tracking-widest text-success bg-success/10 px-2 py-0.5 rounded-lg">{{ __('common.Autorizado') }}</span>
                    <span class="text-xs font-bold text-success">{{ __('common.Aprovado') }}</span>
                </div>
                <h2 class="text-xs font-bold text-(--text)">{{ __('common.Orçamento Aprovado') }}</h2>
                <p class="text-xs text-(--text-soft) leading-tight">{{ __('common.O orçamento foi aprovado. Pode iniciar a reparação.') }}</p>
            </div>

            <div id="techRejectedCard" class="hidden rounded-2xl border border-danger/30 bg-danger/5 p-4 shadow-sm space-y-2">
                <div class="flex items-center justify-between border-b border-(--border) pb-2">
                    <span class="text-xs font-bold uppercase tracking-widest text-danger bg-danger/10 px-2 py-0.5 rounded-lg">{{ __('common.Recusado') }}</span>
                </div>
                <p class="text-xs text-(--text-soft) text-center py-1">{{ __('common.Orçamento recusado pela Administração.') }}</p>
            </div>

            <div id="techCompletionCard" class="hidden rounded-2xl border border-success/30 bg-(--surface) p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-(--border) pb-2">
                    <span class="text-xs font-bold uppercase tracking-widest text-success bg-success/10 px-2 py-0.5 rounded-lg">{{ __('common.A minha intervenção') }}</span>
                    <span class="text-xs font-bold text-success">{{ __('common.Autorizado') }}</span>
                </div>
                <p class="text-xs text-(--text-soft) leading-tight">{{ __('common.Intervenção autorizada. Conclua os trabalhos e registe os dados finais.') }}</p>
                <div class="space-y-2.5 pt-1">
                    <div>
                        <label for="techTotalCost" class="block text-xs font-bold uppercase tracking-wider text-(--text-soft) mb-1">{{ __('common.Custo Final (€)') }}</label>
                        <input type="number" id="techTotalCost" step="0.01" placeholder="0.00" class="w-full rounded-xl border border-(--border) bg-(--surface-2) px-3 py-1.5 text-xs text-(--text) font-mono">
                    </div>
                    <div>
                        <label for="techFinalReport" class="block text-xs font-bold uppercase tracking-wider text-(--text-soft) mb-1">{{ __('common.Relatório Técnico') }}</label>
                        <textarea id="techFinalReport" rows="2" placeholder="{{ __('common.Descrição da reparação efetuada...') }}" class="w-full rounded-xl border border-(--border) bg-(--surface-2) px-3 py-1.5 text-xs text-(--text) resize-none"></textarea>
                    </div>
                    <button type="button" id="btnFinishTicket" class="w-full py-2 bg-success/10 text-success border border-success/30 hover:bg-success/20 rounded-xl text-xs font-bold uppercase tracking-wider transition cursor-pointer">
                        {{ __('tickets.Finalizar e Fechar Ticket') }}
                    </button>
                </div>
            </div>
            @endif

        </div>
    </div>

    <div id="ticketMessage" class="mt-4 min-h-6 px-1 text-xs font-medium transition-all duration-300"></div>

    <div id="priorityWarningModal" role="dialog" aria-modal="true" aria-labelledby="priorityWarningTitle" class="hidden fixed inset-0 z-[9999] items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="relative w-full max-w-md rounded-2xl border border-(--border) bg-(--surface) p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-warning bg-warning/10 px-2 py-0.5 rounded-lg">{{ __('common.Prioridades') }}</span>
                <span class="flex h-6 w-6 items-center justify-center rounded-xl bg-warning/10 text-warning">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                </span>
            </div>
            <div>
                <h2 id="priorityWarningTitle" class="text-sm font-black text-(--text)">{{ __('tickets.Atenção: Tickets Prioritários') }}</h2>
                <p id="priorityWarningCount" class="mt-1 text-xs text-(--text-soft) leading-relaxed"></p>
                <p id="priorityWarningCurrent" class="mt-1 text-xs text-(--text-soft)"></p>
                <p id="priorityWarningAction" class="mt-1 text-xs text-(--text-soft)"></p>
            </div>
            <div class="grid grid-cols-2 gap-2 pt-2">
                <button type="button" id="btnViewUrgentTickets" class="py-2 px-3 bg-primary text-[var(--on-primary)] text-xs font-bold rounded-xl hover:opacity-90 transition cursor-pointer">{{ __('tickets.Ver Tickets Urgentes') }}</button>
                <button type="button" id="btnForceStartTicket" class="py-2 px-3 bg-warning/10 text-warning border border-warning/30 hover:bg-warning/20 text-xs font-bold rounded-xl transition cursor-pointer">{{ __('common.Prosseguir Mesmo Assim') }}</button>
            </div>
        </div>
    </div>

</x-ui.partials.page-header>
@endsection
