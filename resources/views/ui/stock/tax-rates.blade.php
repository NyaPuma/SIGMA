@extends('ui.layout')

@section('page_key', 'stock-tax-rates')

@php($taxLabel = \App\Services\LocaleService::indirectTaxLabel())

@section('content')
<x-ui.partials.page-header
    :title="str_replace('IVA', $taxLabel, __('common.Taxas de IVA'))"
    :subtitle="__('stock.Configure as taxas de IVA aplicáveis ao preço das peças.')"
>
    <x-slot:actions>
        <x-ui.page-actions.group>
            <x-ui.page-actions.back-button :href="route('ui.stock.dashboard')" :label="__('stock.Voltar ao Stock')" />
        </x-ui.page-actions.group>
    </x-slot:actions>

    {{-- Create / edit form --}}
    <div class="mb-6 rounded-2xl border border-(--border) bg-(--surface) p-5 shadow-sm">
        <h2 id="taxRateFormTitle" class="mb-4 text-xs font-black uppercase tracking-wider text-(--text)">{{ str_replace('IVA', $taxLabel, __('common.Nova taxa de IVA')) }}</h2>
        <form id="taxRateForm" class="grid gap-4 md:grid-cols-2 lg:grid-cols-5" novalidate data-tax-rate-form-mode="create" data-tax-rate-id="">
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-(--text-soft)" for="trName">
                    {{ __('common.Nome') }} <span class="text-danger">*</span>
                </label>
                <input id="trName" name="name" type="text" required placeholder="{{ str_replace('IVA', $taxLabel, __('common.Ex: IVA normal')) }}"
                    class="w-full rounded-xl border border-(--border) bg-(--surface-2) px-3 py-2.5 text-xs text-(--text) placeholder-(--text-soft) outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-(--text-soft)" for="trPercent">
                    {{ __('common.Percentagem') }} <span class="text-danger">*</span>
                </label>
                <input id="trPercent" name="percent" type="number" step="0.01" min="0" max="100" required placeholder="{{ __('common.Ex: 23') }}"
                    class="w-full rounded-xl border border-(--border) bg-(--surface-2) px-3 py-2.5 text-xs text-(--text) placeholder-(--text-soft) outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
            </div>
            <div class="flex items-center">
                <label class="inline-flex cursor-pointer items-center gap-2.5 pt-6">
                    <input id="trDefault" name="is_default" type="checkbox"
                        class="h-4 w-4 rounded border-(--border) text-primary focus:ring-primary">
                    <span class="text-xs font-semibold text-(--text)">{{ __('common.Taxa padrão') }}</span>
                </label>
            </div>
            <div class="flex items-center">
                <label class="inline-flex cursor-pointer items-center gap-2.5 pt-6">
                    <input id="trActive" name="active" type="checkbox" checked
                        class="h-4 w-4 rounded border-(--border) text-primary focus:ring-primary">
                    <span class="text-xs font-semibold text-(--text)">{{ __('common.Ativa') }}</span>
                </label>
            </div>
            <div class="flex items-end gap-3">
                <button type="submit" id="trSubmit" class="inline-flex items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-xs font-semibold text-white transition hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ __('ui.Guardar') }}
                </button>
                <button type="button" id="trReset" class="inline-flex items-center justify-center rounded-xl border border-(--border) bg-(--surface-2) px-4 py-2.5 text-xs font-semibold text-(--text) transition hover:bg-(--border)">
                    {{ __('ui.Cancelar edição') }}
                </button>
                <p id="trMessage" class="text-xs font-medium text-(--text-soft)"></p>
            </div>
        </form>
    </div>

    {{-- Tax rates table --}}
    <div class="overflow-hidden rounded-2xl border border-(--border) bg-(--surface) shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-(--border) bg-(--surface-2)">
                <tr>
                    <th class="px-5 py-4 text-xs font-black uppercase tracking-wider text-(--text)">{{ __('common.Nome') }}</th>
                    <th class="px-5 py-4 text-xs font-black uppercase tracking-wider text-(--text)">{{ __('common.Percentagem') }}</th>
                    <th class="px-5 py-4 text-xs font-black uppercase tracking-wider text-(--text)">{{ __('common.Padrão') }}</th>
                    <th class="px-5 py-4 text-xs font-black uppercase tracking-wider text-(--text)">{{ __('common.Estado') }}</th>
                    <th class="px-5 py-4 text-xs font-black uppercase tracking-wider text-(--text) text-right">{{ __('common.Ações') }}</th>
                </tr>
            </thead>
            <tbody id="taxRatesBody" class="divide-y divide-(--border)">
                @foreach($taxRates as $taxRate)
                    <tr class="transition-colors hover:bg-(--surface-2)">
                        <td class="px-5 py-4 text-xs font-bold text-(--text)">{{ $taxRate->name }}</td>
                        <td class="px-5 py-4 text-xs font-semibold text-(--text)">{{ app(\App\Services\LocalizationService::class)->formatPercent((float) $taxRate->percent) }}</td>
                        <td class="px-5 py-4 text-xs font-semibold text-(--text)">
                            @if($taxRate->is_default)
                                <span class="inline-block rounded-lg bg-primary/10 px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-primary">✓ {{ __('common.Padrão') }}</span>
                            @else
                                <span class="text-(--text-soft)">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-xs font-semibold">
                            @if($taxRate->active)
                                <span class="inline-block rounded-lg bg-success/10 px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-success">{{ __('common.Ativa') }}</span>
                            @else
                                <span class="inline-block rounded-lg bg-(--surface-2) px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-(--text-muted)">{{ __('common.Inativa') }}</span>
                            @endif
                        </td>
            <td class="ui-listing-actions px-5 py-4 text-right">
                <div class="inline-flex items-center justify-end gap-1.5">
                    <button type="button" data-tax-rate-edit="{{ $taxRate->id }}" data-name="{{ $taxRate->name }}" data-percent="{{ $taxRate->percent }}" data-default="{{ $taxRate->is_default ? '1' : '' }}" data-active="{{ $taxRate->active ? '1' : '' }}"
                        class="inline-flex min-h-[28px] items-center justify-center rounded-lg border border-(--border) bg-(--surface) px-3 py-1.5 text-xs font-semibold text-(--text) shadow-sm transition-all hover:bg-(--surface-2)">{{ __('ui.Editar') }}</button>
                    <button type="button" data-tax-rate-delete="{{ $taxRate->id }}"
                        class="inline-flex min-h-[28px] items-center justify-center rounded-lg border border-danger/25 bg-danger/10 px-3 py-1.5 text-xs font-semibold text-danger shadow-sm transition-all hover:bg-danger/20">{{ __('ui.Eliminar') }}</button>
                </div>
            </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-ui.partials.page-header>
@endsection
