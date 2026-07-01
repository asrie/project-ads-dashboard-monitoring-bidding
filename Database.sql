-- =============================================================================
--  Dashboard Monitoring Programmatic Ads & Website Performance
--  Canonical PostgreSQL schema (ERD source-of-truth, see DATABASE.md)
-- -----------------------------------------------------------------------------
--  Catatan:
--   * File ini adalah representasi DDL dari Laravel migrations di
--     backend/database/migrations. Migration Laravel tetap menjadi sumber
--     eksekusi schema yang sebenarnya (CLAUDE.md > Database Guidelines §6).
--   * Primary key entity utama menggunakan UUID. UUID di-generate di layer
--     aplikasi (Eloquent HasUuids), sehingga kolom id tidak memakai DEFAULT.
--     Bila ingin generate di DB, aktifkan pgcrypto dan pakai gen_random_uuid().
--   * Kolom "unsigned*" pada Laravel dipetakan ke bigint/integer/smallint di
--     PostgreSQL (Postgres tidak punya tipe unsigned). Sifat non-negatif
--     ditegakkan di layer aplikasi.
--   * Timestamp memakai "timestamp without time zone" (perilaku default Laravel).
-- =============================================================================

-- Opsional: untuk gen_random_uuid() bila UUID ingin di-generate oleh database.
-- CREATE EXTENSION IF NOT EXISTS "pgcrypto";

BEGIN;

-- =============================================================================
--  1. AUTH & IDENTITY
-- =============================================================================

CREATE TABLE users (
    id                  uuid            PRIMARY KEY,
    name                varchar(255)    NOT NULL,
    email               varchar(255)    NOT NULL,
    email_verified_at   timestamp       NULL,
    password            varchar(255)    NOT NULL,
    role                varchar(255)    NOT NULL DEFAULT 'viewer',  -- enum Role
    remember_token      varchar(100)    NULL,
    created_at          timestamp       NULL,
    updated_at          timestamp       NULL,
    CONSTRAINT users_email_unique UNIQUE (email)
);
CREATE INDEX users_role_index ON users (role);

COMMENT ON TABLE  users      IS 'Pengguna dashboard. role mengikuti enum Role (admin, programmatic_revenue, adops, tech, viewer).';
COMMENT ON COLUMN users.role IS 'RBAC role: admin | programmatic_revenue | adops | tech | viewer';

CREATE TABLE password_reset_tokens (
    email       varchar(255)    PRIMARY KEY,
    token       varchar(255)    NOT NULL,
    created_at  timestamp       NULL
);

-- =============================================================================
--  2. DIMENSI / REFERENSI  (domains, pages, ad_slots, bidders)
-- =============================================================================

CREATE TABLE domains (
    id          uuid            PRIMARY KEY,
    name        varchar(255)    NOT NULL,
    url         varchar(255)    NOT NULL,
    is_active   boolean         NOT NULL DEFAULT true,
    created_at  timestamp       NULL,
    updated_at  timestamp       NULL
);
COMMENT ON TABLE domains IS 'Properti web (situs) yang dimonitor. Akar dari hampir seluruh relasi.';

CREATE TABLE pages (
    id          uuid            PRIMARY KEY,
    domain_id   uuid            NOT NULL,
    path        varchar(255)    NOT NULL,
    title       varchar(255)    NULL,
    created_at  timestamp       NULL,
    updated_at  timestamp       NULL,
    CONSTRAINT pages_domain_id_foreign
        FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE
);
CREATE INDEX pages_domain_id_path_index ON pages (domain_id, path);
COMMENT ON TABLE pages IS 'Halaman/URL path pada sebuah domain.';

