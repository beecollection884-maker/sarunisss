# Laporan Bug — Aplikasi SARUNIS (SMP IP YAKIN)

**Tanggal laporan:** 4 Agustus 2026
**Image yang diuji:** `hmbasahay/saruniss:allinone`
**Digest:** `sha256:84544b2b869068265bd9583e2ae7a3dcd0c722bb3ee3e773866cc2378c58bef4`
**Stack:** Laravel 13.1.1 · PHP 8.3.33 · MariaDB 11.8.8
**Metode:** Analisis kode sumber + query langsung ke database bawaan image

---

## Ringkasan Eksekutif

Ditemukan **1 bug kritis** yang menyebabkan pengguna sah tidak bisa login, beserta
beberapa temuan turunan dan masalah keamanan.

Bug utamanya: **fitur Reset Data menghapus tabel profil (`teachers`, `students`)
tetapi tidak menghapus tabel `users`.** Karena akses portal disyaratkan memiliki
profil, semua akun yang profilnya terhapus menjadi tidak bisa login — sementara
akunnya sendiri tetap ada dan tetap membawa role.

Dampak terukur pada database bawaan image:

| Temuan | Jumlah |
|---|---:|
| Akun guru (nama nyata) tanpa profil → **tidak bisa login** | **87** |
| Akun siswa (nama nyata) tanpa profil → **tidak bisa login** | **77** |
| Akun orang tua tanpa anak tertaut → **tidak bisa login** | **139** |
| Siswa tanpa kelas (`school_class_id` NULL) | **320** (100%) |
| Total akun `@localhost` hasil impor | 587 |
| Total akun di sistem | 1.052 |

Dari 1.052 akun, sekitar **303 akun tidak dapat mengakses portal apa pun.**

---

## BUG-01 — Reset Data menghasilkan akun yatim (tidak bisa login) 🔴 KRITIS

### Gejala yang dialami pengguna

Login berhasil (email & password benar), tetapi langsung ditolak dengan HTTP 403:

```
Akun guru.19850220120240110022.5@localhost belum memiliki hak akses ke portal mana pun.
```

Pesan ini menyesatkan — akun tersebut **memiliki** role `guru_mapel`. Yang hilang
adalah baris profilnya di tabel `teachers`.

### Akar penyebab

Terdapat tiga komponen yang masing-masing benar, tetapi kombinasinya menghasilkan bug.

**1. Otorisasi portal mensyaratkan profil**

`app/Services/AuthService.php:232-235`

```php
if ($portal === 'guru-mapel') {
    return $user->hasRole(UserRole::GURU_MAPEL) &&
        $user->teacherProfile !== null;      // ← syarat kedua
}
```

**2. Reset Data menghapus profil, tetapi `users` dilindungi**

`app/Http/Controllers/Admin/DataResetController.php:85-91`

```php
'data_guru' => [
    'label' => 'Data Guru',
    'tables' => ['subject_attendances', 'teaching_assignments',
                 'subject_teacher', 'school_classes', 'teachers'],
    //                                                 ^^^^^^^^ dihapus
],
```

Baris 164 pada method yang sama:

```php
'protected_tables' => ['users', 'app_settings', 'migrations', ...],
//                      ^^^^^ users TIDAK IKUT dihapus
```

**3. Foreign key memutus tautan secara diam-diam**

```
teachers.user_id  → users.id   ON DELETE SET NULL
students.user_id  → users.id   ON DELETE SET NULL
```

### Alur kejadiannya

```
1. Admin menjalankan Reset Data grup "Data Guru"
   └─ tabel `teachers` dikosongkan
   └─ tabel `users` TIDAK disentuh (masuk protected_tables)

2. Akun user tetap ada, role `guru_mapel` tetap melekat
   └─ tapi teacherProfile sekarang NULL

3. Admin impor ulang CSV guru
   └─ Teacher baru dibuat (id baru)
   └─ ensureImportedTeacherAccount() melihat teacher->user === null
   └─ maka dibuatlah USER BARU, bukan memakai user lama

4. Email bentrok → uniqueImportedEmail() menambahkan suffix
   └─ guru.<NIP>@localhost  →  .2@  →  .3@  →  .4@  →  .5@

5. Akun lama menjadi sampah permanen: punya role, tanpa profil, tidak bisa login
```

