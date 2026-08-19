<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * @property int $fiscal_year
 * @property Carbon $effective_date
 * @property float $annual_exemption
 * @property array<int, array{up_to: int|null, rate: float|int}> $brackets
 */
class TaxBracket extends BaseModel
{
    protected $table = 'tax_brackets';

    protected $fillable = [
        'fiscal_year', 'effective_date', 'annual_exemption', 'brackets', 'notes',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'annual_exemption' => 'decimal:2',
        'brackets' => 'array',
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
