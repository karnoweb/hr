<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scope catalog `code` uniqueness by branch_id (HR-023).
 *
 * Pre-flight: if upgrading an existing database, ensure no duplicate (branch_id, code)
 * pairs exist before running this migration. Fresh installs are safe.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'departments',
        'positions',
        'shifts',
        'shift_patterns',
        'salary_items',
        'salary_structures',
    ];

    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        foreach ($this->tables as $suffix) {
            Schema::table($prefix.$suffix, function (Blueprint $table) {
                $table->dropUnique(['code']);
                $table->unique(['branch_id', 'code']);
            });
        }
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        foreach ($this->tables as $suffix) {
            Schema::table($prefix.$suffix, function (Blueprint $table) {
                $table->dropUnique(['branch_id', 'code']);
                $table->unique(['code']);
            });
        }
    }
};
