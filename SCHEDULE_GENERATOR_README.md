# Fitur Otomatis Plotting Jadwal Mapel dan Jadwal Mengajar Guru

## Gambaran Umum

Sistem ini menyediakan fitur lengkap untuk membuat dan mengelola jadwal pelajaran secara otomatis. Fitur ini mencakup:

1. **Automatic Schedule Generation** - Membuat jadwal secara otomatis
2. **Schedule Optimization** - Analisis dan optimisasi jadwal
3. **Conflict Detection** - Deteksi konflik jadwal guru dan ruangan
4. **Workload Analysis** - Analisis beban kerja guru
5. **Schedule Display** - Menampilkan jadwal dalam berbagai format

## Komponen Utama

### 1. ScheduleGeneratorService

Layanan untuk membuat jadwal otomatis

**File:** `app/Services/ScheduleGeneratorService.php`

**Fitur:**

- Membuat jadwal otomatis berdasarkan data guru dan mapel
- Deteksi konflik waktu dan ruangan
- Alokasi ruangan otomatis
- Validasi data sebelum generation

### 2. ScheduleOptimizerService

Layanan untuk analisis dan optimisasi jadwal

**File:** `app/Services/ScheduleOptimizerService.php`

**Fitur:**

- Deteksi konflik jadwal guru
- Deteksi konflik penggunaan ruangan
- Analisis beban kerja guru
- Analisis distribusi jadwal harian
- Memberikan rekomendasi perbaikan

### 3. ScheduleDisplayService

Layanan untuk menampilkan jadwal dalam berbagai format

**File:** `app/Services/ScheduleDisplayService.php`

**Fitur:**

- Tampilan jadwal per kelas
- Tampilan jadwal per guru
- Export ke format HTML, CSV, ICS

### 4. Artisan Commands

#### a. Command: `schedule:generate`

Membuat jadwal otomatis

```bash
# Generate jadwal untuk semua kelas
php artisan schedule:generate 2025-2026

# Generate untuk kelas tertentu
php artisan schedule:generate 2025-2026 --class=1

# Mode force (hapus jadwal lama terlebih dahulu)
php artisan schedule:generate 2025-2026 --force

# Validasi saja tanpa generate
php artisan schedule:generate 2025-2026 --validate-only
```

#### b. Command: `schedule:analyze`

Analisis jadwal untuk deteksi konflik

```bash
# Analisis lengkap
php artisan schedule:analyze 2025-2026

# Tampilkan hanya konflik
php artisan schedule:analyze 2025-2026 --conflicts-only

# Generate laporan
php artisan schedule:analyze 2025-2026 --report
```

### 5. Model: ScheduleGeneration

Melacak history pembuatan jadwal

**File:** `app/Models/ScheduleGeneration.php`

**Field:**

- `academic_year` - Tahun akademik
- `generated_by_user_id` - User yang membuat jadwal
- `total_classes` - Total kelas
- `total_assignments` - Total assignment
- `successful_slots` - Slot yang berhasil
- `failed_slots` - Slot yang gagal
- `conflicts_detected` - Konflik terdeteksi
- `result_data` - Detail hasil (JSON)

## Cara Kerja Schedule Generation

### Algoritma

1. **Inisialisasi Time Slots**
    - Membuat slot waktu kosong untuk setiap hari
    - Waktu operasional: 07:00 - 15:00
    - Durasi pelajaran: 45 menit
    - Break: 30 menit antar pelajaran

2. **Ambil Data**
    - Ambil semua kelas untuk tahun akademik
    - Ambil semua mapel yang harus diajarkan
    - Ambil guru yang qualified

3. **Proses Penempatan Jadwal**
    - Untuk setiap kelas:
        - Untuk setiap mapel di kelas:
            - Cari guru qualified
            - Cari slot waktu kosong
            - Cek apakah guru tidak ada konflik
            - Buat teaching assignment

4. **Output**
    - Statistik jumlah jadwal yang berhasil dibuat
    - Detail konflik jika ada
    - Jadwal gagal (jika guru tidak tersedia, dll)

