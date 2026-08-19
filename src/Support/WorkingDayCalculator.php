<?php

namespace Karnoweb\Hr\Support;

use Illuminate\Support\Carbon;
use Karnoweb\Hr\Models\Holiday;

/**
 * Shared working-day logic for Attendance, Leave, and Payroll (HR-041).
 */
final class WorkingDayCalculator
{
    /**
     * @return list<Carbon>
     */
    public function dates(Carbon $start, Carbon $end, ?int $branchId = null): array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        if ($end->lt($start)) {
            return [];
        }

        $holidayDates = $this->holidayDatesForRange($start, $end, $branchId);
        $workingDaysConfig = config('hr.working_days', []);
        $dates = [];

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $dayName = strtolower($cursor->englishDayOfWeek);

            if (! ($workingDaysConfig[$dayName] ?? false)) {
                continue;
            }

            $isoDate = $cursor->toDateString();

            if (isset($holidayDates[$isoDate])) {
                continue;
            }

            $dates[] = $cursor->copy();
        }

        return $dates;
    }

    public function count(Carbon $start, Carbon $end, ?int $branchId = null): int
    {
        return count($this->dates($start, $end, $branchId));
    }

    public function isWorkingDay(Carbon $date, ?int $branchId = null): bool
    {
        return count($this->dates($date->copy()->startOfDay(), $date->copy()->startOfDay(), $branchId)) === 1;
    }

    public function isHoliday(Carbon $date, ?int $branchId = null): bool
    {
        return Holiday::query()
            ->forBranch($branchId)
            ->forDate($date->toDateString())
            ->exists();
    }

    /**
     * @return array<string, true> ISO date => true
     */
    protected function holidayDatesForRange(Carbon $start, Carbon $end, ?int $branchId): array
    {
        $rows = Holiday::query()
            ->forBranch($branchId)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->pluck('date');

        $dates = [];

        foreach ($rows as $date) {
            $dates[Carbon::parse($date)->toDateString()] = true;
        }

        return $dates;
    }
}
