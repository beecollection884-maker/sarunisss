# Quick Start - Fitur Otomatis Plotting Jadwal

## 🚀 Mulai Cepat

### Step 1: Setup Database

```bash
# Run migration untuk membuat tabel schedule_generations
php artisan migrate
```

### Step 2: Generate Jadwal Pertama Kali

```bash
# Validasi data terlebih dahulu
php artisan schedule:generate 2025-2026 --validate-only

# Jika ada warning, perbaiki data di aplikasi (tambah guru, hubungkan mapel, dll)

# Generate jadwal untuk semua kelas
php artisan schedule:generate 2025-2026

# Atau untuk kelas spesifik
php artisan schedule:generate 2025-2026 --class=1
```

### Step 3: Analisis Jadwal

```bash
# Cek apakah ada konflik
php artisan schedule:analyze 2025-2026 --conflicts-only

# Analisis lengkap dengan rekomendasi
php artisan schedule:analyze 2025-2026

# Generate laporan detail
php artisan schedule:analyze 2025-2026 --report
```

### Step 4: Tambah Routes (Opsional)

Jika ingin mengakses via web interface, tambahkan routes dari `SCHEDULE_ROUTES.php` ke `routes/web.php`:

```php
// Di routes/web.php, tambahkan:
include 'schedule-routes.php'; // atau copy paste isi dari SCHEDULE_ROUTES.php
```

---

## 📊 Struktur File yang Dibuat

```
app/
├── Services/
│   ├── ScheduleGeneratorService.php      ← Generate jadwal otomatis
│   ├── ScheduleOptimizerService.php      ← Analisis & optimisasi
│   └── ScheduleDisplayService.php        ← Format tampilan jadwal
├── Models/
│   └── ScheduleGeneration.php            ← Model tracking
├── Console/Commands/
│   ├── GenerateScheduleCommand.php       ← Command: schedule:generate
│   └── AnalyzeScheduleCommand.php        ← Command: schedule:analyze
└── Http/Controllers/
    └── ScheduleController.php            ← Web controller (opsional)

database/migrations/
└── 2026_05_25_000001_create_schedule_generations_table.php

Documentation:
├── SCHEDULE_GENERATOR_README.md          ← Dokumentasi lengkap
└── SCHEDULE_ROUTES.php                   ← Route examples
```

---

## 🎯 Cara Kerja Sistem

### Automatic Schedule Generation

```
Input Data (Guru, Mapel, Kelas)
         ↓
   Validasi Data
         ↓
   Ambil Jadwal Existing (jika ada)
         ↓
   Inisialisasi Time Slots
   - Senin-Jumat: 07:00-15:00
   - Durasi pelajaran: 45 menit
   - Break: 30 menit
         ↓
   Untuk Setiap Kelas:
   - Ambil mapel yang harus diajarkan
   - Cari guru qualified
   - Cek slot waktu kosong
   - Cek guru tidak ada konflik
   - Buat TeachingAssignment
         ↓
   Output Statistik & Report
```

---

## 💻 Command Cheatsheet

### Generate Jadwal

```bash
# Basic
php artisan schedule:generate 2025-2026

# Dengan options
php artisan schedule:generate 2025-2026 --class=1           # Kelas tertentu
php artisan schedule:generate 2025-2026 --force             # Hapus lama & buat baru
php artisan schedule:generate 2025-2026 --validate-only     # Hanya validasi

# Kombinasi
php artisan schedule:generate 2025-2026 --class=1 --force
```

### Analisis Jadwal

```bash
# Basic
php artisan schedule:analyze 2025-2026

# Dengan options
php artisan schedule:analyze 2025-2026 --conflicts-only     # Hanya konflik
php artisan schedule:analyze 2025-2026 --report             # Simpan laporan

# Kombinasi
php artisan schedule:analyze 2025-2026 --conflicts-only --report
```

---

## 🌐 Web Interface (via Routes)

Jika sudah menambah routes, akses via browser:

### Generate Jadwal

- **GET** `/schedule/generate` - Form generate jadwal
- **POST** `/schedule/generate` - Submit generate

### Lihat Jadwal

- **GET** `/schedule/class/1/2025-2026` - Jadwal kelas
- **GET** `/schedule/teacher/1/2025-2026` - Jadwal guru

### Analisis

- **GET** `/schedule/analyze/2025-2026` - Laporan analisis

### Export

- **GET** `/schedule/export/1/2025-2026/html` - Export HTML
- **GET** `/schedule/export/1/2025-2026/csv` - Export CSV
- **GET** `/schedule/export/1/2025-2026/ics` - Export Kalender

---

## 📝 Contoh Penggunaan dalam Controller

