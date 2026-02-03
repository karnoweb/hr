<?php

namespace Karnoweb\Hr\Enums;

enum PayrollPeriodStatus: string
{
    case Draft = 'draft';
    case Calculating = 'calculating';
    case Calculated = 'calculated';
    case Approved = 'approved';
    case Paid = 'paid';
    case Locked = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('hr::enums.payroll_period_status.draft'),
            self::Calculating => __('hr::enums.payroll_period_status.calculating'),
            self::Calculated => __('hr::enums.payroll_period_status.calculated'),
            self::Approved => __('hr::enums.payroll_period_status.approved'),
            self::Paid => __('hr::enums.payroll_period_status.paid'),
            self::Locked => __('hr::enums.payroll_period_status.locked'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Calculating => 'info',
            self::Calculated => 'primary',
            self::Approved => 'success',
            self::Paid => 'success',
            self::Locked => 'dark',
        };
    }

    public function canCalculate(): bool
    {
        return in_array($this, [self::Draft, self::Calculated], true);
    }

    public function canApprove(): bool
    {
        return $this === self::Calculated;
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::Draft, self::Calculated], true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
