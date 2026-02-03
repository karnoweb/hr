<?php

namespace Karnoweb\Hr\Enums;

enum EmployeeStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('hr::enums.employee_status.active'),
            self::Suspended => __('hr::enums.employee_status.suspended'),
            self::Terminated => __('hr::enums.employee_status.terminated'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'warning',
            self::Terminated => 'danger',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
