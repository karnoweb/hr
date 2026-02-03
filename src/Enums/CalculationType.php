<?php

namespace Karnoweb\Hr\Enums;

enum CalculationType: string
{
    case Fixed = 'fixed';
    case Formula = 'formula';
    case Percentage = 'percentage';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('hr::enums.calculation_type.fixed'),
            self::Formula => __('hr::enums.calculation_type.formula'),
            self::Percentage => __('hr::enums.calculation_type.percentage'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
