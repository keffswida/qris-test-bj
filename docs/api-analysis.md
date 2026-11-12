# API Analysis — Bank Jatim (JatimAPI) SNAP v1.9.4

Sumber: `Document Specification JatimAPI - SNAP v.1.9.4.pdf` (73 halaman, versi v1.9.4, 01 Juli 2026).

Dokumen ini adalah ringkasan hasil analisis yang menjadi dasar implementasi aplikasi tester.
Rincian teknis wajib mengacu pada PDF; dokumen ini hanya menyaring informasi yang relevan
dengan lingkup tester: **connectivity test, signature generation, dan get access token**.

---

## 1. Base URL & Environment

- Seluruh URL endpoint di PDF ditulis sebagai `<domain>/...` (placeholder). PDF **tidak**
  menyebutkan URL sandbox/production yang konkret.
- Nilai domain diperoleh dari Bank Jatim pada saat onboarding partner dan dikonfigurasi
  melalui `.env` (`BANK_JATIM_BASE_URL`).
- **Asumsi terbuka:** nilai `BANK_JATIM_BASE_URL` harus diisi oleh pengguna dengan domain
  yang diberikan Bank Jatim. Aplikasi memvalidasi konfigurasi sebelum mengirim request.

## 2. Format Waktu (Timestamp)

- `X-TIMESTAMP` mengikuti ISO-8601 dengan timezone: `YYYY-MM-DDThh:mm:ssTZD`
  Contoh dari PDF: `2024-07-16T19:50:23+07:00`
- Server aplikasi menggunakan timezone `Asia/Jakarta` (`+07:00`).
- Timestamp yang dipakai di header dan di dalam StringToSign **harus sama**.

## 3. Autentikasi — Access Token B2B

### 3.1. Endpoint

| Item     | Nilai                                        |
| -------- | -------------------------------------------- |
| Method   | `POST`                                       |
| URL      | `<domain>/access_token/v1.0/b2b`             |
| Headers  | `Content-Type`, `X-TIMESTAMP`, `X-CLIENT-KEY`, `X-SIGNATURE` |
| Body     | `{"grantType":"client_credentials","additionalInfo":{}}` |

### 3.2. Headers request token

| Header         | Nilai                                                                 |
| -------------- | --------------------------------------------------------------------- |
| Content-Type   | `application/json`                                                    |
| X-TIMESTAMP    | ISO-8601 timestamp                                                    |
| X-CLIENT-KEY   | `client_id` yang diperoleh dari Bank Jatim                            |
| X-SIGNATURE    | Hasil **Signature Access Token** (lihat bagian 4.1)                   |

### 3.3. Response sukses

```json
{
  "responseCode": "2007300",
  "responseMessage": "Successful",
  "accessToken": "<jwt>",
  "tokenType": "Bearer",
  "expiresIn": "900",
  "additionalInfo": {}
}
```

`expiresIn` berupa string detik.

### 3.4. Error code endpoint token

| HTTP | Error Code | Keterangan                                              |
| ---- | ---------- | ------------------------------------------------------- |
| 400  | 4007301    | Invalid Field Format {X-TIMESTAMP/ grantType}           |
| 400  | 4007302    | Invalid Mandatory Field {X-TIMESTAMP/ X-CLIENT-KEY/ X-SIGNATURE/ Content-Type} |
| 401  | 4017300    | Unauthorized. [Invalid Client Secret/ Invalid Signature]|
| 401  | 4017301    | Unauthorized. Content Type Request is Invalid           |
| 403  | 4037322    | Token Limit Invalid                                     |
| 500  | 5007300    | General Error. [reason]                                 |

## 4. Digital Signature

Dua jenis signature dengan formula berbeda. Keduanya mendukung **2 metode enkripsi**
yang disepakati dengan Bank Jatim: **Symmetric** dan **Asymmetric**.

### 4.1. Signature Access Token (untuk request Access Token B2B)

```
StringToSign = Client_ID + "|" + X-TIMESTAMP
```

| Metode      | Formula X-SIGNATURE                                      |
| ----------- | -------------------------------------------------------- |
| Symmetric   | `base64( HMAC-SHA512(ClientSecret, StringToSign) )`      |
| Asymmetric  | `base64( SHA256withRSA(PrivateKey, StringToSign) )`      |

### 4.2. Signature Service (untuk endpoint web service/transaksi)

```
Symmetric:
StringToSign = HTTPMethod + ":" + RelativeUrl + ":" + AccessToken + ":"
             + Lowercase(HexEncode(SHA-256(MinifyJson(RequestBody)))) + ":" + Timestamp

Asymmetric (TANPA AccessToken):
StringToSign = HTTPMethod + ":" + RelativeUrl + ":"
             + Lowercase(HexEncode(SHA-256(MinifyJson(RequestBody)))) + ":" + Timestamp
```

