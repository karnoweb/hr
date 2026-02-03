<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Karnoweb\Hr\Enums\PayrollPeriodStatus;

class PayrollPeriod extends BaseModel
{
    protected $table = 'payroll_periods';

    protected $fillable = [
        'branch_id', 'year', 'month', 'start_date', 'end_date', 'working_days', 'status',
        'calculated_at', 'approved_at', 'paid_at', 'locked_at', 'approved_by', 'notes', 'summary',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => PayrollPeriodStatus::class,
        'calculated_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'locked_at' => 'datetime',
        'summary' => 'array',
    ];

    public function records(): HasMany
    {
        return $this->hasMany(PayrollRecord::class, 'payroll_period_id');
    }

    public function scopeForBranch(Builder $query, $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }
}
