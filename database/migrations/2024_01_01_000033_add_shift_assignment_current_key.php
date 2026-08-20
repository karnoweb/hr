<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enforce at most one active shift assignment per employee (HR-039).
 *
 * Uses the current_key pattern documented in docs/concepts/architecture.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        $table = $prefix.'employee_shift_assignments';

        Schema::table($table, function (Blueprint $table) {
            $table->unsignedBigInteger('current_key')->nullable()->after('is_active');
            $table->unique('current_key');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::table($prefix.'employee_shift_assignments', function (Blueprint $table) {
            $table->dropUnique(['current_key']);
            $table->dropColumn('current_key');
        });
    }
};
