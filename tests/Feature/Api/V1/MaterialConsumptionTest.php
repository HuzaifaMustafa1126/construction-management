<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialConsumption;
use App\Models\MeasurementUnit;
use App\Models\Project;
use App\Models\ProjectInventory;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MaterialConsumptionTest extends TestCase
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

    private Project $otherProject;

    private Project $foreignProject;

    private ProjectInventory $inventory;

    private ProjectInventory $inventory2;

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
        $this->project = $this->project($this->company, $this->admin, 'CON-A');
        $this->otherProject = $this->project($this->company, $this->admin, 'CON-A2');
        $this->foreignProject = $this->project($this->foreignCompany, $this->foreignAdmin, 'CON-B');
        $this->attach($this->project, $this->supervisor, 'supervisor');
        $this->attach($this->project, $this->client, 'client');
        $this->inventory = $this->inventory($this->project, $this->material($this->company, $this->admin, 'CEM', 3));
        $this->inventory2 = $this->inventory($this->project, $this->material($this->company, $this->admin, 'PCS', 0));
        $this->stock($this->inventory, '100.0000', 'opening_stock');
        $this->stock($this->inventory, '20.0000', 'material_received');
        $this->stock($this->inventory2, '10.0000', 'opening_stock');
    }

    public function test_admin_creates_and_updates_draft_without_changing_stock(): void
    {
        $before = InventoryTransaction::count();
        $response = $this->withToken($this->token($this->admin))->postJson($this->url(), $this->payload())->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonCount(2, 'data.items');
        $consumption = MaterialConsumption::findOrFail($response->json('data.id'));
        $this->assertNotEmpty($consumption->consumption_number);
        $this->assertSame($before, InventoryTransaction::count());
        $this->withToken($this->token($this->admin))->patchJson($this->itemUrl($consumption), ['purpose' => 'Updated', 'items' => [['project_inventory_id' => $this->inventory->id, 'quantity' => '2.125']]])->assertOk()->assertJsonPath('data.purpose', 'Updated');
        $this->assertSame($before, InventoryTransaction::count());
        $second = $this->draft();
        $this->assertNotSame($consumption->consumption_number, $second->consumption_number);
    }

    public function test_validation_rejects_wrong_inventory_duplicates_precision_and_protected_fields(): void
    {
        $foreignInventory = $this->inventory($this->foreignProject, $this->material($this->foreignCompany, $this->foreignAdmin, 'FOR', 2));
        $token = $this->token($this->admin);
        $this->withToken($token)->postJson($this->url(), $this->payload([['project_inventory_id' => $foreignInventory->id, 'quantity' => '1']]))->assertUnprocessable()->assertJsonValidationErrors('items.0.project_inventory_id');
        foreach (['0', '-1', '1.0001'] as $quantity) {
            $this->withToken($token)->postJson($this->url(), $this->payload([['project_inventory_id' => $this->inventory->id, 'quantity' => $quantity]]))->assertUnprocessable();
        }
        $this->withToken($token)->postJson($this->url(), $this->payload([['project_inventory_id' => $this->inventory->id, 'quantity' => '1'], ['project_inventory_id' => $this->inventory->id, 'quantity' => '2']]))->assertUnprocessable()->assertJsonValidationErrors('items.1.project_inventory_id');
        $this->withToken($token)->postJson($this->url(), array_merge($this->payload(), ['company_id' => 999, 'project_id' => 999, 'status' => 'posted', 'recorded_by' => 999]))->assertUnprocessable()->assertJsonValidationErrors(['company_id', 'project_id', 'status', 'recorded_by']);
    }

    public function test_project_assignment_permissions_and_tenant_hiding_are_enforced(): void
    {
        $this->givePermission($this->supervisor, 'material_consumptions.create');
        $this->givePermission($this->supervisor, 'material_consumptions.post');
        $this->withToken($this->token($this->supervisor))->postJson($this->url(), $this->payload())->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->givePermission($this->unassigned, 'material_consumptions.create');
        $this->withToken($this->token($this->unassigned))->postJson($this->url(), $this->payload())->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->client))->getJson($this->url())->assertForbidden();
        $this->app['auth']->forgetGuards();
        $consumption = $this->draft();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->foreignAdmin))->getJson($this->itemUrl($consumption))->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->admin))->getJson("/api/v1/projects/{$this->otherProject->id}/material-consumptions/{$consumption->id}")->assertNotFound();
    }

    public function test_posting_deducts_exact_stock_and_is_idempotent(): void
    {
        $consumption = $this->draft([['project_inventory_id' => $this->inventory->id, 'quantity' => '20.125']]);
        $response = $this->withToken($this->token($this->admin))->postJson($this->itemUrl($consumption).'/post')->assertOk()->assertJsonPath('data.status', 'posted')->assertJsonCount(1, 'transaction_ids');
        $this->assertDatabaseHas('inventory_transactions', ['consumption_item_id' => $consumption->items()->first()->id, 'transaction_type' => 'material_consumed', 'direction' => 'out', 'quantity' => '20.1250']);
        $this->withToken($this->token($this->admin))->getJson("/api/v1/projects/{$this->project->id}/inventory/{$this->inventory->id}")->assertOk()->assertJsonPath('data.current_stock', '99.875');
        $this->withToken($this->token($this->admin))->postJson($this->itemUrl($consumption).'/post')->assertConflict();
        $this->assertDatabaseCount('inventory_transactions', 4);
        $this->withToken($this->token($this->admin))->getJson($this->itemUrl($consumption).'/transactions')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $response->json('transaction_ids.0'));
    }

    public function test_insufficient_stock_rolls_back_every_item_and_never_goes_negative(): void
    {
        $consumption = $this->draft([['project_inventory_id' => $this->inventory->id, 'quantity' => '1.000'], ['project_inventory_id' => $this->inventory2->id, 'quantity' => '11']]);
        $before = InventoryTransaction::count();
        $this->withToken($this->token($this->admin))->postJson($this->itemUrl($consumption).'/post')->assertUnprocessable()->assertJsonPath('message', 'Insufficient stock. No material was consumed.');
        $this->assertSame($before, InventoryTransaction::count());
        $this->assertSame('draft', $consumption->fresh()->status);
        $this->assertNull($consumption->fresh()->posted_at);
    }

    public function test_terminal_documents_are_immutable_and_cancel_is_stock_neutral(): void
    {
        $cancelled = $this->draft();
        $before = InventoryTransaction::count();
        $this->withToken($this->token($this->admin))->postJson($this->itemUrl($cancelled).'/cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->withToken($this->token($this->admin))->patchJson($this->itemUrl($cancelled), ['purpose' => 'No'])->assertConflict();
        $this->assertSame($before, InventoryTransaction::count());
        $posted = $this->draft([['project_inventory_id' => $this->inventory->id, 'quantity' => '1.000']]);
        $this->withToken($this->token($this->admin))->postJson($this->itemUrl($posted).'/post')->assertOk();
        $this->withToken($this->token($this->admin))->patchJson($this->itemUrl($posted), ['purpose' => 'No'])->assertConflict();
        $this->withToken($this->token($this->admin))->postJson($this->itemUrl($posted).'/cancel')->assertConflict();
    }

    public function test_database_source_uniqueness_blocks_duplicate_ledger_posting(): void
    {
        $consumption = $this->draft([['project_inventory_id' => $this->inventory->id, 'quantity' => '1.000']]);
        $this->withToken($this->token($this->admin))->postJson($this->itemUrl($consumption).'/post')->assertOk();
        $transaction = InventoryTransaction::whereNotNull('consumption_item_id')->firstOrFail();
        $this->expectException(UniqueConstraintViolationException::class);
        InventoryTransaction::forceCreate($transaction->only(['company_id', 'project_id', 'project_inventory_id', 'material_id', 'consumption_item_id', 'transaction_type', 'quantity', 'direction', 'transaction_date', 'created_by']));
    }

    public function test_filters_pagination_summary_history_and_activity_are_scoped(): void
    {
        $draft = $this->draft();
        $posted = $this->draft([['project_inventory_id' => $this->inventory->id, 'quantity' => '2.500']]);
        $token = $this->token($this->admin);
        $this->withToken($token)->postJson($this->itemUrl($posted).'/post')->assertOk();
        $this->withToken($token)->getJson($this->url().'?status=draft&date_from=2026-10-01&date_to=2026-10-31&search='.urlencode($draft->consumption_number).'&per_page=1')->assertOk()->assertJsonPath('meta.total', 1);
        $this->withToken($token)->getJson($this->url().'?sort=invalid')->assertUnprocessable();
        $this->withToken($token)->getJson($this->url().'?per_page=101')->assertUnprocessable();
        $this->withToken($token)->getJson($this->url().'/summary')->assertOk()->assertJsonPath('data.total_documents', 2)->assertJsonPath('data.draft_documents', 1)->assertJsonPath('data.posted_documents', 1)->assertJsonPath('data.consumed_by_material.0.quantity', '2.500');
        $events = DB::table('activity_log')->whereIn('event', ['material_consumption.created', 'material_consumption.posted'])->pluck('event')->all();
        $this->assertContains('material_consumption.created', $events);
        $this->assertContains('material_consumption.posted', $events);
    }

    public function test_soft_deleted_material_remains_in_historical_document(): void
    {
        $consumption = $this->draft([['project_inventory_id' => $this->inventory->id, 'quantity' => '1.000']]);
        $this->withToken($this->token($this->admin))->postJson($this->itemUrl($consumption).'/post')->assertOk();
        $this->inventory->material->delete();
        $this->withToken($this->token($this->admin))->getJson($this->itemUrl($consumption))->assertOk()->assertJsonPath('data.items.0.material.code', 'CEM');
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

    private function inventory(Project $project, Material $material): ProjectInventory
    {
        return ProjectInventory::forceCreate(['company_id' => $project->company_id, 'project_id' => $project->id, 'material_id' => $material->id, 'minimum_stock_level' => '0', 'created_by' => $project->created_by]);
    }

    private function stock(ProjectInventory $inventory, string $quantity, string $type): void
    {
        InventoryTransaction::forceCreate(['company_id' => $inventory->company_id, 'project_id' => $inventory->project_id, 'project_inventory_id' => $inventory->id, 'material_id' => $inventory->material_id, 'transaction_type' => $type, 'quantity' => $quantity, 'direction' => 'in', 'transaction_date' => '2026-10-20', 'created_by' => $this->admin->id]);
    }

    private function payload(?array $items = null): array
    {
        return ['consumption_date' => '2026-10-23', 'purpose' => 'Foundation', 'work_location' => 'Section A', 'items' => $items ?? [['project_inventory_id' => $this->inventory->id, 'quantity' => '2.500'], ['project_inventory_id' => $this->inventory2->id, 'quantity' => '3']]];
    }

    private function draft(?array $items = null): MaterialConsumption
    {
        $response = $this->withToken($this->token($this->admin))->postJson($this->url(), $this->payload($items))->assertCreated();

        return MaterialConsumption::findOrFail($response->json('data.id'));
    }

    private function token(User $user): string
    {
        return $user->createToken('consumption-test')->plainTextToken;
    }

    private function givePermission(User $user, string $name): void
    {
        setPermissionsTeamId($user->company_id);
        $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        $user->unsetRelation('permissions');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        setPermissionsTeamId(null);
    }

    private function url(): string
    {
        return "/api/v1/projects/{$this->project->id}/material-consumptions";
    }

    private function itemUrl(MaterialConsumption $consumption): string
    {
        return $this->url()."/{$consumption->id}";
    }
}
