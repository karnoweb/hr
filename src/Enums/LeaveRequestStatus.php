<?php

namespace Karnoweb\Hr\Enums;

enum LeaveRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('hr::enums.leave_request_status.pending'),
            self::Approved => __('hr::enums.leave_request_status.approved'),
            self::Rejected => __('hr::enums.leave_request_status.rejected'),
            self::Cancelled => __('hr::enums.leave_request_status.cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'secondary',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
