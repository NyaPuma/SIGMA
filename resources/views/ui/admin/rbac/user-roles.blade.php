@extends('ui.layout')
@section('page_key', 'rbac-roles')
@section('content')
<x-ui.partials.page-header title="Roles de Utilizadores" subtitle="Atribuir role/perfil a cada utilizador." badge="Admin">
    @if(session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    <div class="ui-listing-table w-full overflow-hidden bg-[var(--surface)] border border-[var(--border)] rounded-2xl shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[var(--border)] text-left text-xs">
                <thead class="bg-[var(--surface-2)] text-[var(--text-soft)] uppercase tracking-wider font-bold text-xs">
                    <tr>
                        <th class="px-6 py-4">Utilizador</th>
                        <th class="px-6 py-4">Email</th>
                        <th class="px-6 py-4">Role atual</th>
                        <th class="px-6 py-4">Alterar role</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)]">
                    @foreach($users as $u)
                    <tr>
                        <td class="px-6 py-4 font-medium">{{ $u->name }}</td>
                        <td class="px-6 py-4">{{ $u->email }}</td>
                        <td class="px-6 py-4">{{ $u->profile?->display_name ?? $u->profile?->name ?? '-' }}</td>
                        <td class="px-6 py-4">
                            <form method="POST" action="{{ route('ui.admin.rbac.user-roles.update', $u) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <select name="profile_id" class="rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-sm">
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" @selected($u->profile_id == $role->id)>{{ $role->display_name ?? $role->name }} ({{ $role->area ?? '—' }})</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="ui-button ui-button--primary px-3 py-2 text-xs font-bold rounded-xl">Guardar</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-ui.partials.page-header>
@endsection
