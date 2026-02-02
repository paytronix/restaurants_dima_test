<?php

use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\Admin\AvailabilityController;
use App\Http\Controllers\Api\V1\Admin\CalendarController as AdminCalendarController;
use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\CouponController;
use App\Http\Controllers\Api\V1\Admin\DeliveryZoneController;
use App\Http\Controllers\Api\V1\Admin\LeadTimeController;
use App\Http\Controllers\Api\V1\Admin\LocationController as AdminLocationController;
use App\Http\Controllers\Api\V1\Admin\MenuItemController;
use App\Http\Controllers\Api\V1\Admin\ModifierController;
use App\Http\Controllers\Api\V1\Admin\ModifierOptionController;
use App\Http\Controllers\Api\V1\Admin\PickupPointController;
use App\Http\Controllers\Api\V1\Admin\PricingRuleController;
use App\Http\Controllers\Api\V1\Admin\SoldoutController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\Payment\PaymentController;
use App\Http\Controllers\Api\V1\Payment\WebhookController;
use App\Http\Controllers\Api\V1\Promotion\OrderCouponController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

RateLimiter::for('auth', function (Request $request) {
    $limit = (int) config('auth.login_rate_limit', 5);

    return Limit::perMinute($limit)->by($request->ip());
});

RateLimiter::for('password-reset', function (Request $request) {
    $limit = (int) config('auth.forgot_rate_limit', 3);

    return Limit::perHour($limit)->by($request->ip());
});

RateLimiter::for('email-verify', function (Request $request) {
    $limit = (int) config('auth.verify_rate_limit', 3);

    return Limit::perHour($limit)->by($request->user()?->id ?: $request->ip());
});

