@extends('ui.layout')

@section('page_key', 'users-edit')

@section('content')
<x-ui.partials.page-header
    :title="__('ui.Editar Utilizador')"
    :subtitle="__('ticket_media.Atualize as credenciais, fotografia e permissões de acesso do perfil de utilizador.')"
>
    <div class="rounded-3xl border border-[var(--border)] bg-[var(--surface)] p-6 shadow-sm">
        <form id="editUserForm" class="space-y-6" enctype="multipart/form-data" data-user-mode="edit" data-user-id="{{ $targetUser->id }}" data-profile-id="{{ $targetUser->profile_id }}">
            
            {{-- Avatar / Profile Picture Visual Section --}}
            <div class="flex flex-col sm:flex-row items-center gap-6 p-4 bg-[var(--surface-2)] rounded-2xl border border-[var(--border)]">
                <div class="relative w-24 h-24 rounded-2xl overflow-hidden border-2 border-primary/30 shadow-md bg-[var(--surface)] flex-shrink-0 flex items-center justify-center">
                    <img id="avatarPreview" 
                         src="{{ $targetUser->avatar_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($targetUser->name) . '&background=f97316&color=ffffff&bold=true' }}" 
                         alt="{{ __('ticket_media.Fotografia do Utilizador') }}" 
                         class="w-full h-full object-cover">
                </div>

                <div class="space-y-2 text-center sm:text-left">
                    <h2 class="text-sm font-bold text-[var(--text)]">{{ __('ticket_media.Fotografia do Utilizador') }}</h2>
                    <p class="text-xs text-[var(--text-soft)]">{{ __('ticket_media.Carregue uma imagem (PNG, JPG ou WEBP até 2MB).') }}</p>
                    
                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-3 pt-1">
                        <label for="avatarInput" class="cursor-pointer px-4 py-2 bg-primary/10 hover:bg-primary/20 text-primary font-bold text-xs rounded-xl border border-primary/30 transition shadow-sm inline-flex items-center gap-1.5">
                            &#128247; {{ __('ticket_media.Escolher Fotografia') }}
                        </label>
                        <input type="file" id="avatarInput" name="avatar" accept="image/*" class="hidden">
                        <span id="avatarFileName" class="text-xs text-[var(--text-soft)] truncate max-w-[180px]">{{ __('ticket_media.Nenhum ficheiro selecionado') }}</span>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div>
                    <label for="userName" class="mb-2 block text-xs font-bold uppercase tracking-[0.2em] text-[var(--text-soft)]">{{ __('common.Nome Completo') }}</label>
                    <input type="text" id="userName" name="name" required value="{{ $targetUser->name }}" class="w-full rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3 text-sm text-[var(--text)] outline-none focus:border-primary focus:ring-4 focus:ring-primary/15" placeholder="{{ __('common.Ex.: João Silva') }}">
                </div>
                <div>
                    <label for="userEmail" class="mb-2 block text-xs font-bold uppercase tracking-[0.2em] text-[var(--text-soft)]">{{ __('common.Endereço de Email') }}</label>
                    <input type="email" id="userEmail" name="email" required value="{{ $targetUser->email }}" class="w-full rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3 text-sm text-[var(--text)] outline-none focus:border-primary focus:ring-4 focus:ring-primary/15" placeholder="{{ __('common.Ex.: joao@empresa.pt') }}">
                </div>
                <div>
                    <label for="userPassword" class="mb-2 block text-xs font-bold uppercase tracking-[0.2em] text-[var(--text-soft)]">{{ __('auth.Nova Palavra-passe (deixar em branco para manter)') }}</label>
                    <input type="password" id="userPassword" name="password" class="w-full rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3 text-sm text-[var(--text)] outline-none focus:border-primary focus:ring-4 focus:ring-primary/15" placeholder="••••••••">
                </div>
                <div>
                    <label for="userProfileId" class="mb-2 block text-xs font-bold uppercase tracking-[0.2em] text-[var(--text-soft)]">{{ __('common.Perfil de Acesso') }}</label>
                    <select id="userProfileId" name="profile_id" required disabled class="w-full rounded-2xl border border-[var(--border)] bg-[var(--surface-2)] px-4 py-3 text-sm text-[var(--text)] outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 disabled:opacity-60 disabled:cursor-not-allowed">
                        <option value="">{{ __('ui.A carregar perfis...') }}</option>
                    </select>
                </div>
                <div>
                    <label for="userActive" class="mb-2 block text-xs font-bold uppercase tracking-[0.2em] text-[var(--text-soft)]">{{ __('common.Estado da Conta') }}</label>
                    <div class="flex items-center gap-3 mt-2">
                        <input type="checkbox" id="userActive" name="active" value="1" {{ $targetUser->active ? 'checked' : '' }} class="h-4 w-4 rounded border-[var(--border)] text-primary focus:ring-primary">
                        <span class="text-sm font-semibold text-[var(--text)]">{{ __('auth.Conta ativa (permite login)') }}</span>
                    </div>
                </div>
            </div>

            <div id="formMessage" class="min-h-6 text-sm font-medium text-[var(--text-soft)]"></div>

            <div class="mt-6 flex flex-wrap gap-3">
                <button type="submit" id="submitBtn" class="ui-button ui-button--primary inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-semibold transition hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed">{{ __('ui.Guardar Alterações') }}</button>
                <a href="{{ route('ui.users') }}" class="ui-button ui-button--outline inline-flex items-center justify-center rounded-2xl border border-[var(--border)] bg-[var(--surface)] px-5 py-3 text-sm font-semibold text-[var(--text)] transition hover:bg-[var(--surface-2)]">
                    {{ __('ui.Cancelar') }}
                </a>
            </div>
        </form>
    </div>
</x-ui.partials.page-header>
@endsection

