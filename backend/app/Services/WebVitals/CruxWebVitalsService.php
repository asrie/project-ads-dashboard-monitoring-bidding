<?php

declare(strict_types=1);

namespace App\Services\WebVitals;

use App\Models\Domain;
use App\Models\WebVitalDaily;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fetches real field Web Vitals from the Google Chrome UX Report (CrUX) API
 * and stores the p75 values into web_vitals_daily (origin-level).
 *
 * Requires a free API key in services.crux.key (CRUX_API_KEY).
 */
class CruxWebVitalsService
{
    private const ENDPOINT = 'https://chromeuxreport.googleapis.com/v1/records:queryRecord';

    /** CrUX form factor => internal device. */
    private const FORM_FACTORS = ['PHONE' => 'mobile', 'DESKTOP' => 'desktop'];

    public function configured(): bool
    {
        return ! empty(config('services.crux.key'));
    }

    /**
     * @return array<int, array{domain: string, device: string, status: string, lcp?: float}>
     */
    public function fetchAll(): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('CRUX_API_KEY is not set.');
        }

        $out = [];
        foreach (Domain::where('is_active', true)->get() as $domain) {
            foreach (self::FORM_FACTORS as $formFactor => $device) {
                $out[] = $this->fetchOne($domain, $formFactor, $device);
            }
        }

        return $out;
    }

    /**
     * @return array{domain: string, device: string, status: string, lcp?: float}
     */
    private function fetchOne(Domain $domain, string $formFactor, string $device): array
    {
        $key = (string) config('services.crux.key');

        $response = Http::acceptJson()->post(self::ENDPOINT.'?key='.$key, [
            'origin' => rtrim($domain->url, '/'),
            'formFactor' => $formFactor,
        ]);

        // 404 => CrUX has no data for this origin/form factor (low traffic).
        if ($response->status() === 404) {
            return ['domain' => $domain->name, 'device' => $device, 'status' => 'no_data'];
        }

        if (! $response->successful()) {
            return ['domain' => $domain->name, 'device' => $device, 'status' => 'error '.$response->status()];
        }

        $metrics = $response->json('record.metrics', []);

        $lcp = $this->p75($metrics, 'largest_contentful_paint');
        $inp = $this->p75($metrics, 'interaction_to_next_paint');
        $cls = $this->p75($metrics, 'cumulative_layout_shift');
        $fcp = $this->p75($metrics, 'first_contentful_paint');
        $ttfb = $this->p75($metrics, 'experimental_time_to_first_byte');

        WebVitalDaily::updateOrCreate(
            [
                'domain_id' => $domain->id,
                'page_id' => null,
                'device' => $device,
                'date' => now()->toDateString(),
            ],
            [
                'lcp' => $lcp,
                'inp' => $inp,
                'cls' => $cls,
                'fcp' => $fcp,
                'ttfb' => $ttfb,
                'tbt' => 0, // TBT is a lab metric; not available from CrUX field data.
                'samples' => 1000, // nominal weight (CrUX reports distributions, not counts)
            ],
        );

        return ['domain' => $domain->name, 'device' => $device, 'status' => 'ok', 'lcp' => $lcp];
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function p75(array $metrics, string $key): float
    {
        return (float) ($metrics[$key]['percentiles']['p75'] ?? 0);
    }
}
