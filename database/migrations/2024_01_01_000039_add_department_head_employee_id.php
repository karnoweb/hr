<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Department head reference for ApproverType::DepartmentHead (HR-125).
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::table($prefix.'departments', function (Blueprint $table) use ($prefix) {
            $table->unsignedBigInteger('head_employee_id')->nullable()->after('parent_id');
            $table->foreign('head_employee_id')
                ->references('id')
                ->on($prefix.'employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::table($prefix.'departments', function (Blueprint $table) {
            $table->dropForeign(['head_employee_id']);
            $table->dropColumn('head_employee_id');
        });
    }
};
