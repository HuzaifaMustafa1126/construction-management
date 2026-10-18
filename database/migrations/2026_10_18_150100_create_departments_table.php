<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name', 125);
            $table->string('code', 50);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('Active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->string('active_name', 125)->nullable()
                ->virtualAs('case when deleted_at is null then lower(name) else null end');
            $table->string('active_code', 50)->nullable()
                ->virtualAs('case when deleted_at is null then lower(code) else null end');
            $table->unique(['company_id', 'active_name']);
            $table->unique(['company_id', 'active_code']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
