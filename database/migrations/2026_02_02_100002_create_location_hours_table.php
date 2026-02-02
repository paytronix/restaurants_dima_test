<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('day_of_week')->unsigned();
            $table->time('open_time');
            $table->time('close_time');
            $table->string('fulfillment_type', 20);
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(
                ['location_id', 'day_of_week', 'fulfillment_type', 'open_time', 'close_time'],
                'location_hours_unique'
            );
            $table->index(['location_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_hours');
    }
};
