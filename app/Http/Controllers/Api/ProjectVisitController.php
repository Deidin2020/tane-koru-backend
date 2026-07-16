<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Visit\StoreProjectVisitRequest;
use App\Http\Requests\Visit\UpdateProjectVisitRequest;
use App\Http\Resources\ProjectVisitResource;
use App\Models\Project;
use App\Models\ProjectVisit;
use App\Models\User;
use App\Support\DefaultSalesperson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectVisitController extends Controller
{
    public function __construct(
        private readonly DefaultSalesperson $defaultSalesperson,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = ProjectVisit::query()->with('agency');

        if ($from = $request->query('from')) {
            $query->where('visit_date', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->where('visit_date', '<=', $to);
        }

        if ($salesRepId = $request->query('sales_rep_id')) {
            $query->where('sales_rep_id', $salesRepId);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->whereHas('agency', fn ($agency) => $agency
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"));
            });
        }

        $order = $request->query('order') === 'asc' ? 'asc' : 'desc';
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 50), 1), 200);
        $results = $query->orderBy('visit_date', $order)->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => ProjectVisitResource::collection($results->getCollection())->resolve(),
            'meta' => [
                'page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ]);
    }

    public function store(StoreProjectVisitRequest $request): JsonResponse
    {
        $project = Project::resolveDefault();

        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();
        $data['sales_rep_id'] = $data['sales_rep_id'] ?? $this->defaultSalesperson->idOrFail();
        $visit = ProjectVisit::query()->create([
            ...$data,
            'project_id' => $project->id,
            'created_by' => $user->id,
        ])->load('agency');

        return response()->json(new ProjectVisitResource($visit), 201);
    }

    public function update(UpdateProjectVisitRequest $request, ProjectVisit $projectVisit): ProjectVisitResource
    {
        /** @var User $user */
        $user = $request->user();
        $projectVisit->fill($request->validated());
        $projectVisit->updated_by = $user->id;
        $projectVisit->save();

        return new ProjectVisitResource($projectVisit->load('agency'));
    }

    public function destroy(ProjectVisit $projectVisit): JsonResponse
    {
        $projectVisit->delete();

        return response()->json([], 204);
    }
}
