<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agency\StoreAgencyRequest;
use App\Http\Requests\Agency\UpdateAgencyRequest;
use App\Http\Resources\AgencyResource;
use App\Http\Resources\ClientResource;
use App\Http\Resources\CompanyVisitResource;
use App\Http\Resources\ProjectVisitResource;
use App\Models\AgencyCompany;
use App\Models\User;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgencyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AgencyCompany::query();

        if ($search = trim((string) $request->query('search'))) {
            $query->where('name', 'like', "%{$search}%");
        }

        $order = $request->query('order') === 'desc' ? 'desc' : 'asc';
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 50), 1), 200);
        $results = $query->orderBy('name', $order)->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => AgencyResource::collection($results->getCollection())->resolve(),
            'meta' => [
                'page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ]);
    }

    public function store(StoreAgencyRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $name = trim($request->string('name')->toString());
        $normalizedName = $this->normalizeName($name);

        if ($normalizedName === '') {
            return ApiError::validation('The given data was invalid.', [
                'name' => ['The name field is required.'],
            ]);
        }

        if (AgencyCompany::query()->where('normalized_name', $normalizedName)->exists()) {
            return ApiError::make(409, 'AGENCY_NAME_ALREADY_EXISTS', 'An agency/company with this name already exists.')->toResponse($request);
        }

        $agency = AgencyCompany::query()->create([
            'name' => $name,
            'normalized_name' => $normalizedName,
            'category' => $request->input('category'),
            'contact_person' => $request->input('contact_person'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'address' => $request->input('address'),
            'notes' => $request->input('notes'),
            'created_by' => $user->id,
        ]);

        return response()->json((new AgencyResource($agency))->resolve(), 201);
    }

    public function show(AgencyCompany $agency): AgencyResource
    {
        return new AgencyResource($agency);
    }

    public function update(UpdateAgencyRequest $request, AgencyCompany $agency): AgencyResource|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            $normalizedName = $this->normalizeName($name);

            if ($normalizedName === '') {
                return ApiError::validation('The given data was invalid.', ['name' => ['The name field is required.']]);
            }
            if (AgencyCompany::query()->where('normalized_name', $normalizedName)->whereKeyNot($agency->id)->exists()) {
                return ApiError::make(409, 'AGENCY_NAME_ALREADY_EXISTS', 'An agency/company with this name already exists.')->toResponse($request);
            }

            $data['name'] = $name;
            $data['normalized_name'] = $normalizedName;
        }

        $agency->fill($data);
        $agency->updated_by = $user->id;
        $agency->save();

        return new AgencyResource($agency->fresh());
    }

    public function destroy(AgencyCompany $agency): JsonResponse
    {
        if ($agency->clients()->exists() || $agency->projectVisits()->exists() || $agency->companyVisits()->exists()) {
            return ApiError::make(409, 'AGENCY_HAS_RELATED_RECORDS', 'The agency/company is linked to existing records.')->toResponse(request());
        }

        $agency->delete();

        return response()->json([], 204);
    }

    public function summary(AgencyCompany $agency): JsonResponse
    {
        return response()->json([
            'project_visits' => $agency->projectVisits()->count(),
            'company_visits' => $agency->companyVisits()->count(),
            'clients' => $agency->clients()->count(),
            'reservations' => $agency->clients()->where('status', 'reservation')->count(),
            'deals' => $agency->clients()->where('status', 'deal')->count(),
        ]);
    }

    public function clients(AgencyCompany $agency): JsonResponse
    {
        return response()->json([
            'data' => ClientResource::collection(
                $agency->clients()->with('agency')->latest()->get()
            )->resolve(),
        ]);
    }

    public function projectVisits(AgencyCompany $agency): JsonResponse
    {
        return response()->json([
            'data' => ProjectVisitResource::collection(
                $agency->projectVisits()->with('agency')->latest('visit_date')->get()
            )->resolve(),
        ]);
    }

    public function companyVisits(AgencyCompany $agency): JsonResponse
    {
        return response()->json([
            'data' => CompanyVisitResource::collection(
                $agency->companyVisits()->with('agency')->latest('visit_date')->get()
            )->resolve(),
        ]);
    }

    private function normalizeName(string $name): string
    {
        return Str::of($name)->lower()->squish()->replaceMatches('/[^\pL\pN]+/u', '-')->trim('-')->toString();
    }
}
