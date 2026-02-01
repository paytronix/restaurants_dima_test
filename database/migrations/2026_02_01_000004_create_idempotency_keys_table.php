<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key_hash', 64);
            $table->string('scope', 100);
            $table->string('request_hash', 64);
            $table->json('response_json')->nullable();
            $table->string('status', 50)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['key_hash', 'scope'], 'idx_key_scope');
            $table->index('expires_at', 'idx_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
