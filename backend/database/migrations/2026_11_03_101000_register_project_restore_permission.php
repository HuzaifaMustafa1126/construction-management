<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'name' => 'projects.restore', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $permissionId = DB::table('permissions')->where('name', 'projects.restore')->where('guard_name', 'web')->value('id');
        DB::table('roles')->where('name', 'company_admin')->where('guard_name', 'web')->whereNotNull('company_id')
            ->pluck('id')->each(fn (int $roleId) => DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId, 'role_id' => $roleId,
            ]));
    }

    public function down(): void
    {
        // Retain the permission and customized grants on rollback.
    }
};
