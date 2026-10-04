# 🎯 Setup Configuration via Environment Variables - Complete!

## ✅ Task Completed

Semua konfigurasi jadwal sekolah telah dipindahkan dari hardcoded constants menjadi environment variables yang dapat diubah di `.env`.

---

## 📊 Ringkasan Perubahan

### ✨ Yang Dibuat

| File                           | Keterangan                              |
| ------------------------------ | --------------------------------------- |
| **config/schedule.php**        | File konfigurasi terpusat untuk jadwal  |
| **CONFIG_GUIDE.md**            | Panduan lengkap konfigurasi jadwal      |
| **CONFIGURATION_CHANGELOG.md** | Daftar lengkap perubahan yang dilakukan |

### 🔄 Yang Diperbarui

| File                                          | Perubahan                               |
| --------------------------------------------- | --------------------------------------- |
| **.env**                                      | Ditambah 11 variabel konfigurasi jadwal |
| **.env.example**                              | Ditambah 11 variabel dengan dokumentasi |
| **app/Services/ScheduleGeneratorService.php** | Menggunakan `config()` bukan constants  |
| **app/Services/ScheduleOptimizerService.php** | Menggunakan `config()` bukan constants  |
| **app/Services/ScheduleDisplayService.php**   | Menggunakan `config()` bukan constants  |
| **QUICK_START.md**                            | Diperbarui referensi konfigurasi        |
| **SCHEDULE_GENERATOR_README.md**              | Diperbarui referensi konfigurasi        |

---

## 🎛️ Variabel Konfigurasi yang Tersedia

### Jam Operasional

```env
SCHEDULE_SCHOOL_START_HOUR=7        # Jam mulai (0-23)
SCHEDULE_SCHOOL_END_HOUR=15         # Jam selesai (0-23)
```

### Durasi

```env
SCHEDULE_LESSON_DURATION=45         # Durasi pelajaran (menit)
SCHEDULE_BREAK_DURATION=30          # Durasi break (menit)
```

### Hari Operasional

```env
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4 # 0=Senin, 1=Selasa, ... 6=Minggu
```

### Beban Kerja

```env
SCHEDULE_MAX_TEACHER_HOURS=25       # Maksimal jam mengajar guru/minggu
```

### Format & Mode

```env
SCHEDULE_ROOM_NAME_FORMAT=Ruang {class}     # Format nama ruangan
SCHEDULE_SIMULATION_MODE=false              # Mode simulasi
```

### Validasi

```env
SCHEDULE_ALLOW_SUBJECTS_WITHOUT_TEACHER=false
SCHEDULE_ALLOW_CLASSES_WITHOUT_SUBJECTS=false
SCHEDULE_ALLOW_TEACHERS_WITHOUT_SCHEDULE=false
```

---

## 📝 Contoh Penggunaan

### Scenario 1: Sekolah Pagi 7-3 (Default)

Tidak perlu ubah, gunakan default:

```env
SCHEDULE_SCHOOL_START_HOUR=7
SCHEDULE_SCHOOL_END_HOUR=15
SCHEDULE_LESSON_DURATION=45
SCHEDULE_BREAK_DURATION=30
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4
```

### Scenario 2: Sekolah Full Day 6-4 Senin-Sabtu

```env
SCHEDULE_SCHOOL_START_HOUR=6
SCHEDULE_SCHOOL_END_HOUR=16
SCHEDULE_LESSON_DURATION=50
SCHEDULE_BREAK_DURATION=20
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4,5
SCHEDULE_MAX_TEACHER_HOURS=30
```

### Scenario 3: Sekolah Pagi Singkat 7-12

```env
SCHEDULE_SCHOOL_START_HOUR=7
SCHEDULE_SCHOOL_END_HOUR=12
SCHEDULE_LESSON_DURATION=40
SCHEDULE_BREAK_DURATION=10
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4
SCHEDULE_MAX_TEACHER_HOURS=20
```

