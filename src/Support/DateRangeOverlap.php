<?php

namespace Karnoweb\Hr\Support;

use Illuminate\Support\Carbon;

/**
 * Shared date-range overlap checks for leave and mission requests.
 */
final class DateRangeOverlap
{
    public static function overlaps(Carbon $startA, Carbon $endA, Carbon $startB, Carbon $endB): bool
    {
        return $startA->lte($endB) && $endA->gte($startB);
    }

    public static function rangesOverlap(
        Carbon|string $startA,
        Carbon|string $endA,
        Carbon|string $startB,
        Carbon|string $endB
    ): bool {
        $aStart = Carbon::parse($startA)->startOfDay();
        $aEnd = Carbon::parse($endA)->startOfDay();
        $bStart = Carbon::parse($startB)->startOfDay();
        $bEnd = Carbon::parse($endB)->startOfDay();

        return self::overlaps($aStart, $aEnd, $bStart, $bEnd);
    }
}
