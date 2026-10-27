<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_consumptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('consumption_number', 80);
            $table->date('consumption_date');
            $table->string('status', 20)->default('draft');
            $table->string('purpose')->nullable();
            $table->string('work_location')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'consumption_number'], 'consumptions_company_number_unique');
            $table->index(['company_id', 'project_id', 'status'], 'consumptions_company_project_status_idx');
            $table->index(['project_id', 'consumption_date'], 'consumptions_project_date_idx');
        });

        Schema::create('material_consumption_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_consumption_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_inventory_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['material_consumption_id', 'project_inventory_id'], 'consumption_items_inventory_unique');
            $table->index(['company_id', 'material_id'], 'consumption_items_company_material_idx');
        });

        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->foreignId('consumption_item_id')->nullable()->after('delivery_item_id')->constrained('material_consumption_items')->restrictOnDelete();
            $table->unique('consumption_item_id', 'inventory_tx_consumption_item_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->dropUnique('inventory_tx_consumption_item_unique');
            $table->dropConstrainedForeignId('consumption_item_id');
        });
        Schema::dropIfExists('material_consumption_items');
        Schema::dropIfExists('material_consumptions');
    }
};
