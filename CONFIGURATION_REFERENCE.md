# Environment Configuration Reference

## 📊 Configuration Matrix

```
┌─────────────────────────────────────────────────────────────────┐
│                  SCHEDULE CONFIGURATION HIERARCHY               │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  .env (Environment Variables)                                  │
│  ├── SCHEDULE_SCHOOL_START_HOUR         [7]                   │
│  ├── SCHEDULE_SCHOOL_END_HOUR           [15]                  │
│  ├── SCHEDULE_LESSON_DURATION           [45 menit]            │
│  ├── SCHEDULE_BREAK_DURATION            [30 menit]            │
│  ├── SCHEDULE_OPERATIONAL_DAYS          [0,1,2,3,4]           │
│  ├── SCHEDULE_MAX_TEACHER_HOURS         [25 jam]              │
│  ├── SCHEDULE_ROOM_NAME_FORMAT          [Ruang {class}]       │
│  ├── SCHEDULE_SIMULATION_MODE            [false]               │
│  └── SCHEDULE_ALLOW_*                    [validation flags]    │
│          ↓ (read by)                                           │
│  config/schedule.php                                          │
│  └── Parsed configuration array                               │
│          ↓ (accessed via)                                     │
│  config('schedule.*')                                         │
│          ↓ (used by)                                          │
│  Services & Commands                                          │
│  ├── ScheduleGeneratorService                                 │
│  ├── ScheduleOptimizerService                                 │
│  └── ScheduleDisplayService                                   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🎯 Configuration Overview

### School Hours (Jam Operasional)

```
Timeline Example: 07:00 - 15:00
═════════════════════════════════════════════════════════════════

07:00 ├─ PELAJARAN 1 (45 menit)
      │
07:45 ├─ BREAK (30 menit)  ← SCHEDULE_BREAK_DURATION
      │
08:15 ├─ PELAJARAN 2 (45 menit)  ← SCHEDULE_LESSON_DURATION
      │
09:00 ├─ BREAK (30 menit)
      │
09:30 ├─ PELAJARAN 3 (45 menit)
      │
...

15:00 └─ END OF SCHOOL (SCHEDULE_SCHOOL_END_HOUR)

Variables:
  SCHEDULE_SCHOOL_START_HOUR = 7   (Jam mulai)
  SCHEDULE_SCHOOL_END_HOUR = 15    (Jam selesai)
```

### Operational Days (Hari Operasional)

```
Day Mapping:
  0 = Senin   (Monday)
  1 = Selasa  (Tuesday)
  2 = Rabu    (Wednesday)
  3 = Kamis   (Thursday)
  4 = Jumat   (Friday)
  5 = Sabtu   (Saturday)
  6 = Minggu  (Sunday)

Examples:
  SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4      (Senin-Jumat)
  SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4,5    (Senin-Sabtu)
  SCHEDULE_OPERATIONAL_DAYS=0,1,2          (Senin-Rabu saja)
```

### Teacher Workload (Beban Kerja Guru)

```
SCHEDULE_MAX_TEACHER_HOURS = 25 jam/minggu

Workload Status:
  < 12.5 jam  → Rendah
  12.5-17.5   → Normal
  17.5-25     → Tinggi
  > 25        → Sangat Tinggi (Overloaded!)
              ↑
              Threshold based on SCHEDULE_MAX_TEACHER_HOURS
