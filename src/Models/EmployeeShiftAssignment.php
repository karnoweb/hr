<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeShiftAssignment extends BaseModel
{
    protected $table = 'employee_shift_assignments';

    protected $fillable = [
        'employee_id', 'shift_id', 'shift_pattern_id', 'effective_date', 'end_date', 'pattern_start_date', 'is_active',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'end_date' => 'date',
        'pattern_start_date' => 'date',
        'is_active' => 'boolean',
    ];

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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
