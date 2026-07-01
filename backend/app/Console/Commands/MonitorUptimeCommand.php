<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ServerHealth\UptimeMonitorService;
use Illuminate\Console\Command;

class MonitorUptimeCommand extends Command
{
    protected $signature = 'monitor:uptime';

    protected $description = 'Ping active domains via HTTP and record real uptime/response-time checks.';

    public function handle(UptimeMonitorService $service): int
    {
        $checks = $service->checkAll();

        if ($checks === []) {
            $this->warn('No active domains to check.');

            return self::SUCCESS;
        }

        foreach ($checks as $check) {
            $check->loadMissing('domain');
            $this->line(sprintf(
                '%-14s %-4s %5dms  HTTP %d%s',
                $check->domain?->name ?? $check->domain_id,
                strtoupper($check->status),
                $check->response_time_ms,
                $check->http_status,
                $check->error_message ? '  ('.$check->error_message.')' : '',
            ));
        }

        return self::SUCCESS;
    }
}