```

---

## 📋 Complete Variable Reference

### 1. Jam Operasional (School Hours)

| Var                          | Type | Default | Range | Description         |
| ---------------------------- | ---- | ------- | ----- | ------------------- |
| `SCHEDULE_SCHOOL_START_HOUR` | int  | `7`     | 0-23  | Jam mulai sekolah   |
| `SCHEDULE_SCHOOL_END_HOUR`   | int  | `15`    | 0-23  | Jam selesai sekolah |

**Constraints:**

- Harus 24-hour format
- END_HOUR > START_HOUR
- Misal: START=7, END=15 → 07:00-15:00

---

### 2. Durasi Pelajaran & Break (Lesson Durations)

| Var                        | Type | Default | Min | Max | Description              |
| -------------------------- | ---- | ------- | --- | --- | ------------------------ |
| `SCHEDULE_LESSON_DURATION` | int  | `45`    | 15  | 120 | Durasi pelajaran (menit) |
| `SCHEDULE_BREAK_DURATION`  | int  | `30`    | 0   | 60  | Durasi break (menit)     |

**Notes:**

- Total slot = LESSON_DURATION + BREAK_DURATION
- Example: 45 + 30 = 75 menit per slot
- Harus positif

---

### 3. Hari Operasional (Operational Days)

| Var                         | Type   | Default     | Format    | Description        |
| --------------------------- | ------ | ----------- | --------- | ------------------ |
| `SCHEDULE_OPERATIONAL_DAYS` | string | `0,1,2,3,4` | CSV (0-6) | Hari kerja sekolah |

**Valid Values:**

```
0 = Senin
1 = Selasa
2 = Rabu
3 = Kamis
4 = Jumat
5 = Sabtu
6 = Minggu
```

**Examples:**

```env
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4      # Senin-Jumat
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4,5    # Senin-Sabtu
SCHEDULE_OPERATIONAL_DAYS=0,2,4          # Senin, Rabu, Jumat saja
```

---

### 4. Beban Kerja Guru (Teacher Workload)

| Var                          | Type | Default | Min | Description              |
| ---------------------------- | ---- | ------- | --- | ------------------------ |
| `SCHEDULE_MAX_TEACHER_HOURS` | int  | `25`    | 1   | Maksimal jam/minggu guru |

**Usage:**

- Guru mengajar > nilai ini → "Overloaded"
- Digunakan untuk rekomendasi perbaikan
- Threshold workload dihitung dinamis dari nilai ini

---

### 5. Format & Identitas (Format & Identity)

| Var                         | Type   | Default         | Description         |
| --------------------------- | ------ | --------------- | ------------------- |
| `SCHEDULE_ROOM_NAME_FORMAT` | string | `Ruang {class}` | Format nama ruangan |

**Placeholders:**

- `{class}` → Nama kelas (misal "10A")

**Examples:**

```env
SCHEDULE_ROOM_NAME_FORMAT=Ruang {class}         # "Ruang 10A"
SCHEDULE_ROOM_NAME_FORMAT=Kelas {class}         # "Kelas 10A"
SCHEDULE_ROOM_NAME_FORMAT=Lab {class}           # "Lab 10A"
SCHEDULE_ROOM_NAME_FORMAT=R-{class}             # "R-10A"
```

---

### 6. Mode & Debug (Mode & Debugging)

| Var                        | Type | Default | Values     | Description                       |
| -------------------------- | ---- | ------- | ---------- | --------------------------------- |
| `SCHEDULE_SIMULATION_MODE` | bool | `false` | true/false | Mode simulasi (tidak simpan data) |

**When true:**

- Generate jadwal tanpa simpan ke DB
- Gunakan untuk testing
- Performa lebih cepat

---

### 7. Opsi Validasi (Validation Options)

| Var                                        | Type | Default | Values     | Description               |
| ------------------------------------------ | ---- | ------- | ---------- | ------------------------- |
| `SCHEDULE_ALLOW_SUBJECTS_WITHOUT_TEACHER`  | bool | `false` | true/false | Izinkan mapel tanpa guru  |
| `SCHEDULE_ALLOW_CLASSES_WITHOUT_SUBJECTS`  | bool | `false` | true/false | Izinkan kelas tanpa mapel |
| `SCHEDULE_ALLOW_TEACHERS_WITHOUT_SCHEDULE` | bool | `false` | true/false | Izinkan guru tanpa jadwal |

**When false (default):**

- Akan warning/error saat validation
- Harus fix data sebelum generate

---

## 🔧 Real-World Configurations

### Config 1: Sekolah Indonesia Standard

```env
# 07:00-15:00 dengan 45 menit pelajaran
SCHEDULE_SCHOOL_START_HOUR=7
SCHEDULE_SCHOOL_END_HOUR=15
SCHEDULE_LESSON_DURATION=45
SCHEDULE_BREAK_DURATION=30
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4
SCHEDULE_MAX_TEACHER_HOURS=25
```

### Config 2: International School

```env
# 08:00-16:00 dengan 50 menit pelajaran + Sabtu
SCHEDULE_SCHOOL_START_HOUR=8
SCHEDULE_SCHOOL_END_HOUR=16
SCHEDULE_LESSON_DURATION=50
SCHEDULE_BREAK_DURATION=30
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4,5
SCHEDULE_MAX_TEACHER_HOURS=28
```

### Config 3: Islamic Boarding School (Pesantren)

```env
# 06:30-14:30 dengan 40 menit pelajaran
SCHEDULE_SCHOOL_START_HOUR=6
SCHEDULE_SCHOOL_END_HOUR=14
SCHEDULE_LESSON_DURATION=40
SCHEDULE_BREAK_DURATION=25
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4
SCHEDULE_MAX_TEACHER_HOURS=30
```

### Config 4: Half-Day School

```env
# 07:00-12:00 dengan 40 menit pelajaran
SCHEDULE_SCHOOL_START_HOUR=7
SCHEDULE_SCHOOL_END_HOUR=12
SCHEDULE_LESSON_DURATION=40
SCHEDULE_BREAK_DURATION=15
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4
SCHEDULE_MAX_TEACHER_HOURS=20
```

---

## 🎯 Quick Decision Tree

```
Kapan buat schedule baru?
│
├─ Setup awal? → Copy default dari .env.example
│
├─ Ubah jam operasional? → Edit SCHEDULE_SCHOOL_START/END_HOUR
│
├─ Tambah hari (Sabtu)? → Edit SCHEDULE_OPERATIONAL_DAYS
│
├─ Ubah durasi pelajaran? → Edit SCHEDULE_LESSON_DURATION
│
├─ Ubah beban guru? → Edit SCHEDULE_MAX_TEACHER_HOURS
│
└─ Test tanpa simpan? → Set SCHEDULE_SIMULATION_MODE=true
```

---

## ✅ Validation Checklist

```
Sebelum generate, cek:

□ SCHEDULE_SCHOOL_START_HOUR < SCHEDULE_SCHOOL_END_HOUR
  Contoh: 7 < 15 ✓

□ SCHEDULE_LESSON_DURATION + SCHEDULE_BREAK_DURATION < jam operasional
  Contoh: 45 + 30 = 75 menit per slot ✓

□ SCHEDULE_OPERATIONAL_DAYS hanya berisi 0-6 (valid day numbers)
  Contoh: 0,1,2,3,4 ✓

□ SCHEDULE_MAX_TEACHER_HOURS > 0
  Contoh: 25 ✓

□ Semua value adalah number (kecuali OPERATIONAL_DAYS & FORMAT)
  Contoh: 7 (bukan "07") ✓
```

---

## 📖 File Documentation

| File                    | Purpose                       |
| ----------------------- | ----------------------------- |
| **config/schedule.php** | Configuration file definition |
| **CONFIG_GUIDE.md**     | Detailed configuration guide  |
| **SETUP_COMPLETE.md**   | Setup completion summary      |
| **.env**                | Your local environment values |
| **.env.example**        | Template for new projects     |

---

**Version:** 1.0  
**Last Updated:** May 25, 2026