- `RelativeUrl` = path endpoint lengkap termasuk parameter URL (contoh: `/api/v1.0/qr/qr-mpm-generate`).
- `MinifyJson(RequestBody)` = JSON tanpa whitespace; **jika tidak ada request body, gunakan string kosong**.
- Body hash = SHA-256 (hex) dari body yang di-minify, lalu **lowercase**.

| Metode      | Formula X-SIGNATURE                                      |
| ----------- | -------------------------------------------------------- |
| Symmetric   | `base64( HMAC-SHA512(ClientSecret, StringToSign) )`      |
| Asymmetric  | `base64( SHA256withRSA(PrivateKey, StringToSign) )`      |

### 4.3. Kunci Asymmetric

- Partner membuat RSA Key Pair: **Private Key format PKCS8**, **Public Key format X.509**,
  RSA key size **2048 bit**.
- Public key X.509 dikirim ke Bank Jatim untuk verifikasi.
- Algorithm signing: `SHA256withRSA` (setara `openssl_sign` dengan `OPENSSL_ALGO_SHA256`).

## 5. Struktur Header Transaksi (Web Service)

| Header         | Attribute   | Type       | Keterangan                                            |
| -------------- | ----------- | ---------- | ----------------------------------------------------- |
| Content-Type   | Mandatory   | String     | `application/json`                                    |
| Authorization  | Conditional | String     | `Bearer <AccessToken>`                                |
| X-TIMESTAMP    | Mandatory   | String     | Waktu request (ISO-8601)                              |
| X-SIGNATURE    | Mandatory   | String     | Hasil Signature Service                               |
| X-PARTNER-ID   | Mandatory   | String(36) | Unique Partner ID dari Bank Jatim                     |
| X-EXTERNAL-ID  | Mandatory   | String(36) | Referensi unik dari partner per request per hari      |
| CHANNEL-ID     | Mandatory   | String(5)  | Channel ID sesuai endpoint (00001/00002)              |

## 6. Daftar Endpoint (seluruh Web Service pada PDF)

| #  | Service                                  | Method | URL                                                    | Channel-ID |
| -- | ---------------------------------------- | ------ | ------------------------------------------------------ | ---------- |
| 1  | Balance Inquiry                          | POST   | `/api/v1.0/balance-inquiry`                            | 00001      |
| 2  | Bank Statement                           | POST   | `/api/v1.0/bank-statement`                             | 00001      |
| 3  | Internal Account Inquiry                 | POST   | `/api/v1.0/account-inquiry-internal`                   | 00001      |
| 4  | External Account Inquiry                 | POST   | `/api/v1.0/account-inquiry-external`                   | 00001      |
| 5  | Transfer Intrabank                       | POST   | `/api/v1.0/transfer-intrabank`                         | 00001      |
| 6  | Transfer Interbank                       | POST   | `/api/v1.0/transfer-interbank`                         | 00001      |
| 7  | Virtual Account Create                   | POST   | `/api/v1.0/transfer-va/create-va`                      | 00002      |
| 8  | Virtual Account Update                   | PUT    | `/api/v1.0/transfer-va/update-va`                      | 00002      |
| 9  | Virtual Account Inquiry VA               | POST   | `/api/v1.0/transfer-va/inquiry-va`                     | 00002      |
| 10 | Virtual Account Delete                   | DELETE | `/api/v1.0/transfer-va/delete-va`                      | 00002      |
| 11 | MPM Generate QR MPM                      | POST   | `/api/v1.0/qr/qr-mpm-generate`                         | 00002      |
| 12 | MPM Query Payment                        | POST   | `/api/v1.0/qr/qr-mpm-query`                            | 00002      |
| 13 | MPM Payment Notify                       | POST   | `/api/v1.0/qr/qr-mpm-notify`                           | 00002      |
| 14 | Virtual Account Payment Notify           | POST   | `/api/v1.0/transfer-va/va-notify`                      | 00002      |
| 15 | VA Notification Payment from Intra Bank  | POST   | `/api/v1.0/transfer-va/notify-payment-intrabank`       | 00002      |
| 16 | Transfer Status Inquiry Bank             | POST   | `/api/v1.0/transfer/status`                            | 00002      |
| 17 | Interbank Bulk Transfer                  | POST   | `/api/v1.0/transfer-interbank-bulk`                    | 00002      |
| 18 | Interbank Bulk Transfer Notify           | POST   | `/api/v1.0/transfer-interbank-bulk/notify`             | 00002      |
| 19 | Transaction History List                 | POST   | `/api/v1.0/transaction-history-list`                   | 00001      |
| 20 | Bill Inquiry PBB                         | POST   | `/api/v1.0/bill/inquiry-pbb`                           | 00001      |
| 21 | Bill Payment PBB                         | POST   | `/api/v1.0/bill/payment-pbb`                           | 00001      |

Token endpoint: `POST <domain>/access_token/v1.0/b2b`.

## 7. Endpoint yang Diimplementasikan Tester

### 7.1. Access Token B2B — `POST /access_token/v1.0/b2b`

Lihat bagian 3.

### 7.2. MPM Generate QR MPM — `POST /api/v1.0/qr/qr-mpm-generate` (CHANNEL-ID 00002)

