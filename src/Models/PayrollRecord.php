<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Karnoweb\Hr\Enums\PayrollRecordStatus;

/**
 * @property int $payroll_period_id
 * @property int $employee_id
 * @property float $base_salary
 * @property float $gross_salary
 * @property float $net_salary
 * @property float $payable
 * @property float $insurance_employee
 * @property float $insurance_employer
 * @property float $insurance_unemployment
 * @property float $taxable_income
 * @property float $tax
 * @property float $loan_deduction
 * @property array<string, mixed>|null $calculation_log
 * @property PayrollRecordStatus $status
 */
class PayrollRecord extends BaseModel
{
    protected $table = 'payroll_records';

    protected $fillable = [
        'payroll_period_id', 'employee_id', 'hr_document_id',
        'working_days', 'present_days', 'absent_days', 'leave_days_paid', 'leave_days_unpaid', 'mission_days',
        'late_minutes', 'early_leave_minutes', 'overtime_minutes', 'overtime_night_minutes', 'overtime_holiday_minutes',
        'earnings', 'deductions', 'base_salary', 'gross_earnings', 'total_deductions', 'gross_salary',
        'insurance_employee', 'insurance_employer', 'insurance_unemployment', 'taxable_income', 'tax',
        'net_salary', 'loan_deduction', 'payable', 'status', 'notes', 'calculation_log',
    ];

    protected $casts = [
        'earnings' => 'array',
        'deductions' => 'array',
        'calculation_log' => 'array',
        'status' => PayrollRecordStatus::class,
        'base_salary' => 'decimal:2',
        'gross_earnings' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'payable' => 'decimal:2',
    ];

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeForPeriod(Builder $query, $periodId): Builder
    {
        return $query->where('payroll_period_id', $periodId);
    }
}
