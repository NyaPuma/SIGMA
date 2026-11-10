<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class RbacController extends Controller
{
    public function roles(Request $request): View
    {
        $this->ensureAdmin($request);
        $roles = UserProfile::query()->withCount('users', 'permissions')->orderBy('area')->orderBy('name')->get();

        return view('ui.admin.rbac.roles', compact('roles'));
    }

    public function editRole(Request $request, UserProfile $role): View
    {
        $this->ensureAdmin($request);
        $permissions = Permission::query()->orderBy('module')->orderBy('name')->get()->groupBy('module');
        $role->load('permissions');

        return view('ui.admin.rbac.role-edit', compact('role', 'permissions'));
    }

    public function updateRole(Request $request, UserProfile $role): RedirectResponse
    {
        $this->ensureAdmin($request);
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'area' => ['nullable', 'string', 'max:50'],
            'active' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);
        $role->update([
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? null,
            'area' => $data['area'] ?? null,
            'active' => $request->boolean('active'),
        ]);
        /** @var array<int, int|string>|null $perms */
        $perms = $data['permissions'] ?? [];
        $role->permissions()->sync(is_array($perms) ? $perms : []);

        return redirect()->route('ui.admin.rbac.roles')->with('success', 'Role atualizada com sucesso.');
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:user_profiles,name'],
            'slug' => ['required', 'string', 'max:100', 'unique:user_profiles,slug'],
            'display_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'area' => ['nullable', 'string', 'max:50'],
        ]);
        $data['active'] = true;
        $data['is_system'] = false;
        UserProfile::create($data);

        return redirect()->route('ui.admin.rbac.roles')->with('success', 'Role criada com sucesso.');
    }

    public function userRoles(Request $request): View
    {
        $this->ensureAdmin($request);
        $users = User::query()->with('profile')->orderBy('name')->get();
        $roles = UserProfile::query()->where('active', true)->orderBy('display_name')->get();

        return view('ui.admin.rbac.user-roles', compact('users', 'roles'));
    }

    public function updateUserRole(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin($request);
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'profile_id' => ['required', 'integer', 'exists:user_profiles,id'],
        ]);
        $user->update(['profile_id' => $data['profile_id']]);

        return redirect()->route('ui.admin.rbac.user-roles')->with('success', 'Role do utilizador atualizada.');
    }

    private function ensureAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }
    }
}
