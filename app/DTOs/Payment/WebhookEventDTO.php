<?php

namespace App\DTOs\Payment;

readonly class WebhookEventDTO
{
    public function __construct(
        public string $eventId,
        public string $eventType,
        public ?string $providerPaymentId,
        public ?string $status,
        public array $rawPayload,
    ) {}
}
