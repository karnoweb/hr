<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends BaseModel
{
    use SoftDeletes;

    protected $table = 'shifts';

    protected $fillable = [
        'branch_id', 'code', 'name', 'name_en', 'start_time', 'end_time', 'break_start', 'break_end',
        'work_minutes', 'is_night_shift', 'is_active', 'color', 'metadata',
    ];

    protected $casts = [
        'is_night_shift' => 'boolean',
        'is_active' => 'boolean',
        'work_minutes' => 'integer',
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
