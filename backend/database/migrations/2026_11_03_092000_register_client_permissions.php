<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = ['clients.view', 'clients.create', 'clients.update', 'clients.delete'];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $now = now();
        foreach (self::PERMISSIONS as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $permissionIds = DB::table('permissions')->where('guard_name', 'web')
            ->whereIn('name', self::PERMISSIONS)->pluck('id');
        $adminRoleIds = DB::table('roles')->where('guard_name', 'web')
            ->where('name', 'company_admin')->whereNotNull('company_id')->pluck('id');

        foreach ($adminRoleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId, 'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Permissions and customized grants are intentionally retained on rollback.
    }
};
