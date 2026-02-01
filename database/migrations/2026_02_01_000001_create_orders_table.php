<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 50)->default('draft');
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->string('currency', 3)->default('PLN');
            $table->timestamps();

            $table->index('status');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
