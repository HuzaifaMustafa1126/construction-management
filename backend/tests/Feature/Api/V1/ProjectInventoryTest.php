<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MeasurementUnit;
use App\Models\Project;
use App\Models\ProjectInventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProjectInventoryTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Company $otherCompany;

    private User $admin;

    private User $supervisor;

    private User $unassigned;

    private User $client;

    private User $foreignAdmin;

    private Project $project;

    private Project $secondProject;

    private Project $foreignProject;

    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->otherCompany = Company::factory()->create();
        $this->admin = $this->user($this->company, 'company_admin');
        $this->supervisor = $this->user($this->company, 'site_supervisor');
        $this->unassigned = $this->user($this->company, 'site_supervisor');
        $this->client = $this->user($this->company, 'client');
        $this->foreignAdmin = $this->user($this->otherCompany, 'company_admin');
        $this->project = $this->makeProject($this->company, $this->admin, 'INV-A');
        $this->secondProject = $this->makeProject($this->company, $this->admin, 'INV-A2');
        $this->foreignProject = $this->makeProject($this->otherCompany, $this->foreignAdmin, 'INV-B');
        $this->attach($this->project, $this->supervisor);
        $this->attach($this->project, $this->client, 'client');
        $this->material = $this->makeMaterial($this->company, $this->admin, 'MAT-001', 3);
    }

    public function test_admin_assigns_material_with_server_ownership_zero_stock_and_no_opening_entry(): void
    {
        $response = $this->withToken($this->token($this->admin))->postJson($this->url(), ['material_id' => $this->material->id, 'minimum_stock_level' => '50.000'])->assertCreated()
            ->assertJsonPath('data.current_stock', '0.000')->assertJsonPath('data.opening_stock_recorded', false);
        $this->assertDatabaseHas('project_inventories', ['id' => $response->json('data.id'), 'company_id' => $this->company->id, 'project_id' => $this->project->id, 'created_by' => $this->admin->id]);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_assignment_validation_tenant_status_duplicates_and_independent_projects(): void
    {
        $token = $this->token($this->admin);
        $payload = ['material_id' => $this->material->id, 'minimum_stock_level' => '1.000'];
        $this->withToken($token)->postJson($this->url(), array_merge($payload, ['company_id' => $this->otherCompany->id, 'project_id' => $this->secondProject->id, 'current_stock' => '9']))->assertUnprocessable()->assertJsonValidationErrors(['company_id', 'project_id', 'current_stock']);
        $this->withToken($token)->postJson($this->url(), $payload)->assertCreated();
        $this->withToken($token)->postJson($this->url(), $payload)->assertConflict();
        $this->withToken($token)->postJson("/api/v1/projects/{$this->secondProject->id}/inventory", $payload)->assertCreated();
        $foreign = $this->makeMaterial($this->otherCompany, $this->foreignAdmin, 'FOREIGN', 3);
        $this->withToken($token)->postJson($this->url(), ['material_id' => $foreign->id])->assertUnprocessable()->assertJsonValidationErrors('material_id');
        $inactive = $this->makeMaterial($this->company, $this->admin, 'INACTIVE', 3, false);
        $this->withToken($token)->postJson($this->url(), ['material_id' => $inactive->id])->assertUnprocessable()->assertJsonValidationErrors('material_id');
    }

    public function test_project_access_and_permissions_are_both_enforced(): void
    {
        $this->inventory();
        $this->withToken($this->token($this->supervisor))->getJson($this->url())->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->unassigned))->getJson($this->url())->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->client))->getJson($this->url())->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->admin))->getJson("/api/v1/projects/{$this->foreignProject->id}/inventory")->assertNotFound();
    }

    public function test_opening_stock_is_exact_immutable_server_derived_and_unique(): void
    {
        $inventory = $this->inventory();
        $url = $this->itemUrl($inventory).'/opening-stock';
        $token = $this->token($this->admin);
        foreach (['0', '-1', '1.0001'] as $quantity) {
            $this->withToken($token)->postJson($url, ['quantity' => $quantity, 'transaction_date' => '2026-10-21'])->assertUnprocessable();
        }
        $response = $this->withToken($token)->postJson($url, ['quantity' => '500.125', 'transaction_date' => '2026-10-21', 'notes' => 'Initial'])->assertCreated()->assertJsonPath('data.quantity', '500.125')->assertJsonPath('data.direction', 'in')->assertJsonPath('data.transaction_type', 'opening_stock');
        $tx = InventoryTransaction::findOrFail($response->json('data.id'));
        $this->assertSame($this->company->id, $tx->company_id);
        $this->assertSame($this->project->id, $tx->project_id);
        $this->assertSame($this->material->id, $tx->material_id);
        $this->withToken($token)->postJson($url, ['quantity' => '1.000', 'transaction_date' => '2026-10-21'])->assertConflict();
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->patchJson("/api/v1/inventory-transactions/{$tx->id}", ['quantity' => '2'])->assertNotFound();
        $this->deleteJson("/api/v1/inventory-transactions/{$tx->id}")->assertNotFound();
    }

    public function test_supervisor_needs_explicit_opening_permission_and_client_is_denied(): void
    {
        $inventory = $this->inventory();
        $url = $this->itemUrl($inventory).'/opening-stock';
        $this->withToken($this->token($this->supervisor))->postJson($url, ['quantity' => '2.000', 'transaction_date' => '2026-10-21'])->assertForbidden();
        $this->givePermission($this->supervisor, 'inventory.opening_stock');
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->supervisor))->postJson($url, ['quantity' => '2.000', 'transaction_date' => '2026-10-21'])->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->client))->postJson($url, ['quantity' => '3.000', 'transaction_date' => '2026-10-21'])->assertNotFound();
    }

    public function test_listing_details_filters_history_and_overview_use_ledger_balance(): void
    {
        $inventory = $this->inventory('10.000');
        $otherMaterial = $this->makeMaterial($this->company, $this->admin, 'BRK-001', 0);
        $other = $this->inventory('0', $otherMaterial);
        $this->opening($inventory, '5.125');
        $token = $this->token($this->admin);
        $this->withToken($token)->getJson($this->url().'?search=MAT&material_category_id='.$this->material->material_category_id.'&material_status=1&low_stock=1&per_page=1&sort=material_name')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.current_stock', '5.125');
        $this->withToken($token)->getJson($this->url().'?stock_status=low_stock')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $inventory->id);
        $this->withToken($token)->getJson($this->url().'?stock_status=out_of_stock')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $other->id);
        $this->withToken($token)->getJson($this->url().'?stock_status=good')->assertOk()->assertJsonPath('meta.total', 0);
        $this->withToken($token)->getJson($this->itemUrl($inventory))->assertOk()->assertJsonPath('data.current_stock', '5.125');
        $this->withToken($token)->getJson($this->itemUrl($inventory).'/transactions?transaction_type=opening_stock&date_from=2026-10-01&date_to=2026-10-31')->assertOk()->assertJsonPath('meta.total', 1);
        $this->withToken($token)->getJson($this->url().'/overview')->assertOk()->assertJsonPath('data.total_assigned_materials', 2)->assertJsonPath('data.materials_with_opening_stock', 1)->assertJsonPath('data.materials_without_opening_stock', 1)->assertJsonPath('data.materials_below_minimum_stock', 1)->assertJsonPath('data.materials_with_zero_stock', 1);
        $this->withToken($token)->getJson($this->url().'?sort=stock')->assertUnprocessable();
        $this->assertSame('0.0000', $other->fresh()->minimum_stock_level);
    }

    public function test_minimum_update_only_and_cross_or_wrong_project_inventory_is_hidden(): void
    {
        $inventory = $this->inventory();
        $token = $this->token($this->admin);
        $this->withToken($token)->patchJson($this->itemUrl($inventory), ['minimum_stock_level' => '100.125'])->assertOk()->assertJsonPath('data.minimum_stock_level', '100.125');
        $this->withToken($token)->patchJson($this->itemUrl($inventory), ['minimum_stock_level' => '-1'])->assertUnprocessable();
        $this->withToken($token)->patchJson($this->itemUrl($inventory), ['minimum_stock_level' => '1', 'material_id' => 999, 'project_id' => 999, 'company_id' => 999, 'current_stock' => '99'])->assertUnprocessable()->assertJsonValidationErrors(['material_id', 'project_id', 'company_id', 'current_stock']);
        $this->withToken($token)->getJson("/api/v1/projects/{$this->secondProject->id}/inventory/{$inventory->id}")->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->foreignAdmin))->getJson($this->itemUrl($inventory))->assertNotFound();
    }

    public function test_inventory_events_are_logged_without_secrets(): void
    {
        $response = $this->withToken($this->token($this->admin))->postJson($this->url(), ['material_id' => $this->material->id])->assertCreated();
        $inventory = ProjectInventory::findOrFail($response->json('data.id'));
        $this->withToken($this->token($this->admin))->postJson($this->itemUrl($inventory).'/opening-stock', ['quantity' => '1.000', 'transaction_date' => '2026-10-21'])->assertCreated();
        $this->withToken($this->token($this->admin))->patchJson($this->itemUrl($inventory), ['minimum_stock_level' => '2.000'])->assertOk();
        $events = DB::table('activity_log')->whereIn('event', ['project_inventory.created', 'inventory.opening_stock_recorded', 'project_inventory.minimum_stock_updated'])->pluck('event')->all();
        $this->assertCount(3, $events);
        $properties = DB::table('activity_log')->pluck('properties')->implode(' ');
        $this->assertStringNotContainsString('password', strtolower($properties));
        $this->assertStringNotContainsString('token', strtolower($properties));
    }

    private function user(Company $c, string $role): User
    {
        $u = User::factory()->for($c)->create(['status' => 'Active']);
        $this->grantRole($u, $role);

        return $u;
    }

    private function makeProject(Company $c, User $u, string $code): Project
    {
        return Project::factory()->for($c)->create(['created_by' => $u->id, 'currency' => $c->currency, 'code' => $code]);
    }

    private function attach(Project $p, User $u, string $type = 'supervisor'): void
    {
        $p->members()->attach($u->id, ['company_id' => $p->company_id, 'assignment_type' => $type, 'assigned_by' => $p->created_by, 'assigned_at' => now()]);
    }

    private function makeMaterial(Company $c, User $u, string $code, int $precision, bool $active = true): Material
    {
        $unit = MeasurementUnit::forceCreate(['company_id' => $c->id, 'name' => 'Unit '.$code, 'symbol' => 'u'.strtolower($code), 'unit_type' => 'count', 'decimal_precision' => $precision, 'is_system' => false, 'is_active' => true]);
        $category = MaterialCategory::forceCreate(['company_id' => $c->id, 'name' => 'Category '.$code, 'is_active' => true, 'created_by' => $u->id]);

        return Material::forceCreate(['company_id' => $c->id, 'material_category_id' => $category->id, 'measurement_unit_id' => $unit->id, 'name' => 'Material '.$code, 'code' => $code, 'is_active' => $active, 'created_by' => $u->id]);
    }

    private function inventory(string $minimum = '0', ?Material $material = null): ProjectInventory
    {
        return ProjectInventory::forceCreate(['company_id' => $this->company->id, 'project_id' => $this->project->id, 'material_id' => ($material ?? $this->material)->id, 'minimum_stock_level' => $minimum, 'created_by' => $this->admin->id]);
    }

    private function opening(ProjectInventory $i, string $quantity): InventoryTransaction
    {
        return InventoryTransaction::forceCreate(['company_id' => $i->company_id, 'project_id' => $i->project_id, 'project_inventory_id' => $i->id, 'material_id' => $i->material_id, 'transaction_type' => 'opening_stock', 'quantity' => $quantity, 'direction' => 'in', 'transaction_date' => '2026-10-21', 'created_by' => $this->admin->id]);
    }

    private function givePermission(User $u, string $name): void
    {
        setPermissionsTeamId($u->company_id);
        $u->givePermissionTo(Permission::findOrCreate($name, 'web'));
        $u->unsetRelation('permissions');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        setPermissionsTeamId(null);
    }

    private function token(User $u): string
    {
        return $u->createToken('inventory-test')->plainTextToken;
    }

    private function url(): string
    {
        return "/api/v1/projects/{$this->project->id}/inventory";
    }

    private function itemUrl(ProjectInventory $i): string
    {
        return $this->url()."/{$i->id}";
    }
}
