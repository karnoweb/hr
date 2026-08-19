<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $salary_structure_id
 * @property int $salary_item_id
 * @property float|string $value
 * @property-read SalaryItem|null $salaryItem
 */
class SalaryStructureItem extends BaseModel
{
    protected $table = 'salary_structure_items';

    protected $fillable = ['salary_structure_id', 'salary_item_id', 'value'];

    protected $casts = ['value' => 'decimal:2'];

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
    }

    public function salaryItem(): BelongsTo
    {
        return $this->belongsTo(SalaryItem::class);
    }
}
