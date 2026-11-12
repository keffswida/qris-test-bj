# Overview — Bank Jatim SNAP API Integration Tester

## 1. Project Overview

Aplikasi sederhana untuk menguji konektivitas dan integrasi API Bank Jatim (QRIS) melalui server yang telah disediakan.

Aplikasi ini berfungsi sebagai integration testing tool untuk memastikan server dapat berkomunikasi dengan API Bank Jatim, khususnya pada proses autentikasi dan pembuatan signature.

Implementasi menggunakan PHP Native tanpa framework tambahan, dengan MySQL sebagai database untuk menyimpan riwayat pengujian dan informasi teknis yang diperlukan.

### Tujuan Utama

1. Menguji apakah server dapat terhubung ke API Bank Jatim.
2. Mengimplementasikan mekanisme autentikasi sesuai spesifikasi SNAP Bank Jatim.
3. Menguji proses mendapatkan access token.
4. Menguji proses pembuatan signature.
5. Menyediakan informasi request dan response untuk kebutuhan debugging.
6. Menyimpan riwayat pengujian agar hasil komunikasi API dapat ditelusuri.

## 2. Technology Stack

| Komponen          | Teknologi                             |
| ----------------- | ------------------------------------- |
| Backend           | PHP Native                            |
| Database          | MySQL                                 |
| HTTP Client       | PHP cURL                              |
| Data Exchange     | JSON                                  |
| API Protocol      | REST API                              |
| Authentication    | Mengikuti spesifikasi Bank Jatim SNAP |
| Digital Signature | Mengikuti spesifikasi Bank Jatim SNAP |
| Configuration     | Environment variables (`.env`)        |

### Technical Constraints

- Tidak menggunakan Laravel, CodeIgniter, atau framework PHP lainnya.
- Tidak menggunakan frontend framework.
- Tidak menambahkan dependency yang tidak diperlukan.
- Gunakan PHP cURL untuk komunikasi HTTP.
- Gunakan PDO dengan prepared statements untuk komunikasi database.
- Gunakan struktur kode modular dan sederhana.
- Pisahkan konfigurasi, business logic, HTTP client, dan presentation.
- Jangan melakukan overengineering.

## 3. Primary Reference Document

Dokumen berikut merupakan landasan utama implementasi:

**Document Specification JatimAPI - SNAP v.1.9.4.pdf**

Lokasi dokumen:

```text
/project-root/
├── Document Specification JatimAPI - SNAP v.1.9.4.pdf
├── overview.md
└── ...
```

AI CLI wajib membaca dan menganalisis dokumen tersebut sebelum melakukan implementasi.

### Document Analysis Requirements

Sebelum menulis kode, lakukan hal berikut:

1. Pastikan file PDF tersedia di root project.
2. Baca seluruh isi dokumen, bukan hanya halaman awal.
3. Identifikasi spesifikasi API yang berkaitan dengan autentikasi dan QRIS.
4. Identifikasi seluruh endpoint yang dibutuhkan.
5. Identifikasi HTTP method setiap endpoint.
6. Identifikasi format request dan response.
7. Identifikasi mekanisme autentikasi.
8. Identifikasi algoritma dan format digital signature.
9. Identifikasi header wajib dan parameter yang diperlukan.
10. Identifikasi aturan timestamp, timezone, dan format tanggal.
11. Identifikasi HTTP status code dan business response code.
12. Identifikasi ketentuan error handling dan batasan request.

### Important Rules

- Dokumen PDF merupakan single source of truth untuk implementasi integrasi Bank Jatim.
- Jangan mengarang endpoint, parameter, header, algoritma signature, maupun response.
- Jangan menggunakan spesifikasi SNAP dari bank lain sebagai pengganti spesifikasi Bank Jatim.
- Jangan mengasumsikan bahwa mekanisme autentikasi atau signature sama dengan implementasi SNAP lainnya.
- Jika terdapat informasi yang tidak tersedia atau ambigu, dokumentasikan sebagai pertanyaan atau asumsi yang perlu dikonfirmasi.
- Jangan melanjutkan implementasi bagian yang belum memiliki dasar spesifikasi yang cukup.
- Jika PDF tidak ditemukan atau tidak dapat dibaca, hentikan implementasi integrasi dan informasikan kendalanya.

Setelah analisis selesai, buat dokumentasi ringkas mengenai endpoint, authentication flow, signature generation, request structure, dan response structure yang akan diimplementasikan.

## 4. Core Features

### 4.1. API Connectivity Test

Memastikan server dapat berkomunikasi dengan endpoint Bank Jatim.

Fitur:

