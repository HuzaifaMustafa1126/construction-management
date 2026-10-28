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
use Tests\TestCase;

class MaterialRequestsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    private Project $project;

    private ProjectInventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->for($this->company)->create(['status' => 'Active']);
        $this->grantRole($this->admin, 'company_admin');
        $this->project = Project::factory()->for($this->company)->create(['created_by' => $this->admin->id, 'currency' => $this->company->currency]);
        $category = MaterialCategory::forceCreate(['company_id' => $this->company->id, 'name' => 'Cement', 'is_active' => true, 'created_by' => $this->admin->id]);
        $unit = MeasurementUnit::forceCreate(['company_id' => null, 'name' => 'Bag', 'symbol' => 'bag', 'unit_type' => 'count', 'decimal_precision' => 0, 'is_system' => true, 'is_active' => true]);
        $material = Material::forceCreate(['company_id' => $this->company->id, 'name' => 'Cement', 'code' => 'CEM', 'material_category_id' => $category->id, 'measurement_unit_id' => $unit->id, 'is_active' => true, 'created_by' => $this->admin->id]);
        $this->inventory = ProjectInventory::forceCreate(['company_id' => $this->company->id, 'project_id' => $this->project->id, 'material_id' => $material->id, 'minimum_stock_level' => 10, 'created_by' => $this->admin->id]);
        InventoryTransaction::forceCreate(['company_id' => $this->company->id, 'project_id' => $this->project->id, 'project_inventory_id' => $this->inventory->id, 'material_id' => $material->id, 'transaction_type' => 'opening_stock', 'quantity' => 100, 'direction' => 'in', 'transaction_date' => '2026-10-28', 'created_by' => $this->admin->id]);
    }

    public function test_approval_does_not_deduct_and_partial_issue_deducts_once(): void
    {
        $token = $this->admin->createToken('s7')->plainTextToken;
        $created = $this->withToken($token)->postJson('/api/v1/material-requests', ['project_id' => $this->project->id, 'required_date' => '2026-10-29', 'items' => [['project_inventory_id' => $this->inventory->id, 'quantity_requested' => '60']]])->assertCreated();
        $id = $created->json('data.id');
        $item = $created->json('data.items.0.id');
        $this->withToken($token)->postJson("/api/v1/material-requests/$id/submit")->assertOk();
        $this->withToken($token)->postJson("/api/v1/material-requests/$id/approve", ['items' => [['id' => $item, 'quantity_approved' => '60']]])->assertOk();
        $this->assertSame('100.0000', $this->balance());
        $this->withToken($token)->postJson("/api/v1/material-requests/$id/issue", ['idempotency_key' => '5a72ea90-c151-4d52-9e0a-38dcb7302e11', 'issue_date' => '2026-10-29', 'items' => [['id' => $item, 'quantity' => '25']]])->assertOk()->assertJsonPath('data.status', 'partially_issued');
        $this->assertSame('75.0000', $this->balance());
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->withToken($token)->postJson("/api/v1/material-requests/$id/issue", ['idempotency_key' => '5a72ea90-c151-4d52-9e0a-38dcb7302e11', 'issue_date' => '2026-10-29', 'items' => [['id' => $item, 'quantity' => '25']]])->assertOk();
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertSame('75.0000', $this->balance());
    }

    public function test_return_increases_stock_once_and_cannot_exceed_issued_quantity(): void
    {
        [$requestId, $itemId, $token] = $this->approvedRequest('20');

        $this->withToken($token)->postJson("/api/v1/material-requests/$requestId/issue", [
            'idempotency_key' => '709b9694-ddde-4d7d-a103-91cdf04b2432',
            'issue_date' => '2026-10-29',
            'items' => [['id' => $itemId, 'quantity' => '20']],
        ])->assertOk();

        $payload = [
            'idempotency_key' => 'a8ba1108-4775-4be0-aa52-8c807e6df7f1',
            'return_date' => '2026-10-30',
            'item_id' => $itemId,
            'quantity' => '7.5',
        ];
        $this->withToken($token)->postJson("/api/v1/material-requests/$requestId/return", $payload)->assertOk();
        $this->withToken($token)->postJson("/api/v1/material-requests/$requestId/return", $payload)->assertOk();

        $this->assertSame('87.5000', $this->balance());
        $this->assertDatabaseCount('inventory_transactions', 3);
        $this->withToken($token)->postJson("/api/v1/material-requests/$requestId/return", [
            'idempotency_key' => '961ca83c-4bba-4be4-bdb7-3a6701b5a960',
            'return_date' => '2026-10-30',
            'item_id' => $itemId,
            'quantity' => '13',
        ])->assertStatus(422);
    }

    public function test_transfer_creates_atomic_pair_and_preserves_total_stock(): void
    {
        $destinationProject = Project::factory()->for($this->company)->create([
            'created_by' => $this->admin->id,
            'currency' => $this->company->currency,
        ]);
        $destination = ProjectInventory::forceCreate([
            'company_id' => $this->company->id,
            'project_id' => $destinationProject->id,
            'material_id' => $this->inventory->material_id,
            'minimum_stock_level' => 0,
            'created_by' => $this->admin->id,
        ]);
        $token = $this->admin->createToken('transfer')->plainTextToken;
        $payload = [
            'idempotency_key' => '5ce0be07-2a1b-4bc0-beab-d638144e37cb',
            'source_project_id' => $this->project->id,
            'destination_project_id' => $destinationProject->id,
            'source_inventory_id' => $this->inventory->id,
            'destination_inventory_id' => $destination->id,
            'quantity' => '35.25',
            'transfer_date' => '2026-10-29',
        ];

        $this->withToken($token)->postJson('/api/v1/inventory-transfers', $payload)->assertCreated();
        $this->withToken($token)->postJson('/api/v1/inventory-transfers', $payload)->assertOk();

        $this->assertSame('64.7500', $this->balance());
        $destinationBalance = (string) InventoryTransaction::where('project_inventory_id', $destination->id)
            ->selectRaw("coalesce(sum(case when direction='in' then quantity else -quantity end),0) b")
            ->value('b');
        $this->assertSame('35.2500', $destinationBalance);
        $this->assertSame('100.0000', (string) InventoryTransaction::whereIn('project_inventory_id', [$this->inventory->id, $destination->id])
            ->selectRaw("sum(case when direction='in' then quantity else -quantity end) b")
            ->value('b'));
        $pair = InventoryTransaction::whereIn('transaction_type', ['transfer_out', 'transfer_in'])->orderBy('id')->get();
        $this->assertCount(2, $pair);
        $this->assertSame($pair[1]->id, $pair[0]->related_transaction_id);
        $this->assertSame($pair[0]->id, $pair[1]->related_transaction_id);
    }

    private function approvedRequest(string $quantity): array
    {
        $token = $this->admin->createToken('approved-request')->plainTextToken;
        $created = $this->withToken($token)->postJson('/api/v1/material-requests', [
            'project_id' => $this->project->id,
            'required_date' => '2026-10-29',
            'items' => [['project_inventory_id' => $this->inventory->id, 'quantity_requested' => $quantity]],
        ])->assertCreated();
        $requestId = $created->json('data.id');
        $itemId = $created->json('data.items.0.id');
        $this->withToken($token)->postJson("/api/v1/material-requests/$requestId/submit")->assertOk();
        $this->withToken($token)->postJson("/api/v1/material-requests/$requestId/approve", [
            'items' => [['id' => $itemId, 'quantity_approved' => $quantity]],
        ])->assertOk();

        return [$requestId, $itemId, $token];
    }

    private function balance(): string
    {
        return (string) InventoryTransaction::where('project_inventory_id', $this->inventory->id)->selectRaw("sum(case when direction='in' then quantity else -quantity end) b")->value('b');
    }
}
