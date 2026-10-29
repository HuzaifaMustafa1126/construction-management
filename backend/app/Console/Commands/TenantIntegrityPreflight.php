<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

class TenantIntegrityPreflight extends Command
{
    protected $signature = 'tenancy:integrity-preflight {--json : Emit machine-readable JSON} {--sample-limit=10 : Maximum identifier samples per check}';

    protected $description = 'Run a read-only, point-in-time tenant relationship integrity inspection';

    private Connection $db;

    private int $sampleLimit;

    /** @var array<int, array<string, mixed>> */
    private array $results = [];

    public function handle(): int
    {
        $this->sampleLimit = min(100, max(1, (int) $this->option('sample-limit')));
        $this->db = DB::connection();

        try {
            $identity = $this->databaseIdentity();
            $this->assertMySql($identity);
            $this->runChecks();
        } catch (Throwable $exception) {
            $payload = [
                'status' => 'BLOCKED',
                'exit_code' => 2,
                'database' => $identity ?? ['connection' => $this->db->getName()],
                'message' => $exception->getMessage(),
                'checks' => $this->results,
            ];
            $this->render($payload);

            return self::FAILURE + 1;
        }

        $violations = collect($this->results)->sum('violations');
        $incomplete = collect($this->results)->contains(fn (array $result): bool => in_array($result['status'], ['BLOCKED', 'NOT VERIFIED', 'UNSUPPORTED'], true));
        $exitCode = $incomplete ? 2 : ($violations > 0 ? 1 : 0);
        $payload = [
            'status' => $incomplete ? 'INCOMPLETE' : ($violations > 0 ? 'VIOLATIONS FOUND' : 'CLEAN'),
            'exit_code' => $exitCode,
            'database' => $identity,
            'summary' => [
                'checks' => count($this->results),
                'violations' => $violations,
                'incomplete_checks' => collect($this->results)->whereIn('status', ['BLOCKED', 'NOT VERIFIED', 'UNSUPPORTED'])->count(),
                'sample_limit' => $this->sampleLimit,
            ],
            'checks' => $this->results,
        ];
        $this->render($payload);

        return $exitCode;
    }

    /** @return array<string, mixed> */
    private function databaseIdentity(): array
    {
        $row = (array) $this->db->selectOne('select database() as database_name, version() as server_version, @@read_only as server_read_only');
        if (empty($row['database_name'])) {
            throw new \RuntimeException('The resolved database name is empty; target identity cannot be verified.');
        }

        return [
            'connection' => $this->db->getName(),
            'driver' => $this->db->getDriverName(),
            'database' => $row['database_name'],
            'server_version' => $row['server_version'],
            'server_read_only' => (bool) $row['server_read_only'],
            'target_verified' => true,
        ];
    }

    /** @param array<string, mixed> $identity */
    private function assertMySql(array $identity): void
    {
        if ($identity['driver'] !== 'mysql') {
            throw new \RuntimeException("Unsupported driver [{$identity['driver']}]; this registry uses trusted MySQL metadata queries.");
        }
    }

    private function runChecks(): void
    {
        $this->checkSchemaPresence();
        $this->checkDeclaredForeignKeys();
        $this->checkCompanyOwnership();
        $this->checkTenantUserAttribution();

        foreach ($this->relationshipRegistry() as $check) {
            $this->runSqlCheck(...$check);
        }

        $this->checkAssignmentTypes();
        $this->checkInventoryReferenceTypes();
        $this->checkRbacModelTypes();
    }

    private function checkSchemaPresence(): void
    {
        $required = $this->requiredTables();
        $rows = $this->db->select('select table_name from information_schema.tables where table_schema = database()');
        $present = collect($rows)->map(fn ($row): string => $row->table_name)->all();
        $missing = array_values(array_diff($required, $present));
        $this->results[] = [
            'id' => 'schema.required_tables',
            'description' => 'All registry tables exist in the resolved schema',
            'status' => $missing === [] ? 'PASSED' : 'BLOCKED',
            'checked_rows' => count($required),
            'violations' => count($missing),
            'samples' => array_slice(array_map(fn (string $table): array => ['table' => $table], $missing), 0, $this->sampleLimit),
        ];
        if ($missing !== []) {
            throw new \RuntimeException('Required registry tables are missing: '.implode(', ', $missing));
        }
    }

