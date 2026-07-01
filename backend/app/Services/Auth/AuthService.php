<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService
{
    /**
     * Attempt login and return token payload.
     *
     * @return array{access_token: string, token_type: string, expires_in: int, user: User}
     *
     * @throws ValidationException
     */
    public function login(string $email, string $password): array
    {
        $token = auth('api')->attempt(['email' => $email, 'password' => $password]);

        if ($token === false) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        /** @var User $user */
        $user = auth('api')->user();

        return $this->tokenPayload((string) $token, $user);
    }

    public function logout(): void
    {
        // Invalidates and blacklists the current token (SECURITY.md §2.2).
        auth('api')->logout();
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int, user: User}
     */
    public function refresh(): array
    {
        $token = auth('api')->refresh();
        /** @var User $user */
        $user = auth('api')->user();

        return $this->tokenPayload($token, $user);
    }

    public function me(): User
    {
        /** @var User $user */
        $user = auth('api')->user();

        return $user;
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int, user: User}
     */
    private function tokenPayload(string $token, User $user): array
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => (int) (JWTAuth::factory()->getTTL() * 60),
            'user' => $user,
        ];
    }
}
