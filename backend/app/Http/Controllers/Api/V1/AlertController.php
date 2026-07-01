<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AlertIndexRequest;
use App\Http\Requests\DashboardFilterRequest;
use App\Services\Alerts\AlertService;
use App\Support\ApiResponse;
use App\Support\DashboardFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function __construct(private readonly AlertService $service) {}

    public function index(AlertIndexRequest $request): JsonResponse
    {
        $filters = DashboardFilters::fromArray($request->validated());
        $paginator = $this->service->paginate($filters, [
            'severity' => $request->validated('severity'),
            'category' => $request->validated('category'),
            'status' => $request->validated('status'),
        ]);

        return ApiResponse::paginated($paginator, $paginator->items());
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success($this->service->detail($id));
    }

    public function acknowledge(string $id, Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->acknowledge($id, (string) $request->user()->id)
        );
    }

    public function insights(DashboardFilterRequest $request): JsonResponse
    {
        $filters = DashboardFilters::fromArray($request->validated());

        return ApiResponse::success($this->service->insights($filters));
    }
}
