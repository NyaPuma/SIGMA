{{--
|--------------------------------------------------------------------------
| Item Selector Modal
|--------------------------------------------------------------------------
| Reusable modal for selecting items (parts, equipment, etc.)
| Used in: stock movements, maintenance plans, budget add items
--}}

<div id="itemSelectorModal" class="item-selector-modal" role="dialog" aria-modal="true" aria-labelledby="itemSelectorModalTitle" tabindex="-1" hidden>

    {{-- Overlay --}}
    <div class="item-selector-modal__overlay" data-item-selector-close></div>

    {{-- Container --}}
    <div class="item-selector-modal__container">

        {{-- Header --}}
        <div class="item-selector-modal__header">
            <div class="item-selector-modal__header-row">
                <h2 id="itemSelectorModalTitle" class="item-selector-modal__title">
                    <svg class="item-selector-modal__title-icon h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                    </svg>
                    {{ __('ui.Selecionar Materiais (Stock)') }}
                </h2>
                <button type="button" class="item-selector-modal__close-btn" data-item-selector-close aria-label="{{ __('ui.Fechar') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Search --}}
            <div class="item-selector-modal__search">
                <svg class="item-selector-modal__search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                </svg>
                <input type="search"
                       id="itemSelectorSearchInput"
                       class="item-selector-modal__search-input"
                       placeholder="{{ __('ui.Pesquisar') }}"
                       aria-label="{{ __('ui.Pesquisar') }}"
                       autocomplete="off">
            </div>
        </div>

        {{-- Body (scrollable) --}}
        <div class="item-selector-modal__body" id="itemSelectorModalBody">
            {{-- Content will be injected here via JavaScript --}}

            {{-- Sample Item Row Template (hidden) --}}
            <template id="itemSelectorRowTemplate">
                <div class="item-selector-modal__row" data-item-id="">
                    <div class="item-selector-modal__row-main">
                        <span class="item-selector-modal__row-name"></span>
                        <span class="item-selector-modal__row-ref"></span>
                    </div>
                    <div class="item-selector-modal__row-meta">
                        <span class="item-selector-modal__row-qty"></span>
                    </div>
                    <div class="item-selector-modal__row-price">
                        <span class="item-selector-modal__row-price-value"></span>
                    </div>
                    <button type="button" class="item-selector-modal__row-select">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </button>
                </div>
            </template>
        </div>

        {{-- Footer / Summary --}}
        <div class="item-selector-modal__footer">
            <div class="item-selector-modal__summary">
                <span class="item-selector-modal__summary-label">{{ __('ui.Materiais (Stock)') }}</span>
                <span class="item-selector-modal__summary-count" id="itemSelectorSummaryCount">0 {{ __('ui.item(s) selecionado(s)') }}</span>
            </div>
            <button type="button" class="item-selector-modal__confirm-btn" id="itemSelectorConfirmBtn">
                {{ __('ui.Confirmar') }}
            </button>
        </div>

    </div>{{-- /.item-selector-modal__container --}}
</div>
