<?php

namespace Karnoweb\Hr\Enums;

enum OvertimeStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('hr::enums.overtime_status.pending'),
            self::Approved => __('hr::enums.overtime_status.approved'),
            self::Rejected => __('hr::enums.overtime_status.rejected'),
            self::Cancelled => __('hr::enums.overtime_status.cancelled'),
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
