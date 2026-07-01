<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AlertCategory;
use App\Enums\AlertStatus;
use App\Enums\Role;
use App\Enums\Severity;
use App\Models\AdSlot;
use App\Models\Alert;
use App\Models\Bidder;
use App\Models\Domain;
use App\Models\Insight;
use App\Models\Page;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Realistic demo dataset for the three KG Media properties so every dashboard
 * module renders meaningful data end-to-end.
 */
class DemoSeeder extends Seeder
{
    private CarbonImmutable $from;

    private CarbonImmutable $to;

    /** @var string[] */
    private array $devices = ['desktop', 'mobile'];

    public function run(): void
    {
        mt_srand(2026); // deterministic dataset

        // Cover the dashboard's default 30-day window (today = 2026-06-15).
        $this->to = CarbonImmutable::parse('2026-06-15');
        $this->from = $this->to->subDays(30);

        $this->seedUsers();
        $domains = $this->seedDomains();
        [$pages, $slots] = $this->seedPagesAndSlots($domains);
        $bidders = $this->seedBidders();

        $this->seedSlotPerformance($domains, $slots);
        $this->seedBidderPerformance($domains, $bidders);
        $this->seedWebVitals($domains, $pages);
        $this->seedPrebidAuctions($domains, $pages);
        $this->seedGamRequests($domains, $pages);
        $this->seedNetworkAds($domains, $pages);
        $this->seedServerChecks($domains);
        $this->seedAlertsAndInsights($domains);

        mt_srand();
    }

    /** @return CarbonImmutable[] */
    private function dateRange(): array
    {
        $dates = [];
        for ($d = $this->from; $d->lessThanOrEqualTo($this->to); $d = $d->addDay()) {
            $dates[] = $d;
        }

        return $dates;
    }

