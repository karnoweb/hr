<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Karnoweb\Hr\Enums\EmployeeStatus;
use Karnoweb\Hr\Exceptions\InvalidEmployeeLifecycleException;

/**
 * @property int $id
 * @property string $employable_type
 * @property int|string $employable_id
 * @property int|null $branch_id
 * @property string $employee_code
 * @property Carbon $hire_date
 * @property Carbon|null $termination_date
 * @property EmployeeStatus $status
 * @property string|null $national_id
 * @property bool $allowLifecycleStatusChange Internal flag set by EmployeeService lifecycle methods.
 * @property bool $insurance_exempt
 * @property bool $tax_exempt
 * @property float|null $additional_tax_exemption
 * @property int $dependents_count
 */
class Employee extends BaseModel
{
    use SoftDeletes;

    protected $table = 'employees';

    /**
     * When true, status may be changed on this instance (set only by EmployeeService
     * terminate / reactivate / suspend). Direct `$employee->update(['status' => …])`
     * is hard-blocked — see InvalidEmployeeLifecycleException.
     */
    public bool $allowLifecycleStatusChange = false;

    protected $fillable = [
        'employable_type', 'employable_id', 'branch_id', 'employee_code', 'hire_date', 'termination_date',
        'status', 'national_id', 'insurance_number', 'birth_date', 'gender', 'marital_status', 'dependents_count',
        'insurance_exempt', 'tax_exempt', 'additional_tax_exemption',
        'education_level', 'military_status', 'bank_name', 'bank_account', 'bank_sheba', 'address', 'phone',
        'emergency_contact', 'emergency_phone', 'metadata',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'termination_date' => 'date',
        'birth_date' => 'date',
        'status' => EmployeeStatus::class,
        'metadata' => 'array',
        'dependents_count' => 'integer',
        'insurance_exempt' => 'boolean',
        'tax_exempt' => 'boolean',
        'additional_tax_exemption' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (Employee $employee): void {
            if (
                $employee->hire_date !== null
                && $employee->termination_date !== null
                && $employee->termination_date->copy()->startOfDay()->lt($employee->hire_date->copy()->startOfDay())
            ) {
                throw new InvalidEmployeeLifecycleException(
                    'termination_date must be on or after hire_date.'
                );
            }
        });

        static::updating(function (Employee $employee): void {
            if ($employee->isDirty('status') && ! $employee->allowLifecycleStatusChange) {
                throw new InvalidEmployeeLifecycleException(
                    'Employee status must be changed via EmployeeService::terminate(), reactivate(), or suspend().'
                );
            }
        });

        static::saved(function (Employee $employee): void {
            $employee->allowLifecycleStatusChange = false;
        });
    }

    /**
     * Mark the next status write as an authorized lifecycle transition.
     */
    public function applyLifecycleStatus(EmployeeStatus $status): static
    {
        $this->allowLifecycleStatusChange = true;
        $this->status = $status;

        return $this;
    }

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
        return $this->hasOne(Contract::class)->where('status', 'active')->latest('start_date');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(EmployeePosition::class);
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(EmployeeShiftAssignment::class);
    }

    public function currentPosition(): HasOne
    {
        return $this->hasOne(EmployeePosition::class)->where('is_primary', true)->whereNull('end_date')->latest('effective_date');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
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
        return $this->hasOne(EmployeeSalary::class)->where('is_current', true);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function payrollRecords(): HasMany
    {
        return $this->hasMany(PayrollRecord::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EmployeeStatus::Active);
    }

    public function scopeForBranch(Builder $query, $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function isActive(): bool
    {
        return $this->status === EmployeeStatus::Active;
    }
}
