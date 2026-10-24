<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('onboarding_status', 40)->default('complete')->after('status')->index();
            $table->boolean('email_verification_required')->default(false)->after('onboarding_status');
            $table->unique('email', 'companies_email_unique');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropUnique('companies_email_unique');
            $table->dropIndex(['onboarding_status']);
            $table->dropColumn(['onboarding_status', 'email_verification_required']);
        });
    }
};
