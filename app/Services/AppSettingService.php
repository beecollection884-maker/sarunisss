<?php

namespace App\Services;

use App\Models\AppSetting;

class AppSettingService
{
    /**
     * @var array<int, array{key:string,label:string,value:string,type:string,description:string}>
     */
    public const DEFAULTS = [
        ['key' => 'school_name', 'label' => 'Nama Sekolah', 'value' => 'SMP IP YAKIN', 'type' => 'text', 'description' => 'Nama sekolah yang tampil di portal.'],
        ['key' => 'academic_year', 'label' => 'Tahun Ajaran Aktif', 'value' => '2025/2026', 'type' => 'text', 'description' => 'Tahun ajaran default untuk data akademik.'],
        ['key' => 'active_semester', 'label' => 'Semester Aktif', 'value' => 'ganjil', 'type' => 'text', 'description' => 'Semester aktif untuk kalender akademik dan agenda dashboard.'],
        ['key' => 'school_start_date', 'label' => 'Tanggal Awal Masuk Sekolah', 'value' => '2025-07-14', 'type' => 'date', 'description' => 'Tanggal pertama awal masuk sekolah / mulai KBM semester aktif (format: YYYY-MM-DD).'],
        ['key' => 'school_end_date', 'label' => 'Tanggal Akhir Masuk Sekolah', 'value' => '2026-06-30', 'type' => 'date', 'description' => 'Tanggal akhir masuk sekolah / akhir KBM semester aktif (format: YYYY-MM-DD).'],
        ['key' => 'school_saturday_enabled', 'label' => 'Sabtu Masuk', 'value' => '1', 'type' => 'boolean', 'description' => 'Tentukan apakah hari Sabtu dihitung sebagai pertemuan KBM.'],
        ['key' => 'attendance_test_mode', 'label' => 'Mode Test Absensi', 'value' => '0', 'type' => 'boolean', 'description' => 'Jika aktif, guru dapat mengisi absensi tanpa mengikuti hari atau jam jadwal.'],
        ['key' => 'contact_phone', 'label' => 'Kontak Sekolah', 'value' => '', 'type' => 'text', 'description' => 'Nomor kontak sekolah.'],
    ];

    public function ensureDefaults(): void
    {
        foreach (self::DEFAULTS as $setting) {
            AppSetting::query()->firstOrCreate(['key' => $setting['key']], $setting);
        }
    }

    public function value(string $key, ?string $default = null): ?string
    {
        $value = AppSetting::query()
            ->where('key', $key)
            ->value('value');

        if ($value !== null) {
            return $value;
        }

        foreach (self::DEFAULTS as $setting) {
            if ($setting['key'] === $key) {
                return $setting['value'];
            }
        }

        return $default;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $fallback = $default ? '1' : '0';

        return filter_var($this->value($key, $fallback), FILTER_VALIDATE_BOOLEAN);
    }

    public function clearCache(): void
    {
    }
}