`app/Services/CsvImportExportService.php:693-701`

```php
$email = $local.'@'.$domain;
$counter = 2;

while (User::query()->where('email', $email)->exists()) {
    $email = $local.'.'.$counter.'@'.$domain;   // ← menumpuk akun baru
    $counter++;
}
```

Perhatikan: importer **sudah** mencocokkan `Teacher` berdasarkan NIP
(`CsvImportExportService.php:233`), tetapi tidak melakukan hal yang sama untuk
`User`. Inkonsistensi inilah letak persoalannya.

### Bukti dari database

Satu guru memiliki **5 akun** — satu per siklus reset-dan-impor:

```
user_id | email                                  | teacher_id
--------|----------------------------------------|------------
    242 | guru.19850220120240110022@localhost    | NULL   ← yatim
    347 | guru.19850220120240110022.2@localhost  | NULL   ← yatim
    369 | guru.19850220120240110022.3@localhost  | NULL   ← yatim
    391 | guru.19850220120240110022.4@localhost  | NULL   ← yatim
   1053 | guru.19850220120240110022.5@localhost  | NULL   ← yatim (aktif)
```

Semua 22 baris di tabel `teachers` memiliki `created_at` dalam rentang
`2026-07-28 06:35:10` – `06:35:15` — bukti bahwa tabel ini pernah dikosongkan
lalu diisi ulang, sementara akun `users` dari gelombang sebelumnya tertinggal.

**22 nama guru** dan **41 nama siswa** memiliki akun duplikat.

### Query verifikasi

```sql
-- Guru bernama nyata yang tidak bisa login
SELECT COUNT(*) FROM users u
LEFT JOIN teachers t ON t.user_id = u.id
WHERE u.roles LIKE '%guru_mapel%'
  AND t.id IS NULL
  AND u.name NOT REGEXP '^Guru [0-9]+$'
  AND u.email NOT LIKE '%sarunis.test';
-- Hasil: 87

-- Siswa bernama nyata yang tidak bisa login
SELECT COUNT(*) FROM users u
LEFT JOIN students s ON s.user_id = u.id
WHERE u.roles LIKE '%siswa%'
  AND s.id IS NULL
  AND u.name NOT REGEXP '^Siswa [0-9]+$'
  AND u.email NOT LIKE '%sarunis.test';
-- Hasil: 77

-- Orang tua tanpa anak tertaut
SELECT COUNT(*) FROM users u
LEFT JOIN students s ON s.parent_user_id = u.id
WHERE u.roles LIKE '%orang_tua%' AND s.id IS NULL;
-- Hasil: 139
```

### Saran perbaikan

**A. Cocokkan User berdasarkan identitas, bukan hanya relasi** *(inti perbaikan)*

`CsvImportExportService.php` — `ensureImportedTeacherAccount()`:

```php
protected function ensureImportedTeacherAccount(Teacher $teacher): void
{
    $teacher->loadMissing('user');
    $user = $teacher->user;

    // BARU: cari akun lama berdasarkan email deterministik dari NIP
    if ($user === null) {
        $canonicalEmail = $this->canonicalImportedEmail(
            'guru', $teacher->nip ?? $teacher->nik
        );
        $user = User::query()->where('email', $canonicalEmail)->first();

        if ($user !== null) {
            $teacher->forceFill(['user_id' => $user->id])->save();
            $teacher->setRelation('user', $user);
        }
    }

    if ($user === null) {
        // ... buat user baru seperti sekarang
    }
}
```

Kuncinya: email harus **deterministik** dari NIP/NISN — tanpa suffix `.2`, `.3`.
Satu NIP = satu akun, selamanya.

**B. Reset Data harus menangani akun terkait**

Beri admin pilihan eksplisit saat mereset `data_guru` / `data_siswa`:

