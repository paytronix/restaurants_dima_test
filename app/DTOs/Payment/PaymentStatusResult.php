<?php

namespace App\DTOs\Payment;

readonly class PaymentStatusResult
{
    public function __construct(
        public bool $success,
        public ?string $status = null,
        public ?string $providerStatus = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public array $rawResponse = [],
    ) {}

    public static function success(string $status, string $providerStatus, array $rawResponse = []): self
    {
        return new self(
            success: true,
            status: $status,
            providerStatus: $providerStatus,
            rawResponse: $rawResponse,
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
