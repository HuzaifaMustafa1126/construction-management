<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_site_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->date('report_date');
            $table->string('title');
            $table->text('work_completed');
            $table->text('work_in_progress')->nullable();
            $table->text('issues')->nullable();
            $table->text('delays')->nullable();
            $table->text('delay_reason')->nullable();
            $table->string('weather_conditions')->nullable();
            $table->text('general_notes')->nullable();
            $table->unsignedInteger('total_workers_present')->default(0);
            $table->unsignedTinyInteger('progress_percentage')->nullable();
            $table->string('status', 25)->default('draft');
            $table->boolean('is_client_visible')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->date('active_report_date')->nullable()
                ->virtualAs('case when deleted_at is null then report_date else null end');
            $table->unique(['project_id', 'active_report_date']);
            $table->index(['company_id', 'project_id', 'status']);
            $table->index(['project_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_site_reports');
    }
};