CREATE TABLE ad_slots (
    id              uuid            PRIMARY KEY,
    domain_id       uuid            NOT NULL,
    name            varchar(255)    NOT NULL,
    ad_unit_path    varchar(255)    NOT NULL,
    sizes           json            NULL,    -- contoh: [[300,250],[300,600]]
    device          varchar(255)    NOT NULL DEFAULT 'desktop',  -- enum Device
    is_active       boolean         NOT NULL DEFAULT true,
    created_at      timestamp       NULL,
    updated_at      timestamp       NULL,
    CONSTRAINT ad_slots_domain_id_foreign
        FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE
);
CREATE INDEX ad_slots_device_index    ON ad_slots (device);
CREATE INDEX ad_slots_domain_id_index ON ad_slots (domain_id);
COMMENT ON TABLE  ad_slots          IS 'Slot iklan (GAM ad unit) pada sebuah domain.';
COMMENT ON COLUMN ad_slots.sizes    IS 'Array ukuran kreatif yang diizinkan, format JSON.';
COMMENT ON COLUMN ad_slots.device   IS 'Target device: desktop | mobile | tablet (enum Device).';

CREATE TABLE bidders (
    id          uuid            PRIMARY KEY,
    name        varchar(255)    NOT NULL,
    code        varchar(255)    NOT NULL,
    is_active   boolean         NOT NULL DEFAULT true,
    created_at  timestamp       NULL,
    updated_at  timestamp       NULL,
    CONSTRAINT bidders_code_unique UNIQUE (code)
);
COMMENT ON TABLE  bidders      IS 'Daftar bidder/SSP (mis. appnexus, rubicon, pubmatic).';
COMMENT ON COLUMN bidders.code IS 'Kode bidder unik sesuai Prebid bidder code.';

-- =============================================================================
--  3. METRIK AGREGAT HARIAN  (dashboard membaca dari sini, bukan raw event)
-- =============================================================================

CREATE TABLE slot_performance_daily (
    id          uuid            PRIMARY KEY,
    date        date            NOT NULL,
    domain_id   uuid            NOT NULL,
    slot_id     uuid            NOT NULL,
    device      varchar(255)    NOT NULL,                 -- enum Device
    ad_requests bigint          NOT NULL DEFAULT 0,
    impressions bigint          NOT NULL DEFAULT 0,
    revenue     numeric(14,4)   NOT NULL DEFAULT 0,
    ecpm        numeric(10,4)   NOT NULL DEFAULT 0,
    fill_rate   numeric(6,3)    NOT NULL DEFAULT 0,       -- persen
    viewability numeric(6,3)    NOT NULL DEFAULT 0,       -- persen
    created_at  timestamp       NULL,
    updated_at  timestamp       NULL,
    CONSTRAINT slot_performance_daily_domain_id_foreign
        FOREIGN KEY (domain_id) REFERENCES domains (id)   ON DELETE CASCADE,
    CONSTRAINT slot_performance_daily_slot_id_foreign
        FOREIGN KEY (slot_id)   REFERENCES ad_slots (id)  ON DELETE CASCADE
);
CREATE INDEX slot_performance_daily_date_index           ON slot_performance_daily (date);
CREATE INDEX slot_performance_daily_device_index         ON slot_performance_daily (device);
CREATE INDEX slot_performance_daily_domain_id_date_index ON slot_performance_daily (domain_id, date);
CREATE INDEX slot_performance_daily_slot_id_date_index   ON slot_performance_daily (slot_id, date);
COMMENT ON TABLE slot_performance_daily IS 'Agregat performa slot per hari per device. Sumber metrik Slot Performance & sebagian Dashboard Overview.';

CREATE TABLE bidder_performance_daily (
    id              uuid            PRIMARY KEY,
    date            date            NOT NULL,
    domain_id       uuid            NOT NULL,
    bidder_id       uuid            NOT NULL,
    bid_requests    bigint          NOT NULL DEFAULT 0,
    bid_responses   bigint          NOT NULL DEFAULT 0,
    bids_won        bigint          NOT NULL DEFAULT 0,
    timeouts        bigint          NOT NULL DEFAULT 0,
    errors          bigint          NOT NULL DEFAULT 0,
    avg_latency_ms  numeric(10,2)   NOT NULL DEFAULT 0,
    revenue         numeric(14,4)   NOT NULL DEFAULT 0,
    avg_cpm         numeric(10,4)   NOT NULL DEFAULT 0,
    created_at      timestamp       NULL,
    updated_at      timestamp       NULL,
    CONSTRAINT bidder_performance_daily_domain_id_foreign
        FOREIGN KEY (domain_id) REFERENCES domains (id)  ON DELETE CASCADE,
    CONSTRAINT bidder_performance_daily_bidder_id_foreign
        FOREIGN KEY (bidder_id) REFERENCES bidders (id)  ON DELETE CASCADE
);
CREATE INDEX bidder_performance_daily_date_index            ON bidder_performance_daily (date);
CREATE INDEX bidder_performance_daily_domain_id_date_index  ON bidder_performance_daily (domain_id, date);
CREATE INDEX bidder_performance_daily_bidder_id_date_index  ON bidder_performance_daily (bidder_id, date);
COMMENT ON TABLE bidder_performance_daily IS 'Agregat performa bidder per hari. Sumber metrik Bidding Monitoring & bidder health.';

