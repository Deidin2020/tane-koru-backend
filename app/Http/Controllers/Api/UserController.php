<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\StoreUserRoleRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserSummaryResource;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()->with(['profile', 'roles'])->orderBy('name')->get();

        return response()->json([
            'data' => UserSummaryResource::collection($users)->resolve(),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $data = $request->validated();
            $user = User::query()->create([
                'name' => $data['full_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
            ]);
            $profile = $user->profile()->create([
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'is_salesperson' => in_array('salesperson', $data['roles'], true),
                'is_active' => $data['is_active'] ?? true,
            ]);
            $roleIds = Role::query()->whereIn('name', $data['roles'])->pluck('id');
            $user->roles()->sync($roleIds->mapWithKeys(fn (int $id) => [$id => ['created_at' => now()]])->all());

            return $user->load(['profile', 'roles']);
        });

        return response()->json((new UserSummaryResource($user))->resolve(), 201);
    }

    public function update(UpdateUserRequest $request, User $user): UserSummaryResource
    {
        $data = $request->validated();
        $profileData = [];

        if (array_key_exists('full_name', $data)) {
            $user->name = $data['full_name'];
            $profileData['full_name'] = $data['full_name'];
        }
        if (array_key_exists('email', $data)) {
            $user->email = $data['email'];
            $profileData['email'] = $data['email'];
        }
        if (array_key_exists('password', $data)) {
            $user->password = $data['password'];
        }
        if (array_key_exists('is_active', $data)) {
            $user->is_active = $data['is_active'];
            $profileData['is_active'] = $data['is_active'];
        }

        DB::transaction(function () use ($user, $profileData): void {
            $user->save();
            $user->profile()->updateOrCreate(['user_id' => $user->id], $profileData + [
                'full_name' => $user->name,
                'email' => $user->email,
            ]);
        });

        return new UserSummaryResource($user->fresh()->load(['profile', 'roles']));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->is($user)) {
            return ApiError::make(409, 'CANNOT_DELETE_CURRENT_USER', 'You cannot delete your own account.')->toResponse($request);
        }

        if ($user->roles()->where('name', 'admin')->exists()) {
            $adminCount = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'admin'))->count();
            if ($adminCount <= 1) {
                return ApiError::make(409, 'LAST_ADMIN_REQUIRED', 'The last administrator cannot be deleted.')->toResponse($request);
            }
        }

        DB::transaction(function () use ($user): void {
            $user->forceFill(['is_active' => false])->save();
            $user->profile?->forceFill([
                'is_active' => false,
                'is_default' => false,
            ])->save();
            $user->delete();
        });

        return response()->json([], 204);
    }

    public function storeRole(StoreUserRoleRequest $request, User $user): JsonResponse
    {
        $role = Role::query()->where('name', $request->string('role')->toString())->first();

        if (! $role) {
            return ApiError::notFound('Role not found.');
        }

        if ($user->roles()->where('roles.id', $role->id)->exists()) {
            return ApiError::conflict('User already has this role.');
        }

        $user->roles()->attach($role->id, ['created_at' => now()]);

        if ($role->name === 'salesperson') {
            $user->profile()->updateOrCreate(['user_id' => $user->id], [
                'full_name' => $user->name,
                'email' => $user->email,
                'is_salesperson' => true,
                'is_active' => $user->is_active,
            ]);
        }

        return response()->json([
            'user_id' => $user->id,
            'roles' => $user->fresh()->roles()->pluck('name')->values()->all(),
        ], 201);
    }

    public function destroyRole(User $user, string $role): JsonResponse
    {
        $roleModel = Role::query()->where('name', $role)->first();

        if (! $roleModel || ! $user->roles()->where('roles.id', $roleModel->id)->exists()) {
            return ApiError::notFound('Role assignment not found.');
        }

        if ($role === 'admin') {
            $adminCount = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'admin'))->count();
            if ($adminCount <= 1) {
                return ApiError::businessRule('Cannot remove the last admin role.');
            }
        }

        $user->roles()->detach($roleModel->id);

        return response()->json([], 204);
    }
}
