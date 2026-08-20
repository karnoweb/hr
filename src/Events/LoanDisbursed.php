<?php

namespace Karnoweb\Hr\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Karnoweb\Hr\Models\Loan;

/**
 * Fired when a loan is approved and disbursed (HR-140).
 */
class LoanDisbursed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Loan $loan,
        public int $employeeId,
        public float $amount,
        public string $disbursedAt,
    ) {}

    public static function fromLoan(Loan $loan): self
    {
        return new self(
            $loan,
            (int) $loan->employee_id,
            round((float) $loan->amount, 2),
            $loan->start_date->toDateString(),
        );
    }
}
