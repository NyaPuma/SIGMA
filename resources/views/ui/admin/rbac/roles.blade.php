@extends('ui.layout')
@section('page_key', 'rbac-roles')
@section('content')
<x-ui.partials.page-header title="Acessos & Roles" subtitle="Gestão de roles e permissões por área." badge="Admin">
    @if(session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 ui-listing-table w-full overflow-hidden bg-[var(--surface)] border border-[var(--border)] rounded-2xl shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[var(--border)] text-left text-xs">
                    <thead class="bg-[var(--surface-2)] text-[var(--text-soft)] uppercase tracking-wider font-bold text-xs">
                        <tr>
                            <th class="px-6 py-4">Role</th>
                            <th class="px-6 py-4">Área</th>
                            <th class="px-6 py-4">Utilizadores</th>
                            <th class="px-6 py-4">Permissões</th>
                            <th class="px-6 py-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--border)]">
                        @foreach($roles as $role)
                        <tr>
                            <td class="px-6 py-4 font-medium">{{ $role->display_name ?? $role->name }} <span class="text-[var(--text-soft)]">({{ $role->slug ?? $role->name }})</span> @if($role->is_system)<span class="ml-2 text-[10px] px-2 py-0.5 rounded-full bg-[var(--surface-2)]">sistema</span>@endif</td>
                            <td class="px-6 py-4">{{ $role->area ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $role->users_count }}</td>
                            <td class="px-6 py-4">{{ $role->permissions_count }}</td>
                            <td class="px-6 py-4 text-right"><a href="{{ route('ui.admin.rbac.roles.edit', $role) }}" class="text-primary font-semibold">Editar</a></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="space-y-6">
            <div class="bg-[var(--surface)] border border-[var(--border)] rounded-2xl p-6">
                <h3 class="font-bold text-base mb-4">Nova Role</h3>
                <form method="POST" action="{{ route('ui.admin.rbac.roles.store') }}" class="space-y-3">
                    @csrf
                    <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Nome (interno) *</label><input name="name" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm" placeholder="ex: legal_manager"></div>
                    <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Slug *</label><input name="slug" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm" placeholder="ex: legal_manager"></div>
                    <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Nome de apresentação *</label><input name="display_name" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"></div>
                    <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Área</label><input name="area" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm" placeholder="hr, finance, legal..."></div>
                    <div><label class="block text-xs font-bold uppercase tracking-wider text-[var(--text-soft)] mb-1">Descrição</label><textarea name="description" rows="2" class="w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm"></textarea></div>
                    <button type="submit" class="ui-button ui-button--primary px-4 py-2 text-xs font-bold rounded-xl w-full">Criar Role</button>
                </form>
            </div>
            <div class="bg-[var(--surface)] border border-[var(--border)] rounded-2xl p-6">
                <h3 class="font-bold text-base mb-2">Atribuição por utilizador</h3>
                <p class="text-sm text-[var(--text-soft)] mb-4">Definir qual a role de cada utilizador.</p>
                <a href="{{ route('ui.admin.rbac.user-roles') }}" class="ui-button ui-button--primary px-4 py-2 text-xs font-bold rounded-xl inline-block">Gerir roles de utilizadores</a>
            </div>
        </div>
    </div>
</x-ui.partials.page-header>
@endsection
