@extends('ui.layout')

@section('page_key', 'users-create')

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
            {{ __('ui.Criar Utilizador') }}
        </li>
    </ol>
</nav>

<x-ui.partials.page-header
    :title="__('ui.Criar Utilizador')"
    :subtitle="__('common.Crie um novo perfil de utilizador e defina as suas credenciais e permissões de acesso.')"
>
    <x-slot:actions>
        <x-ui.page-actions.group>
            <x-ui.page-actions.back-button href="{{ route('ui.users') }}" :label="__('common.Utilizadores')" />
        </x-ui.page-actions.group>
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-2 items-start">
        <div class="space-y-6">
            <form
                id="createUserForm"
                class="space-y-6"
                enctype="multipart/form-data"
                data-user-mode="create"
            >

                {{-- Informações Pessoais --}}
                <x-ui.form.card
                    :title="__('common.Informações Pessoais')"
                    :description="__('common.Atualize os seus dados pessoais.')"
                    icon='<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>'
                >
                    <div class="space-y-4">
                        {{-- Avatar / Profile Picture (visual, optional) --}}
                        <div class="flex flex-col sm:flex-row items-center gap-6 p-4 bg-[var(--surface-2)] rounded-2xl border border-[var(--border)]">
                            <div class="h-20 w-20 rounded-2xl overflow-hidden border-2 border-primary/30 shadow-md bg-[var(--surface)] flex-shrink-0 flex items-center justify-center">
                                <svg class="h-10 w-10 text-[var(--text-soft)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
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
                            :required="true"
                            :placeholder="__('common.Ex.: João Silva')"
                            class="py-3"
                        />

                        <x-ui.form.field :id="'userEmail'" :label="__('common.Endereço de Email')">
                            <x-ui.form.input
                                id="userEmail"
                                name="email"
                                type="email"
                                :placeholder="__('common.Ex.: joao@empresa.pt')"
                                required
                                class="py-3"
                            />
                        </x-ui.form.field>
                    </div>
                </x-ui.form.card>

                {{-- Perfil de Acesso & Estado da Conta --}}
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
                                <input type="checkbox" id="userActive" name="active" value="1" checked class="h-4 w-4 rounded border-[var(--border)] text-primary focus:ring-primary">
                                <label for="userActive" class="text-sm font-semibold text-[var(--text)]">{{ __('auth.Conta ativa (permite login)') }}</label>
                            </div>
                        </x-ui.form.field>
                    </div>
                </x-ui.form.card>

                {{-- Segurança & Palavra-passe --}}
                <x-ui.form.card
                    :title="__('auth.Segurança & Palavra-passe')"
                    :description="__('auth.Defina uma palavra-passe forte para proteger a sua conta.')"
                    icon='<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>'
                >
                    <div x-data="passwordStrength()" class="space-y-4">
                        <x-ui.form.field :id="'userPassword'" :label="__('auth.Palavra-passe')">
                            <x-ui.form.input
                                id="userPassword"
                                name="password"
                                type="password"
                                autocomplete="new-password"
                                x-model="password"
                                minlength="8"
                                :required="true"
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

                        <x-ui.form.field :id="'userPasswordConfirmation'" :label="__('auth.Confirmar palavra-passe')">
                            <x-ui.form.input
                                id="userPasswordConfirmation"
                                name="password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                placeholder="••••••••"
                                class="py-3"
                            />
                        </x-ui.form.field>
                    </div>

                    <x-ui.form.message id="formMessage" />

                    <div class="pt-2 flex flex-wrap gap-3">
                        <x-ui.buttons.submit id="submitBtn" variant="primary" size="md" weight="semibold" class="rounded-2xl disabled:cursor-not-allowed disabled:opacity-50">
                            {{ __('ui.Criar Utilizador') }}
                        </x-ui.buttons.submit>
                        <a href="{{ route('ui.users') }}" class="ui-button ui-button--outline inline-flex items-center justify-center rounded-2xl border border-[var(--border)] bg-[var(--surface)] px-5 py-3 text-sm font-semibold text-[var(--text)] transition hover:bg-[var(--surface-2)]">
                            {{ __('ui.Cancelar') }}
                        </a>
                    </div>
                </x-ui.form.card>
            </form>
        </div>

        <div class="space-y-6">
            {{-- Sobre os Perfis de Acesso (informational) --}}
            <x-ui.form.card
                :title="__('common.Sobre os Perfis de Acesso')"
                :description="__('common.Escolha o perfil que define as permissões de acesso do novo utilizador.')"
                icon='<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>'
            >
                <ul class="space-y-3">
                    <li class="flex items-start gap-3 rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] p-4">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z"/></svg>
                        <div>
                            <p class="text-sm font-bold text-[var(--text)]">{{ __('common.Administrador') }}</p>
                            <p class="mt-0.5 text-xs leading-5 text-[var(--text-soft)]">{{ __('common.Acesso total à administração, gestão de utilizadores e definições do sistema.') }}</p>
                        </div>
                    </li>

                    <li class="flex items-start gap-3 rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] p-4">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085"/></svg>
                        <div>
                            <p class="text-sm font-bold text-[var(--text)]">{{ __('common.Técnico') }}</p>
                            <p class="mt-0.5 text-xs leading-5 text-[var(--text-soft)]">{{ __('common.Acesso à gestão operacional e atividades de manutenção.') }}</p>
                        </div>
                    </li>

                    <li class="flex items-start gap-3 rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] p-4">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <div>
                            <p class="text-sm font-bold text-[var(--text)]">{{ __('common.Utilizador') }}</p>
                            <p class="mt-0.5 text-xs leading-5 text-[var(--text-soft)]">{{ __('common.Acesso às funcionalidades padrão do painel.') }}</p>
                        </div>
                    </li>
                </ul>
            </x-ui.form.card>

            {{-- Depois de criar o utilizador (informational) --}}
            <x-ui.form.card
                :title="__('common.Depois de criar o utilizador')"
                icon='<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
            >
                <ul class="space-y-3">
                    <li class="flex items-start gap-3">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        <p class="text-xs leading-5 text-[var(--text-soft)]">{{ __('common.Se a conta estiver ativa, o utilizador pode iniciar sessão imediatamente.') }}</p>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        <p class="text-xs leading-5 text-[var(--text-soft)]">{{ __('common.A palavra-passe é encriptada e nunca é apresentada novamente.') }}</p>
                    </li>
                    <li class="flex items-start gap-3">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        <p class="text-xs leading-5 text-[var(--text-soft)]">{{ __('common.Os dados podem ser alterados a qualquer momento na página de detalhe.') }}</p>
                    </li>
                </ul>
            </x-ui.form.card>
        </div>
    </div>
</x-ui.partials.page-header>
@endsection