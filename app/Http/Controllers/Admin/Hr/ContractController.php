<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmploymentContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ContractController extends Controller
{
    public function index(Request $request, Employee $employee): View
    {
        $this->authorizeAdminOrPermission($request, 'hr.contracts.view');
        $employee->load(['contracts' => fn ($q) => $q->latest('start_date')]);

        return view('ui.hr.contracts.index', compact('employee'));
    }

    public function create(Request $request, Employee $employee): View
    {
        $this->authorizeAdminOrPermission($request, 'hr.contracts.manage');

        return view('ui.hr.contracts.create', compact('employee'));
    }

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.contracts.manage');
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'type' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'salary_amount' => ['nullable', 'numeric', 'min:0'],
            'salary_currency' => ['nullable', 'string', 'max:10'],
            'work_hours_per_week' => ['nullable', 'numeric', 'min:0', 'max:168'],
            'notes' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['employee_id'] = $employee->id;
        $data['active'] = $request->boolean('active', true);
        $data['salary_currency'] = $data['salary_currency'] ?? 'EUR';
        EmploymentContract::create($data);

        return redirect()->route('ui.hr.employees.contracts.index', $employee)->with('success', 'Contrato criado com sucesso.');
    }

    public function edit(Request $request, Employee $employee, EmploymentContract $contract): View
    {
        $this->authorizeAdminOrPermission($request, 'hr.contracts.manage');
        abort_unless($contract->employee_id === $employee->id, 404);

        return view('ui.hr.contracts.edit', compact('employee', 'contract'));
    }

    public function update(Request $request, Employee $employee, EmploymentContract $contract): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.contracts.manage');
        abort_unless($contract->employee_id === $employee->id, 404);
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'type' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'salary_amount' => ['nullable', 'numeric', 'min:0'],
            'salary_currency' => ['nullable', 'string', 'max:10'],
            'work_hours_per_week' => ['nullable', 'numeric', 'min:0', 'max:168'],
            'notes' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['active'] = $request->boolean('active');
        $contract->update($data);

        return redirect()->route('ui.hr.employees.contracts.index', $employee)->with('success', 'Contrato atualizado com sucesso.');
    }

    public function destroy(Request $request, Employee $employee, EmploymentContract $contract): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.contracts.manage');
        abort_unless($contract->employee_id === $employee->id, 404);
        $contract->delete();

        return redirect()->route('ui.hr.employees.contracts.index', $employee)->with('success', 'Contrato removido com sucesso.');
    }

    private function authorizeAdminOrPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->hasPermission($permission))) {
            abort(403);
        }
    }
}
