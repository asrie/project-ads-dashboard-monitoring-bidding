# SECURITY.md

## Security Guidelines

Project ini menggunakan stack:

- **Backend API:** Laravel PHP
- **Frontend:** Svelte
- **Authentication:** JWT
- **Database:** PostgreSQL
- **UI Framework:** Tailwind CSS + DaisyUI
- **Deployment:** Docker-based environment

Dokumen ini menjadi standar keamanan untuk pengembangan Dashboard Monitoring Programmatic Ads & Website Performance.

---

## 1. Security Objectives

Tujuan utama keamanan aplikasi:

1. Melindungi akses dashboard internal Programmatic Revenue.
2. Menjaga integritas data revenue, bidding, Prebid.js, GAM, Web Core Vitals, dan network ads.
3. Mencegah akses tidak sah ke API dan database.
4. Mencegah kebocoran credential, token, secret, dan konfigurasi integrasi.
5. Menjamin API Laravel aman terhadap risiko umum seperti SQL Injection, XSS, CSRF, IDOR, brute force, dan privilege escalation.
6. Menjaga frontend Svelte agar tidak menyimpan token secara tidak aman.

---

## 2. Authentication Standard

### 2.1 JWT Authentication

Backend Laravel wajib menggunakan JWT untuk autentikasi API.

Recommended package:

- `tymon/jwt-auth`, atau
- Laravel Sanctum dengan custom JWT layer jika diputuskan oleh tim.

Default recommendation untuk project ini: **JWT Bearer Token**.

Format request:

```http
Authorization: Bearer <access_token>
```

### 2.2 Token Rules

Access token harus:

- Memiliki expiration time.
- Tidak berlaku permanen.
- Dikirim hanya melalui HTTPS.
- Tidak disimpan di database dalam bentuk plain token.
- Tidak dicetak ke log aplikasi.
- Tidak dikirim melalui query string URL.

Refresh token, jika digunakan, harus:

- Memiliki masa berlaku lebih panjang dari access token.
- Bisa di-rotate.
- Bisa di-revoke saat logout.
- Disimpan dengan mekanisme aman.

### 2.3 Token Storage di Svelte

Frontend Svelte harus mengikuti aturan berikut:

- Hindari menyimpan JWT di `localStorage` jika aplikasi memiliki risiko XSS tinggi.
- Preferensi terbaik: gunakan **httpOnly secure cookie** untuk refresh token dan in-memory state untuk access token.
- Jika sementara menggunakan `localStorage`, wajib:
  - Tidak menyimpan data user sensitif.
  - Melakukan sanitasi input dan output.
  - Menerapkan Content Security Policy.
  - Menyediakan mekanisme logout yang menghapus token sepenuhnya.

---

## 3. Authorization & RBAC

Aplikasi wajib menerapkan role-based access control.

Role awal:

1. `admin`
2. `programmatic_revenue`
3. `adops`
4. `tech`
5. `viewer`

### 3.1 Access Matrix

| Module | Admin | Programmatic Revenue | AdOps | Tech | Viewer |
|---|---|---|---|---|---|
| Overview Dashboard | Yes | Yes | Yes | Yes | Read Only |
| Slot Performance | Yes | Yes | Yes | Read | Read Only |
| Bidding Monitoring | Yes | Yes | Read | Read | Read Only |
| Prebid Monitoring | Yes | Yes | Read | Yes | Read Only |
| GAM Monitoring | Yes | Read | Yes | Read | Read Only |
| Network Ads | Yes | Read | Read | Yes | Read Only |
| Web Core Vitals | Yes | Read | Read | Yes | Read Only |
| Alert Rules | Yes | Read | Read | Read | No |
| User Management | Yes | No | No | No | No |
| System Settings | Yes | No | No | No | No |

### 3.2 Laravel Implementation

Gunakan:

- Middleware `auth:api` untuk semua endpoint privat.
- Policy atau Gate untuk resource-level authorization.
- Middleware role untuk module-level authorization.
- Jangan hanya mengandalkan frontend route guard.

Contoh route group:

```php
Route::prefix('v1')->middleware(['auth:api'])->group(function () {
    Route::get('/dashboard/overview', [DashboardController::class, 'overview']);
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('/users', UserController::class);
    });
});
```

---

## 4. API Security

### 4.1 API Versioning

