# Deployment Guide — qrisbj.lksbarokah255.my.id

Panduan deploy aplikasi Bank Jatim SNAP API Integration Tester ke VPS.

| Komponen | Nilai |
| --- | --- |
| Target VPS | `root@80.43.0.158` |
| Direktori aplikasi | `/opt/qris-test-bj` |
| Domain | `qrisbj.lksbarokah255.my.id` |
| URL final | `https://qrisbj.lksbarokah255.my.id` |
| Metode deploy | `./deploy.sh` (rsync + Docker Compose) |
| Reverse proxy | Nginx **sudah berjalan di VPS** (ada website lain) |

> **Penting:** VPS sudah menjalankan website lain. Jangan mengubah atau
> menghapus konfigurasi Nginx milik website lain. Tambahkan blok server baru
> saja untuk domain ini.

---

## Prasyarat

Di VPS:

- Docker Engine + Docker Compose plugin (`docker compose version`).
- Nginx berjalan sebagai reverse proxy untuk website lain.
- NTP aktif (cek: `timedatectl` — NTP synchronized: yes). Autentikasi dan
  signature SNAP bergantung pada timestamp yang akurat.
- Firewall hanya membuka port 80/443.

Di lokal:

- File `.env` sudah dibuat dan diisi (lihat bagian 2).
- Akses SSH ke VPS: `ssh root@80.43.0.158`.

---

## 1. DNS

Buat **A record**:

```text
qrisbj.lksbarokah255.my.id → 80.43.0.158
```

Verifikasi sudah resolve:

```bash
dig +short qrisbj.lksbarokah255.my.id
# atau
host qrisbj.lksbarokah255.my.id
```

Lakukan langkah ini sebelum penerbitan SSL certificate.

---

## 2. Siapkan `.env` (lokal)

```bash
# sudah dibuat — pastikan isinya:
# - password kuat untuk APP_AUTH_PASSWORD, DB_PASSWORD, DB_ROOT_PASSWORD
#   (generate: openssl rand -base64 24)
# - BANK_JATIM_BASE_URL, CLIENT_ID, CLIENT_SECRET, PARTNER_ID dari Bank Jatim
cat .env
```

`.env` tidak akan ikut ke Docker image (`.dockerignore`) dan tidak masuk git
(`.gitignore`), tetapi **akan** ikut di-rsync oleh `deploy.sh` ke VPS — itu
memang yang diinginkan, karena container membacanya saat start.

---

## 3. Deploy aplikasi

```bash
./deploy.sh root@80.43.0.158 /opt/qris-test-bj
```

Script ini melakukan:

1. `rsync` project ke `/opt/qris-test-bj` (exclude: `.git`, log, key, PDF).
2. `docker compose up -d --build` di VPS.
3. Menampilkan status container + cek akses lokal `http://127.0.0.1:8080/`.

Verifikasi di VPS:

```bash
ssh root@80.43.0.158
cd /opt/qris-test-bj
docker compose ps          # app dan db harus Up (healthy)
docker compose logs -f app # periksa error saat startup
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/   # 200/302
```

---

## 4. Nginx reverse proxy (blok server baru)

Di VPS, buat file konfigurasi baru (jangan sentuh file site lain):

```bash
nano /etc/nginx/sites-available/qrisbj.lksbarokah255.my.id
```

Isi:

```nginx
server {
    listen 80;
    server_name qrisbj.lksbarokah255.my.id;

    # Placeholder SSL — akan diisi otomatis oleh certbot (bagian 5).

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 90s;
    }
}
```

Aktifkan dan muat ulang:

```bash
ln -s /etc/nginx/sites-available/qrisbj.lksbarokah255.my.id \
      /etc/nginx/sites-enabled/
nginx -t          # wajib OK sebelum reload
systemctl reload nginx
```

Uji lewat HTTP:

```bash
curl -s -o /dev/null -w '%{http_code}\n' \
  -H 'Host: qrisbj.lksbarokah255.my.id' http://127.0.0.1/
```

---

## 5. SSL / HTTPS (certbot)

```bash
apt install -y certbot python3-certbot-nginx   # jika belum ada
certbot --nginx -d qrisbj.lksbarokah255.my.id
```

Certbot akan:

- Menerbitkan certificate Let's Encrypt untuk domain.
- Mengubah blok server menjadi listen 443 SSL.
- Menambahkan redirect HTTP → HTTPS.

Verifikasi renewal:

```bash
certbot renew --dry-run
systemctl list-timers | grep certbot
```

---

## 6. Verifikasi akhir

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://qrisbj.lksbarokah255.my.id
# harus 301/308 (redirect ke HTTPS)

curl -s -o /dev/null -w '%{http_code}\n' https://qrisbj.lksbarokah255.my.id
# harus 200
```

Buka dashboard di browser `https://qrisbj.lksbarokah255.my.id`, login dengan
`APP_AUTH_PASSWORD`, lalu jalankan berurutan:

1. **Connectivity Test** — pastikan VPS bisa menjangkau base URL Bank Jatim
   (DNS, TCP, TLS).
2. **Get Access Token** — uji kredensial symmetric (HMAC-SHA512).
3. **Signature Test** — pastikan signature dihasilkan lokal.
4. **QR MPM Generate** — uji end-to-end dengan data merchant yang valid.
5. **Riwayat** — pastikan setiap pengujian muncul di tabel `api_test_logs`.

Jika semua sukses, acceptance criteria `overview.md` §13 bisa dicentang.

---

## Operasional sehari-hari

Semua perintah dijalankan dari `/opt/qris-test-bj` di VPS.

| Aksi | Perintah |
| --- | --- |
| Status container | `docker compose ps` |
| Log aplikasi | `docker compose logs -f app` |
| Log database | `docker compose logs -f db` |
| Restart | `docker compose restart` |
| Hentikan | `docker compose down` |
| Backup database | `docker compose exec db sh -c 'exec mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > backup.sql` |
| Restore database | `docker compose exec -T db sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < backup.sql` |

> **Peringatan:** jangan pernah menjalankan `docker compose down -v` pada
> prosedur normal — flag `-v` menghapus named volume database.

**Update aplikasi** (setelah ada perubahan kode di lokal):

```bash
./deploy.sh root@80.43.0.158 /opt/qris-test-bj
```

**Rollback**: git checkout commit sebelumnya di lokal, lalu deploy ulang.
