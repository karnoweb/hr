# پکیج HR - پیاده‌سازی کامل 🚀

---

## 1. ساختار پکیج

```
packages/hr/
├── composer.json
├── config/
│   └── hr.php
├── database/
│   └── migrations/
├── resources/
│   └── lang/
│       ├── en/
│       └── fa/
├── src/
│   ├── HrServiceProvider.php
│   ├── Facades/
│   ├── Models/
│   ├── Enums/
│   ├── Services/
│   ├── Calculators/
│   ├── Events/
│   ├── Traits/
│   ├── Contracts/
│   ├── Rules/
│   ├── Helpers/
│   └── Exceptions/
└── tests/
```

---

## 2. composer.json

```json
{
    "name": "yourvendor/hr",
    "description": "A comprehensive HR management package for Laravel",
    "type": "library",
    "license": "MIT",
    "authors": [
        {
            "name": "Your Name",
            "email": "your@email.com"
        }
    ],
    "require": {
        "php": "^8.1",
        "illuminate/support": "^10.0|^11.0",
        "illuminate/database": "^10.0|^11.0",
        "morilog/jalali": "^3.0"
    },
    "require-dev": {
        "orchestra/testbench": "^8.0|^9.0",
        "phpunit/phpunit": "^10.0"
    },
    "autoload": {
        "psr-4": {
            "YourVendor\\Hr\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "YourVendor\\Hr\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "YourVendor\\Hr\\HrServiceProvider"
            ],
            "aliases": {
                "Hr": "YourVendor\\Hr\\Facades\\Hr"
            }
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

---

## 3. Config

```php
<?php
// config/hr.php

