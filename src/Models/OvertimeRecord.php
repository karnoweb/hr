<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Karnoweb\Hr\Enums\OvertimeType;

class OvertimeRecord extends BaseModel
{
    protected $table = 'overtime_records';

    protected $fillable = [
        'employee_id', 'attendance_record_id', 'date', 'calculated_minutes', 'approved_minutes',
        'type', 'status', 'hr_document_id', 'approved_by', 'approved_at', 'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'type' => OvertimeType::class,
        'calculated_minutes' => 'integer',
        'approved_minutes' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeForEmployee(Builder $query, $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }
}
