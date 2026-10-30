<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SECURE_DEFINITION = <<<'SQL'
        ALTER TABLE `staff_invitations`
        MODIFY `expires_at` TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00'
        SQL;

    public function up(): void
    {
        DB::statement(self::SECURE_DEFINITION);
    }

    public function down(): void
    {
        // Forward-only security correction: never restore MariaDB's implicit
        // DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP behavior.
        DB::statement(self::SECURE_DEFINITION);
    }
};
