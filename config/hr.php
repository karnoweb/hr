<?php

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
        'type' => env('HR_CALENDAR_TYPE', 'jalali'),
        'week_starts_on' => 'saturday',
        'year_starts_on' => 'farvardin',
        'locale' => 'fa',
    ],

    /*
    |--------------------------------------------------------------------------
    | Employee Code Generation
    |--------------------------------------------------------------------------
    */
    'employee_code' => [
        'auto_generate' => true,
        // Placeholders: {year}, {sequence}, {branch}
        // When sequence_per_branch=true, format MUST include {branch} or generation throws.
        'format' => '{year}-{sequence}',
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
        'termination' => [
            // forfeit | payout | carry
            'balance_policy' => 'forfeit',
        ],
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
        'monthly_cap' => 120, // minutes per employee per calendar month
        'requires_pre_approval' => false,
        'min_minutes' => 30,
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
        'corrections' => [
            'require_approval' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Insurance Configuration (Social Security)
    |--------------------------------------------------------------------------
    */
    'insurance' => [
        // NEEDS VERIFICATION (legal/regulatory): rates below seed insurance_rates; verify before production.
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
        // NEEDS VERIFICATION (legal/regulatory): exemption/brackets seed tax_brackets (assumed FY 1403).
        'enabled' => true,
        'annual_exemption' => 672000000,
        'dependents_exemption' => [
            // NEEDS VERIFICATION (legal/regulatory): disabled by default — enable only after legal review.
            'enabled' => false,
            'per_dependent_annual' => 0,
        ],
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
        // Cooldown measured from start_date of the most recent Active/Completed loan.
        'min_months_between_loans' => 6,
        'max_active_loans' => 2,
        'max_percentage_of_salary' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Payroll Configuration
    |--------------------------------------------------------------------------
    */
    'payroll' => [
        'closing_day' => 'end_of_month',
        'minimum_wage' => 53304000, // NEEDS VERIFICATION (legal/regulatory)
        'daily_work_minutes' => 480,
        'payment_day' => 1,
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
        'lock_delay_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Workflow Default Approvers
    |--------------------------------------------------------------------------
    */
    'workflow' => [
        'default_approver_type' => 'department_head',
        'skip_on_no_approver' => false,
        'auto_approve_own_department' => false,
    ],

];
