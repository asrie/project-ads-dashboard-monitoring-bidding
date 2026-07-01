<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardFilterRequest;
use App\Http\Requests\GamRequestsRequest;
use App\Services\Gam\GamService;
use App\Support\ApiResponse;
use App\Support\DashboardFilters;
use Illuminate\Http\JsonResponse;

class GamController extends Controller
{
    public function __construct(private readonly GamService $service) {}

    public function health(DashboardFilterRequest $request): JsonResponse
    {
        $filters = DashboardFilters::fromArray($request->validated());

        return ApiResponse::success($this->service->health($filters));
    }

    public function requests(GamRequestsRequest $request): JsonResponse
    {
        $filters = DashboardFilters::fromArray($request->validated());
        $paginator = $this->service->requests($filters, $request->validated('status'));

        return ApiResponse::paginated($paginator, $paginator->items());
    }
}
