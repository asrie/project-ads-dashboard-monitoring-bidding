<?php

declare(strict_types=1);

namespace App\Services\Gam;

use App\Models\AdSlot;
use App\Models\Domain;
use App\Models\SlotPerformanceDaily;
use Carbon\CarbonInterface;
use Google\AdsApi\AdManager\AdManagerServices;
use Google\AdsApi\AdManager\AdManagerSessionBuilder;
use Google\AdsApi\AdManager\v202605\Column;
use Google\AdsApi\AdManager\v202605\Date as GamDate;
use Google\AdsApi\AdManager\v202605\DateRangeType;
use Google\AdsApi\AdManager\v202605\Dimension;
use Google\AdsApi\AdManager\v202605\ExportFormat;
use Google\AdsApi\AdManager\v202605\ReportJob;
use Google\AdsApi\AdManager\v202605\ReportQuery;
use Google\AdsApi\AdManager\v202605\ReportQueryAdUnitView;
use Google\AdsApi\AdManager\v202605\ReportService;
use Google\AdsApi\AdManager\Util\v202605\ReportDownloader;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use RuntimeException;

/**
 * Pulls a real Google Ad Manager report (impressions, ad requests, revenue,
 * Active View viewability per ad unit / date / device) and upserts it into
 * slot_performance_daily.
 *
 * Refinements:
 *  - Resolves each ad unit to a Domain (explicit map → heuristic token match → --domain).
 *  - Auto-creates the AdSlot when an ad unit has no local match (toggle with $autoCreate).
 *  - Captures viewability from the Active View column.
 *
 * Auth: OAuth2 service account (services.gam.service_account_json) whose email
 * is added as a user in the GAM network (services.gam.network_code).
 * Requires PHP ext-soap + ext-sodium at runtime (present in the Docker image).
 */
class GamReportService
{
    private const SCOPE = 'https://www.googleapis.com/auth/dfp';

    public function configured(): bool
    {
        return ! empty(config('services.gam.network_code'))
            && ! empty(config('services.gam.service_account_json'));
    }

