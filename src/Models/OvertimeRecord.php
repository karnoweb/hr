<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Karnoweb\Hr\Enums\OvertimeStatus;
use Karnoweb\Hr\Enums\OvertimeType;

/**
 * @property int $employee_id
 * @property int|null $attendance_record_id
 * @property Carbon $date
 * @property int $calculated_minutes
 * @property int|null $approved_minutes
 * @property OvertimeType $type
 * @property OvertimeStatus $status
 * @property int|null $hr_document_id
 * @property string|null $notes
 */
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
        'status' => OvertimeStatus::class,
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
