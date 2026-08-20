<?php

namespace Karnoweb\Hr\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Karnoweb\Hr\Models\PayrollPeriod;
use Karnoweb\Hr\Support\AccountingEventPayload;

/**
 * Fired after a payroll period is approved and loan deductions are committed (HR-139).
 *
 * Accounting consumers listen to this event to post salary expense, employer insurance
 * liabilities, employee deductions, and loan receivable reductions — never the other way around.
 */
class PayrollPeriodApproved
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<array<string, mixed>>  $employees
     * @param  array<string, float>  $periodTotals
     */
    public function __construct(
        public PayrollPeriod $period,
        public array $employees,
        public array $periodTotals,
        public ?int $approvedBy = null,
    ) {}

    public static function fromPeriod(PayrollPeriod $period, ?int $approvedBy = null): self
    {
        $payload = AccountingEventPayload::fromPayrollPeriod($period);

        return new self(
            $period,
            $payload['employees'],
            $payload['period_totals'],
            $approvedBy,
        );
    }
}
