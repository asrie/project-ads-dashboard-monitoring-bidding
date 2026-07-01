# Menjalankan Stack Secara Lokal (End-to-End)

Dua cara: **(A) Docker Compose** (paling mudah, satu perintah) atau **(B) manual**
untuk development. Untuk Compose, lompat ke bagian "Docker Compose" di bawah.

Panduan manual menjalankan backend Laravel + frontend Svelte + PostgreSQL untuk development.

## 1. PostgreSQL (via Docker)

Port host `5432` sering dipakai layanan lain, jadi container project dipetakan ke **5433**:

```bash
docker run -d --name ads-postgres \
  -e POSTGRES_DB=ads_dashboard \
  -e POSTGRES_USER=ads \
  -e POSTGRES_PASSWORD=ads_secret_2026 \
  -p 5433:5432 \
  postgres:15-alpine
```

`backend/.env` sudah disetel untuk run lokal:

```env
DB_HOST=127.0.0.1
DB_PORT=5433
CACHE_STORE=file       # tidak butuh Redis untuk dev lokal
QUEUE_CONNECTION=sync
```

> Untuk Docker Compose nanti, `DB_HOST` kembali ke nama service (`postgres`) dan
> cache/queue bisa diarahkan ke Redis.

## 2. Backend (Laravel API)

```bash
cd backend
php artisan config:clear
php artisan migrate:fresh --seed --force   # buat schema + data demo
php artisan serve --host=127.0.0.1 --port=8000
```

API tersedia di `http://localhost:8000/api/v1`.

## 3. Frontend (Svelte SPA)

```bash
cd frontend
npm install          # sekali saja
npm run dev          # http://localhost:5173
```

`VITE_API_BASE_URL=http://localhost:8000/api/v1` (default di `.env`).
CORS backend mengizinkan `http://localhost:5173` (`FRONTEND_URL`).

## Kredensial Demo

Semua user password: **`password`**

| Email | Role | Akses |
|---|---|---|
| `admin@kgmedia.io` | admin | penuh |
| `rev@kgmedia.io` | programmatic_revenue | penuh + acknowledge alert |
| `adops@kgmedia.io` | adops | penuh + acknowledge alert |
| `tech.collab@kgmedia.io` | tech | penuh + acknowledge alert |
| `viewer@kgmedia.io` | viewer | read-only (tidak bisa acknowledge) |

## Data Demo

`DemoSeeder` mengisi ~9.600 baris untuk 3 properti KG Media (rentang 30 hari s/d 2026-06-15):

- **Domain:** Kompas TV (`kompas.tv`), Parapuan (`parapuan.co`), Sonora (`sonora.id`)
- Pages, ad slots (desktop/mobile), 10 bidder programmatic
- Metrik harian: slot performance, bidder performance, web vitals
- Event: prebid auctions, GAM requests, network ad requests
- **Server health:** health check uptime/response per jam per domain (`server_checks`)
- 12 alert (beragam severity/status, termasuk server) + 6 insight

Smart AdServer sengaja dibuat "unhealthy" (timeout tinggi) agar alert bidding muncul.

## Docker Compose (cara A — rekomendasi)

Seluruh stack (PostgreSQL + Redis + Laravel php-fpm + Nginx yang menyajikan SPA &
mem-proxy API) dijalankan dengan satu perintah. Nginx menjadi satu entrypoint di
**http://localhost:8090**; frontend memanggil API same-origin di `/api/v1` (tanpa CORS).

Prasyarat: `backend/.env` ada dan berisi `APP_KEY` + `JWT_SECRET` (nilai DB/Redis/cache
di-override oleh `docker-compose.yml`).

```bash
docker compose up -d --build          # build + start semua service
docker compose exec api php artisan db:seed --force   # isi data demo (sekali)
# buka http://localhost:8090
```

Service & port:

| Service | Image | Peran | Port host |
|---|---|---|---|
| `web` | nginx | SPA + proxy `/api` (FastCGI) | `8090` → 80 |
| `api` | php-fpm 8.3 | Laravel API (production) | internal `9000` |
| `postgres` | postgres:15 | database | `5434` → 5432 |
| `redis` | redis:7 | cache + queue | internal `6379` |

