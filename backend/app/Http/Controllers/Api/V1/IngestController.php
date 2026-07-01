<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrebidIngestRequest;
use App\Services\Prebid\PrebidIngestService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class IngestController extends Controller
{
    public function __construct(private readonly PrebidIngestService $service) {}

    public function prebid(PrebidIngestRequest $request): JsonResponse
    {
        $result = $this->service->ingest($request->validated());

        if (! $result['resolved']) {
            return ApiResponse::error([[
                'code' => 'UNKNOWN_DOMAIN',
                'message' => 'Domain is not registered for ingestion.',
                'field' => 'domain',
            ]], 422);
        }

        return ApiResponse::success([
            'received' => $result['received'],
            'stored' => $result['stored'],
        ], [], 202);
    }
}
