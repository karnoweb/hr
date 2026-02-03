<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'employees', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->string('employable_type');
            $table->unsignedBigInteger('employable_id');
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('employee_code', 50)->unique();
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('national_id', 20)->nullable();
            $table->string('insurance_number', 30)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->unsignedInteger('dependents_count')->default(0);
            $table->string('education_level', 50)->nullable();
            $table->string('military_status', 30)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('bank_sheba', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('emergency_contact', 100)->nullable();
            $table->string('emergency_phone', 20)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employable_type', 'employable_id']);
            $table->index(['status', 'branch_id']);
            $table->index('hire_date');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'employees');
    }
};