- Mengirim HTTP request ke endpoint yang ditentukan dalam dokumen.
- Memeriksa konektivitas jaringan.
- Memeriksa DNS resolution dan koneksi SSL/TLS melalui error cURL.
- Menampilkan HTTP status code.
- Menampilkan informasi response.
- Menampilkan informasi error apabila request gagal.

Informasi yang ditampilkan:

- Endpoint tujuan.
- HTTP method.
- HTTP status code.
- Response time.
- Connection status.
- Error message jika terjadi kegagalan.

Catatan: Keberhasilan koneksi TCP/TLS tidak otomatis berarti autentikasi API berhasil.

### 4.2. Get Access Token

Mengimplementasikan proses mendapatkan access token dari Bank Jatim sesuai mekanisme autentikasi yang ditentukan dalam dokumen SNAP v1.9.4.

Fitur:

- Mengirim request autentikasi.
- Menggunakan credential dan parameter sesuai spesifikasi.
- Membuat header dan signature jika diwajibkan.
- Memproses response dari Bank Jatim.
- Menampilkan status keberhasilan atau kegagalan.
- Menampilkan informasi masa berlaku token jika tersedia.
- Menangani token yang expired.

Ketentuan:

- Jangan mengasumsikan format token request.
- Jangan mengasumsikan endpoint token.
- Jangan mengasumsikan format credential.
- Jangan menyimpan access token dalam bentuk plaintext ke database.
- Simpan token di memory selama proses pengujian jika memungkinkan.
- Credential dan token tidak boleh muncul dalam log.

### 4.3. Generate Signature

Mengimplementasikan proses pembuatan digital signature sesuai spesifikasi Bank Jatim.

Fitur:

- Mengambil konfigurasi credential atau key yang dibutuhkan.
- Menghasilkan signature berdasarkan algoritma yang ditentukan.
- Mengikuti format timestamp dan string-to-sign.
- Mengikuti aturan encoding yang ditentukan.
- Menghasilkan signature untuk kebutuhan autentikasi maupun request API sesuai spesifikasi.

Ketentuan:

- Jangan mengasumsikan algoritma RSA, HMAC, SHA, atau algoritma lainnya.
- Identifikasi terlebih dahulu apakah terdapat lebih dari satu jenis signature.
- Bedakan mekanisme signature untuk mendapatkan access token dan mekanisme signature untuk transaksi jika spesifikasi mengharuskannya.
- Jangan menggunakan private key yang sama untuk kebutuhan berbeda tanpa dasar spesifikasi.
- Jangan menyimpan private key atau secret dalam database.
- Jangan menampilkan private key atau secret pada halaman aplikasi maupun log.

### 4.4. API Test Dashboard

Menyediakan halaman sederhana untuk menjalankan dan melihat hasil pengujian.

Informasi dashboard:

| Informasi         | Deskripsi                       |
| ----------------- | ------------------------------- |
| Connection Status | Status konektivitas API         |
| Token Status      | Status pengambilan access token |
| Signature Status  | Status proses signature         |
| Last Test         | Waktu pengujian terakhir        |
| HTTP Status       | HTTP status code                |
| Response Time     | Durasi request                  |
| Error Information | Informasi kegagalan jika ada    |

Dashboard cukup menggunakan HTML, CSS, dan JavaScript sederhana.

Tidak perlu membuat dashboard kompleks.

### 4.5. Request & Response History

Menyimpan riwayat komunikasi API untuk memudahkan proses debugging.

Informasi yang disimpan:

- Jenis pengujian.
- Endpoint.
- HTTP method.
- HTTP status code.
- Request timestamp.
- Response timestamp.
- Response time.
- Request payload yang sudah disanitasi.
- Response payload yang sudah disanitasi.
- Status pengujian.
- Error message jika tersedia.

Informasi sensitif wajib disamarkan atau dihilangkan, termasuk:

- Client secret.
- Private key.
- Access token.
- Signature.
- Password.
- Credential lainnya.

Jangan menyimpan data sensitif hanya demi mempermudah debugging.

## 5. Application Flow

### 5.1. Get Token Flow

Alur berikut merupakan gambaran konseptual. Detail teknis wajib mengikuti PDF.

```text
User
  |
  v
Dashboard
  |
  v
Get Token
  |
  v
Load Configuration
  |
  v
Prepare Authentication Request
  |
  v
Generate Required Signature
  |
  v
Send Request to Bank Jatim
  |
  v
Process Response
  |
  +---- Success
  |       |
  |       v
  |   Display Token Status
  |
  +---- Failed
          |
          v
      Display Error
  |
  v
Save Sanitized Test History
```

### 5.2. Signature Generation Flow