    private function checkCompanyOwnership(): void
    {
        $rows = $this->db->select("select table_name, is_nullable, column_type from information_schema.columns where table_schema = database() and column_name = 'company_id' order by table_name");
        foreach ($rows as $column) {
            $table = $this->identifier($column->table_name);
            $hasId = (bool) $this->db->scalar("select exists(select 1 from information_schema.columns where table_schema=database() and table_name=? and column_name='id')", [$table]);
            $identifierSelect = $hasId ? 'child.id, child.company_id' : 'child.company_id';
            $checked = (int) $this->db->scalar("select count(*) from `{$table}`");
            $allowsNull = in_array($table, ['measurement_units', 'roles', 'users'], true);
            $condition = $allowsNull
                ? 'c.id is null and child.company_id is not null'
                : '(child.company_id is null or c.id is null)';
            $this->runSqlCheck(
                "company.{$table}.parent",
                "{$table}.company_id resolves to a physical company; documented null exceptions are respected",
                "select {$identifierSelect} from `{$table}` child left join companies c on c.id = child.company_id where {$condition}",
                [],
                ['id', 'company_id'],
                $checked,
                ['column_type' => $column->column_type, 'nullable' => $column->is_nullable, 'null_exception' => $allowsNull]
            );
        }

        $this->runSqlCheck('company.lifecycle.soft_deleted', 'Tenant-owned rows attached to soft-deleted companies (lifecycle concern)', $this->companyLifecycleSql('c.deleted_at is not null'), [], ['table_name', 'id', 'company_id']);
        $this->runSqlCheck('company.lifecycle.inactive', 'Tenant-owned rows attached to inactive/suspended companies (lifecycle concern)', $this->companyLifecycleSql("c.status <> 'active'"), [], ['table_name', 'id', 'company_id']);
    }

