<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('email');
            $table->char('pending_email_hash', 64)->nullable()->unique();
            $table->char('token_hash', 64)->nullable()->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedInteger('send_count')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'email']);
            $table->index(['company_id', 'created_at']);
            $table->index(
                ['company_id', 'accepted_at', 'cancelled_at', 'expired_at'],
                'staff_invitations_company_state_index',
            );
        });

        Schema::create('staff_invitation_roles', function (Blueprint $table): void {
            $table->foreignId('staff_invitation_id')->constrained('staff_invitations')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('role_name', 125);
            $table->timestamps();

            $table->unique(['staff_invitation_id', 'role_name'], 'staff_invitation_roles_name_unique');
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_invitation_roles');
        Schema::dropIfExists('staff_invitations');
    }
};