```text
User
  |
  v
Generate Signature
  |
  v
Load Required Configuration
  |
  v
Prepare String-to-Sign
  |
  v
Apply Specified Algorithm
  |
  v
Generate Signature
  |
  v
Display Signature Status
```

Signature yang ditampilkan harus mengikuti kebutuhan pengujian dan tidak boleh membocorkan private key maupun secret.

### 5.3. Connectivity Test Flow

```text
User
  |
  v
Run Connection Test
  |
  v
Send HTTP Request
  |
  v
Receive Response
  |
  +---- Connected
  |       |
  |       v
  |   Display HTTP Result
  |
  +---- Failed
          |
          v
      Display Connection Error
  |
  v
Save Test History
```

## 6. Database Requirements

Database menggunakan MySQL.

Database disiapkan untuk mendukung konfigurasi pengujian dan riwayat komunikasi API.

### Suggested Tables

#### 6.1. api_test_logs

Menyimpan riwayat pengujian API.

| Column           | Type        | Description        |
| ---------------- | ----------- | ------------------ |
| id               | BIGINT      | Primary key        |
| test_type        | VARCHAR(50) | Jenis pengujian    |
| endpoint         | TEXT        | Endpoint tujuan    |
| http_method      | VARCHAR(10) | HTTP method        |
| http_status      | INT         | HTTP status code   |
| request_payload  | JSON        | Sanitized request  |
| response_payload | JSON        | Sanitized response |
| response_time_ms | INT         | Response duration  |
| status           | VARCHAR(20) | success / failed   |
| error_message    | TEXT        | Error information  |
| created_at       | TIMESTAMP   | Waktu pengujian    |

Catatan:

- Schema dapat disesuaikan dengan kebutuhan implementasi.
- Jangan menyimpan data sensitif.
- Jika terdapat data yang tidak tersedia, gunakan NULL sesuai kebutuhan.
- Jangan menyimpan raw HTTP request atau response yang mengandung credential.

#### 6.2. Database Constraints

- Gunakan primary key.
- Gunakan tipe data yang sesuai.
- Gunakan prepared statements.
- Hindari penyimpanan data yang tidak diperlukan.
- Gunakan index pada kolom yang sering digunakan untuk pencarian riwayat.

Tidak perlu membuat database schema yang kompleks.

## 7. Configuration Requirements

Seluruh konfigurasi koneksi dan credential harus dikelola melalui file `.env`.

Contoh struktur konfigurasi:

```env
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=test-qris-bj
DB_USERNAME=root
DB_PASSWORD=

BANK_JATIM_BASE_URL=
BANK_JATIM_CLIENT_ID=
BANK_JATIM_CLIENT_SECRET=
BANK_JATIM_PRIVATE_KEY_PATH=

BANK_JATIM_ENVIRONMENT=sandbox
BANK_JATIM_TIMEOUT=30
```

Catatan:

- Nama dan kebutuhan variabel harus disesuaikan dengan dokumen Bank Jatim.
- Contoh di atas bukan representasi final seluruh parameter autentikasi.
- Jangan mengasumsikan keberadaan private key apabila spesifikasi tidak membutuhkannya.
- Gunakan placeholder untuk konfigurasi yang belum tersedia.
- `.env` wajib dimasukkan ke `.gitignore`.
- Sediakan `.env.example` tanpa credential asli.
- Private key harus disimpan di lokasi yang tidak dapat diakses secara publik melalui web server.
- Pastikan konfigurasi production dan sandbox tidak tertukar.

## 8. Error Handling & Test Scenarios

Implementasi wajib menangani positive case, negative case, dan edge case.

### 8.1. Positive Cases

| Scenario                              | Expected Result                |
| ------------------------------------- | ------------------------------ |
| Server berhasil terhubung ke endpoint | Connection berhasil            |
| Credential valid                      | Authentication berhasil        |
| Request token valid                   | Access token diterima          |
| Signature berhasil dibuat             | Signature dihasilkan           |
| Response API valid                    | Response diproses dengan benar |
| Riwayat pengujian berhasil disimpan   | Log tersedia di database       |
| API memberikan response sukses        | Status sukses ditampilkan      |

### 8.2. Negative Cases

| Scenario                      | Expected Result                                |
| ----------------------------- | ---------------------------------------------- |
| Credential salah              | Authentication gagal                           |
| Client ID tidak valid         | Error ditampilkan                              |
| Signature tidak valid         | Request ditolak atau gagal sesuai response API |
| Endpoint tidak ditemukan      | HTTP 404 ditangani                             |
| Request tidak valid           | HTTP 400 ditangani                             |
| Unauthorized                  | HTTP 401 ditangani                             |
| Forbidden                     | HTTP 403 ditangani                             |
| Server API mengalami gangguan | Error ditampilkan                              |
| Database tidak dapat diakses  | Error ditangani tanpa merusak aplikasi         |
| Request mengalami timeout     | Timeout ditangani                              |
| SSL certificate tidak valid   | Koneksi ditolak dan error dicatat              |

