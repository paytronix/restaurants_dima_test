<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PricingRule\StorePricingRuleRequest;
use App\Http\Requests\PricingRule\UpdatePricingRuleRequest;
use App\Http\Resources\DeliveryPricingRuleResource;
use App\Models\DeliveryPricingRule;
use App\Models\DeliveryZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PricingRuleController extends Controller
{
    public function store(StorePricingRuleRequest $request, DeliveryZone $zone): JsonResponse
    {
        $validated = $request->validated();

        $existingRule = DeliveryPricingRule::where('delivery_zone_id', $zone->id)->first();

        if ($existingRule !== null) {
            $existingRule->update([
                'fee_amount' => $validated['fee_amount'],
                'min_order_amount' => $validated['min_order_amount'] ?? 0,
                'free_delivery_threshold' => $validated['free_delivery_threshold'] ?? null,
                'currency' => $validated['currency'] ?? config('app.currency', 'PLN'),
            ]);

            return response()->json([
                'data' => new DeliveryPricingRuleResource($existingRule->fresh()),
            ]);
        }

        $pricingRule = DeliveryPricingRule::create([
            'delivery_zone_id' => $zone->id,
            'fee_amount' => $validated['fee_amount'],
            'min_order_amount' => $validated['min_order_amount'] ?? 0,
            'free_delivery_threshold' => $validated['free_delivery_threshold'] ?? null,
            'currency' => $validated['currency'] ?? config('app.currency', 'PLN'),
        ]);

        return response()->json([
            'data' => new DeliveryPricingRuleResource($pricingRule),
        ], 201);
    }

    public function update(UpdatePricingRuleRequest $request, DeliveryPricingRule $pricingRule): JsonResponse
    {
        $validated = $request->validated();

        $pricingRule->update($validated);

        return response()->json([
            'data' => new DeliveryPricingRuleResource($pricingRule->fresh()),
        ]);
    }

    public function destroy(DeliveryPricingRule $pricingRule): Response
    {
        $pricingRule->delete();

        return response()->noContent();
    }
}
