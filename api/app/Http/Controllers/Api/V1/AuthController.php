<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            // Uniform error message prevents account enumeration.
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => 'This account has been suspended.',
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken(
            $credentials['device_name'] ?? (string) config('cms.api.token_name', 'cms-dashboard'),
            ['*'],
        );

        return $this->tokenResponse($user, $token->plainTextToken);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        if (! config('cms.site.allow_registration', false)) {
            abort(Response::HTTP_FORBIDDEN, 'Registration is disabled on this site.');
        }

        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->assignRole('subscriber');

        $token = $user->createToken($data['device_name'] ?? 'registration', ['*']);

        return $this->tokenResponse($user, $token->plainTextToken, Response::HTTP_CREATED);
    }

    /**
     * Auth responses are rendered for the account owner, so the email address
     * is included even though the request itself is still unauthenticated.
     */
    protected function tokenResponse(User $user, string $token, int $status = Response::HTTP_OK): JsonResponse
    {
        $payload = (new UserResource($user->load('roles')))->resolve();
        $payload['email'] = $user->email;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $payload,
        ], $status);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles'));
    }

    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->fill($request->validated())->save();

        return new UserResource($user->refresh()->load('roles'));
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->validated('password')])->save();

        // Invalidate every other session for this account.
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()?->id)->delete();

        return response()->json(['message' => 'Password updated.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Signed out of all devices.']);
    }
}
