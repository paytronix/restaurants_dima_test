<?php

namespace App\DTOs\Payment;

readonly class PaymentResult
{
    public function __construct(
        public bool $success,
        public ?string $providerPaymentId = null,
        public ?string $checkoutUrl = null,
        public ?string $clientSecret = null,
        public ?string $status = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public array $metadata = [],
    ) {}

    public static function success(
        string $providerPaymentId,
        ?string $checkoutUrl = null,
        ?string $clientSecret = null,
        string $status = 'pending',
        array $metadata = [],
    ): self {
        return new self(
            success: true,
            providerPaymentId: $providerPaymentId,
            checkoutUrl: $checkoutUrl,
            clientSecret: $clientSecret,
            status: $status,
            metadata: $metadata,
        );
    }

    public static function failure(string $errorCode, string $errorMessage): self
    {
        return new self(
            success: false,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
        );
    }
}
