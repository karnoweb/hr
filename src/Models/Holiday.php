<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

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
        if ($branchId === null) {
            return $query->whereNull('branch_id');
        }

        return $query->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId));
    }

    public function scopeForDate(Builder $query, $date): Builder
    {
        $dateString = $date instanceof \DateTimeInterface
            ? Carbon::instance($date)->toDateString()
            : Carbon::parse($date)->toDateString();

        return $query->whereDate('date', $dateString);
    }
}
