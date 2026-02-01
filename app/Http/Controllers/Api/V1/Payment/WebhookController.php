<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentProviderFactory;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private PaymentProviderFactory $providerFactory,
    ) {}

    public function handle(Request $request, string $provider): JsonResponse
    {
        $supportedProviders = $this->providerFactory->getSupportedProviders();

        if (! in_array($provider, $supportedProviders, true)) {
            return response()->json([
                'title' => 'Not Found',
                'detail' => 'Unknown payment provider',
                'status' => 404,
            ], 404);
        }

        try {
            $paymentProvider = $this->providerFactory->make($provider);

            $verificationResult = $paymentProvider->verifyWebhook($request);

            if (! $verificationResult->valid) {
                Log::warning('Webhook signature verification failed', [
                    'provider' => $provider,
                    'error' => $verificationResult->errorMessage,
                ]);

                return response()->json([
                    'title' => 'Bad Request',
                    'detail' => $verificationResult->errorMessage ?? 'Invalid webhook signature',
                    'status' => 400,
                ], 400);
            }

            $webhookEvent = $paymentProvider->parseWebhook($request);

            $result = $this->paymentService->processWebhook($provider, [
                'eventId' => $webhookEvent->eventId,
                'eventType' => $webhookEvent->eventType,
                'providerPaymentId' => $webhookEvent->providerPaymentId,
                'status' => $webhookEvent->status,
                'rawPayload' => $webhookEvent->rawPayload,
            ]);

            Log::info('Webhook processed', [
                'provider' => $provider,
                'event_id' => $webhookEvent->eventId,
                'event_type' => $webhookEvent->eventType,
                'is_duplicate' => $result->isDuplicate,
                'message' => $result->message,
            ]);

            return response()->json([
                'data' => [
                    'received' => true,
                ],
                'meta' => [],
            ]);

        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'data' => [
                    'received' => true,
                ],
                'meta' => [],
            ]);
        }
    }
}
