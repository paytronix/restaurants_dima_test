<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\MeResource;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __construct(
        private ProfileService $profileService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->profileService->getProfile($user);

        return response()->json([
            'data' => new MeResource($user->fresh(['profile'])),
            'meta' => [],
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->profileService->updateProfile($user, $request->validated());

        return response()->json([
            'data' => new MeResource($user->fresh(['profile'])),
            'meta' => [],
        ]);
    }
}
