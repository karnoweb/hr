<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $employee_id
 * @property int|null $salary_structure_id
 * @property float $base_salary
 * @property Carbon $effective_date
 * @property Carbon|null $end_date
 * @property int|null $hr_document_id
 * @property bool $is_current
 * @property int|null $current_key Set to employee_id for the single current row; null otherwise.
 * @property-read SalaryStructure|null $salaryStructure
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EmployeeSalaryItem> $items
 */
class EmployeeSalary extends BaseModel
{
    protected $table = 'employee_salaries';

    protected $fillable = [
        'employee_id', 'salary_structure_id', 'base_salary', 'effective_date', 'end_date', 'hr_document_id', 'is_current', 'current_key',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'effective_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(EmployeeSalaryItem::class, 'employee_salary_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }
}
