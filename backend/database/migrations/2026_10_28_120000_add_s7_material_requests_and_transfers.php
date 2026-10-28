<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->string('request_number', 80);
            $t->date('required_date');
            $t->string('status', 30)->default('draft');
            $t->text('reason')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['company_id', 'request_number']);
            $t->index(['company_id', 'project_id', 'status']);
        });
        Schema::create('material_request_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('material_request_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_inventory_id')->constrained()->restrictOnDelete();
            $t->foreignId('material_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity_requested', 18, 4);
            $t->decimal('quantity_approved', 18, 4)->nullable();
            $t->decimal('quantity_issued', 18, 4)->default(0);
            $t->decimal('quantity_returned', 18, 4)->default(0);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['material_request_id', 'material_id']);
        });
        Schema::create('inventory_transfers', function (Blueprint $t) {
            $t->id();
            $t->uuid('idempotency_key');
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('source_project_id')->constrained('projects')->restrictOnDelete();
            $t->foreignId('destination_project_id')->constrained('projects')->restrictOnDelete();
            $t->foreignId('source_inventory_id')->constrained('project_inventories')->restrictOnDelete();
            $t->foreignId('destination_inventory_id')->constrained('project_inventories')->restrictOnDelete();
            $t->foreignId('material_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 18, 4);
            $t->date('transfer_date');
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'idempotency_key']);
        });
        Schema::table('inventory_transactions', function (Blueprint $t) {
            $t->foreignId('material_request_item_id')->nullable()->after('stock_adjustment_item_id')->constrained()->restrictOnDelete();
            $t->foreignId('related_transaction_id')->nullable()->after('reference_id')->constrained('inventory_transactions')->nullOnDelete();
            $t->string('idempotency_key', 80)->nullable()->after('related_transaction_id');
            $t->unique(['company_id', 'idempotency_key'], 'inventory_tx_company_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $t) {
            $t->dropUnique('inventory_tx_company_idempotency_unique');
            $t->dropConstrainedForeignId('material_request_item_id');
            $t->dropConstrainedForeignId('related_transaction_id');
            $t->dropColumn('idempotency_key');
        });
        Schema::dropIfExists('inventory_transfers');
        Schema::dropIfExists('material_request_items');
        Schema::dropIfExists('material_requests');
    }
};
