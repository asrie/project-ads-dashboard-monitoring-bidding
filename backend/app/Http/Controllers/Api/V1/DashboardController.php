<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardFilterRequest;
use App\Services\Dashboard\DashboardService;
use App\Support\ApiResponse;
use App\Support\DashboardFilters;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service) {}

    public function overview(DashboardFilterRequest $request): JsonResponse
    {
        $filters = DashboardFilters::fromArray($request->validated());

        return ApiResponse::success($this->service->getOverview($filters));
    }
}