- **Opsi 1 (disarankan):** ikut hapus akun `users` yang hanya punya role tersebut
- **Opsi 2:** pertahankan akun, tetapi cabut rolenya
  (`UserRoleService::detachTeacherRoles()` sudah tersedia dan dipakai di
  `TeacherService::delete():130` — logika ini tinggal dipakai ulang)

Yang penting: jangan tinggalkan akun ber-role tanpa profil.

**C. Perbaiki pesan error agar tidak menyesatkan**

`AuthService.php:138` — bedakan "tidak punya role" dari "profil hilang":

```php
if ($user->hasRole(UserRole::GURU_MAPEL) && $user->teacherProfile === null) {
    abort(403, 'Akun Anda belum tertaut ke data guru. '
             . 'Hubungi admin untuk menautkan akun dengan data kepegawaian.');
}
```

**D. Perbaikan data yang sudah terlanjur (data repair)**

Buat artisan command untuk menautkan ulang berdasarkan NIP/NISN:

```php
// Contoh untuk satu kasus yang sudah diverifikasi:
// teachers.id=22 (FEBRIANSYAH, NIP 19850220120240110022) → users.id=1053
UPDATE teachers SET user_id = 1053 WHERE id = 22 AND user_id IS NULL;
```

Setelah itu hapus akun duplikat yang tidak terpakai. Perlu kehati-hatian:
sebagian akun lama mungkin masih dirujuk oleh `student_notes.user_id` atau
`student_violations.reported_by_id`.

---

## BUG-02 — Seluruh siswa tidak memiliki kelas 🟠 TINGGI

**320 dari 320 siswa** memiliki `school_class_id = NULL`, padahal terdapat
16 kelas terdaftar (7A–9D).

```sql
SELECT COUNT(*) FROM students WHERE school_class_id IS NULL;  -- 320
SELECT COUNT(*) FROM school_classes;                          -- 16
```

**Dampak:** fitur yang bergantung pada kelas kemungkinan besar tidak berfungsi —
absensi kelas, jadwal pelajaran, portal wali kelas.

**Kemungkinan penyebab:** kolom kelas tidak terisi saat impor.
`CsvImportExportService.php:88` memanggil `resolveSchoolClass()` dengan
`$row['school_class_id']` atau `$row['class_name']` — bila kedua kolom kosong
di CSV, hasilnya `null` dan **diterima tanpa peringatan**
(validasi baris 123 menetapkannya `nullable`).

**Saran:** beri peringatan pada hasil impor bila siswa masuk tanpa kelas,
atau jadikan kolom kelas wajib bila memang bisnisnya mensyaratkan demikian.

---

## BUG-03 — Portal orang tua tidak dapat diakses 🟠 TINGGI

**139 akun orang tua** tidak memiliki anak tertaut, sehingga ditolak oleh:

`AuthService.php:227-230`

```php
if ($portal === 'orang-tua') {
    return $user->hasRole(UserRole::ORANG_TUA) &&
        $user->parentStudents()->exists();     // ← gagal di sini
}
```

Penyebabnya sama dengan BUG-01: `students.parent_user_id` juga
`ON DELETE SET NULL`, dan tabel `students` termasuk yang dikosongkan oleh
grup reset `data_siswa`.

---

## BUG-04 — Satu akun memiliki role kosong 🟡 SEDANG

```
id   | name                      | email                                  | roles
-----|---------------------------|----------------------------------------|------
 339 | SAWITRI HANDAYANI, S.Pd   | guru.19850140120240110014.2@localhost  | []
```

Akun ini kehilangan rolenya sama sekali — kemungkinan efek samping
`detachTeacherRoles()` yang mencabut role tanpa menghapus akunnya
(`TeacherService.php:125-134`). Akun tersebut kini tidak berguna dan
tidak dapat dipulihkan lewat UI.

**Saran:** cegah akun tanpa role sama sekali — hapus akunnya, atau
pertahankan role minimum.

---

## SEC-01 — Password default impor ter-hardcode 🔴 KEAMANAN

