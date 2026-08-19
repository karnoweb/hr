<?php

namespace Karnoweb\Hr\Enums;

enum LoanPaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('hr::enums.loan_payment_status.pending'),
            self::Paid => __('hr::enums.loan_payment_status.paid'),
            self::Cancelled => __('hr::enums.loan_payment_status.cancelled'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
