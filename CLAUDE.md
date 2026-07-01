# CLAUDE.md

## Project Context

Project ini adalah **Dashboard Monitoring Programmatic Ads & Website Performance** untuk membantu tim Programmatic Revenue memonitor:

- Slot iklan yang bisa dioptimalkan.
- Aktivitas bidding dan bidder health.
- Prebid.js auction, timeout, error, dan latency.
- Koneksi ke Google Ad Manager (GAM).
- Network request dari script ads dan third-party JavaScript.
- Web Core Vitals, terutama LCP, INP, CLS, FCP, TTFB, dan TBT.
- Korelasi antara monetisasi iklan dan performa website.

Tujuan utama aplikasi adalah membantu tim mendeteksi masalah lebih cepat, mengoptimalkan revenue programmatic, dan menjaga user experience website tetap baik.

---

## Source Documents

Saat mengerjakan project ini, gunakan dokumen berikut sebagai acuan:

1. `README.md` — Business Requirements Document / BRD.
2. `Database.sql` — ERD dan schema PostgreSQL.
3. `SECURITY.md` — standar keamanan Laravel API, Svelte, JWT, RBAC, dan deployment.
4. `TESTING.md` — strategi testing backend, frontend, API, E2E, security, dan Docker.
5. `Dockerfile` — baseline container build.
6. `.dockerignore` — file exclusion untuk Docker build context.

Jika ada konflik, prioritas keputusan:

1. Security dan data privacy.
2. Database schema dan data integrity.
3. API contract.
4. UX dan dashboard clarity.
5. Developer convenience.

---

## Technology Stack

### Backend

- **Framework:** Laravel PHP
- **Role:** REST API backend
- **API Prefix:** `/api/v1`
- **Authentication:** JWT Bearer Token
- **Database:** PostgreSQL
- **Queue:** Laravel Queue
- **Cache:** Redis jika tersedia
- **Testing:** Pest atau PHPUnit

### Frontend

- **Framework:** Svelte
- **Build Tool:** Vite
- **Styling:** Tailwind CSS + DaisyUI
- **Charts:** Chart library yang ringan dan kompatibel dengan Svelte
- **Auth:** JWT-aware API client
- **Testing:** Vitest, Testing Library for Svelte, Playwright

### Infrastructure

- **Container:** Docker
- **Reverse Proxy:** Nginx atau platform ingress yang tersedia
- **Deployment:** Containerized deployment
- **Environment:** development, staging, production

---

## Architecture Principles

1. Laravel bertindak sebagai API-only backend.
2. Svelte bertindak sebagai SPA dashboard frontend.
3. Semua komunikasi frontend-backend melalui REST API `/api/v1`.
4. Semua endpoint privat wajib menggunakan JWT authentication.
5. Semua endpoint role-specific wajib menggunakan authorization middleware, Gate, atau Policy.
6. Data besar harus dipaginasi, difilter, dan diagregasi di backend.
7. Frontend tidak boleh melakukan agregasi berat untuk data volume besar.
8. Business logic utama harus berada di backend service class, bukan controller.
9. Controller harus tipis: validate request, call service, return resource response.
10. Query database harus mempertimbangkan index dan performa PostgreSQL.

---

## Backend Laravel Guidelines

### Folder Convention

Gunakan struktur berikut:

```text
app/
  Http/
    Controllers/Api/V1/
    Middleware/
    Requests/
    Resources/
  Models/
  Services/
    Dashboard/
    Programmatic/
    Prebid/
    Gam/
    WebVitals/
    NetworkAds/
    Alerts/
  Actions/
  Policies/
  Jobs/
  Events/
  Enums/
  DTOs/
  Support/
```

### Controller Rules

Controller tidak boleh berisi business logic kompleks.

Controller pattern:

```php
public function index(SlotPerformanceRequest $request, SlotPerformanceService $service): JsonResponse
{
    $result = $service->getSlotPerformance($request->validated());

    return ApiResponse::success($result);
}
```

### Service Rules

Service class bertanggung jawab untuk:

- Metric calculation.
- Query aggregation.
- Alert evaluation.
- External data normalization.
- Business rule execution.

### Request Validation

Gunakan Laravel Form Request untuk semua endpoint yang menerima input.

Validasi minimal untuk dashboard filter:

- `domain_id`
- `page_id`
- `date_from`
- `date_to`
- `device`
- `slot_id`
- `bidder_id`
- `severity`

### API Response Standard

Semua response API harus konsisten.

Success:

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

Error:

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

