# Deploy ke Railway

Project ini sudah disiapkan untuk deploy dari GitHub ke Railway.

## Yang sudah dikonfigurasi

- `railway.json` memakai Railpack dan menjalankan `npm run build` agar asset Vite dibuat saat deploy.
- `railway/init-app.sh` menjalankan migrasi, membuat storage link, dan membersihkan cache Laravel pada pre-deploy.
- `config/database.php` menerima `DB_URL` dan juga fallback `DATABASE_URL`.
- Healthcheck Railway diarahkan ke endpoint Laravel `/up`.
- `Procfile` tetap tersedia sebagai fallback start command berbasis `php artisan serve`.

## Variables Railway

Buat service app dan database Postgres di Railway, lalu isi Variables app:

```env
APP_NAME="SMP IP YAKIN"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-generated-domain.up.railway.app
APP_KEY=base64:isi-dari-php-artisan-key-generate-show
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

LOG_CHANNEL=stderr
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_URL=${{Postgres.DATABASE_URL}}

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public

INITIAL_ADMIN_EMAIL=admin@example.com
INITIAL_ADMIN_PASSWORD=Password123
INITIAL_ADMIN_NAME="Admin Sekolah"

MAIL_MAILER=log
MAIL_FROM_ADDRESS=hello@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

`INITIAL_ADMIN_EMAIL` dan `INITIAL_ADMIN_PASSWORD` dipakai untuk membuat akun admin pertama saat deploy. Setelah akun berhasil dibuat, hapus variable password atau biarkan tanpa `INITIAL_ADMIN_SYNC_PASSWORD` agar password tidak ditimpa pada deploy berikutnya.

Generate key lokal tanpa mengubah `.env`:

```bash
php artisan key:generate --show
```

## Langkah push

1. Commit perubahan ini.
2. Push ke GitHub.
3. Di Railway, pilih New Project -> Deploy from GitHub repo.
4. Tambahkan Postgres service.
5. Isi Variables di atas.
6. Generate domain di tab Networking, lalu update `APP_URL` dengan domain tersebut dan redeploy.

Catatan: storage file upload di filesystem Railway bersifat ephemeral. Jika foto/dokumen harus permanen, gunakan Railway volume, S3-compatible storage, atau Railway Storage Bucket.
