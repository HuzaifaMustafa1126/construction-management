<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Support\Authorization\PermissionCatalog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CompanyRoleInitializer
{
    public function initialize(Company $company, User $administrator): void
    {
        $previousTeamId = getPermissionsTeamId();
        try {
            setPermissionsTeamId($company->id);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $permissions = collect(PermissionCatalog::all())->mapWithKeys(fn (string $name) => [$name => Permission::findOrCreate($name, 'web')]);
            foreach (array_keys(PermissionCatalog::ROLE_PERMISSIONS) as $roleName) {
                $role = Role::query()->firstOrCreate(['company_id' => $company->id, 'name' => $roleName, 'guard_name' => 'web']);
                $role->syncPermissions($permissions->only(PermissionCatalog::forRole($roleName))->values());
            }
            $administrator->unsetRelation('roles')->assignRole(Role::query()->where('company_id', $company->id)->where('name', 'company_admin')->firstOrFail());
            $administrator->unsetRelation('roles')->unsetRelation('permissions');
        } finally {
            setPermissionsTeamId($previousTeamId);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
