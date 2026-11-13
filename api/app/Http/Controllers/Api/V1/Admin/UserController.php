<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles')
            ->when($request->filled('search'), fn ($q) => $q->where(function ($query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('username', 'like', $search);
            }))
            ->when($request->filled('role'), fn ($q) => $q->role((string) $request->string('role')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        $data = $request->validated();
        $roles = $data['roles'] ?? ['subscriber'];
        unset($data['roles']);

        $user = DB::transaction(function () use ($data, $roles): User {
            $user = User::create($data);
            $user->syncRoles($roles);

            return $user;
        });

        return response()->json([
            'message' => 'User created.',
            'data' => new UserResource($user->load('roles')),
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        $data = $request->validated();
        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        if (array_key_exists('password', $data) && blank($data['password'])) {
            unset($data['password']);
        }

        DB::transaction(function () use ($user, $data, $roles): void {
            $user->fill($data)->save();

            if (is_array($roles)) {
                $user->syncRoles($roles);
            }
        });

        return response()->json([
            'message' => 'User updated.',
            'data' => new UserResource($user->refresh()->load('roles')),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        Gate::authorize('delete', $user);

        // Tokens are revoked so a deleted account cannot keep acting.
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }
}
