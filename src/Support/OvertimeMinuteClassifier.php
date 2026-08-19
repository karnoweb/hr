<?php

namespace Karnoweb\Hr\Support;

use Carbon\Carbon;

/**
 * Splits overtime minutes into regular, night, and holiday buckets (HR-062).
 */
final class OvertimeMinuteClassifier
{
    /**
     * @return array{regular: int, night: int, holiday: int, total: int}
     */
    public function classify(
        Carbon $overtimeStart,
        Carbon $clockOut,
        Carbon $attendanceDate,
        bool $isHoliday
    ): array {
        if ($clockOut->lte($overtimeStart)) {
            return ['regular' => 0, 'night' => 0, 'holiday' => 0, 'total' => 0];
        }

        $total = (int) $overtimeStart->diffInMinutes($clockOut);
        $night = min($total, $this->nightOverlapMinutes($overtimeStart, $clockOut, $attendanceDate));
        $remaining = $total - $night;

        if ($isHoliday) {
            return [
                'regular' => 0,
                'night' => $night,
                'holiday' => $remaining,
                'total' => $total,
            ];
        }

        return [
            'regular' => $remaining,
            'night' => $night,
            'holiday' => 0,
            'total' => $total,
        ];
    }

    public function nightOverlapMinutes(Carbon $start, Carbon $end, Carbon $attendanceDate): int
    {
        $minutes = 0;

        foreach ($this->nightWindowsForRange($start, $end, $attendanceDate) as [$windowStart, $windowEnd]) {
            $minutes += $this->overlapMinutes($start, $end, $windowStart, $windowEnd);
        }

        return $minutes;
    }

    /**
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    protected function nightWindowsForRange(Carbon $start, Carbon $end, Carbon $attendanceDate): array
    {
        $windows = [];
        $cursor = $attendanceDate->copy()->subDay()->startOfDay();
        $lastDay = $end->copy()->startOfDay();

        while ($cursor->lte($lastDay)) {
            $windows[] = $this->nightWindowForCalendarDay($cursor);
            $cursor->addDay();
        }

        return $windows;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function nightWindowForCalendarDay(Carbon $day): array
    {
        $nightStart = (string) config('hr.overtime.night_start', '22:00');
        $nightEnd = (string) config('hr.overtime.night_end', '06:00');

        $start = Carbon::parse($day->toDateString().' '.$this->normalizeTime($nightStart));
        $end = Carbon::parse($day->toDateString().' '.$this->normalizeTime($nightEnd));

        if ($end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    protected function overlapMinutes(Carbon $aStart, Carbon $aEnd, Carbon $bStart, Carbon $bEnd): int
    {
        $start = $aStart->greaterThan($bStart) ? $aStart : $bStart;
        $end = $aEnd->lessThan($bEnd) ? $aEnd : $bEnd;

        if ($end->lte($start)) {
            return 0;
        }

        return (int) $start->diffInMinutes($end);
    }

    protected function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
