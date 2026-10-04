<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case HADIR = 'hadir';
    case IZIN = 'izin';
    case SAKIT = 'sakit';
    case ALPHA = 'alpha';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
