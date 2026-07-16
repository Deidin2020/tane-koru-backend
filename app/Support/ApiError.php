<?php

namespace App\Support;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

class ApiError implements Responsable
{
    public function __construct(
        private readonly int $status,
        private readonly string $code,
        private readonly string $message,
        private readonly array $details = [],
    ) {
    }

    public static function make(int $status, string $code, string $message, array $details = []): self
    {
        return new self($status, $code, $message, $details);
    }

    public static function unauthorized(string $message = 'Unauthorized.'): JsonResponse
    {
        return self::make(401, 'unauthorized', $message)->toResponse(request());
    }

    public static function forbidden(string $message = 'Forbidden.'): JsonResponse
    {
        return self::make(403, 'forbidden', $message)->toResponse(request());
    }

    public static function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return self::make(404, 'not_found', $message)->toResponse(request());
    }

    public static function conflict(string $message, array $details = []): JsonResponse
    {
        return self::make(409, 'conflict', $message, $details)->toResponse(request());
    }

    public static function validation(string $message, array $details = []): JsonResponse
    {
        return self::make(422, 'validation_error', $message, $details)->toResponse(request());
    }

    public static function businessRule(string $message, array $details = []): JsonResponse
    {
        return self::make(422, 'business_rule_violation', $message, $details)->toResponse(request());
    }

    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $this->code,
                'message' => $this->message,
                'details' => (object) $this->details,
            ],
        ], $this->status);
    }
}
