<?php

namespace Tests\Unit\Services;

use App\Models\IdempotencyKey;
use App\Services\Payment\IdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private IdempotencyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IdempotencyService;
    }

    public function test_get_key_hash_returns_consistent_hash(): void
    {
        $key = 'test-idempotency-key';
        $hash1 = $this->service->getKeyHash($key);
        $hash2 = $this->service->getKeyHash($key);

        $this->assertEquals($hash1, $hash2);
        $this->assertEquals(64, strlen($hash1));
    }

    public function test_get_request_hash_returns_consistent_hash(): void
    {
        $data = ['order_id' => 1, 'amount' => 5000];
        $hash1 = $this->service->getRequestHash($data);
        $hash2 = $this->service->getRequestHash($data);

        $this->assertEquals($hash1, $hash2);
    }

    public function test_get_request_hash_is_order_independent(): void
    {
        $data1 = ['order_id' => 1, 'amount' => 5000];
        $data2 = ['amount' => 5000, 'order_id' => 1];

        $hash1 = $this->service->getRequestHash($data1);
        $hash2 = $this->service->getRequestHash($data2);

        $this->assertEquals($hash1, $hash2);
    }

    public function test_check_idempotency_creates_new_record_for_new_key(): void
    {
        $result = $this->service->checkIdempotency(
            'new-key',
            'payment_create',
            ['order_id' => 1]
        );

        $this->assertTrue($result->shouldProceed());
        $this->assertFalse($result->hasCachedResponse());
        $this->assertFalse($result->isConflict());
        $this->assertNotNull($result->record);

        $this->assertDatabaseHas('idempotency_keys', [
            'scope' => 'payment_create',
            'status' => IdempotencyKey::STATUS_PENDING,
        ]);
    }

    public function test_check_idempotency_returns_conflict_for_different_payload(): void
    {
        $keyHash = $this->service->getKeyHash('same-key');
        $requestHash = $this->service->getRequestHash(['order_id' => 1]);

        IdempotencyKey::create([
            'key_hash' => $keyHash,
            'scope' => 'payment_create',
            'request_hash' => $requestHash,
            'status' => IdempotencyKey::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        $result = $this->service->checkIdempotency(
            'same-key',
            'payment_create',
            ['order_id' => 2]
        );

        $this->assertTrue($result->isConflict());
        $this->assertFalse($result->shouldProceed());
    }

    public function test_check_idempotency_returns_cached_response_for_completed_request(): void
    {
        $keyHash = $this->service->getKeyHash('completed-key');
        $requestHash = $this->service->getRequestHash(['order_id' => 1]);

        IdempotencyKey::create([
            'key_hash' => $keyHash,
            'scope' => 'payment_create',
            'request_hash' => $requestHash,
            'status' => IdempotencyKey::STATUS_COMPLETED,
            'response_json' => ['data' => ['id' => 123]],
            'expires_at' => now()->addHours(24),
        ]);

        $result = $this->service->checkIdempotency(
            'completed-key',
            'payment_create',
            ['order_id' => 1]
        );

        $this->assertTrue($result->hasCachedResponse());
        $this->assertEquals(['data' => ['id' => 123]], $result->cachedResponse);
    }

    public function test_check_idempotency_allows_retry_for_pending_request_with_same_payload(): void
    {
        $keyHash = $this->service->getKeyHash('pending-key');
        $requestHash = $this->service->getRequestHash(['order_id' => 1]);

        IdempotencyKey::create([
            'key_hash' => $keyHash,
            'scope' => 'payment_create',
            'request_hash' => $requestHash,
            'status' => IdempotencyKey::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        $result = $this->service->checkIdempotency(
            'pending-key',
            'payment_create',
            ['order_id' => 1]
        );

        $this->assertTrue($result->shouldProceed());
    }

    public function test_expired_keys_are_not_found(): void
    {
        $keyHash = $this->service->getKeyHash('expired-key');
        $requestHash = $this->service->getRequestHash(['order_id' => 1]);

        IdempotencyKey::create([
            'key_hash' => $keyHash,
            'scope' => 'payment_create',
            'request_hash' => $requestHash,
            'status' => IdempotencyKey::STATUS_COMPLETED,
            'response_json' => ['data' => ['id' => 123]],
            'expires_at' => now()->subHour(),
        ]);

        $existing = $this->service->findExisting($keyHash, 'payment_create');

        $this->assertNull($existing);
    }

    public function test_mark_completed_updates_record(): void
    {
        $record = IdempotencyKey::create([
            'key_hash' => 'test-hash',
            'scope' => 'payment_create',
            'request_hash' => 'request-hash',
            'status' => IdempotencyKey::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        $this->service->markCompleted($record, ['data' => ['id' => 456]]);

        $record->refresh();
        $this->assertEquals(IdempotencyKey::STATUS_COMPLETED, $record->status);
        $this->assertEquals(['data' => ['id' => 456]], $record->response_json);
    }

    public function test_cleanup_expired_removes_old_records(): void
    {
        IdempotencyKey::create([
            'key_hash' => 'expired-1',
            'scope' => 'test',
            'request_hash' => 'hash1',
            'status' => IdempotencyKey::STATUS_COMPLETED,
            'expires_at' => now()->subDay(),
        ]);

        IdempotencyKey::create([
            'key_hash' => 'valid-1',
            'scope' => 'test',
            'request_hash' => 'hash2',
            'status' => IdempotencyKey::STATUS_COMPLETED,
            'expires_at' => now()->addDay(),
        ]);

        $deleted = $this->service->cleanupExpired();

        $this->assertEquals(1, $deleted);
        $this->assertDatabaseMissing('idempotency_keys', ['key_hash' => 'expired-1']);
        $this->assertDatabaseHas('idempotency_keys', ['key_hash' => 'valid-1']);
    }
}
