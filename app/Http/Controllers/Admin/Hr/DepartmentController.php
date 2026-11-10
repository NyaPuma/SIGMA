<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreDepartmentRequest;
use App\Http\Requests\HR\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdminOrPermission($request, 'hr.departments.view');
        $departments = Department::query()->with('manager')->orderBy('name')->get();

        return view('ui.hr.departments.index', compact('departments'));
    }

    public function create(Request $request): View
    {
        $this->authorizeAdminOrPermission($request, 'hr.departments.manage');
        $managers = User::query()->where('active', true)->orderBy('name')->get();

        return view('ui.hr.departments.create', compact('managers'));
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.departments.manage');
        Department::create($request->validatedWithDefaults());

        return redirect()->route('ui.hr.departments.index')->with('success', 'Departamento criado com sucesso.');
    }

    public function edit(Request $request, Department $department): View
    {
        $this->authorizeAdminOrPermission($request, 'hr.departments.manage');
        $managers = User::query()->where('active', true)->orderBy('name')->get();

        return view('ui.hr.departments.edit', compact('department', 'managers'));
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.departments.manage');
        $department->update($request->validatedWithDefaults());

        return redirect()->route('ui.hr.departments.index')->with('success', 'Departamento atualizado com sucesso.');
    }

    public function destroy(Request $request, Department $department): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.departments.manage');
        $department->delete();

        return redirect()->route('ui.hr.departments.index')->with('success', 'Departamento removido com sucesso.');
    }

    private function authorizeAdminOrPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->hasPermission($permission))) {
            abort(403);
        }
    }
}
