<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialStockAdjustment;
use App\Models\MaterialStockAdjustmentItem;
use App\Models\MeasurementUnit;
use App\Models\Project;
use App\Models\ProjectInventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReportingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $foreignCompany;

    private User $admin;

    private User $supervisor;

    private User $unassigned;

    private User $client;

    private User $foreignAdmin;

    private Project $project;

    private Project $secondProject;

    private Project $foreignProject;

    private ProjectInventory $inventory;

    private ProjectInventory $emptyInventory;

    private ProjectInventory $negativeInventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->foreignCompany = Company::factory()->create();
        $this->admin = $this->user($this->company, 'company_admin');
        $this->supervisor = $this->user($this->company, 'site_supervisor');
        $this->unassigned = $this->user($this->company, 'site_supervisor');
        $this->client = $this->user($this->company, 'client');
        $this->foreignAdmin = $this->user($this->foreignCompany, 'company_admin');
        $this->project = $this->project($this->company, $this->admin, 'REP-A');
        $this->secondProject = $this->project($this->company, $this->admin, 'REP-A2');
        $this->foreignProject = $this->project($this->foreignCompany, $this->foreignAdmin, 'REP-B');
        $this->attach($this->project, $this->supervisor, 'supervisor');
        $this->attach($this->project, $this->client, 'client');
        $this->inventory = $this->inventory($this->project, $this->material($this->company, $this->admin, 'CEM', 4), '14.0000');
        $this->emptyInventory = $this->inventory($this->project, $this->material($this->company, $this->admin, 'PCS', 0), '0');
        $this->negativeInventory = $this->inventory($this->project, $this->material($this->company, $this->admin, 'NEG', 2), '2.00');
        $this->seedMovements();
    }

    public function test_current_stock_aggregates_every_ledger_type_without_double_counting(): void
    {
        $response = $this->getReport('current-stock')->assertOk()->assertJsonPath('meta.total', 3);
        $cement = collect($response->json('data'))->firstWhere('material.code', 'CEM');
        $this->assertSame('10.0000', $cement['opening_stock']);
        $this->assertSame('5.0000', $cement['received']);
        $this->assertSame('2.0000', $cement['consumed']);
        $this->assertSame('1.0000', $cement['adjustment_in']);
        $this->assertSame('0.5000', $cement['adjustment_out']);
        $this->assertSame('0.2500', $cement['wastage']);
        $this->assertSame('0.1000', $cement['correction_in']);
        $this->assertSame('0.0500', $cement['correction_out']);
        $this->assertSame('13.3000', $cement['current_stock']);
        $this->assertSame('low_stock', $cement['stock_status']);
    }

    public function test_historical_period_has_opening_period_and_closing_balances(): void
    {
        $response = $this->getReport('current-stock?start_date=2026-10-01&end_date=2026-10-04')->assertOk();
        $cement = collect($response->json('data'))->firstWhere('material.code', 'CEM');
        $this->assertSame('10.0000', $cement['opening_balance']);
        $this->assertSame('6.0000', $cement['period_in']);
        $this->assertSame('2.5000', $cement['period_out']);
        $this->assertSame('13.5000', $cement['closing_balance']);
        $this->getReport('current-stock?start_date=2026-10-10&end_date=2026-10-01')->assertUnprocessable();
    }

    public function test_reconciliation_reports_exact_signed_ledger_balance_without_mutation(): void
    {
        $response = $this->getReport('reconciliation?per_page=100')->assertOk()->assertJsonPath('meta.total', 3);
        $cement = collect($response->json('data'))->firstWhere('material.code', 'CEM');
        $this->assertSame('13.3000', $cement['available_stock']);
        $this->assertSame('13.3000', $cement['ledger_sum']);
        $this->assertSame('0.0000', $cement['difference']);
        $this->assertTrue($cement['is_reconciled']);
        $this->assertDatabaseCount('inventory_transactions', 9);
    }

    public function test_overview_and_dynamic_alerts_classify_healthy_empty_and_negative_stock(): void
    {
        $this->getReport('overview')->assertOk()->assertJsonPath('data.total_active_inventory_items', 3)->assertJsonPath('data.low_stock_count', 1)->assertJsonPath('data.out_of_stock_count', 1)->assertJsonPath('data.negative_stock_anomaly_count', 1)->assertJsonCount(9, 'data.recent_movements');
        $response = $this->getReport('alerts')->assertOk()->assertJsonCount(3, 'data');
        $alerts = collect($response->json('data'))->keyBy('material.code');
        $this->assertSame('warning', $alerts['CEM']['severity']);
        $this->assertSame('critical', $alerts['PCS']['severity']);
        $this->assertSame('negative_stock_anomaly', $alerts['NEG']['alert_type']);
    }

    public function test_movements_support_filters_pagination_sources_and_archived_materials(): void
    {
        $this->inventory->material->delete();
        $response = $this->getReport('movements?transaction_type=material_wastage&direction=out&start_date=2026-10-05&end_date=2026-10-05&per_page=1')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.material.code', 'CEM')->assertJsonPath('data.0.source_document_type', 'stock_adjustment')->assertJsonPath('data.0.reason', 'damage');
        $this->getReport("movements?material_id={$this->inventory->material_id}")->assertOk()->assertJsonPath('meta.total', 8);
        $this->getReport('movements?source_document=opening_stock')->assertOk()->assertJsonPath('data.0.transaction_type', 'opening_stock');
    }

    public function test_received_consumed_and_wastage_reports_keep_units_and_categories_separate(): void
    {
        $response = $this->getReport('received-vs-consumed')->assertOk();
        $cement = collect($response->json('data'))->firstWhere('material.code', 'CEM');
        $this->assertSame('5.0000', $cement['received']);
        $this->assertSame('2.0000', $cement['consumed']);
        $this->assertSame('3.0000', $cement['difference']);
        $this->assertSame('0.2500', $cement['wastage']);
        $this->assertSame('0.5500', $cement['adjustment_movement']);
        $this->assertSame('13.3000', $cement['current_stock']);
        $this->getReport('wastage?reason_code=damage')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.quantity', '0.2500')->assertJsonPath('data.0.adjustment.number', 'ADJ-REPORT');
    }

    public function test_company_reports_are_tenant_scoped_and_project_aware(): void
    {
        $otherInventory = $this->inventory($this->secondProject, $this->material($this->company, $this->admin, 'OTH', 2), '1.00');
        $this->tx($otherInventory, 'opening_stock', '2.00', 'in', '2026-10-01');
        $foreignInventory = $this->inventory($this->foreignProject, $this->material($this->foreignCompany, $this->foreignAdmin, 'FOR', 2), '1.00');
        $this->tx($foreignInventory, 'opening_stock', '2.00', 'in', '2026-10-01', $this->foreignAdmin);
        $token = $this->token($this->admin);
        $this->withToken($token)->getJson('/api/v1/inventory-reports/overview')->assertOk()->assertJsonPath('data.total_projects_with_inventory', 2)->assertJsonPath('data.total_active_inventory_items', 4);
        $this->withToken($token)->getJson('/api/v1/inventory-reports/projects')->assertOk()->assertJsonCount(2, 'data');
        $this->withToken($token)->getJson('/api/v1/inventory-reports/alerts')->assertOk();
    }

    public function test_security_enforces_authentication_permissions_project_assignment_and_tenants(): void
    {
        $this->withToken($this->token($this->supervisor))->getJson($this->url('overview'))->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->unassigned))->getJson($this->url('overview'))->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->client))->getJson($this->url('overview'))->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->foreignAdmin))->getJson($this->url('overview'))->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->supervisor))->getJson('/api/v1/inventory-reports/overview')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->getJson($this->url('overview'))->assertUnauthorized();
        $this->withToken($this->token($this->admin))->getJson($this->url('overview').'?company_id='.$this->foreignCompany->id)->assertUnprocessable();
    }

    private function seedMovements(): void
    {
        $this->tx($this->inventory, 'opening_stock', '10.0000', 'in', '2026-09-30');
        $this->tx($this->inventory, 'material_received', '5.0000', 'in', '2026-10-01');
        $this->tx($this->inventory, 'material_consumed', '2.0000', 'out', '2026-10-02');
        $this->tx($this->inventory, 'adjustment_in', '1.0000', 'in', '2026-10-03');
        $this->tx($this->inventory, 'adjustment_out', '0.5000', 'out', '2026-10-04');
        $adjustment = MaterialStockAdjustment::forceCreate(['company_id' => $this->company->id, 'project_id' => $this->project->id, 'adjustment_number' => 'ADJ-REPORT', 'status' => 'posted', 'adjustment_date' => '2026-10-05', 'created_by' => $this->admin->id, 'posted_by' => $this->admin->id, 'posted_at' => now()]);
        $item = MaterialStockAdjustmentItem::forceCreate(['company_id' => $this->company->id, 'material_stock_adjustment_id' => $adjustment->id, 'project_inventory_id' => $this->inventory->id, 'material_id' => $this->inventory->material_id, 'adjustment_type' => 'material_wastage', 'quantity' => '0.2500', 'reason_code' => 'damage']);
        $this->tx($this->inventory, 'material_wastage', '0.2500', 'out', '2026-10-05', null, ['stock_adjustment_item_id' => $item->id]);
        $this->tx($this->inventory, 'inventory_correction_in', '0.1000', 'in', '2026-10-06');
        $this->tx($this->inventory, 'inventory_correction_out', '0.0500', 'out', '2026-10-07');
        $this->tx($this->negativeInventory, 'material_consumed', '1.00', 'out', '2026-10-01');
    }

    private function user(Company $company, string $role): User
    {
        $user = User::factory()->for($company)->create(['status' => 'Active']);
        $this->grantRole($user, $role);

        return $user;
    }

    private function project(Company $company, User $user, string $code): Project
    {
        return Project::factory()->for($company)->create(['created_by' => $user->id, 'currency' => $company->currency, 'code' => $code]);
    }

    private function attach(Project $project, User $user, string $type): void
    {
        $project->members()->attach($user->id, ['company_id' => $project->company_id, 'assignment_type' => $type, 'assigned_by' => $project->created_by, 'assigned_at' => now()]);
    }

    private function material(Company $company, User $user, string $code, int $precision): Material
    {
        $unit = MeasurementUnit::forceCreate(['company_id' => $company->id, 'name' => 'U'.$code, 'symbol' => strtolower($code), 'unit_type' => 'count', 'decimal_precision' => $precision, 'is_system' => false, 'is_active' => true]);
        $category = MaterialCategory::forceCreate(['company_id' => $company->id, 'name' => 'C'.$code, 'is_active' => true, 'created_by' => $user->id]);

        return Material::forceCreate(['company_id' => $company->id, 'material_category_id' => $category->id, 'measurement_unit_id' => $unit->id, 'name' => 'M'.$code, 'code' => $code, 'is_active' => true, 'created_by' => $user->id]);
    }

    private function inventory(Project $project, Material $material, string $minimum): ProjectInventory
    {
        return ProjectInventory::forceCreate(['company_id' => $project->company_id, 'project_id' => $project->id, 'material_id' => $material->id, 'minimum_stock_level' => $minimum, 'created_by' => $project->created_by]);
    }

    private function tx(ProjectInventory $inventory, string $type, string $quantity, string $direction, string $date, ?User $actor = null, array $extra = []): InventoryTransaction
    {
        return InventoryTransaction::forceCreate(array_merge(['company_id' => $inventory->company_id, 'project_id' => $inventory->project_id, 'project_inventory_id' => $inventory->id, 'material_id' => $inventory->material_id, 'transaction_type' => $type, 'quantity' => $quantity, 'direction' => $direction, 'transaction_date' => $date, 'created_by' => ($actor ?? $this->admin)->id], $extra));
    }

    private function token(User $user): string
    {
        return $user->createToken('inventory-report-test')->plainTextToken;
    }

    private function url(string $report): string
    {
        return "/api/v1/projects/{$this->project->id}/inventory-reports/{$report}";
    }

    private function getReport(string $report)
    {
        return $this->withToken($this->token($this->admin))->getJson($this->url($report));
    }
}
