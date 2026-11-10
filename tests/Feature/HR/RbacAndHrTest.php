<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\User;
use App\Models\UserProfile;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RbacAndHrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    private function createUserWithRole(string $roleName, array $attrs = []): User
    {
        $profile = UserProfile::query()->where('name', $roleName)->firstOrFail();

        return User::factory()->create(array_merge([
            'active' => true,
            'profile_id' => $profile->id,
            'api_token' => Str::random(60),
        ], $attrs));
    }

    #[Test]
    public function admin_can_access_rbac_management(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->withHeader('X-Auth-Token', $admin->api_token)
            ->get('/admin/rbac/roles')
            ->assertOk()
            ->assertSee('Acessos');
    }

    #[Test]
    public function non_admin_cannot_access_rbac_management(): void
    {
        $hr = $this->createUserWithRole('hr_manager');
        $this->withHeader('X-Auth-Token', $hr->api_token)
            ->get('/admin/rbac/roles')
            ->assertRedirect();
    }

    #[Test]
    public function hr_manager_can_access_hr_employees(): void
    {
        $hr = $this->createUserWithRole('hr_manager');
        $this->withHeader('X-Auth-Token', $hr->api_token)
            ->get('/ui/hr/employees')
            ->assertOk()
            ->assertSee('Colaboradores');
    }

    #[Test]
    public function regular_user_cannot_access_hr_area(): void
    {
        $user = $this->createUserWithRole('user');
        $this->withHeader('X-Auth-Token', $user->api_token)
            ->get('/ui/hr/employees')
            ->assertRedirect('/ui');
    }

    #[Test]
    public function hr_manager_can_create_department_position_employee(): void
    {
        $hr = $this->createUserWithRole('hr_manager');

        $this->withHeader('X-Auth-Token', $hr->api_token)
            ->post('/ui/hr/departments', ['name' => 'Recursos Humanos', 'code' => 'HR', 'active' => '1'])
            ->assertRedirect(route('ui.hr.departments.index'));
        $this->assertDatabaseHas('departments', ['name' => 'Recursos Humanos', 'code' => 'HR']);

        $dept = Department::query()->firstOrFail();
        $this->withHeader('X-Auth-Token', $hr->api_token)
            ->post('/ui/hr/positions', ['name' => 'Analista', 'department_id' => $dept->id, 'active' => '1'])
            ->assertRedirect(route('ui.hr.positions.index'));
        $this->assertDatabaseHas('positions', ['name' => 'Analista', 'department_id' => $dept->id]);

        $pos = Position::query()->firstOrFail();
        $this->withHeader('X-Auth-Token', $hr->api_token)
            ->post('/ui/hr/employees', [
                'first_name' => 'Ana',
                'last_name' => 'Silva',
                'department_id' => $dept->id,
                'position_id' => $pos->id,
                'status' => 'active',
                'employee_number' => 'EMP-001',
                'email' => 'ana@example.com',
            ])
            ->assertRedirect(route('ui.hr.employees.index'));
        $this->assertDatabaseHas('employees', ['first_name' => 'Ana', 'last_name' => 'Silva', 'employee_number' => 'EMP-001']);
    }

    #[Test]
    public function hr_manager_can_record_attendance_and_leave_request(): void
    {
        $hr = $this->createUserWithRole('hr_manager');
        $dept = Department::create(['name' => 'Operações', 'active' => true]);
        $employee = Employee::create([
            'first_name' => 'João',
            'last_name' => 'Costa',
            'status' => 'active',
            'department_id' => $dept->id,
        ]);

        $this->withHeader('X-Auth-Token', $hr->api_token)
            ->post('/ui/hr/attendance', [
                'employee_id' => $employee->id,
                'date' => '2026-11-10',
                'status' => 'present',
                'break_minutes' => 30,
            ])
            ->assertRedirect(route('ui.hr.attendance.index'));
        $this->assertDatabaseHas('attendance_records', ['employee_id' => $employee->id, 'status' => 'present']);

        $leaveType = LeaveType::create(['name' => 'Férias', 'code' => 'vacation', 'paid' => true, 'active' => true]);
        $this->withHeader('X-Auth-Token', $hr->api_token)
            ->post('/ui/hr/leaves', [
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-05',
                'days' => 5,
                'reason' => 'Férias anuais',
            ])
            ->assertRedirect(route('ui.hr.leaves.index'));
        $this->assertDatabaseHas('leave_requests', ['employee_id' => $employee->id, 'leave_type_id' => $leaveType->id, 'status' => 'pending']);
        $this->assertInstanceOf(LeaveRequest::class, LeaveRequest::query()->first());
        $this->assertInstanceOf(AttendanceRecord::class, AttendanceRecord::query()->first());
    }

    #[Test]
    public function hr_staff_without_approve_permission_cannot_approve_leave(): void
    {
        $hrStaff = $this->createUserWithRole('hr_staff'); // hr_staff has no hr.leaves.approve in seeder
        $dept = Department::create(['name' => 'RH', 'active' => true]);
        $employee = Employee::create(['first_name' => 'Maria', 'last_name' => 'Lima', 'status' => 'active', 'department_id' => $dept->id]);
        $leaveType = LeaveType::create(['name' => 'Férias', 'code' => 'vac', 'paid' => true, 'active' => true]);
        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-12-10',
            'end_date' => '2026-12-11',
            'days' => 2,
            'status' => 'pending',
        ]);

        $this->withHeader('X-Auth-Token', $hrStaff->api_token)
            ->patch('/ui/hr/leaves/'.$leave->id.'/status', ['status' => 'approved'])
            ->assertStatus(403);
    }

    #[Test]
    public function admin_can_create_role_and_assign_to_user(): void
    {
        $admin = $this->createUserWithRole('admin');
        $user = $this->createUserWithRole('user', ['email' => 'target@example.com']);

        $this->withHeader('X-Auth-Token', $admin->api_token)
            ->post('/admin/rbac/roles', [
                'name' => 'legal_manager',
                'slug' => 'legal_manager',
                'display_name' => 'Gestor Jurídico',
                'area' => 'legal',
            ])
            ->assertRedirect(route('ui.admin.rbac.roles'));
        $this->assertDatabaseHas('user_profiles', ['name' => 'legal_manager', 'slug' => 'legal_manager']);

        $role = UserProfile::query()->where('name', 'legal_manager')->firstOrFail();
        $this->withHeader('X-Auth-Token', $admin->api_token)
            ->patch('/admin/rbac/user-roles/'.$user->id, ['profile_id' => $role->id])
            ->assertRedirect(route('ui.admin.rbac.user-roles'));
        $this->assertSame($role->id, $user->fresh()->profile_id);
    }
}