## Authentication & JWT Requirements

Gunakan JWT Bearer Token untuk API authentication.

Header:

```http
Authorization: Bearer <access_token>
```

Auth endpoints minimal:

```text
POST /api/v1/auth/login
POST /api/v1/auth/logout
POST /api/v1/auth/refresh
GET  /api/v1/auth/me
```

Rules:

1. Token harus memiliki expiration.
2. Logout harus merevoke token jika package mendukung blacklist/revocation.
3. Jangan log token.
4. Jangan kirim token melalui query string.
5. Jangan hardcode JWT secret.
6. JWT secret disimpan di `.env`.

---

## Authorization & Roles

Role awal:

- `admin`
- `programmatic_revenue`
- `adops`
- `tech`
- `viewer`

Gunakan middleware atau Policy untuk membatasi akses.

Jangan hanya menyembunyikan menu di Svelte. Backend tetap harus melakukan authorization check.

---

## Core API Modules

### 1. Dashboard Overview

Endpoint contoh:

```text
GET /api/v1/dashboard/overview
```

Data:

- Total revenue.
- Total impression.
- Ad request.
- Fill rate.
- Average eCPM.
- Bid request.
- Bid response.
- Timeout rate.
- GAM health.
- Prebid health.
- Web Core Vitals summary.
- Active alerts.

### 2. Slot Performance

```text
GET /api/v1/slots
GET /api/v1/slots/{id}
```

### 3. Bidding Monitoring

```text
GET /api/v1/bidders
GET /api/v1/bidders/{id}
```

### 4. Prebid Health

```text
GET /api/v1/prebid/health
GET /api/v1/prebid/auctions
```

### 5. GAM Monitoring

```text
GET /api/v1/gam/health
GET /api/v1/gam/requests
```

### 6. Network Ads

```text
GET /api/v1/network-ads
GET /api/v1/network-ads/heavy-requests
```

### 7. Web Core Vitals

```text
GET /api/v1/web-vitals
GET /api/v1/web-vitals/pages
```

### 8. Alerts & Insights

```text
GET /api/v1/alerts
GET /api/v1/alerts/{id}
PATCH /api/v1/alerts/{id}/acknowledge
GET /api/v1/insights
```

---

## Frontend Svelte Guidelines

### Folder Convention

Gunakan struktur berikut:

```text
src/
  lib/
    api/
      client.ts
      auth.ts
      dashboard.ts
      slots.ts
      bidders.ts
      prebid.ts
      gam.ts
      webVitals.ts
      alerts.ts
    components/
      layout/
      dashboard/
      charts/
      tables/
      forms/
      feedback/
    stores/
      auth.ts
      filters.ts
      ui.ts
    utils/
    types/
  routes/
    login/
    dashboard/
    slots/
    bidders/
    prebid/
    gam/
    network-ads/
    web-vitals/
    alerts/
```

### API Client Rules

Buat single API client untuk:

1. Inject JWT Authorization header.
2. Handle 401 Unauthorized.
3. Normalize error response.
4. Handle base URL dari environment variable.
5. Tidak mencetak token ke console.

Environment variable contoh:

```env
VITE_API_BASE_URL=http://localhost:8000/api/v1
```

---

## UI Design System

Frontend menggunakan **Tailwind CSS + DaisyUI** dengan tema utama:

> **Clean white dashboard with samurai blue accent.**

Arah visual:

- Bersih, enterprise, modern, data-dense tetapi tetap readable.
- Dominan putih dan biru.
- Nuansa biru harus terasa tegas, fokus, dan profesional seperti “biru samurai”.
- Hindari tampilan terlalu gelap untuk default mode.
- Dashboard harus nyaman untuk monitoring harian.

### DaisyUI Theme Name

Gunakan nama tema:

```text
samurai-blue
```

### Suggested DaisyUI Theme

Tambahkan di `tailwind.config.js`:

```js
import daisyui from 'daisyui';

export default {
  content: ['./src/**/*.{html,js,svelte,ts}'],
  theme: {
    extend: {}
  },
  plugins: [daisyui],
  daisyui: {
    themes: [
      {
        'samurai-blue': {
          primary: '#1E3A8A',
          'primary-content': '#FFFFFF',
          secondary: '#2563EB',
          'secondary-content': '#FFFFFF',
          accent: '#38BDF8',
          'accent-content': '#0F172A',
          neutral: '#0F172A',
          'neutral-content': '#F8FAFC',
          'base-100': '#FFFFFF',
          'base-200': '#F8FAFC',
          'base-300': '#DBEAFE',
          'base-content': '#0F172A',
          info: '#0EA5E9',
          'info-content': '#FFFFFF',
          success: '#16A34A',
          'success-content': '#FFFFFF',
          warning: '#F59E0B',
          'warning-content': '#111827',
          error: '#DC2626',
          'error-content': '#FFFFFF'
        }
      }
    ]
  }
};
```