return [

    /*
    |--------------------------------------------------------------------------
    | Models Configuration
    |--------------------------------------------------------------------------
    */
    'models' => [
        'user' => env('HR_USER_MODEL', 'App\\Models\\User'),
        'branch' => env('HR_BRANCH_MODEL', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'prefix' => 'hr_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Calendar Configuration
    |--------------------------------------------------------------------------
    */
    'calendar' => [
        'type' => env('HR_CALENDAR_TYPE', 'jalali'), // jalali | gregorian
        'week_starts_on' => 'saturday', // saturday | sunday | monday
        'year_starts_on' => 'farvardin', // farvardin | january
        'locale' => 'fa',
    ],

    /*
    |--------------------------------------------------------------------------
    | Employee Code Generation
    |--------------------------------------------------------------------------
    */
    'employee_code' => [
        'auto_generate' => true,
        'format' => '{year}-{sequence}', // {branch}-{year}-{sequence}
        'sequence_length' => 4,
        'sequence_per_branch' => false,
        'sequence_per_year' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Working Days (Default)
    |--------------------------------------------------------------------------
    */
    'working_days' => [
        'saturday' => true,
        'sunday' => true,
        'monday' => true,
        'tuesday' => true,
        'wednesday' => true,
        'thursday' => true,
        'friday' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Leave Types & Rules
    |--------------------------------------------------------------------------
    */
    'leave' => [
        'types' => [
            'annual' => [
                'name' => 'مرخصی استحقاقی',
                'name_en' => 'Annual Leave',
                'days_per_year' => 26,
                'carry_over' => true,
                'carry_over_max' => 9,
                'requires_approval' => true,
                'paid' => true,
                'color' => '#4CAF50',
            ],
            'sick' => [
                'name' => 'مرخصی استعلاجی',
                'name_en' => 'Sick Leave',
                'days_per_year' => null,
                'requires_document' => true,
                'requires_approval' => true,
                'paid' => true,
                'color' => '#F44336',
            ],
            'unpaid' => [
                'name' => 'مرخصی بدون حقوق',
                'name_en' => 'Unpaid Leave',
                'days_per_year' => null,
                'requires_approval' => true,
                'paid' => false,
                'color' => '#9E9E9E',
            ],
            'hourly' => [
                'name' => 'مرخصی ساعتی',
                'name_en' => 'Hourly Leave',
                'max_hours_per_month' => 12,
                'requires_approval' => true,
                'paid' => true,
                'color' => '#2196F3',
            ],
            'marriage' => [
                'name' => 'مرخصی ازدواج',
                'name_en' => 'Marriage Leave',
                'fixed_days' => 3,
                'once_per_employment' => true,
                'requires_approval' => true,
                'paid' => true,
                'color' => '#E91E63',
            ],
            'maternity' => [
                'name' => 'مرخصی زایمان',
                'name_en' => 'Maternity Leave',
                'fixed_days' => 180,
                'requires_approval' => true,
                'paid' => true,
                'color' => '#FF9800',
            ],
            'bereavement' => [
                'name' => 'مرخصی فوت',
                'name_en' => 'Bereavement Leave',
                'fixed_days' => 3,
                'requires_approval' => true,
                'paid' => true,
                'color' => '#607D8B',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Overtime Rules
    |--------------------------------------------------------------------------
    */
    'overtime' => [
        'rates' => [
            'regular' => 1.4,
            'holiday' => 1.7,
            'night' => 1.35,
        ],
        'night_start' => '22:00',
        'night_end' => '06:00',
        'monthly_cap' => 120, // null = no cap
        'requires_pre_approval' => false,
        'min_minutes' => 30, // حداقل دقیقه برای محاسبه اضافه‌کار
    ],

    /*
    |--------------------------------------------------------------------------
    | Attendance Rules
    |--------------------------------------------------------------------------
    */
    'attendance' => [
        'late_tolerance_minutes' => 15,
        'early_leave_tolerance_minutes' => 15,
        'min_work_hours' => 8,
        'break_duration_minutes' => 60,
        'auto_clock_out' => false,
        'auto_clock_out_after_hours' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Insurance Configuration (Social Security)
    |--------------------------------------------------------------------------
    */
    'insurance' => [
        'social_security' => [
            'enabled' => true,
            'employee_rate' => 7,
            'employer_rate' => 20,
            'unemployment_rate' => 3,
            'ceiling_multiplier' => 7,
        ],
        'supplementary' => [
            'enabled' => false,
            'employee_contribution' => 0,
            'employer_contribution' => 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax Configuration
    |--------------------------------------------------------------------------
    */
    'tax' => [
        'enabled' => true,
        'annual_exemption' => 672000000, // 1403
        'brackets' => [
            ['up_to' => 200000000, 'rate' => 10],
            ['up_to' => 400000000, 'rate' => 15],
            ['up_to' => 600000000, 'rate' => 20],
            ['up_to' => 1000000000, 'rate' => 25],
            ['up_to' => null, 'rate' => 30],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Loan Configuration
    |--------------------------------------------------------------------------
    */
    'loan' => [
        'enabled' => true,
        'max_amount' => null,
        'max_installments' => 24,
        'min_installments' => 1,
        'min_months_between_loans' => 6,
        'max_active_loans' => 2,
        'max_percentage_of_salary' => 50, // حداکثر درصد کسر از حقوق
    ],

    /*
    |--------------------------------------------------------------------------
    | Payroll Configuration
    |--------------------------------------------------------------------------
    */
    'payroll' => [
        'closing_day' => 'end_of_month', // or number like 25
        'minimum_wage' => 53304000, // 1403
        'payment_day' => 1, // روز پرداخت ماه بعد
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Workflow
    |--------------------------------------------------------------------------
    */
    'documents' => [
        'require_approval' => [
            'hire',
            'termination',
            'position_change',
            'salary_change',
            'leave',
            'mission',
            'loan',
            'overtime_approval',
        ],
        'auto_lock_after_approval' => true,
        'lock_delay_hours' => 24, // ساعت تا قفل شدن بعد از تأیید
    ],

    /*
    |--------------------------------------------------------------------------
    | Workflow Default Approvers
    |--------------------------------------------------------------------------
    */
    'workflow' => [
        'default_approver_type' => 'department_head', // user | position | department_head
        'skip_on_no_approver' => false,
        'auto_approve_own_department' => false,
    ],

];
```

---

## 4. Migrations

### 4.1 Departments

```php
<?php
// database/migrations/2024_01_01_000001_create_hr_departments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('level')->default(0);
            $table->string('path')->nullable(); // 1/2/3 for tree queries
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')
                ->references('id')
                ->on($prefix . 'departments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'departments');
    }
};
```

### 4.2 Positions

```php
<?php
// database/migrations/2024_01_01_000002_create_hr_positions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'positions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('grade')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'positions');
    }
};
```

### 4.3 Employees

```php
<?php
// database/migrations/2024_01_01_000003_create_hr_employees_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'employees', function (Blueprint $table) {
            $table->id();
            $table->morphs('employable'); // user_id, user_type
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('employee_code', 50)->unique();
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->string('status', 20)->default('active'); // active, suspended, terminated
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
```

### 4.4 Contracts

```php
<?php
// database/migrations/2024_01_01_000004_create_hr_contracts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix . 'employees')->cascadeOnDelete();
            $table->string('contract_number', 50)->nullable();
            $table->string('type', 30); // permanent, temporary, project, part_time
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active'); // active, ended, terminated
            $table->text('terms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
            $table->index('start_date');
            $table->index('end_date');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'contracts');
    }
};
```

### 4.5 Employee Positions (Pivot with History)

```php
<?php
// database/migrations/2024_01_01_000005_create_hr_employee_positions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'employee_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix . 'employees')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained($prefix . 'departments')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained($prefix . 'positions')->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('hr_document_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'is_primary']);
            $table->index('effective_date');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'employee_positions');
    }
};
```

### 4.6 Shifts

```php
<?php
// database/migrations/2024_01_01_000006_create_hr_shifts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->unsignedInteger('work_minutes'); // calculated work time
            $table->boolean('is_night_shift')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('color', 10)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'shifts');
    }
};
```

### 4.7 Shift Patterns

```php
<?php
// database/migrations/2024_01_01_000007_create_hr_shift_patterns_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'shift_patterns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->unsignedInteger('cycle_days')->default(7); // 7, 14, 21, etc.
            $table->json('pattern'); // [{"day": 0, "shift_id": 1}, {"day": 1, "shift_id": null}]
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'shift_patterns');
    }
};
```

### 4.8 Employee Shift Assignments

```php
<?php
// database/migrations/2024_01_01_000008_create_hr_employee_shift_assignments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'employee_shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix . 'employees')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained($prefix . 'shifts')->nullOnDelete();
            $table->foreignId('shift_pattern_id')->nullable()->constrained($prefix . 'shift_patterns')->nullOnDelete();
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->date('pattern_start_date')->nullable(); // روز شروع چرخه
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['employee_id', 'is_active']);
            $table->index('effective_date');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'employee_shift_assignments');
    }
};
```

### 4.9 Holidays

```php
<?php
// database/migrations/2024_01_01_000009_create_hr_holidays_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->date('date');
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('type', 30)->default('official'); // official, religious, custom
            $table->boolean('is_recurring')->default(false);
            $table->unsignedInteger('recurring_month')->nullable();
            $table->unsignedInteger('recurring_day')->nullable();
            $table->timestamps();

            $table->index('date');
            $table->unique(['branch_id', 'date']);
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'holidays');
    }
};
```

### 4.10 Attendance Records

```php
<?php
// database/migrations/2024_01_01_000010_create_hr_attendance_records_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix . 'employees')->cascadeOnDelete();
            $table->date('date');
            $table->dateTime('clock_in')->nullable();
            $table->dateTime('clock_out')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained($prefix . 'shifts')->nullOnDelete();
            $table->unsignedInteger('work_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_leave_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedInteger('overtime_night_minutes')->default(0);
            $table->unsignedInteger('overtime_holiday_minutes')->default(0);
            $table->string('status', 20)->default('present'); // present, absent, leave, mission, holiday
            $table->string('source', 20)->default('manual'); // manual, device, import
            $table->text('notes')->nullable();
            $table->json('raw_data')->nullable(); // original device data
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'attendance_records');
    }
};
```

### 4.11 Overtime Records

```php
<?php
// database/migrations/2024_01_01_000011_create_hr_overtime_records_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'overtime_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix . 'employees')->cascadeOnDelete();
            $table->foreignId('attendance_record_id')->nullable()->constrained($prefix . 'attendance_records')->nullOnDelete();
            $table->date('date');
            $table->unsignedInteger('calculated_minutes')->default(0);
            $table->unsignedInteger('approved_minutes')->nullable();
            $table->string('type', 20)->default('regular'); // regular, holiday, night
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->unsignedBigInteger('hr_document_id')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'overtime_records');
    }
};
```

### 4.12 Leave Requests

```php
<?php
// database/migrations/2024_01_01_000012_create_hr_leave_requests_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix . 'employees')->cascadeOnDelete();
            $table->string('type', 30); // from config
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable(); // for hourly
            $table->time('end_time')->nullable();
            $table->decimal('days', 5, 2)->default(0);
            $table->decimal('hours', 5, 2)->default(0);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, cancelled
            $table->unsignedBigInteger('hr_document_id')->nullable();
            $table->unsignedBigInteger('substitute_employee_id')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'leave_requests');
    }
};
```

### 4.13 Mission Requests

```php
<?php
// database/migrations/2024_01_01_000013_create_hr_mission_requests_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'mission_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix . 'employees')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('destination');
            $table->text('purpose');
            $table->decimal('days', 5, 2)->default(0);
            $table->string('transportation')->nullable();
            $table->boolean('requires_accommodation')->default(false);
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('hr_document_id')->nullable();
            $table->json('expenses')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'mission_requests');
    }
};
```

### 4.14 Leave Balances

```php
<?php
// database/migrations/2024_01_01_000014_create_hr_leave_balances_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix . 'employees')->cascadeOnDelete();
            $table->unsignedInteger('year');
            $table->string('type', 30);
            $table->decimal('entitled_days', 5, 2)->default(0);
            $table->decimal('used_days', 5, 2)->default(0);
            $table->decimal('carried_days', 5, 2)->default(0);
            $table->decimal('adjustment_days', 5, 2)->default(0);
            $table->decimal('remaining_days', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'type']);
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'leave_balances');
    }
};
```

### 4.15 HR Documents

```php
<?php
// database/migrations/2024_01_01_000015_create_hr_documents_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->foreignId('employee_id')->constrained($prefix . 'employees')->cascadeOnDelete();
            $table->string('type', 30); // hire, termination, position_change, etc.
            $table->string('document_number', 50)->unique();
            $table->date('effective_date');
            $table->date('expiry_date')->nullable();
            $table->string('status', 20)->default('draft'); // draft, pending, approved, rejected, locked
            $table->json('data'); // type-specific data
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'type']);
            $table->index('status');
            $table->index('effective_date');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'documents');
    }
};
```

### 4.16 Document Attachments

```php
<?php
// database/migrations/2024_01_01_000016_create_hr_document_attachments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'document_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_document_id')->constrained($prefix . 'documents')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type', 50);
            $table->unsignedBigInteger('file_size');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'document_attachments');
    }
};
```

### 4.17 Workflows

```php
<?php
// database/migrations/2024_01_01_000017_create_hr_workflows_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'workflows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('name');
            $table->string('document_type', 30);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0); // higher = more specific
            $table->json('conditions')->nullable(); // additional conditions
            $table->timestamps();
            $table->softDeletes();

            $table->index('document_type');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'workflows');
    }
};
```

### 4.18 Workflow Steps

```php
<?php
// database/migrations/2024_01_01_000018_create_hr_workflow_steps_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained($prefix . 'workflows')->cascadeOnDelete();
            $table->unsignedInteger('order');
            $table->string('name');
            $table->string('approver_type', 30); // user, position, department_head, custom
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->json('condition')->nullable(); // e.g., {"days_gt": 3}
            $table->boolean('is_required')->default(true);
            $table->boolean('can_reject')->default(true);
            $table->unsignedInteger('timeout_hours')->nullable();
            $table->string('timeout_action', 20)->nullable(); // skip, escalate, reject
            $table->timestamps();

            $table->index(['workflow_id', 'order']);
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'workflow_steps');
    }
};
```

### 4.19 Document Approvals

```php
<?php
// database/migrations/2024_01_01_000019_create_hr_document_approvals_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'document_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_document_id')->constrained($prefix . 'documents')->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained($prefix . 'workflow_steps')->cascadeOnDelete();
            $table->unsignedBigInteger('assigned_to'); // user_id
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, skipped
            $table->text('comment')->nullable();
            $table->dateTime('acted_at')->nullable();
            $table->dateTime('deadline_at')->nullable();
            $table->timestamps();

            $table->index(['hr_document_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'document_approvals');
    }
};
```

### 4.20 Salary Items

```php
<?php
// database/migrations/2024_01_01_000020_create_hr_salary_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'salary_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('type', 20); // earning, deduction
            $table->string('calculation_type', 20); // fixed, formula, percentage
            $table->decimal('default_value', 15, 2)->nullable();
            $table->text('formula')->nullable();
            $table->string('percentage_of')->nullable(); // base_salary, gross_salary, etc.
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_insurable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'salary_items');
    }
};
```

### 4.21 Salary Structures

```php
<?php
// database/migrations/2024_01_01_000021_create_hr_salary_structures_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'salary_structures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create($prefix . 'salary_structure_items', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('salary_structure_id')->constrained($prefix . 'salary_structures')->cascadeOnDelete();
            $table->foreignId('salary_item_id')->constrained($prefix . 'salary_items')->cascadeOnDelete();
            $table->decimal('value', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['salary_structure_id', 'salary_item_id'], 'structure_item_unique');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'salary_structure_items');
        Schema::dropIfExists($prefix . 'salary_structures');
    }
};
```

### 4.22 Employee Salaries

```php
<?php
// database/migrations/2024_01_01_000022_create_hr_employee_salaries_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix . 'employees')->cascadeOnDelete();
            $table->foreignId('salary_structure_id')->nullable()->constrained($prefix . 'salary_structures')->nullOnDelete();
            $table->decimal('base_salary', 15, 2);
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('hr_document_id')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['employee_id', 'is_current']);
            $table->index('effective_date');
        });

        Schema::create($prefix . 'employee_salary_items', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('employee_salary_id')->constrained($prefix . 'employee_salaries')->cascadeOnDelete();
            $table->foreignId('salary_item_id')->constrained($prefix . 'salary_items')->cascadeOnDelete();
            $table->decimal('value', 15, 2);
            $table->timestamps();

            $table->unique(['employee_salary_id', 'salary_item_id'], 'employee_salary_item_unique');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'employee_salary_items');
        Schema::dropIfExists($prefix . 'employee_salaries');
    }
};
```

### 4.23 Loans

```php
<?php
// database/migrations/2024_01_01_000023_create_hr_loans_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained($prefix . 'employees')->cascadeOnDelete();
            $table->unsignedBigInteger('hr_document_id')->nullable();
            $table->string('loan_number', 50)->unique();
            $table->string('type', 30)->default('general');
            $table->decimal('amount', 15, 2);
            $table->unsignedInteger('installments');
            $table->decimal('installment_amount', 15, 2);
            $table->decimal('remaining_amount', 15, 2);
            $table->unsignedInteger('remaining_installments');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('pending'); // pending, active, completed, cancelled
            $table->text('purpose')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'loans');
    }
};
```

### 4.24 Loan Payments

```php
<?php
// database/migrations/2024_01_01_000024_create_hr_loan_payments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained($prefix . 'loans')->cascadeOnDelete();
            $table->unsignedBigInteger('payroll_record_id')->nullable();
            $table->unsignedInteger('installment_number');
            $table->decimal('amount', 15, 2);
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            $table->string('status', 20)->default('pending'); // pending, paid, overdue
            $table->timestamps();

            $table->index(['loan_id', 'status']);
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'loan_payments');
    }
};
```

### 4.25 Payroll Periods

```php
<?php
// database/migrations/2024_01_01_000025_create_hr_payroll_periods_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedInteger('year');
            $table->unsignedInteger('month');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('working_days');
            $table->string('status', 20)->default('draft'); // draft, calculating, calculated, approved, paid, locked
            $table->dateTime('calculated_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('notes')->nullable();
            $table->json('summary')->nullable(); // totals
            $table->timestamps();

            $table->unique(['branch_id', 'year', 'month']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'payroll_periods');
    }
};
```

### 4.26 Payroll Records

```php
<?php
// database/migrations/2024_01_01_000026_create_hr_payroll_records_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'payroll_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained($prefix . 'payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained($prefix . 'employees')->cascadeOnDelete();
            $table->unsignedBigInteger('hr_document_id')->nullable();

            // Working Time
            $table->unsignedInteger('working_days')->default(0);
            $table->unsignedInteger('present_days')->default(0);
            $table->unsignedInteger('absent_days')->default(0);
            $table->decimal('leave_days_paid', 5, 2)->default(0);
            $table->decimal('leave_days_unpaid', 5, 2)->default(0);
            $table->decimal('mission_days', 5, 2)->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_leave_minutes')->default(0);

            // Overtime
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedInteger('overtime_night_minutes')->default(0);
            $table->unsignedInteger('overtime_holiday_minutes')->default(0);

            // Earnings & Deductions (JSON)
            $table->json('earnings')->nullable();
            $table->json('deductions')->nullable();

            // Calculated Amounts
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('gross_earnings', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('gross_salary', 15, 2)->default(0);

            // Insurance
            $table->decimal('insurance_employee', 15, 2)->default(0);
            $table->decimal('insurance_employer', 15, 2)->default(0);
            $table->decimal('insurance_unemployment', 15, 2)->default(0);

            // Tax
            $table->decimal('taxable_income', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);

            // Net
            $table->decimal('net_salary', 15, 2)->default(0);

            // Loan
            $table->decimal('loan_deduction', 15, 2)->default(0);

            // Final
            $table->decimal('payable', 15, 2)->default(0);

            $table->string('status', 20)->default('draft'); // draft, calculated, approved, paid
            $table->text('notes')->nullable();
            $table->json('calculation_log')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'payroll_records');
    }
};
```

### 4.27 Document History (Audit)

```php
<?php
// database/migrations/2024_01_01_000027_create_hr_document_histories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');

        Schema::create($prefix . 'document_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_document_id')->constrained($prefix . 'documents')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 30); // created, updated, submitted, approved, rejected, locked
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->json('changes')->nullable();
            $table->text('comment')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index('hr_document_id');
        });
    }

    public function down(): void
    {
        $prefix = config('hr.tables.prefix', 'hr_');
        Schema::dropIfExists($prefix . 'document_histories');
    }
};
```

---

## 5. Enums

### 5.1 EmployeeStatus

```php
<?php
// src/Enums/EmployeeStatus.php

