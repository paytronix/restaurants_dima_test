<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('token_hash', 64);
            $table->boolean('revoked')->default(false);
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('replaced_by')->nullable();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('replaced_by')
                ->references('id')
                ->on('refresh_tokens')
                ->onDelete('set null');

            $table->index('token_hash', 'idx_token_hash');
            $table->index(['user_id', 'revoked'], 'idx_user_revoked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