Semua endpoint wajib menggunakan prefix:

```text
/api/v1
```

### 4.2 Request Validation

Semua input dari client wajib divalidasi menggunakan Laravel Form Request.

Wajib dilakukan untuk:

- Login.
- Register user internal.
- Filter dashboard.
- Query date range.
- Domain/page filtering.
- Alert rule configuration.
- Export request.

Contoh:

```php
class DashboardFilterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'domain_id' => ['nullable', 'uuid', 'exists:domains,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'device' => ['nullable', 'in:desktop,mobile,tablet'],
        ];
    }
}
```

### 4.3 SQL Injection Prevention

Gunakan:

- Eloquent ORM.
- Query Builder dengan binding.
- Parameterized query.

Hindari:

```php
DB::select("SELECT * FROM events WHERE page_url = '$pageUrl'");
```

Gunakan:

```php
DB::select('SELECT * FROM events WHERE page_url = ?', [$pageUrl]);
```

### 4.4 Mass Assignment

Semua Laravel model wajib mendefinisikan `$fillable` atau `$guarded` secara eksplisit.

```php
protected $fillable = [
    'name',
    'email',
    'role_id',
];
```

Jangan menggunakan:

```php
protected $guarded = [];
```

kecuali sudah melalui review.

---

## 5. Frontend Security for Svelte

### 5.1 Route Protection

Svelte route guard wajib memeriksa status autentikasi sebelum membuka halaman privat.

Namun, route guard hanya untuk UX. Backend tetap menjadi sumber otorisasi utama.

### 5.2 XSS Prevention

Aturan wajib:

- Hindari penggunaan `{@html}` kecuali benar-benar diperlukan.
- Jika menggunakan `{@html}`, content harus disanitasi.
- Escape output user-generated content.
- Jangan render error message mentah dari backend jika berpotensi mengandung payload.

### 5.3 API Client

Frontend harus memiliki single API client module, misalnya:

```text
src/lib/api/client.ts
```

Tanggung jawab API client:

- Menambahkan `Authorization: Bearer <token>`.
- Menangani 401 Unauthorized.
- Menangani token refresh jika tersedia.
- Menstandarkan error response.
- Tidak mencetak token ke console.

---

## 6. CORS Policy

Laravel CORS harus dibatasi pada domain frontend yang valid.

Development example:

```env
FRONTEND_URL=http://localhost:5173
```

Production example:

```env
FRONTEND_URL=https://programmatic-dashboard.company.internal
```

Jangan gunakan konfigurasi berikut di production:

```text
allowed_origins = ['*']
```

Jika menggunakan cookie-based refresh token:

- `supports_credentials` harus dikonfigurasi dengan benar.
- Origin tidak boleh wildcard.

---

## 7. CSRF Consideration

Untuk pure JWT Bearer Token API:

- CSRF risk lebih rendah karena token tidak otomatis dikirim browser seperti cookie.
- Tetap wajib mencegah XSS karena XSS dapat mencuri token jika token disimpan di storage browser.

Untuk refresh token via cookie:

- Cookie harus `httpOnly`.
- Cookie harus `secure` di production.
- Cookie harus `SameSite=Lax` atau `Strict` sesuai kebutuhan flow.
- Endpoint refresh/logout perlu CSRF strategy yang sesuai.

---

## 8. Rate Limiting

Endpoint sensitif wajib memiliki rate limit.

Minimal:

| Endpoint | Limit |
|---|---:|
| `/api/v1/auth/login` | 5 attempts / minute / IP |
| `/api/v1/auth/refresh` | 20 attempts / minute / user |
| `/api/v1/export/*` | 5 requests / minute / user |
| Dashboard aggregate endpoints | 60 requests / minute / user |

Laravel implementation:

```php
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');
```

---

## 9. Secrets Management

Tidak boleh commit file berikut:

- `.env`
- `.env.production`
- Private key
- JWT secret
- Database password
- GAM credential
- GA4 credential
- Service account JSON

Gunakan `.env.example` untuk template tanpa secret asli.

Wajib set:

```env
APP_KEY=
JWT_SECRET=
DB_PASSWORD=
GAM_SERVICE_ACCOUNT_JSON=
GA4_PROPERTY_ID=
```

### 9.1 JWT Secret

Generate JWT secret secara aman.

