@extends('ui.layout')

@section('page_key', 'stock-plans')

@section('content')
<x-ui.partials.page-header
    :title="__('maintenance_plan.Planos de Manutenção Preventiva')"
    :subtitle="__('equipment.Defina periodicidades de manutenção e as peças previstas por equipamento.')"
>
    <x-slot:actions>
        <x-ui.page-actions.group>
            <x-ui.page-actions.back-button :href="route('ui.stock.dashboard')" :label="__('stock.Voltar ao Stock')" />
        </x-ui.page-actions.group>
    </x-slot:actions>

    {{-- Create / edit form --}}
    <div class="mb-6 rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 shadow-sm">
        <h2 id="planFormTitle" class="mb-4 text-xs font-black uppercase tracking-wider text-[var(--text)]">{{ __('maintenance_plan.Novo plano de manutenção') }}</h2>
        <form id="planForm" class="space-y-4" novalidate data-plan-form-mode="create" data-plan-id="">

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {{-- Plan Name --}}
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)]">
                        {{ __('common.Nome') }} <span class="text-danger">*</span>
                    </label>
                    <input id="plName" name="name" type="text" required placeholder="{{ __('maintenance_plan.Ex: Manutenção trimestral') }}"
                        class="w-full rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3 text-xs text-[var(--text)] outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 transition-all">
                </div>

                {{-- Equipment Selection (with Modal) --}}
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)]">
                        {{ __('equipment.Equipamento') }} <span class="text-danger">*</span>
                    </label>
                    <div class="relative">
                        <input type="text" id="plEquipmentSearch" name="equipment_search" autocomplete="off" required
                            placeholder="{{ __('equipment.Clique para selecionar equipamento...') }}"
                            class="w-full rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3 text-xs text-[var(--text)] outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 transition-all cursor-pointer">
                        <input type="hidden" id="plEquipment" name="equipment_id">
                    </div>
                </div>

                {{-- Interval Type --}}
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)]">
                        {{ __('common.Periodicidade') }} <span class="text-danger">*</span>
                    </label>
                    <select id="plIntervalType" name="interval_type" required
                        class="w-full rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3 text-xs text-[var(--text)] outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 transition-all">
                        <option value="days">{{ __('common.Dias') }}</option>
                        <option value="usage_hours">{{ __('common.Horas de uso') }}</option>
                        <option value="cycles">{{ __('common.Ciclos') }}</option>
                    </select>
                </div>

                {{-- Interval Value --}}
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)]">
                        {{ __('common.Valor') }} <span class="text-danger">*</span>
                    </label>
                    <input id="plIntervalValue" name="interval_value" type="number" min="1" step="1" required placeholder="{{ __('common.Ex: 90') }}"
                        class="w-full rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3 text-xs text-[var(--text)] outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 transition-all">
                </div>

                {{-- Description --}}
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)]">
                        {{ __('common.Descrição') }}
                    </label>
                    <input id="plDescription" name="description" type="text"
                        placeholder="{{ __('messages.Ex: Inspeção completa do sistema') }}"
                        class="w-full rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3 text-xs text-[var(--text)] outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 transition-all">
                </div>
            </div>

            {{-- Parts Preview (Selected Items) --}}
            <div>
                <div class="mb-2 flex items-center justify-between">
                    <label class="text-xs font-bold uppercase tracking-wider text-[var(--text-soft)]">
                        {{ __('stock.Peças previstas (deixe em branco se não aplicável)') }}
                    </label>
                    <button type="button" id="plAddPart" class="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-[var(--text)] hover:bg-[var(--surface-2)] transition">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        {{ __('stock.Adicionar peça') }}
                    </button>
                </div>
                <div id="plPartsContainer" class="space-y-2 rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] p-4 min-h-[100px]">
                    <div class="flex items-center justify-center text-xs text-[var(--text-soft)]" id="plNoParts">
                        <svg class="h-5 w-5 mr-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        {{ __('common.Nenhuma peça selecionada') }}
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <label class="inline-flex cursor-pointer items-center gap-2.5">
                    <input id="plActive" name="active" type="checkbox" checked
                        class="h-4 w-4 rounded border-[var(--border)] text-primary focus:ring-primary">
                    <span class="text-xs font-semibold text-[var(--text)]">{{ __('equipment.Plano ativo') }}</span>
                </label>
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-2">
                <button type="submit" id="plSubmit" class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-3 text-xs font-black uppercase tracking-wider transition hover:opacity-90 disabled:opacity-50 shadow-lg shadow-primary/20">
                    {{ __('ui.Guardar Plano') }}
                </button>
                <button type="button" id="plReset" class="inline-flex items-center justify-center rounded-xl border border-[var(--border)] bg-[var(--surface)] px-4 py-3 text-xs font-semibold text-[var(--text)] hover:bg-[var(--surface-2)] transition">
                    {{ __('ui.Cancelar edição') }}
                </button>
                <p id="plMessage" class="text-xs font-medium text-[var(--text-soft)]"></p>
            </div>
        </form>
    </div>
            </div>
        </form>
    </div>

    <x-ui.listing.filter-panel>
        <x-ui.listing.filter-field for="filter_equipment" :label="__('equipment.Equipamento')">
            <select id="filter_equipment" class="w-full rounded-xl border border-(--border) bg-(--surface-2) px-3 py-2.5 text-xs text-(--text) outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                <option value="">{{ __('common.Todos') }}</option>
                @foreach($equipments as $equipment)
                    <option value="{{ $equipment->id }}">{{ $equipment->name }}</option>
                @endforeach
            </select>
        </x-ui.listing.filter-field>
    </x-ui.listing.filter-panel>

    <x-ui.listing.table-card
        table_id="plansTable"
        body_id="plansTableBody"
        :aria_label="__('maintenance_plan.Lista de planos de manutenção')"
        :loading_message="__('ui.A carregar planos...')"
        :columns="5"
    >
        <x-slot:head>
            <tr>
                <th class="px-5 py-4 font-bold">{{ __('maintenance_plan.Plano') }}</th>
                <th class="px-5 py-4 font-bold">{{ __('equipment.Equipamento') }}</th>
                <th class="px-5 py-4 font-bold">{{ __('common.Periodicidade') }}</th>
                <th class="px-5 py-4 font-bold">{{ __('stock.Peças') }}</th>
                <th class="px-5 py-4 font-bold text-right">{{ __('common.Ações') }}</th>
            </tr>
        </x-slot:head>
    </x-ui.listing.table-card>

    <x-ui.listing.pagination />
</x-ui.partials.page-header>
@endsection
