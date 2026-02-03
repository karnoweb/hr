<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Karnoweb\Hr\Enums\ApproverType;

class WorkflowStep extends BaseModel
{
    protected $table = 'workflow_steps';

    protected $fillable = [
        'workflow_id', 'order', 'name', 'approver_type', 'approver_id', 'condition',
        'is_required', 'can_reject', 'timeout_hours', 'timeout_action',
    ];

    protected $casts = [
        'approver_type' => ApproverType::class,
        'order' => 'integer',
        'is_required' => 'boolean',
        'can_reject' => 'boolean',
        'timeout_hours' => 'integer',
        'condition' => 'array',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class);
    }
}
