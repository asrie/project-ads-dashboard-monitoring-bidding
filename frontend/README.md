# Ads Dashboard — Frontend

SPA dashboard untuk monitoring Programmatic Ads & Website Performance.

## Stack

- **Svelte 5** (runes) + **Vite 8** + **TypeScript**
- **Tailwind CSS 3** + **DaisyUI 4** — tema `samurai-blue`
- **svelte-spa-router** — hash-based client-side routing (SPA)
- Chart SVG ringan buatan sendiri (tanpa dependency chart berat)

## Setup

```bash
cp .env.example .env   # atur VITE_API_BASE_URL
npm install
npm run dev            # http://localhost:5173
```

`VITE_API_BASE_URL` harus menunjuk ke Laravel API (default `http://localhost:8000/api/v1`).

## Scripts

| Perintah | Fungsi |
|---|---|
| `npm run dev` | Dev server (HMR) |
| `npm run build` | `svelte-check` + build produksi ke `dist/` |
| `npm run preview` | Preview hasil build |
| `npm run check` | Type-check Svelte/TS |

## Struktur

```text
src/
  lib/
    api/         # client.ts (JWT, 401 handling) + 1 modul per domain
    components/  # layout, dashboard, charts, tables, forms, feedback
    stores/      # auth (token+user), filters (global), ui (toast)
    types/       # api envelope + domain models
    utils/       # format (currency, percent, ms, bytes, date)
  routes/        # login, dashboard, slots, bidders, prebid, gam,
                 # network-ads, web-vitals, alerts
  App.svelte     # Router + auth guard
```

## Catatan

- API client menyuntik header `Authorization: Bearer <token>`, menangani `401`
  (clear session + redirect ke `/login`), dan menormalkan error ke envelope standar.
- Token disimpan di `localStorage`; tidak pernah dicetak ke console.
- Auth guard di sisi klien hanya untuk UX — backend tetap sumber kebenaran otorisasi.
- Setiap halaman memiliki: title, deskripsi, global filter, loading/empty/error state,
  refresh, dan last-updated timestamp (sesuai UX requirement).
