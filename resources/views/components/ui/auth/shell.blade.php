{{--
|--------------------------------------------------------------------------
| Auth / Secure Area Layout Component
|--------------------------------------------------------------------------
|
| Grid-based layout for authentication or restricted-area pages.
| • 100% free of inline CSS or JS.
| • CSS variable syntax corrected and safe for Tailwind.
| • Support for conditional highlights and global body customization via $attributes.
|
--}}

@props([
    'title',
    'description',
    'eyebrow' => __('common.Área segura'),
    'highlights' => [],
])

<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Services\LocaleService::isRtl(app()->getLocale()) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title === __('common.Gestão de Avarias') ? $title : $title.' — '.__('common.Gestão de Avarias') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">

    @vite('resources/js/early-theme.js')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ route('theme.custom') }}?v={{ \App\Http\Controllers\ThemeController::cacheBuster(request()->user()?->theme) }}">

    @include('ui.partials.theme-meta')

    @include('ui.partials.locale-config')
    <script>
        (function () {
            var saved = localStorage.getItem('theme');
            var dark = saved ? saved === 'dark' : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
</head>
<body {{ $attributes->merge(['class' => 'min-h-screen overflow-x-hidden bg-[var(--bg)] text-[var(--text)] antialiased' . (\App\Services\LocaleService::isRtl(app()->getLocale()) ? ' rtl' : '')]) }}>
    <div class="locale-trigger-wrapper fixed right-4 top-4 z-40">
        @include('ui.partials.locale-trigger')
    </div>
    {{-- Background Visual Effects (Glow) --}}
    <div class="pointer-events-none fixed inset-0 -z-10" aria-hidden="true">
        <div class="absolute inset-0 bg-[var(--bg)]"></div>
        <div class="absolute left-1/2 top-0 h-[900px] w-[900px] -translate-x-1/2 rounded-full bg-primary/10 blur-[180px]"></div>
        <div class="absolute bottom-0 right-0 h-[600px] w-[600px] rounded-full bg-info/10 blur-[180px]"></div>
    </div>

    <main class="min-h-screen bg-[var(--bg)] px-4 py-6 text-[var(--text)] antialiased sm:px-6 lg:px-8">
        <div class="mx-auto flex min-h-[calc(100vh-3rem)] max-w-5xl items-center justify-center">
            <div class="ui-card w-full overflow-hidden rounded-[32px] border border-[var(--border)] bg-[var(--surface)] shadow-2xl shadow-black/10 backdrop-blur-xl">
                <div class="grid min-h-[720px] lg:grid-cols-[0.95fr_1.05fr]">
                    {{-- Informational Side Panel --}}
                    <div class="flex flex-col justify-between bg-[var(--surface-2)]/70 p-8 lg:p-10">
                        <div>
                            @if($eyebrow)
                                <x-ui.text.pill tone="primary" size="sm" class="gap-3 tracking-[0.24em]">
                                    <span class="h-2 w-2 rounded-full bg-primary" aria-hidden="true"></span>
                                    {{ $eyebrow }}
                                </x-ui.text.pill>
                            @endif

                            @if($title)
                                <h1 class="mt-8 text-3xl font-black tracking-tight text-[var(--text)] sm:text-4xl">
                                    {{ $title }}
                                </h1>
                            @endif

                            @if($description)
                                <p class="mt-4 max-w-md text-sm leading-7 text-[var(--text-soft)] sm:text-base">
                                    {{ $description }}
                                </p>
                            @endif
                        </div>

                        @if(!empty($highlights))
                            <div class="mt-10 space-y-4">
                                @foreach($highlights as $highlight)
                                    <div class="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-4">
                                        @if(isset($highlight['title']))
                                            <p class="text-sm font-semibold text-[var(--text)]">{{ $highlight['title'] }}</p>
                                        @endif

                                        @if(isset($highlight['description']))
                                            <p class="mt-2 text-sm leading-7 text-[var(--text-soft)]">{{ $highlight['description'] }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Main Content Panel (Form/Action Slot) --}}
                    <div class="flex items-center justify-center p-6 sm:p-8 lg:p-10">
                        <div class="w-full max-w-md">
                            {{ $slot }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    @include('ui.partials.localization-modal')
</body>
</html>
