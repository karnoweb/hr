<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Karnoweb\Hr\Enums\LeaveRequestStatus;

class MissionRequest extends BaseModel
{
    protected $table = 'mission_requests';

    protected $fillable = [
        'employee_id', 'start_date', 'end_date', 'start_time', 'end_time', 'destination', 'purpose', 'days',
        'transportation', 'requires_accommodation', 'status', 'hr_document_id', 'expenses',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => LeaveRequestStatus::class,
        'requires_accommodation' => 'boolean',
        'days' => 'decimal:2',
        'expenses' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LeaveRequestStatus::Pending);
    }
}