Perintah berguna:

```bash
docker compose logs -f api            # log Laravel
docker compose exec api php artisan migrate:status
docker compose down                   # stop (data tetap di volume)
docker compose down -v                # stop + hapus volume (reset penuh)
```

> **Penting (rebuild kode):** kode Laravel berbagi lewat named volume `backend_app`
> yang hanya terisi dari image saat pertama dibuat. Setelah mengubah kode backend dan
> `--build` ulang, jalankan `docker compose down -v` lalu `up` agar volume memuat kode baru.

Image production sudah dioptimalkan: composer `--no-dev --optimize-autoloader`,
OPcache aktif, `config:cache` + `route:cache` di entrypoint, **tanpa dev server**.

## Data Live (sumber asli)

Selain DemoSeeder (dummy), ada integrasi data **nyata**:

### 1. Uptime & Response Time — aktif, tanpa kredensial

Ping HTTP nyata ke tiap domain aktif, simpan ke `server_checks`.

```bash
php artisan monitor:uptime          # jalankan sekali (manual)
```

Di Docker, service **`scheduler`** menjalankan ini otomatis tiap 5 menit
(`Schedule::command('monitor:uptime')->everyFiveMinutes()` di `routes/console.php`).
Baris hasil punya `region` = `MONITOR_REGION` (mis. `docker`), membedakannya dari data demo.

### 2. Web Vitals (Google CrUX) — butuh API key gratis

Tarik field data p75 (LCP/INP/CLS/FCP/TTFB) per origin & device.

1. Ambil key gratis: <https://developer.chrome.com/docs/crux/api> (tanpa OAuth).
2. Set `CRUX_API_KEY=...` di `backend/.env` (atau env compose).
3. Jalankan: `php artisan webvitals:fetch` (otomatis harian via scheduler).

Tanpa key, command skip dengan aman. Catatan: TBT tidak tersedia di CrUX (metrik lab).

### 3. GAM (revenue/impression) — terimplementasi, butuh kredensial Anda

Konektor GAM sudah jadi: menarik report (DATE × AD_UNIT × DEVICE → ad requests, impressions,
revenue) via Ad Manager API (library resmi `googleads/googleads-php-lib`, API v202605) dan
meng-upsert ke `slot_performance_daily`.

**Setup:**
1. Buat **service account** di Google Cloud, lalu tambahkan email service account itu sebagai
   user di network Google Ad Manager Anda (role minimal: akses report).
2. Unduh JSON key, set di `backend/.env` (atau env compose):
   ```env
   GAM_NETWORK_CODE=1234567890
   GAM_SERVICE_ACCOUNT_JSON=/path/ke/key.json   # path file ATAU isi JSON mentah
   ```
   Di Docker: mount file key ke container `api` lalu set path-nya, atau tempel JSON mentah.
3. Jalankan:
   ```bash
   php artisan gam:sync --from=2026-06-01 --to=2026-06-15   # rentang custom
   php artisan gam:sync                                       # default 7 hari terakhir
   ```
   Otomatis harian via scheduler (04:00).

**Resolusi domain & auto-create:** tiap baris GAM dicocokkan ke `ad_slots` lewat
`ad_unit_path`/`name`. Domain di-resolve dari `GAM_DOMAIN_MAP` (eksplisit) → heuristik
token nama (parent/ad-unit mengandung "kompas"/"sonora"/dll) → opsi `--domain` (paksa).
Ad unit yang ter-resolve domain-nya tapi belum ada slot-nya akan **auto-dibuat**
(matikan dengan `--no-create`). Yang tak ter-resolve domain dilaporkan sebagai "Unresolved".

**Viewability:** ditarik dari kolom Active View (`AD_SERVER_ACTIVE_VIEW_VIEWABLE_IMPRESSIONS_RATE`)
dalam report yang sama → `slot_performance_daily.viewability`. Tanpa kredensial, command skip aman.

Output `gam:sync`: Rows / Matched / Created / Upserted / Unmatched / Unresolved.

### 4. Prebid — terimplementasi (push/ingestion)

