<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardFilterRequest;
use App\Http\Requests\SlotIndexRequest;
use App\Services\Programmatic\SlotService;
use App\Support\ApiResponse;
use App\Support\DashboardFilters;
use Illuminate\Http\JsonResponse;

class SlotController extends Controller
{
    public function __construct(private readonly SlotService $service) {}

    public function index(SlotIndexRequest $request): JsonResponse
    {
        $filters = DashboardFilters::fromArray($request->validated());
        $paginator = $this->service->paginate($filters);

        return ApiResponse::paginated($paginator, $paginator->items());
    }

    public function show(string $id, DashboardFilterRequest $request): JsonResponse
    {
        $filters = DashboardFilters::fromArray($request->validated());

        return ApiResponse::success($this->service->detail($id, $filters));
    }
}
