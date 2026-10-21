<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialDelivery;
use App\Models\MaterialDeliveryItem;
use App\Models\MeasurementUnit;
use App\Models\Project;
use App\Models\ProjectInventory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaterialDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;

    private Company $b;

    private User $admin;

    private User $foreignAdmin;

    private User $supervisor;

    private User $unassigned;

    private User $client;

    private Project $project;

    private Project $otherProject;

    private Project $foreignProject;

    private Supplier $supplier;

    private ProjectInventory $inventory;

    private ProjectInventory $inventory2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Company::factory()->create();
        $this->b = Company::factory()->create();
        $this->admin = $this->user($this->a, 'company_admin');
        $this->foreignAdmin = $this->user($this->b, 'company_admin');
        $this->supervisor = $this->user($this->a, 'site_supervisor');
        $this->unassigned = $this->user($this->a, 'site_supervisor');
        $this->client = $this->user($this->a, 'client');
        $this->project = $this->project($this->a, $this->admin, 'DEL-A');
        $this->otherProject = $this->project($this->a, $this->admin, 'DEL-A2');
        $this->foreignProject = $this->project($this->b, $this->foreignAdmin, 'DEL-B');
        $this->attach($this->project, $this->supervisor, 'supervisor');
        $this->attach($this->project, $this->client, 'client');
        $this->supplier = $this->supplier($this->a, $this->admin);
        $this->inventory = $this->inventory($this->project, $this->material($this->a, $this->admin, 'CEM-1', 3));
        $this->inventory2 = $this->inventory($this->project, $this->material($this->a, $this->admin, 'PCS-1', 0));
    }

    public function test_admin_creates_multi_item_draft_without_stock_and_number_is_unique(): void
    {
        $r = $this->withToken($this->token($this->admin))->postJson($this->url(), $this->payload())->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonCount(2, 'data.items');
        $d = MaterialDelivery::findOrFail($r->json('data.id'));
        $this->assertNotEmpty($d->delivery_number);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $r2 = $this->withToken($this->token($this->admin))->postJson($this->url(), $this->payload())->assertCreated();
        $this->assertNotSame($d->delivery_number, $r2->json('data.delivery_number'));
    }

    public function test_draft_validation_rejects_ownership_cross_project_quantity_precision_and_duplicates(): void
    {
        $foreignSupplier = $this->supplier($this->b, $this->foreignAdmin);
        $foreignInventory = $this->inventory($this->foreignProject, $this->material($this->b, $this->foreignAdmin, 'F-1', 2));
        $token = $this->token($this->admin);
        $this->withToken($token)->postJson($this->url(), array_merge($this->payload(), ['supplier_id' => $foreignSupplier->id]))->assertUnprocessable()->assertJsonValidationErrors('supplier_id');
        $this->withToken($token)->postJson($this->url(), array_merge($this->payload(), ['items' => [['project_inventory_id' => $foreignInventory->id, 'quantity' => '1.00']]]))->assertUnprocessable()->assertJsonValidationErrors('items.0.project_inventory_id');
        foreach (['0', '-1', '1.0001'] as $q) {
            $this->withToken($token)->postJson($this->url(), array_merge($this->payload(), ['items' => [['project_inventory_id' => $this->inventory->id, 'quantity' => $q]]]))->assertUnprocessable();
        }$this->withToken($token)->postJson($this->url(), array_merge($this->payload(), ['company_id' => $this->b->id, 'status' => 'received', 'delivery_number' => 'X']))->assertUnprocessable()->assertJsonValidationErrors(['company_id', 'status', 'delivery_number']);
    }

    public function test_project_permissions_tenant_and_wrong_project_hiding(): void
    {
        $d = $this->draft();
        $this->withToken($this->token($this->supervisor))->getJson($this->url())->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->unassigned))->getJson($this->url())->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->client))->getJson($this->url())->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->foreignAdmin))->getJson($this->itemUrl($d))->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->admin))->getJson("/api/v1/projects/{$this->otherProject->id}/material-deliveries/{$d->id}")->assertNotFound();
    }

    public function test_draft_updates_and_cancels_but_terminal_deliveries_are_immutable(): void
    {
        $d = $this->draft();
        $token = $this->token($this->admin);
        $this->withToken($token)->patchJson($this->itemUrl($d), array_merge($this->payload(), ['vehicle_number' => 'NEW']))->assertOk()->assertJsonPath('data.vehicle_number', 'NEW');
        $this->withToken($token)->postJson($this->itemUrl($d).'/cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->withToken($token)->patchJson($this->itemUrl($d), $this->payload())->assertConflict();
        $this->withToken($token)->postJson($this->itemUrl($d).'/cancel')->assertConflict();
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_receipt_posts_one_incoming_transaction_per_item_and_updates_stock(): void
    {
        $d = $this->draft();
        $this->opening($this->inventory, '10.000');
        $response = $this->withToken($this->token($this->admin))->postJson($this->itemUrl($d).'/receive', ['received_at' => '2026-10-21T14:30:00+05:00', 'notes' => 'Verified'])->assertOk()->assertJsonPath('data.status', 'received')->assertJsonCount(2, 'transaction_ids');
        $this->assertDatabaseHas('material_deliveries', ['id' => $d->id, 'received_by' => $this->admin->id, 'status' => 'received']);
        $this->assertDatabaseHas('inventory_transactions', ['project_inventory_id' => $this->inventory->id, 'transaction_type' => 'material_received', 'direction' => 'in', 'quantity' => '2.5000']);
        $this->withToken($this->token($this->admin))->getJson("/api/v1/projects/{$this->project->id}/inventory/{$this->inventory->id}")->assertOk()->assertJsonPath('data.current_stock', '12.500');
        $this->withToken($this->token($this->admin))->getJson($this->itemUrl($d))->assertOk()->assertJsonPath('data.items.0.inventory_transaction_ids.0', $response->json('transaction_ids.0'));
        $this->withToken($this->token($this->admin))->postJson($this->itemUrl($d).'/receive', ['received_at' => now()->toIso8601String()])->assertConflict();
        $this->assertDatabaseCount('inventory_transactions', 3);
    }

    public function test_receipt_without_opening_and_multiple_deliveries_accumulate_independently(): void
    {
        $one = $this->draft([['project_inventory_id' => $this->inventory->id, 'quantity' => '2.000']]);
        $two = $this->draft([['project_inventory_id' => $this->inventory->id, 'quantity' => '3.000']]);
        $token = $this->token($this->admin);
        foreach ([$one, $two] as $d) {
            $this->withToken($token)->postJson($this->itemUrl($d).'/receive', ['received_at' => now()->toIso8601String()])->assertOk();
        }$this->withToken($token)->getJson("/api/v1/projects/{$this->project->id}/inventory/{$this->inventory->id}")->assertJsonPath('data.current_stock', '5.000');
        $otherMaterial = $this->material($this->a, $this->admin, 'OTHER', 3);
        $otherInv = $this->inventory($this->otherProject, $otherMaterial);
        $this->assertSame('0.0000', $otherInv->minimum_stock_level);
    }

    public function test_database_source_uniqueness_and_failed_receipt_roll_back(): void
    {
        $d = $this->draft();
        $first = $d->items()->orderBy('id')->firstOrFail();
        $this->receivedTx($first);
        $response = $this->withToken($this->token($this->admin))->postJson($this->itemUrl($d).'/receive', ['received_at' => now()->toIso8601String()])->assertConflict();
        $this->assertSame('draft', $d->fresh()->status);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertNull($d->fresh()->posted_at);
    }

    public function test_listing_filters_details_history_and_activity_logs(): void
    {
        $d = $this->draft();
        $token = $this->token($this->admin);
        $this->withToken($token)->getJson($this->url()."?supplier_id={$this->supplier->id}&status=draft&date_from=2026-10-01&date_to=2026-10-31&search=".urlencode($d->delivery_number).'&per_page=1')->assertOk()->assertJsonPath('meta.total', 1);
        $this->withToken($token)->postJson($this->itemUrl($d).'/receive', ['received_at' => now()->toIso8601String()])->assertOk();
        $this->supplier->delete();
        $this->withToken($token)->getJson($this->itemUrl($d))->assertOk()->assertJsonPath('data.supplier.id', $this->supplier->id);
        $events = DB::table('activity_log')->whereIn('event', ['material_delivery.created', 'material_delivery.received'])->pluck('event')->all();
        $this->assertContains('material_delivery.created', $events);
        $this->assertContains('material_delivery.received', $events);
    }

    private function user(Company $c, string $role): User
    {
        $u = User::factory()->for($c)->create(['status' => 'Active']);
        $this->grantRole($u, $role);

        return $u;
    }

    private function project(Company $c, User $u, string $code): Project
    {
        return Project::factory()->for($c)->create(['created_by' => $u->id, 'currency' => $c->currency, 'code' => $code]);
    }

    private function attach(Project $p, User $u, string $type): void
    {
        $p->members()->attach($u->id, ['company_id' => $p->company_id, 'assignment_type' => $type, 'assigned_by' => $p->created_by, 'assigned_at' => now()]);
    }

    private function supplier(Company $c, User $u): Supplier
    {
        return Supplier::forceCreate(['company_id' => $c->id, 'name' => 'Supplier '.uniqid(), 'is_active' => true, 'created_by' => $u->id]);
    }

    private function material(Company $c, User $u, string $code, int $precision): Material
    {
        $unit = MeasurementUnit::forceCreate(['company_id' => $c->id, 'name' => 'U'.$code, 'symbol' => 'u'.strtolower($code), 'unit_type' => 'count', 'decimal_precision' => $precision, 'is_system' => false, 'is_active' => true]);
        $cat = MaterialCategory::forceCreate(['company_id' => $c->id, 'name' => 'C'.$code, 'is_active' => true, 'created_by' => $u->id]);

        return Material::forceCreate(['company_id' => $c->id, 'material_category_id' => $cat->id, 'measurement_unit_id' => $unit->id, 'name' => 'M'.$code, 'code' => $code, 'is_active' => true, 'created_by' => $u->id]);
    }

    private function inventory(Project $p, Material $m): ProjectInventory
    {
        return ProjectInventory::forceCreate(['company_id' => $p->company_id, 'project_id' => $p->id, 'material_id' => $m->id, 'minimum_stock_level' => '0', 'created_by' => $p->created_by]);
    }

    private function payload(?array $items = null): array
    {
        return ['supplier_id' => $this->supplier->id, 'delivery_date' => '2026-10-21', 'supplier_invoice_number' => 'INV-1', 'vehicle_number' => 'ABC-1', 'items' => $items ?? [['project_inventory_id' => $this->inventory->id, 'quantity' => '2.500'], ['project_inventory_id' => $this->inventory2->id, 'quantity' => '3']]];
    }

    private function draft(?array $items = null): MaterialDelivery
    {
        $r = $this->withToken($this->token($this->admin))->postJson($this->url(), $this->payload($items))->assertCreated();

        return MaterialDelivery::findOrFail($r->json('data.id'));
    }

    private function opening(ProjectInventory $i, string $q): void
    {
        InventoryTransaction::forceCreate(['company_id' => $i->company_id, 'project_id' => $i->project_id, 'project_inventory_id' => $i->id, 'material_id' => $i->material_id, 'transaction_type' => 'opening_stock', 'quantity' => $q, 'direction' => 'in', 'transaction_date' => '2026-10-21', 'created_by' => $this->admin->id]);
    }

    private function receivedTx(MaterialDeliveryItem $item): void
    {
        InventoryTransaction::forceCreate(['company_id' => $item->company_id, 'project_id' => $item->delivery->project_id, 'project_inventory_id' => $item->project_inventory_id, 'material_id' => $item->material_id, 'delivery_item_id' => $item->id, 'transaction_type' => 'material_received', 'quantity' => $item->quantity, 'direction' => 'in', 'transaction_date' => '2026-10-21', 'created_by' => $this->admin->id]);
    }

    private function token(User $u): string
    {
        return $u->createToken('delivery-test')->plainTextToken;
    }

    private function url(): string
    {
        return "/api/v1/projects/{$this->project->id}/material-deliveries";
    }

    private function itemUrl(MaterialDelivery $d): string
    {
        return $this->url()."/{$d->id}";
    }
}
