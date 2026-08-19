<?php

namespace Karnoweb\Hr\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Karnoweb\Hr\Models\AttendanceRecord;
use Karnoweb\Hr\Services\AttendanceService;

/**
 * Auto clock-out open attendance rows past the configured threshold (HR-038).
 */
class AutoClockOutCommand extends Command
{
    protected $signature = 'hr:auto-clock-out';

    protected $description = 'Automatically clock out employees who forgot to clock out';

    public function handle(AttendanceService $attendance): int
    {
        if (! config('hr.attendance.auto_clock_out', false)) {
            $this->info('Auto clock-out is disabled in config.');

            return self::SUCCESS;
        }

        $afterHours = (int) config('hr.attendance.auto_clock_out_after_hours', 12);
        $cutoff = Carbon::now()->subHours($afterHours);

        $records = AttendanceRecord::query()
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->where('clock_in', '<=', $cutoff)
            ->with('employee')
            ->get();

        $count = 0;

        foreach ($records as $record) {
            if ($record->employee === null) {
                continue;
            }

            $autoOutAt = Carbon::parse($record->clock_in)->addHours($afterHours);

            $attendance->clockOutRecord($record, $autoOutAt, [
                'source' => 'auto',
                'notes' => trim(($record->notes ?? '').' [auto clock-out]'),
            ]);

            $count++;
        }

        $this->info("Auto clocked out {$count} attendance record(s).");

        return self::SUCCESS;
    }
}
