<?php

namespace Karnoweb\Hr\Enums;

enum TimeoutAction: string
{
    case AutoApprove = 'auto_approve';
    case AutoReject = 'auto_reject';
    case Escalate = 'escalate';
    case Skip = 'skip';

    public function label(): string
    {
        return match ($this) {
            self::AutoApprove => __('hr::enums.timeout_action.auto_approve'),
            self::AutoReject => __('hr::enums.timeout_action.auto_reject'),
            self::Escalate => __('hr::enums.timeout_action.escalate'),
            self::Skip => __('hr::enums.timeout_action.skip'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
