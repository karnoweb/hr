<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Karnoweb\Hr\Enums\WorkflowExecutionMode;

/**
 * @property int|null $branch_id
 * @property WorkflowExecutionMode|string $execution_mode
 * @property array|null $conditions
 */
class Workflow extends BaseModel
{
    use SoftDeletes;

    protected $table = 'workflows';

    protected $fillable = [
        'branch_id', 'name', 'document_type', 'description', 'is_active', 'priority', 'execution_mode', 'conditions',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'conditions' => 'array',
        'execution_mode' => WorkflowExecutionMode::class,
    ];

    /** @return HasMany<WorkflowStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForDocumentType(Builder $query, string $type): Builder
    {
        return $query->where('document_type', $type);
    }

    public static function findForDocument(HrDocument $document): ?self
    {
        return static::active()
            ->forDocumentType($document->type->value)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $document->branch_id))
            ->orderByDesc('priority')
            ->first();
    }
}
