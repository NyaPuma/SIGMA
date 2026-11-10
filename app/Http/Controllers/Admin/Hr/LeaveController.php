<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class LeaveController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdminOrPermission($request, 'hr.leaves.view');
        $requests = LeaveRequest::query()->with(['employee', 'leaveType', 'approver'])->orderByDesc('created_at')->limit(200)->get();
        $employees = Employee::query()->where('status', '!=', 'terminated')->orderBy('last_name')->orderBy('first_name')->get();
        $types = LeaveType::query()->where('active', true)->orderBy('name')->get();

        return view('ui.hr.leaves.index', compact('requests', 'employees', 'types'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.leaves.manage');
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'days' => ['required', 'numeric', 'min:0.5'],
            'reason' => ['nullable', 'string'],
        ]);
        $data['status'] = 'pending';
        LeaveRequest::create($data);

        return redirect()->route('ui.hr.leaves.index')->with('success', 'Pedido de ausência/férias criado.');
    }

    public function updateStatus(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.leaves.approve');
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'status' => ['required', 'string', 'in:approved,rejected,cancelled'],
        ]);
        $leave->update([
            'status' => $data['status'],
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
        ]);

        return redirect()->route('ui.hr.leaves.index')->with('success', 'Estado do pedido atualizado.');
    }

    public function storeType(Request $request): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.leaves.manage');
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'unique:leave_types,code'],
            'days_per_year' => ['nullable', 'integer', 'min:0'],
            'paid' => ['nullable', 'boolean'],
        ]);
        $data['paid'] = $request->boolean('paid', true);
        $data['active'] = true;
        LeaveType::create($data);

        return redirect()->route('ui.hr.leaves.index')->with('success', 'Tipo de ausência criado.');
    }

    private function authorizeAdminOrPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->hasPermission($permission))) {
            abort(403);
        }
    }
}
