<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Services\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class EmailVerificationController extends Controller
{
    public function __construct(
        private EmailVerificationService $verificationService
    ) {}

    public function request(Request $request): JsonResponse
    {
        $this->verificationService->sendVerificationEmail($request->user());

        return response()->json([
            'data' => [
                'message' => 'Verification email sent.',
            ],
            'meta' => [],
        ]);
    }

    public function confirm(VerifyEmailRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $success = $this->verificationService->verify($validated['token']);

        if (! $success) {
            throw new BadRequestHttpException('Invalid or expired verification token.');
        }

        return response()->json([
            'data' => [
                'message' => 'Email verified successfully.',
            ],
            'meta' => [],
        ]);
    }
}
