<?php

namespace Karnoweb\Hr\Enums;

enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('hr::enums.approval_status.pending'),
            self::Approved => __('hr::enums.approval_status.approved'),
            self::Rejected => __('hr::enums.approval_status.rejected'),
            self::Skipped => __('hr::enums.approval_status.skipped'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Skipped => 'secondary',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
