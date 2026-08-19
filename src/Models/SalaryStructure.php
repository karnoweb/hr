<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read Collection<int, SalaryStructureItem> $items
 */
class SalaryStructure extends BaseModel
{
    use SoftDeletes;

    protected $table = 'salary_structures';

    protected $fillable = [
        'branch_id', 'code', 'name', 'name_en', 'description', 'is_default', 'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SalaryStructureItem::class, 'salary_structure_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
