<?php

namespace App\DTOs\Payment;

readonly class WebhookVerificationResult
{
    public function __construct(
        public bool $valid,
        public ?string $errorMessage = null,
    ) {}

    public static function valid(): self
    {
        return new self(valid: true);
    }

    public static function invalid(string $errorMessage): self
    {
        return new self(valid: false, errorMessage: $errorMessage);
    }
}
