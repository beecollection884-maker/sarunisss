# Sarunis

Sarunis adalah aplikasi portal sekolah berbasis Laravel untuk mengelola data akademik dasar, jadwal ajar, dan presensi siswa. Aplikasi ini menyediakan beberapa portal sesuai peran pengguna: Admin Sekolah, Guru Mapel, dan Siswa. Guru mapel yang ditunjuk sebagai wali kelas oleh admin akan mendapatkan akses ke portal wali kelas secara otomatis.

## Stack

- PHP 8.3
- Laravel 13
- SQLite/MySQL/PostgreSQL sesuai konfigurasi `.env`
- Vite 8
- Tailwind CSS 4
- PHPUnit 12

## Fitur Utama

### Autentikasi dan Portal

- Login umum melalui `/auth` dan `POST /login`.
- Login per portal melalui `POST /login/{portal}`.
- Logout melalui `POST /logout`.
- Endpoint profil user aktif melalui `GET /me`.
- Recovery kata sandi:
    - Kirim kode verifikasi melalui `POST /auth/verifikasi-email`.
    - Verifikasi kode melalui `POST /auth/verifikasi-kode`.
    - Reset kata sandi melalui `POST /auth/lupa-kata-sandi`.
- Role portal:
    - `admin` - Administrator sekolah
    - `guru_mapel` - Guru pengajar mata pelajaran
    - `siswa` - Siswa sekolah
- Portal `walikelas` tersedia untuk guru mapel yang ditunjuk sebagai wali kelas oleh admin (memiliki homeroom class assignment).
- Portal gabungan `guru-walikelas` untuk guru mapel yang sekaligus ditunjuk sebagai wali kelas.
- Redirect otomatis dari `/` ke dashboard default sesuai role.

### Admin Sekolah

- Dashboard admin di `/admin/dashboard`.
- Manajemen data siswa:
    - Halaman `/admin/data-siswa`.
    - CRUD API `/admin/siswa`.
    - Data NIK, NISN, gender, tanggal lahir, kontak, alamat, detail biodata, kelas, akun user, dan foto.
- Manajemen data guru:
    - Halaman `/admin/data-guru`.
    - CRUD API `/admin/guru`.
    - Data NIK, NIP, profil guru, status pegawai, jabatan, pendidikan, kontak, alamat, dan foto.
- Manajemen data kelas:
    - Halaman `/admin/data-kelas`.
    - CRUD API `/admin/kelas`.
    - Data nama kelas, level, tahun ajaran, guru wali kelas (homeroom teacher), siswa, dan mata pelajaran.
- Manajemen mata pelajaran:
    - Halaman `/admin/mata-pelajaran`.
    - CRUD API `/admin/mapel`.
    - Data kode mapel, nama, jam pelajaran, guru pengampu, kelas, dan deskripsi.
- Manajemen jadwal ajar:
    - CRUD API `/admin/jadwal-ajar`.
    - Validasi bentrok jadwal guru dan kelas.
- Ploting kelas:
    - Endpoint `PUT /admin/kelas/{schoolClass}/ploting`.
    - Mengatur guru wali kelas (homeroom teacher), siswa, dan mata pelajaran kelas.
- Manajemen pengguna:
    - Halaman `/admin/manajemen-pengguna`.
    - CRUD API `/admin/pengguna`.
    - Mengatur nama, email, password, status verifikasi, dan role.
    - Menghubungkan akun ke profil guru atau siswa.
    - Proteksi agar admin terakhir tidak bisa dihapus atau kehilangan role admin.
- Pengaturan aplikasi:
    - Halaman `/admin/pengaturan`.
    - CRUD API `/admin/setting`.
    - Menyimpan konfigurasi key-value seperti nama sekolah, tahun ajaran, dan kontak.
    - `school_name` dan `academic_year` dipakai di dashboard/halaman direktori.
- Catatan siswa:
    - Halaman `/admin/catatan-siswa`.
    - CRUD API `/admin/catatan`.
    - Mencatat pembinaan, kategori catatan, tindak lanjut, dan status selesai.
    - Ringkasan catatan terbuka tampil di dashboard admin dan guru wali kelas.

### Guru Mapel

- Dashboard di `/guru-mapel/dashboard`.
- Lihat jadwal ajar melalui `GET /guru-mapel/jadwal-ajar`.
- Lihat siswa yang diajar melalui `GET /guru-mapel/siswa`.
- Input absensi mapel melalui `POST /guru-mapel/absensi-mapel`.
- Rekap absensi mapel melalui `GET /guru-mapel/rekap-absensi-mapel`.
- Filter rekap berdasarkan guru, kelas, mapel, jadwal ajar, siswa, tanggal, dan rentang tanggal.

### Wali Kelas

Guru mapel yang ditunjuk sebagai wali kelas oleh admin mendapatkan akses ke portal wali kelas. Penunjukan dilakukan melalui fitur ploting kelas di halaman admin.

- Dashboard di `/walikelas/dashboard`.
- Lihat kelas perwalian melalui `GET /walikelas/kelas`.
- Lihat siswa perwalian melalui `GET /walikelas/siswa`.
- Kelola catatan siswa perwalian melalui `/walikelas/catatan-siswa` dan API `/walikelas/catatan`.
- Input absensi kelas melalui `POST /walikelas/absensi-kelas`.
- Rekap absensi kelas melalui `GET /walikelas/rekap-absensi-kelas`.
- Filter rekap berdasarkan guru, kelas, siswa, tanggal, dan rentang tanggal.

