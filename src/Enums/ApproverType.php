<?php

namespace Karnoweb\Hr\Enums;

enum ApproverType: string
{
    case User = 'user';
    case Position = 'position';
    case DepartmentHead = 'department_head';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::User => __('hr::enums.approver_type.user'),
            self::Position => __('hr::enums.approver_type.position'),
            self::DepartmentHead => __('hr::enums.approver_type.department_head'),
            self::Custom => __('hr::enums.approver_type.custom'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