Contoh:

```bash
php artisan jwt:secret
```

JWT secret wajib berbeda untuk development, staging, dan production.

---

## 10. Logging & Monitoring Security

Log tidak boleh berisi:

- JWT token.
- Password.
- Refresh token.
- Authorization header.
- Service account credential.
- Full request body untuk endpoint auth.

Laravel logging harus menggunakan masking untuk field sensitif.

Field yang wajib dimasking:

```text
password
password_confirmation
token
access_token
refresh_token
authorization
jwt_secret
client_secret
service_account
```

---

## 11. Data Privacy

Aplikasi ini berfokus pada data teknis dan monetisasi, bukan data personal user.

Aturan:

- Jangan menyimpan PII pengunjung website jika tidak diperlukan.
- Page URL boleh disimpan, tetapi query parameter sensitif harus dibersihkan.
- IP address sebaiknya tidak disimpan dalam raw form kecuali ada kebutuhan keamanan yang jelas.
- User agent boleh disimpan untuk agregasi device/browser, bukan profiling individu.

URL sanitization wajib menghapus parameter seperti:

```text
email
phone
token
session
password
auth
utm_user_id
```

---

## 12. File Export Security

Jika dashboard menyediakan export CSV/XLSX:

- Export wajib hanya untuk user terautentikasi.
- Data export harus mengikuti role permission.
- File sementara harus memiliki expiration.
- Export besar harus berjalan via job queue.
- Hindari formula injection pada CSV.

CSV formula injection prevention:

Jika value dimulai dengan `=`, `+`, `-`, atau `@`, prefix dengan apostrophe `'`.

---

## 13. Docker & Deployment Security

Docker image harus:

- Tidak menjalankan app sebagai root jika memungkinkan.
- Tidak menyimpan `.env` di image.
- Menggunakan production dependency only untuk image production.
- Menjalankan Laravel config cache di production.
- Tidak mengekspos port database ke public internet.

Recommended production commands:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Jangan menjalankan:

```bash
php artisan serve
```

di production.

---

## 14. Dependency Security

Wajib melakukan dependency audit secara berkala.

Backend:

```bash
composer audit
```

Frontend:

```bash
npm audit
```

Container:

```bash
docker scout cves <image-name>
```

atau gunakan scanner internal seperti Trivy.

---

## 15. Security Checklist Before Release

Sebelum release staging/production:

- [ ] Semua endpoint privat memakai `auth:api`.
- [ ] Role dan permission sudah diuji.
- [ ] JWT expiration sudah dikonfigurasi.
- [ ] Logout/revoke token berjalan.
- [ ] Login endpoint memiliki rate limit.
- [ ] Semua request penting memakai Form Request validation.
- [ ] Tidak ada secret di repository.
- [ ] `.env.example` tersedia dan aman.
- [ ] CORS tidak wildcard di production.
- [ ] Error response tidak membocorkan stack trace.
- [ ] `APP_DEBUG=false` di production.
- [ ] Log tidak menyimpan token/password.
- [ ] Export data mengikuti permission.
- [ ] Dependency audit sudah dijalankan.
- [ ] Docker image tidak berisi secret.

---

## 16. Incident Response

Jika terjadi indikasi kebocoran token, credential, atau akses tidak sah:

1. Revoke seluruh token user terdampak.
2. Rotate `JWT_SECRET` jika ada indikasi secret bocor.
3. Rotate database password dan service account jika diperlukan.
4. Review access log dan audit log.
5. Disable user mencurigakan.
6. Patch vulnerability.
7. Dokumentasikan root cause dan tindakan korektif.

---

## 17. Secure Coding Rules for Claude / AI Assistant

Saat menghasilkan kode untuk project ini:

1. Selalu gunakan Laravel Form Request untuk validasi.
2. Selalu gunakan middleware auth dan role untuk endpoint privat.
3. Jangan membuat endpoint tanpa authorization check.
4. Jangan hardcode secret, token, atau credential.
5. Jangan menyimpan JWT di console log.
6. Jangan menggunakan raw SQL tanpa binding.
7. Jangan menggunakan `{@html}` di Svelte tanpa sanitasi.
8. Gunakan DTO/resource response untuk API output.
9. Gunakan pagination untuk list besar.
10. Gunakan policy untuk resource sensitif.

