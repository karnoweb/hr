<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Karnoweb\Hr\Enums\LeaveRequestStatus;

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

    public function scopeForEmployee(Builder $query, $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LeaveRequestStatus::Pending);
    }
}
