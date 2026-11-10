<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreWorkScheduleRequest;
use App\Http\Requests\HR\UpdateWorkScheduleRequest;
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

    public function store(StoreWorkScheduleRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.schedules.manage');
        WorkSchedule::create($request->validatedWithDefaults($employee->id));

        return redirect()->route('ui.hr.employees.schedules.index', $employee)->with('success', 'Horário criado com sucesso.');
    }

    public function update(UpdateWorkScheduleRequest $request, Employee $employee, WorkSchedule $schedule): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.schedules.manage');
        abort_unless($schedule->employee_id === $employee->id, 404);
        $schedule->update($request->validatedWithDefaults());

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
