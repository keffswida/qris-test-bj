# Bank Jatim SNAP API Integration Tester

Aplikasi pengujian konektivitas dan integrasi API Bank Jatim (JatimAPI – SNAP v1.9.4).
PHP Native + MySQL, di-deploy dengan Docker Compose.

Dokumentasi utama:

- `overview.md` — spesifikasi project
- `docs/api-analysis.md` — ringkasan analisis spesifikasi SNAP v1.9.4 (endpoint, autentikasi, signature)
- `Document Specification JatimAPI - SNAP v.1.9.4.pdf` — single source of truth

## Fitur

| Fitur | Keterangan |
| --- | --- |
| Connectivity Test | GET ke Base URL — cek DNS, TCP, TLS (SSL verification selalu aktif) |
| Get Access Token | `POST /access_token/v1.0/b2b` dengan Signature Access Token |
| Signature Test | Membuat Signature Access Token & Signature Service secara lokal |
| QR MPM Generate | End-to-end: token → signature → `POST /api/v1.0/qr/qr-mpm-generate` |
| Riwayat Pengujian | Request/response tersanitasi tersimpan di MySQL (`api_test_logs`) |

Metode signature: **symmetric** (HMAC-SHA512) atau **asymmetric** (SHA256withRSA)
sesuai kesepakatan dengan Bank Jatim (`BANK_JATIM_SIGNATURE_METHOD`).

## Prasyarat

- Docker Engine + Docker Compose Plugin (deployment VPS)
- Atau: PHP 8.3 (ekstensi `curl`, `openssl`, `pdo_mysql`) + MySQL 8.0 (development lokal)

## Konfigurasi (.env)

1. Salin `.env.example` menjadi `.env`.
2. Isi kredensial yang diperoleh dari Bank Jatim:

   ```env
   BANK_JATIM_BASE_URL=        # base URL API Bank Jatim (sandbox/production)
   BANK_JATIM_CLIENT_ID=
   BANK_JATIM_CLIENT_SECRET=
   BANK_JATIM_SIGNATURE_METHOD=symmetric   # atau asymmetric
   BANK_JATIM_PRIVATE_KEY_PATH=storage/keys/private.pem   # hanya untuk asymmetric
   BANK_JATIM_PARTNER_ID=      # X-PARTNER-ID
   ```

3. Ganti password database dan password dashboard (`APP_AUTH_PASSWORD`) dengan
   nilai yang kuat.
4. `.env` **wajib** di-gitignore dan tidak boleh dimasukkan ke Docker image
   (sudah ditangani: `.gitignore` + `.dockerignore` + mount read-only).

Private key (asymmetric) disimpan di `storage/keys/` — di luar direktori `public/`,
tidak dapat diakses melalui web server.

## Menjalankan dengan Docker

Build dan jalankan:

```bash
docker compose up -d --build
```

Aplikasi tersedia di `http://localhost:8080` (port sesuai `APP_PORT`).

Perintah operasional:

| Aksi | Perintah |
| --- | --- |
| Status container | `docker compose ps` |
| Log aplikasi | `docker compose logs -f app` |
| Log database | `docker compose logs -f db` |
| Hentikan aplikasi | `docker compose down` |
| Rebuild setelah ubah Dockerfile | `docker compose up -d --build` |
| Masuk ke container aplikasi | `docker compose exec app bash` |
| Masuk ke MySQL | `docker compose exec db mysql -u root -p` |
| Backup database | `docker compose exec db sh -c 'exec mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > backup.sql` |
| Restore database | `docker compose exec -T db sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < backup.sql` |

Catatan: jangan gunakan `docker compose down -v` pada prosedur normal — flag `-v`
menghapus named volume database.

## Menjalankan tanpa Docker (development)

1. Siapkan MySQL dan buat database + tabel:

   ```bash
   mysql -u root -p < database/schema.sql
   ```

2. Sesuaikan `.env` (contoh lokal sudah disediakan di `.env`):
   `DB_HOST=127.0.0.1`, `DB_USERNAME=root`, `DB_PASSWORD=`.

3. Jalankan PHP built-in server dengan document root `public/`:

   ```bash
   php -S localhost:8080 -t public
   ```

4. Buka `http://localhost:8080`.

## Deployment VPS

Target: `https://qrisbj.lksbarokah255.my.id`

1. Pastikan VPS memiliki Docker Engine dan Docker Compose Plugin.
2. Buat DNS **A record** `qrisbj.lksbarokah255.my.id` → IPv4 publik VPS.
3. Upload project ke VPS, isi `.env` (jangan commit `.env`), lalu:

   ```bash
   docker compose up -d --build
   ```

4. Reverse proxy:
   - Jika VPS sudah menjalankan Nginx (atau proxy lain), arahkan request domain ke
     port aplikasi (`APP_PORT`, default 8080).
   - **Jangan** mengubah/merusak konfigurasi website lain yang sudah berjalan di VPS.
5. SSL/HTTPS:
   - Pasang sertifikat valid pada domain, arahkan HTTP → HTTPS.
   - Pastikan mekanisme renewal berjalan (mis. certbot/Let's Encrypt).
6. Firewall: hanya buka port 80/443 ke publik; port MySQL tidak diekspos sama sekali
   (sudah diatur di docker-compose).
7. Pastikan server VPS tersinkronisasi NTP — autentikasi dan signature bergantung
   pada timestamp yang akurat.
8. Dashboard diakses melalui `https://qrisbj.lksbarokah255.my.id`.

## Keamanan

- SSL verification ke API Bank Jatim selalu aktif (tidak dapat dinonaktifkan).
- Credential, access token, dan private key tidak pernah masuk log atau database.
- Payload yang disimpan di riwayat otomatis disanitasi (token/signature disamarkan).
- Dashboard dilindungi password (`APP_AUTH_PASSWORD`) + CSRF protection.
- File `.env`, `src/`, `storage/`, dan `database/` tidak dapat diakses via HTTP
  (document root hanya `public/`).

## Struktur Project

```text
public/           index.php, assets/, .htaccess (document root web server)
src/
  Config/         Config.php (loader .env)
  Database/       Database.php (PDO)
  Http/           HttpClient.php, HttpClientResponse.php (cURL)
  Security/       Auth.php (login + CSRF)
  Services/       SignatureService.php, TokenService.php, BankJatimService.php
  Repositories/   ApiTestLogRepository.php
  Helpers/        autoload.php, Logger.php, ResponseHelper.php
  Views/          helpers.php, login.php, dashboard.php
storage/          logs/ (aplikasi), keys/ (private key)
database/         schema.sql
docker/           php/Dockerfile, apache/vhost.conf
docs/             api-analysis.md
```
