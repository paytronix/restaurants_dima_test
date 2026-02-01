<?php

namespace App\Services\Payment;

use App\Models\IdempotencyKey;
use Illuminate\Http\JsonResponse;

class IdempotencyService
{
    private const EXPIRY_HOURS = 24;

    public function getKeyHash(string $key): string
    {
        return hash('sha256', $key);
    }

    public function getRequestHash(array $requestData): string
    {
        ksort($requestData);

        return hash('sha256', json_encode($requestData));
    }

    public function findExisting(string $keyHash, string $scope): ?IdempotencyKey
    {
        return IdempotencyKey::where('key_hash', $keyHash)
            ->where('scope', $scope)
            ->notExpired()
            ->first();
    }

    public function checkIdempotency(
        string $idempotencyKey,
        string $scope,
        array $requestData
    ): IdempotencyCheckResult {
        $keyHash = $this->getKeyHash($idempotencyKey);
        $requestHash = $this->getRequestHash($requestData);

        $existing = $this->findExisting($keyHash, $scope);

        if ($existing === null) {
            $idempotencyRecord = IdempotencyKey::create([
                'key_hash' => $keyHash,
                'scope' => $scope,
                'request_hash' => $requestHash,
                'status' => IdempotencyKey::STATUS_PENDING,
                'expires_at' => now()->addHours(self::EXPIRY_HOURS),
            ]);

            return IdempotencyCheckResult::proceed($idempotencyRecord);
        }

        if ($existing->request_hash !== $requestHash) {
            return IdempotencyCheckResult::conflict();
        }

        if ($existing->isCompleted() && $existing->response_json !== null) {
            return IdempotencyCheckResult::cached($existing->response_json);
        }

        if ($existing->isPending()) {
            return IdempotencyCheckResult::proceed($existing);
        }

        return IdempotencyCheckResult::proceed($existing);
    }

    public function markCompleted(IdempotencyKey $record, array $response): void
    {
        $record->markAsCompleted($response);
    }

    public function markFailed(IdempotencyKey $record): void
    {
        $record->markAsFailed();
    }

    public function cleanupExpired(): int
    {
        return IdempotencyKey::where('expires_at', '<', now())->delete();
    }
}

class IdempotencyCheckResult
{
    private function __construct(
        public readonly string $action,
        public readonly ?IdempotencyKey $record = null,
        public readonly ?array $cachedResponse = null,
    ) {}

    public static function proceed(IdempotencyKey $record): self
    {
        return new self('proceed', $record);
    }

    public static function cached(array $response): self
    {
        return new self('cached', cachedResponse: $response);
    }

    public static function conflict(): self
    {
        return new self('conflict');
    }

    public function shouldProceed(): bool
    {
        return $this->action === 'proceed';
    }

    public function hasCachedResponse(): bool
    {
        return $this->action === 'cached';
    }

    public function isConflict(): bool
    {
        return $this->action === 'conflict';
    }

    public function getConflictResponse(): JsonResponse
    {
        return response()->json([
            'title' => 'Idempotency Conflict',
            'detail' => 'This idempotency key has already been used with different request parameters',
            'status' => 409,
        ], 409);
    }

    public function getCachedJsonResponse(): JsonResponse
    {
        $response = $this->cachedResponse ?? [];
        $statusCode = $response['_status_code'] ?? 200;
        unset($response['_status_code']);

        return response()->json($response, $statusCode);
    }
}
