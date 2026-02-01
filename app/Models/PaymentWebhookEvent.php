<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
        'signature_valid',
        'payload_json',
        'received_at',
        'processed_at',
        'processing_error',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'payload_json' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    public function markAsProcessed(): void
    {
        $this->processed_at = now();
        $this->save();
    }

    public function markAsFailed(string $error): void
    {
        $this->processing_error = $error;
        $this->save();
    }

    public function scopeUnprocessed($query)
    {
        return $query->whereNull('processed_at');
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }
}