`app/Services/CsvImportExportService.php:26`

```php
protected const IMPORT_DEFAULT_PASSWORD = 'ipyakin2026';
```

Password ini berlaku untuk **seluruh 587 akun hasil impor** (guru, siswa,
orang tua) dan tidak pernah dipaksa diganti saat login pertama.

**Risiko:** siapa pun yang mengetahui pola email (`guru.<NIP>@localhost`,
`siswa.<NISN>@localhost`) dapat masuk sebagai pengguna mana pun. Format
NIP/NISN bersifat publik dan mudah ditebak. Ini mencakup akses ke data
pribadi siswa di bawah umur — catatan pelanggaran, absensi, dan data wali.

**Saran:**
1. Paksa ganti password saat login pertama (kolom `password_changed_at` +
   middleware pengalih)
2. Buat password acak per akun, salurkan lewat kanal terpisah
3. Minimal: jadikan password default sebagai variabel environment, bukan konstanta kode

---

## SEC-02 — Kredensial tertanam di dalam image Docker 🔴 KEAMANAN

Nilai berikut terbaca oleh siapa pun yang memiliki image ini
(`docker image inspect` / `docker run --entrypoint cat`):

| Item | Nilai | Lokasi |
|---|---|---|
| Password root MySQL | `rootpassword` | ENV image |
| `APP_KEY` Laravel | `base64:WQ6QDdNn…` | `all-in-one-entrypoint.sh` |
| Password impor | `ipyakin2026` | `CsvImportExportService.php:26` |
| Password akun test | `password123` | `TestAccountSeeder.php` |
| `APP_DEBUG` | `true` | ENV image |

`APP_KEY` yang bocor sangat berbahaya: kunci ini menandatangani cookie sesi
dan mengenkripsi data. Bila image ini pernah dipakai di lingkungan yang dapat
diakses jaringan, **`APP_KEY` wajib dirotasi.**

`APP_DEBUG=true` menampilkan stack trace lengkap beserta isi konfigurasi
kepada siapa pun yang memicu error.

**Saran:**
1. Seluruh kredensial lewat environment variable saat runtime, bukan ditanam di image
2. `APP_DEBUG=false` sebagai default; aktifkan hanya untuk pengembangan
3. Rotasi `APP_KEY` dan seluruh password bila image pernah dibagikan

---

## OBS-01 — Catatan tambahan (bukan bug)

**Data pribadi di dalam image.** Database bawaan berisi 1.052 akun dengan nama
orang, NIK, NISN, catatan pelanggaran, dan data absensi. Bila ini data siswa
sungguhan, image tidak layak dibagikan secara luas — termasuk data anak di bawah
umur. Sebaiknya sediakan image demo dengan data yang benar-benar anonim.

**Arsitektur all-in-one.** MariaDB dan `php artisan serve` berjalan dalam satu
container. Praktis untuk demo, tetapi `all-in-one-entrypoint.sh` hanya menunggu
proses aplikasi (`wait "$APP_PID"`) — bila MariaDB mati, container tetap
dianggap sehat. Untuk produksi, pisahkan container dan gunakan health check.

**`APP_URL` tidak konsisten.** Image meng-`EXPOSE 8000`, sedangkan
`APP_URL=http://localhost:8080`. Karena Laravel memakai `APP_URL` untuk
membangun URL absolut, ketidakcocokan ini bisa menghasilkan tautan yang salah
kecuali port dipetakan `8080:8000`.

---

## Prioritas Perbaikan

| No | Temuan | Prioritas | Perkiraan usaha |
|---|---|---|---|
| BUG-01 | Akun yatim akibat Reset Data | 🔴 Kritis | Sedang |
| SEC-01 | Password impor ter-hardcode | 🔴 Kritis | Kecil |
| SEC-02 | Kredensial di dalam image | 🔴 Kritis | Kecil |
| BUG-02 | Siswa tanpa kelas | 🟠 Tinggi | Kecil |
| BUG-03 | Portal orang tua tidak bisa diakses | 🟠 Tinggi | Ikut BUG-01 |
| BUG-04 | Akun dengan role kosong | 🟡 Sedang | Kecil |

