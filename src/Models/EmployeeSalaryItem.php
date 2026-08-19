<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $employee_salary_id
 * @property int $salary_item_id
 * @property float|string $value
 * @property-read SalaryItem|null $salaryItem
 */
class EmployeeSalaryItem extends BaseModel
{
    protected $table = 'employee_salary_items';

    protected $fillable = ['employee_salary_id', 'salary_item_id', 'value'];

    protected $casts = ['value' => 'decimal:2'];

    public function employeeSalary(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalary::class);
    }

    public function salaryItem(): BelongsTo
    {
        return $this->belongsTo(SalaryItem::class);
    }
}