CREATE TABLE prebid_auctions (
    id              uuid            PRIMARY KEY,
    auction_id      varchar(255)    NOT NULL,             -- id auction dari Prebid.js
    domain_id       uuid            NOT NULL,
    page_id         uuid            NULL,
    device          varchar(255)    NOT NULL,             -- enum Device
    started_at      timestamp       NOT NULL,
    duration_ms     integer         NOT NULL DEFAULT 0,
    bidder_count    integer         NOT NULL DEFAULT 0,
    bids_received   integer         NOT NULL DEFAULT 0,
    timeouts        integer         NOT NULL DEFAULT 0,
    errors          integer         NOT NULL DEFAULT 0,
    won_bidder      varchar(255)    NULL,
    cpm             numeric(10,4)   NOT NULL DEFAULT 0,
    status          varchar(255)    NOT NULL DEFAULT 'completed',
    created_at      timestamp       NULL,
    updated_at      timestamp       NULL,
    CONSTRAINT prebid_auctions_domain_id_foreign
        FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE,
    CONSTRAINT prebid_auctions_page_id_foreign
        FOREIGN KEY (page_id)   REFERENCES pages (id)   ON DELETE SET NULL
);
CREATE INDEX prebid_auctions_auction_id_index            ON prebid_auctions (auction_id);
CREATE INDEX prebid_auctions_device_index                ON prebid_auctions (device);
CREATE INDEX prebid_auctions_started_at_index            ON prebid_auctions (started_at);
CREATE INDEX prebid_auctions_domain_id_started_at_index  ON prebid_auctions (domain_id, started_at);
COMMENT ON TABLE prebid_auctions IS 'Event-level auction Prebid.js (durasi, timeout, error, pemenang). Sumber Prebid Health & auctions.';

CREATE TABLE gam_requests (
    id              uuid            PRIMARY KEY,
    domain_id       uuid            NOT NULL,
    page_id         uuid            NULL,
    device          varchar(255)    NOT NULL,             -- enum Device
    requested_at    timestamp       NOT NULL,
    ad_unit         varchar(255)    NOT NULL,
    status          varchar(255)    NOT NULL,             -- success | empty | failed
    latency_ms      integer         NOT NULL DEFAULT 0,
    http_status     integer         NOT NULL DEFAULT 200,
    line_item_id    varchar(255)    NULL,
    creative_id     varchar(255)    NULL,
    created_at      timestamp       NULL,
    updated_at      timestamp       NULL,
    CONSTRAINT gam_requests_domain_id_foreign
        FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE,
    CONSTRAINT gam_requests_page_id_foreign
        FOREIGN KEY (page_id)   REFERENCES pages (id)   ON DELETE SET NULL
);
CREATE INDEX gam_requests_device_index                 ON gam_requests (device);
CREATE INDEX gam_requests_requested_at_index           ON gam_requests (requested_at);
CREATE INDEX gam_requests_status_index                 ON gam_requests (status);
CREATE INDEX gam_requests_domain_id_requested_at_index ON gam_requests (domain_id, requested_at);
COMMENT ON TABLE  gam_requests        IS 'Event request ke Google Ad Manager. Sumber GAM Health & requests.';
COMMENT ON COLUMN gam_requests.status IS 'Hasil request GAM: success | empty | failed.';

