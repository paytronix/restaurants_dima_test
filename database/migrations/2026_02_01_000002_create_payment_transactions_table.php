<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('provider_payment_id', 255)->nullable();
            $table->string('status', 50)->default('pending');
            $table->unsignedInteger('amount');
            $table->string('currency', 3);
            $table->string('idempotency_key_hash', 64);
            $table->text('checkout_url')->nullable();
            $table->string('client_secret', 255)->nullable();
            $table->json('metadata_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['provider', 'provider_payment_id'], 'idx_provider_payment_id');
            $table->index('idempotency_key_hash', 'idx_idempotency_key');
            $table->index('status', 'idx_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