---

## 🚀 Cara Menggunakan

### Step 1: Edit .env

```bash
# Buka file .env
nano .env

# Atau gunakan editor favorit Anda
code .env
```

### Step 2: Ubah Konfigurasi

```env
# Contoh: Ubah jam mulai dari 7 menjadi 6
SCHEDULE_SCHOOL_START_HOUR=6        # ← Ubah dari 7 ke 6
```

### Step 3: Simpan

Tekan `Ctrl+S` (atau sesuai editor Anda)

### Step 4: Generate Jadwal

```bash
# Validasi dulu
php artisan schedule:generate 2025-2026 --validate-only

# Jika OK, generate
php artisan schedule:generate 2025-2026 --force
```

---

## 🔍 Verifikasi

### Cek config sudah terbaca

```bash
# Dari terminal project Anda:
php artisan tinker

# Di dalam tinker:
>>> config('schedule.school_start_hour')
=> 7

>>> config('schedule.operational_days')
=> [0, 1, 2, 3, 4]

>>> config('schedule.lesson_duration')
=> 45
```

---

## 📚 Dokumentasi

Untuk panduan lengkap, lihat:

1. **[CONFIG_GUIDE.md](CONFIG_GUIDE.md)** - Panduan konfigurasi detail
2. **[CONFIGURATION_CHANGELOG.md](CONFIGURATION_CHANGELOG.md)** - Changelog lengkap
3. **[QUICK_START.md](QUICK_START.md)** - Quick start guide
4. **[SCHEDULE_GENERATOR_README.md](SCHEDULE_GENERATOR_README.md)** - Full documentation

---

## 🎯 Keuntungan

✅ **Mudah Diubah** - Cukup edit .env, tidak perlu edit kode  
✅ **Environment-Specific** - Bisa berbeda dev/staging/production  
✅ **Flexible** - Mudah menyesuaikan dengan berbagai tipe sekolah  
✅ **Scalable** - Support multiple configurations  
✅ **Well-Documented** - Ada panduan lengkap

---

## 🔄 Services yang Sudah Diperbarui

### ScheduleGeneratorService ✅

- Membaca jam operasional dari config
- Membaca durasi pelajaran dari config
- Membaca hari operasional dari config
- Membaca nama hari dari config

### ScheduleOptimizerService ✅

- Membaca max teacher hours dari config
- Membaca hari operasional dari config
- Membaca nama hari dari config
- Dinamis hitung workload threshold

### ScheduleDisplayService ✅

- Membaca hari operasional dari config
- Membaca nama hari dari config
- Support flexible schedule display

---

## ⚠️ Penting!

1. **Cache config saat production:**

    ```bash
    php artisan config:cache
    ```

2. **Clear cache saat update .env:**

    ```bash
    php artisan config:clear
    ```

3. **Test dengan --validate-only sebelum generate:**
    ```bash
    php artisan schedule:generate 2025-2026 --validate-only
    ```

---

## 📋 Checklist

- ✅ Config file dibuat (`config/schedule.php`)
- ✅ Environment variables ditambah di `.env`
- ✅ Environment variables ditambah di `.env.example`
- ✅ ScheduleGeneratorService diperbarui
- ✅ ScheduleOptimizerService diperbarui
- ✅ ScheduleDisplayService diperbarui
- ✅ CONFIG_GUIDE.md dibuat
- ✅ CONFIGURATION_CHANGELOG.md dibuat
- ✅ Dokumentasi diperbarui
- ✅ Contoh konfigurasi tersedia

---

## 🎉 Selesai!

Sekarang Anda bisa mengubah konfigurasi jadwal sekolah hanya dengan edit file `.env`, tanpa perlu edit kode sama sekali!

**Instruksi lengkap ada di [CONFIG_GUIDE.md](CONFIG_GUIDE.md)**

---

**Version:** 1.0  
**Last Updated:** May 25, 2026  
**Status:** ✅ Complete
