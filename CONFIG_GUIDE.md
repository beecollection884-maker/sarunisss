# Panduan Konfigurasi Jadwal

## Menggunakan Environment Variables

Semua pengaturan jadwal dapat diubah melalui file `.env` tanpa perlu edit kode. Berikut adalah konfigurasi yang tersedia:

---

## 📋 Daftar Environment Variables

### 1. Jam Operasional Sekolah

```env
SCHEDULE_SCHOOL_START_HOUR=7      # Jam mulai (07:00)
SCHEDULE_SCHOOL_END_HOUR=15       # Jam selesai (15:00)
```

**Contoh:**

```env
# Sekolah mulai jam 6 pagi
SCHEDULE_SCHOOL_START_HOUR=6
SCHEDULE_SCHOOL_END_HOUR=14
```

### 2. Durasi Pelajaran

```env
SCHEDULE_LESSON_DURATION=45       # Durasi satu pelajaran (menit)
SCHEDULE_BREAK_DURATION=30        # Durasi istirahat (menit)
```

**Contoh:**

```env
# Pelajaran 50 menit, istirahat 20 menit
SCHEDULE_LESSON_DURATION=50
SCHEDULE_BREAK_DURATION=20
```

### 3. Hari Operasional

```env
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4
```

**Format:** Angka dipisahkan koma (0-6)

- `0` = Senin
- `1` = Selasa
- `2` = Rabu
- `3` = Kamis
- `4` = Jumat
- `5` = Sabtu
- `6` = Minggu

**Contoh:**

```env
# Senin-Jumat saja
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4

# Senin-Sabtu
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4,5

# Hanya Senin-Rabu
SCHEDULE_OPERATIONAL_DAYS=0,1,2
```

### 4. Maksimal Jam Mengajar

```env
SCHEDULE_MAX_TEACHER_HOURS=25
```

Guru yang mengajar lebih dari jam ini akan dianggap "overloaded" dalam analisis.

**Contoh:**

```env
# Standar guru mengajar 24 jam/minggu
SCHEDULE_MAX_TEACHER_HOURS=24
```

### 5. Format Nama Ruangan

```env
SCHEDULE_ROOM_NAME_FORMAT=Ruang {class}
```

Gunakan `{class}` untuk placeholder nama kelas.

**Contoh:**

```env
# Format: "Ruang 10A"
SCHEDULE_ROOM_NAME_FORMAT=Ruang {class}

# Format: "Kelas 10A"
SCHEDULE_ROOM_NAME_FORMAT=Kelas {class}

# Format: "R-10A"
SCHEDULE_ROOM_NAME_FORMAT=R-{class}
```

### 6. Mode Simulasi

```env
SCHEDULE_SIMULATION_MODE=false
```

Set ke `true` untuk testing tanpa generate data sebenarnya (tidak merekam ke database).

### 7. Opsi Validasi

```env
SCHEDULE_ALLOW_SUBJECTS_WITHOUT_TEACHER=false
SCHEDULE_ALLOW_CLASSES_WITHOUT_SUBJECTS=false
SCHEDULE_ALLOW_TEACHERS_WITHOUT_SCHEDULE=false
```

- `ALLOW_SUBJECTS_WITHOUT_TEACHER`: Izinkan mapel tanpa guru
- `ALLOW_CLASSES_WITHOUT_SUBJECTS`: Izinkan kelas tanpa mapel
- `ALLOW_TEACHERS_WITHOUT_SCHEDULE`: Izinkan guru tanpa jadwal

---

## 🔧 Contoh Skenario Konfigurasi

### Skenario 1: Sekolah Full Day

```env
SCHEDULE_SCHOOL_START_HOUR=6
SCHEDULE_SCHOOL_END_HOUR=16
SCHEDULE_LESSON_DURATION=45
SCHEDULE_BREAK_DURATION=20
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4
SCHEDULE_MAX_TEACHER_HOURS=30
```

