<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebVitalsRequest;
use App\Services\WebVitals\WebVitalsService;
use App\Support\ApiResponse;
use App\Support\DashboardFilters;
use Illuminate\Http\JsonResponse;

class WebVitalsController extends Controller
{
    public function __construct(private readonly WebVitalsService $service) {}

    public function summary(WebVitalsRequest $request): JsonResponse
    {
        $filters = DashboardFilters::fromArray($request->validated());

        return ApiResponse::success($this->service->summary($filters));
    }

    public function pages(WebVitalsRequest $request): JsonResponse
    {
        $filters = DashboardFilters::fromArray($request->validated());
        $paginator = $this->service->pages($filters);

        return ApiResponse::paginated($paginator, $paginator->items());
    }
}
