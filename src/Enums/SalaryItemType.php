<?php

namespace Karnoweb\Hr\Enums;

enum SalaryItemType: string
{
    case Earning = 'earning';
    case Deduction = 'deduction';

    public function label(): string
    {
        return match ($this) {
            self::Earning => __('hr::enums.salary_item_type.earning'),
            self::Deduction => __('hr::enums.salary_item_type.deduction'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
