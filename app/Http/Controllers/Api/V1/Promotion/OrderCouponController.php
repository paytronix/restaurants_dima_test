<?php

namespace App\Http\Controllers\Api\V1\Promotion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coupon\ApplyCouponRequest;
use App\Http\Resources\OrderWithCouponResource;
use App\Models\Order;
use App\Services\Promotion\AntiAbuseService;
use App\Services\Promotion\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderCouponController extends Controller
{
    public function __construct(
        private PromotionService $promotionService,
        private AntiAbuseService $antiAbuseService,
    ) {}

    public function store(ApplyCouponRequest $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if ($order->user_id !== null && $order->user_id !== $user?->id) {
            return $this->errorResponse('Forbidden', 'You do not have access to this order', 403);
        }

        if (! $order->isDraft()) {
            return $this->errorResponse(
                'Validation Error',
                'Coupon can only be applied to draft orders',
                422,
                ['order' => ['Coupon can only be applied to draft orders']]
            );
        }

        $ipHash = $this->antiAbuseService->hashIp($request->ip());
        $userAgentHash = $this->antiAbuseService->hashUserAgent($request->userAgent());

        $result = $this->promotionService->applyCoupon(
            $order,
            $request->input('code'),
            $user,
            $ipHash,
            $userAgentHash
        );

        if ($result->isRateLimited()) {
            return $this->errorResponse(
                'Too Many Requests',
                $result->getErrorMessage() ?? 'Too many invalid coupon attempts',
                429
            );
        }

        if (! $result->isSuccess()) {
            return $this->errorResponse(
                'Validation Error',
                $result->getErrorMessage() ?? 'This coupon code is not valid',
                422,
                ['code' => [$result->getErrorMessage() ?? 'This coupon code is not valid']]
            );
        }

        $order->refresh();
        $order->load('coupon');

        return response()->json([
            'data' => [
                'order' => new OrderWithCouponResource($order),
            ],
            'meta' => [],
        ]);
    }

    public function destroy(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if ($order->user_id !== null && $order->user_id !== $user?->id) {
            return $this->errorResponse('Forbidden', 'You do not have access to this order', 403);
        }

        $result = $this->promotionService->removeCoupon($order);

        if (! $result->isSuccess()) {
            return $this->errorResponse(
                'Internal Error',
                $result->getErrorMessage() ?? 'Failed to remove coupon',
                500
            );
        }

        $order->refresh();

        return response()->json([
            'data' => [
                'order' => new OrderWithCouponResource($order),
            ],
            'meta' => [],
        ]);
    }

    private function errorResponse(
        string $title,
        string $detail,
        int $status,
        array $errors = []
    ): JsonResponse {
        $response = [
            'title' => $title,
            'detail' => $detail,
            'status' => $status,
            'trace_id' => Str::uuid()->toString(),
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }
}
