<?php

namespace Karnoweb\Hr\Enums;

enum PayrollRecordStatus: string
{
    case Draft = 'draft';
    case Calculated = 'calculated';
    case Approved = 'approved';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('hr::enums.payroll_record_status.draft'),
            self::Calculated => __('hr::enums.payroll_record_status.calculated'),
            self::Approved => __('hr::enums.payroll_record_status.approved'),
            self::Paid => __('hr::enums.payroll_record_status.paid'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
