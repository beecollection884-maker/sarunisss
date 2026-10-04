<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case GURU_MAPEL = 'guru_mapel';
    case SISWA = 'siswa';
    case WAKASEK_KESISWAAN = 'wakasek_kesiswaan';
    case GURU_PIKET = 'guru_piket';
    case ORANG_TUA = 'orang_tua';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn(self $role): string => $role->value,
            self::cases(),
        );
    }
}
