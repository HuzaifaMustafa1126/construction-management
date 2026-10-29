<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REFERENCES = [
        'projects' => 'created_by',
        'daily_site_reports' => 'created_by',
        'site_photos' => 'uploaded_by',
        'material_categories' => 'created_by',
        'materials' => 'created_by',
        'project_inventories' => 'created_by',
        'inventory_transactions' => 'created_by',
        'suppliers' => 'created_by',
        'material_deliveries' => 'created_by',
        'material_consumptions' => 'created_by',
        'material_stock_adjustments' => 'created_by',
        'material_requests' => 'requested_by',
        'inventory_transfers' => 'created_by',
    ];

    public function up(): void
    {
        foreach (self::REFERENCES as $tableName => $columnName) {
            Schema::table($tableName, function (Blueprint $table) use ($columnName): void {
                $table->dropForeign([$columnName]);
                $table->unsignedBigInteger($columnName)->nullable()->change();
                $table->foreign($columnName)->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::REFERENCES as $tableName => $columnName) {
            if (DB::table($tableName)->whereNull($columnName)->exists()) {
                throw new LogicException("Cannot restore {$tableName}.{$columnName} to NOT NULL while retained records have no user attribution.");
            }

            Schema::table($tableName, function (Blueprint $table) use ($columnName): void {
                $table->dropForeign([$columnName]);
                $table->unsignedBigInteger($columnName)->nullable(false)->change();
                $table->foreign($columnName)->references('id')->on('users')->restrictOnDelete();
            });
        }
    }
};
