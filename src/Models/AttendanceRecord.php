<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Karnoweb\Hr\Enums\AttendanceStatus;

/**
 * @property int $employee_id
 * @property Carbon $date
 * @property Carbon|null $clock_in
 * @property Carbon|null $clock_out
 * @property int|null $shift_id
 * @property int $work_minutes
 * @property int $late_minutes
 * @property int $early_leave_minutes
 * @property int $overtime_minutes
 * @property int $overtime_night_minutes
 * @property int $overtime_holiday_minutes
 * @property AttendanceStatus $status
 * @property string $source
 * @property string|null $notes
 * @property array|null $raw_data
 */
class AttendanceRecord extends BaseModel
{
    protected $table = 'attendance_records';

    protected $fillable = [
        'employee_id', 'date', 'clock_in', 'clock_out', 'shift_id', 'work_minutes', 'late_minutes',
        'early_leave_minutes', 'overtime_minutes', 'overtime_night_minutes', 'overtime_holiday_minutes',
        'status', 'source', 'notes', 'raw_data',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'status' => AttendanceStatus::class,
        'work_minutes' => 'integer',
        'raw_data' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function scopeForEmployee(Builder $query, $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForDate(Builder $query, $date): Builder
    {
        return $query->where('date', $date);
    }
}
