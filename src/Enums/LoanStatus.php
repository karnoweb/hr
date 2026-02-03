<?php

namespace Karnoweb\Hr\Enums;

enum LoanStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('hr::enums.loan_status.pending'),
            self::Active => __('hr::enums.loan_status.active'),
            self::Completed => __('hr::enums.loan_status.completed'),
            self::Cancelled => __('hr::enums.loan_status.cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'info',
            self::Completed => 'success',
            self::Cancelled => 'secondary',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
