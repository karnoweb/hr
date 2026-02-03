<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends BaseModel
{
    protected $table = 'leave_balances';

    protected $fillable = [
        'employee_id', 'year', 'type', 'entitled_days', 'used_days', 'carried_days', 'adjustment_days', 'remaining_days', 'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'entitled_days' => 'decimal:2',
        'used_days' => 'decimal:2',
        'carried_days' => 'decimal:2',
        'adjustment_days' => 'decimal:2',
        'remaining_days' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeForEmployee(Builder $query, $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }
}
