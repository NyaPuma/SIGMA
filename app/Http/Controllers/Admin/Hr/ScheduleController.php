<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\WorkSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ScheduleController extends Controller
{
    public function index(Request $request, Employee $employee): View
    {
        $this->authorizeAdminOrPermission($request, 'hr.schedules.view');
        $employee->load(['schedules' => fn ($q) => $q->orderBy('day_of_week')->orderBy('start_time')]);

        return view('ui.hr.schedules.index', compact('employee'));
    }

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.schedules.manage');
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'break_minutes' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['employee_id'] = $employee->id;
        $data['active'] = $request->boolean('active', true);
        $break = $data['break_minutes'] ?? 0;
        $data['break_minutes'] = is_numeric($break) ? (int) $break : 0;
        WorkSchedule::create($data);

        return redirect()->route('ui.hr.employees.schedules.index', $employee)->with('success', 'Horário criado com sucesso.');
    }

    public function update(Request $request, Employee $employee, WorkSchedule $schedule): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.schedules.manage');
        abort_unless($schedule->employee_id === $employee->id, 404);
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'break_minutes' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['active'] = $request->boolean('active');
        $break = $data['break_minutes'] ?? 0;
        $data['break_minutes'] = is_numeric($break) ? (int) $break : 0;
        $schedule->update($data);

        return redirect()->route('ui.hr.employees.schedules.index', $employee)->with('success', 'Horário atualizado com sucesso.');
    }

    public function destroy(Request $request, Employee $employee, WorkSchedule $schedule): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.schedules.manage');
        abort_unless($schedule->employee_id === $employee->id, 404);
        $schedule->delete();

        return redirect()->route('ui.hr.employees.schedules.index', $employee)->with('success', 'Horário removido com sucesso.');
    }

    private function authorizeAdminOrPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->hasPermission($permission))) {
            abort(403);
        }
    }
}
