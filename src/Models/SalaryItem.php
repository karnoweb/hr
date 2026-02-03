<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Karnoweb\Hr\Enums\CalculationType;
use Karnoweb\Hr\Enums\SalaryItemType;

class SalaryItem extends BaseModel
{
    use SoftDeletes;

    protected $table = 'salary_items';

    protected $fillable = [
        'branch_id', 'code', 'name', 'name_en', 'type', 'calculation_type', 'default_value', 'formula',
        'percentage_of', 'is_taxable', 'is_insurable', 'is_active', 'sort_order', 'metadata',
    ];

    protected $casts = [
        'type' => SalaryItemType::class,
        'calculation_type' => CalculationType::class,
        'is_taxable' => 'boolean',
        'is_insurable' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'default_value' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
