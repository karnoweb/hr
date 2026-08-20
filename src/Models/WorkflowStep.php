<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Karnoweb\Hr\Enums\ApproverType;
use Karnoweb\Hr\Enums\TimeoutAction;
use Karnoweb\Hr\Services\WorkflowValidator;

/**
 * @property int $id
 * @property int|null $approver_id
 * @property int|null $timeout_hours
 * @property int|null $escalation_user_id
 * @property TimeoutAction|string|null $timeout_action
 * @property bool $can_reject
 * @property bool $is_required
 * @property array|null $condition
 * @property string $name
 * @property ApproverType $approver_type
 */
class WorkflowStep extends BaseModel
{
    protected $table = 'workflow_steps';

    protected $fillable = [
        'workflow_id', 'order', 'name', 'approver_type', 'approver_id', 'condition',
        'is_required', 'can_reject', 'timeout_hours', 'timeout_action', 'escalation_user_id',
    ];

    protected $casts = [
        'approver_type' => ApproverType::class,
        'order' => 'integer',
        'is_required' => 'boolean',
        'can_reject' => 'boolean',
        'timeout_hours' => 'integer',
        'condition' => 'array',
        'timeout_action' => TimeoutAction::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (WorkflowStep $step): void {
            app(WorkflowValidator::class)->validateStep($step);
        });
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class);
    }
}
