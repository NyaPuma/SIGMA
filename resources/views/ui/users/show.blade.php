@extends('ui.layout')

@section('page_key', 'user-detail')

@php
    // --- Presentation helpers (styling only, no business rules) ---
    $profileName = $targetUser->profile?->name ?? 'user';
    $translatedProfile = [
        'admin' => __('common.Administrador'),
        'technician' => __('common.Técnico'),
        'user' => __('common.Utilizador'),
    ][$profileName] ?? ucfirst($profileName);

    $isActive = (bool) $targetUser->active;

    // --- Avatar: real photo when a file exists, otherwise a generic icon ---
    $hasAvatar = false;
    try {
        $avatarDisk = $targetUser->avatar_disk ?: 'public';
        $hasAvatar = !empty($targetUser->avatar_path)
            && \Illuminate\Support\Facades\Storage::disk($avatarDisk)->exists($targetUser->avatar_path);
    } catch (\Throwable $e) {
        $hasAvatar = false;
    }
    $avatarUrl = $hasAvatar ? \Illuminate\Support\Facades\Storage::url($targetUser->avatar_path) : null;
@endphp

@section('content')
<nav aria-label="{{ __('common.Breadcrumb') }}" class="mb-4">
    <ol class="flex flex-wrap items-center gap-1.5 text-sm">
        <li>
            <a href="{{ route('ui.index') }}" class="font-medium text-[var(--text-soft)] transition-colors hover:text-[var(--text)]">
                {{ __('dashboard.Painel') }}
            </a>
        </li>
        <li aria-hidden="true" class="select-none text-[var(--text-soft)]">/</li>
        <li>
            <a href="{{ route('ui.users') }}" class="font-medium text-[var(--text-soft)] transition-colors hover:text-[var(--text)]">
                {{ __('common.Utilizadores') }}
            </a>
        </li>
        <li aria-hidden="true" class="select-none text-[var(--text-soft)]">/</li>
        <li aria-current="page" class="font-semibold text-[var(--text)]">
            {{ $targetUser->name }}
        </li>
    </ol>
</nav>

<x-ui.partials.page-header
    :title="__('common.Perfil do Utilizador')"
    :subtitle="$targetUser->name . ' · ' . $targetUser->email"
    :badge="'#' . $targetUser->id"
