<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Services\Payment\Providers\Przelewy24PaymentProvider;
use App\Services\Payment\Providers\StripePaymentProvider;
use App\Services\Payment\Providers\StubPaymentProvider;
use InvalidArgumentException;

class PaymentProviderFactory
{
    private array $providers = [];

    public function __construct()
    {
        $this->providers = [
            'stub' => StubPaymentProvider::class,
            'stripe' => StripePaymentProvider::class,
            'p24' => Przelewy24PaymentProvider::class,
        ];
    }

    public function make(?string $provider = null): PaymentProviderInterface
    {
        $provider = $provider ?? config('payments.default', 'stub');

        if (! isset($this->providers[$provider])) {
            throw new InvalidArgumentException("Unknown payment provider: {$provider}");
        }

        return app($this->providers[$provider]);
    }

    public function getDefault(): PaymentProviderInterface
    {
        return $this->make();
    }

    public function getSupportedProviders(): array
    {
        return array_keys($this->providers);
    }
}
