<?php

namespace Tests\Feature\Api\V1;

use App\Models\Company;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialStockAdjustment;
use App\Models\MeasurementUnit;
use App\Models\Project;
use App\Models\ProjectInventory;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaterialStockAdjustmentTest extends TestCase
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

    private InventoryTransaction $opening;

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
        $this->project = $this->project($this->company, $this->admin, 'ADJ-A');
        $this->otherProject = $this->project($this->company, $this->admin, 'ADJ-A2');
        $this->foreignProject = $this->project($this->foreignCompany, $this->foreignAdmin, 'ADJ-B');
        $this->attach($this->project, $this->supervisor, 'supervisor');
        $this->attach($this->project, $this->client, 'client');
        $this->inventory = $this->inventory($this->project, $this->material($this->company, $this->admin, 'CEM', 4));
        $this->inventory2 = $this->inventory($this->project, $this->material($this->company, $this->admin, 'PCS', 0));
        $this->opening = $this->stock($this->inventory, '20.0000', 'opening_stock', 'in');
        $this->stock($this->inventory2, '10.0000', 'opening_stock', 'in');
    }

    public function test_draft_lifecycle_is_stock_neutral_and_terminal_documents_are_immutable(): void
    {
        $before = InventoryTransaction::count();
        $adjustment = $this->draft();
        $this->assertSame($before, InventoryTransaction::count());
        $token = $this->token($this->admin);
        $this->withToken($token)->patchJson($this->itemUrl($adjustment), ['reason' => 'Updated', 'items' => [$this->item('adjustment_in', '1.2500')]])->assertOk()->assertJsonPath('data.reason', 'Updated');
        $this->assertSame($before, InventoryTransaction::count());
        $this->withToken($token)->postJson($this->itemUrl($adjustment).'/cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->withToken($token)->patchJson($this->itemUrl($adjustment), ['reason' => 'No'])->assertConflict();
        $this->withToken($token)->postJson($this->itemUrl($adjustment).'/post')->assertConflict();
        $this->assertSame($before, InventoryTransaction::count());
    }

    public function test_all_five_types_map_to_correct_immutable_ledger_directions(): void
    {
        $types = ['adjustment_in' => 'in', 'adjustment_out' => 'out', 'material_wastage' => 'out', 'inventory_correction_in' => 'in', 'inventory_correction_out' => 'out'];
        foreach ($types as $type => $direction) {
            $item = $this->item($type, '1.0000');
            if (str_starts_with($type, 'inventory_correction')) {
                $item['reference_inventory_transaction_id'] = $this->opening->id;
                $item['correction_explanation'] = 'Compensates for the original counting error.';
                $item['reason_code'] = 'recording_error';
            }
            $adjustment = $this->draft([$item]);
            $this->withToken($this->token($this->admin))->postJson($this->itemUrl($adjustment).'/post')->assertOk();
            $this->assertDatabaseHas('inventory_transactions', ['stock_adjustment_item_id' => $adjustment->items()->first()->id, 'transaction_type' => $type, 'direction' => $direction, 'quantity' => '1.0000']);
        }
        $this->assertSame('posted', $adjustment->fresh()->status);
        $this->withToken($this->token($this->admin))->patchJson($this->itemUrl($adjustment), ['reason' => 'No'])->assertConflict();
        $this->withToken($this->token($this->admin))->postJson($this->itemUrl($adjustment).'/cancel')->assertConflict();
        $this->assertDatabaseHas('inventory_transactions', ['id' => $this->opening->id, 'transaction_type' => 'opening_stock', 'quantity' => '20.0000']);
    }

    public function test_outgoing_stock_is_checked_atomically_and_never_becomes_negative(): void
    {
        $adjustment = $this->draft([$this->item('material_wastage', '20.0001')]);
        $before = InventoryTransaction::count();
        $this->withToken($this->token($this->admin))->postJson($this->itemUrl($adjustment).'/post')->assertUnprocessable()->assertJsonPath('message', 'Insufficient stock. No adjustment was posted.');
        $this->assertSame($before, InventoryTransaction::count());
        $this->assertSame('draft', $adjustment->fresh()->status);
        $multi = $this->draft([$this->item('adjustment_out', '1.0000'), ['project_inventory_id' => $this->inventory2->id, 'adjustment_type' => 'material_wastage', 'quantity' => '11', 'reason_code' => 'damage']]);
        $this->withToken($this->token($this->admin))->postJson($this->itemUrl($multi).'/post')->assertUnprocessable();
        $this->assertSame('draft', $multi->fresh()->status);
        $this->assertDatabaseMissing('inventory_transactions', ['stock_adjustment_item_id' => $multi->items()->first()->id]);
    }

    public function test_correction_reference_rules_reject_missing_cross_tenant_and_wrong_inventory(): void
    {
        $foreignInventory = $this->inventory($this->foreignProject, $this->material($this->foreignCompany, $this->foreignAdmin, 'FOR', 4));
        $foreignTx = $this->stock($foreignInventory, '5.0000', 'opening_stock', 'in', $this->foreignAdmin);
        $token = $this->token($this->admin);
        $missing = $this->item('inventory_correction_in', '1.0000');
        $this->withToken($token)->postJson($this->url(), $this->payload([$missing]))->assertUnprocessable()->assertJsonValidationErrors(['items.0.reference_inventory_transaction_id', 'items.0.correction_explanation']);
        $cross = array_merge($missing, ['reference_inventory_transaction_id' => $foreignTx->id, 'correction_explanation' => 'Correction']);
        $this->withToken($token)->postJson($this->url(), $this->payload([$cross]))->assertUnprocessable()->assertJsonValidationErrors('items.0.reference_inventory_transaction_id');
        $wrong = array_merge($missing, ['reference_inventory_transaction_id' => InventoryTransaction::where('project_inventory_id', $this->inventory2->id)->firstOrFail()->id, 'correction_explanation' => 'Correction']);
        $this->withToken($token)->postJson($this->url(), $this->payload([$wrong]))->assertUnprocessable()->assertJsonValidationErrors('items.0.reference_inventory_transaction_id');
        $nonCorrection = array_merge($this->item('adjustment_in', '1.0000'), ['reference_inventory_transaction_id' => $this->opening->id]);
        $this->withToken($token)->postJson($this->url(), $this->payload([$nonCorrection]))->assertUnprocessable();
    }

    public function test_strict_validation_rejects_inventory_quantity_reason_duplicates_and_protected_fields(): void
    {
        $foreignInventory = $this->inventory($this->foreignProject, $this->material($this->foreignCompany, $this->foreignAdmin, 'FOR2', 2));
        $token = $this->token($this->admin);
        foreach (['0', '-1', '1.00001'] as $quantity) {
            $this->withToken($token)->postJson($this->url(), $this->payload([$this->item('adjustment_in', $quantity)]))->assertUnprocessable();
        }
        $this->withToken($token)->postJson($this->url(), $this->payload([array_merge($this->item(), ['project_inventory_id' => $foreignInventory->id])]))->assertUnprocessable()->assertJsonValidationErrors('items.0.project_inventory_id');
        $this->withToken($token)->postJson($this->url(), $this->payload([$this->item('invalid')]))->assertUnprocessable()->assertJsonValidationErrors('items.0.adjustment_type');
        $this->withToken($token)->postJson($this->url(), $this->payload([array_merge($this->item(), ['reason_code' => 'invalid'])]))->assertUnprocessable();
        $this->withToken($token)->postJson($this->url(), $this->payload([array_merge($this->item(), ['reason_code' => 'other'])]))->assertUnprocessable()->assertJsonValidationErrors('items.0.notes');
        $this->withToken($token)->postJson($this->url(), $this->payload([$this->item(), $this->item('adjustment_out')]))->assertUnprocessable()->assertJsonValidationErrors('items.1.project_inventory_id');
        $this->withToken($token)->postJson($this->url(), array_merge($this->payload(), ['company_id' => 99, 'status' => 'posted', 'posted_by' => 99]))->assertUnprocessable()->assertJsonValidationErrors(['company_id', 'status', 'posted_by']);
    }

    public function test_permissions_project_assignment_clients_and_tenant_hiding_are_enforced(): void
    {
        $this->withToken($this->token($this->supervisor))->postJson($this->url(), $this->payload())->assertCreated();
        $adjustment = MaterialStockAdjustment::latest('id')->firstOrFail();
        $this->withToken($this->token($this->supervisor))->postJson($this->itemUrl($adjustment).'/post')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->unassigned))->getJson($this->url())->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->client))->getJson($this->url())->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->foreignAdmin))->getJson($this->itemUrl($adjustment))->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($this->admin))->getJson("/api/v1/projects/{$this->otherProject->id}/stock-adjustments/{$adjustment->id}")->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->getJson($this->url())->assertUnauthorized();
    }

    public function test_duplicate_posting_and_source_uniqueness_prevent_duplicate_movements(): void
    {
        $adjustment = $this->draft([$this->item('adjustment_out', '2.0000')]);
        $token = $this->token($this->admin);
        $this->withToken($token)->postJson($this->itemUrl($adjustment).'/post')->assertOk();
        $this->withToken($token)->postJson($this->itemUrl($adjustment).'/post')->assertConflict();
        $this->assertDatabaseCount('inventory_transactions', 3);
        $transaction = InventoryTransaction::whereNotNull('stock_adjustment_item_id')->firstOrFail();
        $this->expectException(UniqueConstraintViolationException::class);
        InventoryTransaction::forceCreate($transaction->only(['company_id', 'project_id', 'project_inventory_id', 'material_id', 'stock_adjustment_item_id', 'transaction_type', 'quantity', 'direction', 'transaction_date', 'created_by']));
    }

    public function test_list_history_summary_decimal_balance_and_activity_logs_are_correct(): void
    {
        $draft = $this->draft([$this->item('adjustment_in', '0.0001')]);
        $posted = $this->draft([$this->item('material_wastage', '1.2500')]);
        $token = $this->token($this->admin);
        $this->withToken($token)->postJson($this->itemUrl($posted).'/post')->assertOk();
        $this->withToken($token)->getJson($this->url().'?status=draft&search='.urlencode($draft->adjustment_number).'&per_page=1')->assertOk()->assertJsonPath('meta.total', 1);
        $this->withToken($token)->getJson($this->url().'/history')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.items.0.direction', 'out');
        $this->withToken($token)->getJson($this->url().'/summary')->assertOk()->assertJsonPath('data.draft_documents', 1)->assertJsonPath('data.posted_documents', 1)->assertJsonPath('data.breakdown.0.quantity', '1.2500');
        $this->withToken($token)->getJson("/api/v1/projects/{$this->project->id}/inventory/{$this->inventory->id}")->assertOk()->assertJsonPath('data.current_stock', '18.7500');
        $events = DB::table('activity_log')->whereIn('event', ['material_stock_adjustment.created', 'material_stock_adjustment.posted'])->pluck('event')->all();
        $this->assertContains('material_stock_adjustment.created', $events);
        $this->assertContains('material_stock_adjustment.posted', $events);
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

    private function stock(ProjectInventory $inventory, string $quantity, string $type, string $direction, ?User $actor = null): InventoryTransaction
    {
        return InventoryTransaction::forceCreate(['company_id' => $inventory->company_id, 'project_id' => $inventory->project_id, 'project_inventory_id' => $inventory->id, 'material_id' => $inventory->material_id, 'transaction_type' => $type, 'quantity' => $quantity, 'direction' => $direction, 'transaction_date' => '2026-10-24', 'created_by' => ($actor ?? $this->admin)->id]);
    }

    private function item(string $type = 'adjustment_in', string $quantity = '1.0000'): array
    {
        return ['project_inventory_id' => $this->inventory->id, 'adjustment_type' => $type, 'quantity' => $quantity, 'reason_code' => $type === 'material_wastage' ? 'damage' : 'physical_count_difference'];
    }

    private function payload(?array $items = null): array
    {
        return ['adjustment_date' => '2026-10-24', 'reason' => 'Physical reconciliation', 'notes' => 'Counted on site', 'items' => $items ?? [$this->item()]];
    }

    private function draft(?array $items = null): MaterialStockAdjustment
    {
        $response = $this->withToken($this->token($this->admin))->postJson($this->url(), $this->payload($items))->assertCreated();

        return MaterialStockAdjustment::findOrFail($response->json('data.id'));
    }

    private function token(User $user): string
    {
        return $user->createToken('stock-adjustment-test')->plainTextToken;
    }

    private function url(): string
    {
        return "/api/v1/projects/{$this->project->id}/stock-adjustments";
    }

    private function itemUrl(MaterialStockAdjustment $adjustment): string
    {
        return $this->url()."/{$adjustment->id}";
    }
}
