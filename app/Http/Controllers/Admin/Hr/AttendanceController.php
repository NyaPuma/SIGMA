<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreAttendanceRecordRequest;
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

    public function store(StoreAttendanceRecordRequest $request): RedirectResponse
    {
        $this->authorizeAdminOrPermission($request, 'hr.attendance.manage');
        $data = $request->validatedWithDefaults($request->user()?->id);
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