### 8.3. Edge Cases

| Scenario                                  | Expected Result                                       |
| ----------------------------------------- | ----------------------------------------------------- |
| Response body kosong                      | Tidak menyebabkan fatal error                         |
| Response bukan JSON valid                 | Parsing error ditangani                               |
| HTTP 200 tetapi business response gagal   | Status mengikuti business response                    |
| HTTP 4xx atau 5xx                         | Error ditangani sesuai response                       |
| Token expired                             | Status expired ditangani                              |
| Token response tidak memiliki field wajib | Response dianggap tidak valid                         |
| Timestamp tidak sesuai format             | Validasi gagal                                        |
| Server memiliki perbedaan waktu           | Timestamp ditangani sesuai ketentuan SNAP             |
| API tidak memberikan response             | Timeout atau connection error ditampilkan             |
| API memberikan response sangat lambat     | Request timeout diterapkan                            |
| Private key tidak ditemukan               | Proses signature dihentikan                           |
| Private key tidak dapat dibaca            | Error ditampilkan                                     |
| Signature generation gagal                | Request tidak dikirim                                 |
| Request dikirim berulang kali             | Tidak menyebabkan kebocoran credential atau data      |
| Logging gagal                             | Kegagalan logging tidak menutupi hasil komunikasi API |

### 8.4. General Error Handling Rules

- Gunakan try-catch pada operasi yang membutuhkan penanganan exception.
- Validasi konfigurasi sebelum mengirim request.
- Terapkan timeout pada HTTP request.
- Jangan menonaktifkan SSL verification.
- Bedakan connection error, HTTP error, dan business error.
- Jangan menampilkan stack trace atau credential kepada pengguna.
- Simpan informasi error yang aman untuk kebutuhan debugging.
- Hindari retry otomatis yang tidak diperlukan.
- Jangan melakukan retry terhadap request yang berpotensi menghasilkan transaksi ganda tanpa aturan idempotency yang jelas.

## 9. Security Requirements

Meskipun aplikasi hanya digunakan untuk pengujian, keamanan tetap wajib diperhatikan.

1. Gunakan HTTPS untuk komunikasi dengan Bank Jatim.
2. SSL verification harus aktif.
3. Jangan hardcode credential.
4. Jangan expose file `.env`.
5. Jangan expose private key melalui web server.
6. Jangan menyimpan access token dan signature secara plaintext di log.
7. Gunakan PDO prepared statements.
8. Validasi seluruh input pengguna.
9. Escape output HTML untuk mencegah XSS.
10. Lindungi halaman pengujian dengan autentikasi sederhana apabila dapat diakses melalui jaringan publik.
11. Gunakan CSRF protection untuk operasi yang mengubah state.
12. Batasi informasi teknis yang ditampilkan pada production environment.

## 10. Suggested Project Structure

Gunakan struktur sederhana dan mudah dikembangkan.

```text
root-project/
│
├── public/
│   ├── index.php
│   ├── assets/
│   │   ├── css/
│   │   └── js/
│   └── .htaccess
│
├── src/
│   ├── Config/
│   │   └── config.php
│   │
│   ├── Database/
│   │   └── Database.php
│   │
│   ├── Services/
│   │   ├── BankJatimService.php
│   │   ├── TokenService.php
│   │   └── SignatureService.php
│   │
│   ├── Http/
│   │   └── HttpClient.php
│   │
│   ├── Repositories/
│   │   └── ApiTestLogRepository.php
│   │
│   └── Helpers/
│       └── ResponseHelper.php
│
├── storage/
│   └── logs/
│
├── database/
│   └── schema.sql
│
├── docs/
│   └── api-analysis.md
│
├── Document Specification JatimAPI - SNAP v.1.9.4.pdf
├── .env
├── .env.example
├── .gitignore
└── overview.md
```

Struktur di atas merupakan rekomendasi awal. AI CLI boleh menyesuaikan struktur apabila terdapat alasan teknis yang jelas, tetapi tetap pertahankan kesederhanaan project.

## 11. Implementation Scope

### In Scope

