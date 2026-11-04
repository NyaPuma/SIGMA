{{--
|--------------------------------------------------------------------------
| Date Format Selection Modal
|--------------------------------------------------------------------------
| Modal to select only the date format, independent of language and currency
--}}

@php
    $supportedDateFormats = \App\Services\PreferencesService::supportedDateFormats();
    $currentDateFormat = \App\Services\PreferencesService::getDateFormat(request());
    $preferences = \App\Services\PreferencesService::current(request);
    
    // Friendly names for formats
    $formatNames = [
        'd/m/Y' => '31/12/2024 (Dia/Mês/Ano)',
        'm/d/Y' => '12/31/2024 (Mês/Dia/Ano)',
        'Y-m-d' => '2024-12-31 (Ano-Mês-Dia)',
        'd-m-Y' => '31-12-2024 (Dia-Mês-Ano)',
        'Y/m/d' => '2024/12/31 (Ano/Mês/Dia)',
        'd.m.Y' => '31.12.2024 (Dia.Mês.Ano)',
        'm-d-Y' => '12-31-2024 (Mês-Dia-Ano)',
    ];
    
    // Sample date for preview
    $sampleDate = now()->format('Y-m-d');
@endphp

<div id="dateFormatModal"
     class="preferences-modal"
     role="dialog"
     aria-modal="true"
     aria-labelledby="dateFormatModalTitle"
     tabindex="-1"
     hidden>

    {{-- Overlay --}}
    <div class="preferences-modal__overlay" data-dateformat-modal-close></div>

    {{-- Container --}}
    <div class="preferences-modal__container">

        {{-- Header --}}
        <div class="preferences-modal__header">
            <div class="preferences-modal__header-row">
                <h2 id="dateFormatModalTitle" class="preferences-modal__title">
                    <span class="preferences-modal__title-icon"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg></span>
                    {{ __('preferences.Selecionar Formato de Data') }}
                </h2>
                <button type="button"
                        class="preferences-modal__close-btn"
                        data-dateformat-modal-close
                        aria-label="{{ __('ui.Fechar') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Current selection info --}}
            <div class="preferences-modal__info">
                <p>{{ __('ui.Formato atual') }}: <strong>{{ $currentDateFormat }}</strong></p>
                <p>{{ __('preferences.Língua atual') }}: <strong>{{ $preferences['language'] }}</strong> | {{ __('preferences.Moeda atual') }}: <strong>{{ $preferences['currency'] }}</strong></p>
                <p class="mt-2 text-sm text-(--text-muted)">{{ __('ui.Exemplo com data de hoje') }}: <strong>{{ now()->format($currentDateFormat) }}</strong></p>
            </div>
        </div>

        {{-- Body (scrollable) --}}
        <div class="preferences-modal__body" id="dateFormatModalBody">
            <div class="preferences-modal__section">
                <div class="preferences-modal__grid date-format-grid">
                    @foreach ($supportedDateFormats as $format)
                        @php
                            $formattedDate = \Carbon\Carbon::parse($sampleDate)->format($format);
                        @endphp
                        <button type="button"
                                class="preferences-modal__card dateformat-card{{ $format === $currentDateFormat ? ' preferences-modal__card--active' : '' }}"
                                data-date-format="{{ $format }}"
                                data-search="{{ strtolower($formatNames[$format] ?? $format) }}"
                                aria-label="{{ $formatNames[$format] ?? $format }}">
                            <span class="preferences-modal__format-example">{{ $formattedDate }}</span>
                            <div class="preferences-modal__info">
                                <span class="preferences-modal__name">{{ $format }}</span>
                                <span class="preferences-modal__description">{{ $formatNames[$format] ?? $format }}</span>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Preview --}}
        <div class="preferences-modal__preview" id="dateFormatPreview">
            <span class="preferences-modal__preview-default" id="dateFormatPreviewDefault">
                {{ __('preferences.Seleccione um formato para pré-visualizar') }}
            </span>
        </div>

    </div>{{-- /.preferences-modal__container --}}

    {{-- Hidden form for date format switching --}}
    <form id="dateFormatForm" class="preferences-modal__form" method="POST" action="{{ route('preferences.date-format') }}">
        @csrf
        <input type="hidden" name="date_format" id="dateFormatFormInput" value="">
    </form>

</div>{{-- /.date-format-modal --}}
