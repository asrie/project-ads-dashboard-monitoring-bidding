# Contributing Guide

Terima kasih sudah berkontribusi ke **Dashboard Monitoring Programmatic Ads & Website
Performance**. Dokumen ini merangkum alur kerja, standar kode, testing, dan aturan
keamanan. Untuk konteks lebih dalam lihat [CLAUDE.md](CLAUDE.md) (panduan arsitektur),
[RUNNING.md](RUNNING.md) (menjalankan stack), [SECURITY.md](SECURITY.md), dan
[TESTING.md](TESTING.md).

---

## Prasyarat

| Tool | Versi | Untuk |
|---|---|---|
| PHP | 8.3 | Backend Laravel |
| Composer | 2.x | Dependency PHP |
| Node.js | 22 (LTS) | Frontend Svelte + Vite 8 |
| Docker + Compose | terbaru | Postgres/Redis lokal & full stack |

---

## Setup Lokal (ringkas)

```bash
# Backend
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan jwt:secret
php artisan migrate:fresh --seed --force
php artisan serve --host=127.0.0.1 --port=8000

# Frontend (terminal lain)
cd frontend
npm install
npm run dev            # http://localhost:5173
```

Butuh PostgreSQL lokal (port 5433) — lihat [RUNNING.md](RUNNING.md) untuk perintah Docker,
opsi Docker Compose (satu perintah), kredensial demo, dan setup data live (CrUX/GAM/Prebid).

Login demo: semua password `password`, mis. `admin@kgmedia.io`.

---

## Alur Kerja Git

Branch `main` **terproteksi** — perubahan masuk lewat Pull Request yang lolos CI.

```
feature branch  →  PR ke staging  →  (integrasi)  →  PR staging ke main
```

1. **Buat branch** dari `staging` untuk tiap unit kerja:
   ```bash
   git checkout staging && git pull
   git checkout -b feat/nama-fitur      # atau fix/, docs/, refactor/, chore/
   ```