- Membaca dan menganalisis PDF spesifikasi Bank Jatim.
- Membuat dokumentasi hasil analisis API.
- Membuat koneksi database MySQL.
- Membuat HTTP client menggunakan PHP cURL.
- Mengimplementasikan authentication flow.
- Mengimplementasikan get access token.
- Mengimplementasikan signature generation.
- Mengimplementasikan connectivity test.
- Menampilkan hasil pengujian pada dashboard.
- Menyimpan sanitized request dan response history.
- Menangani positive, negative, dan edge cases.
- Menyiapkan konfigurasi sandbox dan production.

### Out of Scope

- Implementasi transaksi QRIS secara menyeluruh.
- Payment processing.
- Settlement.
- Refund.
- Payment notification atau callback.
- Rekonsiliasi transaksi.
- Integrasi dengan sistem ERP.
- Multi-user management.
- Role-based access control yang kompleks.
- Dashboard analytics.
- Arsitektur microservices.
- Implementasi framework PHP.

Fitur di luar scope hanya boleh ditambahkan apabila memang dibutuhkan oleh proses pengujian dan didukung oleh spesifikasi.

## 12. Development Workflow

Implementasi dilakukan secara bertahap.

### Phase 1 — Document Analysis

- Periksa keberadaan PDF.
- Baca dan analisis spesifikasi.
- Identifikasi endpoint dan authentication flow.
- Identifikasi signature algorithm.
- Buat `docs/api-analysis.md`.
- Catat informasi yang belum jelas.

**Output:** Dokumentasi teknis berdasarkan spesifikasi Bank Jatim.

### Phase 2 — Project Foundation

- Siapkan struktur project.
- Siapkan konfigurasi `.env`.
- Siapkan koneksi MySQL.
- Buat HTTP client.
- Siapkan logging.

**Output:** Project dapat dijalankan dan database terhubung.

### Phase 3 — Signature Implementation

- Implementasikan algoritma signature sesuai PDF.
- Validasi format string-to-sign.
- Validasi timestamp.
- Uji signature generation.
- Tangani error pada proses signing.

**Output:** Signature dapat dihasilkan dan diuji secara lokal.

### Phase 4 — Authentication Implementation

- Implementasikan authentication request.
- Implementasikan get access token.
- Proses response.
- Tangani token expiration.
- Uji positive dan negative cases.

**Output:** Server dapat melakukan proses autentikasi sesuai spesifikasi.

### Phase 5 — Dashboard & History

- Buat dashboard sederhana.
- Tambahkan tombol connectivity test.
- Tambahkan tombol get token.
- Tambahkan fitur signature test.
- Tampilkan hasil request dan response.
- Tampilkan riwayat pengujian.

**Output:** Pengujian dapat dilakukan melalui browser.

### Phase 6 — Testing & Deployment

- Uji seluruh positive cases.
- Uji negative cases.
- Uji edge cases.
- Pastikan konfigurasi production aman.
- Deploy ke server.
- Uji konektivitas dari server yang akan digunakan untuk integrasi Bank Jatim.

**Output:** Aplikasi siap digunakan untuk pengujian konektivitas dan autentikasi Bank Jatim.

## 13. Acceptance Criteria

Project dinyatakan selesai apabila:

- [ ] AI CLI berhasil membaca seluruh PDF spesifikasi.
- [ ] Dokumentasi endpoint dan authentication flow tersedia.
- [ ] Implementasi tidak menggunakan asumsi yang bertentangan dengan PDF.
- [ ] PHP Native dapat berjalan tanpa framework.
- [ ] Database MySQL berhasil terhubung.
- [ ] HTTP client dapat mengirim request.
- [ ] Signature berhasil dibuat sesuai spesifikasi.
- [ ] Proses get token berhasil menggunakan credential yang valid.
- [ ] Error autentikasi dapat ditangani.
- [ ] Connectivity test dapat dilakukan dari server.
- [ ] Request dan response history dapat disimpan.
- [ ] Informasi sensitif tidak terekspos.
- [ ] Positive, negative, dan edge cases telah diuji.
- [ ] Aplikasi dapat diakses melalui browser pada server tujuan.

## 14. Important Development Principles

AI CLI wajib mengikuti prinsip berikut:

1. **Specification First:** Seluruh implementasi integrasi wajib mengacu pada PDF Bank Jatim SNAP v1.9.4.
2. **Keep It Simple:** Gunakan pendekatan PHP Native yang sederhana dan mudah dipahami.
3. **No Overengineering:** Jangan membuat abstraksi, class, service, atau konfigurasi yang tidak memiliki kebutuhan nyata.
4. **Security First:** Credential, token, private key, dan signature harus ditangani secara aman.
5. **Fail Gracefully:** Setiap kegagalan harus menghasilkan informasi error yang jelas tanpa membuat aplikasi crash.
6. **Test Everything:** Uji positive, negative, dan edge cases.
7. **Traceable:** Setiap pengujian harus dapat ditelusuri melalui riwayat pengujian.
8. **No Blind Implementation:** Jangan mengimplementasikan endpoint atau algoritma yang belum diverifikasi dari dokumentasi.