Route::prefix('v1')->group(function () {
    Route::get('/catalog', [CatalogController::class, 'index']);

    Route::get('/locations', [LocationController::class, 'index']);
    Route::get('/locations/{location}', [LocationController::class, 'show']);
    Route::get('/locations/{location}/pickup-points', [LocationController::class, 'pickupPoints']);
    Route::post('/locations/{location}/delivery/quote', [LocationController::class, 'deliveryQuote']);

    Route::get('/locations/{location}/hours', [CalendarController::class, 'hours']);
    Route::get('/locations/{location}/calendar', [CalendarController::class, 'calendar']);
    Route::get('/locations/{location}/slots', [CalendarController::class, 'slots']);
    Route::post('/locations/{location}/validate-fulfillment', [CalendarController::class, 'validateFulfillment']);

    Route::prefix('auth')->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('/register', [AuthController::class, 'register']);
            Route::post('/login', [AuthController::class, 'login']);
        });

        Route::post('/token/refresh', [AuthController::class, 'refresh']);

        Route::middleware('throttle:password-reset')->group(function () {
            Route::post('/password/forgot', [PasswordResetController::class, 'forgot']);
        });
        Route::post('/password/reset', [PasswordResetController::class, 'reset']);

        Route::post('/email/verify/confirm', [EmailVerificationController::class, 'confirm']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);

            Route::middleware('throttle:email-verify')->group(function () {
                Route::post('/email/verify/request', [EmailVerificationController::class, 'request']);
            });
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [MeController::class, 'show']);
        Route::patch('/me', [MeController::class, 'update']);

        Route::get('/me/addresses', [AddressController::class, 'index']);
        Route::post('/me/addresses', [AddressController::class, 'store']);
        Route::patch('/me/addresses/{id}', [AddressController::class, 'update']);
        Route::delete('/me/addresses/{id}', [AddressController::class, 'destroy']);
        Route::post('/me/addresses/{id}/make-default', [AddressController::class, 'makeDefault']);

        Route::post('/orders/{order}/payments', [PaymentController::class, 'store']);
        Route::get('/orders/{order}/payments/{payment}', [PaymentController::class, 'show']);
        Route::post('/orders/{order}/pay', [PaymentController::class, 'legacyPay']);

        Route::post('/orders/{order}/coupon', [OrderCouponController::class, 'store']);
        Route::delete('/orders/{order}/coupon', [OrderCouponController::class, 'destroy']);
    });

    Route::post('/webhooks/payments/{provider}', [WebhookController::class, 'handle']);

    Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
        Route::apiResource('categories', CategoryController::class);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

        Route::apiResource('menu-items', MenuItemController::class);
        Route::post('menu-items/{item}/modifiers/{modifier}', [MenuItemController::class, 'attachModifier']);
        Route::delete('menu-items/{item}/modifiers/{modifier}', [MenuItemController::class, 'detachModifier']);

        Route::apiResource('modifiers', ModifierController::class);

        Route::post('modifiers/{modifier}/options', [ModifierOptionController::class, 'store']);
        Route::patch('modifiers/{modifier}/options/{option}', [ModifierOptionController::class, 'update']);
        Route::delete('modifiers/{modifier}/options/{option}', [ModifierOptionController::class, 'destroy']);

        Route::post('menu-items/{item}/availabilities', [AvailabilityController::class, 'store']);
        Route::delete('menu-items/{item}/availabilities/{availability}', [AvailabilityController::class, 'destroy']);

        Route::post('menu-items/{item}/soldout', [SoldoutController::class, 'store']);
        Route::delete('menu-items/{item}/soldout/{soldout}', [SoldoutController::class, 'destroy']);

        Route::get('locations', [AdminLocationController::class, 'index']);
        Route::post('locations', [AdminLocationController::class, 'store']);
        Route::get('locations/{location}', [AdminLocationController::class, 'show']);
        Route::patch('locations/{location}', [AdminLocationController::class, 'update']);
        Route::delete('locations/{location}', [AdminLocationController::class, 'destroy']);

        Route::post('locations/{location}/pickup-points', [PickupPointController::class, 'store']);
        Route::patch('pickup-points/{pickupPoint}', [PickupPointController::class, 'update']);
        Route::delete('pickup-points/{pickupPoint}', [PickupPointController::class, 'destroy']);

        Route::post('locations/{location}/delivery-zones', [DeliveryZoneController::class, 'store']);
        Route::patch('delivery-zones/{deliveryZone}', [DeliveryZoneController::class, 'update']);
        Route::delete('delivery-zones/{deliveryZone}', [DeliveryZoneController::class, 'destroy']);

        Route::post('delivery-zones/{zone}/pricing-rules', [PricingRuleController::class, 'store']);
        Route::patch('pricing-rules/{pricingRule}', [PricingRuleController::class, 'update']);
        Route::delete('pricing-rules/{pricingRule}', [PricingRuleController::class, 'destroy']);

        Route::put('locations/{location}/lead-time-settings', [LeadTimeController::class, 'update']);

        Route::get('locations/{location}/hours', [AdminCalendarController::class, 'getHours']);
        Route::put('locations/{location}/hours', [AdminCalendarController::class, 'updateHours']);

        Route::get('locations/{location}/exceptions', [AdminCalendarController::class, 'listExceptions']);
        Route::post('locations/{location}/exceptions', [AdminCalendarController::class, 'storeException']);
        Route::patch('exceptions/{exception}', [AdminCalendarController::class, 'updateException']);
        Route::delete('exceptions/{exception}', [AdminCalendarController::class, 'destroyException']);

        Route::get('locations/{location}/fulfillment-windows', [AdminCalendarController::class, 'getFulfillmentWindows']);
        Route::put('locations/{location}/fulfillment-windows', [AdminCalendarController::class, 'updateFulfillmentWindow']);

        Route::get('coupons', [CouponController::class, 'index']);
        Route::post('coupons', [CouponController::class, 'store']);
        Route::get('coupons/{coupon}', [CouponController::class, 'show']);
        Route::patch('coupons/{coupon}', [CouponController::class, 'update']);
        Route::delete('coupons/{coupon}', [CouponController::class, 'destroy']);
        Route::get('coupons/{coupon}/targets', [CouponController::class, 'listTargets']);
        Route::post('coupons/{coupon}/targets', [CouponController::class, 'storeTarget']);
        Route::delete('coupons/{coupon}/targets/{target}', [CouponController::class, 'destroyTarget']);
    });
});
