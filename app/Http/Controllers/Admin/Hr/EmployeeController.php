<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
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

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.employees.manage');
        $data = $this->validateData($request);
        Employee::create($data);

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

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.employees.manage');
        $data = $this->validateData($request, $employee->id);
        $employee->update($data);

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

    /** @return array<string, mixed> */
    private function validateData(Request $request, ?int $employeeId = null): array
    {
        $unique = 'unique:employees,employee_number'.($employeeId ? ','.$employeeId : '');
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'employee_number' => ['nullable', 'string', 'max:50', $unique],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'hire_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'status' => ['required', 'string', 'in:active,on_leave,terminated'],
            'notes' => ['nullable', 'string'],
        ]);

        return $data;
    }

    private function authorizeAdminOrPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->hasPermission($permission))) {
            abort(403);
        }
    }
}
