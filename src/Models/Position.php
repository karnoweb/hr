<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Karnoweb\Hr\Exceptions\InvalidOrganizationStructureException;

/**
 * @property int|null $branch_id
 * @property string $code
 */
class Position extends BaseModel
{
    use SoftDeletes;

    protected $table = 'positions';

    protected $fillable = [
        'branch_id', 'code', 'name', 'name_en', 'description', 'grade', 'sort_order', 'is_active', 'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
        'grade' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Position $position): void {
            if (
                $position->branch_id === null
                && $position->isDirty('code')
                && static::query()
                    ->whereNull('branch_id')
                    ->where('code', $position->code)
                    ->when($position->exists, fn ($q) => $q->whereKeyNot($position->getKey()))
                    ->exists()
            ) {
                throw new InvalidOrganizationStructureException(
                    'A global position with this code already exists.'
                );
            }
        });
    }

    public function employeePositions(): HasMany
    {
        return $this->hasMany(EmployeePosition::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForBranch(Builder $query, $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }
}
