<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\CompanyRolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanyRolesPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_roles_permissions_seeder_is_idempotent_and_bootstraps_only_verified_admins(): void
    {
        Company::factory()->create(['id' => 1]);
        Company::factory()->create(['id' => 2]);
        User::factory()->for(Company::find(1))->create(['id' => 5, 'role' => 'admin']);
        User::factory()->for(Company::find(2))->create(['id' => 11, 'role' => 'admin']);

        $this->seed(CompanyRolesPermissionsSeeder::class);
        $counts = [
            'roles' => \DB::table('roles')->count(),
            'permissions' => \DB::table('permissions')->count(),
            'assignments' => \DB::table('model_has_roles')->count(),
        ];
        $this->seed(CompanyRolesPermissionsSeeder::class);

        $this->assertSame($counts['roles'], \DB::table('roles')->count());
        $this->assertSame($counts['permissions'], \DB::table('permissions')->count());
        $this->assertSame($counts['assignments'], \DB::table('model_has_roles')->count());
        $this->assertSame(6, $counts['roles']);
        $this->assertSame(2, $counts['assignments']);
    }

    public function test_seeder_adds_department_permissions_without_changing_custom_role_permissions(): void
    {
        Company::factory()->create(['id' => 1]);
        Company::factory()->create(['id' => 2]);
        User::factory()->for(Company::find(1))->create(['id' => 5, 'role' => 'admin']);
        User::factory()->for(Company::find(2))->create(['id' => 11, 'role' => 'admin']);
        $this->seed(CompanyRolesPermissionsSeeder::class);

        $customPermission = Permission::findOrCreate('custom.audit', 'web');
        setPermissionsTeamId(1);
        $customRole = Role::create(['company_id' => 1, 'name' => 'auditor', 'guard_name' => 'web']);
        $customRole->givePermissionTo($customPermission);

        $this->seed(CompanyRolesPermissionsSeeder::class);
        setPermissionsTeamId(1);
        $this->assertSame(['custom.audit'], $customRole->fresh()->permissions->pluck('name')->all());
        $admin = Role::query()->where('company_id', 1)->where('name', 'company_admin')->firstOrFail();
        $this->assertTrue($admin->hasPermissionTo('departments.assign'));
        $this->assertTrue($admin->hasPermissionTo('departments.delete'));
        $this->assertDatabaseCount('permissions', 42);
        setPermissionsTeamId(null);
    }
}