>
    <x-slot:actions>
        <x-ui.page-actions.group>
            <x-ui.page-actions.back-button href="{{ route('ui.users') }}" :label="__('common.Utilizadores')" />
        </x-ui.page-actions.group>
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-2 items-start">
        <div class="space-y-6">
            <form
                id="editUserForm"
                class="space-y-6"
                enctype="multipart/form-data"
                data-user-mode="edit"
                data-user-id="{{ $targetUser->id }}"
                data-profile-id="{{ $targetUser->profile_id }}"
            >

                {{-- Informações Pessoais (profile design) --}}
                <x-ui.form.card
                    :title="__('common.Informações Pessoais')"
                    :description="__('common.Atualize os seus dados pessoais.')"
                    icon='<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>'
                >
                    <div class="space-y-4">
                        {{-- Avatar / Profile Picture --}}
                        <div class="flex flex-col sm:flex-row items-center gap-6 p-4 bg-[var(--surface-2)] rounded-2xl border border-[var(--border)]">
                            <div class="h-20 w-20 rounded-2xl overflow-hidden border-2 border-primary/30 shadow-md bg-[var(--surface)] flex-shrink-0 flex items-center justify-center">
                            @if($avatarUrl)
                                <img id="avatarPreview" src="{{ $avatarUrl }}" alt="{{ __('ticket_media.Fotografia do Utilizador') }}" class="h-full w-full object-cover">
                            @else
                                <svg class="h-10 w-10 text-[var(--text-soft)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            @endif
                        </div>

                            <div class="space-y-2 text-center sm:text-left">
                                <h3 class="text-sm font-bold text-[var(--text)]">{{ __('ticket_media.Fotografia do Utilizador') }}</h3>
                                <p class="text-xs text-[var(--text-soft)]">{{ __('ticket_media.Carregue uma imagem (PNG, JPG ou WEBP até 2MB).') }}</p>

                                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-3 pt-1">
                                    <label for="avatarInput" class="cursor-pointer px-4 py-2 bg-primary/10 hover:bg-primary/20 text-primary font-bold text-xs rounded-xl border border-primary/30 transition shadow-sm inline-flex items-center gap-1.5">
                                        {{ __('ticket_media.Escolher Fotografia') }}
                                    </label>
                                    <input type="file" id="avatarInput" name="avatar" accept="image/*" class="hidden">
                                    <span id="avatarFileName" class="text-xs text-[var(--text-soft)] truncate max-w-44">{{ __('ticket_media.Nenhum ficheiro selecionado') }}</span>
                                </div>
                            </div>
                        </div>

                        <x-ui.auth.text-field
                            id="userName"
                            name="name"
                            :label="__('common.Nome Completo')"
                            type="text"
                            :value="$targetUser->name"
                            :required="true"
                            :placeholder="__('common.Ex.: João Silva')"
                            class="py-3"
                        />

                        <x-ui.form.field :id="'userEmail'" :label="__('common.Endereço de Email')">
                            <x-ui.form.input
                                id="userEmail"
                                name="email"
                                type="email"
                                :value="$targetUser->email"
                                :placeholder="__('common.Ex.: joao@empresa.pt')"
                                required
                                class="py-3"
                            />
                        </x-ui.form.field>
                    </div>
                </x-ui.form.card>

                {{-- Perfil de Acesso & Estado da Conta (edit-user options) --}}
                <x-ui.form.card
                    :title="__('common.Perfil de Acesso')"
                    icon='<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z"/></svg>'
                >
                    <div class="space-y-4">
                        <x-ui.form.field :id="'userProfileId'" :label="__('common.Perfil de Acesso')">
                            <select id="userProfileId" name="profile_id" required disabled class="w-full rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3 text-sm text-[var(--text)] outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 disabled:opacity-60 disabled:cursor-not-allowed">
                                <option value="">{{ __('ui.A carregar perfis...') }}</option>
                            </select>
                        </x-ui.form.field>

                        <x-ui.form.field :label="__('common.Estado da Conta')">
                            <div class="mt-1 flex items-center gap-3">
                                <input type="checkbox" id="userActive" name="active" value="1" {{ $isActive ? 'checked' : '' }} class="h-4 w-4 rounded border-[var(--border)] text-primary focus:ring-primary">
                                <label for="userActive" class="text-sm font-semibold text-[var(--text)]">{{ __('auth.Conta ativa (permite login)') }}</label>
                            </div>
                        </x-ui.form.field>
                    </div>
                </x-ui.form.card>

                {{-- Segurança & Palavra-passe (profile design) --}}
                <x-ui.form.card
                    :title="__('auth.Segurança & Palavra-passe')"
                    :description="__('auth.Defina uma palavra-passe forte para proteger a sua conta.')"
                    icon='<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>'
                >
