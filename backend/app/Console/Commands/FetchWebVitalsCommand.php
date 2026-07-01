<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\WebVitals\CruxWebVitalsService;
use Illuminate\Console\Command;

class FetchWebVitalsCommand extends Command
{
    protected $signature = 'webvitals:fetch';

    protected $description = 'Fetch real field Web Vitals (p75) from the Google CrUX API into web_vitals_daily.';

    public function handle(CruxWebVitalsService $service): int
    {
        if (! $service->configured()) {
            $this->warn('CRUX_API_KEY not set — skipping Web Vitals fetch.');

            return self::SUCCESS;
        }

        foreach ($service->fetchAll() as $r) {
            $line = sprintf('%-14s %-8s %s', $r['domain'], $r['device'], $r['status']);
            if (($r['status'] ?? '') === 'ok') {
                $line .= sprintf('  (LCP p75 %sms)', round($r['lcp'] ?? 0));
            }
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
