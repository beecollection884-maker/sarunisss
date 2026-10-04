# 🎓 Fitur Jadwal Otomatis - Dokumentasi Lengkap

Dokumentasi lengkap untuk fitur automatic schedule generation yang telah diimplementasikan di Laravel project.

---

## 📚 Panduan Navigasi

### 🚀 **Untuk Mulai Cepat**

👉 Baca: [QUICK_START.md](QUICK_START.md)

### ⚙️ **Untuk Setup Konfigurasi**

👉 Baca: [SETUP_COMPLETE.md](SETUP_COMPLETE.md)

### 📖 **Untuk Dokumentasi Lengkap**

👉 Baca: [SCHEDULE_GENERATOR_README.md](SCHEDULE_GENERATOR_README.md)

### 🔧 **Untuk Konfigurasi Detail**

👉 Baca: [CONFIG_GUIDE.md](CONFIG_GUIDE.md)

### 📊 **Untuk Referensi Variable**

👉 Baca: [CONFIGURATION_REFERENCE.md](CONFIGURATION_REFERENCE.md)

### 🐛 **Untuk Troubleshooting**

👉 Baca: [TROUBLESHOOTING_FAQ.md](TROUBLESHOOTING_FAQ.md)

### 🔄 **Untuk Changelog**

👉 Baca: [CONFIGURATION_CHANGELOG.md](CONFIGURATION_CHANGELOG.md)

---

## 📋 Struktur Fitur

```
Fitur Schedule Generator
├── Services (Logic)
│   ├── ScheduleGeneratorService.php      (Generate jadwal)
│   ├── ScheduleOptimizerService.php      (Analisis & optimasi)
│   └── ScheduleDisplayService.php        (Tampil & export)
│
├── Models (Database)
│   ├── ScheduleGeneration.php            (History generate)
│   └── [Models existing] (User, Teacher, Student, etc.)
│
├── Commands (CLI)
│   ├── GenerateScheduleCommand.php       (Generate via CLI)
│   └── AnalyzeScheduleCommand.php        (Analisis via CLI)
│
├── Controllers (Web)
│   └── ScheduleController.php            (REST API & web)
│
├── Configuration
│   └── config/schedule.php               (Konfigurasi jadwal)
│
└── Documentation
    ├── README.md                         (Overview)
    ├── QUICK_START.md                    (Quick start)
    ├── SCHEDULE_GENERATOR_README.md      (Full docs)
    ├── CONFIG_GUIDE.md                   (Config guide)
    ├── CONFIGURATION_REFERENCE.md        (Variable ref)
    ├── SETUP_COMPLETE.md                 (Setup summary)
    ├── TROUBLESHOOTING_FAQ.md            (Troubleshooting)
    └── CONFIGURATION_CHANGELOG.md        (Changelog)
```

---

## 🎯 Fitur Utama

### ✅ Fitur yang Sudah Ada

1. **Automatic Schedule Generation**
    - Generate jadwal mengajar otomatis untuk semua kelas
    - Conflict detection (guru, ruangan)
    - Workload analysis per guru
    - Time slot optimization

2. **Multiple Export Formats**
    - HTML (print-friendly)
    - CSV (spreadsheet)
    - ICS (calendar format)
    - JSON (API)

3. **CLI Commands**
    - `php artisan schedule:generate` - Generate jadwal
    - `php artisan schedule:analyze` - Analisis jadwal

4. **Web Interface**
    - View per-class schedule
    - View per-teacher schedule
    - Conflict detection & reporting
    - Workload analysis
    - Schedule export

5. **Configuration System**
    - Environment-based (.env)
    - All parameters configurable
    - No code changes needed

---

## 🔧 Quick Command Reference

### Generate Jadwal

```bash
# Validate only (no save)
php artisan schedule:generate 2025-2026 --validate-only

# Generate untuk tahun ajaran tertentu
php artisan schedule:generate 2025-2026

# Generate spesifik kelas
php artisan schedule:generate 2025-2026 --class=10A

# Force regenerate (hapus yang lama)
php artisan schedule:generate 2025-2026 --force
```

