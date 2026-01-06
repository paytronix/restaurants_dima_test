<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('status', 20)->default('active');
            $table->string('address_line1', 255);
            $table->string('address_line2', 255)->nullable();
            $table->string('city', 100);
            $table->string('postal_code', 20);
            $table->char('country', 2)->default('PL');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->text('instructions')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_points');
    }
};
