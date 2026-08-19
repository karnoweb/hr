<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One current primary employee position per employee (HR-030).
 *
 * current_key = employee_id only when is_primary=1 AND end_date IS NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::table($prefix.'employee_positions', function (Blueprint $table) {
            $table->unsignedBigInteger('current_key')->nullable()->after('end_date');
            $table->unique('current_key');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::table($prefix.'employee_positions', function (Blueprint $table) {
            $table->dropUnique(['current_key']);
            $table->dropColumn('current_key');
        });
    }
};
