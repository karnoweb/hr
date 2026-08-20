<?php

namespace Karnoweb\Hr\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Locked = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('hr::enums.document_status.draft'),
            self::Pending => __('hr::enums.document_status.pending'),
            self::Approved => __('hr::enums.document_status.approved'),
            self::Rejected => __('hr::enums.document_status.rejected'),
            self::Cancelled => __('hr::enums.document_status.cancelled'),
            self::Locked => __('hr::enums.document_status.locked'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'secondary',
            self::Locked => 'info',
        };
    }

    public function canEdit(): bool
    {
        return $this === self::Draft;
    }

    public function canDelete(): bool
    {
        return $this === self::Draft;
    }

    public function canSubmit(): bool
    {
        return $this === self::Draft;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
