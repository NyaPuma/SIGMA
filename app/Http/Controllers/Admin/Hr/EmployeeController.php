<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreEmployeeRequest;
use App\Http\Requests\HR\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdminOrPermission($request, 'hr.employees.view');
        $employees = Employee::query()
            ->with(['department', 'position', 'user'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('ui.hr.employees.index', compact('employees'));
    }

    public function create(Request $request): View
    {
        $this->authorizeAdminOrPermission($request, 'hr.employees.manage');

        return view('ui.hr.employees.create', $this->formData());
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.employees.manage');
        Employee::create($request->validated());

        return redirect()->route('ui.hr.employees.index')->with('success', 'Colaborador criado com sucesso.');
    }

    public function show(Request $request, Employee $employee): View
    {
        $this->authorizeAdminOrPermission($request, 'hr.employees.view');
        $employee->load([
            'department',
            'position',
            'user',
            'contracts',
            'schedules',
            'attendanceRecords' => fn ($q) => $q->latest('date')->limit(30),
            'leaveRequests' => fn ($q) => $q->latest()->limit(20),
        ]);

        return view('ui.hr.employees.show', compact('employee'));
    }

    public function edit(Request $request, Employee $employee): View
    {
        $this->authorizeAdminOrPermission($request, 'hr.employees.manage');

        return view('ui.hr.employees.edit', array_merge(['employee' => $employee], $this->formData()));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.employees.manage');
        $employee->update($request->validated());

        return redirect()->route('ui.hr.employees.index')->with('success', 'Colaborador atualizado com sucesso.');
    }

    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.employees.manage');
        $employee->delete();

        return redirect()->route('ui.hr.employees.index')->with('success', 'Colaborador removido com sucesso.');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'departments' => Department::query()->where('active', true)->orderBy('name')->get(),
            'positions' => Position::query()->where('active', true)->orderBy('name')->get(),
            'users' => User::query()->where('active', true)->orderBy('name')->get(),
            'statuses' => ['active' => 'Ativo', 'on_leave' => 'Em licença', 'terminated' => 'Terminado'],
        ];
    }

    private function authorizeAdminOrPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->hasPermission($permission))) {
            abort(403);
        }
    }
}
