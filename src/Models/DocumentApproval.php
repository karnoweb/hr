<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Karnoweb\Hr\Enums\ApprovalStatus;

class DocumentApproval extends BaseModel
{
    protected $table = 'document_approvals';

    protected $fillable = [
        'hr_document_id', 'workflow_step_id', 'assigned_to', 'status', 'comment', 'acted_at', 'deadline_at',
    ];

    protected $casts = [
        'status' => ApprovalStatus::class,
        'acted_at' => 'datetime',
        'deadline_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(HrDocument::class, 'hr_document_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ApprovalStatus::Pending);
    }
}
