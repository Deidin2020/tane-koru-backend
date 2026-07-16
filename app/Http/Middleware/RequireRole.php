<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string $requirement): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return ApiError::unauthorized('Authentication required.');
        }

        $roles = $user->roles()->pluck('name')->all();
        $allowed = match ($requirement) {
            'can_write' => collect($roles)->intersect(['admin', 'project_manager', 'sales_manager', 'salesperson'])->isNotEmpty(),
            'is_manager' => collect($roles)->intersect(['admin', 'project_manager', 'sales_manager'])->isNotEmpty(),
            'is_admin' => in_array('admin', $roles, true),
            default => false,
        };

        if (! $allowed) {
            return ApiError::forbidden('You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
