<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientActivityRequest;
use App\Http\Resources\ClientActivityResource;
use App\Models\Client;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;

class ClientActivityController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function index(Client $client): JsonResponse
    {
        $activities = $client->activities()->latest()->get();

        return response()->json([
            'data' => ClientActivityResource::collection($activities)->resolve(),
        ]);
    }

    public function store(StoreClientActivityRequest $request, Client $client): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $activity = $this->activityLogger->log(
            $client,
            'note',
            $user,
            $request->string('message')->toString(),
        );

        return response()->json(new ClientActivityResource($activity), 201);
    }
}
