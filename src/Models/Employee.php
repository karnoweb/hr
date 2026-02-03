<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Karnoweb\Hr\Enums\EmployeeStatus;

class Employee extends BaseModel
{
    use SoftDeletes;

    protected $table = 'employees';

    protected $fillable = [
        'employable_type', 'employable_id', 'branch_id', 'employee_code', 'hire_date', 'termination_date',
        'status', 'national_id', 'insurance_number', 'birth_date', 'gender', 'marital_status', 'dependents_count',
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
    ];

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