### Skenario 2: Sekolah Pagi Singkat

```env
SCHEDULE_SCHOOL_START_HOUR=7
SCHEDULE_SCHOOL_END_HOUR=12
SCHEDULE_LESSON_DURATION=40
SCHEDULE_BREAK_DURATION=15
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4,5
SCHEDULE_MAX_TEACHER_HOURS=20
```

### Skenario 3: Sekolah Internasional

```env
SCHEDULE_SCHOOL_START_HOUR=8
SCHEDULE_SCHOOL_END_HOUR=16
SCHEDULE_LESSON_DURATION=50
SCHEDULE_BREAK_DURATION=30
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4
SCHEDULE_MAX_TEACHER_HOURS=28
```

---

## 📝 Langkah-Langkah Mengubah Konfigurasi

### 1. Buka File `.env`

```bash
# Dari root project
nano .env    # atau gunakan editor favorit Anda
```

### 2. Edit Nilai yang Diinginkan

```env
# Sebelum
SCHEDULE_SCHOOL_START_HOUR=7
SCHEDULE_SCHOOL_END_HOUR=15

# Sesudah
SCHEDULE_SCHOOL_START_HOUR=6
SCHEDULE_SCHOOL_END_HOUR=16
```

### 3. Simpan File

```bash
# Tekan Ctrl+X lalu Y (jika menggunakan nano)
```

### 4. Validate Konfigurasi (Optional)

```bash
# Test tanpa benar-benar generate jadwal
php artisan schedule:generate 2025-2026 --validate-only
```

### 5. Generate Jadwal

```bash
# Generate dengan konfigurasi baru
php artisan schedule:generate 2025-2026 --force
```

---

## ⚙️ Konfigurasi di Kode (Advanced)

Jika ingin mengakses konfigurasi dalam kode:

```php
<?php

// Di Controller atau Service
use Illuminate\Support\Facades\Config;

class MyController
{
    public function index()
    {
        $startHour = config('schedule.school_start_hour');
        $endHour = config('schedule.school_end_hour');
        $lessonDuration = config('schedule.lesson_duration');
        $breakDuration = config('schedule.break_duration');
        $operationalDays = config('schedule.operational_days');
        $maxHours = config('schedule.max_teacher_hours_per_week');

        // Gunakan nilai-nilai ini
    }
}
```

---

## 🔍 Validasi Konfigurasi

### Pastikan Nilai Valid

```env
# ❌ SALAH - SCHEDULE_OPERATIONAL_DAYS harus angka 0-6
SCHEDULE_OPERATIONAL_DAYS=Monday,Tuesday,Wednesday

# ✅ BENAR
SCHEDULE_OPERATIONAL_DAYS=0,1,2

# ❌ SALAH - jam harus 0-23
SCHEDULE_SCHOOL_START_HOUR=25

# ✅ BENAR
SCHEDULE_SCHOOL_START_HOUR=7

# ❌ SALAH - durasi harus positif
SCHEDULE_LESSON_DURATION=-45

# ✅ BENAR
SCHEDULE_LESSON_DURATION=45
```

---

## 📚 File Terkait

- **Konfigurasi:** `config/schedule.php`
- **Environment:** `.env` dan `.env.example`
- **Dokumentasi:** `SCHEDULE_GENERATOR_README.md`
- **Quick Start:** `QUICK_START.md`

---

## 💡 Tips

1. **Backup .env sebelum mengubah:**

    ```bash
    cp .env .env.backup
    ```

2. **Gunakan .env.example sebagai referensi**

3. **Test dengan --validate-only sebelum generate:**

    ```bash
    php artisan schedule:generate 2025-2026 --validate-only
    ```

4. **Cek hasil dengan analisis:**

    ```bash
    php artisan schedule:analyze 2025-2026
    ```

5. **Cache configuration (production):**
    ```bash
    php artisan config:cache
    ```

---

**Last Updated:** May 25, 2026  
**Version:** 1.0
