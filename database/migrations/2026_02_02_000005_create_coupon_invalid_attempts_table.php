<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_invalid_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('ip_hash', 64)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('attempt_count')->default(1);
            $table->timestamp('first_attempt_at');
            $table->timestamp('last_attempt_at');
            $table->timestamps();

            $table->index(['ip_hash', 'last_attempt_at']);
            $table->index(['user_id', 'last_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_invalid_attempts');
    }
};
