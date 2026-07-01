<?php

declare(strict_types=1);

namespace App\Services\ServerHealth;

use App\Models\Domain;
use App\Models\ServerCheck;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Performs real HTTP health checks against each domain and records the
 * measured status + response time into `server_checks`.
 */
class UptimeMonitorService
{
    /**
     * Check every active domain.
     *
     * @return ServerCheck[]
     */
    public function checkAll(): array
    {
        return Domain::query()
            ->where('is_active', true)
            ->get()
            ->map(fn (Domain $domain) => $this->check($domain))
            ->all();
    }

    public function check(Domain $domain): ServerCheck
    {
        $timeout = (int) config('services.monitor.timeout', 15);
        $start = microtime(true);

        $status = 'down';
        $httpStatus = 0;
        $error = null;

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['User-Agent' => 'AdsDashboard-UptimeBot/1.0'])
                ->get($domain->url);

            $httpStatus = $response->status();
            // 2xx/3xx = reachable & serving.
            $status = ($response->successful() || $response->redirect()) ? 'up' : 'down';

            if ($status === 'down') {
                $error = "HTTP {$httpStatus}";
            }
        } catch (\Throwable $e) {
            $status = 'down';
            $error = class_basename($e).': '.Str::limit($e->getMessage(), 180);
        }

        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        return ServerCheck::create([
            'domain_id' => $domain->id,
            'checked_at' => now(),
            'status' => $status,
            'response_time_ms' => $elapsedMs,
            'http_status' => $httpStatus,
            'region' => (string) config('services.monitor.region', 'default'),
            'error_message' => $error,
        ]);
    }
}