Set theme di root HTML:

```html
<html data-theme="samurai-blue">
```

### Color Palette

Gunakan palette berikut sebagai referensi:

| Token | Color | Usage |
|---|---|---|
| Primary Samurai Blue | `#1E3A8A` | Primary button, active nav, key accents |
| Primary Hover | `#1D4ED8` | Hover state |
| Secondary Blue | `#2563EB` | Secondary action, chart highlight |
| Sky Accent | `#38BDF8` | Small accent, info badge |
| Deep Navy | `#0F172A` | Main text, headings |
| Muted Slate | `#64748B` | Secondary text |
| Soft Blue | `#EFF6FF` | Info background |
| Border Blue | `#DBEAFE` | Card/table border |
| Page BG | `#F8FAFC` | App background |
| White | `#FFFFFF` | Card background |

### UI Component Rules

#### Layout

- Sidebar kiri dengan background putih.
- Active menu menggunakan biru samurai dengan teks putih.
- Header/topbar putih dengan border bawah soft blue.
- Main content menggunakan background `#F8FAFC`.
- Card menggunakan background putih, border soft blue, dan shadow ringan.

#### Cards

Gunakan DaisyUI card:

```html
<div class="card bg-base-100 border border-base-300 shadow-sm">
  <div class="card-body">
    <h2 class="card-title text-base-content">Revenue</h2>
  </div>
</div>
```

#### Buttons

- Primary action: `btn btn-primary`.
- Secondary action: `btn btn-secondary`.
- Neutral action: `btn btn-outline`.
- Destructive action: `btn btn-error`.

#### Badges

- Healthy: `badge badge-success`.
- Warning: `badge badge-warning`.
- Critical: `badge badge-error`.
- Info: `badge badge-info`.
- Default: `badge badge-outline`.

#### Tables

- Gunakan table dengan header sticky jika data panjang.
- Zebra boleh digunakan, tetapi tetap subtle.
- Kolom angka harus right-aligned.
- Kolom status menggunakan badge.

#### Charts

- Chart container harus berada di card putih.
- Gunakan biru sebagai warna utama data series.
- Alert/error series boleh merah.
- Warning series boleh amber.
- Jangan memakai terlalu banyak warna dalam satu chart.

---

## UX Requirements

Setiap halaman dashboard wajib memiliki:

1. Title yang jelas.
2. Deskripsi singkat halaman.
3. Global filter: domain, date range, device.
4. Loading state.
5. Empty state.
6. Error state.
7. Refresh action.
8. Last updated timestamp.

Untuk data table:

1. Pagination.
2. Sorting.
3. Search/filter.
4. Export jika relevan.
5. Status badge.
6. Link ke detail page.

---

## Security Rules

Ikuti `SECURITY.md`.

Aturan paling penting:

1. Semua endpoint privat wajib JWT auth.
2. Semua endpoint role-specific wajib authorization check.
3. Semua input wajib divalidasi.
4. Jangan hardcode secret.
5. Jangan log token/password.
6. Jangan gunakan raw SQL tanpa binding.
7. Jangan gunakan `{@html}` di Svelte tanpa sanitasi.
8. CORS production tidak boleh wildcard.
9. `APP_DEBUG=false` di production.
10. Export data harus mengikuti permission.

---

## Testing Rules

Ikuti `TESTING.md`.

Aturan paling penting:

1. Tambahkan test untuk setiap endpoint baru.
2. Tambahkan auth test untuk endpoint privat.
3. Tambahkan permission test untuk endpoint role-specific.
4. Tambahkan validation test untuk Form Request.
5. Tambahkan component test untuk komponen Svelte penting.
6. Tambahkan E2E test untuk flow login dan dashboard utama.
7. Gunakan mock/fake untuk external API.
8. Test harus bisa dijalankan via Docker.

---

## Database Guidelines

Gunakan `Database.sql` sebagai referensi ERD.

Aturan:

1. Gunakan UUID untuk entity utama jika schema sudah mendukung.
2. Gunakan foreign key untuk relasi penting.
3. Gunakan index untuk filter umum:
   - domain
   - page
   - slot
   - bidder
   - timestamp
   - device
   - severity
