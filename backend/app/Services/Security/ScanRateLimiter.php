<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Per-user rate limiting for security scans, backed by a configurable cache
 * store (set services.security_scan.rate_limit_store to 'upstash' for Upstash
 * Redis; otherwise the default store is used).
 */
class ScanRateLimiter
{
    /**
     * @return array{allowed: bool, retry_after: int, limit: string}
     */
    public function check(string $userId): array
    {
        $store = $this->store();
        $perMinute = (int) config('services.security_scan.per_minute', 3);
        $perHour = (int) config('services.security_scan.per_hour', 30);

        $windows = [
            ['key' => "secscan:min:{$userId}", 'max' => $perMinute, 'ttl' => 60, 'label' => "{$perMinute}/menit"],
            ['key' => "secscan:hr:{$userId}", 'max' => $perHour, 'ttl' => 3600, 'label' => "{$perHour}/jam"],
        ];

        foreach ($windows as $w) {
            if ((int) $store->get($w['key'], 0) >= $w['max']) {
                return ['allowed' => false, 'retry_after' => $w['ttl'], 'limit' => $w['label']];
            }
        }

        foreach ($windows as $w) {
            if ($store->get($w['key']) === null) {
                $store->put($w['key'], 1, $w['ttl']);
            } else {
                $store->increment($w['key']);
            }
        }

        return ['allowed' => true, 'retry_after' => 0, 'limit' => ''];
    }

    private function store(): Repository
    {
        $name = config('services.security_scan.rate_limit_store');

        return $name ? Cache::store($name) : Cache::store();
    }
}
