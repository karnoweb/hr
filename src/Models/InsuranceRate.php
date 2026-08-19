<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $effective_date
 * @property float $employee_rate
 * @property float $employer_rate
 * @property float $unemployment_rate
 * @property float $ceiling_multiplier
 */
class InsuranceRate extends BaseModel
{
    protected $table = 'insurance_rates';

    protected $fillable = [
        'effective_date', 'employee_rate', 'employer_rate', 'unemployment_rate', 'ceiling_multiplier', 'notes',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'employee_rate' => 'decimal:2',
        'employer_rate' => 'decimal:2',
        'unemployment_rate' => 'decimal:2',
        'ceiling_multiplier' => 'decimal:2',
    ];

    public static function forDate(\DateTimeInterface|string $date): ?self
    {
        $dateString = Carbon::parse($date)->toDateString();

        return static::query()
            ->whereDate('effective_date', '<=', $dateString)
            ->orderByDesc('effective_date')
            ->first();
    }

    public function scopeEffectiveOn(Builder $query, Carbon $date): Builder
    {
        return $query
            ->whereDate('effective_date', '<=', $date->toDateString())
            ->orderByDesc('effective_date');
    }
}
