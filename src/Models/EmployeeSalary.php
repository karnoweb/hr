<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeSalary extends BaseModel
{
    protected $table = 'employee_salaries';

    protected $fillable = [
        'employee_id', 'salary_structure_id', 'base_salary', 'effective_date', 'end_date', 'hr_document_id', 'is_current',
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
