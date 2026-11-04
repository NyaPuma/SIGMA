{{--
|-------------------------------------------------------------------------- |
Profile Security Form Card Component
|-------------------------------------------------------------------------- |
| Card with the password-change form.
| • Fields reuse Design System form components (label + input).
| • Requirements checklist and strength meter via Alpine.js, without inline CSS or JS.
| --}}
@props([])

<x-ui.form.card
    :title="__('auth.Segurança & Palavra-passe')"
    :description="__('auth.Defina uma palavra-passe forte para proteger a sua conta.')"
    icon='<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>'
>
    <form
        id="passwordForm"
        class="space-y-4"
        novalidate
        x-data="passwordStrength()"
        data-saving-message="{{ __('auth.A atualizar palavra-passe...') }}"
        data-success-message="{{ __('messages.Palavra-passe atualizada com sucesso.') }}"
        data-error-message="{{ __('auth.Não foi possível atualizar a palavra-passe.') }}"
    >
        <x-ui.form.field :id="'currentPassword'" :label="__('auth.Palavra-passe atual')">
            <x-ui.form.input
                id="currentPassword"
                name="current_password"
                type="password"
                autocomplete="current-password"
                placeholder="••••••••"
                class="py-3"
            />
        </x-ui.form.field>

        <x-ui.form.field :id="'newPassword'" :label="__('auth.Nova palavra-passe')">
            <x-ui.form.input
                id="newPassword"
                name="password"
                type="password"
                autocomplete="new-password"
                x-model="password"
                :placeholder="__('stock.Mínimo 8 caracteres')"
                class="py-3"
            />

            <ul class="mt-3 space-y-1.5" aria-label="{{ __('auth.Requisitos da palavra-passe') }}">
                <li
                    :class="lengthOk ? 'text-success' : 'text-(--text-muted)'"
                    class="flex items-center gap-2 text-xs"
                >
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ __('stock.Mínimo de 8 caracteres') }}
                </li>

                <li
                    :class="caseOk ? 'text-success' : 'text-(--text-muted)'"
                    class="flex items-center gap-2 text-xs"
                >
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ __('common.Pelo menos 1 letra maiúscula e 1 letra minúscula') }}
                </li>

                <li
                    :class="symbolNumberOk ? 'text-success' : 'text-(--text-muted)'"
                    class="flex items-center gap-2 text-xs"
                >
                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ __('common.Pelo menos 1 símbolo ou número') }}
                </li>
            </ul>

            <div class="mt-3">
                <div class="flex items-center gap-3">
                    <div
                        class="flex flex-1 gap-1.5"
                        role="progressbar"
                        aria-valuemin="0"
                        aria-valuemax="3"
                        :aria-valuenow="score"
                    >
                        <span
                            :class="[score >= 1 ? barColor : 'bg-[var(--border)]', 'h-1.5 flex-1 rounded-full transition-colors']"
                            aria-hidden="true"
                        ></span>
                        <span
                            :class="[score >= 2 ? barColor : 'bg-[var(--border)]', 'h-1.5 flex-1 rounded-full transition-colors']"
                            aria-hidden="true"
                        ></span>
                        <span
                            :class="[score >= 3 ? barColor : 'bg-[var(--border)]', 'h-1.5 flex-1 rounded-full transition-colors']"
                            aria-hidden="true"
                        ></span>
                    </div>

                    <span
                        x-show="score > 0"
                        x-cloak
                        x-text="levelLabel"
                        :class="[score > 0 ? levelClass : 'text-(--text-muted)', 'min-w-12 text-right text-xs font-medium']"
                    ></span>
                </div>
            </div>
        </x-ui.form.field>

        <x-ui.form.field :id="'newPasswordConfirmation'" :label="__('auth.Confirmar palavra-passe')">
            <x-ui.form.input
                id="newPasswordConfirmation"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                placeholder="••••••••"
                class="py-3"
            />
        </x-ui.form.field>

        <x-ui.form.message id="passwordMessage" />

        <div class="pt-2">
            <x-ui.buttons.submit id="submitPasswordBtn" variant="primary" size="md" weight="semibold" class="rounded-2xl disabled:cursor-not-allowed disabled:opacity-50">
                {{ __('auth.Atualizar palavra-passe') }}
            </x-ui.buttons.submit>
        </div>
    </form>
</x-ui.form.card>
