<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StorePositionRequest;
use App\Http\Requests\HR\UpdatePositionRequest;
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

    public function store(StorePositionRequest $request): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.positions.manage');
        Position::create($request->validatedWithDefaults());

        return redirect()->route('ui.hr.positions.index')->with('success', 'Cargo criado com sucesso.');
    }

    public function edit(Request $request, Position $position): View
    {
        $this->authorizeAdminOrPermission($request, 'hr.positions.manage');
        $departments = Department::query()->where('active', true)->orderBy('name')->get();

        return view('ui.hr.positions.edit', compact('position', 'departments'));
    }

    public function update(UpdatePositionRequest $request, Position $position): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.positions.manage');
        $position->update($request->validatedWithDefaults());

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
