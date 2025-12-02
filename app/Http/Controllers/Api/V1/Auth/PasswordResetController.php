<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PasswordResetController extends Controller
{
    public function __construct(
        private PasswordResetService $resetService
    ) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->resetService->sendResetEmail($validated['email']);

        return response()->json([
            'data' => [
                'message' => 'If the email exists, a reset link has been sent.',
            ],
            'meta' => [],
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $success = $this->resetService->reset(
            $validated['token'],
            $validated['email'],
            $validated['password']
        );

        if (! $success) {
            throw new BadRequestHttpException('Invalid or expired reset token.');
        }

        return response()->json([
            'data' => [
                'message' => 'Password reset successfully.',
            ],
            'meta' => [],
        ]);
    }
}
