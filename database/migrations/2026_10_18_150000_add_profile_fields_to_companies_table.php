<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('state', 100)->nullable()->after('city');
            $table->string('postal_code', 30)->nullable()->after('country');
            $table->string('website')->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['state', 'postal_code', 'website']);
        });
    }
};
