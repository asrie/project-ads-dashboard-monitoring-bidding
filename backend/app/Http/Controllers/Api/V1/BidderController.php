<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BidderIndexRequest;
use App\Http\Requests\DashboardFilterRequest;
use App\Services\Programmatic\BidderService;
use App\Support\ApiResponse;
use App\Support\DashboardFilters;
use Illuminate\Http\JsonResponse;

class BidderController extends Controller
{
    public function __construct(private readonly BidderService $service) {}

    public function index(BidderIndexRequest $request): JsonResponse
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
