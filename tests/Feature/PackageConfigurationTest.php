<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackageConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_teams_use_the_company_id_key(): void
    {
        $this->assertTrue(config('permission.teams'));
        $this->assertSame('company_id', config('permission.column_names.team_foreign_key'));
    }

    public function test_permission_tables_have_the_team_aware_schema(): void
    {
        foreach ([
            'roles',
            'permissions',
            'model_has_roles',
            'model_has_permissions',
            'role_has_permissions',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");
        }

        $this->assertTrue(Schema::hasColumn('roles', 'company_id'));
        $this->assertTrue(Schema::hasColumn('model_has_roles', 'company_id'));
        $this->assertTrue(Schema::hasColumn('model_has_permissions', 'company_id'));
    }

    public function test_activity_log_table_has_the_published_schema(): void
    {
        $this->assertTrue(Schema::hasTable('activity_log'));

        foreach ([
            'log_name',
            'description',
            'subject_type',
            'subject_id',
            'causer_type',
            'causer_id',
            'event',
            'properties',
            'batch_uuid',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('activity_log', $column),
                "Expected activity_log column [{$column}] to exist.",
            );
        }
    }
}