Digunakan sebagai uji end-to-end konektivitas + autentikasi + signature (endpoint QRIS).

**Request:**

| Field            | Type        | Wajib | Keterangan                                                   |
| ---------------- | ----------- | ----- | ----------------------------------------------------------- |
| partnerReferenceNo | String(64) | Ya    | Referensi unik partner; tidak boleh sama dengan request sebelumnya (baik success maupun failed) |
| amount.value     | String(10,2)| Ya    | Nominal, 2 desimal                                          |
| amount.currency  | String(3)   | Ya    | `IDR`                                                       |
| merchantId       | String(64)  | Ya    | Merchant ID / QRIS Merchant PAN                             |
| storeId          | String(20)  | Ya    | Store ID / nama merchant                                    |
| terminalId       | String(16)  | Ya    | QRIS Terminal ID                                            |
| validityPeriod   | String(25)  | Ya    | Expired QR (ISO-8601); wajib > timestamp saat request (mandatory sejak v1.9.4) |
| additionalInfo.billNumber       | String(24) | Ya | Kode bill partner                                |
| additionalInfo.transactionPurpose | String(15) | Ya | Tujuan transaksi                                 |
| additionalInfo.customerLabel     | String(15) | Ya | Label customer                                   |

Contoh request:

```json
{
  "partnerReferenceNo": "2024070800000000000001",
  "amount": { "value": "1000.00", "currency": "IDR" },
  "merchantId": "9360011400000000016",
  "storeId": "DEV STORE",
  "terminalId": "ID1020036843461",
  "validityPeriod": "2024-07-13T15:59:17+07:00",
  "additionalInfo": {
    "billNumber": "20240708144802",
    "transactionPurpose": "TEST BILL",
    "customerLabel": "TEST CUSTOMER"
  }
}
```

**Response sukses:** `responseCode: "2004700"`, plus `referenceNo`, `partnerReferenceNo`,
`qrContent` (QR MPM string), `merchantName`, `storeId`, `terminalId`, `additionalInfo`.

### 7.3. Referensi: MPM Query Payment — `POST /api/v1.0/qr/qr-mpm-query` (00002)

Status transaksi (`latestTransactionStatus`): `00` Success, `01` Initiated, `02` Paying,
`03` Pending, `04` Refunded, `05` Canceled, `06` Failed, `07` Not found.
(Tidak diimplementasikan sebagai aksi tester; hanya dicatat sebagai referensi.)

## 8. Global Response Status

| Category | HTTP | Case Code | Error Code | Error Message                                        |
| -------- | ---- | --------- | ---------- | ---------------------------------------------------- |
| Success  | 200  | any       | 00         | Success                                              |
| System   | 400  | any       | 00         | Bad Request. [reason]                                |
| System   | 400  | any       | 01         | Invalid Field Format {fieldName}                     |
| System   | 400  | any       | 02         | Missing Mandatory Field {fieldName}                  |
| System   | 401  | any       | 00         | Unauthorized. [reason]                               |
| System   | 401  | any       | 01         | Access Token Invalid                                 |
| System   | 403  | any       | 01         | Feature Not Allowed. [reason]                        |
| System   | 409  | any       | 00         | Conflict. Cannot use same X-EXTERNAL-ID in same day  |
| System   | 409  | any       | 01         | Duplicate partnerReferenceNo                         |
| System   | 500  | any       | 00         | General Error. [reason]                              |
| System   | 500  | any       | 02         | External Server Error. [reason]                      |

## 9. Aturan Error Handling yang Diimplementasikan

- HTTP 200 tapi `responseCode` bisnis bukan success → status mengikuti response bisnis.
- Response body kosong / bukan JSON valid → parsing error ditangani, tidak fatal.
- Curl error dibedakan: DNS resolution, koneksi TCP, TLS/SSL, timeout.
- Token expired → status expired ditampilkan dan token di-refresh saat dibutuhkan.
- Response token tanpa field wajib (`accessToken`, `expiresIn`) → dianggap tidak valid.
- Private key tidak ditemukan / tidak dapat dibaca → proses signing dihentikan dengan pesan jelas.
- Kegagalan penyimpanan log DB tidak menutupi hasil komunikasi API.

## 10. Asumsi & Pertanyaan Terbuka

1. **Base URL konkret** (sandbox/production) tidak tercantum di PDF — harus diperoleh dari
   Bank Jatim dan diisi di `.env`. *(pertanyaan terbuka untuk konfirmasi)*
2. Metode enkripsi (symmetric/asymmetric) ditentukan lewat kesepakatan dengan Bank Jatim —
   dikonfigurasi lewat `BANK_JATIM_SIGNATURE_METHOD`.
3. `X-PARTNER-ID`, `client_id`, `client_secret` diperoleh dari Bank Jatim — dikonfigurasi
   lewat `.env`, tidak di-hardcode.
4. Nilai `CHANNEL-ID` per endpoint mengikuti tabel bagian 6 (00001/00002).