### Analisis Jadwal

```bash
# Analisis lengkap
php artisan schedule:analyze 2025-2026

# Hanya lihat conflicts
php artisan schedule:analyze 2025-2026 --conflicts-only

# Generate JSON report
php artisan schedule:analyze 2025-2026 --report
```

### Web Routes

```
GET    /schedule/generate              Form generate
POST   /schedule/generate              Process generate
GET    /schedule/class/{id}/{year}     View kelas schedule
GET    /schedule/teacher/{id}/{year}   View guru schedule
GET    /schedule/analyze/{year}        Analisis
GET    /schedule/conflicts/{year}      Lihat conflicts
GET    /schedule/export/{classId}/{year}/{format}  Export
```

---

## 🎛️ Environment Variables

Semua di `.env`:

```env
# Jam Operasional (07:00 - 15:00)
SCHEDULE_SCHOOL_START_HOUR=7
SCHEDULE_SCHOOL_END_HOUR=15

# Durasi (45 menit pelajaran + 30 menit break)
SCHEDULE_LESSON_DURATION=45
SCHEDULE_BREAK_DURATION=30

# Hari Operasional (Senin-Jumat)
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4

# Beban Kerja (maks 25 jam/minggu)
SCHEDULE_MAX_TEACHER_HOURS=25

# Format & Mode
SCHEDULE_ROOM_NAME_FORMAT=Ruang {class}
SCHEDULE_SIMULATION_MODE=false

# Validasi
SCHEDULE_ALLOW_SUBJECTS_WITHOUT_TEACHER=false
SCHEDULE_ALLOW_CLASSES_WITHOUT_SUBJECTS=false
SCHEDULE_ALLOW_TEACHERS_WITHOUT_SCHEDULE=false
```

---

## 📊 Contoh Use Cases

### Use Case 1: Sekolah Reguler

```env
SCHEDULE_SCHOOL_START_HOUR=7
SCHEDULE_SCHOOL_END_HOUR=15
SCHEDULE_LESSON_DURATION=45
SCHEDULE_BREAK_DURATION=30
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4
SCHEDULE_MAX_TEACHER_HOURS=25
```

**Generate:**

```bash
php artisan schedule:generate 2025-2026 --validate-only
php artisan schedule:generate 2025-2026 --force
```

**Lihat:**

```
GET /schedule/class/10A/2025-2026
GET /schedule/teacher/1/2025-2026
```

---

### Use Case 2: International School

```env
SCHEDULE_SCHOOL_START_HOUR=8
SCHEDULE_SCHOOL_END_HOUR=16
SCHEDULE_LESSON_DURATION=50
SCHEDULE_BREAK_DURATION=30
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4,5  # Include Saturday
SCHEDULE_MAX_TEACHER_HOURS=28
```

---

### Use Case 3: School dengan Multiple Shifts

Buat 2 config di database, pakai di conditional logic.

---

## 🔄 Workflow Standar

### 1. Setup Awal

```bash
# Copy config
cp config/schedule.php config/schedule.php

# Setup .env
nano .env
# Edit schedule variables

# Clear cache
php artisan config:clear
```

### 2. Validasi Data

```bash
# Pastikan data lengkap
# - Semua kelas ada
# - Semua guru ada
# - Semua mapel ada
# - Semua teaching assignment ada
```

### 3. Validate Konfigurasi

```bash
php artisan schedule:generate 2025-2026 --validate-only
```

### 4. Generate Jadwal

```bash
php artisan schedule:generate 2025-2026 --force
```

### 5. Analisis Hasil

```bash
php artisan schedule:analyze 2025-2026

# Jika ada conflict:
php artisan schedule:analyze 2025-2026 --conflicts-only
```

### 6. Export & Distribusi

```bash
# Via web
GET /schedule/export/{classId}/2025-2026/html
GET /schedule/export/{classId}/2025-2026/csv
GET /schedule/export/{classId}/2025-2026/ics
```

