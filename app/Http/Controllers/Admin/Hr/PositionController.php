<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PositionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdminOrPermission($request, 'hr.positions.view');
        $positions = Position::query()->with('department')->orderBy('name')->get();

        return view('ui.hr.positions.index', compact('positions'));
    }

    public function create(Request $request): View
    {
        $this->authorizeAdminOrPermission($request, 'hr.positions.manage');
        $departments = Department::query()->where('active', true)->orderBy('name')->get();

        return view('ui.hr.positions.create', compact('departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.positions.manage');
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'description' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['active'] = $request->boolean('active', true);
        Position::create($data);

        return redirect()->route('ui.hr.positions.index')->with('success', 'Cargo criado com sucesso.');
    }

    public function edit(Request $request, Position $position): View
    {
        $this->authorizeAdminOrPermission($request, 'hr.positions.manage');
        $departments = Department::query()->where('active', true)->orderBy('name')->get();

        return view('ui.hr.positions.edit', compact('position', 'departments'));
    }

    public function update(Request $request, Position $position): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.positions.manage');
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'description' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['active'] = $request->boolean('active');
        $position->update($data);

        return redirect()->route('ui.hr.positions.index')->with('success', 'Cargo atualizado com sucesso.');
    }

    public function destroy(Request $request, Position $position): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.positions.manage');
        $position->delete();

        return redirect()->route('ui.hr.positions.index')->with('success', 'Cargo removido com sucesso.');
    }

    private function authorizeAdminOrPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->hasPermission($permission))) {
            abort(403);
        }
    }
}
