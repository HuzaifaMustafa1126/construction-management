<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CompanyRolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function (): void {
            $permissions = collect(PermissionCatalog::all())->mapWithKeys(
                fn (string $name) => [$name => Permission::findOrCreate($name, 'web')],
            );

            foreach (Company::query()->cursor() as $company) {
                setPermissionsTeamId($company->id);

                foreach (array_keys(PermissionCatalog::ROLE_PERMISSIONS) as $roleName) {
                    $role = Role::query()->firstOrCreate([
                        'company_id' => $company->id,
                        'name' => $roleName,
                        'guard_name' => 'web',
                    ]);

                    if ($role->wasRecentlyCreated) {
                        $role->syncPermissions(
                            $permissions->only(PermissionCatalog::forRole($roleName))->values(),
                        );
                    }
                }

            }
        });

        setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
