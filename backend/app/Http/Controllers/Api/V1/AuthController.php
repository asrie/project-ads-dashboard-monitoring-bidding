<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $service) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $payload = $this->service->login(
            $request->validated('email'),
            $request->validated('password'),
        );

        return ApiResponse::success($this->tokenResponse($payload));
    }

    public function logout(): JsonResponse
    {
        $this->service->logout();

        return ApiResponse::success(['message' => 'Logged out successfully.']);
    }

    public function refresh(): JsonResponse
    {
        $payload = $this->service->refresh();

        return ApiResponse::success($this->tokenResponse($payload));
    }

    public function me(): JsonResponse
    {
        return ApiResponse::success([
            'user' => new UserResource($this->service->me()),
        ]);
    }

    /**
     * @param  array{access_token: string, token_type: string, expires_in: int, user: User}  $payload
     */
    private function tokenResponse(array $payload): array
    {
        return [
            'access_token' => $payload['access_token'],
            'token_type' => $payload['token_type'],
            'expires_in' => $payload['expires_in'],
            'user' => new UserResource($payload['user']),
        ];
    }
}
