<?php

namespace App\Http\Middleware;

use App\Support\ApiError;
use App\Support\ApiTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    public function __construct(
        private readonly ApiTokenService $tokens,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->bearerToken();

        if (! $header) {
            return ApiError::unauthorized('Missing bearer token.');
        }

        $resolved = $this->tokens->resolve($header);

        if (! $resolved) {
            return ApiError::unauthorized('Invalid or expired bearer token.');
        }

        $request->attributes->set('api_token_payload', $resolved['payload']);
        $request->attributes->set('api_token_value', $header);
        $request->setUserResolver(fn () => $resolved['user']);

        return $next($request);
    }
}
