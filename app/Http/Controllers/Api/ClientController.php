<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\FollowUpRequest;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\DefaultSalesperson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly DefaultSalesperson $defaultSalesperson,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Client::query()->with('agency');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('client_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('required_unit', 'like', "%{$search}%");
            });
        }

        if ($salespersonId = $request->query('salesperson_id')) {
            $query->where('assigned_salesperson_id', $salespersonId);
        }

        if ($createdFrom = $request->query('created_from')) {
            $query->whereDate('created_at', '>=', $createdFrom);
        }

        if ($createdTo = $request->query('created_to')) {
            $query->whereDate('created_at', '<=', $createdTo);
        }

        if ($followUp = $request->query('follow_up')) {
            $today = today()->format('Y-m-d');
            match ($followUp) {
                'today' => $query->whereDate('follow_up_date', $today),
                'overdue' => $query->whereDate('follow_up_date', '<', $today),
                'upcoming' => $query->whereDate('follow_up_date', '>', $today),
                'none' => $query->whereNull('follow_up_date'),
                default => null,
            };
        }

        $sort = in_array($request->query('sort'), ['created_at', 'last_activity_at', 'client_name', 'follow_up_date'], true)
            ? $request->query('sort')
            : 'created_at';
        $order = $request->query('order') === 'asc' ? 'asc' : 'desc';

        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 50), 1), 200);
        $results = $query->orderBy($sort, $order)->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => ClientResource::collection($results->getCollection())->resolve(),
            'meta' => [
                'page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ]);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $project = Project::resolveDefault();

        /** @var User $user */
        $user = $request->user();

        $client = DB::transaction(function () use ($request, $project, $user): Client {
            $data = $request->validated();

            $data['assigned_salesperson_id'] = $data['assigned_salesperson_id'] ?? $this->defaultSalesperson->idOrFail();

            if (($data['lead_source'] ?? null) !== 'agency') {
                $data['agency_id'] = null;
            }

            $client = Client::query()->create([
                ...$data,
                'project_id' => $project->id,
                'status' => 'new',
                'presentation_completed' => (bool) ($data['presentation_completed'] ?? false),
                'created_by' => $user->id,
            ]);

            $this->activityLogger->log($client, 'client_created', $user, 'Client record created');

            return $client->load('agency');
        });

        return response()->json(new ClientResource($client), 201);
    }

    public function show(Client $client): ClientResource
    {
        return new ClientResource($client->load('agency'));
    }

    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        if (($data['lead_source'] ?? $client->lead_source) !== 'agency') {
            $data['agency_id'] = null;
        }

        $client->fill($data);
        $client->updated_by = $user->id;
        $client->save();

        return new ClientResource($client->load('agency'));
    }

    public function saveFollowUp(FollowUpRequest $request, Client $client): ClientResource|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();
        $parts = [];

        if ($note = trim((string) ($data['note'] ?? ''))) {
            $parts[] = $note;
        }

        if (! empty($data['follow_up_date'])) {
            $parts[] = 'Next follow-up: '.$data['follow_up_date'];
        }

        DB::transaction(function () use ($client, $data, $user, $parts): void {
            $client->follow_up_date = $data['follow_up_date'] ?? null;
            $client->updated_by = $user->id;
            $client->save();

            $this->activityLogger->log(
                $client,
                'follow_up',
                $user,
                $parts !== [] ? implode(' · ', $parts) : 'Follow-up logged'
            );
        });

        return new ClientResource($client->fresh()->load('agency'));
    }
}
