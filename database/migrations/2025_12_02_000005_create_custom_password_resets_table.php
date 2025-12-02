<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_password_resets', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255);
            $table->string('token', 64);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('email', 'idx_password_resets_email');
            $table->index('token', 'idx_password_resets_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_password_resets');
    }
};
