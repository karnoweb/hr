<?php

namespace Karnoweb\Hr\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Karnoweb\Hr\Models\PayrollPeriod;
use Karnoweb\Hr\Support\AccountingEventPayload;

/**
 * Fired after a payroll period is marked paid (HR-141).
 */
class PayrollPeriodPaid
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
    ) {}

    public static function fromPeriod(PayrollPeriod $period): self
    {
        $payload = AccountingEventPayload::fromPayrollPeriod($period);

        return new self(
            $period,
            $payload['employees'],
            $payload['period_totals'],
        );
    }
}
