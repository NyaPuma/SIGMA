<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdminOrPermission($request, 'hr.attendance.view');
        $records = AttendanceRecord::query()->with(['employee', 'recorder'])->orderByDesc('date')->orderByDesc('id')->limit(200)->get();
        $employees = Employee::query()->where('status', 'active')->orderBy('last_name')->orderBy('first_name')->get();

        return view('ui.hr.attendance.index', compact('records', 'employees'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.attendance.manage');
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date', 'after_or_equal:check_in'],
            'break_minutes' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'string', 'in:present,absent,late,half_day,remote,holiday'],
            'notes' => ['nullable', 'string'],
        ]);
        $break = $data['break_minutes'] ?? 0;
        $data['break_minutes'] = is_numeric($break) ? (int) $break : 0;
        $data['recorded_by'] = $request->user()?->id;

        /** @var array<string, mixed> $keys */
        $keys = ['employee_id' => $data['employee_id'], 'date' => $data['date']];
        AttendanceRecord::query()->updateOrCreate($keys, $data);

        return redirect()->route('ui.hr.attendance.index')->with('success', 'Registo de assiduidade guardado.');
    }

    public function destroy(Request $request, AttendanceRecord $attendance): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.attendance.manage');
        $attendance->delete();

        return redirect()->route('ui.hr.attendance.index')->with('success', 'Registo removido.');
    }

    private function authorizeAdminOrPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if (! $user || (! $user->isAdmin() && ! $user->hasPermission($permission))) {
            abort(403);
        }
    }
}