4. Data event besar harus dipartisi atau diagregasi jika volume meningkat.
5. Hindari query dashboard langsung ke raw event jika sudah ada table agregat.
6. Gunakan migration Laravel untuk implementasi schema aktual.

---

## Data & Metrics Rules

Metric penting:

- Revenue.
- eCPM.
- Fill rate.
- Ad request.
- Impression.
- Bid request.
- Bid response.
- Bid timeout.
- No bid rate.
- Bidder latency.
- Prebid auction duration.
- GAM request success/failure.
- Network ads request duration.
- LCP.
- INP.
- CLS.
- FCP.
- TTFB.
- TBT.

Calculation rules harus berada di backend service, bukan frontend.

---

## Alert Rules

Severity:

- `low`
- `medium`
- `high`
- `critical`

Contoh threshold awal:

| Metric | Warning | Critical |
|---|---:|---:|
| Bidder timeout rate | > 10% | > 25% |
| Bid response rate | < 40% | < 20% |
| Prebid auction duration | > 1000 ms | > 2000 ms |
| GAM failed request | > 5% | > 15% |
| Fill rate | < 70% | < 50% |
| LCP | > 2.5 s | > 4 s |
| CLS | > 0.1 | > 0.25 |
| INP | > 200 ms | > 500 ms |
| Revenue drop | > 15% | > 30% |

Alert harus memiliki:

- Severity.
- Category.
- Metric.
- Current value.
- Threshold.
- Entity terkait.
- Timestamp.
- Suggested action.

---

## Coding Standards

### PHP / Laravel

- Gunakan strict typing jika memungkinkan.
- Gunakan Form Request.
- Gunakan API Resource.
- Gunakan Service class.
- Gunakan Enum untuk status/severity/role jika cocok.
- Hindari fat controller.
- Hindari query N+1.
- Gunakan pagination untuk list besar.

### TypeScript / Svelte

- Gunakan TypeScript.
- Definisikan type untuk API response.
- Gunakan store untuk auth dan global filter.
- Hindari duplicate fetch logic.
- Buat reusable component untuk card, table, badge, filter, dan chart.
- Jangan simpan sensitive token sembarangan.

---

## Docker Guidelines

Docker setup harus mendukung:

1. Laravel API container.
2. Svelte frontend container.
3. PostgreSQL container untuk local development.
4. Redis container jika queue/cache dipakai.
5. Environment variable berbasis `.env`.

Jangan memasukkan secret ke Docker image.

Production image harus optimized dan tidak menjalankan development server.

---

## Suggested Implementation Order

1. Setup Laravel API skeleton.
2. Setup PostgreSQL migration dari `Database.sql`.
3. Setup JWT authentication.
4. Setup roles dan permissions.
5. Setup Svelte + Tailwind + DaisyUI `samurai-blue` theme.
6. Build login flow.
7. Build API client dan auth store.
8. Build dashboard overview API.
9. Build dashboard overview UI.
10. Build slot performance module.
11. Build bidder monitoring module.
12. Build Prebid/GAM health module.
13. Build Web Core Vitals module.
14. Build network ads module.
15. Build alerts and insights module.
16. Add tests based on `TESTING.md`.
17. Harden security based on `SECURITY.md`.
18. Dockerize app.

---

## Do Not Do

1. Jangan membuat endpoint tanpa auth kecuali login/healthcheck/public docs.
2. Jangan menaruh logic berat di frontend.
3. Jangan hardcode API URL di component.
4. Jangan hardcode JWT secret.
5. Jangan simpan credential GAM/GA4 di repository.
6. Jangan menggunakan raw SQL tanpa binding.
7. Jangan membuat chart tanpa loading/empty/error state.
8. Jangan membuat UI dengan warna acak di luar design system.
9. Jangan mengubah schema tanpa migration dan alasan jelas.
10. Jangan menambahkan dependency besar tanpa kebutuhan kuat.

---

## Definition of Done

Feature dianggap selesai jika:

- API endpoint tersedia dan terdokumentasi.
- Request validation tersedia.
- Authorization tersedia.
- Test backend tersedia.
- UI Svelte tersedia.
- Loading, empty, error state tersedia.
- UI mengikuti DaisyUI `samurai-blue` theme.
- Test frontend tersedia untuk komponen penting.
- Tidak ada secret di kode.
- Tidak ada token/password di log.
- Docker build tetap berjalan.
- Dokumentasi terkait diperbarui jika ada perubahan.