namespace YourVendor\Hr\Enums;

enum EmployeeStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('hr::enums.employee_status.active'),
            self::Suspended => __('hr::enums.employee_status.suspended'),
            self::Terminated => __('hr::enums.employee_status.terminated'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'warning',
            self::Terminated => 'danger',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

### 5.2 ContractType

```php
<?php
// src/Enums/ContractType.php

namespace YourVendor\Hr\Enums;

enum ContractType: string
{
    case Permanent = 'permanent';
    case Temporary = 'temporary';
    case Project = 'project';
    case PartTime = 'part_time';
    case Internship = 'internship';

    public function label(): string
    {
        return match ($this) {
            self::Permanent => __('hr::enums.contract_type.permanent'),
            self::Temporary => __('hr::enums.contract_type.temporary'),
            self::Project => __('hr::enums.contract_type.project'),
            self::PartTime => __('hr::enums.contract_type.part_time'),
            self::Internship => __('hr::enums.contract_type.internship'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

### 5.3 ContractStatus

```php
<?php
// src/Enums/ContractStatus.php

namespace YourVendor\Hr\Enums;

enum ContractStatus: string
{
    case Active = 'active';
    case Ended = 'ended';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('hr::enums.contract_status.active'),
            self::Ended => __('hr::enums.contract_status.ended'),
            self::Terminated => __('hr::enums.contract_status.terminated'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

### 5.4 DocumentType

```php
<?php
// src/Enums/DocumentType.php

namespace YourVendor\Hr\Enums;

enum DocumentType: string
{
    case Hire = 'hire';
    case Termination = 'termination';
    case Rehire = 'rehire';
    case ContractStart = 'contract_start';
    case ContractEnd = 'contract_end';
    case ContractExtend = 'contract_extend';
    case PositionChange = 'position_change';
    case SalaryChange = 'salary_change';
    case Leave = 'leave';
    case Mission = 'mission';
    case OvertimeApproval = 'overtime_approval';
    case Warning = 'warning';
    case Reward = 'reward';
    case Loan = 'loan';
    case Payroll = 'payroll';

    public function label(): string
    {
        return match ($this) {
            self::Hire => __('hr::enums.document_type.hire'),
            self::Termination => __('hr::enums.document_type.termination'),
            self::Rehire => __('hr::enums.document_type.rehire'),
            self::ContractStart => __('hr::enums.document_type.contract_start'),
            self::ContractEnd => __('hr::enums.document_type.contract_end'),
            self::ContractExtend => __('hr::enums.document_type.contract_extend'),
            self::PositionChange => __('hr::enums.document_type.position_change'),
            self::SalaryChange => __('hr::enums.document_type.salary_change'),
            self::Leave => __('hr::enums.document_type.leave'),
            self::Mission => __('hr::enums.document_type.mission'),
            self::OvertimeApproval => __('hr::enums.document_type.overtime_approval'),
            self::Warning => __('hr::enums.document_type.warning'),
            self::Reward => __('hr::enums.document_type.reward'),
            self::Loan => __('hr::enums.document_type.loan'),
            self::Payroll => __('hr::enums.document_type.payroll'),
        };
    }

    public function requiresApproval(): bool
    {
        return in_array($this->value, config('hr.documents.require_approval', []));
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

### 5.5 DocumentStatus

```php
<?php
// src/Enums/DocumentStatus.php

namespace YourVendor\Hr\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Locked = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('hr::enums.document_status.draft'),
            self::Pending => __('hr::enums.document_status.pending'),
            self::Approved => __('hr::enums.document_status.approved'),
            self::Rejected => __('hr::enums.document_status.rejected'),
            self::Locked => __('hr::enums.document_status.locked'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Locked => 'info',
        };
    }

    public function canEdit(): bool
    {
        return $this === self::Draft;
    }

    public function canDelete(): bool
    {
        return $this === self::Draft;
    }

    public function canSubmit(): bool
    {
        return $this === self::Draft;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

### 5.6 AttendanceStatus

```php
<?php
// src/Enums/AttendanceStatus.php

namespace YourVendor\Hr\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Leave = 'leave';
    case Mission = 'mission';
    case Holiday = 'holiday';
    case Weekend = 'weekend';

    public function label(): string
    {
        return match ($this) {
            self::Present => __('hr::enums.attendance_status.present'),
            self::Absent => __('hr::enums.attendance_status.absent'),
            self::Leave => __('hr::enums.attendance_status.leave'),
            self::Mission => __('hr::enums.attendance_status.mission'),
            self::Holiday => __('hr::enums.attendance_status.holiday'),
            self::Weekend => __('hr::enums.attendance_status.weekend'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Absent => 'danger',
            self::Leave => 'warning',
            self::Mission => 'info',
            self::Holiday => 'secondary',
            self::Weekend => 'secondary',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

### 5.7 LeaveRequestStatus

```php
<?php
// src/Enums/LeaveRequestStatus.php

namespace YourVendor\Hr\Enums;

enum LeaveRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('hr::enums.leave_request_status.pending'),
            self::Approved => __('hr::enums.leave_request_status.approved'),
            self::Rejected => __('hr::enums.leave_request_status.rejected'),
            self::Cancelled => __('hr::enums.leave_request_status.cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'secondary',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

### 5.8 OvertimeType

```php
<?php
// src/Enums/OvertimeType.php

namespace YourVendor\Hr\Enums;

enum OvertimeType: string
{
    case Regular = 'regular';
    case Holiday = 'holiday';
    case Night = 'night';

    public function label(): string
    {
        return match ($this) {
            self::Regular => __('hr::enums.overtime_type.regular'),
            self::Holiday => __('hr::enums.overtime_type.holiday'),
            self::Night => __('hr::enums.overtime_type.night'),
        };
    }

    public function rate(): float
    {
        return match ($this) {
            self::Regular => config('hr.overtime.rates.regular', 1.4),
            self::Holiday => config('hr.overtime.rates.holiday', 1.7),
            self::Night => config('hr.overtime.rates.night', 1.35),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

### 5.9 PayrollPeriodStatus

```php
<?php
// src/Enums/PayrollPeriodStatus.php

namespace YourVendor\Hr\Enums;

enum PayrollPeriodStatus: string
{
    case Draft = 'draft';
    case Calculating = 'calculating';
    case Calculated = 'calculated';
    case Approved = 'approved';
    case Paid = 'paid';
    case Locked = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('hr::enums.payroll_period_status.draft'),
            self::Calculating => __('hr::enums.payroll_period_status.calculating'),
            self::Calculated => __('hr::enums.payroll_period_status.calculated'),
            self::Approved => __('hr::enums.payroll_period_status.approved'),
            self::Paid => __('hr::enums.payroll_period_status.paid'),
            self::Locked => __('hr::enums.payroll_period_status.locked'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Calculating => 'info',
            self::Calculated => 'primary',
            self::Approved => 'success',
            self::Paid => 'success',
            self::Locked => 'dark',
        };
    }

    public function canCalculate(): bool
    {
        return in_array($this, [self::Draft, self::Calculated]);
    }

    public function canApprove(): bool
    {
        return $this === self::Calculated;
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::Draft, self::Calculated]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

### 5.10 SalaryItemType

```php
<?php
// src/Enums/SalaryItemType.php

namespace YourVendor\Hr\Enums;

enum SalaryItemType: string
{
    case Earning = 'earning';
    case Deduction = 'deduction';

    public function label(): string
    {
        return match ($this) {
            self::Earning => __('hr::enums.salary_item_type.earning'),
            self::Deduction => __('hr::enums.salary_item_type.deduction'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

### 5.11 CalculationType

```php
<?php
// src/Enums/CalculationType.php

namespace YourVendor\Hr\Enums;

enum CalculationType: string
{
    case Fixed = 'fixed';
    case Formula = 'formula';
    case Percentage = 'percentage';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('hr::enums.calculation_type.fixed'),
            self::Formula => __('hr::enums.calculation_type.formula'),
            self::Percentage => __('hr::enums.calculation_type.percentage'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

### 5.12 LoanStatus

```php
<?php
// src/Enums/LoanStatus.php

namespace YourVendor\Hr\Enums;

enum LoanStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('hr::enums.loan_status.pending'),
            self::Active => __('hr::enums.loan_status.active'),
            self::Completed => __('hr::enums.loan_status.completed'),
            self::Cancelled => __('hr::enums.loan_status.cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'info',
            self::Completed => 'success',
            self::Cancelled => 'secondary',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

### 5.13 ApproverType

```php
<?php
// src/Enums/ApproverType.php

namespace YourVendor\Hr\Enums;

enum ApproverType: string
{
    case User = 'user';
    case Position = 'position';
    case DepartmentHead = 'department_head';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::User => __('hr::enums.approver_type.user'),
            self::Position => __('hr::enums.approver_type.position'),
            self::DepartmentHead => __('hr::enums.approver_type.department_head'),
            self::Custom => __('hr::enums.approver_type.custom'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

### 5.14 ApprovalStatus

```php
<?php
// src/Enums/ApprovalStatus.php

namespace YourVendor\Hr\Enums;

enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('hr::enums.approval_status.pending'),
            self::Approved => __('hr::enums.approval_status.approved'),
            self::Rejected => __('hr::enums.approval_status.rejected'),
            self::Skipped => __('hr::enums.approval_status.skipped'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Skipped => 'secondary',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

---

## 6. Models

### 6.1 Base Model

```php
<?php
// src/Models/BaseModel.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

abstract class BaseModel extends Model
{
    public function getTable(): string
    {
        return config('hr.tables.prefix', 'hr_') . parent::getTable();
    }

    protected static function boot(): void
    {
        parent::boot();

        if (method_exists(static::class, 'bootSoftDeletes')) {
            static::bootSoftDeletes();
        }
    }
}
```

### 6.2 Department

```php
<?php
// src/Models/Department.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Department extends BaseModel
{
    use SoftDeletes;

    protected $table = 'departments';

    protected $fillable = [
        'branch_id',
        'parent_id',
        'code',
        'name',
        'name_en',
        'description',
        'level',
        'path',
        'sort_order',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'level' => 'integer',
        'sort_order' => 'integer',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    public function employeePositions(): HasMany
    {
        return $this->hasMany(EmployeePosition::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(EmployeePosition::class)
            ->where('is_primary', true)
            ->whereNull('end_date');
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeForBranch(Builder $query, $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    // ═══════════════════════════════════════════════════════════
    // METHODS
    // ═══════════════════════════════════════════════════════════

    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    public function getAncestors(): array
    {
        $ancestors = [];
        $current = $this->parent;

        while ($current) {
            $ancestors[] = $current;
            $current = $current->parent;
        }

        return array_reverse($ancestors);
    }

    public function getAllDescendantIds(): array
    {
        $ids = [];

        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }

        return $ids;
    }

    public function updatePath(): void
    {
        $path = $this->parent ? $this->parent->path . '/' . $this->id : (string) $this->id;
        $level = substr_count($path, '/');

        $this->update([
            'path' => $path,
            'level' => $level,
        ]);

        foreach ($this->children as $child) {
            $child->updatePath();
        }
    }

    protected static function booted(): void
    {
        static::created(function (Department $department) {
            $department->updatePath();
        });

        static::updated(function (Department $department) {
            if ($department->isDirty('parent_id')) {
                $department->updatePath();
            }
        });
    }
}
```

### 6.3 Position

```php
<?php
// src/Models/Position.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Position extends BaseModel
{
    use SoftDeletes;

    protected $table = 'positions';

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'name_en',
        'description',
        'grade',
        'sort_order',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'grade' => 'integer',
        'sort_order' => 'integer',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════

    public function employeePositions(): HasMany
    {
        return $this->hasMany(EmployeePosition::class);
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForBranch(Builder $query, $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }
}
```

### 6.4 Employee

```php
<?php
// src/Models/Employee.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use YourVendor\Hr\Enums\EmployeeStatus;

class Employee extends BaseModel
{
    use SoftDeletes;

    protected $table = 'employees';

    protected $fillable = [
        'employable_type',
        'employable_id',
        'branch_id',
        'employee_code',
        'hire_date',
        'termination_date',
        'status',
        'national_id',
        'insurance_number',
        'birth_date',
        'gender',
        'marital_status',
        'dependents_count',
        'education_level',
        'military_status',
        'bank_name',
        'bank_account',
        'bank_sheba',
        'address',
        'phone',
        'emergency_contact',
        'emergency_phone',
        'metadata',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'termination_date' => 'date',
        'birth_date' => 'date',
        'status' => EmployeeStatus::class,
        'metadata' => 'array',
        'dependents_count' => 'integer',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════

    public function employable(): MorphTo
    {
        return $this->morphTo();
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function currentContract(): HasOne
    {
        return $this->hasOne(Contract::class)
            ->where('status', 'active')
            ->latest('start_date');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(EmployeePosition::class);
    }

    public function currentPosition(): HasOne
    {
        return $this->hasOne(EmployeePosition::class)
            ->where('is_primary', true)
            ->whereNull('end_date')
            ->latest('effective_date');
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(EmployeeShiftAssignment::class);
    }

    public function currentShiftAssignment(): HasOne
    {
        return $this->hasOne(EmployeeShiftAssignment::class)
            ->where('is_active', true)
            ->latest('effective_date');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function overtimeRecords(): HasMany
    {
        return $this->hasMany(OvertimeRecord::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function missionRequests(): HasMany
    {
        return $this->hasMany(MissionRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(HrDocument::class);
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    public function currentSalary(): HasOne
    {
        return $this->hasOne(EmployeeSalary::class)
            ->where('is_current', true);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function activeLoans(): HasMany
    {
        return $this->hasMany(Loan::class)
            ->where('status', 'active');
    }

    public function payrollRecords(): HasMany
    {
        return $this->hasMany(PayrollRecord::class);
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EmployeeStatus::Active);
    }

    public function scopeForBranch(Builder $query, $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeHiredBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('hire_date', [$from, $to]);
    }

    public function scopeInDepartment(Builder $query, $departmentId): Builder
    {
        return $query->whereHas('currentPosition', function ($q) use ($departmentId) {
            $q->where('department_id', $departmentId);
        });
    }

    // ═══════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════

    public function getDepartmentAttribute(): ?Department
    {
        return $this->currentPosition?->department;
    }

    public function getPositionAttribute(): ?Position
    {
        return $this->currentPosition?->position;
    }

    public function getShiftAttribute(): ?Shift
    {
        return $this->currentShiftAssignment?->shift;
    }

    public function getShiftPatternAttribute(): ?ShiftPattern
    {
        return $this->currentShiftAssignment?->shiftPattern;
    }

    public function getBaseSalaryAttribute(): float
    {
        return $this->currentSalary?->base_salary ?? 0;
    }

    public function getYearsOfServiceAttribute(): float
    {
        if (!$this->hire_date) {
            return 0;
        }

        $endDate = $this->termination_date ?? now();
        return $this->hire_date->diffInYears($endDate);
    }

    public function getMonthsOfServiceAttribute(): int
    {
        if (!$this->hire_date) {
            return 0;
        }

        $endDate = $this->termination_date ?? now();
        return $this->hire_date->diffInMonths($endDate);
    }

    // ═══════════════════════════════════════════════════════════
    // METHODS
    // ═══════════════════════════════════════════════════════════

    public function isActive(): bool
    {
        return $this->status === EmployeeStatus::Active;
    }

    public function isTerminated(): bool
    {
        return $this->status === EmployeeStatus::Terminated;
    }

    public function isSuspended(): bool
    {
        return $this->status === EmployeeStatus::Suspended;
    }

    public function getLeaveBalance(string $type, int $year): ?LeaveBalance
    {
        return $this->leaveBalances()
            ->where('type', $type)
            ->where('year', $year)
            ->first();
    }

    public function getShiftForDate(\Carbon\Carbon $date): ?Shift
    {
        $assignment = $this->currentShiftAssignment;

        if (!$assignment) {
            return null;
        }

        // Fixed shift
        if ($assignment->shift_id) {
            return $assignment->shift;
        }

        // Pattern shift
        if ($assignment->shift_pattern_id && $assignment->pattern_start_date) {
            $pattern = $assignment->shiftPattern;
            $daysSinceStart = $assignment->pattern_start_date->diffInDays($date);
            $dayInCycle = $daysSinceStart % $pattern->cycle_days;

            $patternData = collect($pattern->pattern)->firstWhere('day', $dayInCycle);

            if ($patternData && isset($patternData['shift_id'])) {
                return Shift::find($patternData['shift_id']);
            }
        }

        return null;
    }
}
```

### 6.5 Contract

```php
<?php
// src/Models/Contract.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use YourVendor\Hr\Enums\ContractType;
use YourVendor\Hr\Enums\ContractStatus;

class Contract extends BaseModel
{
    use SoftDeletes;

    protected $table = 'contracts';

    protected $fillable = [
        'employee_id',
        'contract_number',
        'type',
        'start_date',
        'end_date',
        'status',
        'terms',
        'metadata',
    ];

    protected $casts = [
        'type' => ContractType::class,
        'status' => ContractStatus::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'metadata' => 'array',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ContractStatus::Active);
    }

    public function scopeExpiring(Builder $query, int $days = 30): Builder
    {
        return $query->active()
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays($days)]);
    }

    // ═══════════════════════════════════════════════════════════
    // METHODS
    // ═══════════════════════════════════════════════════════════

    public function isActive(): bool
    {
        return $this->status === ContractStatus::Active;
    }

    public function isPermanent(): bool
    {
        return $this->type === ContractType::Permanent;
    }

    public function isExpired(): bool
    {
        return $this->end_date && $this->end_date->isPast();
    }

    public function daysUntilExpiry(): ?int
    {
        if (!$this->end_date) {
            return null;
        }

        return now()->diffInDays($this->end_date, false);
    }
}
```

### 6.6 EmployeePosition

```php
<?php
// src/Models/EmployeePosition.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class EmployeePosition extends BaseModel
{
    protected $table = 'employee_positions';

    protected $fillable = [
        'employee_id',
        'department_id',
        'position_id',
        'is_primary',
        'effective_date',
        'end_date',
        'hr_document_id',
        'metadata',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'effective_date' => 'date',
        'end_date' => 'date',
        'metadata' => 'array',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(HrDocument::class, 'hr_document_id');
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('end_date');
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeActiveAt(Builder $query, $date): Builder
    {
        return $query->where('effective_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            });
    }
}
```

### 6.7 Shift

```php
<?php
// src/Models/Shift.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Shift extends BaseModel
{
    use SoftDeletes;

    protected $table = 'shifts';

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'name_en',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
        'work_minutes',
        'is_night_shift',
        'is_active',
        'color',
        'metadata',
    ];

    protected $casts = [
        'is_night_shift' => 'boolean',
        'is_active' => 'boolean',
        'work_minutes' => 'integer',
        'metadata' => 'array',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeShiftAssignment::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForBranch(Builder $query, $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    // ═══════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════

    public function getStartTimeAttribute($value): Carbon
    {
        return Carbon::parse($value);
    }

    public function getEndTimeAttribute($value): Carbon
    {
        return Carbon::parse($value);
    }

    public function getBreakMinutesAttribute(): int
    {
        if (!$this->break_start || !$this->break_end) {
            return 0;
        }

        return Carbon::parse($this->break_start)->diffInMinutes(Carbon::parse($this->break_end));
    }

    // ═══════════════════════════════════════════════════════════
    // METHODS
    // ═══════════════════════════════════════════════════════════

    public function calculateWorkMinutes(): int
    {
        $start = $this->start_time;
        $end = $this->end_time;

        // Handle overnight shifts
        if ($end->lessThan($start)) {
            $end->addDay();
        }

        $totalMinutes = $start->diffInMinutes($end);
        $breakMinutes = $this->break_minutes;

        return $totalMinutes - $breakMinutes;
    }

    public function isWithinShift(Carbon $time): bool
    {
        $timeOnly = Carbon::parse($time->format('H:i'));
        $start = Carbon::parse($this->start_time->format('H:i'));
        $end = Carbon::parse($this->end_time->format('H:i'));

        if ($end->lessThan($start)) {
            // Overnight shift
            return $timeOnly->greaterThanOrEqualTo($start) || $timeOnly->lessThanOrEqualTo($end);
        }

        return $timeOnly->between($start, $end);
    }

    protected static function booted(): void
    {
        static::saving(function (Shift $shift) {
            $shift->work_minutes = $shift->calculateWorkMinutes();
        });
    }
}
```

### 6.8 ShiftPattern

```php
<?php
// src/Models/ShiftPattern.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class ShiftPattern extends BaseModel
{
    use SoftDeletes;

    protected $table = 'shift_patterns';

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'name_en',
        'cycle_days',
        'pattern',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cycle_days' => 'integer',
        'pattern' => 'array',
        'metadata' => 'array',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeShiftAssignment::class);
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForBranch(Builder $query, $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    // ═══════════════════════════════════════════════════════════
    // METHODS
    // ═══════════════════════════════════════════════════════════

    public function getShiftForDay(int $dayInCycle): ?Shift
    {
        $patternItem = collect($this->pattern)->firstWhere('day', $dayInCycle);

        if ($patternItem && isset($patternItem['shift_id'])) {
            return Shift::find($patternItem['shift_id']);
        }

        return null;
    }

    public function getShiftForDate(\Carbon\Carbon $date, \Carbon\Carbon $startDate): ?Shift
    {
        $daysSinceStart = $startDate->diffInDays($date);
        $dayInCycle = $daysSinceStart % $this->cycle_days;

        return $this->getShiftForDay($dayInCycle);
    }
}
```

### 6.9 EmployeeShiftAssignment

```php
<?php
// src/Models/EmployeeShiftAssignment.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class EmployeeShiftAssignment extends BaseModel
{
    protected $table = 'employee_shift_assignments';

    protected $fillable = [
        'employee_id',
        'shift_id',
        'shift_pattern_id',
        'effective_date',
        'end_date',
        'pattern_start_date',
        'is_active',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'end_date' => 'date',
        'pattern_start_date' => 'date',
        'is_active' => 'boolean',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function shiftPattern(): BelongsTo
    {
        return $this->belongsTo(ShiftPattern::class);
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeActiveAt(Builder $query, $date): Builder
    {
        return $query->where('effective_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            });
    }

    // ═══════════════════════════════════════════════════════════
    // METHODS
    // ═══════════════════════════════════════════════════════════

    public function getShiftForDate(\Carbon\Carbon $date): ?Shift
    {
        if ($this->shift_id) {
            return $this->shift;
        }

        if ($this->shift_pattern_id && $this->pattern_start_date) {
            return $this->shiftPattern->getShiftForDate($date, $this->pattern_start_date);
        }

        return null;
    }
}
```

### 6.10 Holiday

```php
<?php
// src/Models/Holiday.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Builder;

class Holiday extends BaseModel
{
    protected $table = 'holidays';

    protected $fillable = [
        'branch_id',
        'date',
        'name',
        'name_en',
        'type',
        'is_recurring',
        'recurring_month',
        'recurring_day',
    ];

    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
        'recurring_month' => 'integer',
        'recurring_day' => 'integer',
    ];

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeForBranch(Builder $query, $branchId): Builder
    {
        return $query->where(function ($q) use ($branchId) {
            $q->whereNull('branch_id')
                ->orWhere('branch_id', $branchId);
        });
    }

    public function scopeForDate(Builder $query, $date): Builder
    {
        return $query->where('date', $date);
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('date', $year);
    }

    public function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('date', $year)
            ->whereMonth('date', $month);
    }

    // ═══════════════════════════════════════════════════════════
    // STATIC METHODS
    // ═══════════════════════════════════════════════════════════

    public static function isHoliday(\Carbon\Carbon $date, $branchId = null): bool
    {
        return static::forBranch($branchId)
            ->forDate($date)
            ->exists();
    }

    public static function getHolidaysForMonth(int $year, int $month, $branchId = null): array
    {
        return static::forBranch($branchId)
            ->forMonth($year, $month)
            ->pluck('date')
            ->map(fn($d) => $d->format('Y-m-d'))
            ->toArray();
    }
}
```

### 6.11 AttendanceRecord

```php
<?php
// src/Models/AttendanceRecord.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use YourVendor\Hr\Enums\AttendanceStatus;

class AttendanceRecord extends BaseModel
{
    protected $table = 'attendance_records';

    protected $fillable = [
        'employee_id',
        'date',
        'clock_in',
        'clock_out',
        'shift_id',
        'work_minutes',
        'late_minutes',
        'early_leave_minutes',
        'overtime_minutes',
        'overtime_night_minutes',
        'overtime_holiday_minutes',
        'status',
        'source',
        'notes',
        'raw_data',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'status' => AttendanceStatus::class,
        'work_minutes' => 'integer',
        'late_minutes' => 'integer',
        'early_leave_minutes' => 'integer',
        'overtime_minutes' => 'integer',
        'overtime_night_minutes' => 'integer',
        'overtime_holiday_minutes' => 'integer',
        'raw_data' => 'array',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeForEmployee(Builder $query, $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForDate(Builder $query, $date): Builder
    {
        return $query->where('date', $date);
    }

    public function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('date', $year)
            ->whereMonth('date', $month);
    }

    public function scopeBetweenDates(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    public function scopePresent(Builder $query): Builder
    {
        return $query->where('status', AttendanceStatus::Present);
    }

    public function scopeAbsent(Builder $query): Builder
    {
        return $query->where('status', AttendanceStatus::Absent);
    }

    // ═══════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════

    public function getWorkHoursAttribute(): float
    {
        return round($this->work_minutes / 60, 2);
    }

    public function getOvertimeHoursAttribute(): float
    {
        return round($this->overtime_minutes / 60, 2);
    }

    public function getTotalOvertimeMinutesAttribute(): int
    {
        return $this->overtime_minutes + 
               $this->overtime_night_minutes + 
               $this->overtime_holiday_minutes;
    }

    // ═══════════════════════════════════════════════════════════
    // METHODS
    // ═══════════════════════════════════════════════════════════

    public function isPresent(): bool
    {
        return $this->status === AttendanceStatus::Present;
    }

    public function isAbsent(): bool
    {
        return $this->status === AttendanceStatus::Absent;
    }

    public function hasClockIn(): bool
    {
        return !is_null($this->clock_in);
    }

    public function hasClockOut(): bool
    {
        return !is_null($this->clock_out);
    }

    public function isComplete(): bool
    {
        return $this->hasClockIn() && $this->hasClockOut();
    }
}
```

### 6.12 OvertimeRecord

```php
<?php
// src/Models/OvertimeRecord.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use YourVendor\Hr\Enums\OvertimeType;

class OvertimeRecord extends BaseModel
{
    protected $table = 'overtime_records';

    protected $fillable = [
        'employee_id',
        'attendance_record_id',
        'date',
        'calculated_minutes',
        'approved_minutes',
        'type',
        'status',
        'hr_document_id',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'type' => OvertimeType::class,
        'calculated_minutes' => 'integer',
        'approved_minutes' => 'integer',
        'approved_at' => 'datetime',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(HrDocument::class, 'hr_document_id');
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeForEmployee(Builder $query, $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('date', $year)
            ->whereMonth('date', $month);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    // ═══════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════

    public function getEffectiveMinutesAttribute(): int
    {
        return $this->approved_minutes ?? $this->calculated_minutes;
    }

    public function getRateAttribute(): float
    {
        return $this->type->rate();
    }
}
```

### 6.13 LeaveRequest

```php
<?php
// src/Models/LeaveRequest.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use YourVendor\Hr\Enums\LeaveRequestStatus;

class LeaveRequest extends BaseModel
{
    protected $table = 'leave_requests';

    protected $fillable = [
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'days',
        'hours',
        'reason',
        'status',
        'hr_document_id',
        'substitute_employee_id',
        'attachments',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => LeaveRequestStatus::class,
        'days' => 'decimal:2',
        'hours' => 'decimal:2',
        'attachments' => 'array',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(HrDocument::class, 'hr_document_id');
    }

    public function substituteEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'substitute_employee_id');
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeForEmployee(Builder $query, $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('start_date', $year);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LeaveRequestStatus::Pending);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', LeaveRequestStatus::Approved);
    }

    public function scopeOverlapping(Builder $query, $startDate, $endDate): Builder
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
                ->orWhereBetween('end_date', [$startDate, $endDate])
                ->orWhere(function ($q2) use ($startDate, $endDate) {
                    $q2->where('start_date', '<=', $startDate)
                        ->where('end_date', '>=', $endDate);
                });
        });
    }

    // ═══════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════

    public function getTypeConfigAttribute(): array
    {
        return config("hr.leave.types.{$this->type}", []);
    }

    public function getTypeNameAttribute(): string
    {
        return $this->type_config['name'] ?? $this->type;
    }

    public function isPaidAttribute(): bool
    {
        return $this->type_config['paid'] ?? true;
    }

    // ═══════════════════════════════════════════════════════════
    // METHODS
    // ═══════════════════════════════════════════════════════════

    public function isPending(): bool
    {
        return $this->status === LeaveRequestStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === LeaveRequestStatus::Approved;
    }

    public function isHourly(): bool
    {
        return $this->type === 'hourly';
    }

    public function canCancel(): bool
    {
        return $this->isPending() && $this->start_date->isFuture();
    }
}
```

### 6.14 MissionRequest

```php
<?php
// src/Models/MissionRequest.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use YourVendor\Hr\Enums\LeaveRequestStatus;

class MissionRequest extends BaseModel
{
    protected $table = 'mission_requests';

    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'destination',
        'purpose',
        'days',
        'transportation',
        'requires_accommodation',
        'status',
        'hr_document_id',
        'expenses',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => LeaveRequestStatus::class,
        'requires_accommodation' => 'boolean',
        'days' => 'decimal:2',
        'expenses' => 'array',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(HrDocument::class, 'hr_document_id');
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeForEmployee(Builder $query, $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LeaveRequestStatus::Pending);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', LeaveRequestStatus::Approved);
    }
}
```

### 6.15 LeaveBalance

```php
<?php
// src/Models/LeaveBalance.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class LeaveBalance extends BaseModel
{
    protected $table = 'leave_balances';

    protected $fillable = [
        'employee_id',
        'year',
        'type',
        'entitled_days',
        'used_days',
        'carried_days',
        'adjustment_days',
        'remaining_days',
        'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'entitled_days' => 'decimal:2',
        'used_days' => 'decimal:2',
        'carried_days' => 'decimal:2',
        'adjustment_days' => 'decimal:2',
        'remaining_days' => 'decimal:2',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeForEmployee(Builder $query, $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    // ═══════════════════════════════════════════════════════════
    // METHODS
    // ═══════════════════════════════════════════════════════════

    public function recalculate(): void
    {
        $this->remaining_days = $this->entitled_days 
                              + $this->carried_days 
                              + $this->adjustment_days 
                              - $this->used_days;
        $this->save();
    }

    public function use(float $days): void
    {
        $this->used_days += $days;
        $this->recalculate();
    }

    public function restore(float $days): void
    {
        $this->used_days -= $days;
        $this->recalculate();
    }

    public function hasBalance(float $days): bool
    {
        return $this->remaining_days >= $days;
    }
}
```

### 6.16 HrDocument

```php
<?php
// src/Models/HrDocument.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use YourVendor\Hr\Enums\DocumentType;
use YourVendor\Hr\Enums\DocumentStatus;
use YourVendor\Hr\Exceptions\DocumentLockedException;

class HrDocument extends BaseModel
{
    use SoftDeletes;

    protected $table = 'documents';

    protected $fillable = [
        'branch_id',
        'employee_id',
        'type',
        'document_number',
        'effective_date',
        'expiry_date',
        'status',
        'data',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
        'locked_at',
        'metadata',
    ];

    protected $casts = [
        'type' => DocumentType::class,
        'status' => DocumentStatus::class,
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
        'data' => 'array',
        'metadata' => 'array',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DocumentAttachment::class, 'hr_document_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class, 'hr_document_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(DocumentHistory::class, 'hr_document_id');
    }

    public function creator(): BelongsTo
    {
        $userModel = config('hr.models.user');
        return $this->belongsTo($userModel, 'created_by');
    }

    public function approver(): BelongsTo
    {
        $userModel = config('hr.models.user');
        return $this->belongsTo($userModel, 'approved_by');
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeForEmployee(Builder $query, $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForType(Builder $query, $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeForBranch(Builder $query, $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Draft);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Pending);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Approved);
    }

    public function scopeLocked(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Locked);
    }

    // ═══════════════════════════════════════════════════════════
    // METHODS
    // ═══════════════════════════════════════════════════════════

    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    public function isPending(): bool
    {
        return $this->status === DocumentStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === DocumentStatus::Approved;
    }

    public function isLocked(): bool
    {
        return $this->status === DocumentStatus::Locked;
    }

    public function canEdit(): bool
    {
        return $this->status->canEdit();
    }

    public function canDelete(): bool
    {
        return $this->status->canDelete();
    }

    public function canSubmit(): bool
    {
        return $this->status->canSubmit();
    }

    public function requiresApproval(): bool
    {
        return $this->type->requiresApproval();
    }

    public function ensureEditable(): void
    {
        if (!$this->canEdit()) {
            throw new DocumentLockedException(
                "Document #{$this->document_number} is not editable in {$this->status->value} status."
            );
        }
    }

    public function getDataAttribute($value): array
    {
        return json_decode($value, true) ?? [];
    }

    public function getData(string $key, $default = null)
    {
        return data_get($this->data, $key, $default);
    }

    public function setData(string $key, $value): void
    {
        $data = $this->data;
        data_set($data, $key, $value);
        $this->data = $data;
    }

    // ═══════════════════════════════════════════════════════════
    // DOCUMENT NUMBER GENERATION
    // ═══════════════════════════════════════════════════════════

    protected static function booted(): void
    {
        static::creating(function (HrDocument $document) {
            if (empty($document->document_number)) {
                $document->document_number = static::generateDocumentNumber($document);
            }
        });
    }

    public static function generateDocumentNumber(HrDocument $document): string
    {
        $prefix = strtoupper(substr($document->type->value, 0, 3));
        $year = now()->format('Y');
        $sequence = static::where('type', $document->type)
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }
}
```

### 6.17 DocumentAttachment

```php
<?php
// src/Models/DocumentAttachment.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAttachment extends BaseModel
{
    protected $table = 'document_attachments';

    protected $fillable = [
        'hr_document_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'title',
        'description',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════

    public function document(): BelongsTo
    {
        return $this->belongsTo(HrDocument::class, 'hr_document_id');
    }

    // ═══════════════════════════════════════════════════════════
    // ACCESSORS
    // ═══════════════════════════════════════════════════════════

    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
```

### 6.18 Workflow

```php
<?php
// src/Models/Workflow.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Workflow extends BaseModel
{
    use SoftDeletes;

    protected $table = 'workflows';

    protected $fillable = [
        'branch_id',
        'name',
        'document_type',
        'description',
        'is_active',
        'priority',
        'conditions',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'conditions' => 'array',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('order');
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForDocumentType(Builder $query, string $type): Builder
    {
        return $query->where('document_type', $type);
    }

    public function scopeForBranch(Builder $query, $branchId): Builder
    {
        return $query->where(function ($q) use ($branchId) {
            $q->whereNull('branch_id')
                ->orWhere('branch_id', $branchId);
        });
    }

    // ═══════════════════════════════════════════════════════════
    // STATIC METHODS
    // ═══════════════════════════════════════════════════════════

    public static function findForDocument(HrDocument $document): ?self
    {
        return static::active()
            ->forDocumentType($document->type->value)
            ->forBranch($document->branch_id)
            ->orderByDesc('priority')
            ->orderByDesc('branch_id') // Prefer branch-specific over global
            ->first();
    }
}
```

### 6.19 WorkflowStep

```php
<?php
// src/Models/WorkflowStep.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use YourVendor\Hr\Enums\ApproverType;

class WorkflowStep extends BaseModel
{
    protected $table = 'workflow_steps';

    protected $fillable = [
        'workflow_id',
        'order',
        'name',
        'approver_type',
        'approver_id',
        'condition',
        'is_required',
        'can_reject',
        'timeout_hours',
        'timeout_action',
    ];

    protected $casts = [
        'approver_type' => ApproverType::class,
        'order' => 'integer',
        'is_required' => 'boolean',
        'can_reject' => 'boolean',
        'timeout_hours' => 'integer',
        'condition' => 'array',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class);
    }

    // ═══════════════════════════════════════════════════════════
    // METHODS
    // ═══════════════════════════════════════════════════════════

    public function meetsCondition(HrDocument $document): bool
    {
        if (empty($this->condition)) {
            return true;
        }

        foreach ($this->condition as $key => $value) {
            $documentValue = $document->getData($key);

            if (is_array($value)) {
                if (isset($value['gt']) && $documentValue <= $value['gt']) {
                    return false;
                }
                if (isset($value['gte']) && $documentValue < $value['gte']) {
                    return false;
                }
                if (isset($value['lt']) && $documentValue >= $value['lt']) {
                    return false;
                }
                if (isset($value['lte']) && $documentValue > $value['lte']) {
                    return false;
                }
            } else {
                if ($documentValue != $value) {
                    return false;
                }
            }
        }

        return true;
    }

    public function getApproverUserId(HrDocument $document): ?int
    {
        return match ($this->approver_type) {
            ApproverType::User => $this->approver_id,
            ApproverType::DepartmentHead => $this->getDepartmentHeadUserId($document),
            ApproverType::Position => $this->getPositionUserId($document),
            default => null,
        };
    }

    protected function getDepartmentHeadUserId(HrDocument $document): ?int
    {
        // Get department head from employee's department
        $employee = $document->employee;
        $department = $employee->department;

        if (!$department) {
            return null;
        }

        // Find employee with head position in this department
        // This is customizable based on your business logic
        return null;
    }

    protected function getPositionUserId(HrDocument $document): ?int
    {
        // Find employee with specific position
        $position = Position::find($this->approver_id);
        if (!$position) {
            return null;
        }

        $employeePosition = EmployeePosition::where('position_id', $position->id)
            ->where('is_primary', true)
            ->whereNull('end_date')
            ->first();

        return $employeePosition?->employee?->employable_id;
    }
}
```

### 6.20 DocumentApproval

```php
<?php
// src/Models/DocumentApproval.php

namespace YourVendor\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use YourVendor\Hr\Enums\ApprovalStatus;

class DocumentApproval extends BaseModel
{
    protected $table = 'document_approvals';

    protected $fillable = [
        'hr_document_id',
        'workflow_step_id',
        'assigned_to',
        'status',
        'comment',
        'acted_at',
        'deadline_at',
    ];

    protected $casts = [
        'status' => ApprovalStatus::class,
        'acted_at' => 'datetime',
        'deadline_at' => 'datetime',
    ];

    // ═══════════════════════════════════════════════════════════
    // RELATIONSHIPS
    // ═══════════════════════════════════════════════════════════

    public function document(): BelongsTo
    {
        return $this->belongsTo(HrDocument::class, 'hr_document_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id');
    }

    public function assignee(): BelongsTo
    {
        $userModel = config('hr.models.user');
        return $this->belongsTo($userModel, 'assigned_to');
    }

    // ═══════════════════════════════════════════════════════════
    // SCOPES
    // ═══════════════════════════════════════════════════════════

    public function scopeForUser(Builder $query, $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ApprovalStatus::Pending);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->pending()
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<', now());
    }

    // ═══════════════════════════════════════════════════════════
    // METHODS
    // ═══════════════════════════════════════════════════════════

    public function isPending(): bool
    {
        return $this->status === ApprovalStatus::Pending;
    }