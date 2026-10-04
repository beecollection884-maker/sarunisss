# Troubleshooting & FAQ

## ❓ Pertanyaan Umum

### Q1: Saya sudah ubah .env, kenapa perubahan tidak terlihat?

**Jawab:** Laravel cache configuration. Solusi:

```bash
# Clear cache
php artisan config:clear

# Cache ulang
php artisan config:cache
```

---

### Q2: Bagaimana format SCHEDULE_OPERATIONAL_DAYS yang benar?

**Jawab:** Gunakan angka 0-6 dipisahkan koma, NO SPACES:

✅ Benar:

```env
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4
```

❌ Salah:

```env
SCHEDULE_OPERATIONAL_DAYS=0, 1, 2, 3, 4  # Spasi!
SCHEDULE_OPERATIONAL_DAYS="0,1,2,3,4"    # Quotes!
SCHEDULE_OPERATIONAL_DAYS=Senin,Selasa   # Text!
```

---

### Q3: Apa bedanya SCHEDULE_SIMULATION_MODE=true vs false?

**Jawab:**

| Mode      | Simpan DB | Gunakan Kapan        |
| --------- | --------- | -------------------- |
| **true**  | ❌ Tidak  | Testing, Development |
| **false** | ✅ Ya     | Production, Final    |

```bash
# Testing (tidak simpan)
SCHEDULE_SIMULATION_MODE=true
php artisan schedule:generate 2025-2026 --validate-only

# Actual (simpan)
SCHEDULE_SIMULATION_MODE=false
php artisan schedule:generate 2025-2026
```

---

### Q4: Bagaimana jika saya mau pelajaran 50 menit, break 20 menit?

**Jawab:**

```env
SCHEDULE_LESSON_DURATION=50
SCHEDULE_BREAK_DURATION=20

# Total per slot: 50 + 20 = 70 menit
```

---

### Q5: Apakah bisa punya multiple configurations (dev vs production)?

**Jawab:** Ya! Gunakan `.env.local`:

```bash
# .env (untuk semua environment)
SCHEDULE_SCHOOL_START_HOUR=7

# .env.local (hanya lokal, override .env)
SCHEDULE_SCHOOL_START_HOUR=8

# Laravel membaca .env.local lebih dulu
```

---

### Q6: Bagaimana cara lihat config yang sedang dipakai?

**Jawab:** Gunakan Tinker:

```bash
php artisan tinker

# Di Tinker
>>> config('schedule')
=> [
     'school_start_hour' => 7,
     'school_end_hour' => 15,
     'lesson_duration' => 45,
     ...
   ]

>>> config('schedule.school_start_hour')
=> 7
```

---

## 🐛 Troubleshooting

### ❌ Error: "Undefined index: school_start_hour"

**Penyebab:** config/schedule.php tidak ada atau corrupt

**Solusi:**

```bash
# Cek file ada
ls config/schedule.php

# Jika tidak ada, buat ulang dari git
git checkout config/schedule.php

# Clear cache
php artisan config:clear
```

---

### ❌ Error: "Invalid operational days format"

**Penyebab:** Format SCHEDULE_OPERATIONAL_DAYS salah

**Solusi:**

```env
# ❌ SALAH - Ada spasi
SCHEDULE_OPERATIONAL_DAYS=0, 1, 2, 3, 4

# ✅ BENAR - Tanpa spasi
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4
```

---

### ❌ Error: "School end hour must be after start hour"

**Penyebab:** END_HOUR <= START_HOUR

**Solusi:**

```env
# ❌ SALAH
SCHEDULE_SCHOOL_START_HOUR=15
SCHEDULE_SCHOOL_END_HOUR=7

# ✅ BENAR
SCHEDULE_SCHOOL_START_HOUR=7
SCHEDULE_SCHOOL_END_HOUR=15
```

---

### ❌ Error: "No available time slots"

**Penyebab:** Operasional terlalu pendek vs durasi pelajaran + break

**Solusi:**

```bash
# Cek: Total operasional >= (lesson + break) * number_of_slots

# Contoh: 7:00-15:00 = 8 jam = 480 menit
# Jika pelajaran 45 + break 30 = 75 menit per slot
# Bisa: 480 / 75 = 6.4 slot (benar!)

# Jika 45 + 60 = 105 menit per slot
# Bisa: 480 / 105 = 4.5 slot (benar!)

# Tapi jika 7:00-12:00 = 5 jam = 300 menit
# Dan pelajaran 45 + 60 = 105 menit per slot
# Hanya: 300 / 105 = 2.8 slot (tidak cukup!)
```

---

### ❌ Error: "Guru terlalu banyak jam"

**Penyebab:** Teacher workload > SCHEDULE_MAX_TEACHER_HOURS

**Solusi:**

