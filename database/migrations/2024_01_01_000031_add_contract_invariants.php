<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One active contract per employee (HR-027) + unique contract_number (HR-029).
 *
 * Uses the current_key / active_key pattern from docs/concepts/architecture.md:
 * active_key = employee_id only while status is active; NULL otherwise.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::table($prefix.'contracts', function (Blueprint $table) {
            $table->unsignedBigInteger('active_key')->nullable()->after('status');
            $table->unique('active_key');
            $table->unique('contract_number');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::table($prefix.'contracts', function (Blueprint $table) {
            $table->dropUnique(['active_key']);
            $table->dropUnique(['contract_number']);
            $table->dropColumn('active_key');
        });
    }
};