CREATE TABLE network_ad_requests (
    id              uuid            PRIMARY KEY,
    domain_id       uuid            NOT NULL,
    page_id         uuid            NULL,
    device          varchar(255)    NOT NULL,             -- enum Device
    observed_at     timestamp       NOT NULL,
    resource_url    text            NOT NULL,
    vendor          varchar(255)    NULL,
    type            varchar(255)    NOT NULL DEFAULT 'script',  -- script | xhr | img | css | font
    size_bytes      bigint          NOT NULL DEFAULT 0,
    duration_ms     integer         NOT NULL DEFAULT 0,
    is_third_party  boolean         NOT NULL DEFAULT true,
    is_blocking     boolean         NOT NULL DEFAULT false,
    status_code     integer         NOT NULL DEFAULT 200,
    created_at      timestamp       NULL,
    updated_at      timestamp       NULL,
    CONSTRAINT network_ad_requests_domain_id_foreign
        FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE,
    CONSTRAINT network_ad_requests_page_id_foreign
        FOREIGN KEY (page_id)   REFERENCES pages (id)   ON DELETE SET NULL
);
CREATE INDEX network_ad_requests_device_index                ON network_ad_requests (device);
CREATE INDEX network_ad_requests_observed_at_index           ON network_ad_requests (observed_at);
CREATE INDEX network_ad_requests_vendor_index                ON network_ad_requests (vendor);
CREATE INDEX network_ad_requests_domain_id_observed_at_index ON network_ad_requests (domain_id, observed_at);
COMMENT ON TABLE network_ad_requests IS 'Network request dari script ads & third-party JS (berat/blocking). Sumber Network Ads.';

CREATE TABLE web_vitals_daily (
    id          uuid            PRIMARY KEY,
    date        date            NOT NULL,
    domain_id   uuid            NOT NULL,
    page_id     uuid            NULL,
    device      varchar(255)    NOT NULL,                 -- enum Device
    lcp         numeric(8,1)    NOT NULL DEFAULT 0,       -- ms
    inp         numeric(8,1)    NOT NULL DEFAULT 0,       -- ms
    cls         numeric(6,3)    NOT NULL DEFAULT 0,       -- unitless
    fcp         numeric(8,1)    NOT NULL DEFAULT 0,       -- ms
    ttfb        numeric(8,1)    NOT NULL DEFAULT 0,       -- ms
    tbt         numeric(8,1)    NOT NULL DEFAULT 0,       -- ms
    samples     bigint          NOT NULL DEFAULT 0,
    created_at  timestamp       NULL,
    updated_at  timestamp       NULL,
    CONSTRAINT web_vitals_daily_domain_id_foreign
        FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE,
    CONSTRAINT web_vitals_daily_page_id_foreign
        FOREIGN KEY (page_id)   REFERENCES pages (id)   ON DELETE SET NULL
);
CREATE INDEX web_vitals_daily_date_index           ON web_vitals_daily (date);
CREATE INDEX web_vitals_daily_device_index         ON web_vitals_daily (device);
CREATE INDEX web_vitals_daily_domain_id_date_index ON web_vitals_daily (domain_id, date);
CREATE INDEX web_vitals_daily_page_id_date_index   ON web_vitals_daily (page_id, date);
COMMENT ON TABLE web_vitals_daily IS 'Agregat Web Core Vitals harian (LCP, INP, CLS, FCP, TTFB, TBT). Sumber Web Core Vitals.';

-- =============================================================================
--  4. ALERTS & INSIGHTS
-- =============================================================================

