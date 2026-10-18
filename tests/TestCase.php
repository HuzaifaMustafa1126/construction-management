<?php

namespace Tests;

use App\Models\User;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function grantRole(User $user, string $roleName): Role
    {
        $companyId = (int) $user->company_id;
        setPermissionsTeamId($companyId);

        foreach (PermissionCatalog::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (array_keys(PermissionCatalog::ROLE_PERMISSIONS) as $defaultRole) {
            $candidate = Role::query()->firstOrCreate([
                'company_id' => $companyId,
                'name' => $defaultRole,
                'guard_name' => 'web',
            ]);
            $candidate->syncPermissions(PermissionCatalog::forRole($defaultRole));
        }

        $role = Role::query()->firstOrCreate([
            'company_id' => $companyId, 'name' => $roleName, 'guard_name' => 'web',
        ]);
        if (array_key_exists($roleName, PermissionCatalog::ROLE_PERMISSIONS)) {
            $role->syncPermissions(PermissionCatalog::forRole($roleName));
        }
        $user->unsetRelation('roles')->assignRole($role);
        $user->unsetRelation('roles');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        setPermissionsTeamId(null);

        return $role;
    }
}
