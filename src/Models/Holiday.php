<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;

class Holiday extends BaseModel
{
    protected $table = 'holidays';

    protected $fillable = [
        'branch_id', 'date', 'name', 'name_en', 'type', 'is_recurring', 'recurring_month', 'recurring_day',
    ];

    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
    ];

    public function scopeForBranch(Builder $query, $branchId): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId));
    }

    public function scopeForDate(Builder $query, $date): Builder
    {
        return $query->where('date', $date);
    }
}
