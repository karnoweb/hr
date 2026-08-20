<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit sequential/parallel workflow execution (HR-130).
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::table($prefix.'workflows', function (Blueprint $table) {
            $table->string('execution_mode', 20)->default('parallel')->after('priority');
        });

        Schema::table($prefix.'workflow_steps', function (Blueprint $table) {
            $table->unsignedBigInteger('escalation_user_id')->nullable()->after('timeout_action');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::table($prefix.'workflows', function (Blueprint $table) {
            $table->dropColumn('execution_mode');
        });

        Schema::table($prefix.'workflow_steps', function (Blueprint $table) {
            $table->dropColumn('escalation_user_id');
        });
    }
};
