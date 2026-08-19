<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $current_key Set to employee_id for the single current primary row; null otherwise.
 */
class EmployeePosition extends BaseModel
{
    protected $table = 'employee_positions';

    protected $fillable = [
        'employee_id', 'department_id', 'position_id', 'is_primary', 'effective_date', 'end_date',
        'current_key', 'hr_document_id', 'metadata',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'effective_date' => 'date',
        'end_date' => 'date',
        'metadata' => 'array',
    ];

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

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('end_date');
    }
}