Prebid tidak punya API tarik — browser di situs publisher mengirim event auction ke
endpoint ingestion. Endpoint: **`POST /api/v1/ingest/prebid`** (di luar JWT; di-guard
**ingest key** + rate limit 600/menit). Memetakan batch auction ke `prebid_auctions`
(idempoten via `auction_id`).

**Setup:**
1. Set di `backend/.env` / env compose:
   ```env
   PREBID_INGEST_KEY=<rahasia-telemetri>
   PREBID_INGEST_ORIGINS=https://www.kompas.tv,https://www.parapuan.co,https://www.sonora.id
   ```
   (`PREBID_INGEST_ORIGINS` menambah origin publisher ke CORS untuk beacon browser.)
2. Pasang [`prebid-collector.js`](prebid-collector.js) di tiap situs **setelah** Prebid.js.
   Isi `ENDPOINT`, `INGEST_KEY`, `DOMAIN`. Snippet mendengarkan `auctionEnd`/`bidWon`,
   membatch, lalu kirim via `navigator.sendBeacon` (key di body, tanpa preflight) /
   `fetch keepalive` (key di header `X-Ingest-Key`).

Data masuk langsung tampil di halaman **Prebid Health** (auction terbaru + tren).
Contoh kirim manual:
```bash
curl -X POST http://localhost:8090/api/v1/ingest/prebid \
  -H "X-Ingest-Key: <key>" -H "Content-Type: application/json" \
  -d '{"domain":"https://www.kompas.tv","auctions":[{"auction_id":"a1","duration_ms":900,"status":"completed"}]}'
```

## Ad Layout Preview (Playwright)

Menu **Ad Layout** (📱) — snapshot mobile situs + **peta posisi slot iklan** (header-bidding
vs direct), bukan iframe live (situs berita memblokir framing + cross-origin). Pilih domain →
**Capture Preview** → screenshot full-page + overlay kotak slot yang bisa diklik (klik slot →
nama slot dari `ad_slots` + metrik agregat eCPM/fill/revenue bila ter-map).

Komponen: service Docker **`renderer`** (Node + Playwright, internal `:3000`) yang dipanggil
Laravel via `RENDERER_URL`. Renderer membuka halaman pada viewport mobile, scroll untuk memicu
lazy-load, lalu membaca geometri slot dari `googletag.pubads().getSlots()` + `pbjs.adUnits`.

- Capture di-gate role (viewer read-only) + rate limit 10/menit. Butuh `RENDERER_URL` di-set
  (sudah otomatis di compose: `http://renderer:3000`).
- **Catatan:** iklan yang ter-render server-side bisa beda dari iklan user asli (posisi/ukuran
  slot tetap akurat); ad unit path situs nyata perlu diselaraskan ke `ad_slots` agar metrik muncul
  (sama seperti GAM). Penyetelan **Playwright di-pin EXACT `1.49.1`** agar cocok dengan browser di
  base image.

Verifikasi nyata: capture kompas.tv mobile mendeteksi 11 slot GPT (`/31800665/KOMPAS.TV_Mobile_Web/...`),
5 header-bidding.

## Security Site Inspector

Menu **Security Inspector** (🛡️) — OSINT & security scanner untuk domain milik Anda
(DNS, SSL, headers, WHOIS/RDAP, ports, robots.txt, DNSSEC, HTTP/2-3, tech stack).
Pilih domain → **Jalankan Scan** → hasil + grade A–F, riwayat per domain, Export JSON.
Endpoint scan dibatasi role (viewer read-only) dan **rate-limited**.

Rate limiting default 3/menit + 30/jam per user (cache store). Untuk pakai **Upstash Redis**:

```env
SECURITY_SCAN_RATELIMIT_STORE=upstash
UPSTASH_REDIS_URL=rediss://default:<token>@<host>:<port>
# atau: UPSTASH_REDIS_HOST/PORT/PASSWORD + UPSTASH_REDIS_SCHEME=tls
```

Butuh PHP `curl` + `openssl` (tersedia di image Docker `api`).

## Catatan

- Backend uji: jalankan phpunit langsung dengan flag sqlite — lihat memory project /
  `backend/` (PHP lokal tanpa `pdo_sqlite`).
- Browser autofill kadang mengisi field "Domain ID (opsional)"; kosongkan atau klik
  **Reset** jika muncul error validasi UUID.
