<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Services\LocaleService::isRtl(app()->getLocale()) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('common.Comunicar Avaria') }} — {{ __('common.Gestão de Avarias') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">

    @vite('resources/js/early-theme.js')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ route('theme.custom') }}?v={{ \App\Http\Controllers\ThemeController::cacheBuster(request()->user()?->theme) }}">

    @include('ui.partials.theme-meta')
    @include('ui.partials.locale-config')
</head>
<body class="min-h-screen overflow-x-hidden bg-[var(--bg)] text-[var(--text)] antialiased{{ \App\Services\LocaleService::isRtl(app()->getLocale()) ? ' rtl' : '' }}">
    <div class="locale-trigger-wrapper fixed right-4 top-4 z-40">
        @include('ui.partials.locale-trigger')
    </div>
    {{-- Background Visual Effects (Glow) --}}
    <div class="pointer-events-none fixed inset-0 -z-10" aria-hidden="true">
        <div class="absolute inset-0 bg-[var(--bg)]"></div>
        <div class="absolute left-1/2 top-0 h-[600px] w-[600px] -translate-x-1/2 rounded-full bg-primary/10 blur-[160px]"></div>
        <div class="absolute bottom-0 right-0 h-[500px] w-[500px] rounded-full bg-info/10 blur-[160px]"></div>
    </div>

    <div class="min-h-screen px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-2xl">

            {{-- Header --}}
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl border border-[var(--border)] bg-[var(--surface-2)]">
                        <svg class="h-5 w-5 shrink-0 text-[var(--text-soft)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z"/></svg>
                    </span>
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.24em] text-[var(--text-soft)]">{{ __('common.Gestão de Avarias') }}</p>
                        <h1 class="text-xl font-black tracking-tight text-[var(--text)]">{{ __('common.Comunicar Avaria') }}</h1>
                    </div>
                </div>
            </div>

            <div class="ui-card overflow-hidden rounded-[32px] border border-[var(--border)] bg-[var(--surface)] shadow-2xl shadow-black/10 backdrop-blur-xl">

                {{-- Equipment Information --}}
                <div class="border-b border-[var(--border)] bg-[var(--surface-2)]/60 p-6">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-bold text-[var(--text)]">
                            {{ $equipment->category?->name ?? __('equipment.Equipamento') }}
                        </span>
                        @if($equipment->room)
                            <span class="inline-flex items-center gap-1.5 rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-semibold text-[var(--text-soft)]">
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm4.5 0a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z"/></svg>
                                {{ $equipment->room->name }}
                            </span>
                        @endif
                        @if($equipment->asset_tag)
                            <span class="ml-auto inline-flex items-center rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 font-mono text-xs font-bold text-[var(--text-soft)]">
                                {{ $equipment->asset_tag }}
                            </span>
                        @endif
                    </div>
                    <h2 class="mt-4 text-lg font-black tracking-tight text-[var(--text)]">{{ $equipment->name }}</h2>
                    <p class="mt-1 text-xs text-[var(--text-soft)]">
                        {{ trim(($equipment->brand ?? '') . ' ' . ($equipment->model ?? '')) ?: __('equipment.Equipamento registado') }}
                    </p>
                </div>

                {{-- Form --}}
                <form method="POST" action="{{ route('ticket.public.store') }}" enctype="multipart/form-data" class="space-y-6 p-6">
                    @csrf
                    <input type="hidden" name="equipment_id" value="{{ $equipment->id }}">

                    @if($errors->any())
                        <div class="rounded-2xl border border-danger/25 bg-danger/10 p-4">
                            <p class="text-xs font-bold uppercase tracking-wider text-danger">{{ __('common.Verifique os campos assinalados.') }}</p>
                        </div>
                    @endif

                    {{-- Problem Type --}}
                    <div>
                        <label for="problem_type" class="mb-2 block text-xs font-bold uppercase tracking-[0.2em] text-[var(--text-soft)]">{{ __('common.Tipo de Problema *') }}</label>
                        <select name="problem_type" id="problem_type" required
                            class="w-full appearance-none rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3.5 text-sm text-[var(--text)] outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/15">
                            <option value="" disabled {{ old('problem_type') === null ? 'selected' : '' }}>{{ __('common.Selecione o tipo de problema...') }}</option>
                            @foreach($problemTypes as $type)
                                <option value="{{ $type->value }}" {{ old('problem_type') === $type->value ? 'selected' : '' }}>
                                    {{ $type->label() }}
                                </option>
                            @endforeach
                        </select>
                        @error('problem_type')
                            <p class="mt-1.5 text-xs font-semibold text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Description --}}
                    <div>
                        <label for="description" class="mb-2 block text-xs font-bold uppercase tracking-[0.2em] text-[var(--text-soft)]">{{ __('common.Descrição do Problema *') }}</label>
                        <textarea name="description" id="description" rows="4" required maxlength="5000" placeholder="{{ __('equipment.Descreva de forma objetiva o que se passa com o equipamento...') }}"
                            class="w-full resize-none rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3.5 text-sm text-[var(--text)] outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/15">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="mt-1.5 text-xs font-semibold text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Photo (Optional) --}}
                    <div>
                        <label for="photo" class="mb-2 block text-xs font-bold uppercase tracking-[0.2em] text-[var(--text-soft)]">{{ __('ticket_media.Fotografia (Opcional)') }}</label>
                        <div class="flex items-center gap-3 rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-2.5">
                            <label for="photo" class="cursor-pointer rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-1.5 text-xs font-semibold text-[var(--text)] transition hover:bg-[var(--surface-2)]">
                                {{ __('ticket_media.Escolher ficheiro') }}
                            </label>
                            <input type="file" name="photo" id="photo" accept="{{ $photoAccept }}" class="hidden">
                            <span id="photoName" class="truncate text-xs text-[var(--text-soft)]" data-placeholder="{{ __('ticket_media.Nenhum ficheiro selecionado') }}">{{ __('ticket_media.Nenhum ficheiro selecionado') }}</span>
                        </div>
                        @error('photo')
                            <p class="mt-1.5 text-xs font-semibold text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Reporter (Optional) --}}
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="reporter_name" class="mb-2 block text-xs font-bold uppercase tracking-[0.2em] text-[var(--text-soft)]">{{ __('common.O Seu Nome (Opcional)') }}</label>
                            <input type="text" name="reporter_name" id="reporter_name" maxlength="150" value="{{ old('reporter_name') }}" placeholder="{{ __('common.Ex.: Ana Silva') }}"
                                class="w-full rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3.5 text-sm text-[var(--text)] outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/15">
                        </div>
                        <div>
                            <label for="reporter_contact" class="mb-2 block text-xs font-bold uppercase tracking-[0.2em] text-[var(--text-soft)]">{{ __('common.Contacto (Opcional)') }}</label>
                            <input type="text" name="reporter_contact" id="reporter_contact" maxlength="150" value="{{ old('reporter_contact') }}" placeholder="{{ __('common.Ex.: 912 345 678') }}"
                                class="w-full rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3.5 text-sm text-[var(--text)] outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/15">
                        </div>
                    </div>

                    {{-- Submit --}}
                    <button type="submit"
                        class="ui-button ui-button--primary inline-flex w-full items-center justify-center gap-2 rounded-2xl px-4 py-4 text-sm font-black shadow-lg shadow-primary/20 transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/30">
                        {{ __('common.Enviar Pedido de Intervenção') }}
                    </button>

                    <p class="text-center text-xs leading-5 text-[var(--text-soft)]">
                        {{ __('common.Ao enviar, receberá um número de referência para acompanhar o estado da intervenção.') }}
                    </p>
                </form>
            </div>
        </div>
    </div>

    @include('ui.partials.localization-modal')
</body>
</html>
