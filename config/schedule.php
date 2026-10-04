<?php

/**
 * Schedule Configuration
 * 
 * Konfigurasi untuk fitur otomatis plotting jadwal.
 * Semua nilai dapat diubah melalui environment variables.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Jam Operasional Sekolah
    |--------------------------------------------------------------------------
    |
    | Jam mulai dan selesai operasional sekolah dalam format 24-hour (0-23)
    |
    | Default: 07:00 - 15:00
    */
    'school_start_hour' => (int) env('SCHEDULE_SCHOOL_START_HOUR', 7),
    'school_end_hour' => (int) env('SCHEDULE_SCHOOL_END_HOUR', 15),

    /*
    |--------------------------------------------------------------------------
    | Durasi Pelajaran
    |--------------------------------------------------------------------------
    |
    | Durasi satu sesi pelajaran dalam menit
    | Default: 45 menit
    */
    'lesson_duration' => (int) env('SCHEDULE_LESSON_DURATION', 45),

    /*
    |--------------------------------------------------------------------------
    | Durasi Break / Istirahat
    |--------------------------------------------------------------------------
    |
    | Durasi break antar pelajaran dalam menit
    | Default: 30 menit
    */
    'break_duration' => (int) env('SCHEDULE_BREAK_DURATION', 30),

    /*
    |--------------------------------------------------------------------------
    | Hari Operasional
    |--------------------------------------------------------------------------
    |
    | Hari-hari yang sekolah beroperasi dalam format numeric (0-6)
    | 0 = Senin, 1 = Selasa, 2 = Rabu, 3 = Kamis, 4 = Jumat, 5 = Sabtu, 6 = Minggu
    | 
    | Default: Senin-Jumat (0-4)
    | 
    | Contoh format .env:
    | SCHEDULE_OPERATIONAL_DAYS="0,1,2,3,4"    # Senin-Jumat
    | SCHEDULE_OPERATIONAL_DAYS="0,1,2,3,4,5"  # Senin-Sabtu
    */
    'operational_days' => array_map('intval', array_filter(explode(',', env('SCHEDULE_OPERATIONAL_DAYS', '0,1,2,3,4')), fn($val) => $val !== '')),

    /*
    |--------------------------------------------------------------------------
    | Maksimal Jam Mengajar Per Minggu
    |--------------------------------------------------------------------------
    |
    | Jam maksimal seorang guru mengajar per minggu
    | Jika melebihi, akan dianggap overloaded
    | Default: 25 jam
    */
    'max_teacher_hours_per_week' => (int) env('SCHEDULE_MAX_TEACHER_HOURS', 25),

    /*
    |--------------------------------------------------------------------------
    | Format Nama Ruangan
    |--------------------------------------------------------------------------
    |
    | Format default untuk alokasi ruangan jika tidak ditentukan
    | Gunakan {class} untuk nama kelas
    | Default: "Ruang {class}"
    */
    'room_name_format' => env('SCHEDULE_ROOM_NAME_FORMAT', 'Ruang {class}'),

    /*
    |--------------------------------------------------------------------------
    | Nama Hari (Localization)
    |--------------------------------------------------------------------------
    |
    | Nama-nama hari dalam bahasa lokal
    */
    'day_names' => [
        0 => 'Senin',
        1 => 'Selasa',
        2 => 'Rabu',
        3 => 'Kamis',
        4 => 'Jumat',
        5 => 'Sabtu',
        6 => 'Minggu',
    ],

    /*
    |--------------------------------------------------------------------------
    | Simulasi Penjadwalan
    |--------------------------------------------------------------------------
    |
    | Enable simulation mode untuk testing tanpa generate data sebenarnya
    | Default: false
    */
    'simulation_mode' => (bool) env('SCHEDULE_SIMULATION_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Validasi
    |--------------------------------------------------------------------------
    */
    'validation' => [
        // Izinkan mapel tanpa guru
        'allow_subjects_without_teacher' => (bool) env('SCHEDULE_ALLOW_SUBJECTS_WITHOUT_TEACHER', false),

        // Izinkan kelas tanpa mapel
        'allow_classes_without_subjects' => (bool) env('SCHEDULE_ALLOW_CLASSES_WITHOUT_SUBJECTS', false),

        // Izinkan guru tanpa jadwal (tidak dianggap error)
        'allow_teachers_without_schedule' => (bool) env('SCHEDULE_ALLOW_TEACHERS_WITHOUT_SCHEDULE', false),
    ],
];