    private function checkDeclaredForeignKeys(): void
    {
        $rows = $this->db->select('select k.constraint_name, k.table_name, k.column_name, k.referenced_table_name, k.referenced_column_name, c.is_nullable
            from information_schema.key_column_usage k
            join information_schema.columns c on c.table_schema=k.table_schema and c.table_name=k.table_name and c.column_name=k.column_name
            where k.table_schema=database() and k.referenced_table_name is not null
            order by k.table_name,k.constraint_name,k.ordinal_position');

        foreach ($rows as $foreignKey) {
            $child = $this->identifier($foreignKey->table_name);
            $childColumn = $this->identifier($foreignKey->column_name);
            $parent = $this->identifier($foreignKey->referenced_table_name);
            $parentColumn = $this->identifier($foreignKey->referenced_column_name);
            $hasId = (bool) $this->db->scalar("select exists(select 1 from information_schema.columns where table_schema=database() and table_name=? and column_name='id')", [$child]);
            $identifierSelect = $hasId ? "child.id, child.`{$childColumn}` parent_id" : "child.`{$childColumn}` parent_id";
            $checked = (int) $this->db->scalar("select count(*) from `{$child}` where `{$childColumn}` is not null");
            $this->runSqlCheck(
                "foreign_key.{$child}.{$childColumn}",
                "Physical parent exists for {$child}.{$childColumn} -> {$parent}.{$parentColumn}",
                "select {$identifierSelect} from `{$child}` child left join `{$parent}` parent on parent.`{$parentColumn}`=child.`{$childColumn}` where child.`{$childColumn}` is not null and parent.`{$parentColumn}` is null",
                [],
                ['id', 'parent_id'],
                $checked,
                ['constraint' => $foreignKey->constraint_name, 'nullable' => $foreignKey->is_nullable]
            );
        }
    }

    private function checkTenantUserAttribution(): void
    {
        $references = [
            'departments' => ['created_by'],
            'projects' => ['created_by', 'updated_by'],
            'project_user' => ['user_id', 'assigned_by'],
            'daily_site_reports' => ['created_by', 'submitted_by', 'reviewed_by'],
            'site_photos' => ['uploaded_by', 'reviewed_by'],
            'material_categories' => ['created_by'],
            'materials' => ['created_by'],
            'project_inventories' => ['created_by'],
            'inventory_transactions' => ['created_by'],
            'suppliers' => ['created_by'],
            'material_deliveries' => ['received_by', 'created_by'],
            'material_consumptions' => ['recorded_by', 'created_by'],
            'material_stock_adjustments' => ['created_by', 'posted_by', 'cancelled_by'],
            'material_requests' => ['requested_by', 'approved_by'],
            'inventory_transfers' => ['created_by'],
        ];

        foreach ($references as $table => $columns) {
            foreach ($columns as $column) {
                $this->runSqlCheck(
                    "attribution.{$table}.{$column}.company",
                    "Non-null {$table}.{$column} belongs to the row company",
                    "select child.id from `{$table}` child join users u on u.id=child.`{$column}` where child.`{$column}` is not null and (u.company_id is null or child.company_id<>u.company_id)",
                    [],
                    ['id'],
                );
            }
        }
    }

    /**
     * @return array<int, array{0:string,1:string,2:string,3?:array<int, mixed>,4?:array<int, string>,5?:int|null,6?:array<string, mixed>}>
     */
    private function relationshipRegistry(): array
    {
        $checks = [
            ['users.department', 'User department belongs to the same company', 'select child.id from users child join departments p on p.id=child.department_id where child.department_id is not null and (child.company_id is null or child.company_id<>p.company_id)'],
            ['project_user.project_company', 'Membership company matches project company', 'select pu.id from project_user pu join projects p on p.id=pu.project_id where pu.company_id<>p.company_id'],
            ['project_user.user_company', 'Membership company matches user company and excludes company-less platform users', 'select pu.id from project_user pu join users u on u.id=pu.user_id where u.company_id is null or pu.company_id<>u.company_id'],
            ['project_user.assigned_by_company', 'Membership assigner belongs to membership company', 'select pu.id from project_user pu join users u on u.id=pu.assigned_by where pu.assigned_by is not null and (u.company_id is null or pu.company_id<>u.company_id)'],
            ['project_user.duplicate', 'Project/user membership is unique', 'select min(id) id, project_id, user_id from project_user group by project_id,user_id having count(*)>1', [], ['id', 'project_id', 'user_id']],
            ['reports.project_company', 'Report company matches project company', 'select r.id from daily_site_reports r join projects p on p.id=r.project_id where r.company_id<>p.company_id'],
            ['report_workers.parent_company', 'Worker row company matches report company', 'select w.id from daily_site_report_workers w join daily_site_reports r on r.id=w.daily_site_report_id where w.company_id<>r.company_id'],
            ['photos.project_company', 'Photo company matches project company', 'select ph.id from site_photos ph join projects p on p.id=ph.project_id where ph.company_id<>p.company_id'],
            ['photos.report_alignment', 'Linked photo company/project match linked report', 'select ph.id from site_photos ph join daily_site_reports r on r.id=ph.daily_site_report_id where ph.daily_site_report_id is not null and (ph.company_id<>r.company_id or ph.project_id<>r.project_id)'],
            ['materials.category_company', 'Material category belongs to material company', 'select m.id from materials m join material_categories c on c.id=m.material_category_id where m.company_id<>c.company_id'],
            ['materials.unit_scope', 'Material unit is global/system or belongs to material company', 'select m.id from materials m join measurement_units u on u.id=m.measurement_unit_id where not ((u.company_id is null and u.is_system=1) or u.company_id=m.company_id)'],
            ['measurement_units.global_classification', 'Company-less units are explicitly system units', 'select u.id from measurement_units u where u.company_id is null and u.is_system<>1'],
            ['project_inventory.alignment', 'Inventory company matches project and material companies', 'select pi.id from project_inventories pi join projects p on p.id=pi.project_id join materials m on m.id=pi.material_id where pi.company_id<>p.company_id or pi.company_id<>m.company_id'],
            ['deliveries.alignment', 'Delivery company matches project and supplier', 'select d.id from material_deliveries d join projects p on p.id=d.project_id join suppliers s on s.id=d.supplier_id where d.company_id<>p.company_id or d.company_id<>s.company_id'],
            ['delivery_items.alignment', 'Delivery item company/material/inventory/project align with document', 'select i.id from material_delivery_items i join material_deliveries d on d.id=i.material_delivery_id join project_inventories pi on pi.id=i.project_inventory_id where i.company_id<>d.company_id or i.company_id<>pi.company_id or i.material_id<>pi.material_id or d.project_id<>pi.project_id'],
            ['consumptions.project_company', 'Consumption company matches project', 'select d.id from material_consumptions d join projects p on p.id=d.project_id where d.company_id<>p.company_id'],
            ['consumption_items.alignment', 'Consumption item company/material/inventory/project align with document', 'select i.id from material_consumption_items i join material_consumptions d on d.id=i.material_consumption_id join project_inventories pi on pi.id=i.project_inventory_id where i.company_id<>d.company_id or i.company_id<>pi.company_id or i.material_id<>pi.material_id or d.project_id<>pi.project_id'],
            ['adjustments.project_company', 'Adjustment company matches project', 'select d.id from material_stock_adjustments d join projects p on p.id=d.project_id where d.company_id<>p.company_id'],
            ['adjustment_items.alignment', 'Adjustment item company/material/inventory/project align with document', 'select i.id from material_stock_adjustment_items i join material_stock_adjustments d on d.id=i.material_stock_adjustment_id join project_inventories pi on pi.id=i.project_inventory_id where i.company_id<>d.company_id or i.company_id<>pi.company_id or i.material_id<>pi.material_id or d.project_id<>pi.project_id'],
            ['requests.project_company', 'Request company matches project', 'select d.id from material_requests d join projects p on p.id=d.project_id where d.company_id<>p.company_id'],
            ['request_items.alignment', 'Request item company/material/inventory/project align with document', 'select i.id from material_request_items i join material_requests d on d.id=i.material_request_id join project_inventories pi on pi.id=i.project_inventory_id where i.company_id<>d.company_id or i.company_id<>pi.company_id or i.material_id<>pi.material_id or d.project_id<>pi.project_id'],
            ['inventory_transactions.alignment', 'Ledger company/project/material align with project inventory', 'select t.id from inventory_transactions t join project_inventories pi on pi.id=t.project_inventory_id where t.company_id<>pi.company_id or t.project_id<>pi.project_id or t.material_id<>pi.material_id'],
            ['inventory_transactions.delivery_item', 'Ledger delivery item aligns with transaction', 'select t.id from inventory_transactions t join material_delivery_items i on i.id=t.delivery_item_id join material_deliveries d on d.id=i.material_delivery_id where t.delivery_item_id is not null and (t.company_id<>i.company_id or t.project_id<>d.project_id or t.project_inventory_id<>i.project_inventory_id or t.material_id<>i.material_id)'],
            ['inventory_transactions.consumption_item', 'Ledger consumption item aligns with transaction', 'select t.id from inventory_transactions t join material_consumption_items i on i.id=t.consumption_item_id join material_consumptions d on d.id=i.material_consumption_id where t.consumption_item_id is not null and (t.company_id<>i.company_id or t.project_id<>d.project_id or t.project_inventory_id<>i.project_inventory_id or t.material_id<>i.material_id)'],
            ['inventory_transactions.adjustment_item', 'Ledger adjustment item aligns with transaction', 'select t.id from inventory_transactions t join material_stock_adjustment_items i on i.id=t.stock_adjustment_item_id join material_stock_adjustments d on d.id=i.material_stock_adjustment_id where t.stock_adjustment_item_id is not null and (t.company_id<>i.company_id or t.project_id<>d.project_id or t.project_inventory_id<>i.project_inventory_id or t.material_id<>i.material_id)'],
            ['inventory_transactions.request_item', 'Ledger request item aligns with transaction', 'select t.id from inventory_transactions t join material_request_items i on i.id=t.material_request_item_id join material_requests d on d.id=i.material_request_id where t.material_request_item_id is not null and (t.company_id<>i.company_id or t.project_id<>d.project_id or t.project_inventory_id<>i.project_inventory_id or t.material_id<>i.material_id)'],
            ['inventory_transactions.related', 'Related ledger transaction belongs to same company/material and opposite transfer leg', "select t.id from inventory_transactions t join inventory_transactions r on r.id=t.related_transaction_id where t.related_transaction_id is not null and (t.company_id<>r.company_id or t.material_id<>r.material_id or not (t.transaction_type='transfer_in' and r.transaction_type='transfer_out'))"],
            ['transfers.alignment', 'Transfer company/material and source/destination inventory/project align', 'select t.id from inventory_transfers t join project_inventories s on s.id=t.source_inventory_id join project_inventories d on d.id=t.destination_inventory_id where t.company_id<>s.company_id or t.company_id<>d.company_id or t.source_project_id<>s.project_id or t.destination_project_id<>d.project_id or t.material_id<>s.material_id or t.material_id<>d.material_id'],
            ['rbac.roles_company', 'Role company resolves and global roles are not used', 'select r.id from roles r left join companies c on c.id=r.company_id where r.company_id is null or c.id is null'],
            ['rbac.model_roles_alignment', 'Role company, assignment team, and user company/guard align', "select mr.model_id id from model_has_roles mr join roles r on r.id=mr.role_id join users u on mr.model_type='App\\\\Models\\\\User' and u.id=mr.model_id where mr.company_id<>r.company_id or mr.company_id<>u.company_id or r.guard_name<>'web'"],
            ['rbac.model_permissions_alignment', 'Direct permission team and user company/guard align', "select mp.model_id id from model_has_permissions mp join permissions p on p.id=mp.permission_id join users u on mp.model_type='App\\\\Models\\\\User' and u.id=mp.model_id where mp.company_id<>u.company_id or p.guard_name<>'web'"],
            ['rbac.role_permissions_guard', 'Role-permission guards are compatible', 'select rp.role_id id, rp.permission_id from role_has_permissions rp join roles r on r.id=rp.role_id join permissions p on p.id=rp.permission_id where r.guard_name<>p.guard_name', [], ['id', 'permission_id']],
        ];

        return $checks;
    }

    private function checkAssignmentTypes(): void
    {
        $this->runSqlCheck('project_user.assignment_type', 'Assignment type is one explicitly defined by the application', "select id from project_user where assignment_type not in ('member','supervisor','client')", [], ['id']);
    }

    private function checkInventoryReferenceTypes(): void
    {
        $known = [
            'App\\Models\\MaterialDeliveryItem',
            'App\\Models\\MaterialConsumptionItem',
            'App\\Models\\MaterialStockAdjustmentItem',
            'App\\Models\\MaterialRequestItem',
            'inventory_transfer',
        ];
        $placeholders = implode(',', array_fill(0, count($known), '?'));
        $this->runSqlCheck(
            'polymorphic.inventory_transactions.unknown_type',
            'Inventory transaction reference types are in the trusted allowlist',
            "select id, reference_type from inventory_transactions where reference_type is not null and reference_type not in ({$placeholders})",
            $known,
            ['id', 'reference_type'],
        );
        $this->runSqlCheck(
            'polymorphic.inventory_transactions.partial_reference',
            'Inventory transaction reference type and ID are both null or both present',
            'select id from inventory_transactions where (reference_type is null and reference_id is not null) or (reference_type is not null and reference_id is null)',
        );
    }

    private function checkRbacModelTypes(): void
    {
        foreach (['model_has_roles', 'model_has_permissions'] as $table) {
            $this->runSqlCheck(
                "rbac.{$table}.unsupported_model_type",
                "{$table} model type is the explicitly supported User model",
                "select model_id id, model_type from {$table} where model_type <> ?",
                ['App\\Models\\User'],
                ['id', 'model_type'],
            );
            $this->runSqlCheck(
                "rbac.{$table}.missing_user",
                "{$table} User subject exists, including soft-deleted users",
                "select p.model_id id from {$table} p left join users u on u.id=p.model_id where p.model_type=? and u.id is null",
                ['App\\Models\\User'],
            );
        }
    }

    /**
     * @param  array<int, mixed>  $bindings
     * @param  array<int, string>  $sampleColumns
     * @param  array<string, mixed>  $meta
     */
    private function runSqlCheck(string $id, string $description, string $sql, array $bindings = [], array $sampleColumns = ['id'], ?int $checkedRows = null, array $meta = []): void
    {
        try {
            $count = (int) ((array) $this->db->selectOne("select count(*) aggregate from ({$sql}) integrity_rows", $bindings))['aggregate'];
            $samples = [];
            if ($count > 0) {
                foreach ($this->db->select("select * from ({$sql}) integrity_rows limit {$this->sampleLimit}", $bindings) as $row) {
                    $values = (array) $row;
                    $samples[] = array_intersect_key($values, array_flip($sampleColumns));
                }
            }
            $this->results[] = array_merge([
                'id' => $id,
                'description' => $description,
                'status' => $count === 0 ? 'PASSED' : 'FAILED',
                'checked_rows' => $checkedRows,
                'violations' => $count,
                'samples' => $samples,
            ], $meta);
        } catch (Throwable $exception) {
            $this->results[] = [
                'id' => $id,
                'description' => $description,
                'status' => 'NOT VERIFIED',
                'checked_rows' => $checkedRows,
                'violations' => 0,
                'samples' => [],
                'reason' => $exception->getMessage(),
            ];
        }
    }

    private function companyLifecycleSql(string $condition): string
    {
        $tables = collect($this->db->select("select table_name from information_schema.columns where table_schema=database() and column_name='company_id' and is_nullable='NO'"))
            ->map(fn ($row): string => $this->identifier($row->table_name))
            ->reject(fn (string $table): bool => $table === 'companies');

        return $tables->map(function (string $table) use ($condition): string {
            $hasId = (bool) $this->db->scalar("select exists(select 1 from information_schema.columns where table_schema=database() and table_name=? and column_name='id')", [$table]);
            $identifier = $hasId ? 'child.id' : 'null id';

            return "select '{$table}' table_name, {$identifier}, child.company_id from `{$table}` child join companies c on c.id=child.company_id where {$condition}";
        })
            ->implode(' union all ');
    }

    /** @return array<int, string> */
    private function requiredTables(): array
    {
        return [
            'companies', 'users', 'departments', 'projects', 'project_user', 'daily_site_reports', 'daily_site_report_workers',
            'site_photos', 'measurement_units', 'material_categories', 'materials', 'project_inventories', 'inventory_transactions',
            'suppliers', 'material_deliveries', 'material_delivery_items', 'material_consumptions', 'material_consumption_items',
            'material_stock_adjustments', 'material_stock_adjustment_items', 'material_requests', 'material_request_items', 'inventory_transfers',
            'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions',
        ];
    }

    private function identifier(string $identifier): string
    {
        if (! preg_match('/^[a-z0-9_]+$/', $identifier)) {
            throw new \RuntimeException('Unsafe schema identifier encountered.');
        }

        return $identifier;
    }

    /** @param array<string, mixed> $payload */
    private function render(array $payload): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->info("Tenant integrity preflight: {$payload['status']} (exit {$payload['exit_code']})");
        $this->line('Connection: '.($payload['database']['connection'] ?? 'unknown'));
        $this->line('Driver: '.($payload['database']['driver'] ?? 'unknown'));
        $this->line('Database: '.($payload['database']['database'] ?? 'unverified'));
        $this->line('Server: '.($payload['database']['server_version'] ?? 'unverified'));
        $this->line('Server read-only: '.(($payload['database']['server_read_only'] ?? false) ? 'yes' : 'no'));
        if (isset($payload['message'])) {
            $this->error($payload['message']);
        }
        foreach ($payload['checks'] as $result) {
            $checked = $result['checked_rows'] === null ? 'n/a' : $result['checked_rows'];
            $this->line("[{$result['status']}] {$result['id']} checked={$checked} violations={$result['violations']}");
            foreach ($result['samples'] as $sample) {
                $this->line('  sample '.json_encode($sample, JSON_UNESCAPED_SLASHES));
            }
            if (isset($result['reason'])) {
                $this->line('  reason '.$result['reason']);
            }
        }
        if (isset($payload['summary'])) {
            $this->newLine();
            $this->line("Checks: {$payload['summary']['checks']}; violations: {$payload['summary']['violations']}; incomplete: {$payload['summary']['incomplete_checks']}");
        }
    }
}