```php
<?php
namespace App\Http\Controllers;

use App\Services\ScheduleGeneratorService;
use App\Services\ScheduleOptimizerService;

class MyController extends Controller
{
    public function generateSchedule(
        ScheduleGeneratorService $generator,
        ScheduleOptimizerService $optimizer
    ) {
        // 1. Validasi
        $validation = $generator->validateBeforeGeneration('2025-2026');

        // 2. Generate
        $result = $generator->generateSchedule('2025-2026');

        // 3. Analisis
        $analysis = $optimizer->generateScheduleReport('2025-2026');
        $conflicts = $optimizer->detectTeacherConflicts('2025-2026');

        return response()->json([
            'result' => $result,
            'analysis' => $analysis,
            'conflicts' => $conflicts,
        ]);
    }
}
```

---

## ⚙️ Konfigurasi

Semua konfigurasi jadwal dapat diubah melalui file `.env` tanpa edit kode:

```env
# Jam operasional sekolah
SCHEDULE_SCHOOL_START_HOUR=7        # 07:00
SCHEDULE_SCHOOL_END_HOUR=15         # 15:00

# Durasi pelajaran & break
SCHEDULE_LESSON_DURATION=45         # 45 menit per pelajaran
SCHEDULE_BREAK_DURATION=30          # 30 menit break

# Hari operasional (0=Senin, 1=Selasa, dst)
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4 # Senin-Jumat

# Maksimal jam mengajar guru per minggu
SCHEDULE_MAX_TEACHER_HOURS=25

# Format nama ruangan
SCHEDULE_ROOM_NAME_FORMAT=Ruang {class}
```

📖 **Dokumentasi lengkap:** Lihat [CONFIG_GUIDE.md](CONFIG_GUIDE.md)

**Contoh mengubah konfigurasi:**

```env
# Sekolah mulai pukul 06:00 dan berakhir 16:00
SCHEDULE_SCHOOL_START_HOUR=6
SCHEDULE_SCHOOL_END_HOUR=16

# Pelajaran 50 menit
SCHEDULE_LESSON_DURATION=50

# Termasuk Sabtu
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4,5
```

---

## 🔍 Deteksi Konflik

Sistem secara otomatis mendeteksi:

1. **Guru Bentrok** - Guru punya jadwal tumpang tindih
2. **Ruangan Bentrok** - Ruangan dipakai bersamaan
3. **Jadwal Tidak Seimbang** - Beban jam per hari tidak merata
4. **Beban Kerja Tinggi** - Guru > nilai `SCHEDULE_MAX_TEACHER_HOURS` jam/minggu

---

## 📤 Export Format

### HTML

- Cetak-friendly layout
- Bisa langsung print ke PDF

### CSV

- Bisa dibuka di Excel
- Mudah di-share

### ICS

- Format kalender standar
- Bisa import ke Google Calendar, Outlook, dll

---

## ⚠️ Troubleshooting

### Jadwal gagal dibuat

**Penyebab:** Guru tidak tersedia atau mapel tidak ada guru

**Solusi:**

```bash
# Cek dengan validasi
php artisan schedule:generate 2025-2026 --validate-only

# Perbaiki data, lalu try again
php artisan schedule:generate 2025-2026 --force
```

### Terlalu banyak konflik

**Penyebab:** Data guru/mapel/kelas tidak sesuai

**Solusi:**

```bash
# Lihat detail rekomendasi
php artisan schedule:analyze 2025-2026

# Manual adjustment jadwal via aplikasi
# Lalu lakukan analisis ulang
```

### Performa lambat

**Penyebab:** Database besar

**Solusi:**

- Jalankan di off-peak hours
- Optimize database indexes
- Generate per kelas, bukan semua sekaligus

---

## 📚 Dokumentasi Lengkap

Lihat `SCHEDULE_GENERATOR_README.md` untuk dokumentasi lengkap.

---

## 🎓 Workflow Rekomendasi

```
1. Setup Database
   ↓
2. Validasi Data (--validate-only)
   ↓
3. Perbaiki Data (jika ada error/warning)
   ↓
4. Generate Jadwal (--force)
   ↓
5. Analisis Hasil (--conflicts-only)
   ↓
6. Perbaiki Konflik (manual adjustment)
   ↓
7. Final Analysis (--report)
   ↓
8. Export untuk Distribusi
```

---

## 🚨 Penting!

- **Validasi dahulu** sebelum generate
- **Backup data** sebelum regenerate dengan --force
- **Analisis setelah generate** untuk memastikan jadwal optimal
- **Hubungi yang terkait** (guru, wali kelas) sebelum finalisasi jadwal

---

**Created:** May 25, 2026  
**Version:** 1.0
