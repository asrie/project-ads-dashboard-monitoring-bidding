'use strict';

/**
 * Headless renderer for the dashboard "Ad Layout" preview.
 *
 * POST /render { url, device }  ->  { screenshot_base64, page_width, page_height,
 *   viewport_css_width, slots: [...], prebid_units: [...], header: {...} }
 *
 * Loads the page at a mobile viewport, waits for ads, auto-scrolls to trigger
 * lazy slots, then reads ad-slot geometry directly from googletag / pbjs.
 * Only used for the org's own registered domains (authorized).
 */

const express = require('express');
const { chromium, devices } = require('playwright');

const app = express();
app.use(express.json({ limit: '2mb' }));

const NAV_TIMEOUT = 45000;

app.get('/health', (_req, res) => res.json({ ok: true }));

app.post('/render', async (req, res) => {
  const url = String(req.body.url || '');
  if (!/^https?:\/\//i.test(url)) {
    return res.status(400).json({ error: 'A valid http(s) url is required.' });
  }

  let browser;
  try {
    browser = await chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
    const context = await browser.newContext({
      ...devices['iPhone 13'],
      reducedMotion: 'reduce',
      locale: 'id-ID',
    });
    const page = await context.newPage();

    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT }).catch(() => {});
    await dismissConsent(page);
    // Give GPT/Prebid time to define slots and render.
    await page.waitForTimeout(3000);
    await autoScroll(page);
    await page.waitForTimeout(1500);
    await page.evaluate(() => window.scrollTo(0, 0)).catch(() => {});
    await page.waitForTimeout(500);

    const data = await page.evaluate(extractAdMap);
    const shot = await page.screenshot({ fullPage: true, type: 'jpeg', quality: 70 });

    res.json({ ...data, screenshot_base64: shot.toString('base64') });
  } catch (err) {
    res.status(500).json({ error: String(err && err.message ? err.message : err) });
  } finally {
    if (browser) await browser.close().catch(() => {});
  }
});

async function dismissConsent(page) {
  // Best-effort: click common consent "accept" buttons (IDs/text vary by CMP).
  const selectors = [
    'button#onetrust-accept-btn-handler',
    'button[aria-label="Accept all"]',
    'button[mode="primary"]',
    '.fc-cta-consent',
    'button:has-text("Setuju")',
    'button:has-text("Terima")',
    'button:has-text("Accept")',
  ];
  for (const sel of selectors) {
    try {
      const el = await page.$(sel);
      if (el) { await el.click({ timeout: 1500 }).catch(() => {}); break; }
    } catch (_) { /* ignore */ }
  }
}

async function autoScroll(page) {
  await page.evaluate(async () => {
    await new Promise((resolve) => {
      let total = 0;
      const step = 600;
      const timer = setInterval(() => {
        window.scrollBy(0, step);
        total += step;
        if (total >= document.body.scrollHeight + 1000) {
          clearInterval(timer);
          resolve();
        }
      }, 250);
    });
  }).catch(() => {});
}

/* Runs INSIDE the page. Reads slot geometry from googletag + Prebid adUnits. */
function extractAdMap() {
  const out = {
    viewport_css_width: window.innerWidth,
    page_width: document.documentElement.scrollWidth,
    page_height: document.documentElement.scrollHeight,
    slots: [],
    prebid_units: [],
    header: null,
  };

  const rectOf = (el) => {
    const r = el.getBoundingClientRect();
    return { x: Math.round(r.left + window.scrollX), y: Math.round(r.top + window.scrollY), w: Math.round(r.width), h: Math.round(r.height) };
  };

  try {
    const h = document.querySelector('header') || document.querySelector('[role="banner"]');
    if (h) out.header = rectOf(h);
  } catch (_) {}

  try {
    const pb = window.pbjs;
    if (pb && Array.isArray(pb.adUnits)) {
      out.prebid_units = pb.adUnits.map((u) => u && u.code).filter(Boolean);
    }
  } catch (_) {}

  try {
    const gt = window.googletag;
    if (gt && typeof gt.pubads === 'function') {
      const slots = gt.pubads().getSlots ? gt.pubads().getSlots() : [];
      slots.forEach((s) => {
        try {
          const elementId = s.getSlotElementId ? s.getSlotElementId() : null;
          const adUnitPath = s.getAdUnitPath ? s.getAdUnitPath() : null;
          const el = elementId ? document.getElementById(elementId) : null;
          const rect = el ? rectOf(el) : null;
          let sizes = [];
          try {
            sizes = (s.getSizes ? s.getSizes() : []).map((z) =>
              z && z.getWidth ? [z.getWidth(), z.getHeight()] : String(z),
            );
          } catch (_) {}
          out.slots.push({ element_id: elementId, ad_unit_path: adUnitPath, rect, sizes });
        } catch (_) {}
      });
    }
  } catch (_) {}

  return out;
}

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`renderer listening on :${PORT}`));