---

**Final Goal**

Menghasilkan aplikasi PHP Native sederhana yang berjalan di server dan mampu menguji komunikasi dengan Bank Jatim melalui implementasi SNAP API, khususnya proses connectivity test, signature generation, dan get access token.

Fokus utama project ini adalah membuktikan bahwa server dapat melakukan komunikasi dan autentikasi dengan API Bank Jatim secara benar, aman, dan sesuai dokumentasi resmi.

## 15. Docker Setup & VPS Deployment

Project wajib mendukung Docker agar aplikasi dapat dijalankan secara konsisten di lingkungan development maupun production (VPS).

Gunakan Docker Compose untuk mengelola container aplikasi PHP dan MySQL.

### 15.1. Docker Technology

| Component | Technology |
|---|---|
| Container Runtime | Docker |
| Container Orchestration | Docker Compose |
| Web Server | Apache |
| Backend Runtime | PHP Native |
| PHP Version | PHP 8.3 atau versi stabil yang kompatibel |
| Database | MySQL 8.0 |
| HTTP Extension | PHP cURL |
| Database Extension | PDO MySQL |
| Configuration | Environment variables (`.env`) |

Gunakan image resmi PHP dan MySQL sejauh memungkinkan.

### 15.2. Docker Project Structure

Tambahkan file dan direktori berikut ke struktur project:

```text
root-project/
│
├── docker/
│   ├── php/
│   │   └── Dockerfile
│   │
│   └── apache/
│       └── vhost.conf
│
├── docker-compose.yml
├── .dockerignore
├── .env
├── .env.example
└── ...
```

Sesuaikan konfigurasi Docker dengan struktur project yang sudah ada.

### 15.3. PHP Dockerfile

Buat Dockerfile untuk aplikasi PHP Native dengan ketentuan:

- Gunakan official PHP Apache image.
- Install PHP extension `pdo_mysql`.
- Install PHP extension `curl`.
- Aktifkan Apache module yang diperlukan.
- Atur document root menuju direktori `public/`.
- Pastikan Apache hanya mengekspos direktori publik aplikasi.
- Atur timezone ke `Asia/Jakarta`.
- Gunakan konfigurasi PHP yang sesuai untuk production.
- Jangan memasukkan credential atau private key ke dalam image.

### 15.4. Docker Compose

Buat `docker-compose.yml` dengan dua service utama:

**1. app**

- Menjalankan PHP Native melalui Apache.
- Build image menggunakan `docker/php/Dockerfile`.
- Menggunakan konfigurasi dari `.env`.
- Mengekspos HTTP melalui port yang dapat dikonfigurasi.
- Menunggu database siap sebelum menjalankan pemeriksaan koneksi aplikasi.
- Menyediakan restart policy `unless-stopped`.

**2. db**

- Menggunakan MySQL 8.0.
- Mengambil konfigurasi database dari `.env`.
- Menggunakan named volume agar data tetap tersimpan meskipun container dibuat ulang.
- Tidak mengekspos port MySQL ke publik secara default.
- Hanya dapat diakses oleh service aplikasi melalui internal Docker network.
- Mengaktifkan MySQL healthcheck.
- Menyediakan restart policy `unless-stopped`.

Gunakan internal Docker network agar komunikasi aplikasi dan database tidak perlu melewati jaringan publik.

### 15.5. Docker Environment Variables

Tambahkan konfigurasi Docker berikut ke `.env.example`:

```env
APP_PORT=8080
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Jakarta

DB_HOST=db
DB_PORT=3306
DB_DATABASE=test-qris-bj
DB_USERNAME=qris_user
DB_PASSWORD=CHANGE_THIS_PASSWORD
DB_ROOT_PASSWORD=CHANGE_THIS_ROOT_PASSWORD
```

Catatan:

- `DB_HOST` harus menggunakan nama service Docker, yaitu `db`, bukan `localhost`.
- Password wajib diganti dengan nilai yang kuat sebelum deployment.
- Jangan commit file `.env`.
- Gunakan `.env.example` sebagai template.
- Jangan menyimpan credential Bank Jatim di Dockerfile atau docker-compose.yml.
- Credential Bank Jatim tetap dikelola melalui `.env` sesuai bagian Configuration Requirements.

### 15.6. Persistent Storage

Database MySQL wajib menggunakan Docker named volume.

Ketentuan:

