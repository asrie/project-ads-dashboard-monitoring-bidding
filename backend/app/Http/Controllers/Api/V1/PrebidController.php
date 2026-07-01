<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardFilterRequest;
use App\Http\Requests\PrebidAuctionRequest;
use App\Services\Prebid\PrebidService;
use App\Support\ApiResponse;
use App\Support\DashboardFilters;
use Illuminate\Http\JsonResponse;

class PrebidController extends Controller
{
    public function __construct(private readonly PrebidService $service) {}

    public function health(DashboardFilterRequest $request): JsonResponse
    {
        $filters = DashboardFilters::fromArray($request->validated());

        return ApiResponse::success($this->service->health($filters));
    }

    public function auctions(PrebidAuctionRequest $request): JsonResponse
    {
        $filters = DashboardFilters::fromArray($request->validated());
        $paginator = $this->service->auctions($filters, $request->validated('status'));

        return ApiResponse::paginated($paginator, $paginator->items());
    }
}