    /**
     * Run + ingest a report for the given (inclusive) date range.
     *
     * @return array{rows:int, matched:int, created:int, upserted:int, unmatched:int, unresolved:int, samples:string[]}
     */
    public function sync(CarbonInterface $from, CarbonInterface $to, ?string $domainId = null, bool $autoCreate = true): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('GAM is not configured (network code / service account missing).');
        }

        $csv = $this->runReport($from, $to);

        return $this->ingestCsv($csv, $domainId, $autoCreate);
    }

    private function session(): \Google\AdsApi\AdManager\AdManagerSession
    {
        $credential = (new OAuth2TokenBuilder())
            ->withJsonKeyFilePath($this->resolveKeyFilePath())
            ->withScopes([self::SCOPE])
            ->build();

        return (new AdManagerSessionBuilder())
            ->withApplicationName((string) config('services.gam.application_name', 'Ads Dashboard'))
            ->withNetworkCode((string) config('services.gam.network_code'))
            ->withOAuth2Credential($credential)
            ->build();
    }

    private function runReport(CarbonInterface $from, CarbonInterface $to): string
    {
        $session = $this->session();
        $services = new AdManagerServices();
        /** @var ReportService $reportService */
        $reportService = $services->get($session, ReportService::class);

        $query = new ReportQuery();
        $query->setDimensions([
            Dimension::DATE,
            Dimension::AD_UNIT_NAME,
            Dimension::PARENT_AD_UNIT_NAME,
            Dimension::DEVICE_CATEGORY_NAME,
        ]);
        $query->setColumns([
            Column::TOTAL_AD_REQUESTS,
            Column::AD_SERVER_IMPRESSIONS,
            Column::AD_SERVER_CPM_AND_CPC_REVENUE,
            Column::AD_SERVER_ACTIVE_VIEW_VIEWABLE_IMPRESSIONS_RATE,
        ]);
        $query->setAdUnitView(ReportQueryAdUnitView::FLAT);
        $query->setDateRangeType(DateRangeType::CUSTOM_DATE);
        $query->setStartDate($this->gamDate($from));
        $query->setEndDate($this->gamDate($to));

        $job = new ReportJob();
        $job->setReportQuery($query);
        $job = $reportService->runReportJob($job);

        $downloader = new ReportDownloader($reportService, $job->getId());
        $downloader->waitForReportToFinish();

        $gzPath = tempnam(sys_get_temp_dir(), 'gam_report_').'.csv.gz';
        $downloader->downloadReport(ExportFormat::CSV_DUMP, $gzPath);
        $raw = (string) file_get_contents($gzPath);
        @unlink($gzPath);

        $decoded = @gzdecode($raw);

        return $decoded !== false ? $decoded : $raw;
    }

    /**
     * Parse a GAM CSV report and upsert it. Public so a downloaded report can be
     * replayed/tested without re-hitting the API.
     *
     * @return array{rows:int, matched:int, created:int, upserted:int, unmatched:int, unresolved:int, samples:string[]}
     */
    public function ingestCsv(string $csv, ?string $forcedDomainId = null, bool $autoCreate = true): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        if (count($lines) < 2) {
            return $this->emptyResult();
        }

        $header = str_getcsv(array_shift($lines));
        $idx = [
            'date' => $this->col($header, 'DATE'),
            'ad_unit' => $this->col($header, 'AD_UNIT_NAME'),
            'parent' => $this->colOptional($header, 'PARENT_AD_UNIT_NAME'),
            'device' => $this->col($header, 'DEVICE_CATEGORY_NAME'),
            'requests' => $this->col($header, 'TOTAL_AD_REQUESTS'),
            'impressions' => $this->col($header, 'AD_SERVER_IMPRESSIONS'),
            'revenue' => $this->col($header, 'AD_SERVER_CPM_AND_CPC_REVENUE'),
            'viewability' => $this->colOptional($header, 'ACTIVE_VIEW_VIEWABLE_IMPRESSIONS_RATE'),
        ];

        $forcedDomain = $forcedDomainId ? Domain::find($forcedDomainId) : null;
        $domainIndex = $this->domainIndex();
        $domainMap = $this->normalizedMap();

        $rows = $matched = $upserted = $unmatched = $unresolved = 0;
        $created = [];
        $samples = [];
        /** @var array<string, AdSlot> $slotCache */
        $slotCache = [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $rows++;
            $cells = str_getcsv($line);

            $adUnit = trim($cells[$idx['ad_unit']] ?? '');
            $parent = $idx['parent'] !== null ? trim($cells[$idx['parent']] ?? '') : '';
            $device = $this->device($cells[$idx['device']] ?? '');

            $domain = $forcedDomain ?: $this->resolveDomain($adUnit, $parent, $domainIndex, $domainMap);
            if (! $domain) {
                $unresolved++;
                $this->sample($samples, $parent !== '' ? "$parent / $adUnit" : $adUnit);

                continue;
            }

            $cacheKey = $domain->id.'|'.$adUnit;
            $slot = $slotCache[$cacheKey] ?? null;
            if (! $slot) {
                $slot = $this->findSlot($adUnit, $domain);
                if (! $slot && $autoCreate && $adUnit !== '') {
                    $slot = AdSlot::firstOrCreate(
                        ['domain_id' => $domain->id, 'name' => $adUnit],
                        ['ad_unit_path' => $adUnit, 'device' => $device, 'is_active' => true],
                    );
                    if ($slot->wasRecentlyCreated) {
                        $created[$cacheKey] = true;
                    }
                }
                if ($slot) {
                    $slotCache[$cacheKey] = $slot;
                }
            }

            if (! $slot) {
                $unmatched++;
                $this->sample($samples, $adUnit);

                continue;
            }
            $matched++;

            $adRequests = (int) ($cells[$idx['requests']] ?? 0);
            $impressions = (int) ($cells[$idx['impressions']] ?? 0);
            $revenue = ((int) ($cells[$idx['revenue']] ?? 0)) / 1_000_000; // micros -> currency
            $viewRate = $idx['viewability'] !== null ? (float) ($cells[$idx['viewability']] ?? 0) : 0.0;

            SlotPerformanceDaily::updateOrCreate(
                [
                    'date' => $cells[$idx['date']] ?? null,
                    'slot_id' => $slot->id,
                    'device' => $device,
                ],
                [
                    'domain_id' => $slot->domain_id,
                    'ad_requests' => $adRequests,
                    'impressions' => $impressions,
                    'revenue' => round($revenue, 4),
                    'ecpm' => $impressions > 0 ? round($revenue / $impressions * 1000, 4) : 0,
                    'fill_rate' => $adRequests > 0 ? round($impressions / $adRequests * 100, 3) : 0,
                    // Active View rate is a 0..1 fraction -> percentage.
                    'viewability' => round($viewRate * 100, 3),
                ],
            );
            $upserted++;
        }

        return [
            'rows' => $rows,
            'matched' => $matched,
            'created' => count($created),
            'upserted' => $upserted,
            'unmatched' => $unmatched,
            'unresolved' => $unresolved,
            'samples' => array_keys(array_flip($samples)),
        ];
    }

    private function findSlot(string $adUnit, Domain $domain): ?AdSlot
    {
        if ($adUnit === '') {
            return null;
        }

        return AdSlot::query()
            ->where('domain_id', $domain->id)
            ->where(fn ($q) => $q->where('ad_unit_path', $adUnit)->orWhere('name', $adUnit))
            ->first();
    }

    /**
     * Resolve an ad unit to a Domain: explicit map → heuristic token match.
     *
     * @param  array<int, array{domain: Domain, tokens: string[]}>  $index
     * @param  array<string, Domain>  $map  normalized parent/ad-unit name => Domain
     */
    private function resolveDomain(string $adUnit, string $parent, array $index, array $map): ?Domain
    {
        // 1. Explicit GAM_DOMAIN_MAP (matched on parent or ad unit name).
        foreach ([$parent, $adUnit] as $name) {
            $key = $this->normalize($name);
            if ($key !== '' && isset($map[$key])) {
                return $map[$key];
            }
        }

        // 2. Heuristic: domain token appears in the (parent + ad unit) string.
        $haystack = $this->normalize($parent.' '.$adUnit);
        foreach ($index as $entry) {
            foreach ($entry['tokens'] as $token) {
                if ($token !== '' && str_contains($haystack, $token)) {
                    return $entry['domain'];
                }
            }
        }

        return null;
    }

    /** @return array<int, array{domain: Domain, tokens: string[]}> */
    private function domainIndex(): array
    {
        return Domain::all()->map(function (Domain $d) {
            $host = strtolower((string) (parse_url($d->url, PHP_URL_HOST) ?: $d->url));
            $host = preg_replace('/^www\./', '', $host);
            $base = explode('.', $host)[0] ?? $host;          // kompas
            $nameTok = $this->normalize($d->name);            // kompastv

            $tokens = array_values(array_unique(array_filter([
                str_replace('.', '', $host), // kompastv
                $base,                       // kompas
                $nameTok,                    // kompastv
            ], fn ($t) => strlen((string) $t) >= 4)));

            return ['domain' => $d, 'tokens' => $tokens];
        })->all();
    }

    /** @return array<string, Domain> normalized name -> Domain (from GAM_DOMAIN_MAP). */
    private function normalizedMap(): array
    {
        $raw = (array) config('services.gam.domain_map', []);
        if ($raw === []) {
            return [];
        }

        $domains = Domain::all();
        $map = [];
        foreach ($raw as $gamName => $target) {
            $domain = $domains->first(function (Domain $d) use ($target) {
                return $d->id === $target
                    || rtrim($d->url, '/') === rtrim((string) $target, '/')
                    || $this->normalize($d->url) === $this->normalize((string) $target);
            });
            if ($domain) {
                $map[$this->normalize((string) $gamName)] = $domain;
            }
        }

        return $map;
    }

    private function normalize(string $s): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($s)) ?? '';
    }

    /** @param string[] $samples */
    private function sample(array &$samples, string $name): void
    {
        if ($name !== '' && count($samples) < 10) {
            $samples[] = $name;
        }
    }

    private function device(string $gamDevice): string
    {
        return match (strtolower(trim($gamDevice))) {
            'desktop' => 'desktop',
            'tablet' => 'tablet',
            'smartphone', 'feature phone', 'mobile' => 'mobile',
            default => 'desktop',
        };
    }

    private function col(array $header, string $token): int
    {
        $i = $this->colOptional($header, $token);
        if ($i === null) {
            throw new RuntimeException("GAM report is missing the '{$token}' column.");
        }

        return $i;
    }

    private function colOptional(array $header, string $token): ?int
    {
        foreach ($header as $i => $name) {
            if (str_contains((string) $name, $token)) {
                return $i;
            }
        }

        return null;
    }

    private function gamDate(CarbonInterface $date): GamDate
    {
        $d = new GamDate();
        $d->setYear((int) $date->year);
        $d->setMonth((int) $date->month);
        $d->setDay((int) $date->day);

        return $d;
    }

    private function resolveKeyFilePath(): string
    {
        $value = (string) config('services.gam.service_account_json');

        if (str_starts_with(ltrim($value), '{')) {
            $path = storage_path('app/gam-service-account.json');
            if (! is_file($path) || md5_file($path) !== md5($value)) {
                file_put_contents($path, $value);
                @chmod($path, 0600);
            }

            return $path;
        }

        if (! is_file($value)) {
            throw new RuntimeException("GAM service account key file not found: {$value}");
        }

        return $value;
    }

    private function emptyResult(): array
    {
        return ['rows' => 0, 'matched' => 0, 'created' => 0, 'upserted' => 0, 'unmatched' => 0, 'unresolved' => 0, 'samples' => []];
    }
}
