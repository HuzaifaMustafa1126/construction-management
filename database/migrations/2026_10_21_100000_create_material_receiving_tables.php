<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('contact_person')->nullable();
            $t->string('phone', 50)->nullable();
            $t->string('email')->nullable();
            $t->text('address')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['company_id', 'is_active'], 'suppliers_company_active_idx');
            $t->index(['company_id', 'name'], 'suppliers_company_name_idx');
        });
        Schema::create('material_deliveries', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->string('delivery_number', 80);
            $t->string('supplier_invoice_number')->nullable();
            $t->date('delivery_date');
            $t->timestamp('received_at')->nullable();
            $t->string('status', 20)->default('draft');
            $t->string('vehicle_number', 100)->nullable();
            $t->text('delivery_note')->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('posted_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['company_id', 'delivery_number'], 'deliveries_company_number_unique');
            $t->index(['company_id', 'project_id', 'status'], 'deliveries_company_project_status_idx');
            $t->index(['project_id', 'delivery_date'], 'deliveries_project_date_idx');
        });
        Schema::create('material_delivery_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('material_delivery_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_inventory_id')->constrained()->restrictOnDelete();
            $t->foreignId('material_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 18, 4);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['material_delivery_id', 'project_inventory_id'], 'delivery_items_inventory_unique');
            $t->index(['company_id', 'material_id'], 'delivery_items_company_material_idx');
        });
        Schema::table('inventory_transactions', function (Blueprint $t): void {
            $t->foreignId('delivery_item_id')->nullable()->after('material_id')->constrained('material_delivery_items')->restrictOnDelete();
            $t->unique('delivery_item_id', 'inventory_tx_delivery_item_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $t): void {
            $t->dropUnique('inventory_tx_delivery_item_unique');
            $t->dropConstrainedForeignId('delivery_item_id');
        });
        Schema::dropIfExists('material_delivery_items');
        Schema::dropIfExists('material_deliveries');
        Schema::dropIfExists('suppliers');
    }
};
