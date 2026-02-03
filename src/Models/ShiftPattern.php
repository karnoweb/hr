<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShiftPattern extends BaseModel
{
    use SoftDeletes;

    protected $table = 'shift_patterns';

    protected $fillable = [
        'branch_id', 'code', 'name', 'name_en', 'cycle_days', 'pattern', 'is_active', 'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cycle_days' => 'integer',
        'pattern' => 'array',
        'metadata' => 'array',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeShiftAssignment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
