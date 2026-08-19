<?php

namespace Karnoweb\Hr\Console\Commands;

use Illuminate\Console\Command;
use Karnoweb\Hr\Services\LeaveBalanceService;

/**
 * Carry remaining leave balances into a new calendar year (HR-053).
 */
class CarryOverLeaveBalancesCommand extends Command
{
    protected $signature = 'hr:carry-over-leave-balances {fromYear} {toYear}';

    protected $description = 'Carry over eligible leave balances from one year to the next';

    public function handle(LeaveBalanceService $balances): int
    {
        $fromYear = (int) $this->argument('fromYear');
        $toYear = (int) $this->argument('toYear');

        if ($toYear <= $fromYear) {
            $this->error('toYear must be greater than fromYear.');

            return self::FAILURE;
        }

        $count = $balances->carryOverYear($fromYear, $toYear);

        $this->info("Processed carry-over for {$count} leave balance row(s) from {$fromYear} to {$toYear}.");

        return self::SUCCESS;
    }
}
