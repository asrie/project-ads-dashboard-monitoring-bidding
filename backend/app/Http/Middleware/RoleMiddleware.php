<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Module-level RBAC. Usage: ->middleware('role:admin,programmatic_revenue')
 * Backend remains the source of authorization truth (SECURITY.md §3.2).
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error([[
                'code' => 'UNAUTHENTICATED',
                'message' => 'Authentication required.',
                'field' => null,
            ]], 401);
        }

        if (! empty($roles) && ! in_array($user->role->value, $roles, true)) {
            return ApiResponse::error([[
                'code' => 'FORBIDDEN',
                'message' => 'You do not have permission to access this resource.',
                'field' => null,
            ]], 403);
        }

        return $next($request);
    }
}