- Data database tidak boleh hilang ketika container dihentikan atau dibuat ulang.
- Gunakan named volume untuk direktori data MySQL.
- Jangan menggunakan `docker compose down -v` dalam prosedur deployment normal karena dapat menghapus data database.
- Backup database harus dapat dilakukan menggunakan `mysqldump`.
- File log aplikasi dapat menggunakan Docker volume atau bind mount dengan permission yang sesuai.
- Jangan menyimpan private key di direktori publik atau image Docker.

### 15.7. Apache & Security Configuration

Konfigurasi Apache wajib memperhatikan keamanan deployment:

- Document root mengarah ke `/var/www/html/public`.
- Direktori `src/`, `storage/`, `database/`, dan file konfigurasi tidak boleh dapat diakses melalui HTTP.
- File `.env` tidak boleh dapat diakses dari browser.
- Aktifkan konfigurasi PHP production.
- Nonaktifkan directory listing.
- Pastikan error detail dan stack trace tidak ditampilkan ke pengguna.
- Gunakan HTTPS pada VPS melalui reverse proxy atau web server yang menangani SSL.
- Jangan menonaktifkan SSL verification ketika aplikasi berkomunikasi dengan Bank Jatim.

### 15.8. VPS Deployment Requirements

Deployment ditujukan untuk VPS Linux.

Ketentuan:

1. VPS harus memiliki Docker Engine dan Docker Compose Plugin.
2. Aplikasi dijalankan menggunakan Docker Compose.
3. Port aplikasi harus dapat dikonfigurasi.
4. Database MySQL tidak boleh diekspos langsung ke internet.
5. Gunakan firewall VPS untuk membatasi akses port.
6. Jika menggunakan domain, arahkan domain ke IP VPS.
7. Gunakan HTTPS untuk akses dashboard.
8. Pastikan container dapat melakukan outbound HTTPS request menuju API Bank Jatim.
9. Pastikan VPS dapat melakukan DNS resolution.
10. Pastikan waktu server tersinkronisasi dengan NTP karena proses autentikasi dan signature dapat bergantung pada timestamp.
11. Pastikan private key dan credential hanya dapat diakses oleh aplikasi.
12. Gunakan restart policy agar aplikasi dapat berjalan kembali setelah VPS restart.

Jangan mengasumsikan VPS menggunakan Nginx atau Apache yang sudah terpasang di host. Konfigurasi deployment harus dapat berjalan secara mandiri melalui Docker.

Jika VPS sudah memiliki reverse proxy, konfigurasi port dan routing harus dapat disesuaikan tanpa mengubah implementasi aplikasi.

### 15.9. Docker Commands

Dokumentasikan perintah berikut untuk operasional aplikasi.

**Build dan jalankan aplikasi:**

```bash
docker compose up -d --build
```

**Melihat status container:**

```bash
docker compose ps
```

**Melihat log aplikasi:**

```bash
docker compose logs -f app
```

**Melihat log database:**

```bash
docker compose logs -f db
```

**Menghentikan aplikasi:**

```bash
docker compose down
```

**Rebuild aplikasi setelah perubahan Dockerfile:**

```bash
docker compose up -d --build
```

**Masuk ke container aplikasi:**

```bash
docker compose exec app bash
```

**Masuk ke MySQL:**

```bash
docker compose exec db mysql -u root -p
```

**Backup database:**

```bash
docker compose exec db sh -c 'exec mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > backup.sql
```

**Restore database:**

```bash
docker compose exec -T db sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < backup.sql
```

### 15.10. Docker Acceptance Criteria

- [ ] Dockerfile berhasil melakukan build.
- [ ] Docker Compose berhasil menjalankan seluruh service.
- [ ] Container PHP Apache berjalan dengan normal.
- [ ] PHP cURL tersedia.
- [ ] PDO MySQL tersedia.
- [ ] Aplikasi dapat mengakses MySQL melalui internal Docker network.
- [ ] MySQL healthcheck berhasil.
- [ ] Data MySQL tetap tersedia setelah container dibuat ulang.
- [ ] Aplikasi dapat diakses melalui port yang ditentukan.
- [ ] Direktori internal dan file `.env` tidak dapat diakses melalui HTTP.
- [ ] Container dapat restart secara otomatis.
- [ ] Aplikasi dapat melakukan outbound HTTPS request dari VPS.
- [ ] Deployment dapat dilakukan tanpa memasang PHP dan MySQL secara langsung pada host VPS.

### 15.11. Docker Development Principles

