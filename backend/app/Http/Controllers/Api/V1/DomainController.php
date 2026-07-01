<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DomainController extends Controller
{
    public function index(): JsonResponse
    {
        $domains = Domain::query()
            ->orderBy('name')
            ->get(['id', 'name', 'url', 'is_active'])
            ->map(fn (Domain $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'url' => $d->url,
                'is_active' => $d->is_active,
            ]);

        return ApiResponse::success($domains);
    }
}
