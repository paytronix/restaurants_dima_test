<?php

namespace Tests\Unit\Services;

use App\Services\Payment\Providers\StripePaymentProvider;
use App\Services\Payment\Providers\StubPaymentProvider;
use Illuminate\Http\Request;
use Tests\TestCase;

class WebhookVerificationTest extends TestCase
{
    public function test_stub_provider_always_verifies_webhook(): void
    {
        $provider = new StubPaymentProvider;
        $request = Request::create('/webhook', 'POST', ['event_id' => 'test']);

        $result = $provider->verifyWebhook($request);

        $this->assertTrue($result->valid);
    }

    public function test_stripe_provider_fails_without_signature_header(): void
    {
        config(['payments.stripe.webhook_secret' => 'whsec_test_secret']);

        $provider = new StripePaymentProvider;
        $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');

        $result = $provider->verifyWebhook($request);

        $this->assertFalse($result->valid);
        $this->assertEquals('Missing Stripe-Signature header', $result->errorMessage);
    }

    public function test_stripe_provider_fails_without_webhook_secret_configured(): void
    {
        config(['payments.stripe.webhook_secret' => '']);

        $provider = new StripePaymentProvider;
        $request = Request::create('/webhook', 'POST');
        $request->headers->set('Stripe-Signature', 't=123,v1=abc');

        $result = $provider->verifyWebhook($request);

        $this->assertFalse($result->valid);
        $this->assertEquals('Webhook secret not configured', $result->errorMessage);
    }

    public function test_stripe_provider_fails_with_invalid_signature_format(): void
    {
        config(['payments.stripe.webhook_secret' => 'whsec_test_secret']);

        $provider = new StripePaymentProvider;
        $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');
        $request->headers->set('Stripe-Signature', 'invalid-format');

        $result = $provider->verifyWebhook($request);

        $this->assertFalse($result->valid);
        $this->assertEquals('Invalid signature format', $result->errorMessage);
    }

    public function test_stripe_provider_fails_with_old_timestamp(): void
    {
        config(['payments.stripe.webhook_secret' => 'whsec_test_secret']);

        $provider = new StripePaymentProvider;
        $oldTimestamp = time() - 400;
        $request = Request::create('/webhook', 'POST', [], [], [], [], '{}');
        $request->headers->set('Stripe-Signature', "t={$oldTimestamp},v1=abc123");

        $result = $provider->verifyWebhook($request);

        $this->assertFalse($result->valid);
        $this->assertEquals('Timestamp outside tolerance', $result->errorMessage);
    }

    public function test_stripe_provider_verifies_valid_signature(): void
    {
        $secret = 'whsec_test_secret';
        config(['payments.stripe.webhook_secret' => $secret]);

        $provider = new StripePaymentProvider;
        $payload = '{"type":"payment_intent.succeeded"}';
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
        $request->headers->set('Stripe-Signature', "t={$timestamp},v1={$signature}");

        $result = $provider->verifyWebhook($request);

        $this->assertTrue($result->valid);
    }

    public function test_stripe_provider_fails_with_wrong_signature(): void
    {
        $secret = 'whsec_test_secret';
        config(['payments.stripe.webhook_secret' => $secret]);

        $provider = new StripePaymentProvider;
        $payload = '{"type":"payment_intent.succeeded"}';
        $timestamp = time();

        $request = Request::create('/webhook', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
        $request->headers->set('Stripe-Signature', "t={$timestamp},v1=wrong_signature");

        $result = $provider->verifyWebhook($request);

        $this->assertFalse($result->valid);
        $this->assertEquals('Signature verification failed', $result->errorMessage);
    }
}
