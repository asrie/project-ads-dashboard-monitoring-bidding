<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardFilterRequest;
use App\Http\Requests\ServerChecksRequest;
use App\Services\ServerHealth\ServerHealthService;
use App\Support\ApiResponse;
use App\Support\DashboardFilters;
use Illuminate\Http\JsonResponse;

class ServerController extends Controller
{
    public function __construct(private readonly ServerHealthService $service) {}

    public function health(DashboardFilterRequest $request): JsonResponse
    {
        $filters = DashboardFilters::fromArray($request->validated());

        return ApiResponse::success($this->service->summary($filters));
    }

    public function checks(ServerChecksRequest $request): JsonResponse
    {
        $filters = DashboardFilters::fromArray($request->validated());
        $paginator = $this->service->checks($filters, $request->validated('status'));

        return ApiResponse::paginated($paginator, $paginator->items());
    }
}
