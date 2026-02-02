<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('fulfillment_type', 20);
            $table->integer('slot_interval_min')->default(15);
            $table->integer('slot_duration_min')->default(15);
            $table->integer('min_lead_time_min')->default(30);
            $table->integer('cutoff_min_before_close')->default(30);
            $table->integer('max_days_ahead')->default(7);
            $table->timestamps();

            $table->unique(['location_id', 'fulfillment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_windows');
    }
};
