<?php

namespace Karnoweb\Hr\Enums;

enum ContractStatus: string
{
    case Active = 'active';
    case Ended = 'ended';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('hr::enums.contract_status.active'),
            self::Ended => __('hr::enums.contract_status.ended'),
            self::Terminated => __('hr::enums.contract_status.terminated'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
