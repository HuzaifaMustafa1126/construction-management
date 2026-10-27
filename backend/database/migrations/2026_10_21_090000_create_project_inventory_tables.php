<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_inventories')) {
            Schema::create('project_inventories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->foreignId('project_id')->constrained()->restrictOnDelete();
                $table->foreignId('material_id')->constrained()->restrictOnDelete();
                $table->decimal('minimum_stock_level', 18, 4)->default(0);
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->unsignedBigInteger('active_material_id')->nullable()
                    ->virtualAs('case when deleted_at is null then material_id else null end');
                $table->unique(['project_id', 'active_material_id'], 'project_inventories_active_material_unique');
                $table->index(['company_id', 'project_id'], 'project_inventory_company_project_idx');
                $table->index(['company_id', 'material_id'], 'project_inventory_company_material_idx');
            });
        }

        $transactionsAlreadyExist = Schema::hasTable('inventory_transactions');
        if (! $transactionsAlreadyExist) {
            Schema::create('inventory_transactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->restrictOnDelete();
                $table->foreignId('project_id')->constrained()->restrictOnDelete();
                $table->foreignId('project_inventory_id')->constrained()->restrictOnDelete();
                $table->foreignId('material_id')->constrained()->restrictOnDelete();
                $table->string('transaction_type', 40);
                $table->decimal('quantity', 18, 4);
                $table->string('direction', 10);
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->date('transaction_date');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
                $table->unsignedBigInteger('opening_stock_inventory_id')->nullable()
                    ->virtualAs("case when transaction_type = 'opening_stock' then project_inventory_id else null end");
                $table->unique('opening_stock_inventory_id', 'inventory_transactions_one_opening_stock_unique');
                $table->index(['company_id', 'project_id', 'transaction_date'], 'inventory_tx_company_project_date_idx');
                $table->index(['project_inventory_id', 'transaction_type'], 'inventory_tx_inventory_type_idx');
            });
        } else {
            // MariaDB DDL is not transactional. This safely completes a table left
            // behind if an earlier migration attempt failed while adding indexes.
            Schema::table('inventory_transactions', function (Blueprint $table): void {
                $table->index(['company_id', 'project_id', 'transaction_date'], 'inventory_tx_company_project_date_idx');
                $table->index(['project_inventory_id', 'transaction_type'], 'inventory_tx_inventory_type_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('project_inventories');
    }
};
