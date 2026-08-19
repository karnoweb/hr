<?php

namespace Karnoweb\Hr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

/**
 * @property int $cycle_days
 * @property array<int, array{day: int, shift_id?: int|null}>|null $pattern
 */
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

    protected static function booted(): void
    {
        static::saving(function (ShiftPattern $pattern): void {
            $pattern->assertPatternStructure();
        });
    }

    /**
     * pattern JSON must contain exactly cycle_days entries indexed 0..cycle_days-1 (HR-044).
     */
    public function assertPatternStructure(): void
    {
        if (! $this->isDirty('pattern') && ! $this->isDirty('cycle_days')) {
            return;
        }

        $cycleDays = (int) ($this->cycle_days ?? 0);

        if ($cycleDays < 1) {
            throw new InvalidArgumentException('cycle_days must be at least 1.');
        }

        $pattern = $this->pattern;

        if (! is_array($pattern) || count($pattern) !== $cycleDays) {
            throw new InvalidArgumentException(
                "Shift pattern must contain exactly {$cycleDays} day entries matching cycle_days."
            );
        }

        $days = collect($pattern)->pluck('day')->sort()->values()->all();
        $expected = range(0, $cycleDays - 1);

        if ($days !== $expected) {
            throw new InvalidArgumentException(
                'Shift pattern day indexes must be consecutive integers from 0 to '.($cycleDays - 1).'.'
            );
        }
    }

    public function shiftForDay(int $dayInCycle): ?Shift
    {
        $patternItem = collect($this->pattern)->firstWhere('day', $dayInCycle);

        if (! is_array($patternItem) || empty($patternItem['shift_id'])) {
            return null;
        }

        return Shift::query()->find($patternItem['shift_id']);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeShiftAssignment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
