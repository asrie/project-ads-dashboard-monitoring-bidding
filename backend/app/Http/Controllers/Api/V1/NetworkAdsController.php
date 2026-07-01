<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardFilterRequest;
use App\Http\Requests\NetworkAdsRequest;
use App\Services\NetworkAds\NetworkAdsService;
use App\Support\ApiResponse;
use App\Support\DashboardFilters;
use Illuminate\Http\JsonResponse;

class NetworkAdsController extends Controller
{
    public function __construct(private readonly NetworkAdsService $service) {}

    public function index(NetworkAdsRequest $request): JsonResponse
    {
        $filters = DashboardFilters::fromArray($request->validated());
        $thirdParty = $request->has('third_party') ? $request->boolean('third_party') : null;

        $paginator = $this->service->paginate($filters, $request->validated('type'), $thirdParty);

        return ApiResponse::paginated($paginator, $paginator->items());
    }

    public function heavyRequests(DashboardFilterRequest $request): JsonResponse
    {
        $filters = DashboardFilters::fromArray($request->validated());

        return ApiResponse::success($this->service->heavyRequests($filters));
    }
}
