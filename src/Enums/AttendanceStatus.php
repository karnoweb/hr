<?php

namespace Karnoweb\Hr\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Leave = 'leave';
    case Mission = 'mission';
    case Holiday = 'holiday';
    case Weekend = 'weekend';

    public function label(): string
    {
        return match ($this) {
            self::Present => __('hr::enums.attendance_status.present'),
            self::Absent => __('hr::enums.attendance_status.absent'),
            self::Leave => __('hr::enums.attendance_status.leave'),
            self::Mission => __('hr::enums.attendance_status.mission'),
            self::Holiday => __('hr::enums.attendance_status.holiday'),
            self::Weekend => __('hr::enums.attendance_status.weekend'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Absent => 'danger',
            self::Leave => 'warning',
            self::Mission => 'info',
            self::Holiday => 'secondary',
            self::Weekend => 'secondary',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