### Siswa

- Dashboard di `/siswa/dashboard`.
- Lihat jadwal sekolah melalui `GET /siswa/jadwal-sekolah`.
- Lihat daftar hadir kelas melalui `GET /siswa/daftar-hadir-kelas`.
- Rekap kehadiran pribadi.

### Absensi

- Status absensi:
    - `hadir`
    - `izin`
    - `sakit`
    - `alpha`
- Absensi mapel berdasarkan jadwal ajar.
- Absensi kelas berdasarkan kelas perwalian.
- Catatan per siswa pada setiap record absensi.
- Data absensi menggunakan pola update-or-create untuk tanggal dan siswa yang sama.

## Instalasi

1. Install dependency PHP.

```bash
composer install
```

2. Salin file environment dan buat app key.

```bash
cp .env.example .env
php artisan key:generate
```

3. Atur koneksi database di `.env`.

Contoh SQLite:

```env
DB_CONNECTION=sqlite
```

Pastikan file database sudah ada jika memakai SQLite:

```bash
touch database/database.sqlite
```

4. Jalankan migrasi dan seeder.

```bash
php artisan migrate --seed
```

5. Install dependency frontend dan build asset.

```bash
npm install
npm run build
```

6. Jalankan aplikasi.

```bash
php artisan serve
```

Mode pengembangan penuh juga tersedia dari script Composer:

```bash
composer run dev
```

## Akun Demo

Seeder menyediakan akun demo berikut. Semua password default adalah `password`.

| Portal            | Email                     | Catatan                                     |
| ----------------- | ------------------------- | ------------------------------------------- |
| Admin Sekolah     | `admin@sarunis.test`      | Administrator                               |
| Guru Mapel        | `guru.mapel@sarunis.test` | Hanya guru mapel tanpa wali kelas           |
| Guru + Wali Kelas | `guru.wali@sarunis.test`  | Guru mapel yang ditunjuk sebagai wali kelas |
| Siswa             | `siswa@sarunis.test`      | Siswa biasa                                 |

## Endpoint Penting

| Area                | Method                    | Path                                 |
| ------------------- | ------------------------- | ------------------------------------ |
| Auth                | GET                       | `/auth`                              |
| Auth                | POST                      | `/login`                             |
| Auth                | POST                      | `/login/{portal}`                    |
| Auth                | GET                       | `/auth/portals`                      |
| Auth Recovery       | POST                      | `/auth/verifikasi-email`             |
| Auth Recovery       | POST                      | `/auth/verifikasi-kode`              |
| Auth Recovery       | POST                      | `/auth/lupa-kata-sandi`              |
| Auth                | GET                       | `/me`                                |
| Auth                | POST                      | `/logout`                            |
| Admin               | GET                       | `/admin/dashboard`                   |
| Admin Siswa         | API Resource              | `/admin/siswa`                       |
| Admin Guru          | API Resource              | `/admin/guru`                        |
| Admin Kelas         | API Resource              | `/admin/kelas`                       |
| Admin Mapel         | API Resource              | `/admin/mapel`                       |
| Admin Jadwal Ajar   | API Resource              | `/admin/jadwal-ajar`                 |
| Admin Ploting       | PUT                       | `/admin/kelas/{schoolClass}/ploting` |
| Admin Pengguna      | API Resource              | `/admin/pengguna`                    |
| Admin Pengaturan    | API Resource              | `/admin/setting`                     |
| Admin Catatan Siswa | API Resource              | `/admin/catatan`                     |
| Guru Mapel          | GET                       | `/guru-mapel/jadwal-ajar`            |
| Guru Mapel          | GET                       | `/guru-mapel/siswa`                  |
| Guru Mapel          | GET                       | `/guru-mapel/rekap-absensi-mapel`    |
| Guru Mapel          | POST                      | `/guru-mapel/absensi-mapel`          |
| Wali Kelas          | GET                       | `/walikelas/kelas`                   |
| Wali Kelas          | GET                       | `/walikelas/siswa`                   |
| Wali Kelas          | GET/POST/PUT/PATCH/DELETE | `/walikelas/catatan`                 |
| Wali Kelas          | GET                       | `/walikelas/catatan-siswa`           |
| Wali Kelas          | GET                       | `/walikelas/rekap-absensi-kelas`     |
| Wali Kelas          | POST                      | `/walikelas/absensi-kelas`           |
| Siswa               | GET                       | `/siswa/jadwal-sekolah`              |
| Siswa               | GET                       | `/siswa/daftar-hadir-kelas`          |

## Testing

Jalankan seluruh test:

```bash
composer test
```

Atau langsung dengan Artisan:

```bash
php artisan test
```

## Catatan Pengembangan

- Kode verifikasi recovery dikirim melalui mailer Laravel. Pada konfigurasi development saat ini `MAIL_MAILER=log`, sehingga kode dapat dicek di log aplikasi.
- Endpoint auth recovery memakai rate limit khusus `auth-recovery`.
- Password reset dan manajemen pengguna mewajibkan minimal 8 karakter dengan huruf dan angka.
- Setelah menarik perubahan baru, jalankan `php artisan migrate` agar tabel recovery, pengaturan, dan catatan siswa tersedia.

README ini mendokumentasikan kondisi fitur berdasarkan route, controller, service, request validation, migration, seeder, dan view yang ada di project saat ini.