### Konfigurasi

Semua konfigurasi dapat diubah via file `.env` tanpa edit kode:

```env
# Jam operasional sekolah (24-hour format)
SCHEDULE_SCHOOL_START_HOUR=7        # 07:00
SCHEDULE_SCHOOL_END_HOUR=15         # 15:00

# Durasi pelajaran & break (dalam menit)
SCHEDULE_LESSON_DURATION=45         # 45 menit per pelajaran
SCHEDULE_BREAK_DURATION=30          # 30 menit break

# Hari operasional (0=Senin, 1=Selasa, 2=Rabu, 3=Kamis, 4=Jumat, 5=Sabtu, 6=Minggu)
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4 # Senin-Jumat

# Maksimal jam mengajar guru per minggu
SCHEDULE_MAX_TEACHER_HOURS=25

# Format nama ruangan (gunakan {class} untuk placeholder)
SCHEDULE_ROOM_NAME_FORMAT=Ruang {class}

# Mode simulasi (test tanpa generate data sebenarnya)
SCHEDULE_SIMULATION_MODE=false

# Opsi validasi
SCHEDULE_ALLOW_SUBJECTS_WITHOUT_TEACHER=false
SCHEDULE_ALLOW_CLASSES_WITHOUT_SUBJECTS=false
SCHEDULE_ALLOW_TEACHERS_WITHOUT_SCHEDULE=false
```

**Lihat [CONFIG_GUIDE.md](CONFIG_GUIDE.md) untuk dokumentasi konfigurasi lengkap.**

## Fitur Analisis dan Optimisasi

### Deteksi Konflik

```php
// Dari service
$conflicts = $optimizer->detectTeacherConflicts($academicYear);
$roomConflicts = $optimizer->detectRoomConflicts($academicYear);
```

**Output Konflik Guru:**

```
[
  [
    'teacher_id' => 1,
    'teacher_name' => 'Budi',
    'assignment1' => [...],
    'assignment2' => [...],
    'type' => 'time_overlap'
  ]
]
```

### Analisis Beban Kerja

```php
$workloads = $optimizer->analyzeTeacherWorkload($academicYear);

// Output per guru
[
  [
    'teacher_id' => 1,
    'teacher_name' => 'Budi',
    'total_hours_per_week' => 24.5,
    'sessions_per_week' => 5,
    'unique_subjects' => 2,
    'unique_classes' => 3,
    'workload_status' => 'Tinggi',
    'is_overloaded' => false
  ]
]
```

### Rekomendasi Perbaikan

```php
$recommendations = $optimizer->getRecommendations($academicYear);

// Tipe rekomendasi:
// - overloaded_teachers
// - scheduling_conflicts
// - imbalanced_schedule
// - unscheduled_teachers
```

## Format Tampilan Jadwal

### Format Tabel Per Kelas

```php
$service = new ScheduleDisplayService();
$schedule = $service->getClassScheduleTable($classId, $academicYear);

// Output:
[
  'class_name' => '10A',
  'class_level' => 'X',
  'academic_year' => '2025-2026',
  'schedule' => [
    'Senin' => [
      [
        'subject' => 'Matematika',
        'teacher' => 'Budi',
        'time' => '07:00 - 07:45',
        'room' => 'Ruang 10A'
      ],
      ...
    ],
    ...
  ]
]
```

### Export ke Berbagai Format

```php
// HTML
$html = $service->exportToHTML($classId, $academicYear);

// CSV
$csv = $service->exportToCSV($classId, $academicYear);

// ICS (untuk kalender)
$ics = $service->generateICSCalendar($classId, $academicYear);

// JSON (untuk API)
$json = $service->getScheduleJSON($classId, $academicYear);
```

## Validasi Sebelum Generation

Sistem akan mengecek:

1. **Apakah ada mapel tanpa guru?**
    - Jika ya, mapel tersebut tidak bisa dijadwalkan

2. **Apakah ada kelas tanpa mapel?**
    - Kelas tanpa mapel akan dilewat

