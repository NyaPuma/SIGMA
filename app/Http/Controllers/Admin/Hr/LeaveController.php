<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreLeaveRequestRequest;
use App\Http\Requests\HR\StoreLeaveTypeRequest;
use App\Http\Requests\HR\UpdateLeaveStatusRequest;
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

    public function store(StoreLeaveRequestRequest $request): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.leaves.manage');
        LeaveRequest::create($request->validatedWithDefaults());

        return redirect()->route('ui.hr.leaves.index')->with('success', 'Pedido de ausência/férias criado.');
    }

    public function updateStatus(UpdateLeaveStatusRequest $request, LeaveRequest $leave): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.leaves.approve');
        $leave->update([
            'status' => $request->validated('status'),
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
        ]);

        return redirect()->route('ui.hr.leaves.index')->with('success', 'Estado do pedido atualizado.');
    }

    public function storeType(StoreLeaveTypeRequest $request): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.leaves.manage');
        LeaveType::create($request->validatedWithDefaults());

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