1. Gunakan Docker Compose sebagai entry point untuk menjalankan aplikasi.
2. Pisahkan konfigurasi Docker dari source code aplikasi.
3. Gunakan official image yang sesuai.
4. Jangan menjalankan MySQL pada container aplikasi.
5. Jangan mengekspos database ke publik.
6. Jangan menyimpan credential di image Docker.
7. Pastikan data database persisten.
8. Gunakan konfigurasi production yang aman.
9. Hindari penggunaan container tambahan yang tidak diperlukan.
10. Jangan menggunakan Kubernetes atau orchestration tambahan karena kebutuhan project masih sederhana.

**Final Docker Goal**

Aplikasi dapat dijalankan dan di-deploy ke VPS menggunakan Docker Compose dengan dua container utama, yaitu PHP Apache dan MySQL.

Seluruh kebutuhan komunikasi API Bank Jatim, penyimpanan riwayat pengujian, serta konfigurasi credential harus tetap berjalan normal di lingkungan Docker.
## 16. VPS Domain & Access Configuration

Aplikasi akan di-deploy pada VPS dan diakses melalui domain berikut:

| Component | Configuration |
|---|---|
| Deployment Platform | VPS Linux |
| Domain | `qrisbj.lksbarokah255.my.id` |
| Application URL | `https://qrisbj.lksbarokah255.my.id` |
| Protocol | HTTPS |
| Deployment Method | Docker Compose |
| Web Server | Apache (Docker Container) |
| Backend | PHP Native |
| Database | MySQL 8.0 |

### 16.1. Domain Configuration

Gunakan domain `qrisbj.lksbarokah255.my.id` sebagai alamat utama aplikasi.

Ketentuan:

- Buat DNS A record untuk `qrisbj.lksbarokah255.my.id` yang mengarah ke IPv4 publik VPS.
- Pastikan DNS sudah mengarah dengan benar sebelum melakukan konfigurasi SSL.
- Aplikasi harus dapat diakses menggunakan domain tersebut.
- Jangan mengubah konfigurasi root domain maupun subdomain lain yang sudah berjalan di VPS.

### 16.2. Application Access

URL utama aplikasi:

```text
https://qrisbj.lksbarokah255.my.id
```

Ketentuan:

- Aplikasi diakses langsung melalui root domain, tanpa subdirectory atau path tambahan.
- HTTP harus diarahkan ke HTTPS.
- Dashboard aplikasi tersedia melalui URL utama.
- Endpoint internal aplikasi mengikuti routing yang dibuat pada aplikasi.
- Database MySQL tetap berada di internal Docker network dan tidak dapat diakses langsung dari internet.

### 16.3. Reverse Proxy & Docker Networking

- Aplikasi tetap dijalankan menggunakan Docker Compose.
- Apache di dalam container menangani HTTP request aplikasi.
- Port aplikasi harus dapat dikonfigurasi agar dapat dihubungkan dengan reverse proxy VPS.
- Jika VPS sudah menggunakan Nginx sebagai reverse proxy, arahkan proxy ke port aplikasi yang diekspos oleh Docker.
- Periksa konfigurasi web server dan reverse proxy yang sudah berjalan sebelum melakukan perubahan.
- Jangan mengasumsikan konfigurasi reverse proxy yang sedang digunakan.
- Jangan mengubah atau mengganggu website dan subdomain lain yang sudah berjalan di VPS.

### 16.4. SSL/HTTPS Configuration

- Domain wajib menggunakan SSL certificate yang valid.
- Seluruh akses aplikasi menggunakan HTTPS.
- HTTP diarahkan ke HTTPS.
- Pastikan mekanisme renewal SSL certificate berjalan.
- Jangan menonaktifkan SSL verification pada komunikasi outbound aplikasi ke API Bank Jatim.

### 16.5. Deployment Acceptance Criteria

- [ ] DNS domain mengarah ke IPv4 publik VPS.
- [ ] Docker Compose berhasil menjalankan aplikasi.
- [ ] Reverse proxy dapat meneruskan request ke container aplikasi.
- [ ] Domain dapat diakses menggunakan HTTPS.
- [ ] HTTP otomatis diarahkan ke HTTPS.
- [ ] Dashboard dapat diakses melalui `https://qrisbj.lksbarokah255.my.id`.
- [ ] MySQL hanya dapat diakses melalui internal Docker network.
- [ ] Deployment tidak mengganggu website atau subdomain lain di VPS.
- [ ] Aplikasi dapat melakukan outbound HTTPS request menuju API Bank Jatim.

**Final Deployment Goal**

Aplikasi dapat berjalan di VPS menggunakan Docker Compose dan dapat diakses melalui domain `https://qrisbj.lksbarokah255.my.id` dengan koneksi HTTPS yang valid.
