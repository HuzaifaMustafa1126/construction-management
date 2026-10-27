<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurement_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('symbol', 30);
            $table->string('unit_type', 30);
            $table->unsignedTinyInteger('decimal_precision')->default(2);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('uniqueness_company_id')->virtualAs('coalesce(company_id, 0)');
            $table->string('active_normalized_symbol')->nullable()->virtualAs('case when deleted_at is null then lower(trim(symbol)) else null end');
            $table->unique(['uniqueness_company_id', 'active_normalized_symbol'], 'measurement_units_active_symbol_unique');
            $table->index(['company_id', 'is_active']);
            $table->index(['is_system', 'is_active']);
        });

        Schema::create('material_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->string('active_normalized_name')->nullable()->virtualAs('case when deleted_at is null then lower(trim(name)) else null end');
            $table->unique(['company_id', 'active_normalized_name'], 'material_categories_active_name_unique');
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('measurement_unit_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 100);
            $table->text('description')->nullable();
            $table->text('specification')->nullable();
            $table->string('brand')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->string('active_normalized_code', 100)->nullable()->virtualAs('case when deleted_at is null then lower(trim(code)) else null end');
            $table->unique(['company_id', 'active_normalized_code'], 'materials_active_code_unique');
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'material_category_id']);
            $table->index(['company_id', 'measurement_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
        Schema::dropIfExists('material_categories');
        Schema::dropIfExists('measurement_units');
    }
};