    private function rand(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    private function randf(float $min, float $max, int $precision = 2): float
    {
        return round($min + (mt_rand() / mt_getrandmax()) * ($max - $min), $precision);
    }

    private function seedUsers(): void
    {
        $users = [
            ['name' => 'Admin KG', 'email' => 'admin@kgmedia.io', 'role' => Role::Admin],
            ['name' => 'Rama Revenue', 'email' => 'rev@kgmedia.io', 'role' => Role::ProgrammaticRevenue],
            ['name' => 'Dina AdOps', 'email' => 'adops@kgmedia.io', 'role' => Role::AdOps],
            ['name' => 'Tech Collab', 'email' => 'tech.collab@kgmedia.io', 'role' => Role::Tech],
            ['name' => 'Vito Viewer', 'email' => 'viewer@kgmedia.io', 'role' => Role::Viewer],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make('password'),
                    'role' => $u['role']->value,
                ],
            );
        }
    }

    /** @return array<string, Domain> */
    private function seedDomains(): array
    {
        $defs = [
            'kompastv' => ['name' => 'Kompas TV', 'url' => 'https://www.kompas.tv'],
            'parapuan' => ['name' => 'Parapuan', 'url' => 'https://www.parapuan.co'],
            'sonora' => ['name' => 'Sonora', 'url' => 'https://www.sonora.id'],
        ];

        $domains = [];
        foreach ($defs as $key => $def) {
            $domains[$key] = Domain::updateOrCreate(
                ['url' => $def['url']],
                ['name' => $def['name'], 'is_active' => true],
            );
        }

        return $domains;
    }

    /**
     * @param  array<string, Domain>  $domains
     * @return array{0: array<int, Page>, 1: array<int, AdSlot>}
     */
    private function seedPagesAndSlots(array $domains): array
    {
        $pagePaths = [
            'kompastv' => [
                ['/', 'Beranda Kompas TV'],
                ['/live', 'Live Streaming'],
                ['/news', 'Berita Terkini'],
                ['/program/sapa-indonesia', 'Sapa Indonesia'],
                ['/video/highlight', 'Video Highlight'],
            ],
            'parapuan' => [
                ['/', 'Beranda Parapuan'],
                ['/karier', 'Karier'],
                ['/lifestyle', 'Lifestyle'],
                ['/parenting', 'Parenting'],
                ['/wellness', 'Wellness'],
            ],
            'sonora' => [
                ['/', 'Beranda Sonora'],
                ['/news', 'News'],
                ['/entertainment', 'Entertainment'],
                ['/lifestyle', 'Lifestyle'],
                ['/talks', 'Sonora Talks'],
            ],
        ];

        $slotDefs = [
            ['Billboard Top', 'desktop', [[970, 250], [728, 90]]],
            ['Medium Rectangle', 'desktop', [[300, 250]]],
            ['Sticky Sidebar', 'desktop', [[300, 600]]],
            ['Mobile Anchor', 'mobile', [[320, 50], [320, 100]]],
            ['In-Article Mobile', 'mobile', [[300, 250]]],
        ];

        $pages = [];
        $slots = [];

        foreach ($domains as $key => $domain) {
            foreach ($pagePaths[$key] as $p) {
                $pages[] = Page::updateOrCreate(
                    ['domain_id' => $domain->id, 'path' => $p[0]],
                    ['title' => $p[1]],
                );
            }

            foreach ($slotDefs as $i => $s) {
                $slots[] = AdSlot::updateOrCreate(
                    ['domain_id' => $domain->id, 'name' => $s[0]],
                    [
                        'ad_unit_path' => "/{$this->adNetworkCode($key)}/{$key}_".Str::slug($s[0], '_'),
                        'sizes' => $s[2],
                        'device' => $s[1],
                        'is_active' => true,
                    ],
                );
            }
        }

        return [$pages, $slots];
    }

    private function adNetworkCode(string $key): string
    {
        return match ($key) {
            'kompastv' => '21755500000',
            'parapuan' => '21755500001',
            default => '21755500002',
        };
    }

    /** @return array<int, Bidder> */
    private function seedBidders(): array
    {
        $defs = [
            ['Google AdX', 'adx'],
            ['Amazon TAM', 'amazon'],
            ['Magnite (Rubicon)', 'rubicon'],
            ['PubMatic', 'pubmatic'],
            ['OpenX', 'openx'],
            ['Index Exchange', 'ix'],
            ['Criteo', 'criteo'],
            ['Sovrn', 'sovrn'],
            ['Smart AdServer', 'smartadserver'],
            ['AppNexus (Xandr)', 'appnexus'],
        ];

        $bidders = [];
        foreach ($defs as $d) {
            $bidders[] = Bidder::updateOrCreate(
                ['code' => $d[1]],
                ['name' => $d[0], 'is_active' => true],
            );
        }

        return $bidders;
    }

    /**
     * @param  array<string, Domain>  $domains
     * @param  array<int, AdSlot>  $slots
     */
    private function seedSlotPerformance(array $domains, array $slots): void
    {
        $now = now();
        $rows = [];

        foreach ($this->dateRange() as $date) {
            // Weekends dip slightly; create a believable trend.
            $weekendFactor = in_array((int) $date->dayOfWeek, [0, 6], true) ? 0.82 : 1.0;

            foreach ($slots as $slot) {
                $adRequests = (int) ($this->rand(6000, 48000) * $weekendFactor);
                $fillRate = $this->randf(58, 94, 3);
                $impressions = (int) round($adRequests * $fillRate / 100);
                $ecpm = $this->randf(0.35, 3.6, 4);
                $revenue = round($impressions / 1000 * $ecpm, 4);

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'date' => $date->toDateString(),
                    'domain_id' => $slot->domain_id,
                    'slot_id' => $slot->id,
                    'device' => $slot->device,
                    'ad_requests' => $adRequests,
                    'impressions' => $impressions,
                    'revenue' => $revenue,
                    'ecpm' => $ecpm,
                    'fill_rate' => $fillRate,
                    'viewability' => $this->randf(48, 82, 3),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertChunked('slot_performance_daily', $rows);
    }

    /**
     * @param  array<string, Domain>  $domains
     * @param  array<int, Bidder>  $bidders
     */
    private function seedBidderPerformance(array $domains, array $bidders): void
    {
        $now = now();
        $rows = [];

        foreach ($this->dateRange() as $date) {
            foreach ($domains as $domain) {
                foreach ($bidders as $i => $bidder) {
                    // Make one bidder consistently unhealthy (high timeout) for alerts.
                    $unhealthy = $bidder->code === 'smartadserver';

                    $bidRequests = $this->rand(15000, 220000);
                    $responseRate = $unhealthy ? $this->randf(18, 38) : $this->randf(34, 72);
                    $bidResponses = (int) round($bidRequests * $responseRate / 100);
                    $bidsWon = (int) round($bidResponses * $this->randf(6, 22) / 100);
                    $timeoutRate = $unhealthy ? $this->randf(14, 28) : $this->randf(1, 9);
                    $timeouts = (int) round($bidRequests * $timeoutRate / 100);
                    $errors = (int) round($bidRequests * $this->randf(0.1, 1.5) / 100);
                    $cpm = $this->randf(0.25, 2.8, 4);
                    $revenue = round($bidsWon / 1000 * $cpm, 4);

                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'date' => $date->toDateString(),
                        'domain_id' => $domain->id,
                        'bidder_id' => $bidder->id,
                        'bid_requests' => $bidRequests,
                        'bid_responses' => $bidResponses,
                        'bids_won' => $bidsWon,
                        'timeouts' => $timeouts,
                        'errors' => $errors,
                        'avg_latency_ms' => $unhealthy ? $this->randf(380, 720) : $this->randf(70, 380),
                        'revenue' => $revenue,
                        'avg_cpm' => $cpm,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        $this->insertChunked('bidder_performance_daily', $rows);
    }

    /**
     * @param  array<string, Domain>  $domains
     * @param  array<int, Page>  $pages
     */
    private function seedWebVitals(array $domains, array $pages): void
    {
        $now = now();
        $rows = [];

        foreach ($this->dateRange() as $date) {
            foreach ($pages as $page) {
                foreach ($this->devices as $device) {
                    // Mobile vitals are worse than desktop.
                    $mobile = $device === 'mobile';

                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'date' => $date->toDateString(),
                        'domain_id' => $page->domain_id,
                        'page_id' => $page->id,
                        'device' => $device,
                        'lcp' => $this->randf($mobile ? 2200 : 1500, $mobile ? 4400 : 3000, 1),
                        'inp' => $this->randf($mobile ? 160 : 90, $mobile ? 480 : 260, 1),
                        'cls' => $this->randf(0.02, $mobile ? 0.28 : 0.16, 3),
                        'fcp' => $this->randf($mobile ? 1200 : 800, $mobile ? 2800 : 2000, 1),
                        'ttfb' => $this->randf($mobile ? 350 : 200, $mobile ? 1300 : 900, 1),
                        'tbt' => $this->randf($mobile ? 180 : 90, $mobile ? 560 : 320, 1),
                        'samples' => $this->rand(180, 5200),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        $this->insertChunked('web_vitals_daily', $rows);
    }

    /**
     * @param  array<string, Domain>  $domains
     * @param  array<int, Page>  $pages
     */
    private function seedPrebidAuctions(array $domains, array $pages): void
    {
        $now = now();
        $rows = [];
        $winners = ['adx', 'rubicon', 'pubmatic', 'openx', 'ix', 'criteo', 'amazon'];

        $pagesByDomain = [];
        foreach ($pages as $p) {
            $pagesByDomain[$p->domain_id][] = $p->id;
        }

        foreach ($this->dateRange() as $date) {
            foreach ($domains as $domain) {
                $perDay = $this->rand(12, 24);
                for ($i = 0; $i < $perDay; $i++) {
                    $startedAt = $date->addHours($this->rand(0, 23))->addMinutes($this->rand(0, 59));
                    $duration = $this->rand(280, 1900);
                    $roll = $this->rand(1, 100);
                    $status = $roll > 92 ? 'timeout' : ($roll > 88 ? 'error' : 'completed');
                    $timeouts = $status === 'timeout' ? $this->rand(1, 4) : ($this->rand(0, 100) > 80 ? 1 : 0);
                    $errors = $status === 'error' ? $this->rand(1, 3) : 0;
                    if ($status === 'timeout') {
                        $duration = $this->rand(1900, 2600);
                    }

                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'auction_id' => 'auc_'.Str::lower(Str::random(12)),
                        'domain_id' => $domain->id,
                        'page_id' => $pagesByDomain[$domain->id][array_rand($pagesByDomain[$domain->id])] ?? null,
                        'device' => $this->devices[array_rand($this->devices)],
                        'started_at' => $startedAt,
                        'duration_ms' => $duration,
                        'bidder_count' => $this->rand(5, 12),
                        'bids_received' => $this->rand(2, 10),
                        'timeouts' => $timeouts,
                        'errors' => $errors,
                        'won_bidder' => $status === 'completed' ? $winners[array_rand($winners)] : null,
                        'cpm' => $status === 'completed' ? $this->randf(0.3, 4.2, 4) : 0,
                        'status' => $status,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        $this->insertChunked('prebid_auctions', $rows);
    }

    /**
     * @param  array<string, Domain>  $domains
     * @param  array<int, Page>  $pages
     */
    private function seedGamRequests(array $domains, array $pages): void
    {
        $now = now();
        $rows = [];

        $pagesByDomain = [];
        foreach ($pages as $p) {
            $pagesByDomain[$p->domain_id][] = $p->id;
        }

        foreach ($this->dateRange() as $date) {
            foreach ($domains as $domain) {
                $perDay = $this->rand(14, 26);
                for ($i = 0; $i < $perDay; $i++) {
                    $roll = $this->rand(1, 100);
                    $status = $roll > 95 ? 'failed' : ($roll > 84 ? 'empty' : 'success');
                    $http = $status === 'failed' ? ($this->rand(0, 1) ? 502 : 504) : 200;

                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'domain_id' => $domain->id,
                        'page_id' => $pagesByDomain[$domain->id][array_rand($pagesByDomain[$domain->id])] ?? null,
                        'device' => $this->devices[array_rand($this->devices)],
                        'requested_at' => $date->addHours($this->rand(0, 23))->addMinutes($this->rand(0, 59)),
                        'ad_unit' => 'div-gpt-ad-'.$this->rand(1000, 9999),
                        'status' => $status,
                        'latency_ms' => $status === 'failed' ? $this->rand(800, 3000) : $this->rand(40, 420),
                        'http_status' => $http,
                        'line_item_id' => $status === 'success' ? (string) $this->rand(5000000000, 5999999999) : null,
                        'creative_id' => $status === 'success' ? (string) $this->rand(138000000000, 138999999999) : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        $this->insertChunked('gam_requests', $rows);
    }

    /**
     * @param  array<string, Domain>  $domains
     * @param  array<int, Page>  $pages
     */
    private function seedNetworkAds(array $domains, array $pages): void
    {
        $now = now();
        $rows = [];

        $resources = [
            ['https://securepubads.g.doubleclick.net/tag/js/gpt.js', 'Google GPT', 'script', true],
            ['https://www.googletagservices.com/tag/js/gpt.js', 'Google GPT', 'script', true],
            ['https://c.amazon-adsystem.com/aax2/apstag.js', 'Amazon TAM', 'script', true],
            ['https://static.criteo.net/js/ld/publishertag.prebid.js', 'Criteo', 'script', true],
            ['https://ads.pubmatic.com/AdServer/js/pwt/pubmatic.js', 'PubMatic', 'script', true],
            ['https://js-sec.indexww.com/ht/p/index.js', 'Index Exchange', 'script', true],
            ['https://acdn.adnxs.com/prebid/prebid.js', 'AppNexus', 'script', true],
            ['https://cdn.confiant-integrations.net/confiant.js', 'Confiant', 'script', true],
            ['https://tags.crwdcntrl.net/c/segment.js', 'Lotame', 'xhr', true],
            ['https://www.google-analytics.com/analytics.js', 'Google Analytics', 'script', true],
            ['https://connect.facebook.net/en_US/fbevents.js', 'Meta Pixel', 'script', true],
            ['https://static.doubleclick.net/instream/ad_status.js', 'DoubleClick', 'script', true],
            ['/assets/app.bundle.js', 'First Party', 'script', false],
            ['/assets/styles.css', 'First Party', 'css', false],
        ];

        foreach ($this->dateRange() as $date) {
            // Sample a few days to keep the table focused on heavy resources.
            if ($this->rand(0, 100) > 45) {
                continue;
            }
            foreach ($domains as $domain) {
                foreach ($resources as $r) {
                    $heavy = $r[1] !== 'First Party';
                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'domain_id' => $domain->id,
                        'page_id' => null,
                        'device' => $this->devices[array_rand($this->devices)],
                        'observed_at' => $date->addHours($this->rand(0, 23)),
                        'resource_url' => $r[0],
                        'vendor' => $r[1],
                        'type' => $r[2],
                        'size_bytes' => $heavy ? $this->rand(28_000, 320_000) : $this->rand(8_000, 90_000),
                        'duration_ms' => $heavy ? $this->rand(40, 680) : $this->rand(10, 120),
                        'is_third_party' => $r[3],
                        'is_blocking' => $heavy && $this->rand(0, 100) > 70,
                        'status_code' => $this->rand(0, 100) > 97 ? 404 : 200,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        $this->insertChunked('network_ad_requests', $rows);
    }

    /**
     * Hourly uptime/response checks per domain with a few injected incidents.
     *
     * @param  array<string, Domain>  $domains
     */
    private function seedServerChecks(array $domains): void
    {
        $now = now();
        $rows = [];
        $regions = ['jakarta', 'singapore'];

        // Baseline latency & reliability per property (sonora slightly worse).
        $profile = [
            'kompastv' => ['lat' => [110, 320], 'downChance' => 1],
            'parapuan' => ['lat' => [130, 360], 'downChance' => 2],
            'sonora' => ['lat' => [160, 480], 'downChance' => 4],
        ];

        foreach ($domains as $key => $domain) {
            $p = $profile[$key];

            foreach ($this->dateRange() as $date) {
                for ($hour = 0; $hour < 24; $hour++) {
                    $checkedAt = $date->addHours($hour)->addMinutes($this->rand(0, 59));

                    // Random outage (per-mille) — produces realistic >99% uptime.
                    $isDown = $this->rand(1, 1000) <= $p['downChance'] * 2;

                    if ($isDown) {
                        $rows[] = [
                            'id' => (string) Str::uuid(),
                            'domain_id' => $domain->id,
                            'checked_at' => $checkedAt,
                            'status' => 'down',
                            'response_time_ms' => $this->rand(0, 1200),
                            'http_status' => $this->rand(0, 1) ? 502 : 503,
                            'region' => $regions[array_rand($regions)],
                            'error_message' => $this->rand(0, 1)
                                ? 'Connection timed out'
                                : 'Upstream returned 5xx',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        continue;
                    }

                    // Occasional latency spike during peak hours (19:00-22:00).
                    $peak = $hour >= 19 && $hour <= 22;
                    $max = $peak ? (int) ($p['lat'][1] * 1.8) : $p['lat'][1];

                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'domain_id' => $domain->id,
                        'checked_at' => $checkedAt,
                        'status' => 'up',
                        'response_time_ms' => $this->rand($p['lat'][0], $max),
                        'http_status' => 200,
                        'region' => $regions[array_rand($regions)],
                        'error_message' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        $this->insertChunked('server_checks', $rows);
    }

    /**
     * @param  array<string, Domain>  $domains
     */
    private function seedAlertsAndInsights(array $domains): void
    {
        $k = $domains['kompastv'];
        $p = $domains['parapuan'];
        $s = $domains['sonora'];

        $alerts = [
            [Severity::Critical, AlertCategory::Bidding, 'timeout_rate', 27.4, 25, 'bidder', 'Smart AdServer', $k->id, 'Bidder Smart AdServer timeout rate 27.4% melebihi ambang kritis 25%.', 'Periksa konfigurasi endpoint dan turunkan timeout bidder atau nonaktifkan sementara.', AlertStatus::Open],
            [Severity::High, AlertCategory::Prebid, 'auction_duration', 2180, 2000, 'domain', 'Kompas TV', $k->id, 'Rata-rata durasi auction Prebid 2180ms melewati ambang kritis 2000ms.', 'Kurangi jumlah bidder lambat dan evaluasi setTimeout Prebid.', AlertStatus::Open],
            [Severity::High, AlertCategory::Gam, 'failed_request_rate', 6.2, 5, 'domain', 'Parapuan', $p->id, 'GAM failed request rate 6.2% di atas ambang peringatan 5%.', 'Cek koneksi ke Google Ad Manager dan status line item.', AlertStatus::Open],
            [Severity::Medium, AlertCategory::WebVitals, 'lcp', 4180, 4000, 'page', 'Parapuan /parenting', $p->id, 'LCP mobile halaman /parenting 4180ms melebihi ambang kritis 4000ms.', 'Optimalkan gambar hero dan lazy-load iklan below-the-fold.', AlertStatus::Open],
            [Severity::Medium, AlertCategory::WebVitals, 'cls', 0.27, 0.25, 'page', 'Sonora /entertainment', $s->id, 'CLS mobile 0.27 melebihi ambang kritis 0.25.', 'Reservasi ruang untuk slot iklan agar layout tidak bergeser.', AlertStatus::Open],
            [Severity::High, AlertCategory::Revenue, 'revenue_drop', -18.5, -15, 'domain', 'Sonora', $s->id, 'Revenue turun 18.5% dibanding periode sebelumnya.', 'Bandingkan fill rate dan eCPM per bidder untuk menemukan penyebab.', AlertStatus::Open],
            [Severity::Low, AlertCategory::Slot, 'fill_rate', 64.0, 70, 'slot', 'Sticky Sidebar', $k->id, 'Fill rate slot Sticky Sidebar 64% di bawah ambang 70%.', 'Tambahkan demand partner atau aktifkan floor price dinamis.', AlertStatus::Acknowledged],
            [Severity::Medium, AlertCategory::Network, 'heavy_script', 312, 250, 'domain', 'Kompas TV', $k->id, 'Script pihak ketiga 312KB melebihi anggaran 250KB.', 'Tunda pemuatan script non-kritis dan aktifkan async.', AlertStatus::Open],
            [Severity::Low, AlertCategory::Bidding, 'no_bid_rate', 58.0, 60, 'bidder', 'Sovrn', $p->id, 'No-bid rate Sovrn mendekati ambang.', 'Pantau performa dan pertimbangkan penyesuaian floor.', AlertStatus::Resolved],
            [Severity::Critical, AlertCategory::Gam, 'failed_request_rate', 16.8, 15, 'domain', 'Sonora', $s->id, 'GAM failed request rate 16.8% melewati ambang kritis 15%.', 'Eskalasi ke tim AdOps; periksa kuota dan status akun GAM.', AlertStatus::Open],
            [Severity::High, AlertCategory::Server, 'uptime', 98.6, 99, 'domain', 'Sonora', $s->id, 'Uptime Sonora 98.6% di bawah ambang 99% (beberapa insiden 5xx).', 'Periksa health upstream origin dan konfigurasi load balancer.', AlertStatus::Open],
            [Severity::Medium, AlertCategory::Server, 'response_time', 612, 500, 'domain', 'Sonora', $s->id, 'Rata-rata response time Sonora 612ms melebihi ambang 500ms saat peak.', 'Aktifkan caching/CDN untuk halaman berat dan audit query lambat.', AlertStatus::Open],
        ];

        foreach ($alerts as $a) {
            Alert::create([
                'severity' => $a[0]->value,
                'category' => $a[1]->value,
                'metric' => $a[2],
                'current_value' => $a[3],
                'threshold_value' => $a[4],
                'entity_type' => $a[5],
                'entity_label' => $a[6],
                'domain_id' => $a[7],
                'message' => $a[8],
                'suggested_action' => $a[9],
                'status' => $a[10]->value,
                'triggered_at' => $this->to->subDays($this->rand(0, 6))->subHours($this->rand(0, 23)),
            ]);
        }

        $insights = [
            ['Optimasi Sticky Sidebar Kompas TV', 'Slot Sticky Sidebar memiliki viewability tinggi namun fill rate rendah. Menambahkan 2 demand partner berpotensi menaikkan revenue ~12%.', 'optimization', 'high', 'fill_rate', $k->id],
            ['Bidder lambat menaikkan durasi auction', 'Smart AdServer berkontribusi pada timeout. Menonaktifkannya dapat menurunkan rata-rata durasi auction di bawah 1500ms.', 'anomaly', 'high', 'auction_duration', $k->id],
            ['Tren eCPM mobile Parapuan naik', 'eCPM mobile Parapuan naik 9% dalam 14 hari terakhir, dipimpin oleh PubMatic dan Index Exchange.', 'trend', 'medium', 'ecpm', $p->id],
            ['CLS mobile perlu perhatian', 'Beberapa halaman Sonora memiliki CLS mobile mendekati ambang. Reservasi ruang iklan akan memperbaiki skor Core Web Vitals.', 'optimization', 'medium', 'cls', $s->id],
            ['Script pihak ketiga membebani LCP', 'Total transfer script ads di Kompas TV tinggi. Defer script non-kritis dapat memangkas LCP ~300ms.', 'optimization', 'medium', 'lcp', $k->id],
            ['Insiden uptime Sonora terkonsentrasi saat peak', 'Mayoritas insiden 5xx Sonora terjadi pada jam 19:00-22:00. Auto-scaling origin saat peak dapat menstabilkan uptime di atas 99.9%.', 'anomaly', 'high', 'uptime', $s->id],
        ];

        foreach ($insights as $i) {
            Insight::create([
                'title' => $i[0],
                'description' => $i[1],
                'type' => $i[2],
                'impact' => $i[3],
                'related_metric' => $i[4],
                'domain_id' => $i[5],
                'generated_at' => $this->to->subDays($this->rand(0, 5)),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function insertChunked(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
        $this->command?->info(sprintf('Seeded %d rows into %s', count($rows), $table));
    }
}
