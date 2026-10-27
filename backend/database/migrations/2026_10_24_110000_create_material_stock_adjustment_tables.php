<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_stock_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('adjustment_number', 80);
            $table->string('status', 20)->default('draft');
            $table->date('adjustment_date');
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'adjustment_number'], 'stock_adjustments_company_number_unique');
            $table->index(['company_id', 'project_id', 'status'], 'stock_adjustments_company_project_status_idx');
            $table->index(['project_id', 'adjustment_date'], 'stock_adjustments_project_date_idx');
        });

        Schema::create('material_stock_adjustment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_stock_adjustment_id')->constrained('material_stock_adjustments', indexName: 'stock_adjustment_items_document_fk')->cascadeOnDelete();
            $table->foreignId('project_inventory_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('adjustment_type', 40);
            $table->decimal('quantity', 18, 4);
            $table->string('reason_code', 50);
            $table->text('notes')->nullable();
            $table->text('correction_explanation')->nullable();
            $table->foreignId('reference_inventory_transaction_id')->nullable()->constrained('inventory_transactions', indexName: 'stock_adjustment_items_reference_tx_fk')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['material_stock_adjustment_id', 'project_inventory_id'], 'stock_adjustment_items_inventory_unique');
            $table->index(['company_id', 'material_id'], 'stock_adjustment_items_company_material_idx');
            $table->index('reference_inventory_transaction_id', 'stock_adjustment_items_reference_idx');
        });

        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->foreignId('stock_adjustment_item_id')->nullable()->after('consumption_item_id')->constrained('material_stock_adjustment_items')->restrictOnDelete();
            $table->unique('stock_adjustment_item_id', 'inventory_tx_stock_adjustment_item_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->dropUnique('inventory_tx_stock_adjustment_item_unique');
            $table->dropConstrainedForeignId('stock_adjustment_item_id');
        });
        Schema::dropIfExists('material_stock_adjustment_items');
        Schema::dropIfExists('material_stock_adjustments');
    }
};
