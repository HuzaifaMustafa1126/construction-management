<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('daily_site_report_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('caption')->nullable();
            $table->text('description')->nullable();
            $table->date('photo_date');
            $table->string('time_category', 20);
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('storage_disk', 40)->default('local');
            $table->string('storage_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('approval_status', 20)->default('pending');
            $table->boolean('is_client_visible')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'photo_date']);
            $table->index(['project_id', 'approval_status', 'is_client_visible']);
            $table->index(['daily_site_report_id', 'deleted_at']);
            $table->unique(['storage_disk', 'storage_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_photos');
    }
};