2. **Commit** dengan gaya [Conventional Commits](https://www.conventionalcommits.org/):
   ```text
   feat(slots): tambah filter viewability
   fix(auth): tangani token kedaluwarsa di refresh
   ci: cache composer vendor
   docs(readme): perbarui langkah setup
   test(gam): tambah kasus unresolved domain
   ```
   Prefix umum: `feat`, `fix`, `refactor`, `perf`, `test`, `docs`, `ci`, `chore`.
3. **Push & buka PR:**
   ```bash
   git push -u origin feat/nama-fitur
   gh pr create --base staging --title "feat: ..." --web
   ```
4. **Merge** hanya setelah **CI hijau** (dan review bila ada). Gunakan *squash merge*
   agar histori `staging`/`main` rapi.

> Jangan push langsung ke `main` — akan ditolak branch protection. Promosikan lewat
> PR `staging → main`.

---

## Standar Kode

### Backend (Laravel / PHP)

Ikuti [CLAUDE.md › Backend Laravel Guidelines](CLAUDE.md). Inti:

- **Controller tipis:** validasi (Form Request) → panggil Service → kembalikan API Resource.
  Business logic **tidak** di controller.
- **Service class** untuk kalkulasi metrik, agregasi query, evaluasi alert, normalisasi data.
- Gunakan **strict typing** (`declare(strict_types=1);`), **Enum** untuk status/severity/role,
  **API Resource** untuk response.
- Hindari **N+1** (eager load), gunakan **pagination** untuk list besar, pertimbangkan index.
- **Format wajib dengan Pint** sebelum commit:
  ```bash
  cd backend
  vendor/bin/pint            # auto-fix
  vendor/bin/pint --test     # cek (dipakai CI)
  ```

### Frontend (Svelte / TypeScript)

Ikuti [CLAUDE.md › Frontend Svelte Guidelines](CLAUDE.md). Inti:

- **TypeScript** wajib; definisikan type untuk response API.
- Gunakan **store** untuk auth & global filter; hindari duplikasi fetch — lewat API client tunggal.
- **Komponen reusable** untuk card, table, badge, filter, chart.
- Patuhi design system **DaisyUI `samurai-blue`** — jangan warna acak di luar palette.
- Jangan `{@html}` tanpa sanitasi. Jangan simpan token sensitif sembarangan.
- Type-check sebelum commit:
  ```bash
  cd frontend
  npm run check
  ```

---

## Testing

Tambahkan test untuk tiap endpoint/komponen baru (lihat [TESTING.md](TESTING.md) dan
[CLAUDE.md › Testing Rules](CLAUDE.md)):

- Endpoint privat → test **401 tanpa JWT**.
- Endpoint role-specific → test **permission** (viewer read-only).
- Form Request → test **validasi**.
- External API (GAM/CrUX/renderer) → gunakan **mock/fake**.

**Jalankan test backend:**

```bash
cd backend
php artisan test
```

> **Catatan Windows/Laragon:** bila PHP CLI lokal tidak meng-enable driver SQLite,
> `php artisan test` gagal (`could not find driver`) karena mem-fork subprocess.
> Jalankan phpunit langsung dengan DLL eksplisit:
> ```bash
> php -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll vendor/bin/phpunit
> ```
> Test suite memakai SQLite `:memory:`; sebagian query khusus PostgreSQL hanya berjalan
> penuh terhadap Postgres.

**Frontend:**

```bash
cd frontend
npm run check      # type-check
npm run build      # svelte-check + build
```

---

## Continuous Integration

Setiap push ke `main`/`staging` dan setiap PR menjalankan
[`.github/workflows/ci.yml`](.github/workflows/ci.yml):

| Job | Langkah |
|---|---|
| **Backend (Laravel)** | `composer install` → **Pint** (`--test`) → **PHPUnit** (SQLite `:memory:`) |
| **Frontend (Svelte)** | `npm ci` → `npm run build` (svelte-check + vite build) |

PR **tidak bisa di-merge** ke `main` sebelum kedua job hijau. Pastikan `vendor/bin/pint`
dan test lokal lolos sebelum push agar CI tidak merah.

---

## Keamanan

Ikuti [SECURITY.md](SECURITY.md) dan [CLAUDE.md › Security Rules](CLAUDE.md):

- **Jangan commit secret** — `.env`, key GAM/GA4, token. Hanya `.env.example` (placeholder) yang di-track.
- Semua endpoint privat wajib **JWT**; endpoint role-specific wajib **authorization** di backend.
- **Validasi semua input**; jangan raw SQL tanpa binding; jangan log token/password.
- CORS production tidak boleh wildcard; `APP_DEBUG=false` di production.

Menemukan celah keamanan? **Jangan** buka issue publik — hubungi maintainer langsung.

---

## Definition of Done

Sebuah perubahan dianggap selesai bila (ringkas dari [CLAUDE.md](CLAUDE.md)):

- [ ] Endpoint tersedia + request validation + authorization.
- [ ] Test backend (termasuk auth/permission) tersedia dan lolos.
- [ ] UI Svelte lengkap dengan loading / empty / error state, sesuai tema `samurai-blue`.
- [ ] Test frontend untuk komponen penting (bila relevan).
- [ ] Tidak ada secret di kode; tidak ada token/password di log.
- [ ] Pint + type-check + build lolos (CI hijau).
- [ ] Dokumentasi terkait diperbarui (README/DATABASE/RUNNING bila ada perubahan schema/API).

---

## Checklist PR

Sebelum minta review, pastikan:

- [ ] Branch dari `staging`, nama deskriptif (`feat/`, `fix/`, …).
- [ ] Commit mengikuti Conventional Commits.
- [ ] `vendor/bin/pint --test` & `npm run check` lolos lokal.
- [ ] Test relevan ditambahkan dan hijau.
- [ ] Tidak ada file `.env`/secret/artefak build ikut ter-commit.
- [ ] Deskripsi PR menjelaskan **apa** & **kenapa**, plus cara uji.

Selamat berkontribusi! 🚀
