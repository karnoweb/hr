<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enforce exactly one current salary per employee (HR-069).
 *
 * current_key = employee_id only when is_current = true; NULL otherwise.
 *
 * @see docs/concepts/architecture.md
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::table($prefix.'employee_salaries', function (Blueprint $table) {
            $table->unsignedBigInteger('current_key')->nullable()->after('is_current');
            $table->unique('current_key');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::table($prefix.'employee_salaries', function (Blueprint $table) {
            $table->dropUnique(['current_key']);
            $table->dropColumn('current_key');
        });
    }
};