3. **Apakah ada guru tanpa jadwal?**
    - Guru yang tidak terpilih akan ditampilkan sebagai warning

## Database Migration

Jalankan migration untuk membuat tabel schedule_generations:

```bash
php artisan migrate
```

Tabel ini akan mencatat:

- Kapan jadwal dibuat
- Siapa yang membuat
- Statistik hasil generation
- Detail hasil dalam format JSON

## Integrasi dengan Aplikasi

### Di Controller

```php
use App\Services\ScheduleGeneratorService;
use App\Services\ScheduleOptimizerService;
use App\Services\ScheduleDisplayService;

class ScheduleController extends Controller
{
    public function __construct(
        private ScheduleGeneratorService $generator,
        private ScheduleOptimizerService $optimizer,
        private ScheduleDisplayService $display
    ) {}

    public function generateSchedule(Request $request)
    {
        $academicYear = $request->input('academic_year');
        $result = $this->generator->generateSchedule($academicYear);

        return response()->json($result);
    }

    public function showSchedule($classId, $academicYear)
    {
        $schedule = $this->display->getClassScheduleTable($classId, $academicYear);
        return view('schedule.show', compact('schedule'));
    }

    public function analyzeSchedule($academicYear)
    {
        $analysis = $this->optimizer->generateScheduleReport($academicYear);
        return response()->json($analysis);
    }
}
```

## Best Practices

1. **Validasi Data Terlebih Dahulu**

    ```bash
    php artisan schedule:generate 2025-2026 --validate-only
    ```

2. **Backup Jadwal Lama**
    - Jika ingin regenerate, backup terlebih dahulu

3. **Analisis Setelah Generation**

    ```bash
    php artisan schedule:analyze 2025-2026 --report
    ```

4. **Perbaiki Konflik**
    - Berdasarkan rekomendasi dari command

5. **Export untuk Distribusi**
    - Gunakan format HTML atau PDF untuk cetak
    - Gunakan ICS untuk integrasi kalender

## Troubleshooting

### Jadwal Gagal Dibuat

**Penyebab:**

- Guru tidak tersedia di slot apapun
- Mapel tidak memiliki guru
- Kelas tidak memiliki mapel

**Solusi:**

- Cek dengan `--validate-only`
- Tambah guru atau slot waktu
- Hubungkan guru dengan mapel

### Konflik Jadwal Terdeteksi

**Penyebab:**

- Guru punya jadwal bertabrakan
- Ruangan dipakai bersamaan

**Solusi:**

- Jalankan `schedule:analyze` untuk detail
- Manual adjustment jadwal
- Regenerate dengan perubahan parameter

### Performa Lambat

**Penyebab:**

- Banyak data guru/mapel/kelas
- Database query yang heavy

**Solusi:**

- Optimize database indexes
- Batasi tahun akademik tertentu

## File yang Dibuat

1. `app/Services/ScheduleGeneratorService.php` - Service utama
2. `app/Services/ScheduleOptimizerService.php` - Optimizer
3. `app/Services/ScheduleDisplayService.php` - Display formatter
4. `app/Models/ScheduleGeneration.php` - Model tracking
5. `app/Console/Commands/GenerateScheduleCommand.php` - Artisan command
6. `app/Console/Commands/AnalyzeScheduleCommand.php` - Analisis command
7. `database/migrations/2026_05_25_000001_create_schedule_generations_table.php` - Migration

## Contoh Penggunaan Lengkap

```bash
# 1. Validasi data terlebih dahulu
php artisan schedule:generate 2025-2026 --validate-only

# 2. Jika ada warning, perbaiki data (tambah guru, dll)

# 3. Generate jadwal
php artisan schedule:generate 2025-2026 --force

# 4. Analisis hasil
php artisan schedule:analyze 2025-2026

# 5. Jika ada konflik, lakukan adjustment manual

# 6. Generate laporan lengkap
php artisan schedule:analyze 2025-2026 --report

# 7. Export jadwal untuk distribusi
# (melalui controller/UI)
```

---

**Created:** May 25, 2026  
**Version:** 1.0  
**Compatibility:** Laravel 11+