CREATE TABLE alerts (
    id              uuid            PRIMARY KEY,
    severity        varchar(255)    NOT NULL,             -- enum Severity
    category        varchar(255)    NOT NULL,             -- enum AlertCategory
    metric          varchar(255)    NOT NULL,
    current_value   numeric(14,4)   NULL,
    threshold_value numeric(14,4)   NULL,
    entity_type     varchar(255)    NULL,                 -- polymorphic: domain|slot|bidder|page
    entity_id       uuid            NULL,                 -- polymorphic (tidak FK)
    entity_label    varchar(255)    NULL,
    domain_id       uuid            NULL,
    message         text            NOT NULL,
    suggested_action text           NULL,
    status          varchar(255)    NOT NULL DEFAULT 'open',  -- enum AlertStatus
    acknowledged_by uuid            NULL,
    acknowledged_at timestamp       NULL,
    triggered_at    timestamp       NOT NULL,
    created_at      timestamp       NULL,
    updated_at      timestamp       NULL,
    CONSTRAINT alerts_domain_id_foreign
        FOREIGN KEY (domain_id)       REFERENCES domains (id) ON DELETE SET NULL,
    CONSTRAINT alerts_acknowledged_by_foreign
        FOREIGN KEY (acknowledged_by) REFERENCES users (id)   ON DELETE SET NULL
);
CREATE INDEX alerts_severity_index         ON alerts (severity);
CREATE INDEX alerts_category_index         ON alerts (category);
CREATE INDEX alerts_status_index           ON alerts (status);
CREATE INDEX alerts_triggered_at_index     ON alerts (triggered_at);
CREATE INDEX alerts_status_severity_index  ON alerts (status, severity);
COMMENT ON TABLE  alerts             IS 'Alert hasil evaluasi threshold metrik. entity_* bersifat polymorphic (tanpa FK).';
COMMENT ON COLUMN alerts.entity_type IS 'Jenis entitas terkait: domain | slot | bidder | page (longgar, tanpa FK).';
COMMENT ON COLUMN alerts.entity_id   IS 'ID entitas terkait sesuai entity_type. Tidak diberi FK karena polymorphic.';

CREATE TABLE insights (
    id              uuid            PRIMARY KEY,
    title           varchar(255)    NOT NULL,
    description     text            NOT NULL,
    type            varchar(255)    NOT NULL DEFAULT 'optimization',  -- optimization | anomaly | trend
    impact          varchar(255)    NOT NULL DEFAULT 'medium',        -- low | medium | high
    related_metric  varchar(255)    NULL,
    domain_id       uuid            NULL,
    generated_at    timestamp       NOT NULL,
    created_at      timestamp       NULL,
    updated_at      timestamp       NULL,
    CONSTRAINT insights_domain_id_foreign
        FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE SET NULL
);
CREATE INDEX insights_generated_at_index ON insights (generated_at);
COMMENT ON TABLE insights IS 'Insight/rekomendasi optimasi yang dihasilkan sistem.';

-- =============================================================================
--  5. MODUL OPERASIONAL  (uptime, security inspector, ad-layout preview)
-- =============================================================================

CREATE TABLE server_checks (
    id                  uuid            PRIMARY KEY,
    domain_id           uuid            NOT NULL,
    checked_at          timestamp       NOT NULL,
    status              varchar(255)    NOT NULL,         -- up | down
    response_time_ms    integer         NOT NULL DEFAULT 0,
    http_status         integer         NOT NULL DEFAULT 200,
    region              varchar(255)    NULL,
    error_message       varchar(255)    NULL,
    created_at          timestamp       NULL,
    updated_at          timestamp       NULL,
    CONSTRAINT server_checks_domain_id_foreign
        FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE
);
CREATE INDEX server_checks_checked_at_index           ON server_checks (checked_at);
CREATE INDEX server_checks_status_index               ON server_checks (status);
CREATE INDEX server_checks_domain_id_checked_at_index ON server_checks (domain_id, checked_at);
CREATE INDEX server_checks_status_checked_at_index    ON server_checks (status, checked_at);
COMMENT ON TABLE server_checks IS 'Event uptime/response-time monitoring per domain. Uptime % dihitung dari log ini.';

CREATE TABLE security_scans (
    id              uuid            PRIMARY KEY,
    domain_id       uuid            NOT NULL,
    target_url      varchar(255)    NOT NULL,
    target_host     varchar(255)    NOT NULL,
    status          varchar(255)    NOT NULL DEFAULT 'completed',  -- completed | failed
    grade           varchar(255)    NULL,                 -- A..F
    score           smallint        NULL,                 -- 0..100
    results         json            NULL,                 -- DNS/SSL/headers/WHOIS/ports/...
    error           text            NULL,
    requested_by    uuid            NULL,
    started_at      timestamp       NULL,
    finished_at     timestamp       NULL,
    created_at      timestamp       NULL,
    updated_at      timestamp       NULL,
    CONSTRAINT security_scans_domain_id_foreign
        FOREIGN KEY (domain_id)    REFERENCES domains (id) ON DELETE CASCADE,
    CONSTRAINT security_scans_requested_by_foreign
        FOREIGN KEY (requested_by) REFERENCES users (id)   ON DELETE SET NULL
);
CREATE INDEX security_scans_domain_id_created_at_index ON security_scans (domain_id, created_at);
COMMENT ON TABLE  security_scans         IS 'Riwayat scan Security Site Inspector (1 baris per run per domain).';
COMMENT ON COLUMN security_scans.results IS 'Hasil terstruktur lengkap (DNS, SSL, headers, WHOIS, ports, ...) format JSON.';