<div x-data="passwordStrength()" class="space-y-4">
                        <x-ui.form.field :id="'userPassword'" :label="__('auth.Nova Palavra-passe (deixar em branco para manter)')">
                        <x-ui.form.input
                            id="userPassword"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            x-model="password"
                            :placeholder="__('stock.Mínimo 8 caracteres')"
                            class="py-3"
                        />

                        <ul class="mt-3 space-y-1.5" aria-label="{{ __('auth.Requisitos da palavra-passe') }}">
                            <li :class="lengthOk ? 'text-success' : 'text-(--text-muted)'" class="flex items-center gap-2 text-xs">
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                {{ __('stock.Mínimo de 8 caracteres') }}
                            </li>
                            <li :class="caseOk ? 'text-success' : 'text-(--text-muted)'" class="flex items-center gap-2 text-xs">
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                {{ __('common.Pelo menos 1 letra maiúscula e 1 letra minúscula') }}
                            </li>
                            <li :class="symbolNumberOk ? 'text-success' : 'text-(--text-muted)'" class="flex items-center gap-2 text-xs">
                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                {{ __('common.Pelo menos 1 símbolo ou número') }}
                            </li>
                        </ul>

                        <div class="mt-3">
                            <div class="flex items-center gap-3">
                                <div class="flex flex-1 gap-1.5" role="progressbar" aria-valuemin="0" aria-valuemax="3" :aria-valuenow="score">
                                    <span :class="[score >= 1 ? barColor : 'bg-[var(--border)]', 'h-1.5 flex-1 rounded-full transition-colors']" aria-hidden="true"></span>
                                    <span :class="[score >= 2 ? barColor : 'bg-[var(--border)]', 'h-1.5 flex-1 rounded-full transition-colors']" aria-hidden="true"></span>
                                    <span :class="[score >= 3 ? barColor : 'bg-[var(--border)]', 'h-1.5 flex-1 rounded-full transition-colors']" aria-hidden="true"></span>
                                </div>
                                <span x-show="score > 0" x-cloak x-text="levelLabel" :class="[score > 0 ? levelClass : 'text-(--text-muted)', 'min-w-12 text-right text-xs font-medium']"></span>
                            </div>
                        </div>
                    </x-ui.form.field>
                </div>

                <x-ui.form.message id="formMessage" />

                <div class="pt-2 flex flex-wrap gap-3">
                    <x-ui.buttons.submit id="submitBtn" variant="primary" size="md" weight="semibold" class="rounded-2xl disabled:cursor-not-allowed disabled:opacity-50">
                        {{ __('ui.Guardar Alterações') }}
                    </x-ui.buttons.submit>
                    <a href="{{ route('ui.users') }}" class="ui-button ui-button--outline inline-flex items-center justify-center rounded-2xl border border-[var(--border)] bg-[var(--surface)] px-5 py-3 text-sm font-semibold text-[var(--text)] transition hover:bg-[var(--surface-2)]">
                        {{ __('ui.Cancelar') }}
                    </a>
                </div>
            </x-ui.form.card>
            </form>
        </div>

        <div class="space-y-6">
            {{-- Summary (profile design) --}}
            <section class="rounded-3xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary text-lg font-black text-[var(--on-primary)]" aria-hidden="true">
                        {{ strtoupper(substr($targetUser->name ?? 'U', 0, 1)) }}
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-[var(--text)]">{{ $targetUser->name ?? __('common.utilizador') }}</h2>
                        <p class="text-sm text-[var(--text-soft)]">{{ $targetUser->email }}</p>
                        <x-ui.text.pill tone="primary" size="xs" class="mt-2">
                            {{ $translatedProfile }}
                        </x-ui.text.pill>
                    </div>
                </div>

                <div class="mt-8 space-y-4 rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] p-4 text-sm text-[var(--text-soft)]">
                    <div class="flex items-center justify-between border-b border-[var(--border)] pb-3">
                        <span>{{ __('common.Estado') }}</span>
                        <span class="font-semibold text-[var(--text)]">{{ $isActive ? __('equipment.Ativo') : __('equipment.Inativo') }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-[var(--border)] pb-3">
                        <span>{{ __('common.Última atualização') }}</span>
                        <span class="font-semibold text-[var(--text)]">{{ app(\App\Services\LocalizationService::class)->formatDateTime($targetUser->updated_at) ?: '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>{{ __('common.Membro desde') }}</span>
                        <span class="font-semibold text-[var(--text)]">{{ app(\App\Services\LocalizationService::class)->formatDate($targetUser->created_at) ?: '—' }}</span>
                    </div>
                </div>
            </section>

            {{-- Danger zone (profile design) --}}
            <x-ui.form.card
                tone="danger"
                :title="__('common.Zona de Perigo')"
                :description="__('ui.A eliminação da conta é irreversível e remove todos os dados associados.')"
                icon-variant="danger"
                icon='<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>'
            >
                <div x-data="{ open: false }">
                    <x-ui.buttons.button
                        type="button"
                        @click="open = true"
                        variant="danger"
                        size="md"
                        weight="semibold"
                    >
                        {{ __('ui.Eliminar') }}
                    </x-ui.buttons.button>

                    <div
                        x-show="open"
                        x-cloak
                        @keydown.escape.window="open = false"
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="delete-user-modal-title"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
                    >
                        <div class="w-full max-w-md rounded-2xl border border-danger/20 bg-[var(--surface)] p-6 shadow-xl">
                            <h3 id="delete-user-modal-title" class="text-lg font-bold text-[var(--text)]">{{ __('ui.Eliminar') }}</h3>
                            <p class="mt-3 text-sm leading-6 text-[var(--text-soft)]">
                                {{ __('ui.A eliminação da conta é irreversível e remove todos os dados associados.') }}
                                <strong class="text-[var(--text)]">{{ $targetUser->name }}</strong>
                            </p>

                            <div class="mt-6 flex items-center justify-end gap-3">
                                <x-ui.buttons.button type="button" @click="open = false" variant="secondary" size="md" weight="semibold">
                                    {{ __('ui.Cancelar') }}
                                </x-ui.buttons.button>

                                <form method="POST" action="{{ route('admin.users.destroy', $targetUser) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.buttons.button type="submit" variant="danger" size="md" weight="semibold">
                                        {{ __('ui.Eliminar') }}
                                    </x-ui.buttons.button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </x-ui.form.card>
        </div>
    </div>
</x-ui.partials.page-header>
@endsection