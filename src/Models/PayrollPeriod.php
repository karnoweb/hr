<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Karnoweb\Hr\Enums\PayrollPeriodStatus;

/**
 * @property int|null $branch_id
 * @property int $year
 * @property int $month
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property int $working_days
 * @property PayrollPeriodStatus $status
 * @property-read Collection<int, PayrollRecord> $records
 */
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
