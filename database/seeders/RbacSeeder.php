<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Admin/System
            ['slug' => 'admin.access', 'name' => 'Acesso à administração', 'module' => 'admin'],
            ['slug' => 'admin.users.manage', 'name' => 'Gerir utilizadores', 'module' => 'admin'],
            ['slug' => 'admin.roles.manage', 'name' => 'Gerir roles e permissões', 'module' => 'admin'],
            ['slug' => 'admin.settings.manage', 'name' => 'Gerir definições do sistema', 'module' => 'admin'],
            // HR
            ['slug' => 'hr.access', 'name' => 'Acesso a Recursos Humanos', 'module' => 'hr'],
            ['slug' => 'hr.departments.view', 'name' => 'Ver departamentos', 'module' => 'hr'],
            ['slug' => 'hr.departments.manage', 'name' => 'Gerir departamentos', 'module' => 'hr'],
            ['slug' => 'hr.positions.view', 'name' => 'Ver cargos', 'module' => 'hr'],
            ['slug' => 'hr.positions.manage', 'name' => 'Gerir cargos', 'module' => 'hr'],
            ['slug' => 'hr.employees.view', 'name' => 'Ver colaboradores', 'module' => 'hr'],
            ['slug' => 'hr.employees.manage', 'name' => 'Gerir colaboradores', 'module' => 'hr'],
            ['slug' => 'hr.contracts.view', 'name' => 'Ver contratos', 'module' => 'hr'],
            ['slug' => 'hr.contracts.manage', 'name' => 'Gerir contratos', 'module' => 'hr'],
            ['slug' => 'hr.attendance.view', 'name' => 'Ver assiduidade', 'module' => 'hr'],
            ['slug' => 'hr.attendance.manage', 'name' => 'Gerir assiduidade', 'module' => 'hr'],
            ['slug' => 'hr.schedules.view', 'name' => 'Ver escalas/horários', 'module' => 'hr'],
            ['slug' => 'hr.schedules.manage', 'name' => 'Gerir escalas/horários', 'module' => 'hr'],
            ['slug' => 'hr.leaves.view', 'name' => 'Ver ausências/férias', 'module' => 'hr'],
            ['slug' => 'hr.leaves.manage', 'name' => 'Gerir ausências/férias', 'module' => 'hr'],
            ['slug' => 'hr.leaves.approve', 'name' => 'Aprovar ausências/férias', 'module' => 'hr'],
            // Operations / existing areas (for future granular use)
            ['slug' => 'operations.tickets.view', 'name' => 'Ver tickets', 'module' => 'operations'],
            ['slug' => 'operations.tickets.manage', 'name' => 'Gerir tickets', 'module' => 'operations'],
            ['slug' => 'operations.stock.view', 'name' => 'Ver stock', 'module' => 'operations'],
            ['slug' => 'operations.stock.manage', 'name' => 'Gerir stock', 'module' => 'operations'],
            // Finance (future)
            ['slug' => 'finance.access', 'name' => 'Acesso a Finanças', 'module' => 'finance'],
            ['slug' => 'finance.payments.view', 'name' => 'Ver pagamentos', 'module' => 'finance'],
            ['slug' => 'finance.payments.manage', 'name' => 'Gerir pagamentos', 'module' => 'finance'],
            ['slug' => 'finance.payroll.view', 'name' => 'Ver salários', 'module' => 'finance'],
            ['slug' => 'finance.payroll.manage', 'name' => 'Gerir salários', 'module' => 'finance'],
        ];

        $permissionIds = [];
        foreach ($permissions as $p) {
            $perm = Permission::query()->updateOrCreate(
                ['slug' => $p['slug']],
                ['name' => $p['name'], 'module' => $p['module'], 'description' => $p['description'] ?? null]
            );
            $permissionIds[$p['slug']] = $perm->id;
        }

        // Ensure base roles exist
        $roles = [
            ['name' => 'admin', 'slug' => 'admin', 'display_name' => 'Administrador', 'area' => 'admin', 'is_system' => true],
            ['name' => 'technician', 'slug' => 'technician', 'display_name' => 'Técnico', 'area' => 'operations', 'is_system' => true],
            ['name' => 'user', 'slug' => 'user', 'display_name' => 'Utilizador', 'area' => 'general', 'is_system' => true],
            ['name' => 'hr_manager', 'slug' => 'hr_manager', 'display_name' => 'Gestor de RH', 'area' => 'hr', 'is_system' => false],
            ['name' => 'hr_staff', 'slug' => 'hr_staff', 'display_name' => 'Colaborador de RH', 'area' => 'hr', 'is_system' => false],
            ['name' => 'finance_manager', 'slug' => 'finance_manager', 'display_name' => 'Gestor Financeiro', 'area' => 'finance', 'is_system' => false],
        ];

        $roleModels = [];
        foreach ($roles as $r) {
            $role = UserProfile::query()->updateOrCreate(
                ['name' => $r['name']],
                [
                    'slug' => $r['slug'],
                    'display_name' => $r['display_name'],
                    'area' => $r['area'],
                    'is_system' => $r['is_system'],
                    'active' => true,
                ]
            );
            $roleModels[$r['name']] = $role;
        }

        // Admin gets all permissions
        $roleModels['admin']->permissions()->sync(array_values($permissionIds));

        // HR Manager - full HR
        $hrAll = array_values(array_filter($permissionIds, fn ($id, $slug) => str_starts_with($slug, 'hr.'), ARRAY_FILTER_USE_BOTH));
        $roleModels['hr_manager']->permissions()->sync($hrAll);

        // HR Staff - view + manage attendance/leaves but not contracts/approve? give view+manage basics, approve leaves
        $hrStaff = [];
        foreach (['hr.access', 'hr.departments.view', 'hr.positions.view', 'hr.employees.view', 'hr.employees.manage', 'hr.contracts.view', 'hr.attendance.view', 'hr.attendance.manage', 'hr.schedules.view', 'hr.schedules.manage', 'hr.leaves.view', 'hr.leaves.manage'] as $slug) {
            if (isset($permissionIds[$slug])) {
                $hrStaff[] = $permissionIds[$slug];
            }
        }
        $roleModels['hr_staff']->permissions()->sync($hrStaff);

        // Finance manager - finance area
        $finance = array_values(array_filter($permissionIds, fn ($id, $slug) => str_starts_with($slug, 'finance.'), ARRAY_FILTER_USE_BOTH));
        $roleModels['finance_manager']->permissions()->sync($finance);

        // Technician - operations
        $ops = [];
        foreach (['operations.tickets.view', 'operations.tickets.manage', 'operations.stock.view', 'operations.stock.manage'] as $slug) {
            if (isset($permissionIds[$slug])) {
                $ops[] = $permissionIds[$slug];
            }
        }
        $roleModels['technician']->permissions()->sync($ops);

        // User - minimal (none specific now)
        $roleModels['user']->permissions()->sync([]);
    }
}
