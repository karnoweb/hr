<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Karnoweb\Hr\Enums\LeaveRequestStatus;

/**
 * @property int $employee_id
 * @property string $type
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string|float $days
 * @property string|float $hours
 * @property LeaveRequestStatus $status
 * @property int|null $hr_document_id
 * @property string|null $reason
 */
class LeaveRequest extends BaseModel
{
    protected $table = 'leave_requests';

    protected $fillable = [
        'employee_id', 'type', 'start_date', 'end_date', 'start_time', 'end_time', 'days', 'hours',
        'reason', 'status', 'hr_document_id', 'substitute_employee_id', 'attachments',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => LeaveRequestStatus::class,
        'days' => 'decimal:2',
        'hours' => 'decimal:2',
        'attachments' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function hrDocument(): BelongsTo
    {
        return $this->belongsTo(HrDocument::class, 'hr_document_id');
    }

    public function substituteEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'substitute_employee_id');
    }

    public function scopeForEmployee(Builder $query, $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LeaveRequestStatus::Pending);
    }
}