```env
# Opsi 1: Naikkan max hours
SCHEDULE_MAX_TEACHER_HOURS=30

# Opsi 2: Kurangi beban (tambah guru/mapel)
# Opsi 3: Ubah jadwal
```

---

### ❌ Config tidak berubah setelah edit .env

**Penyebab:** Config di-cache

**Solusi:**

```bash
# Clear semua cache
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Atau reload via Horizon jika pakai
php artisan horizon:terminate
```

---

## 🔍 Debug Tips

### Tip 1: Lihat nilai config actual

```bash
php artisan tinker

>>> dd(config('schedule.school_start_hour'))
7

>>> dd(config('schedule.operational_days'))
array:5 [
  0 => 0
  1 => 1
  2 => 2
  3 => 3
  4 => 4
]
```

---

### Tip 2: Validate konfigurasi sebelum generate

```bash
# Jangan langsung generate, test dulu
php artisan schedule:generate 2025-2026 --validate-only

# Output akan show issues jika ada
```

---

### Tip 3: Generate dengan detailed output

```bash
php artisan schedule:generate 2025-2026 -v

# -v = normal verbosity
# -vv = more verbose
# -vvv = debug
```

---

### Tip 4: Lihat log jika error

```bash
# Check Laravel log
tail -f storage/logs/laravel.log

# Lihat error detail
cat storage/logs/laravel.log | grep schedule
```

---

### Tip 5: Test dengan simulation mode

```bash
# Set simulation mode
SCHEDULE_SIMULATION_MODE=true

# Generate tanpa simpan (safe untuk testing)
php artisan schedule:generate 2025-2026

# Lihat hasilnya tanpa khawatir corrupt data
```

---

## 📊 Performance Optimization

### Jika Generate Lambat

```env
# Kurangi durasi pelajaran
SCHEDULE_LESSON_DURATION=40  # dari 45

# Atau kurangi hari operasional
SCHEDULE_OPERATIONAL_DAYS=0,1,2,3,4  # single session
```

---

### Jika Memory Habis

```bash
# Naikkan PHP memory
php -d memory_limit=512M artisan schedule:generate 2025-2026

# Atau di .env
LARAVEL_MEMORY_LIMIT=512M
```

---

## 🔐 Security

### ❌ JANGAN

```env
# JANGAN hardcode password di .env
SCHEDULE_DB_PASSWORD=secret123

# JANGAN commit .env ke git
git add .env  # JANGAN!
```

---

### ✅ LAKUKAN

```bash
# Copy dari example
cp .env.example .env

# Add ke .gitignore
echo ".env" >> .gitignore

# Commit .env.example (tanpa secret)
git add .env.example
git commit -m "Add env template"
```

---

## 📝 Maintenance

### Regular Checks

```bash
# Check setiap bulan
php artisan schedule:analyze 2025-2026

# Lihat conflicts
php artisan schedule:analyze 2025-2026 --conflicts-only

# Generate report
php artisan schedule:analyze 2025-2026 --report
```

---

### Update Config

```bash
# Jika perlu ubah jam operasional semester depan
SCHEDULE_SCHOOL_START_HOUR=6  # Lebih cepat

# Clear cache
php artisan config:clear

# Test dulu
php artisan schedule:generate 2026-2027 --validate-only

# Jika OK, generate
php artisan schedule:generate 2026-2027 --force
```

---

## 🎯 Best Practices

### 1. Always Validate First

```bash
# ✅ BENAR
php artisan schedule:generate 2025-2026 --validate-only
php artisan schedule:generate 2025-2026

# ❌ SALAH
php artisan schedule:generate 2025-2026 --force  # Skip check
```

---

### 2. Keep .env Backup

```bash
# Backup sebelum ubah
cp .env .env.backup

# Jika ada masalah
cp .env.backup .env
```

---

### 3. Use Version Control

```bash
# Track .env.example saja
git add .env.example
git commit -m "Update schedule config template"

# JANGAN track .env
echo ".env" >> .gitignore
```

---

### 4. Document Changes

```bash
# Update CONFIGURATION_CHANGELOG.md
# Catat setiap perubahan config penting
```

---

### 5. Test di Dev Dulu

```env
# development/.env
SCHEDULE_SIMULATION_MODE=true

# production/.env
SCHEDULE_SIMULATION_MODE=false
```

---

## 📞 Still Need Help?

1. Lihat [CONFIG_GUIDE.md](CONFIG_GUIDE.md) - Detailed guide
2. Lihat [CONFIGURATION_REFERENCE.md](CONFIGURATION_REFERENCE.md) - Variable reference
3. Lihat [SETUP_COMPLETE.md](SETUP_COMPLETE.md) - Quick setup
4. Check log: `storage/logs/laravel.log`
5. Run validation: `php artisan schedule:generate --validate-only`

---

**Version:** 1.0  
**Last Updated:** May 25, 2026
