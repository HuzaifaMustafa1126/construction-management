<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CompanyRolesPermissionsSeeder extends Seeder
{
    private const ADMIN_BOOTSTRAP = [1 => 5, 2 => 11];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function (): void {
            $permissions = collect(PermissionCatalog::all())->mapWithKeys(
                fn (string $name) => [$name => Permission::findOrCreate($name, 'web')],
            );

            foreach (self::ADMIN_BOOTSTRAP as $companyId => $userId) {
                $company = Company::query()->find($companyId);
                $user = User::query()->find($userId);

                if (! $company || ! $user || (int) $user->company_id !== $companyId) {
                    throw new LogicException("RBAC bootstrap prerequisites failed for company {$companyId} and user {$userId}.");
                }

                setPermissionsTeamId($companyId);

                foreach (array_keys(PermissionCatalog::ROLE_PERMISSIONS) as $roleName) {
                    $role = Role::query()->firstOrCreate([
                        'company_id' => $companyId,
                        'name' => $roleName,
                        'guard_name' => 'web',
                    ]);

                    $role->syncPermissions(
                        $permissions->only(PermissionCatalog::forRole($roleName))->values(),
                    );
                }

                $user->unsetRelation('roles');
                $user->assignRole('company_admin');
            }
        });

        setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
