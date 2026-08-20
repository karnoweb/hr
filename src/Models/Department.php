<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Karnoweb\Hr\Exceptions\InvalidOrganizationStructureException;

/**
 * @property int|null $head_employee_id
 * @property int|null $branch_id
 * @property int|null $parent_id
 * @property string $code
 * @property string|null $path
 * @property int|null $level
 */
class Department extends BaseModel
{
    use SoftDeletes;

    protected $table = 'departments';

    protected $fillable = [
        'branch_id', 'parent_id', 'head_employee_id', 'code', 'name', 'name_en', 'description',
        'level', 'path', 'sort_order', 'is_active', 'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'level' => 'integer',
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    public function employeePositions(): HasMany
    {
        return $this->hasMany(EmployeePosition::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeForBranch(Builder $query, $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    protected static function booted(): void
    {
        static::saving(function (Department $department): void {
            if ($department->isDirty('parent_id') && $department->parent_id !== null) {
                $department->assertNoParentCycle((int) $department->parent_id);
            }

            if (
                $department->branch_id === null
                && $department->isDirty('code')
                && static::query()
                    ->whereNull('branch_id')
                    ->where('code', $department->code)
                    ->when($department->exists, fn ($q) => $q->whereKeyNot($department->getKey()))
                    ->exists()
            ) {
                throw new InvalidOrganizationStructureException(
                    'A global department with this code already exists.'
                );
            }
        });

        static::deleting(function (Department $department): void {
            if ($department->children()->exists()) {
                throw new InvalidOrganizationStructureException(
                    'Cannot delete a department that still has child departments. Reassign or remove children first.'
                );
            }
        });

        static::created(function (Department $department) {
            $department->updatePath();
        });

        static::updated(function (Department $department) {
            if ($department->wasChanged('parent_id')) {
                $department->updatePath();
            }
        });
    }

    /**
     * Recompute path/level for this node and all descendants atomically.
     */
    public function updatePath(): void
    {
        DB::transaction(function (): void {
            $this->refreshPathForSubtree($this);
        });
    }

    protected function refreshPathForSubtree(Department $node): void
    {
        $parent = $node->parent_id
            ? static::query()->whereKey($node->parent_id)->first()
            : null;

        $path = $parent ? $parent->path.'/'.$node->id : (string) $node->id;
        $level = substr_count($path, '/');

        $node->forceFill(['path' => $path, 'level' => $level])->saveQuietly();

        static::query()
            ->where('parent_id', $node->getKey())
            ->orderBy('id')
            ->each(function (Department $child): void {
                $this->refreshPathForSubtree($child);
            });
    }

    protected function assertNoParentCycle(int $proposedParentId): void
    {
        if ($this->exists && $proposedParentId === (int) $this->getKey()) {
            throw new InvalidOrganizationStructureException('A department cannot be its own parent.');
        }

        $cursorId = $proposedParentId;
        $visited = [];

        while ($cursorId !== null) {
            if ($this->exists && $cursorId === (int) $this->getKey()) {
                throw new InvalidOrganizationStructureException(
                    'Setting this parent would create a cycle in the department tree.'
                );
            }

            if (isset($visited[$cursorId])) {
                throw new InvalidOrganizationStructureException(
                    'The proposed parent chain contains a cycle.'
                );
            }

            $visited[$cursorId] = true;
            $cursorId = static::query()->whereKey($cursorId)->value('parent_id');
        }
    }
}