---

## 🐛 Common Issues & Solutions

### Issue: "No available time slots"

**Solusi:** Operasional terlalu pendek

```env
# ❌ Tidak cukup: 7-12 = 5 jam
SCHEDULE_SCHOOL_START_HOUR=7
SCHEDULE_SCHOOL_END_HOUR=12

# ✅ Cukup: 7-15 = 8 jam
SCHEDULE_SCHOOL_START_HOUR=7
SCHEDULE_SCHOOL_END_HOUR=15
```

---

### Issue: "Teacher overloaded"

**Solusi:** Naikkan max hours atau tambah guru

```env
# Opsi 1: Naikkan limit
SCHEDULE_MAX_TEACHER_HOURS=30

# Opsi 2: Kurangi durasi (40 menit vs 45)
SCHEDULE_LESSON_DURATION=40
```

---

### Issue: Config tidak berubah

**Solusi:** Clear cache

```bash
php artisan config:clear
php artisan cache:clear
```

---

## 📈 Performance Tips

1. **Jika lambat:**
    - Gunakan `--class=xxxxxx` untuk generate per kelas
    - Naikkan PHP memory limit

2. **Jika memory error:**

    ```bash
    php -d memory_limit=1G artisan schedule:generate 2025-2026
    ```

3. **Testing mode:**
    ```env
    SCHEDULE_SIMULATION_MODE=true
    ```

---

## 🔐 Security Notes

- **JANGAN commit .env** → Add ke .gitignore
- **Validasi input** → Semua sudah di-validate
- **Check permission** → Add middleware/auth sesuai kebutuhan
- **Monitor logs** → `storage/logs/laravel.log`

---

## 📞 Documentation Files Summary

| File                             | Purpose              | Pembaca Target   |
| -------------------------------- | -------------------- | ---------------- |
| **README.md**                    | Overview             | Admin            |
| **QUICK_START.md**               | Quick reference      | Admin, Developer |
| **SCHEDULE_GENERATOR_README.md** | Full documentation   | Developer        |
| **CONFIG_GUIDE.md**              | Configuration detail | Admin, DevOps    |
| **CONFIGURATION_REFERENCE.md**   | Variable reference   | Admin, DevOps    |
| **SETUP_COMPLETE.md**            | Setup summary        | Admin            |
| **TROUBLESHOOTING_FAQ.md**       | Troubleshooting      | Admin, DevOps    |
| **CONFIGURATION_CHANGELOG.md**   | Changes log          | Developer        |

---

## ✨ Next Steps (Optional)

### Future Enhancements

1. **UI Dashboard**
    - Schedule visualization
    - Drag-drop interface
    - Real-time preview

2. **Mobile App**
    - Schedule view
    - Notification
    - Offline support

3. **Integration**
    - Google Calendar
    - Outlook Calendar
    - WhatsApp notification

4. **AI Optimization**
    - Smart conflict resolution
    - Predictive analysis
    - Auto-recommendation

5. **Multi-School**
    - Handle multiple schools
    - Different configs per school
    - Comparative analysis

---

## 🎉 Kesimpulan

Fitur jadwal otomatis sudah lengkap dengan:

✅ **Core Functionality** - Generate, analyze, optimize, export  
✅ **Configuration** - Flexible via .env  
✅ **Documentation** - Lengkap dengan contoh  
✅ **CLI Commands** - Easy automation  
✅ **Web Interface** - User-friendly  
✅ **Error Handling** - Comprehensive validation  
✅ **Performance** - Optimized

**Ready untuk production!** 🚀

---

## 📞 Support

- Lihat documentation sesuai kebutuhan
- Check troubleshooting FAQ
- Review logs di `storage/logs/laravel.log`
- Run validation sebelum generate

---

**Project:** Sarunis School Schedule Generator  
**Version:** 1.0  
**Status:** ✅ Complete & Ready  
**Last Updated:** May 25, 2026
