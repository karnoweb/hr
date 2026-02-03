<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends BaseModel
{
    use SoftDeletes;

    protected $table = 'departments';

    protected $fillable = [
        'branch_id', 'parent_id', 'code', 'name', 'name_en', 'description',
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
        static::created(function (Department $department) {
            $department->updatePath();
        });
        static::updated(function (Department $department) {
            if ($department->isDirty('parent_id')) {
                $department->updatePath();
            }
        });
    }

    public function updatePath(): void
    {
        $path = $this->parent ? $this->parent->path . '/' . $this->id : (string) $this->id;
        $level = substr_count($path, '/');
        $this->update(['path' => $path, 'level' => $level]);
        foreach ($this->children as $child) {
            $child->updatePath();
        }
    }
}