**Urutan yang disarankan:**

1. **BUG-01 bagian A** — hentikan pertambahan akun duplikat setiap impor
2. **BUG-01 bagian B** — cegah Reset Data menghasilkan akun yatim baru
3. **BUG-01 bagian D** — pulihkan 303 akun yang sudah terdampak
4. **SEC-01 & SEC-02** — perbaikan kecil, dampak keamanan besar
5. **BUG-02** — periksa alur impor kelas siswa

---

## Cara Mereproduksi

```bash
docker pull hmbasahay/saruniss:allinone
docker run -d --name saruniss -p 8080:8000 \
  -e APP_URL=http://localhost:8080 \
  -v saruniss-db:/var/lib/mysql \
  hmbasahay/saruniss:allinone
```

Buka `http://localhost:8080`, lalu coba login sebagai guru mana pun dari daftar
87 akun yatim (password: `ipyakin2026`) — misalnya:

```
guru.19850000000000001@localhost
```

Hasil: HTTP 403 *"belum memiliki hak akses ke portal mana pun"*.

Bandingkan dengan akun test yang berfungsi normal:

```
test.admin@sarunis.test  /  password123
```

---

## Lampiran — Query Diagnostik

```sql
-- Ringkasan seluruh temuan dalam satu query
SELECT 'guru tanpa profil' AS temuan, COUNT(*) AS jml
  FROM users u LEFT JOIN teachers t ON t.user_id = u.id
  WHERE u.roles LIKE '%guru_mapel%' AND t.id IS NULL
UNION ALL
SELECT 'siswa tanpa profil', COUNT(*)
  FROM users u LEFT JOIN students s ON s.user_id = u.id
  WHERE u.roles LIKE '%siswa%' AND s.id IS NULL
UNION ALL
SELECT 'ortu tanpa anak', COUNT(*)
  FROM users u LEFT JOIN students s ON s.parent_user_id = u.id
  WHERE u.roles LIKE '%orang_tua%' AND s.id IS NULL
UNION ALL
SELECT 'siswa tanpa kelas', COUNT(*)
  FROM students WHERE school_class_id IS NULL
UNION ALL
SELECT 'akun role kosong', COUNT(*)
  FROM users WHERE roles = '[]' OR roles IS NULL;

-- Daftar akun duplikat per guru
SELECT name, COUNT(*) AS jml_akun,
       GROUP_CONCAT(id ORDER BY id) AS user_ids
FROM users
WHERE roles LIKE '%guru_mapel%'
  AND name NOT REGEXP '^Guru [0-9]+$'
  AND email NOT LIKE '%sarunis.test'
GROUP BY name HAVING jml_akun > 1
ORDER BY jml_akun DESC;

-- Kandidat perbaikan: cocokkan user yatim dengan profil via nama
SELECT u.id AS user_id, u.email, t.id AS teacher_id, t.nip
FROM users u
JOIN teachers t ON t.name = u.name AND t.user_id IS NULL
LEFT JOIN teachers tp ON tp.user_id = u.id
WHERE u.roles LIKE '%guru_mapel%' AND tp.id IS NULL;
```

---

## Catatan Metodologi

Seluruh temuan dalam dokumen ini diverifikasi langsung terhadap database bawaan
image, bukan berdasarkan pembacaan kode semata. Nomor baris merujuk pada berkas
sumber di dalam image (`/var/www/`).

Satu perbaikan data telah diterapkan pada lingkungan uji lokal untuk mengonfirmasi
diagnosis — menautkan `teachers.id=22` ke `users.id=1053` berhasil memulihkan
akses login. Verifikasi dilakukan lewat logika aplikasi sendiri:

```php
$svc->defaultPortal($user);   // sebelumnya: null  →  sesudah: 'guru-mapel'
```

Perbaikan tersebut **hanya diterapkan pada satu akun di lingkungan uji lokal**,
tidak pada lingkungan lain.