CREATE TABLE page_previews (
    id                  uuid            PRIMARY KEY,
    domain_id           uuid            NOT NULL,
    page_id             uuid            NULL,
    device              varchar(255)    NOT NULL DEFAULT 'mobile',
    url                 varchar(255)    NOT NULL,
    status              varchar(255)    NOT NULL DEFAULT 'completed',  -- completed | failed
    image_path          varchar(255)    NULL,
    page_width          integer         NOT NULL DEFAULT 0,    -- CSS px
    page_height         integer         NOT NULL DEFAULT 0,    -- CSS px
    viewport_css_width  integer         NOT NULL DEFAULT 390,
    slot_count          smallint        NOT NULL DEFAULT 0,
    slots               json            NULL,                  -- enriched slot map
    header              json            NULL,                  -- header/nav rect
    error               text            NULL,
    requested_by        uuid            NULL,
    captured_at         timestamp       NULL,
    created_at          timestamp       NULL,
    updated_at          timestamp       NULL,
    CONSTRAINT page_previews_domain_id_foreign
        FOREIGN KEY (domain_id)    REFERENCES domains (id) ON DELETE CASCADE,
    CONSTRAINT page_previews_page_id_foreign
        FOREIGN KEY (page_id)      REFERENCES pages (id)   ON DELETE SET NULL,
    CONSTRAINT page_previews_requested_by_foreign
        FOREIGN KEY (requested_by) REFERENCES users (id)   ON DELETE SET NULL
);
CREATE INDEX page_previews_domain_id_created_at_index ON page_previews (domain_id, created_at);
COMMENT ON TABLE page_previews IS 'Capture ad-layout preview (screenshot mobile + geometri slot GPT/Prebid) hasil Playwright.';

-- =============================================================================
--  6. TABEL SISTEM LARAVEL  (cache, queue) — bukan domain bisnis
-- =============================================================================

CREATE TABLE cache (
    key         varchar(255)    PRIMARY KEY,
    value       text            NOT NULL,
    expiration  bigint          NOT NULL
);
CREATE INDEX cache_expiration_index ON cache (expiration);

CREATE TABLE cache_locks (
    key         varchar(255)    PRIMARY KEY,
    owner       varchar(255)    NOT NULL,
    expiration  bigint          NOT NULL
);

CREATE TABLE jobs (
    id              bigserial       PRIMARY KEY,
    queue           varchar(255)    NOT NULL,
    payload         text            NOT NULL,
    attempts        smallint        NOT NULL,
    reserved_at     integer         NULL,
    available_at    integer         NOT NULL,
    created_at      integer         NOT NULL
);
CREATE INDEX jobs_queue_index ON jobs (queue);

CREATE TABLE job_batches (
    id              varchar(255)    PRIMARY KEY,
    name            varchar(255)    NOT NULL,
    total_jobs      integer         NOT NULL,
    pending_jobs    integer         NOT NULL,
    failed_jobs     integer         NOT NULL,
    failed_job_ids  text            NOT NULL,
    options         text            NULL,
    cancelled_at    integer         NULL,
    created_at      integer         NOT NULL,
    finished_at     integer         NULL
);

CREATE TABLE failed_jobs (
    id          bigserial       PRIMARY KEY,
    uuid        varchar(255)    NOT NULL,
    connection  text            NOT NULL,
    queue       text            NOT NULL,
    payload     text            NOT NULL,
    exception   text            NOT NULL,
    failed_at   timestamp       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid)
);

COMMIT;

-- =============================================================================
--  END OF SCHEMA
-- =============================================================================
