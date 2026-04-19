<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Responses\ApiEnvelope;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            email:    $request->string('email')->toString(),
            password: $request->string('password')->toString(),
            ip:       $request->ip() ?? '0.0.0.0',
        );

        return ApiEnvelope::data($result, 200);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $this->authService->logout($user, $request->bearerToken());

        return ApiEnvelope::data(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return ApiEnvelope::data($this->authService->userPayload($user));
    }
}
