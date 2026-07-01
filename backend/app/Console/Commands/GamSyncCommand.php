<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Gam\GamReportService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GamSyncCommand extends Command
{
    protected $signature = 'gam:sync
        {--from= : Start date YYYY-MM-DD (default: 7 days ago)}
        {--to= : End date YYYY-MM-DD (default: yesterday)}
        {--domain= : Force all rows to this domain UUID}
        {--no-create : Do not auto-create ad slots for unmatched GAM ad units}';

    protected $description = 'Pull a Google Ad Manager report and upsert it into slot_performance_daily.';

    public function handle(GamReportService $service): int
    {
        if (! $service->configured()) {
            $this->warn('GAM not configured — set GAM_NETWORK_CODE and GAM_SERVICE_ACCOUNT_JSON. Skipping.');

            return self::SUCCESS;
        }

        $to = $this->option('to') ? CarbonImmutable::parse($this->option('to')) : CarbonImmutable::yesterday();
        $from = $this->option('from') ? CarbonImmutable::parse($this->option('from')) : $to->subDays(6);

        $this->info("Fetching GAM report {$from->toDateString()} .. {$to->toDateString()} …");

        try {
            $result = $service->sync($from, $to, $this->option('domain'), ! $this->option('no-create'));
        } catch (\Throwable $e) {
            $this->error('GAM sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Rows', 'Matched', 'Created', 'Upserted', 'Unmatched', 'Unresolved'],
            [[
                $result['rows'], $result['matched'], $result['created'],
                $result['upserted'], $result['unmatched'], $result['unresolved'],
            ]],
        );

        if ($result['created'] > 0) {
            $this->info("Auto-created {$result['created']} new ad slot(s) from GAM ad units.");
        }

        if (($result['unmatched'] + $result['unresolved']) > 0) {
            $this->warn('Ad units without a domain match (set GAM_DOMAIN_MAP or use --domain):');
            foreach ($result['samples'] as $name) {
                $this->line('  • '.$name);
            }
        }

        return self::SUCCESS;
    }
}
