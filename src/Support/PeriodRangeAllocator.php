<?php

namespace Karnoweb\Hr\Support;

use Carbon\Carbon;

/**
 * Splits leave/mission day totals across calendar windows (payroll period, year).
 *
 * Stored `days` is the source of truth; working days in the overlap determine
 * the share so a range that crosses a month or year is not counted twice.
 */
final class PeriodRangeAllocator
{
    public function __construct(
        protected WorkingDayCalculator $workingDays,
    ) {}

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function overlap(
        Carbon $rangeStart,
        Carbon $rangeEnd,
        Carbon $windowStart,
        Carbon $windowEnd,
    ): ?array {
        $start = $rangeStart->copy()->startOfDay()->max($windowStart->copy()->startOfDay());
        $end = $rangeEnd->copy()->startOfDay()->min($windowEnd->copy()->startOfDay());

        if ($end->lt($start)) {
            return null;
        }

        return [$start, $end];
    }

    public function allocateDaysInWindow(
        Carbon $rangeStart,
        Carbon $rangeEnd,
        Carbon $windowStart,
        Carbon $windowEnd,
        float $totalDays,
        ?int $branchId = null,
    ): float {
        if ($totalDays <= 0) {
            return 0.0;
        }

        $overlap = $this->overlap($rangeStart, $rangeEnd, $windowStart, $windowEnd);

        if ($overlap === null) {
            return 0.0;
        }

        [$overlapStart, $overlapEnd] = $overlap;

        if ($overlapStart->equalTo($rangeStart->copy()->startOfDay())
            && $overlapEnd->equalTo($rangeEnd->copy()->startOfDay())
        ) {
            return round($totalDays, 2);
        }

        $windowWorking = $this->workingDays->count($overlapStart, $overlapEnd, $branchId);
        $totalWorking = $this->workingDays->count($rangeStart, $rangeEnd, $branchId);

        if ($totalWorking > 0) {
            return round($totalDays * ($windowWorking / $totalWorking), 2);
        }

        $windowCalendar = $overlapStart->diffInDays($overlapEnd) + 1;
        $rangeCalendar = $rangeStart->copy()->startOfDay()->diffInDays($rangeEnd->copy()->startOfDay()) + 1;

        if ($rangeCalendar <= 0) {
            return 0.0;
        }

        return round($totalDays * ($windowCalendar / $rangeCalendar), 2);
    }

    /**
     * @return array<int, float> year => allocated days
     */
    public function allocateDaysByYear(
        Carbon $rangeStart,
        Carbon $rangeEnd,
        float $totalDays,
        ?int $branchId = null,
    ): array {
        $start = $rangeStart->copy()->startOfDay();
        $end = $rangeEnd->copy()->startOfDay();

        if ($end->lt($start) || $totalDays <= 0) {
            return [];
        }

        if ((int) $start->year === (int) $end->year) {
            return [(int) $start->year => round($totalDays, 2)];
        }

        $allocated = [];
        $assigned = 0.0;
        $cursor = $start->copy();

        while ($cursor->year <= $end->year) {
            $year = (int) $cursor->year;
            $yearEnd = $cursor->copy()->endOfYear()->startOfDay();
            $windowEnd = $yearEnd->lt($end) ? $yearEnd : $end;
            $isLast = $year === (int) $end->year;

            if ($isLast) {
                $allocated[$year] = round($totalDays - $assigned, 2);
            } else {
                $part = $this->allocateDaysInWindow($start, $end, $cursor, $windowEnd, $totalDays, $branchId);
                $allocated[$year] = $part;
                $assigned += $part;
            }

            $cursor = $cursor->copy()->addYear()->startOfYear();
        }

        return array_filter($allocated, fn (float $days) => $days > 0);
    }
}
