<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 30);
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->string('fulfillment_type', 20)->nullable();
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->index(['location_id', 'date']);
            $table->index(['location_id', 'date', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_exceptions');
    }
};
