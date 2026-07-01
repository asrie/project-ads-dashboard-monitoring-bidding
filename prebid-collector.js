/*!
 * Prebid → Ads Dashboard collector.
 *
 * Drop this on each publisher site AFTER Prebid.js (pbjs) is available.
 * It listens to Prebid auction events, batches them, and pushes them to the
 * dashboard ingestion endpoint (Prebid is push, not pull).
 *
 *   1. Set ENDPOINT to your API (e.g. https://dashboard.example.com/api/v1/ingest/prebid)
 *   2. Set INGEST_KEY to the value of PREBID_INGEST_KEY on the backend.
 *   3. Set DOMAIN to this site's registered domain URL/host.
 *
 * Auth: the ingest key is sent in the JSON body (so navigator.sendBeacon works
 * without custom headers and avoids a CORS preflight). Keep the key per-site;
 * treat it as a write-only telemetry token, not a secret that grants read access.
 */
(function () {
  'use strict';

  var ENDPOINT = 'http://localhost:8090/api/v1/ingest/prebid';
  var INGEST_KEY = 'REPLACE_WITH_PREBID_INGEST_KEY';
  var DOMAIN = window.location.origin; // e.g. https://www.kompas.tv
  var FLUSH_INTERVAL_MS = 10000;
  var MAX_BATCH = 50;

  if (typeof window.pbjs === 'undefined') {
    return; // Prebid not present on this page.
  }
  var pbjs = window.pbjs;
  pbjs.que = pbjs.que || [];

  var buffer = [];
  var wonByAuction = {}; // auctionId -> { won_bidder, cpm }

  function device() {
    var w = window.innerWidth || document.documentElement.clientWidth || 0;
    if (/Mobi|Android|iPhone/i.test(navigator.userAgent) && w < 768) return 'mobile';
    if (/iPad|Tablet/i.test(navigator.userAgent) || (w >= 768 && w < 1024)) return 'tablet';
    return 'desktop';
  }

  function flush(useBeacon) {
    if (!buffer.length) return;
    var batch = buffer.splice(0, MAX_BATCH);
    var payload = {
      ingest_key: INGEST_KEY,
      domain: DOMAIN,
      auctions: batch,
    };
    var body = JSON.stringify(payload);

    // sendBeacon: survives page unload, no preflight (text/plain simple request).
    if (useBeacon && navigator.sendBeacon) {
      navigator.sendBeacon(ENDPOINT, new Blob([body], { type: 'text/plain' }));
      return;
    }
    fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'text/plain' },
      body: body,
      keepalive: true,
    }).catch(function () { /* swallow — telemetry must never break the page */ });
  }

  pbjs.que.push(function () {
    // Capture the winning bid (cpm + bidder) per auction.
    pbjs.onEvent('bidWon', function (bid) {
      if (bid && bid.auctionId) {
        wonByAuction[bid.auctionId] = {
          won_bidder: bid.bidderCode || bid.bidder || null,
          cpm: typeof bid.cpm === 'number' ? bid.cpm : 0,
        };
      }
    });

    // One record per completed auction.
    pbjs.onEvent('auctionEnd', function (auction) {
      try {
        var requested = (auction.bidderRequests || []).length;
        var received = (auction.bidsReceived || []).length;
        var timeouts = (auction.timeout || auction.bidsTimeout || []).length || 0;
        var noBids = (auction.noBids || []).length;
        var won = wonByAuction[auction.auctionId] || {};
        var status = timeouts > 0 ? 'timeout' : 'completed';

        buffer.push({
          auction_id: auction.auctionId,
          page_path: window.location.pathname,
          device: device(),
          started_at: new Date(auction.timestamp || auction.auctionStart || Date.now()).toISOString(),
          duration_ms: Math.max(0, (auction.auctionEnd || Date.now()) - (auction.timestamp || auction.auctionStart || Date.now())),
          bidder_count: requested,
          bids_received: received,
          timeouts: timeouts,
          errors: 0,
          won_bidder: won.won_bidder || null,
          cpm: won.cpm || 0,
          status: status,
          _noBids: noBids, // informational; ignored server-side
        });
        delete wonByAuction[auction.auctionId];

        if (buffer.length >= MAX_BATCH) flush(false);
      } catch (e) { /* never throw inside an ad lifecycle hook */ }
    });
  });

  setInterval(function () { flush(false); }, FLUSH_INTERVAL_MS);
  // Flush whatever is buffered when the user leaves the page.
  window.addEventListener('pagehide', function () { flush(true); });
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') flush(true);
  });
})();
