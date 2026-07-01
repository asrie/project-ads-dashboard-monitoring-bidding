<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards browser-push ingestion endpoints with a shared ingest key.
 * The key is accepted via the X-Ingest-Key header or an `ingest_key` body
 * field (the latter supports navigator.sendBeacon, which can't set headers).
 */
class VerifyIngestKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.prebid.ingest_key');

        if ($expected === '') {
            return ApiResponse::error([[
                'code' => 'INGEST_DISABLED',
                'message' => 'Ingestion is not configured.',
                'field' => null,
            ]], 503);
        }

        $provided = (string) ($request->header('X-Ingest-Key') ?? $request->input('ingest_key', ''));

        if (! hash_equals($expected, $provided)) {
            return ApiResponse::error([[
                'code' => 'INVALID_INGEST_KEY',
                'message' => 'Invalid or missing ingest key.',
                'field' => null,
            ]], 401);
        }

        return $next($request);
    }
}
