<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_site_report_workers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('daily_site_report_id')->constrained()->cascadeOnDelete();
            $table->string('worker_type', 30);
            $table->unsignedInteger('workers_present');
            $table->timestamps();
            $table->unique(['daily_site_report_id', 'worker_type'], 'dsr_workers_report_type_unique');
            $table->index(['company_id', 'daily_site_report_id'], 'dsr_workers_company_report_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_site_report_workers');
    }
};
