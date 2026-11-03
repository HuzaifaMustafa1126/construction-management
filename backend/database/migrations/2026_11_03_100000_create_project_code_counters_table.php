<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_code_counters', function (Blueprint $table): void {
            $table->foreignId('company_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });

        $now = now();
        DB::table('companies')->orderBy('id')->each(function (object $company) use ($now): void {
            $next = 1;
            DB::table('projects')->where('company_id', $company->id)->pluck('code')->each(
                function (string $code) use (&$next): void {
                    if (preg_match('/^PRJ-(\d+)$/i', $code, $matches)) {
                        $next = max($next, ((int) $matches[1]) + 1);
                    }
                },
            );
            DB::table('project_code_counters')->insert([
                'company_id' => $company->id, 'next_number' => $next,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_code_counters');
    }
};
