<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Cache;

trait ClearsDashboardCaches
{
    protected static function bootClearsDashboardCaches(): void
    {
        static::saved(function (): void {
            static::clearDashboardCaches();
        });

        static::deleted(function (): void {
            static::clearDashboardCaches();
        });
    }

    public static function clearDashboardCaches(): void
    {
        // Bersihkan cache payload direktori admin
        Cache::forget('admin_students_payload');
        Cache::forget('admin_teachers_payload');
        Cache::forget('admin_classes_payload');
        Cache::forget('admin_subjects_payload');

        // Bersihkan cache dashboard utama
        Cache::forget('admin_dashboard_heavy_data');
        Cache::forget('wakasek_dashboard_data');
        Cache::forget('guru_piket_dashboard_data');
    }
}
