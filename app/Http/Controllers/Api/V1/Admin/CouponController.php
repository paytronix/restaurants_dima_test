<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coupon\StoreCouponRequest;
use App\Http\Requests\Coupon\StoreCouponTargetRequest;
use App\Http\Requests\Coupon\UpdateCouponRequest;
use App\Http\Resources\CouponResource;
use App\Http\Resources\CouponTargetResource;
use App\Models\Coupon;
use App\Models\CouponTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Coupon::query();

        if ($request->has('active')) {
            $isActive = filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        if ($request->has('code')) {
            $code = $request->input('code');
            $query->where('code', 'like', '%'.strtoupper($code).'%');
        }

        if ($request->has('starts_after')) {
            $query->where('starts_at', '>=', $request->input('starts_after'));
        }

        if ($request->has('ends_before')) {
            $query->where('ends_at', '<=', $request->input('ends_before'));
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $coupons = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => CouponResource::collection($coupons),
            'meta' => [
                'current_page' => $coupons->currentPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
                'last_page' => $coupons->lastPage(),
            ],
        ]);
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        $coupon = Coupon::create($data);

        return response()->json([
            'data' => new CouponResource($coupon),
            'meta' => [],
        ], 201);
    }

    public function show(Coupon $coupon): JsonResponse
    {
        return response()->json([
            'data' => new CouponResource($coupon),
            'meta' => [],
        ]);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        $coupon->update($data);

        return response()->json([
            'data' => new CouponResource($coupon->fresh()),
            'meta' => [],
        ]);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        return response()->json(null, 204);
    }

    public function storeTarget(StoreCouponTargetRequest $request, Coupon $coupon): JsonResponse
    {
        $data = $request->validated();

        $existingTarget = CouponTarget::where('coupon_id', $coupon->id)
            ->where('target_type', $data['target_type'])
            ->where('target_id', $data['target_id'])
            ->first();

        if ($existingTarget !== null) {
            return response()->json([
                'title' => 'Conflict',
                'detail' => 'This target is already attached to the coupon',
                'status' => 409,
                'trace_id' => Str::uuid()->toString(),
            ], 409);
        }

        $target = CouponTarget::create([
            'coupon_id' => $coupon->id,
            'target_type' => $data['target_type'],
            'target_id' => $data['target_id'],
        ]);

        return response()->json([
            'data' => new CouponTargetResource($target),
            'meta' => [],
        ], 201);
    }

    public function destroyTarget(Coupon $coupon, int $targetId): JsonResponse
    {
        $target = CouponTarget::where('coupon_id', $coupon->id)
            ->where('id', $targetId)
            ->first();

        if ($target === null) {
            return response()->json([
                'title' => 'Not Found',
                'detail' => 'Target not found',
                'status' => 404,
                'trace_id' => Str::uuid()->toString(),
            ], 404);
        }

        $target->delete();

        return response()->json(null, 204);
    }

    public function listTargets(Coupon $coupon): JsonResponse
    {
        $targets = $coupon->targets()->get();

        return response()->json([
            'data' => CouponTargetResource::collection($targets),
            'meta' => [],
        ]);
    }
}
