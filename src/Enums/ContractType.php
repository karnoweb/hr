<?php

namespace Karnoweb\Hr\Enums;

enum ContractType: string
{
    case Permanent = 'permanent';
    case Temporary = 'temporary';
    case Project = 'project';
    case PartTime = 'part_time';
    case Internship = 'internship';

    public function label(): string
    {
        return match ($this) {
            self::Permanent => __('hr::enums.contract_type.permanent'),
            self::Temporary => __('hr::enums.contract_type.temporary'),
            self::Project => __('hr::enums.contract_type.project'),
            self::PartTime => __('hr::enums.contract_type.part_time'),
            self::Internship => __('hr::enums.contract_type.internship'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
