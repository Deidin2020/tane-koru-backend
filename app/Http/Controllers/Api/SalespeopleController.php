<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Salesperson\StoreSalespersonRequest;
use App\Http\Requests\Salesperson\UpdateSalespersonRequest;
use App\Http\Resources\SalespersonResource;
use App\Models\Profile;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SalespeopleController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Profile::query()
            ->with('user')
            ->where('is_salesperson', true)
            ->orderBy('full_name')
            ->get();

        return response()->json([
            'data' => SalespersonResource::collection($rows)->resolve(),
        ]);
    }

    public function store(StoreSalespersonRequest $request): JsonResponse
    {
        $profile = Profile::query()->create([
            ...$request->validated(),
            'is_salesperson' => true,
            'is_active' => $request->boolean('is_active', true),
            'is_default' => false,
        ]);

        return response()->json((new SalespersonResource($profile))->resolve(), 201);
    }

    public function update(UpdateSalespersonRequest $request, Profile $salesperson): SalespersonResource|JsonResponse
    {
        if (! $salesperson->is_salesperson) {
            return ApiError::notFound('Salesperson not found.');
        }

        if ($request->has('is_active') && ! $request->boolean('is_active') && $salesperson->is_default) {
            return ApiError::make(409, 'DEFAULT_SALESPERSON_MUST_BE_ACTIVE', 'The default salesperson cannot be deactivated.')->toResponse($request);
        }

        $salesperson->update($request->validated());

        return new SalespersonResource($salesperson->fresh()->load('user'));
    }

    public function destroy(Profile $salesperson): JsonResponse
    {
        if (! $salesperson->is_salesperson) {
            return ApiError::notFound('Salesperson not found.');
        }

        if ($salesperson->clients()->exists() || $salesperson->projectVisits()->exists() || $salesperson->companyVisits()->exists()) {
            return ApiError::make(409, 'SALESPERSON_HAS_RELATED_RECORDS', 'The salesperson is linked to existing records.')->toResponse(request());
        }

        if ($salesperson->user_id) {
            $salesperson->forceFill([
                'is_salesperson' => false,
                'is_active' => false,
                'is_default' => false,
            ])->save();
        } else {
            $salesperson->delete();
        }

        return response()->json([], 204);
    }

    public function setDefault(Profile $salesperson): SalespersonResource|JsonResponse
    {
        if (! $salesperson->is_salesperson) {
            return ApiError::notFound('Salesperson not found.');
        }
        if (! $salesperson->is_active) {
            return ApiError::make(409, 'INACTIVE_SALESPERSON', 'An inactive salesperson cannot be the default.')->toResponse(request());
        }

        DB::transaction(function () use ($salesperson): void {
            Profile::query()->where('is_salesperson', true)->lockForUpdate()->update(['is_default' => false]);
            $salesperson->forceFill(['is_default' => true])->save();
        });

        return new SalespersonResource($salesperson->fresh()->load('user'));
    }
}
