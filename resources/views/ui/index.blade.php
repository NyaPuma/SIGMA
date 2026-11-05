@extends('ui.layout')

@section('page_key', 'dashboard')

@php
    $profileName = $user->profile->name ?? 'user';
    $isAdmin = $profileName === 'admin';
    $isOperations = $isAdmin || $profileName === 'technician';

    $profileLabel = match ($profileName) {
        'admin' => __('common.Administrador'),
        'technician' => __('common.Técnico'),
        default => __('common.Funcionário'),
    };
@endphp

@section('content')
    <div class="space-y-6">
        <x-ui.dashboard.welcome-panel
            :userName="$user->name ?? __('common.utilizador')"
            :profileLabel="$profileLabel"
        />

        @if($isAdmin)
            <div id="metricsPanel" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4"></div>
        @else
            <div class="rounded-2xl border border-dashed border-(--border) bg-(--surface-2) p-5 text-center">
                <p class="text-xs text-(--text-soft)">{{ __('dashboard.Métricas disponíveis apenas para Administrador.') }}</p>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-2xl border border-(--border) bg-(--surface) p-6 shadow-[0_1px_2px_rgba(0,0,0,0.01)] flex flex-col justify-between">
            <div>
                <div class="mb-4 flex items-center justify-between border-b border-(--border) pb-3">
                    <div>
                        <x-ui.text.eyebrow as="p" size="xs" tracking="widest" class="font-bold">{{ __('dashboard.Atividade Recente') }}</x-ui.text.eyebrow>
                        <h2 class="text-base font-bold text-(--text)">{{ __('tickets.Últimas Ocorrências Registadas') }}</h2>
                    </div>
                    <a href="/ui/tickets" class="text-xs font-bold text-primary hover:underline flex items-center gap-1">
                        {{ __('common.Ver todos') }} &rarr;
                    </a>
                </div>

                <div id="recentTicketsTable" class="overflow-x-auto">
                    <div class="text-xs text-(--text-soft) animate-pulse py-4">
                        {{ __('tickets.A carregar fluxo de ocorrências...') }}
                    </div>
                </div>
            </div>
        </div>

        @if($isOperations)
        <div class="rounded-2xl border border-(--border) bg-(--surface) p-6 shadow-[0_1px_2px_rgba(0,0,0,0.01)] flex flex-col justify-between">
            <div>
                <div class="mb-4 border-b border-(--border) pb-3">
                    <x-ui.text.eyebrow as="p" size="xs" tracking="widest" class="font-bold">{{ __('common.Operações') }}</x-ui.text.eyebrow>
                    <h2 class="text-base font-bold text-(--text)">{{ __('equipment.Piquete Técnico Ativo') }}</h2>
                </div>

                <div id="picketList" class="space-y-3 mt-2"></div>
            </div>

            @if($isAdmin)
                <div class="mt-6 pt-4 border-t border-(--border)">
                    <a href="/ui/users" class="w-full inline-flex items-center justify-center bg-(--surface-2) hover:bg-(--border) text-(--text) border border-(--border) text-xs font-bold py-2.5 px-4 rounded-xl transition">
                        {{ __('common.Gerir Equipas & Piquetes') }}
                    </a>
                </div>
            @endif
        </div>
        @endif
    </div>
    </div>
@endsection
