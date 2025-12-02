<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\AuthTokenResource;
use App\Http\Resources\TokenRefreshResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json([
            'data' => new AuthTokenResource($result),
            'meta' => [
                'email_verification_required' => true,
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->authService->login($validated['email'], $validated['password']);

        return response()->json([
            'data' => new AuthTokenResource($result),
            'meta' => [],
        ]);
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->authService->refresh($validated['refresh_token']);

        return response()->json([
            'data' => new TokenRefreshResource($result),
            'meta' => [],
        ]);
    }

    public function logout(Request $request): Response
    {
        $this->authService->logout($request->user());

        return response()->noContent();
    }
}
