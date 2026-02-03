<?php

namespace Karnoweb\Hr\Enums;

enum OvertimeType: string
{
    case Regular = 'regular';
    case Holiday = 'holiday';
    case Night = 'night';

    public function label(): string
    {
        return match ($this) {
            self::Regular => __('hr::enums.overtime_type.regular'),
            self::Holiday => __('hr::enums.overtime_type.holiday'),
            self::Night => __('hr::enums.overtime_type.night'),
        };
    }

    public function rate(): float
    {
        return (float) match ($this) {
            self::Regular => config('hr.overtime.rates.regular', 1.4),
            self::Holiday => config('hr.overtime.rates.holiday', 1.7),
            self::Night => config('hr.overtime.rates.night', 1.35),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
